<section class="card mb-4" id="client-process-groups" aria-labelledby="client-process-groups-title">
    <div class="card-body">
        <h2 id="client-process-groups-title" class="h5">Procesos del solicitante</h2>
        <p class="text-muted">Cada proceso reúne su negocio de HubSpot y su proyecto de Teamleader. El saldo de una misma fase se cuenta una sola vez.</p>
        @if(session('process_association_success'))<div class="alert alert-success" role="status">{{ session('process_association_success') }}</div>@endif
        @if($errors->has('teamleader_id') || $errors->has('deal_id'))<div class="alert alert-danger">{{ $errors->first('teamleader_id') ?: $errors->first('deal_id') }}</div>@endif
        @if(count($processGrouping['proposals']) > 0)
            <form method="POST" action="{{ route('crud.users.process-associations', $user) }}" class="mb-3">
                @csrf
                <input type="hidden" name="mode" value="automatic">
                <button class="btn btn-outline-primary">Asociar {{ count($processGrouping['proposals']) }} coincidencia(s) clara(s) sin IA</button>
                <div class="small text-muted mt-1">Las coincidencias claras ya se presentan agrupadas. Guardar conserva su asociación para las siguientes consultas.</div>
            </form>
        @endif
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Proceso</th><th>Negocio HubSpot</th><th>Proyecto Teamleader</th><th>Agrupación</th></tr></thead>
                <tbody>
                    @forelse($processGrouping['groups'] as $group)
                        <tr>
                            <td>{{ $group['title'] }}</td>
                            <td>@forelse($group['deals'] as $deal)<div>{{ $deal['title'] }} <span class="small text-muted">{{ $deal['hubspot_id'] ? '· '.$deal['hubspot_id'] : '' }}</span></div>@empty<span class="text-muted">Sin negocio asociado</span>@endforelse</td>
                            <td>@forelse($group['projects'] as $project)<div>{{ $project['title'] }}</div>@empty<span class="text-muted">Sin proyecto asociado</span>@endforelse</td>
                            <td>
                                @if(count($group['deals']) && count($group['projects']))
                                    <span class="badge {{ $group['proposed'] ? 'bg-info' : 'bg-success' }}">{{ $group['proposed'] ? 'Coincidencia clara' : 'Agrupado' }}</span>
                                @else<span class="badge bg-secondary">Pendiente de asociación</span>@endif
                            </td>
                        </tr>
                    @empty<tr><td colspan="4" class="text-muted">No hay procesos registrados.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        @if(count($processGrouping['unlinked_deals']) && $processGrouping['available_projects']->isNotEmpty())
            <details class="mt-2">
                <summary>Asociar un proceso manualmente</summary>
                <form method="POST" action="{{ route('crud.users.process-associations', $user) }}" class="row g-2 align-items-end mt-1">
                    @csrf
                    <input type="hidden" name="mode" value="manual">
                    <div class="col-md-5"><label for="process-deal" class="form-label">Negocio HubSpot</label><select id="process-deal" name="deal_id" class="form-select" required>
                        <option value="">Seleccionar negocio</option>
                        @foreach($processGrouping['unlinked_deals'] as $deal)
                            @if(!empty($deal['hubspot_id']))<option value="{{ $deal['id'] }}">{{ $deal['dealname'] ?? $deal['hubspot_id'] }}</option>@endif
                        @endforeach
                    </select></div>
                    <div class="col-md-5"><label for="process-project" class="form-label">Proyecto Teamleader</label><select id="process-project" name="teamleader_id" class="form-select" required>
                        <option value="">Seleccionar proyecto</option>
                        @foreach($processGrouping['available_projects'] as $project)<option value="{{ $project->id }}">{{ $project->title }}</option>@endforeach
                    </select></div>
                    <div class="col-md-2"><button class="btn btn-primary w-100">Agrupar</button></div>
                </form>
            </details>
        @endif
    </div>
</section>
