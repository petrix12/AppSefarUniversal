@once
    @php($viewerTimezone = \App\Services\KalendiaTimezone::preferred())
    <div class="form-group mb-3" data-kalendia-timezone-controls data-preference-url="{{ route('kalendia.timezone') }}">
        <label for="kalendia-timezone">Zona horaria de los horarios mostrados</label>
        <select id="kalendia-timezone" class="form-control" style="max-width:30rem" data-kalendia-timezone>
            <option value="auto">Detectar automáticamente</option>
            @foreach(['Europe/Madrid'=>'España peninsular','Atlantic/Canary'=>'España · Canarias','America/Santiago'=>'Chile · Santiago',
                'America/Punta_Arenas'=>'Chile · Magallanes','Pacific/Easter'=>'Chile · Rapa Nui','America/Caracas'=>'Venezuela','America/Bogota'=>'Colombia'] as $zone=>$label)
                <option value="{{ $zone }}">{{ $label }} · {{ $zone }}</option>
            @endforeach
        </select>
        <small class="form-text text-muted">Horas en <strong data-kalendia-timezone-label>{{ $viewerTimezone }}</strong>. {{ $timezoneContextHelp ?? 'La fecha de la reunión determina el horario de verano.' }}</small>
    </div>
    <script src="{{ asset('js/kalendia-timezone.js') }}?v={{ filemtime(public_path('js/kalendia-timezone.js')) }}" defer></script>
@endonce
