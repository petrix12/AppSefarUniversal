<div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead><tr>@if($showClient ?? false)<th>Cliente</th>@endif<th>Servicio</th><th>Fecha y hora local</th><th>Profesional</th><th>Estado</th><th>Reunión</th></tr></thead>
        <tbody>
        @forelse($appointments as $item)
            <tr>
                @if($showClient ?? false)<td>@if($item->user)<a href="{{ route('crud.users.edit', $item->user_id) }}">{{ $item->user->name }}</a>@else{{ data_get($item->customer, 'name') ?: 'Contacto de Kalendia' }}@endif</td>@endif
                <td>{{ $item->service_name ?: 'Reunión' }}</td>
                <td><time data-kalendia-instant="{{ $item->starts_at->toIso8601String() }}">{{ $item->starts_at->timezone(\App\Services\KalendiaTimezone::preferred())->format('d/m/Y H:i') }}</time><br><small class="text-muted">Hasta <time data-kalendia-instant="{{ $item->ends_at->toIso8601String() }}">{{ $item->ends_at->timezone(\App\Services\KalendiaTimezone::preferred())->format('d/m/Y H:i') }}</time></small></td>
                <td>{{ $item->staff_name ?: 'Equipo Sefar' }}</td>
                <td><span class="badge badge-{{ $item->status === 'cancelled' ? 'secondary' : 'info' }} bg-{{ $item->status === 'cancelled' ? 'secondary' : 'info' }}">{{ $item->display_status }}</span></td>
                <td>
                    @if($item->meeting_url && $item->status !== 'cancelled' && !($showClient ?? false))<a class="btn btn-sm btn-outline-primary" href="{{ $item->meeting_url }}" target="_blank" rel="noopener noreferrer">Abrir reunión <i class="fas fa-external-link-alt" aria-hidden="true"></i></a>@endif
                    @if($showClient ?? false)<a class="btn btn-sm btn-outline-secondary mt-1" href="{{ route('admin.kalendia.appointment', $item) }}" data-manage-kalendia-appointment>Gestionar</a>@endif
                    @unless($item->meeting_url || ($showClient ?? false))<span class="text-muted">Enlace pendiente</span>@endunless
                </td>
            </tr>
        @empty<tr><td colspan="{{ ($showClient ?? false) ? 6 : 5 }}" class="text-muted">No hay reuniones registradas.</td></tr>@endforelse
        </tbody>
    </table>
</div>
