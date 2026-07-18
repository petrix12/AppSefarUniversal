{{-- resources/views/teamleader/documents/index.blade.php --}}
@extends('adminlte::page')

@section('title', 'Archivos TL')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:.75rem">
        <h1 class="mb-0"><i class="fas fa-paperclip mr-2"></i>Archivos migrados de Teamleader</h1>
        <a href="{{ route('teamleader.documents.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-sync-alt mr-1"></i> Actualizar
        </a>
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-4 col-sm-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ number_format($stats['total']) }}</h3>
                    <p>Total migrados</p>
                </div>
                <div class="icon"><i class="fas fa-folder-open"></i></div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ number_format($stats['downloaded']) }}</h3>
                    <p>Descargados en S3</p>
                </div>
                <div class="icon"><i class="fas fa-cloud-download-alt"></i></div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ number_format($stats['pending']) }}</h3>
                    <p>Pendientes</p>
                </div>
                <div class="icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-primary">
        <div class="card-header">
            <form method="GET" action="{{ route('teamleader.documents.index') }}" class="form-inline flex-wrap" style="gap:.5rem">
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    class="form-control form-control-sm"
                    placeholder="Buscar archivo, ID, ruta S3..."
                    style="min-width:260px"
                >

                <select name="entity_type" class="form-control form-control-sm">
                    <option value="">Todas las entidades</option>
                    @foreach(['contact' => 'Contactos', 'project' => 'Proyectos', 'deal' => 'Deals', 'company' => 'Empresas'] as $value => $label)
                        <option value="{{ $value }}" {{ request('entity_type') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>

                <select name="downloaded" class="form-control form-control-sm">
                    <option value="">Todos los estados</option>
                    <option value="1" {{ request('downloaded') === '1' ? 'selected' : '' }}>Descargados</option>
                    <option value="0" {{ request('downloaded') === '0' ? 'selected' : '' }}>Pendientes</option>
                </select>

                <select name="extension" class="form-control form-control-sm">
                    <option value="">Todas las extensiones</option>
                    @foreach($extensions as $extension)
                        <option value="{{ $extension }}" {{ request('extension') === $extension ? 'selected' : '' }}>
                            {{ strtoupper($extension) }}
                        </option>
                    @endforeach
                </select>

                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-search mr-1"></i> Filtrar
                </button>
                <a href="{{ route('teamleader.documents.index') }}" class="btn btn-sm btn-secondary">
                    <i class="fas fa-times mr-1"></i> Limpiar
                </a>
            </form>

            <div class="card-tools">
                <span class="badge badge-info">{{ number_format($documents->total()) }} archivo(s)</span>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Archivo</th>
                            <th>Estado</th>
                            <th>Contacto / cliente</th>
                            <th>Entidad Teamleader</th>
                            <th>Negocio app</th>
                            <th>Fechas</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documents as $document)
                            @php
                                $project = $context['projects']->get($document->entity_id);
                                $tlDeal = $context['deals']->get($document->entity_id);
                                $localDeal = $context['localDeals']->get($document->entity_id);

                                $contact = null;
                                $company = null;

                                if ($document->entity_type === 'contact') {
                                    $contact = $context['contacts']->get($document->entity_id);
                                } elseif ($document->entity_type === 'company') {
                                    $company = $context['companies']->get($document->entity_id);
                                } elseif ($document->entity_type === 'project' && $project) {
                                    if ($project->customer_type === 'contact') {
                                        $contact = $context['contacts']->get($project->customer_id);
                                    } elseif ($project->customer_type === 'company') {
                                        $company = $context['companies']->get($project->customer_id);
                                    }
                                } elseif ($document->entity_type === 'deal' && $tlDeal) {
                                    if ($tlDeal->customer_type === 'contact') {
                                        $contact = $context['contacts']->get($tlDeal->customer_id);
                                    } elseif ($tlDeal->customer_type === 'company') {
                                        $company = $context['companies']->get($tlDeal->customer_id);
                                    }
                                }
                            @endphp

                            <tr>
                                <td style="min-width:260px">
                                    <i class="fas fa-file mr-1 text-secondary"></i>
                                    <strong>{{ \Illuminate\Support\Str::limit($document->name ?: 'Archivo', 70) }}</strong>
                                    @if($document->extension)
                                        <span class="badge badge-light border">{{ strtoupper($document->extension) }}</span>
                                    @endif
                                    <br>
                                    <small class="text-muted">
                                        ID: <code>{{ $document->id }}</code>
                                        @if($document->size_bytes)
                                            / {{ $document->readable_size }}
                                        @endif
                                    </small>
                                </td>
                                <td>
                                    @if($document->downloaded && $document->s3_path)
                                        <span class="badge badge-success">En S3</span>
                                    @else
                                        <span class="badge badge-warning">Pendiente</span>
                                    @endif
                                </td>
                                <td style="min-width:180px">
                                    @if($contact)
                                        <a href="{{ route('teamleader.contacts.show', $contact->id) }}" class="font-weight-bold">
                                            {{ $contact->full_name ?: '(Sin nombre)' }}
                                        </a>
                                        @if($contact->email)
                                            <br><small class="text-muted">{{ $contact->email }}</small>
                                        @endif
                                    @elseif($company)
                                        <strong>{{ $company->name ?: 'Empresa' }}</strong>
                                        @if($company->email)
                                            <br><small class="text-muted">{{ $company->email }}</small>
                                        @endif
                                    @else
                                        <span class="text-muted">Sin cliente local</span>
                                    @endif
                                </td>
                                <td style="min-width:180px">
                                    @if($document->entity_type === 'project' && $project)
                                        <span class="badge badge-primary">Proyecto</span><br>
                                        <a href="{{ route('teamleader.projects.show', $project->id) }}">
                                            {{ \Illuminate\Support\Str::limit($project->title ?: $project->id, 55) }}
                                        </a>
                                    @elseif($document->entity_type === 'deal' && $tlDeal)
                                        <span class="badge badge-info">Deal</span><br>
                                        <strong>{{ \Illuminate\Support\Str::limit($tlDeal->title ?: $tlDeal->id, 55) }}</strong>
                                    @elseif($document->entity_type === 'contact' && $contact)
                                        <span class="badge badge-secondary">Contacto</span><br>
                                        <small><code>{{ $contact->id }}</code></small>
                                    @elseif($document->entity_type === 'company' && $company)
                                        <span class="badge badge-secondary">Empresa</span><br>
                                        <small><code>{{ $company->id }}</code></small>
                                    @else
                                        <span class="badge badge-light border">{{ $document->entity_type ?: '-' }}</span><br>
                                        <small><code>{{ $document->entity_id }}</code></small>
                                    @endif
                                </td>
                                <td>
                                    @if($localDeal)
                                        <a href="{{ route('deals.edit', $localDeal->id) }}" target="_blank" class="btn btn-xs btn-outline-success">
                                            <i class="fas fa-briefcase mr-1"></i> Ver negocio
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td style="min-width:130px">
                                    <small class="text-muted d-block">
                                        TL: {{ $document->tl_created_at?->format('d/m/Y') ?? '-' }}
                                    </small>
                                    <small class="text-muted d-block">
                                        S3: {{ $document->downloaded_at?->format('d/m/Y H:i') ?? '-' }}
                                    </small>
                                </td>
                                <td class="text-right">
                                    @if($document->downloaded && $document->s3_path)
                                        <a href="{{ route('teamleader.documents.download', $document->id) }}" target="_blank" class="btn btn-xs btn-outline-primary">
                                            <i class="fas fa-download mr-1"></i> Ver
                                        </a>
                                    @endif

                                    @if($contact)
                                        <a href="{{ route('teamleader.contacts.show', $contact->id) }}" class="btn btn-xs btn-outline-secondary">
                                            <i class="fas fa-user mr-1"></i> Contacto
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                    No se encontraron archivos migrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($documents->hasPages())
            <div class="card-footer">
                {{ $documents->links() }}
            </div>
        @endif
    </div>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop
