<?php

namespace App\Http\Controllers;

use App\Models\ConsultationCalendar;
use App\Models\Servicio;
use Illuminate\Http\Request;

class AdminConsultationCalendarController extends Controller
{
    public function index()
    {
        $calendars = ConsultationCalendar::with(['servicio', 'availabilityRules'])
            ->orderByDesc('created_at')
            ->get();

        return view('admin.consultation_calendars.index', compact('calendars'));
    }

    public function create()
    {
        return view('admin.consultation_calendars.create', [
            'calendar' => null,
            'servicios' => $this->serviceOptions(),
            'weekdays' => $this->weekdays(),
        ]);
    }

    public function store(Request $request)
    {
        $calendar = ConsultationCalendar::create($this->validatedData($request));
        $this->syncAvailability($calendar, $request);

        return redirect()
            ->route('admin.consultation-calendars.index')
            ->with('success', 'Calendario creado.');
    }

    public function edit(ConsultationCalendar $consultation_calendar)
    {
        $consultation_calendar->load('availabilityRules');

        return view('admin.consultation_calendars.edit', [
            'calendar' => $consultation_calendar,
            'servicios' => $this->serviceOptions(),
            'weekdays' => $this->weekdays(),
        ]);
    }

    public function update(Request $request, ConsultationCalendar $consultation_calendar)
    {
        $consultation_calendar->update($this->validatedData($request));
        $this->syncAvailability($consultation_calendar, $request);

        return redirect()
            ->route('admin.consultation-calendars.index')
            ->with('success', 'Calendario actualizado.');
    }

    public function destroy(ConsultationCalendar $consultation_calendar)
    {
        $consultation_calendar->delete();

        return redirect()
            ->route('admin.consultation-calendars.index')
            ->with('success', 'Calendario eliminado.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'servicio_id' => ['nullable', 'exists:servicios,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'timezone' => ['required', 'string', 'max:64'],
            'slot_duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $data['activo'] = $request->boolean('activo');
        $data['buffer_minutes'] = (int) ($data['buffer_minutes'] ?? 0);

        return $data;
    }

    private function syncAvailability(ConsultationCalendar $calendar, Request $request): void
    {
        $calendar->availabilityRules()->delete();

        foreach ($request->input('availability', []) as $weekday => $rule) {
            if (empty($rule['enabled']) || empty($rule['starts_at']) || empty($rule['ends_at'])) {
                continue;
            }

            if ($rule['starts_at'] >= $rule['ends_at']) {
                continue;
            }

            $calendar->availabilityRules()->create([
                'weekday' => (int) $weekday,
                'starts_at' => $rule['starts_at'],
                'ends_at' => $rule['ends_at'],
                'slot_duration_minutes' => $rule['slot_duration_minutes'] ?? null,
                'buffer_minutes' => (int) ($rule['buffer_minutes'] ?? 0),
                'activo' => true,
            ]);
        }
    }

    private function serviceOptions()
    {
        return Servicio::orderBy('nombre')->get(['id', 'nombre', 'id_hubspot']);
    }

    private function weekdays(): array
    {
        return [
            0 => 'Domingo',
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miercoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sabado',
        ];
    }
}
