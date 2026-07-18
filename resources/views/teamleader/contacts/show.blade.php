{{-- resources/views/tl/contacts/show.blade.php --}}
@extends('adminlte::page')

@section('title', $contact->full_name)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>
            <a href="{{ route('teamleader.contacts.index') }}" class="btn btn-sm btn-secondary mr-2">
                <i class="fas fa-arrow-left"></i>
            </a>
            {{ $contact->full_name ?: '(Sin nombre)' }}
        </h1>
        <div class="d-flex flex-wrap" style="gap:.4rem">
            @can('administrador')
                <form method="POST"
                      action="{{ route('teamleader.contacts.documents.import', $contact->id) }}"
                      class="d-inline"
                      onsubmit="return confirm('Importar archivos de este contacto desde Teamleader hacia S3?');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-file-import mr-1"></i> Importar archivos
                    </button>
                </form>
            @endcan

            <a href="{{ $contact->raw_data['web_url'] ?? '#' }}" target="_blank" class="btn btn-sm btn-outline-info">
                <i class="fas fa-external-link-alt mr-1"></i> Ver en Teamleader
            </a>
        </div>
    </div>
@endsection

@section('content')
@foreach(['status' => 'success', 'error' => 'danger'] as $key => $class)
    @if(session($key))
        <div class="alert alert-{{ $class }} alert-dismissible fade show">
            {!! session($key) !!}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif
@endforeach

<div class="row">

    {{-- Columna izquierda: datos del contacto --}}
    <div class="col-md-4">

        {{-- Tarjeta principal --}}
        <div class="card card-primary card-outline">
            <div class="card-body text-center">
                <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                     style="width:80px;height:80px">
                    <span class="text-white font-weight-bold" style="font-size:2rem">
                        {{ strtoupper(substr($contact->first_name ?? '?', 0, 1)) }}
                    </span>
                </div>
                <h4 class="mb-0">{{ $contact->full_name ?: '(Sin nombre)' }}</h4>
                <small class="text-muted">ID: <code>{{ $contact->id }}</code></small>
                <div class="mt-2">
                    @if($contact->status === 'active')
                        <span class="badge badge-success">Activo</span>
                    @else
                        <span class="badge badge-danger">{{ $contact->status }}</span>
                    @endif
                </div>
            </div>
            <div class="card-footer p-0">
                <ul class="list-group list-group-flush">
                    @if($contact->email)
                    <li class="list-group-item">
                        <i class="fas fa-envelope text-primary mr-2"></i>
                        <a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>
                    </li>
                    @endif

                    @foreach($contact->emails ?? [] as $em)
                        @if($em['email'] !== $contact->email)
                        <li class="list-group-item">
                            <i class="fas fa-envelope-open text-secondary mr-2"></i>
                            <small>{{ $em['type'] }}:</small> {{ $em['email'] }}
                        </li>
                        @endif
                    @endforeach

                    @foreach($contact->telephones ?? [] as $tel)
                    <li class="list-group-item">
                        <i class="fas fa-phone text-success mr-2"></i>
                        <small>{{ $tel['type'] }}:</small> {{ $tel['number'] }}
                    </li>
                    @endforeach

                    @if($contact->passport)
                    <li class="list-group-item">
                        <i class="fas fa-passport text-warning mr-2"></i>
                        Pasaporte: <code>{{ $contact->passport }}</code>
                    </li>
                    @endif

                    @if(!empty($contact->raw_data['birthdate']))
                    <li class="list-group-item">
                        <i class="fas fa-birthday-cake text-danger mr-2"></i>
                        {{ \Carbon\Carbon::parse($contact->raw_data['birthdate'])->format('d/m/Y') }}
                    </li>
                    @endif

                    @if(!empty($contact->raw_data['gender']))
                    <li class="list-group-item">
                        <i class="fas fa-venus-mars text-info mr-2"></i>
                        {{ ucfirst($contact->raw_data['gender']) }}
                    </li>
                    @endif

                    @if(!empty($contact->raw_data['language']))
                    <li class="list-group-item">
                        <i class="fas fa-language text-secondary mr-2"></i>
                        {{ strtoupper($contact->raw_data['language']) }}
                    </li>
                    @endif

                    @if(!empty($contact->raw_data['website']))
                    <li class="list-group-item">
                        <i class="fas fa-globe text-primary mr-2"></i>
                        <a href="{{ $contact->raw_data['website'] }}" target="_blank">
                            {{ Str::limit($contact->raw_data['website'], 35) }}
                        </a>
                    </li>
                    @endif
                </ul>
            </div>
        </div>

        {{-- Tags --}}
        @if(!empty($contact->tags))
        <div class="card card-outline card-secondary">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-tags mr-1"></i> Tags</h6></div>
            <div class="card-body">
                @foreach($contact->tags as $tag)
                    <span class="badge badge-light border mr-1 mb-1" style="font-size:.85rem">{{ $tag }}</span>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Fechas --}}
        <div class="card card-outline card-secondary">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-clock mr-1"></i> Fechas</h6></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <small class="text-muted">Creado en TL</small>
                        <small>{{ $contact->tl_added_at?->format('d/m/Y H:i') ?? '—' }}</small>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <small class="text-muted">Actualizado en TL</small>
                        <small>{{ $contact->tl_updated_at?->format('d/m/Y H:i') ?? '—' }}</small>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <small class="text-muted">Sync local</small>
                        <small>{{ $contact->updated_at->format('d/m/Y H:i') }}</small>
                    </li>
                </ul>
            </div>
        </div>

    </div>

    {{-- Columna derecha: tabs --}}
    <div class="col-md-8">
        <div class="card card-outline card-primary">
            <div class="card-header p-0">
                <ul class="nav nav-tabs" id="contactTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#tab-projects">
                            <i class="fas fa-project-diagram mr-1"></i>
                            Proyectos <span class="badge badge-primary">{{ $projects->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#tab-deals">
                            <i class="fas fa-handshake mr-1"></i>
                            Deals <span class="badge badge-success">{{ $deals->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#tab-invoices">
                            <i class="fas fa-file-invoice-dollar mr-1"></i>
                            Facturas <span class="badge badge-warning">{{ $invoices->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#tab-docs">
                            <i class="fas fa-paperclip mr-1"></i>
                            Documentos <span class="badge badge-secondary">{{ $documents->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#tab-custom">
                            <i class="fas fa-list mr-1"></i>
                            Campos
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body tab-content">

                {{-- Proyectos --}}
                <div class="tab-pane active" id="tab-projects">
                    @forelse($projects as $project)
                    <a href="{{ route('teamleader.projects.show', $project) }}" class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            <strong>{{ $project->title ?? '(Sin título)' }}</strong><br>
                            <small class="text-muted">
                                {{ $project->starts_on?->format('d/m/Y') ?? '—' }}
                                →
                                {{ $project->due_on?->format('d/m/Y') ?? '—' }}
                            </small>
                        </div>
                        <div class="text-right">
                            @php
                                $badgeColor = match($project->status) {
                                    'active'    => 'success',
                                    'on_hold'   => 'warning',
                                    'done'      => 'primary',
                                    'cancelled' => 'danger',
                                    default     => 'secondary',
                                };
                            @endphp
                            <span class="badge badge-{{ $badgeColor }}">{{ $project->status }}</span>
                            @if($project->budget_amount)
                                <br><small>{{ number_format($project->budget_amount, 2) }} {{ $project->budget_currency }}</small>
                            @endif
                        </div>
                    </a>
                    @empty
                        <p class="text-muted text-center py-3">Sin proyectos registrados</p>
                    @endforelse
                </div>

                {{-- Deals --}}
                <div class="tab-pane" id="tab-deals">
                    @forelse($deals as $deal)
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            <strong>{{ $deal->title ?? '(Sin título)' }}</strong><br>
                            <small class="text-muted">{{ $deal->tl_created_at?->format('d/m/Y') ?? '—' }}</small>
                        </div>
                        <div class="text-right">
                            @php
                                $badgeColor = match($deal->status) {
                                    'open' => 'info',
                                    'won'  => 'success',
                                    'lost' => 'danger',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge badge-{{ $badgeColor }}">{{ $deal->status }}</span>
                            @if($deal->amount)
                                <br><small>{{ number_format($deal->amount, 2) }} {{ $deal->currency }}</small>
                            @endif
                        </div>
                    </div>
                    @empty
                        <p class="text-muted text-center py-3">Sin deals registrados</p>
                    @endforelse
                </div>

                {{-- Facturas --}}
                <div class="tab-pane" id="tab-invoices">
                    @forelse($invoices as $invoice)
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            <strong>{{ $invoice->invoice_number ?? '(Sin número)' }}</strong><br>
                            <small class="text-muted">{{ $invoice->invoice_date?->format('d/m/Y') ?? '—' }}</small>
                        </div>
                        <div class="text-right">
                            @php
                                $badgeColor = match($invoice->status) {
                                    'paid'        => 'success',
                                    'outstanding' => 'warning',
                                    'late'        => 'danger',
                                    'draft'       => 'secondary',
                                    default       => 'light',
                                };
                            @endphp
                            <span class="badge badge-{{ $badgeColor }}">{{ $invoice->status }}</span>
                            @if($invoice->total_price_incl_tax)
                                <br><small>{{ number_format($invoice->total_price_incl_tax, 2) }} {{ $invoice->currency }}</small>
                            @endif
                        </div>
                    </div>
                    @empty
                        <p class="text-muted text-center py-3">Sin facturas registradas</p>
                    @endforelse
                </div>

                {{-- Documentos --}}
                <div class="tab-pane" id="tab-docs">
                    @forelse($documents as $doc)
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            <i class="fas fa-file mr-1 text-secondary"></i>
                            <strong>{{ $doc->name ?? 'Archivo' }}</strong>
                            @if($doc->extension)
                                <span class="badge badge-light border">{{ $doc->extension }}</span>
                            @endif
                            <br>
                            <small class="text-muted">
                                {{ $doc->tl_created_at?->format('d/m/Y') ?? '—' }}
                                @if($doc->size_bytes)
                                    · {{ number_format($doc->size_bytes / 1024, 1) }} KB
                                @endif
                            </small>
                        </div>
                        <div>
                            @if($doc->downloaded && $doc->s3_path)
                                <a href="#" class="btn btn-xs btn-outline-primary">
                                    <i class="fas fa-download"></i>
                                </a>
                            @else
                                <span class="badge badge-warning">Pendiente</span>
                            @endif
                        </div>
                    </div>
                    @empty
                        <p class="text-muted text-center py-3">Sin documentos registrados</p>
                    @endforelse
                </div>

                {{-- Custom fields --}}
                <div class="tab-pane" id="tab-custom">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th>ID del campo</th>
                                    <th>Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($contact->custom_fields ?? [] as $cf)
                                    @if(!is_null($cf['value']))
                                    <tr>
                                        <td><code style="font-size:.75rem">{{ $cf['definition']['id'] }}</code></td>
                                        <td>{{ is_array($cf['value']) ? json_encode($cf['value']) : $cf['value'] }}</td>
                                    </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
@stop
