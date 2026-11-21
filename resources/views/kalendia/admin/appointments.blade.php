@extends('adminlte::page')
@section('title', 'Citas y enlaces')
@section('content_header')<h1>Kalendia · Citas y enlaces</h1>@stop
@section('css')
    <link rel="stylesheet" href="{{ asset('css/kalendia-calendar.css') }}?v={{ filemtime(public_path('css/kalendia-calendar.css')) }}">
@stop
@section('content')
    @include('kalendia.partials.messages')
    <div class="card"><div class="card-body">
        @include('kalendia.partials.timezone')
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3"><h2 class="h5">{{ $client ? 'Reuniones de '.$client->name : 'Reuniones de clientes' }}</h2>
            <form method="POST" action="{{ route('admin.kalendia.refresh') }}">@csrf<button class="btn btn-outline-primary" type="submit"><i class="fas fa-sync-alt mr-1" aria-hidden="true"></i>Actualizar desde Kalendia</button></form>
        </div>
        <form method="GET" class="row mb-3" id="kalendia-calendar-filters">
            @if($client)<input type="hidden" name="client" value="{{ $client->id }}">@endif
            <input type="hidden" name="date" value="{{ request('date') }}" data-calendar-date>
            <input type="hidden" name="view" value="{{ request('view', 'dayGridMonth') }}" data-calendar-view>
            <div class="col-sm-5"><label for="appointment-status">Estado</label><select class="form-control" id="appointment-status" name="status"><option value="">Todos</option>@foreach(['confirmed'=>'Confirmadas','pending'=>'Pendientes','cancelled'=>'Canceladas','completed'=>'Completadas','no_show'=>'No asistieron'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-sm-3 align-self-end mt-2"><button class="btn btn-primary">Filtrar</button></div>
        </form>
        <section class="kalendia-agenda" aria-label="Calendario de reuniones">
            <div class="kalendia-agenda-toolbar">
                <div class="kalendia-agenda-navigation">
                    <div class="btn-group" role="group" aria-label="Navegar por el calendario">
                        <button type="button" class="btn btn-outline-secondary" data-calendar-action="prev" aria-label="Periodo anterior"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-calendar-action="today">Hoy</button>
                        <button type="button" class="btn btn-outline-secondary" data-calendar-action="next" aria-label="Periodo siguiente"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                    </div>
                    <label class="mb-0" for="kalendia-calendar-date"><span class="sr-only">Ir a una fecha</span><input type="date" id="kalendia-calendar-date" class="form-control" aria-label="Ir a una fecha"></label>
                </div>
                <div class="btn-group kalendia-agenda-views" role="group" aria-label="Vista del calendario">
                    @foreach(['dayGridMonth'=>'Mes','timeGridWeek'=>'Semana','timeGridDay'=>'Día','listMonth'=>'Lista'] as $view=>$label)
                        <button type="button" class="btn btn-outline-primary" data-calendar-view-button="{{ $view }}" aria-pressed="false">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <h3 class="h4 kalendia-agenda-title" id="kalendia-calendar-title" aria-live="polite"></h3>
            <div class="kalendia-agenda-legend" aria-label="Estados de las citas">
                @foreach(['confirmed'=>'Confirmada','pending'=>'Pendiente','completed'=>'Completada','cancelled'=>'Cancelada','no_show'=>'No asistió'] as $status=>$label)
                    <span><i class="kalendia-agenda-dot kalendia-status-{{ $status }}" aria-hidden="true"></i>{{ $label }}</span>
                @endforeach
            </div>
            <p class="text-muted small mb-2" id="kalendia-calendar-summary" role="status">Cargando calendario…</p>
            <div class="alert alert-warning" id="kalendia-calendar-error" role="alert" hidden></div>
            <div class="kalendia-agenda-viewport" tabindex="0" aria-label="Calendario; desplázate horizontalmente en la vista semanal si es necesario">
                <div id="kalendia-calendar" data-events-url="{{ route('admin.kalendia.events') }}"
                    data-initial-date="{{ request('date') ?: now(\App\Services\KalendiaTimezone::preferred())->format('Y-m-d') }}"
                    data-initial-view="{{ request('view', 'dayGridMonth') }}"></div>
            </div>
            <p class="text-muted small mt-3 mb-0">Pulsa una cita para gestionarla y abrir su enlace de reunión. Los cambios de Kalendia se consultan automáticamente cada cinco minutos.</p>
        </section>
        <details class="mt-4" @if(request('page')) open @endif>
            <summary class="text-muted mb-3">Historial completo en tabla</summary>
            @include('kalendia.partials.appointments', ['showClient' => true])
            {{ $appointments->links() }}
        </details>
    </div></div>
@stop
@section('js')
    <script src="{{ asset('vendor/fullcalendar/luxon.min.js') }}" defer></script>
    <script src="{{ asset('vendor/fullcalendar/index.global.min.js') }}" defer></script>
    <script src="{{ asset('vendor/fullcalendar/luxon3.global.min.js') }}" defer></script>
    <script src="{{ asset('vendor/fullcalendar/es.global.min.js') }}" defer></script>
    <script src="{{ asset('js/kalendia-calendar.js') }}?v={{ filemtime(public_path('js/kalendia-calendar.js')) }}" defer></script>
@stop
