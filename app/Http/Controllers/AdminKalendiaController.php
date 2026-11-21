<?php
namespace App\Http\Controllers;
use App\Models\{KalendiaAppointment, KalendiaAppointmentAction, KalendiaConsultationOffer, User};
use App\Services\{KalendiaApi, KalendiaApiException, KalendiaAppointmentService};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class AdminKalendiaController extends Controller
{
    public function offers(KalendiaApi $api)
    {
        $services = []; $apiUnavailable = false;
        try { $services = $api->services(); } catch (KalendiaApiException) { $apiUnavailable = true; }
        $offers = KalendiaConsultationOffer::orderBy('type')->orderBy('name')->get();
        return view('kalendia.admin.offers', compact('offers', 'services', 'apiUnavailable'));
    }
    public function saveOffer(Request $request, KalendiaApi $api, ?KalendiaConsultationOffer $offer = null)
    {
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
        $request->validate(['client' => ['nullable','integer','exists:users,id'], 'status' => ['nullable','in:confirmed,pending,cancelled,completed']]);
        $client = $request->filled('client') ? User::findOrFail($request->integer('client')) : null;
        $appointments = KalendiaAppointment::with('user')->when($client, fn ($q) => $q->where('user_id', $client->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->orderByDesc('starts_at')->paginate(30)->withQueryString();
        return response()->view('kalendia.admin.appointments', compact('appointments', 'client'))->header('Cache-Control', 'private, no-store');
    }
    public function refresh(KalendiaAppointmentService $service)
    {
        try { $count = $service->syncPage(); }
        catch (KalendiaApiException) { return back()->withErrors(['kalendia' => 'No se pudo consultar Kalendia. Se conserva la información anterior.']); }
        return back()->with('success', 'Sincronización completada: '.$count.' citas consultadas.');
    }
    public function show(KalendiaAppointment $appointment)
    {
        $operation = KalendiaAppointmentAction::where('appointment_id', $appointment->id)->whereIn('status', ['processing','uncertain'])->latest()->first();
        return response()->view('kalendia.admin.appointment', ['appointment' => $appointment, 'operation' => $operation,
            'operationId' => $operation?->uuid ?: (string) Str::uuid()])->header('Cache-Control', 'private, no-store');
    }
    public function change(Request $request, KalendiaAppointment $appointment, KalendiaAppointmentService $service)
    {
        $data = $request->validate(['operation_id' => ['required','uuid'], 'action' => ['required','in:reschedule,cancel'],
            'timezone' => ['bail','nullable','string','timezone:all'],
            'date' => ['required_if:action,reschedule','nullable','date_format:Y-m-d'], 'time' => ['required_if:action,reschedule','nullable','date_format:H:i']]);
        $service->change($appointment, $request->user(), $data['operation_id'], $data['action'], $data);
        return redirect()->route('admin.kalendia.appointment', $appointment)->with('success', 'Cita actualizada en Kalendia y en el COS.');
    }
}
