<?php
namespace App\Http\Controllers;
use App\Models\{KalendiaAppointment, KalendiaAppointmentAction, KalendiaConsultationOffer, User};
use App\Services\{KalendiaApi, KalendiaApiException, KalendiaAppointmentService, KalendiaConsultationCatalog};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class AdminKalendiaController extends Controller
{
    public function offers(KalendiaConsultationCatalog $catalog)
    {
        $services = []; $apiUnavailable = false;
        try { $services = $catalog->sync()['services']; } catch (KalendiaApiException) { $apiUnavailable = true; }
        $offers = KalendiaConsultationOffer::orderBy('type')->orderBy('name')->get();
        return view('kalendia.admin.offers', compact('offers', 'services', 'apiUnavailable'));
    }
    public function syncOffers(KalendiaConsultationCatalog $catalog)
    {
        try { $result = $catalog->sync(true); }
        catch (KalendiaApiException) { return back()->withErrors(['kalendia' => 'No se pudo actualizar el catálogo de Kalendia. Se conservan los precios guardados.']); }
        return redirect()->route('admin.kalendia.offers')->with('success', $result['available'].' servicios con precio sincronizados desde Kalendia.');
    }
    public function saveOffer(Request $request, KalendiaApi $api, ?KalendiaConsultationOffer $offer = null)
    {
        if ($offer?->catalog_service_id) {
            $data = $request->validate(['type' => ['required','string','max:100'], 'active' => ['required','boolean']]);
            $offer->update($data);
            return redirect()->route('admin.kalendia.offers')->with('success', 'Visibilidad de la consulta actualizada. El nombre y el precio se mantienen sincronizados con Kalendia.');
        }
        $data = $request->validate(['name' => ['required','string','max:255'], 'type' => ['required','string','max:100'],
            'description' => ['nullable','string','max:3000'], 'remote_service_id' => ['required','regex:/^svc_[A-Za-z0-9_-]+$/D'],
            'price' => ['nullable','numeric','min:0','max:99999999.99','decimal:0,2'], 'active' => ['nullable','boolean']]);
        try { $remote = $api->serviceById($data['remote_service_id']); }
        catch (KalendiaApiException) { return back()->withInput()->withErrors(['remote_service_id' => 'No se pudo comprobar el servicio en Kalendia.']); }
        if (! $remote) return back()->withInput()->withErrors(['remote_service_id' => 'Selecciona un servicio existente en Kalendia.']);
        ($offer ?? new KalendiaConsultationOffer)->forceFill($data + ['currency' => 'EUR'])->forceFill(['active' => $request->boolean('active')])->save();
        return redirect()->route('admin.kalendia.offers')->with('success', 'Consulta guardada. Sin precio positivo, la contratación permanece deshabilitada.');
    }
    public function appointments(Request $request, KalendiaAppointmentService $service)
    {
        $this->validateAppointmentFilters($request);
        $request->validate(['date' => ['nullable','date_format:Y-m-d'], 'view' => ['nullable','in:dayGridMonth,timeGridWeek,timeGridDay,listMonth'],
            'appointment' => ['nullable','integer','exists:kalendia_appointments,id']]);
        $client = $request->filled('client') ? User::findOrFail($request->integer('client')) : null;
        $appointments = KalendiaAppointment::with('user')->when($client, fn ($q) => $q->where('user_id', $client->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->orderByDesc('starts_at')->paginate(30)->withQueryString();
        return response()->view('kalendia.admin.appointments', compact('appointments', 'client'))->header('Cache-Control', 'private, no-store');
    }
    public function events(Request $request)
    {
        $this->validateAppointmentFilters($request);
        $data = $request->validate(['start' => ['required','string','max:64','date'],
            'end' => ['required','string','max:64','date'], 'timeZone' => ['nullable','string','timezone:all']]);
        $start = CarbonImmutable::parse($data['start'])->utc();
        $end = CarbonImmutable::parse($data['end'])->utc();
        abort_if($end <= $start || $start->diffInSeconds($end) > 62 * 86400, 422, 'Selecciona un intervalo de hasta dos meses.');

        // The calendar reads the local mirror only. Its visible range must not be paginated.
        $appointments = KalendiaAppointment::with('user:id,name')
            ->when($request->filled('client'), fn ($q) => $q->where('user_id', $request->integer('client')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->where('starts_at', '<', $end->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $start->format('Y-m-d H:i:s'))
            ->orderBy('starts_at')->limit(2001)->get();
        abort_if($appointments->count() > 2000, 422, 'Hay demasiadas citas. Usa la vista diaria o filtra por cliente o estado.');

        return response()->json($appointments->map(function (KalendiaAppointment $appointment) {
            $name = $appointment->user?->name ?: data_get($appointment->customer, 'name') ?: 'Contacto de Kalendia';
            return ['id' => (string) $appointment->id, 'title' => $name,
                'start' => $appointment->starts_at->toIso8601String(), 'end' => $appointment->ends_at->toIso8601String(),
                'url' => route('admin.kalendia.appointment', $appointment),
                'extendedProps' => ['service' => $appointment->service_name ?: 'Reunión',
                    'staff' => $appointment->staff_name ?: 'Equipo Sefar',
                    'status' => $appointment->status, 'statusLabel' => $appointment->display_status]];
        }))->header('Cache-Control', 'private, no-store');
    }
    private function validateAppointmentFilters(Request $request): void
    {
        $request->validate(['client' => ['nullable','integer','exists:users,id'],
            'status' => ['nullable','in:confirmed,pending,cancelled,completed,no_show']]);
    }
    public function refresh(KalendiaAppointmentService $service)
    {
        try { $count = $service->syncPage(); }
        catch (KalendiaApiException) { return back()->withErrors(['kalendia' => 'No se pudo consultar Kalendia. Se conserva la información anterior.']); }
        return back()->with('success', 'Sincronización completada: '.$count.' citas consultadas.');
    }
    private function appointmentData(KalendiaAppointment $appointment): array
    {
        $operation = KalendiaAppointmentAction::where('appointment_id', $appointment->id)->whereIn('status', ['processing','uncertain'])->latest()->first();
        return ['appointment' => $appointment, 'operation' => $operation, 'operationId' => $operation?->uuid ?: (string) Str::uuid()];
    }
    private function appointmentFragment(KalendiaAppointment $appointment): string
    {
        return view('kalendia.admin.partials.appointment-details', $this->appointmentData($appointment))->render();
    }
    public function show(Request $request, KalendiaAppointment $appointment)
    {
        if ($request->expectsJson()) return response()->json(['html' => $this->appointmentFragment($appointment)])
            ->header('Cache-Control', 'private, no-store');
        return redirect()->route('admin.kalendia.appointments', ['appointment' => $appointment->id,
            'date' => $appointment->starts_at->timezone(\App\Services\KalendiaTimezone::preferred())->format('Y-m-d')]);
    }
    public function change(Request $request, KalendiaAppointment $appointment, KalendiaAppointmentService $service)
    {
        $data = $request->validate(['operation_id' => ['required','uuid'], 'action' => ['required','in:reschedule,cancel'],
            'timezone' => ['bail','nullable','string','timezone:all'],
            'date' => ['required_if:action,reschedule','nullable','date_format:Y-m-d'], 'time' => ['required_if:action,reschedule','nullable','date_format:H:i']]);
        try { $service->change($appointment, $request->user(), $data['operation_id'], $data['action'], $data); }
        catch (ValidationException $error) {
            if (! $request->expectsJson()) throw $error;
            return response()->json(['message' => 'No se pudo confirmar el cambio.', 'errors' => $error->errors(),
                'html' => $this->appointmentFragment($appointment->fresh())], 422)->header('Cache-Control', 'private, no-store');
        }
        if ($request->expectsJson()) return response()->json(['message' => 'Cita actualizada en Kalendia y en el COS.',
            'html' => $this->appointmentFragment($appointment->fresh())])->header('Cache-Control', 'private, no-store');
        return redirect()->route('admin.kalendia.appointment', $appointment)->with('success', 'Cita actualizada en Kalendia y en el COS.');
    }
}
