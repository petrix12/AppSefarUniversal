@extends('adminlte::page')

@section('title', trim(($user->nombres ?? '') . ' ' . ($user->apellidos ?? '')) ?: $user->name)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>
            <a href="{{ route('crud.users.index') }}" class="btn btn-sm btn-secondary mr-2">
                <i class="fas fa-arrow-left"></i>
            </a>
            {{ trim(($user->nombres ?? '') . ' ' . ($user->apellidos ?? '')) ?: $user->name ?: '(Sin nombre)' }}
        </h1>
        <div class="d-flex flex-wrap" style="gap:.4rem">
            <a href="{{ route('crud.users.edit', $user) }}" class="btn btn-sm btn-primary">
                <i class="fas fa-edit mr-1"></i> Abrir COS
            </a>
            @if($user->passport || $user->genealogy_tree_id)
                <a href="{{ route('arboles.tree.user', $user) }}" class="btn btn-sm btn-outline-success" target="_blank">
                    <i class="fas fa-project-diagram mr-1"></i> Ver árbol
                </a>
            @endif
        </div>
    </div>
@endsection

@section('content')
@php
    $displayName = trim(($user->nombres ?? '') . ' ' . ($user->apellidos ?? '')) ?: $user->name;
    $primaryPhone = $user->phone ?: $user->numero_de_telefono ?? null;
    $attributes = collect($user->getAttributes())
        ->except(['id', 'password', 'password_md5', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'profile_photo_path', 'created_at', 'updated_at']);
@endphp

<div class="row">
    <div class="col-md-4">
        <div class="card card-primary card-outline">
            <div class="card-body text-center">
                <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px">
                    <span class="text-white font-weight-bold" style="font-size:2rem">{{ strtoupper(substr($displayName ?: '?', 0, 1)) }}</span>
                </div>
                <h4 class="mb-0">{{ $displayName ?: '(Sin nombre)' }}</h4>
                <small class="text-muted">ID interno: <code>{{ $user->id }}</code></small>
                <div class="mt-2">
                    @if($user->hasRole('Cliente'))
                        <span class="badge badge-success">Cliente</span>
                    @else
                        <span class="badge badge-secondary">Usuario interno</span>
                    @endif
                </div>
            </div>
            <div class="card-footer p-0">
                <ul class="list-group list-group-flush">
                    @if($user->email)
                    <li class="list-group-item"><i class="fas fa-envelope text-primary mr-2"></i><a href="mailto:{{ $user->email }}">{{ $user->email }}</a></li>
                    @endif
                    @if($primaryPhone)
                    <li class="list-group-item"><i class="fas fa-phone text-success mr-2"></i>{{ $primaryPhone }}</li>
                    @endif
                    @if($user->passport)
                    <li class="list-group-item"><i class="fas fa-passport text-warning mr-2"></i>Pasaporte: <code>{{ $user->passport }}</code></li>
                    @endif
                    @if($user->date_of_birth)
                    <li class="list-group-item"><i class="fas fa-birthday-cake text-danger mr-2"></i>{{ \Carbon\Carbon::parse($user->date_of_birth)->format('d/m/Y') }}</li>
                    @endif
                    @if($user->genero)
                    <li class="list-group-item"><i class="fas fa-venus-mars text-info mr-2"></i>{{ $user->genero }}</li>
                    @endif
                    @if($user->pais_de_residencia || $user->city)
                    <li class="list-group-item"><i class="fas fa-map-marker-alt text-secondary mr-2"></i>{{ collect([$user->city, $user->pais_de_residencia])->filter()->implode(', ') }}</li>
                    @endif
                </ul>
            </div>
        </div>

        <div class="card card-outline card-secondary">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-clock mr-1"></i> Fechas</h6></div>
            <div class="card-body p-0"><ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><small class="text-muted">Registro</small><small>{{ $user->created_at?->format('d/m/Y H:i') ?? '—' }}</small></li>
                <li class="list-group-item d-flex justify-content-between"><small class="text-muted">Última actualización</small><small>{{ $user->updated_at?->format('d/m/Y H:i') ?? '—' }}</small></li>
            </ul></div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card card-outline card-primary">
            <div class="card-header p-0">
                <ul class="nav nav-tabs" id="userTabs">
                    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-projects"><i class="fas fa-project-diagram mr-1"></i> Negocios <span class="badge badge-primary">{{ $negocios->count() }}</span></a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-purchases"><i class="fas fa-handshake mr-1"></i> Compras <span class="badge badge-success">{{ $purchases->count() }}</span></a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-invoices"><i class="fas fa-file-invoice-dollar mr-1"></i> Facturas <span class="badge badge-warning">{{ $facturas->count() }}</span></a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-documents"><i class="fas fa-paperclip mr-1"></i> Documentos <span class="badge badge-secondary">{{ $documents->count() }}</span></a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-custom"><i class="fas fa-list mr-1"></i> Campos</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-audit"><i class="fas fa-history mr-1"></i> Auditoría <span class="badge badge-dark">{{ $changeAudits->count() }}</span></a></li>
                </ul>
            </div>
            <div class="card-body tab-content">
                <div class="tab-pane active" id="tab-projects">
                    @forelse($negocios as $negocio)
                    <div class="tl-contact-row d-flex justify-content-between align-items-center border-bottom py-2">
                        <div><strong>{{ $negocio->nombre ?? $negocio->dealname ?? 'Negocio' }}</strong><br><small class="text-muted">{{ $negocio->created_at?->format('d/m/Y') ?? '—' }}</small></div>
                        <span class="badge badge-{{ ($negocio->estatus ?? $negocio->status ?? '') === 'active' ? 'success' : 'secondary' }}">{{ $negocio->estatus ?? $negocio->status ?? 'Sin estado' }}</span>
                    </div>
                    @empty <p class="text-muted text-center py-3">Sin negocios registrados</p> @endforelse
                </div>
                <div class="tab-pane" id="tab-purchases">
                    @forelse($purchases as $purchase)
                    <div class="tl-contact-row d-flex justify-content-between align-items-center border-bottom py-2">
                        <div><strong>{{ $purchase->servicio?->nombre ?? $purchase->servicio_hs_id ?? $purchase->descripcion ?? 'Compra' }}</strong><br><small class="text-muted">{{ $purchase->created_at?->format('d/m/Y') ?? '—' }}</small></div>
                        <span class="badge badge-{{ $purchase->pagado ? 'success' : 'warning' }}">{{ $purchase->pagado ? 'Pagada' : 'Pendiente' }}</span>
                    </div>
                    @empty <p class="text-muted text-center py-3">Sin compras registradas</p> @endforelse
                </div>
                <div class="tab-pane" id="tab-invoices">
                    @forelse($facturas as $factura)
                    <div class="tl-contact-row d-flex justify-content-between align-items-center border-bottom py-2"><div><strong>{{ $factura->hash_factura ?? 'Factura' }}</strong><br><small class="text-muted">{{ $factura->created_at?->format('d/m/Y') ?? '—' }}</small></div><span class="badge badge-secondary">{{ $factura->met ?? 'Registrada' }}</span></div>
                    @empty <p class="text-muted text-center py-3">Sin facturas registradas</p> @endforelse
                </div>
                <div class="tab-pane" id="tab-documents">
                    @forelse($documents as $document)
                    <div class="tl-contact-row d-flex justify-content-between align-items-center border-bottom py-2"><div><i class="fas fa-file mr-1 text-secondary"></i><strong>{{ $document->file ?? 'Archivo' }}</strong><br><small class="text-muted">{{ $document->created_at?->format('d/m/Y') ?? '—' }}</small></div><span class="badge badge-light border">{{ $document->tipo ?? 'Documento' }}</span></div>
                    @empty <p class="text-muted text-center py-3">Sin documentos registrados</p> @endforelse
                </div>
                <div class="tab-pane" id="tab-custom"><div class="table-responsive"><table class="table table-sm"><thead class="thead-light"><tr><th>Campo</th><th>Valor</th></tr></thead><tbody>@foreach($attributes as $field => $value) @if(!is_null($value) && $value !== '')<tr><td><code style="font-size:.75rem">{{ $field }}</code></td><td>{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</td></tr>@endif @endforeach</tbody></table></div></div>
                <div class="tab-pane" id="tab-audit">
                    @forelse($changeAudits as $audit)
                    <div class="border-bottom py-2"><strong>{{ $audit->changedBy?->name ?? 'Sistema' }}</strong><small class="text-muted float-right">{{ $audit->created_at?->format('d/m/Y H:i:s') }}</small>@foreach($audit->new_values as $field => $newValue)<br><small><code>{{ $field }}</code>: {{ is_scalar($audit->old_values[$field] ?? null) || ($audit->old_values[$field] ?? null) === null ? ($audit->old_values[$field] ?? '—') : json_encode($audit->old_values[$field], JSON_UNESCAPED_UNICODE) }} → {{ is_scalar($newValue) || $newValue === null ? ($newValue ?? '—') : json_encode($newValue, JSON_UNESCAPED_UNICODE) }}</small>@endforeach</div>
                    @empty <p class="text-muted text-center py-3">Aún no hay cambios registrados.</p> @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('css')
<style>
    .tl-contact-row { color: inherit; text-decoration: none; }
    .tl-contact-row:hover { background: #f8fafc; }
</style>
@stop
