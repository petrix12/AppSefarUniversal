@php
    $passportHistories = \Illuminate\Support\Facades\Schema::hasTable('user_passport_histories')
        ? $user->passportHistories()->with('changedBy:id,name')->get()
        : collect();
@endphp
<section class="mb-4" aria-labelledby="passport-history-title">
    <h3 id="passport-history-title" class="h5">Histórico de pasaportes</h3>
    <p class="text-muted">Los números anteriores se conservan cuando se actualiza el pasaporte.</p>
    @if($passportHistories->isEmpty())
        <p class="text-muted">No hay cambios de pasaporte registrados.</p>
    @else
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead><tr>
                    <th scope="col">Pasaporte anterior</th>
                    <th scope="col">Pasaporte actualizado</th>
                    <th scope="col">Fecha del cambio</th>
                    @if($canEditCos ?? false)<th scope="col">Actualizado por</th>@endif
                </tr></thead>
                <tbody>
                    @foreach($passportHistories as $history)
                        <tr>
                            <td>{{ $history->passport }}</td>
                            <td>{{ $history->replacement_passport ?: 'Sin número' }}</td>
                            <td>{{ $history->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                            @if($canEditCos ?? false)<td>{{ $history->changedBy?->name ?? 'Sistema' }}</td>@endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
