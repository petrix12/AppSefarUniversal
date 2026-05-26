@extends('adminlte::page')

@section('title', $user->name)

@section('content_header')
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

@stop

@section('content')

@php
    // Simulando un array de países (puedes llenarlo con todos los que necesites)
    $opcionesPersonas = [
        'Soporte IT', 'Crisanto Bello', 'Abel Tejeda', 'rrcastro@sefarvzla.com',
        // ...
        'Liliana Du Bois'
    ];
@endphp

<x-app-layout>
    <div>
        @if(auth()->user()->roles[0]->id != 5)
        <div class="flex flex-col">
            <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                    {{-- Inicio --}}
                    <div class="bg-gray-50">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                            <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                                <span class="ctvSefar block text-indigo-600">{{$user->nombres}} {{$user->apellidos}}</span>
                            </h2>
                            <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                                <div class="inline-flex rounded-md shadow">
                                    <a href="{{ route('crud.users.index') }}" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                        Volver a {{ __('Users list') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Fin --}}
                </div>
            </div>
        </div>
        @endif
        <div class="card p-4">
            <ul class="nav nav-tabs" id="formTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link active" id="mystatus-tab" data-bs-toggle="tab" data-bs-target="#mystatus" type="button" role="tab" aria-controls="mystatus" aria-selected="true">
                        @if(auth()->user()->roles[0]->id == 5)
                        Mi Estatus
                        @else
                        Estatus de Cliente
                        @endif
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="personal-data-tab" data-bs-toggle="tab" data-bs-target="#personal_data" type="button" role="tab" aria-controls="personal_data" aria-selected="true">
                        Datos personales
                    </button>
                </li>
                @if(auth()->user()->roles[0]->id == 1)
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="adminchangepassword-tab" data-bs-toggle="tab" data-bs-target="#adminchangepassword" type="button" role="tab" aria-controls="adminchangepassword" aria-selected="true">
                        Contraseña
                    </button>
                </li>
                @elseif(auth()->user()->roles[0]->id == 5)
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="mypassword-tab" data-bs-toggle="tab" data-bs-target="#mypassword" type="button" role="tab" aria-controls="mypassword" aria-selected="true">
                        Cambiar mi Contraseña
                    </button>
                </li>
                @endif
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="familiars-tab" data-bs-toggle="tab" data-bs-target="#familiars" type="button" role="tab" aria-controls="familiars" aria-selected="false">
                        Familiares registrados
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab" aria-controls="payments" aria-selected="false">
                        Pagos realizados
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="paymentspen-tab" data-bs-toggle="tab" data-bs-target="#paymentspen" type="button" role="tab" aria-controls="paymentspen" aria-selected="false">
                        Pagos pendientes
                    </button>
                </li>
                @if(auth()->user()->roles[0]->id == 5)
                <li class="nav-item">
                    <button style="color:black" class="nav-link" id="client-req-tab"
                            data-bs-toggle="tab" data-bs-target="#client-req"
                            type="button" role="tab" aria-controls="client-req" aria-selected="false">
                        Mis solicitudes de documentos
                    </button>
                </li>
                @else
                {{-- === TAB para ADMIN === --}}
                <li class="nav-item">
                    <button style="color:black" class="nav-link" id="admin-req-tab"
                            data-bs-toggle="tab" data-bs-target="#admin-req"
                            type="button" role="tab" aria-controls="admin-req" aria-selected="false">
                        Solicitudes de documentos
                    </button>
                </li>
                @endif
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab" aria-controls="documents" aria-selected="false">
                        Archivos Cargados
                    </button>
                </li>
                @if(auth()->user()->roles[0]->id == 1 || auth()->user()->roles[0]->id == 2)
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="etiquetado-tab" data-bs-toggle="tab" data-bs-target="#etiquetado" type="button" role="tab" aria-controls="etiquetado" aria-selected="false">
                        Etiquetado
                    </button>
                </li>
                @endif
                @if(auth()->user()->roles[0]->id == 17 || auth()->user()->roles[0]->id == 1 || auth()->user()->roles[0]->id == 4 || auth()->user()->roles[0]->id == 16 || auth()->user()->roles[0]->id == 15)
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="negocios-tab" data-bs-toggle="tab" data-bs-target="#negocios" type="button" role="tab" aria-controls="negocios" aria-selected="false">
                        Negocios
                    </button>
                </li>
                @endif
                @if(auth()->user()->roles[0]->id != 5)
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="teamleader-migration-tab" data-bs-toggle="tab" data-bs-target="#teamleader-migration" type="button" role="tab" aria-controls="teamleader-migration" aria-selected="false">
                        Teamleader
                        @if(($teamleaderMigration['contact'] ?? null))
                            <span class="badge bg-secondary ms-1">{{ ($teamleaderMigration['summary']['deals'] ?? 0) + ($teamleaderMigration['summary']['projects'] ?? 0) + ($teamleaderMigration['summary']['invoices'] ?? 0) }}</span>
                        @endif
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="client-tasks-tab" data-bs-toggle="tab" data-bs-target="#client-tasks" type="button" role="tab" aria-controls="client-tasks" aria-selected="false">
                        Tareas
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="client-chat-tab" data-bs-toggle="tab" data-bs-target="#client-chat" type="button" role="tab" aria-controls="client-chat" aria-selected="false">
                        Chat interno
                        <span id="clientInternalChatBadge" class="badge bg-danger ms-1 d-none">0</span>
                    </button>
                </li>
                @endif
            </ul>
            <style>
                /* Título de la tarjeta */
                .card-title {
                    width: 100%;
                    font-weight: bold;
                    text-align: center;
                }
            </style>
            <div class="tab-content mt-4" id="formTabsContent">
                <!-- Primer Formulario -->
                @php $rolId = auth()->user()->roles[0]->id; @endphp

                <div class="tab-pane fade show active" id="mystatus" role="tabpanel" aria-labelledby="mystatus-tab">
                    {{-- ══ BOTÓN DE SINCRONIZACIÓN ══ --}}
                    @if($rolId !== 5)
                    <div style="
                        display: flex;
                        justify-content: flex-end;
                        gap: .5rem;
                        flex-wrap: wrap;
                        margin-bottom: 1rem;
                    ">
                        <form
                            method="POST"
                            action="{{ route('users.sync-deals', $user) }}"
                            id="formSync"
                            onsubmit="onSyncSubmit(event)"
                        >
                            @csrf
                            <button
                                type="submit"
                                id="btnSync"
                                style="
                                    display: inline-flex;
                                    align-items: center;
                                    gap: .45rem;
                                    background: #4f46e5;
                                    color: #fff;
                                    border: none;
                                    border-radius: .5rem;
                                    padding: .45rem 1rem;
                                    font-size: .83rem;
                                    font-weight: 600;
                                    cursor: pointer;
                                    transition: background .2s;
                                "
                                onmouseover="this.style.background='#4338ca'"
                                onmouseout="this.style.background='#4f46e5'"
                            >
                                <i class="fas fa-sync-alt" id="iconSync"></i>
                                <span id="labelSync">Sincronizar COS del cliente</span>
                            </button>
                        </form>
                        <button
                            type="button"
                            id="btnCosReviewTask"
                            style="
                                display: inline-flex;
                                align-items: center;
                                gap: .45rem;
                                background: #0f766e;
                                color: #fff;
                                border: none;
                                border-radius: .5rem;
                                padding: .45rem 1rem;
                                font-size: .83rem;
                                font-weight: 600;
                                cursor: pointer;
                                transition: background .2s;
                            "
                            onmouseover="this.style.background='#115e59'"
                            onmouseout="this.style.background='#0f766e'"
                        >
                            <i class="fas fa-clipboard-check" id="iconCosReviewTask"></i>
                            <span id="labelCosReviewTask">Solicitar revision del COS a Sistemas</span>
                        </button>
                        <button
                            type="button"
                            id="btnNotifyCosStatus"
                            style="
                                display: inline-flex;
                                align-items: center;
                                gap: .45rem;
                                background: #7c3aed;
                                color: #fff;
                                border: none;
                                border-radius: .5rem;
                                padding: .45rem 1rem;
                                font-size: .83rem;
                                font-weight: 600;
                                cursor: pointer;
                                transition: background .2s;
                            "
                            onmouseover="this.style.background='#6d28d9'"
                            onmouseout="this.style.background='#7c3aed'"
                        >
                            <i class="fas fa-bell" id="iconNotifyCosStatus"></i>
                            <span id="labelNotifyCosStatus">Notificar estatus al cliente</span>
                        </button>
                    </div>

                    {{-- Flash de éxito --}}
                    @if(session('sync_success'))
                    <div
                        id="alertSync"
                        style="
                            background: #f0fdf4;
                            border: 1px solid #bbf7d0;
                            border-radius: .5rem;
                            padding: .6rem 1rem;
                            font-size: .83rem;
                            color: #15803d;
                            font-weight: 600;
                            margin-bottom: 1rem;
                            display: flex;
                            align-items: center;
                            gap: .5rem;
                        "
                    >
                        <i class="fas fa-check-circle"></i>
                        {{ session('sync_success') }}
                    </div>
                    @endif

                    <script>
                    function onSyncSubmit(e) {
                        const btn   = document.getElementById('btnSync');
                        const icon  = document.getElementById('iconSync');
                        const label = document.getElementById('labelSync');

                        // Bloquear botón y mostrar spinner
                        btn.disabled = true;
                        btn.style.background = '#6366f1';
                        btn.style.cursor = 'not-allowed';
                        icon.classList.add('fa-spin');
                        label.textContent = 'Sincronizando... Puede tardar un tiempo dependiendo de la cantidad de datos del cliente.';
                    }
                    </script>
                    @endif

                    @if($rolId !== 5)
                    <div style="
                        background: #f8fafc;
                        border: 1px solid #e2e8f0;
                        border-radius: .6rem;
                        padding: 1rem 1.25rem;
                        margin-bottom: 1.25rem;
                        display: flex;
                        flex-wrap: wrap;
                        align-items: center;
                        gap: .5rem 1.25rem;
                        font-size: .85rem;
                        color: #374151;
                    ">
                        {{-- Nombre --}}
                        <span style="font-weight:700; font-size:.95rem; color:#111827; flex-basis:100%;">
                            <i class="fas fa-user" style="color:#94a3b8; margin-right:.4rem;"></i>
                            {{ $user->name }}
                        </span>

                        {{-- Email --}}
                        @if($user->email)
                            <span>
                                <i class="fas fa-envelope" style="color:#94a3b8; margin-right:.35rem;"></i>
                                <a href="mailto:{{ $user->email }}"
                                style="color:#2563eb; text-decoration:none;"
                                onmouseover="this.style.textDecoration='underline'"
                                onmouseout="this.style.textDecoration='none'">
                                    {{ $user->email }}
                                </a>
                            </span>
                        @else
                            <span style="color:#dc2626; font-weight:600;">
                                <i class="fas fa-envelope" style="opacity:.4; margin-right:.35rem;"></i>
                                Sin email registrado
                            </span>
                        @endif

                        <span style="color:#cbd5e1;">|</span>

                        {{-- Teléfono --}}
                        @if($user->phone)
                            <span>
                                <i class="fas fa-phone" style="color:#94a3b8; margin-right:.35rem;"></i>
                                <a href="tel:{{ $user->phone }}"
                                style="color:#374151; text-decoration:none;"
                                onmouseover="this.style.color='#2563eb'"
                                onmouseout="this.style.color='#374151'">
                                    {{ $user->phone }}
                                </a>
                            </span>
                        @else
                            <span style="color:#dc2626; font-weight:600;">
                                <i class="fas fa-phone" style="opacity:.4; margin-right:.35rem;"></i>
                                Sin teléfono registrado
                            </span>
                        @endif

                    </div>
                    @endif

                    @if($rolId !== 5)
                        @php
                            $tlPaymentTotals = $teamleaderProjectPayments['totals'] ?? [];
                            $tlPaymentProjects = collect($teamleaderProjectPayments['projects'] ?? []);
                            $tlPaymentRows = $tlPaymentProjects
                                ->flatMap(function ($project) {
                                    return collect($project['phases'] ?? [])
                                        ->filter(function ($phase) {
                                            return ($phase['status'] ?? 'empty') !== 'empty'
                                                || (float) ($phase['effective_preestab_amount'] ?? 0) > 0
                                                || (float) ($phase['effective_paid_amount'] ?? 0) > 0
                                                || trim((string) ($phase['preestab_raw'] ?? '')) !== ''
                                                || trim((string) ($phase['paid_raw'] ?? '')) !== '';
                                        })
                                        ->map(function ($phase) use ($project) {
                                            $phase['project_title'] = $project['project_title'] ?? $project['project_id'] ?? '-';
                                            return $phase;
                                        });
                                })
                                ->values();
                            $tlPaymentHasData = $tlPaymentRows->isNotEmpty();
                            $tlMoney = fn ($amount) => number_format((float) $amount, 2, ',', '.') . ' EUR';
                            $tlStatusLabels = [
                                'paid' => 'Pagado',
                                'partial' => 'Parcial',
                                'pending' => 'Pendiente',
                                'exonerated' => 'Exonerado',
                                'included' => 'Incluido',
                                'empty' => 'Sin datos',
                            ];
                            $tlStatusClasses = [
                                'paid' => 'bg-success',
                                'partial' => 'bg-warning text-dark',
                                'pending' => 'bg-danger',
                                'exonerated' => 'bg-info text-dark',
                                'included' => 'bg-secondary',
                                'empty' => 'bg-light text-dark',
                            ];
                        @endphp

                        <div class="mb-4" style="border:1px solid #dbeafe; border-radius:.65rem; overflow:hidden; background:#fff;">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.75rem; padding:1rem 1.25rem; background:#eff6ff; border-bottom:1px solid #dbeafe;">
                                <div>
                                    <div style="font-size:.78rem; font-weight:700; color:#1d4ed8; text-transform:uppercase; letter-spacing:.04em;">
                                        Finanzas Teamleader
                                    </div>
                                    <h3 style="font-size:1.1rem; font-weight:800; color:#111827; margin:0;">
                                        Resumen global por proyectos
                                    </h3>
                                </div>
                                @if(($teamleaderMigration['contact'] ?? null))
                                    <span class="badge bg-primary" style="font-size:.78rem;">
                                        {{ $tlPaymentTotals['projects'] ?? 0 }} proyecto(s)
                                    </span>
                                @else
                                    <span class="badge bg-secondary" style="font-size:.78rem;">Sin contacto TL asociado</span>
                                @endif
                            </div>

                            <div style="padding:1rem 1.25rem;">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div style="background:#f8fafc; border:1px solid #e5e7eb; border-radius:.55rem; padding:.85rem;">
                                            <div class="small text-muted">Preestablecido</div>
                                            <div style="font-size:1.15rem; font-weight:800; color:#111827;">
                                                {{ $tlMoney($tlPaymentTotals['preestab_amount'] ?? 0) }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:.55rem; padding:.85rem;">
                                            <div class="small text-muted">Pagado / abonado</div>
                                            <div style="font-size:1.15rem; font-weight:800; color:#166534;">
                                                {{ $tlMoney($tlPaymentTotals['paid_amount'] ?? 0) }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:.55rem; padding:.85rem;">
                                            <div class="small text-muted">Saldo pendiente</div>
                                            <div style="font-size:1.15rem; font-weight:800; color:#9a3412;">
                                                {{ $tlMoney($tlPaymentTotals['balance_amount'] ?? 0) }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:.55rem; padding:.85rem;">
                                            <div class="small text-muted">Sobrepago detectado</div>
                                            <div style="font-size:1.15rem; font-weight:800; color:#991b1b;">
                                                {{ $tlMoney($tlPaymentTotals['overpaid_amount'] ?? 0) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @if($tlPaymentHasData)
                                    <div class="table-responsive mt-3">
                                        <table class="table table-sm table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Proyecto</th>
                                                    <th>Fase</th>
                                                    <th>Estado</th>
                                                    <th class="text-end">Preestab</th>
                                                    <th class="text-end">Pagado</th>
                                                    <th class="text-end">Saldo</th>
                                                    <th>Valor TL</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($tlPaymentRows->take(12) as $phase)
                                                    @php
                                                        $status = $phase['status'] ?? 'empty';
                                                        $rawPieces = array_filter([
                                                            trim((string) ($phase['preestab_raw'] ?? '')),
                                                            trim((string) ($phase['paid_raw'] ?? '')),
                                                        ]);
                                                    @endphp
                                                    <tr>
                                                        <td>{{ \Illuminate\Support\Str::limit($phase['project_title'] ?? '-', 42) }}</td>
                                                        <td>Fase {{ $phase['phase'] ?? '-' }}</td>
                                                        <td>
                                                            <span class="badge {{ $tlStatusClasses[$status] ?? 'bg-secondary' }}">
                                                                {{ $tlStatusLabels[$status] ?? $status }}
                                                            </span>
                                                        </td>
                                                        <td class="text-end">{{ $tlMoney($phase['effective_preestab_amount'] ?? 0) }}</td>
                                                        <td class="text-end">{{ $tlMoney($phase['effective_paid_amount'] ?? 0) }}</td>
                                                        <td class="text-end fw-bold {{ ((float) ($phase['balance_amount'] ?? 0)) > 0 ? 'text-danger' : 'text-success' }}">
                                                            {{ $tlMoney($phase['balance_amount'] ?? 0) }}
                                                        </td>
                                                        <td class="small text-muted">{{ \Illuminate\Support\Str::limit(implode(' | ', $rawPieces), 70) ?: '-' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    @if($tlPaymentRows->count() > 12)
                                        <div class="small text-muted mt-2">
                                            Se muestran 12 de {{ $tlPaymentRows->count() }} fases con movimiento.
                                        </div>
                                    @endif
                                @else
                                    <div class="alert alert-light border mt-3 mb-0">
                                        No hay montos detectados en los campos Fase 1/2/3 Preestab y Pagado de los proyectos Teamleader asociados.
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($rolId !== 5)
                        @php
                            $tlContactMain = $teamleaderMigration['contact'] ?? null;
                            $tlSummaryMain = $teamleaderMigration['summary'] ?? [];
                            $tlProjectsMain = collect($teamleaderMigration['projects'] ?? []);
                            $tlDealsMain = collect($teamleaderMigration['deals'] ?? []);
                            $tlInvoicesMain = collect($teamleaderMigration['invoices'] ?? []);
                            $tlDocumentsMain = collect($teamleaderMigration['documents'] ?? []);
                            $tlMatchLabelsMain = $teamleaderMigration['match_labels'] ?? [];
                            $canViewTeamleader = auth()->user()->can('tl.view');

                            $tlAssociatedRows = collect()
                                ->concat($tlProjectsMain->map(function ($project) use ($canViewTeamleader) {
                                    return [
                                        'type' => 'Proyecto',
                                        'icon' => 'fa-project-diagram',
                                        'name' => $project->title ?: $project->id,
                                        'status' => $project->status ?: '-',
                                        'amount' => $project->budget_amount ? number_format((float) $project->budget_amount, 2, ',', '.') . ' ' . ($project->budget_currency ?: 'EUR') : '-',
                                        'meta' => $project->custom_field_value ?: '-',
                                        'updated' => $project->tl_updated_at ?: $project->updated_at,
                                        'url' => $canViewTeamleader ? route('teamleader.projects.show', $project->id) : null,
                                    ];
                                }))
                                ->concat($tlDealsMain->map(function ($deal) {
                                    return [
                                        'type' => 'Deal',
                                        'icon' => 'fa-handshake',
                                        'name' => $deal->title ?: $deal->id,
                                        'status' => $deal->status ?: '-',
                                        'amount' => $deal->amount ? number_format((float) $deal->amount, 2, ',', '.') . ' ' . ($deal->currency ?: 'EUR') : '-',
                                        'meta' => optional($deal->estimated_closing_date)->format('d/m/Y') ?: '-',
                                        'updated' => $deal->tl_updated_at ?: $deal->updated_at,
                                        'url' => null,
                                    ];
                                }))
                                ->concat($tlInvoicesMain->map(function ($invoice) use ($canViewTeamleader) {
                                    return [
                                        'type' => 'Factura',
                                        'icon' => 'fa-file-invoice-dollar',
                                        'name' => $invoice->invoice_number ?: $invoice->id,
                                        'status' => $invoice->status ?: '-',
                                        'amount' => $invoice->total_price_incl_tax ? number_format((float) $invoice->total_price_incl_tax, 2, ',', '.') . ' ' . ($invoice->currency ?: 'EUR') : '-',
                                        'meta' => optional($invoice->invoice_date)->format('d/m/Y') ?: '-',
                                        'updated' => $invoice->tl_updated_at ?: $invoice->updated_at,
                                        'url' => $canViewTeamleader ? route('teamleader.invoices.show', $invoice->id) : null,
                                    ];
                                }))
                                ->concat($tlDocumentsMain->map(function ($document) {
                                    return [
                                        'type' => 'Documento',
                                        'icon' => 'fa-file-alt',
                                        'name' => $document->name ?: $document->id,
                                        'status' => $document->downloaded ? 'Descargado' : 'Pendiente',
                                        'amount' => $document->readable_size,
                                        'meta' => $document->entity_type ?: '-',
                                        'updated' => $document->tl_updated_at ?: $document->updated_at,
                                        'url' => null,
                                    ];
                                }))
                                ->sortByDesc(function ($row) {
                                    return $row['updated'] ? \Illuminate\Support\Carbon::parse($row['updated'])->timestamp : 0;
                                })
                                ->values();
                        @endphp

                        <div class="mb-4" style="border:1px solid #e5e7eb; border-radius:.65rem; overflow:hidden; background:#fff;">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.75rem; padding:1rem 1.25rem; background:#f8fafc; border-bottom:1px solid #e5e7eb;">
                                <div>
                                    <div style="font-size:.78rem; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:.04em;">
                                        Registros asociados
                                    </div>
                                    <h3 style="font-size:1.1rem; font-weight:800; color:#111827; margin:0;">
                                        Teamleader
                                    </h3>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge bg-secondary">{{ $tlContactMain ? 1 : 0 }} contacto</span>
                                    <span class="badge bg-secondary">{{ $tlSummaryMain['projects'] ?? 0 }} proyecto(s)</span>
                                    <span class="badge bg-secondary">{{ $tlSummaryMain['deals'] ?? 0 }} deal(s)</span>
                                    <span class="badge bg-secondary">{{ $tlSummaryMain['invoices'] ?? 0 }} factura(s)</span>
                                    <span class="badge bg-secondary">{{ $tlSummaryMain['documents'] ?? 0 }} documento(s)</span>
                                </div>
                            </div>

                            <div style="padding:1rem 1.25rem;">
                                @if(! $tlContactMain)
                                    <div class="alert alert-light border mb-0">
                                        No se encontro un contacto Teamleader asociado por ID, pasaporte o correo.
                                    </div>
                                @else
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                                        <div>
                                            <div class="small text-muted">Contacto Teamleader</div>
                                            <div style="font-size:1rem; font-weight:800; color:#111827;">
                                                {{ $tlContactMain->full_name ?: $tlContactMain->email ?: $tlContactMain->id }}
                                            </div>
                                            <div class="small text-muted">
                                                {{ $tlContactMain->email ?: 'Sin email TL' }} | ID {{ $tlContactMain->id }}
                                            </div>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 align-items-center">
                                            @foreach($tlMatchLabelsMain as $label)
                                                <span class="badge bg-success">{{ $label }}</span>
                                            @endforeach
                                            @if($canViewTeamleader)
                                                <a href="{{ route('teamleader.contacts.show', $tlContactMain->id) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-external-link-alt me-1"></i> Ver contacto TL
                                                </a>
                                            @endif
                                        </div>
                                    </div>

                                    @if($tlAssociatedRows->isNotEmpty())
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover align-middle mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Tipo</th>
                                                        <th>Registro</th>
                                                        <th>Estado</th>
                                                        <th>Monto / Tamano</th>
                                                        <th>Dato clave</th>
                                                        <th>Actualizado</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($tlAssociatedRows->take(12) as $row)
                                                        <tr>
                                                            <td>
                                                                <span class="badge bg-light text-dark">
                                                                    <i class="fas {{ $row['icon'] }} me-1"></i>{{ $row['type'] }}
                                                                </span>
                                                            </td>
                                                            <td>{{ \Illuminate\Support\Str::limit($row['name'], 48) }}</td>
                                                            <td>{{ $row['status'] }}</td>
                                                            <td>{{ $row['amount'] }}</td>
                                                            <td class="small text-muted">{{ \Illuminate\Support\Str::limit($row['meta'], 42) }}</td>
                                                            <td class="small text-muted">
                                                                {{ $row['updated'] ? \Illuminate\Support\Carbon::parse($row['updated'])->format('d/m/Y H:i') : '-' }}
                                                            </td>
                                                            <td class="text-end">
                                                                @if($row['url'])
                                                                    <a href="{{ $row['url'] }}" target="_blank" class="btn btn-xs btn-outline-secondary">
                                                                        <i class="fas fa-eye"></i>
                                                                    </a>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        @if($tlAssociatedRows->count() > 12)
                                            <div class="small text-muted mt-2">
                                                Se muestran 12 de {{ $tlAssociatedRows->count() }} registros asociados.
                                            </div>
                                        @endif
                                    @else
                                        <div class="alert alert-light border mb-0">
                                            El contacto Teamleader fue asociado, pero no hay proyectos, deals, facturas o documentos vinculados.
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endif

                    @if(sizeof($cosuser)>0)
                        @foreach ($cosuser as $index => $proceso)
                            @if(array_key_exists($proceso['servicio'], $cos))
                                <div class="card mb-4 shadow-sm">
                                    @php
                                        // Encontrar el negocio que corresponde a este proceso
                                        $negocioDebug = null;
                                        foreach ($negocios as $neg) {
                                            $negServ = is_object($neg)
                                                ? ($neg->servicio_solicitado2 ?? $neg->servicio_solicitado ?? '')
                                                : ($neg['servicio_solicitado2'] ?? $neg['servicio_solicitado'] ?? '');

                                            if ($negServ == ($proceso['servicio'] ?? '')) {
                                                $negocioDebug = $neg;
                                                break;
                                            }
                                        }

                                        $camposCos = [
                                            // ── Jurídicos ──────────────────────────────────────────
                                            'nacionalidad_concedida'            => 'Nacionalidad Concedida',
                                            'n7__fecha_de_resolucion'           => 'Fecha de Resolución',
                                            'n13__fecha_recurso_alzada'         => 'Fecha Recurso de Alzada',
                                            'formalizacion_r__alzada'           => 'Formalización R. Alzada',
                                            'n5__fecha_de_formalizacion'        => 'Fecha de Formalización',
                                            'codigo_de_proceso'                 => 'Código de Proceso',
                                            'tasa_pagada'                       => 'Tasa Pagada',
                                            'enviado_a_pago_de_tasas'           => 'Enviado a Pago de Tasas',
                                            'fase_3_pagado'                     => 'Fase 3 Pagada',
                                            'fase_3_pagado__teamleader_'        => 'Fase 3 Pagada (TL)',
                                            'fase_3_preestab'                   => 'Fase 3 Preestablecida',
                                            'fecha_solicitud_viajudicial'       => 'Fecha Solicitud Vía Judicial',
                                            'fecha_solicitud_recursoalzada'     => 'Fecha Solicitud Recurso Alzada',
                                            'fecha_solicitud_recurso_urgencia'  => 'Fecha Solicitud Recurso Urgencia',
                                            'fecha_solicitud_resolucionexpresa' => 'Fecha Solicitud Resolución Expresa',
                                            // ── Genealógicos ───────────────────────────────────────
                                            'n7__enviado_al_dto_juridico'       => 'Enviado al Dto. Jurídico',
                                            'n4__certificado_descargado'        => 'Certificado Descargado',
                                            'n3__informe_cargado'               => 'Informe Cargado',
                                            'fase_2_preestab'                   => 'Fase 2 Preestablecida',
                                            'fase_2_pagado'                     => 'Fase 2 Pagada',
                                            'fase_2_pagado__teamleader_'        => 'Fase 2 Pagada (TL)',
                                            'fase_1_pagado'                     => 'Fase 1 Pagada',
                                            'fase_1_pagado__teamleader_'        => 'Fase 1 Pagada (TL)',
                                            'fase_1_preestab'                   => 'Fase 1 Preestablecida',
                                            // ── Identificación ─────────────────────────────────────
                                            'hubspot_id'                        => 'HubSpot ID',
                                            'servicio_solicitado'               => 'Servicio Solicitado',
                                            'servicio_solicitado2'              => 'Servicio Solicitado 2',
                                        ];

                                        // Leer valores del negocio (disponibles para todos)
                                        $valoresCos = [];
                                        if ($negocioDebug) {
                                            foreach ($camposCos as $campo => $etiqueta) {
                                                $valor = is_object($negocioDebug)
                                                    ? $negocioDebug->getAttribute($campo)
                                                    : ($negocioDebug[$campo] ?? null);

                                                $valoresCos[$campo] = [
                                                    'label' => $etiqueta,
                                                    'valor' => $valor,
                                                    'isset' => !is_null($valor) && $valor !== '',
                                                ];
                                            }
                                        }

                                        // Separar campos con y sin valor (para el panel vendedor)
                                        $camposConValor    = array_filter($valoresCos, fn($c) => $c['isset']);
                                        $camposSinValor    = array_filter($valoresCos, fn($c) => !$c['isset']);

                                        // Agrupar por categoría para el panel vendedor
                                        $gruposVendedor = [
                                            '💶 Pagos' => [
                                                'fase_1_pagado', 'fase_1_pagado__teamleader_', 'fase_1_preestab',
                                                'fase_2_pagado', 'fase_2_pagado__teamleader_', 'fase_2_preestab',
                                                'fase_3_pagado', 'fase_3_pagado__teamleader_', 'fase_3_preestab',
                                                'tasa_pagada',   'enviado_a_pago_de_tasas',
                                            ],
                                            '⚖️ Jurídico' => [
                                                'nacionalidad_concedida',
                                                'n7__fecha_de_resolucion',
                                                'n13__fecha_recurso_alzada',
                                                'formalizacion_r__alzada',
                                                'n5__fecha_de_formalizacion',
                                                'codigo_de_proceso',
                                                'fecha_solicitud_viajudicial',
                                                'fecha_solicitud_recursoalzada',
                                                'fecha_solicitud_recurso_urgencia',
                                                'fecha_solicitud_resolucionexpresa',
                                            ],
                                            '🌳 Genealógico' => [
                                                'n7__enviado_al_dto_juridico',
                                                'n4__certificado_descargado',
                                                'n3__informe_cargado',
                                            ],
                                            '🪪 Identificación' => [
                                                'hubspot_id',
                                                'servicio_solicitado',
                                                'servicio_solicitado2',
                                            ],
                                        ];
                                    @endphp

                                    {{-- Header con título y estatus --}}
                                    <div class="card-header text-center bg-white">
                                        <h1 class="card-title mt-4 mb-2" style="font-size:1.8rem;">
                                            Proceso: {!! $proceso['servicio'] !!}
                                        </h1>

                                        <p class="pb-4" style="font-size:1.4rem;">
                                            Estatus actual: <b>{{ $proceso['currentStepName'] ?? 'No iniciado' }}</b>
                                        </p>

                                        @if(isset($proceso['warning']))
                                            <div class="alert alert-warning fade show py-2 d-flex justify-content-center align-items-center gap-2" role="alert">
                                                <i class="fas fa-exclamation-triangle" style="font-size: 20px"></i>
                                                <div style="font-size:1rem;">{!! $proceso['warning'] !!}</div>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- ══ DEBUG (solo rol 1) ══ --}}
                                    @if($rolId == 1)
                                    <div class="accordion mb-2" id="debugAccordion{{ $index }}">
                                        <div class="accordion-item" style="border: 2px dashed #f0ad4e;">
                                            <h2 class="accordion-header">
                                                <button
                                                    class="accordion-button collapsed py-2 px-3"
                                                    type="button"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#debugCollapse{{ $index }}"
                                                    style="background:#fff8e1; color:#5a3e00; font-size:0.82rem; font-weight:600;"
                                                >
                                                    🛠️ DEBUG &nbsp;·&nbsp;
                                                    <span class="mx-2">{{ $proceso['servicio'] ?? '—' }}</span>
                                                    &nbsp;·&nbsp;
                                                    Regla: <code class="mx-1" style="font-size:0.8rem;">{{ $proceso['description'] ?? 'N/A' }}</code>
                                                    &nbsp;·&nbsp;
                                                    Gen: <code class="mx-1">{{ $proceso['currentStepGen'] ?? 'N/A' }}</code>
                                                    Jur: <code class="mx-1">{{ $proceso['currentStepJur'] ?? 'N/A' }}</code>
                                                    Cert: <code class="mx-1">{{ $proceso['certificadoDescargado'] ?? 'N/A' }}</code>
                                                </button>
                                            </h2>
                                            <div id="debugCollapse{{ $index }}" class="accordion-collapse collapse">
                                                <div class="accordion-body p-3" style="background:#fffdf5; font-size:0.82rem;">

                                                    <h6 class="fw-bold text-warning-emphasis border-bottom pb-1 mb-2">📊 Resultado COS</h6>
                                                    <div class="row row-cols-2 row-cols-md-4 g-2 mb-3">
                                                        @php
                                                            $resumenCos = [
                                                                'Regla activa'      => $proceso['description'] ?? null,
                                                                'Nombre del paso'   => $proceso['currentStepName'] ?? null,
                                                                'Step Gen (índice)' => $proceso['currentStepGen'] ?? null,
                                                                'Step Jur (índice)' => $proceso['currentStepJur'] ?? null,
                                                                'Cert. Descargado'  => $proceso['certificadoDescargado'] ?? null,
                                                                'Subproceso'        => $proceso['subproceso'] ?? null,
                                                                '% Gen'             => isset($proceso['progressPercentageGen']) ? $proceso['progressPercentageGen'].'%' : null,
                                                                '% Jur'             => isset($proceso['progressPercentageJur']) ? $proceso['progressPercentageJur'].'%' : null,
                                                            ];
                                                        @endphp
                                                        @foreach ($resumenCos as $label => $val)
                                                        <div class="col">
                                                            <div class="p-2 rounded border bg-white h-100">
                                                                <div class="text-muted" style="font-size:0.72rem;">{{ $label }}</div>
                                                                <div class="fw-semibold">
                                                                    {{ is_null($val) ? '—' : $val }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        @endforeach
                                                    </div>

                                                    @if(!empty($proceso['warning']))
                                                    <h6 class="fw-bold text-danger border-bottom pb-1 mb-2">⚠️ Warning</h6>
                                                    <div class="p-2 border rounded bg-white mb-3">{!! $proceso['warning'] !!}</div>
                                                    @endif

                                                    <h6 class="fw-bold text-success border-bottom pb-1 mb-2">🗄️ Campos del negocio</h6>
                                                    @if($negocioDebug)
                                                    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-2">
                                                        @foreach ($valoresCos as $campo => $info)
                                                        <div class="col">
                                                            <div class="p-2 rounded border h-100"
                                                                style="background:{{ $info['isset'] ? '#f0fff4' : '#fff5f5' }}; border-color:{{ $info['isset'] ? '#b2dfdb' : '#ffcdd2' }} !important;">
                                                                <div class="text-muted text-truncate" style="font-size:0.70rem;" title="{{ $campo }}">{{ $campo }}</div>
                                                                <div style="font-size:0.75rem; font-weight:500; word-break:break-all;">
                                                                    @if($info['isset'])
                                                                        <span class="text-success">{{ Str::limit((string)$info['valor'], 40) }}</span>
                                                                    @else
                                                                        <span class="text-danger fst-italic">— no definido</span>
                                                                    @endif
                                                                </div>
                                                                <div style="font-size:0.68rem; margin-top:2px;" class="text-muted">{{ $info['label'] }}</div>
                                                            </div>
                                                        </div>
                                                        @endforeach
                                                    </div>
                                                    @else
                                                        <div class="alert alert-warning py-2 mb-0">
                                                            No se encontró el negocio para <code>{{ $proceso['servicio'] }}</code>
                                                        </div>
                                                    @endif

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    {{-- ══ PANEL VENDEDOR (roles 2,3,4 — NO 1 ni 5) ══ --}}
                                    @if($rolId !== 1 && $rolId !== 5)
                                    <div class="accordion mb-3" id="vendedorAccordion{{ $index }}">
                                        <div class="accordion-item" style="border:1px solid #e2e8f0; border-radius:.6rem; overflow:hidden;">

                                            <h2 class="accordion-header">
                                                <button
                                                    class="accordion-button collapsed py-2 px-3"
                                                    type="button"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#vendedorCollapse{{ $index }}"
                                                    style="background:#f8fafc; color:#1e293b; font-size:0.85rem; font-weight:600;"
                                                >
                                                    <i class="fas fa-folder-open" style="color:#6366f1; margin-right:.5rem;"></i>
                                                    Datos del negocio &nbsp;·&nbsp;
                                                    <span style="color:#6366f1;">{{ $proceso['servicio'] }}</span>
                                                    &nbsp;·&nbsp;
                                                    <span style="
                                                        font-size:.72rem; font-weight:600;
                                                        background:#ede9fe; color:#5b21b6;
                                                        border-radius:999px; padding:.15rem .55rem;
                                                    ">{{ $proceso['currentStepName'] ?? 'Sin estado' }}</span>

                                                    {{-- Badge: cuántos campos tienen valor --}}
                                                    @if(count($camposConValor) > 0)
                                                    <span style="
                                                        font-size:.68rem; font-weight:700;
                                                        background:#dcfce7; color:#15803d;
                                                        border-radius:999px; padding:.15rem .55rem;
                                                        margin-left:.5rem;
                                                    ">{{ count($camposConValor) }} datos</span>
                                                    @endif
                                                </button>
                                            </h2>

                                            <div id="vendedorCollapse{{ $index }}" class="accordion-collapse collapse">
                                                <div class="accordion-body p-3" style="background:#ffffff; font-size:.83rem;">

                                                    {{-- ── Resumen COS ── --}}
                                                    <p style="font-size:.72rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.5rem;">
                                                        Resumen del estado
                                                    </p>
                                                    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(140px,1fr)); gap:.5rem; margin-bottom:1rem;">
                                                        @php
                                                            $resumenVendedor = [
                                                                ['icon'=>'fa-flag',      'label'=>'Paso Gen',     'value'=> $proceso['currentStepGen'] ?? null],
                                                                ['icon'=>'fa-gavel',     'label'=>'Paso Jur',     'value'=> $proceso['currentStepJur'] ?? null],
                                                                ['icon'=>'fa-certificate','label'=>'Certificado', 'value'=> $proceso['certificadoDescargado'] ?? null],
                                                                ['icon'=>'fa-code-branch','label'=>'Subproceso',  'value'=> $proceso['subproceso'] ?? null],
                                                                ['icon'=>'fa-chart-bar', 'label'=>'% Gen',        'value'=> isset($proceso['progressPercentageGen']) ? $proceso['progressPercentageGen'].'%' : null],
                                                                ['icon'=>'fa-chart-bar', 'label'=>'% Jur',        'value'=> isset($proceso['progressPercentageJur']) ? $proceso['progressPercentageJur'].'%' : null],
                                                                ['icon'=>'fa-tag',       'label'=>'Regla activa', 'value'=> $proceso['description'] ?? null],
                                                            ];
                                                        @endphp
                                                        @foreach($resumenVendedor as $item)
                                                        <div style="
                                                            background:{{ is_null($item['value']) ? '#fafafa' : '#f0fdf4' }};
                                                            border:1px solid {{ is_null($item['value']) ? '#e2e8f0' : '#bbf7d0' }};
                                                            border-radius:.45rem; padding:.5rem .65rem;
                                                        ">
                                                            <div style="font-size:.68rem; color:#94a3b8; margin-bottom:.2rem;">
                                                                <i class="fas {{ $item['icon'] }}" style="margin-right:.25rem;"></i>{{ $item['label'] }}
                                                            </div>
                                                            <div style="font-weight:600; font-size:.82rem; color:{{ is_null($item['value']) ? '#cbd5e1' : '#15803d' }};">
                                                                {{ $item['value'] ?? '—' }}
                                                            </div>
                                                        </div>
                                                        @endforeach
                                                    </div>

                                                    {{-- ── Campos del negocio agrupados ── --}}
                                                    @if($negocioDebug)
                                                        @foreach($gruposVendedor as $grupoNombre => $grupoKeys)
                                                            @php
                                                                $camposDelGrupo = array_filter(
                                                                    $valoresCos,
                                                                    fn($k) => in_array($k, $grupoKeys),
                                                                    ARRAY_FILTER_USE_KEY
                                                                );
                                                                $hayValoresEnGrupo = count(array_filter($camposDelGrupo, fn($c) => $c['isset'])) > 0;
                                                            @endphp

                                                            <p style="font-size:.72rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.5rem; margin-top:.75rem;">
                                                                {{ $grupoNombre }}
                                                            </p>

                                                            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px,1fr)); gap:.5rem; margin-bottom:.5rem;">
                                                                @foreach($camposDelGrupo as $campo => $info)
                                                                <div style="
                                                                    background:{{ $info['isset'] ? '#f0fdf4' : '#fafafa' }};
                                                                    border:1px solid {{ $info['isset'] ? '#bbf7d0' : '#e2e8f0' }};
                                                                    border-radius:.45rem; padding:.5rem .65rem;
                                                                    opacity:{{ $info['isset'] ? '1' : '0.5' }};
                                                                ">
                                                                    <div style="font-size:.68rem; color:#94a3b8; margin-bottom:.2rem;">
                                                                        {{ $info['label'] }}
                                                                    </div>
                                                                    <div style="font-weight:600; font-size:.82rem; color:{{ $info['isset'] ? '#15803d' : '#cbd5e1' }}; word-break:break-all;">
                                                                        {{ $info['isset'] ? Str::limit((string)$info['valor'], 50) : '—' }}
                                                                    </div>
                                                                </div>
                                                                @endforeach
                                                            </div>
                                                        @endforeach
                                                    @else
                                                        <div class="alert alert-warning py-2 mb-0" style="font-size:.8rem;">
                                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                                            No se encontró el negocio para <code>{{ $proceso['servicio'] }}</code>
                                                        </div>
                                                    @endif

                                                    {{-- ── Warning ── --}}
                                                    @if(!empty($proceso['warning']))
                                                    <div style="
                                                        background:#fff7ed; border:1px solid #fed7aa;
                                                        border-radius:.4rem; padding:.5rem .75rem;
                                                        font-size:.78rem; color:#9a3412; margin-top:.75rem;
                                                    ">
                                                        <i class="fas fa-exclamation-triangle" style="margin-right:.35rem;"></i>
                                                        {!! $proceso['warning'] !!}
                                                    </div>
                                                    @endif

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    {{-- Barras de progreso --}}
                                    <div class="card-body text-center" style="background: rgba(0,0,0,0.05);">
                                        @include('crud.users.partials.progress-bars', [
                                            'proceso' => $proceso,
                                            'cos' => $cos,
                                            'index' => $index
                                        ])
                                    </div>

                                    {{-- Detalle del estatus --}}
                                    @if(isset($proceso['currentStepDetails']))
                                        <div class="py-2 px-2">
                                            <div class="card-header text-center bg-white">
                                                <h4 class="mb-4 mt-4" style="font-size:1.4rem;"><b>Detalle de tu estatus</b></h4>
                                                <p class="pb-4">{!! $proceso['currentStepDetails']['promesa'] ?? '' !!}</p>
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Carrusel de imágenes --}}
                                    @if(isset($imageUrls) && count($imageUrls) > 0)
                                        @include('crud.users.partials.image-carousel', [
                                            'imageUrls' => $imageUrls,
                                            'carouselId' => "carousel-{$index}"
                                        ])
                                    @endif

                                    {{-- Información adicional (Acordeón) --}}
                                    @if(isset($proceso['currentStepDetails']['textos_adicionales']) && count($proceso['currentStepDetails']['textos_adicionales']) > 0)
                                        @include('crud.users.partials.additional-info', [
                                            'textos' => $proceso['currentStepDetails']['textos_adicionales'],
                                            'accordionId' => "accordion-{$index}"
                                        ])
                                    @endif

                                    {{-- CTAs --}}
                                    @if(isset($proceso['currentStepDetails']['ctas']) && count($proceso['currentStepDetails']['ctas']) > 0)
                                        @include('crud.users.partials.ctas', [
                                            'ctas' => $proceso['currentStepDetails']['ctas']
                                        ])
                                    @endif
                                </div>
                            @endif
                        @endforeach

                        <!-- Modal Resumen de Paso -->
                        <div class="modal fade" id="modalResumenPaso" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">

                                    <div class="modal-header">
                                        <h5 class="modal-title" id="tituloPaso"></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>

                                    <div class="modal-body" id="descripcionPaso"></div>

                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                            Cerrar
                                        </button>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <script>
                        document.addEventListener("DOMContentLoaded", function () {

                            const modalEl = document.getElementById("modalResumenPaso");
                            const modal = new bootstrap.Modal(modalEl);

                            document.querySelectorAll(".progress-step.active, .progress-step.warningesfera")
                                .forEach(step => {

                                    step.addEventListener("click", function () {
                                        modalEl.dataset.titulo = `${this.dataset.nombre}`;
                                        modalEl.dataset.descripcion = this.dataset.descripcion;

                                        modal.show();
                                    });

                                });

                            modalEl.addEventListener("shown.bs.modal", function () {
                                document.getElementById("tituloPaso").innerHTML = modalEl.dataset.titulo;
                                document.getElementById("descripcionPaso").innerHTML = modalEl.dataset.descripcion;
                            });

                        });
                        </script>
                    @else
                        @include('crud.users.partials.no-status-available')
                    @endif
                </div>

                @push('scripts')
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            // Auto-scroll a paso activo
                            document.querySelectorAll('.progress-scroll-container').forEach(container => {
                                const activeSteps = container.querySelectorAll('.progress-step.active');

                                if (activeSteps.length > 0) {
                                    const lastActive = activeSteps[activeSteps.length - 1];
                                    const containerWidth = container.clientWidth;
                                    const stepRect = lastActive.getBoundingClientRect();
                                    const containerRect = container.getBoundingClientRect();
                                    const stepCenter = stepRect.left - containerRect.left + stepRect.width / 2;

                                    container.scrollTo({
                                        left: stepCenter - containerWidth / 2 + container.scrollLeft,
                                        behavior: 'smooth'
                                    });
                                }
                            });

                            // Auto-play carruseles
                            const carousels = document.querySelectorAll('.carousel');
                            carousels.forEach(carousel => {
                                new bootstrap.Carousel(carousel, {
                                    interval: 3000,
                                    ride: 'carousel'
                                });
                            });
                        });
                    </script>
                @endpush


                <div class="tab-pane fade show" id="personal_data" role="tabpanel" aria-labelledby="personal-data-tab">
                    <form id="datos-personales-form">
                        @csrf
                        <input type="hidden" id="id" name="id" value="{{$user->id}}" />
                        <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                            <span class="ctvSefar block text-indigo-600">Datos Personales</span>
                        </h2>
                        <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="nombres" name="nombres" value="{{ old('nombres', $user->nombres) }}" placeholder="Ingrese su nombre">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="apellido" class="block text-sm font-medium text-gray-700">Apellido</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="apellidos" name="apellidos" value="{{ old('apellidos', $user->apellidos) }}" placeholder="Ingrese su apellido">
                            </div>
                        </div>
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            @php
                                $fechaNacimiento = '';
                                if ($user->date_of_birth) {
                                    try {
                                        // Intenta primero el formato europeo
                                        $fechaNacimiento = \Carbon\Carbon::createFromFormat('d/m/Y', $user->date_of_birth)->format('Y-m-d');
                                    } catch (\Exception $e) {
                                        try {
                                            // Si falla, intenta parseo genérico (Y-m-d, etc.)
                                            $fechaNacimiento = \Carbon\Carbon::parse($user->date_of_birth)->format('Y-m-d');
                                        } catch (\Exception $e2) {
                                            $fechaNacimiento = '';
                                        }
                                    }
                                }
                            @endphp
                            <div style="flex: 1;" class="mb-3">
                                <label for="date_of_birth" class="block text-sm font-medium text-gray-700">Fecha de Nacimiento</label>
                                <input
                                    type="date"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="date_of_birth"
                                    name="date_of_birth"
                                    value="{{ old('date_of_birth', $fechaNacimiento) }}"
                                    placeholder="Ingrese fecha de nacimiento"
                                >
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="genero" class="block text-sm font-medium text-gray-700">Genero</label>
                                <select
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="genero"
                                    name="genero">

                                    <option value="" {{ old('genero', $user->genero ?? '') === '' ? 'selected' : '' }}></option>
                                    <option value="FEMENINO / FEMALE"
                                        {{ old('genero', $user->genero ?? '') === 'FEMENINO' || old('genero', $user->genero ?? '') === 'FEMENINO / FEMALE' ? 'selected' : '' }}>
                                        FEMENINO / FEMALE
                                    </option>
                                    <option value="MASCULINO / MALE"
                                        {{ old('genero', $user->genero ?? '') === 'MASCULINO' || old('genero', $user->genero ?? '') === 'MASCULINO / MALE' ? 'selected' : '' }}>
                                        MASCULINO / MALE
                                    </option>
                                    <option value="OTROS / OTHERS"
                                        {{ in_array((string) old('genero', $user->genero ?? ''), ['OTRO', 'OTROS', 'OTROS / OTHERS', 'OTROS / OT']) ? 'selected' : '' }}>
                                        OTROS / OTHERS
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="veces_casado" class="block text-sm font-medium text-gray-700">Veces Casado</label>
                                <input
                                    type="number"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="veces_casado"
                                    name="veces_casado"
                                    value="{{ old('veces_casado', $user->veces_casado ?? '') }}"
                                    placeholder="Ingrese la cantidad de veces casado">
                            </div>

                            <div style="flex: 1;" class="mb-3">
                                <label for="edo_civil" class="block text-sm font-medium text-gray-700">Estado Civil</label>
                                <select
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="edo_civil"
                                    name="edo_civil">
                                    <option value="" {{ old('edo_civil', $user->edo_civil ?? '') === '' ? 'selected' : '' }}></option>
                                    <option value="SOLTERO (A)" {{ old('edo_civil', $user->edo_civil ?? '') === 'SOLTERO (A)' ? 'selected' : '' }}>SOLTERO (A)</option>
                                    <option value="CASADO (A)" {{ old('edo_civil', $user->edo_civil ?? '') === 'CASADO (A)' ? 'selected' : '' }}>CASADO (A)</option>
                                    <option value="DIVORCIADO (A)" {{ old('edo_civil', $user->edo_civil ?? '') === 'DIVORCIADO (A)' ? 'selected' : '' }}>DIVORCIADO (A)</option>
                                    <option value="VIUDO (A)" {{ old('edo_civil', $user->edo_civil ?? '') === 'VIUDO (A)' ? 'selected' : '' }}>VIUDO (A)</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="email" class="block text-sm font-medium text-gray-700">Correo</label>
                                <input type="email" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="email" name="email" value="{{ old('email', $user->email) }}" placeholder="Ingrese su correo electrónico">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="telefono" class="block text-sm font-medium text-gray-700">Teléfono</label>
                                <input type="tel" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="Ingrese su número de teléfono">
                            </div>
                        </div>

                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="tiene_hermanos" class="block text-sm font-medium text-gray-700">¿Tiene Hermanos realizando procesos en Sefar Universal?</label>
                                <select
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="tiene_hermanos"
                                    name="tiene_hermanos">
                                    <option value="" {{ old('tiene_hermanos', $user->tiene_hermanos ?? '') == '' ? 'selected' : '' }}>Seleccione una opción</option>
                                    <option value="0" {{ old('tiene_hermanos', $user->tiene_hermanos ?? '') == 0 ? 'selected' : '' }}>No</option>
                                    <option value="1" {{ old('tiene_hermanos', $user->tiene_hermanos ?? '') == 1 ? 'selected' : '' }}>Si</option>
                                </select>
                            </div>
                        </div>
                        @if(auth()->user()->roles[0]->id == 1 || auth()->user()->roles[0]->id == 15 || auth()->user()->roles[0]->id == 16)
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="detalle_de_la_solicitud" class="block text-sm font-medium text-gray-700">Detalles de la solicitud</label>
                                <textarea
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="detalle_de_la_solicitud"
                                    name="detalle_de_la_solicitud"
                                    rows="3"
                                    placeholder="Ingrese Detalles de la Solicitud"
                                >{{ old('detalle_de_la_solicitud', $user->detalle_de_la_solicitud) }}</textarea>
                            </div>
                        </div>
                        @endif
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="pay" class="block text-sm font-medium text-gray-700">{{ __('Payment status') }} del registro</label>
                                @if(auth()->user()->roles[0]->id == 1)
                                    <select name="pay" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        @if ($user->pay == 0)
                                            <option selected value=0>No ha pagado</option>
                                        @else
                                            <option value=0>No ha pagado</option>
                                        @endif

                                        @if ($user->pay == 1)
                                            <option selected value=1>Pagó</option>
                                        @else
                                            <option value=1>Pagó</option>
                                        @endif

                                        @if ($user->pay == 2)
                                            <option selected value=2>Pagó y completó información</option>
                                        @else
                                            <option value=2>Pagó y completó información</option>
                                        @endif

                                        @if ($user->pay == 3)
                                            <option selected value=3>Pagó pero no se registró en Hubspot</option>
                                        @else
                                            <option value=3>Pagó pero no se registró en Hubspot</option>
                                        @endif
                                    </select>
                                @else
                                    <p>
                                        @if ($user->pay == 0)
                                            No ha pagado
                                        @endif

                                        @if ($user->pay == 1)
                                            Pagó
                                        @endif

                                        @if ($user->pay == 2)
                                            Pagó y completó información
                                        @endif

                                        @if ($user->pay == 3)
                                            Pagó pero no se registró en Hubspot
                                        @endif
                                    </p>
                                @endif
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="contrato" class="block text-sm font-medium text-gray-700">Servicio Principal</label>
                                @if(auth()->user()->roles[0]->id == 1)
                                <select name="servicio" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option></option>
                                    @foreach ($servicios as $servicio)
                                        <option {{ $user->servicio == $servicio->id_hubspot ? 'selected' : '' }} > {{$servicio->id_hubspot}}</option>
                                    @endforeach
                                </select>
                                @else
                                    <p>
                                        {{$user->servicio}}
                                    </p>
                                @endif
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="contrato" class="block text-sm font-medium text-gray-700">Contrato</label>
                                @if(auth()->user()->roles[0]->id == 1)
                                    <select name="contrato" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        @if ($user->contrato == 0)
                                            <option selected value=0>No ha firmado contrato</option>
                                        @else
                                            <option value=0>No ha firmado contrato</option>
                                        @endif

                                        @if ($user->contrato == 1)
                                            <option selected value=1>Firmó Contrato</option>
                                        @else
                                            <option value=1>Firmó Contrato</option>
                                        @endif
                                    </select>
                                @else
                                    <p>
                                        @if ($user->pay == 0)
                                            No ha firmado contrato
                                        @endif

                                        @if ($user->pay == 1)
                                            Firmó Contrato
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>

                        <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                            <span class="ctvSefar block text-indigo-600">
                            Datos del Pasaporte
                            </span>
                        </h2>

                        <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="pasaporte" class="block text-sm font-medium text-gray-700">Número de Pasaporte</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="passport" name="passport" value="{{ old('passport', $user->passport) }}" placeholder="Ingrese su número de pasaporte">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="nombre_en_pasaporte" class="block text-sm font-medium text-gray-700">Nombre en Pasaporte</label>
                                <input
                                    type="text"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="nombre_en_pasaporte"
                                    name="nombre_en_pasaporte"
                                    value="{{ old('nombre_en_pasaporte', $user->nombre_en_pasaporte ?? '') }}"
                                    placeholder="Ingrese el nombre tal como aparece en el pasaporte">
                            </div>

                            <!-- Campo País de Expedición del Pasaporte -->
                            <div style="flex: 1;" class="mb-3">
                                <label for="pais_de_expedicion_del_pasaporte" class="block text-sm font-medium text-gray-700">País de Expedición del Pasaporte</label>
                                <input
                                    type="text"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="pais_de_expedicion_del_pasaporte"
                                    name="pais_de_expedicion_del_pasaporte"
                                    value="{{ old('pais_de_expedicion_del_pasaporte', $user->pais_de_expedicion_del_pasaporte ?? '') }}"
                                    placeholder="Ingrese el país de expedición del pasaporte">
                            </div>

                            <!-- Campo Fecha de Caducidad del Pasaporte -->
                            <div style="flex: 1;" class="mb-3">
                                <label for="fecha_de_caducidad_del_pasaporte" class="block text-sm font-medium text-gray-700">Fecha de Caducidad del Pasaporte</label>
                                <input
                                    type="date"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="fecha_de_caducidad_del_pasaporte"
                                    name="fecha_de_caducidad_del_pasaporte"
                                    value="{{ old('fecha_de_caducidad_del_pasaporte', \Carbon\Carbon::parse($user->fecha_de_caducidad_del_pasaporte ?? now())->format('Y-m-d')) }}"
                                    placeholder="Ingrese la fecha de caducidad del pasaporte">
                            </div>
                        </div>

                        <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                            <span class="ctvSefar block text-indigo-600">Direcciones</span>
                        </h2>
                        <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="pais_de_residencia" class="block text-sm font-medium text-gray-700">Pais de Residencia</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="pais_de_residencia" name="pais_de_residencia" value="{{ old('pais_de_residencia', $user->pais_de_residencia) }}" placeholder="Ingrese su Pais de Residencia">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="city" class="block text-sm font-medium text-gray-700">Ciudad de Residencia</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="city" name="city" value="{{ old('city', $user->city) }}" placeholder="Ingrese su ciudad de Residencia">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="anos_en_residencia_actual" class="block text-sm font-medium text-gray-700">Años en Residencia Actual</label>
                                <input
                                    type="text"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="anos_en_residencia_actual"
                                    name="anos_en_residencia_actual"
                                    value="{{ old('anos_en_residencia_actual', $user->anos_en_residencia_actual ?? '') }}"
                                    placeholder="Ingrese los años en la residencia actual">
                            </div>
                        </div>
                        <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="address" class="block text-sm font-medium text-gray-700">Direccion actual</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="address" name="address" value="{{ old('address', $user->address) }}" placeholder="Ingrese su Dirección actual">
                            </div>
                        </div>

                        <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="pais_de_nacimiento" class="block text-sm font-medium text-gray-700">Pais de Nacimiento</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="pais_de_nacimiento" name="pais_de_nacimiento" value="{{ old('pais_de_nacimiento', $user->pais_de_nacimiento) }}" placeholder="Ingrese su País de Nacimiento">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="ciudad_de_nacimiento" class="block text-sm font-medium text-gray-700">Ciudad de Nacimiento</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="ciudad_de_nacimiento" name="ciudad_de_nacimiento" value="{{ old('ciudad_de_nacimiento', $user->ciudad_de_nacimiento) }}" placeholder="Ingrese su Ciudad de Nacimiento">
                            </div>
                        </div>
                        <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="direccion_en_el_pais_de_origen" class="block text-sm font-medium text-gray-700">Dirección en el País de Origen</label>
                                <input
                                    type="text"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="direccion_en_el_pais_de_origen"
                                    name="direccion_en_el_pais_de_origen"
                                    value="{{ old('direccion_en_el_pais_de_origen', $user->direccion_en_el_pais_de_origen ?? '') }}"
                                    placeholder="Ingrese la dirección en el país de origen">
                            </div>
                        </div>
                        <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="paises_donde_ha_residido_en_los_ultimos_5_anos" class="block text-sm font-medium text-gray-700">Países donde ha residido en los últimos 5 años</label>
                                <textarea
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="paises_donde_ha_residido_en_los_ultimos_5_anos"
                                    name="paises_donde_ha_residido_en_los_ultimos_5_anos"
                                    rows="3"
                                    placeholder="Ingrese los países donde ha residido en los últimos 5 años">{{ old('paises_donde_ha_residido_en_los_ultimos_5_anos', $user->paises_donde_ha_residido_en_los_ultimos_5_anos ?? '') }}</textarea>
                            </div>
                        </div>

                        @if(auth()->user()->roles[0]->id == 1 || auth()->user()->roles[0]->id == 15 || auth()->user()->roles[0]->id == 4 || auth()->user()->roles[0]->id == 17)

                            <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                                <span class="ctvSefar block text-indigo-600">Otros datos personales</span>
                            </h2>
                            <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="conyuge_interesado_en_proceso" class="block text-sm font-medium text-gray-700">Conyuge Interesado</label>
                                    <input
                                        type="checkbox"
                                        class="mt-1 py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        id="conyuge_interesado_en_proceso"
                                        name="conyuge_interesado_en_proceso"
                                        style="width: 33.6px!important; height: 33.6px!important;"
                                        value="1"
                                        {{ old('conyuge_interesado_en_proceso', $user->conyuge_interesado_en_proceso ? 'checked' : '') }}
                                    />
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="nombre_completo_del_conyuge" class="block text-sm font-medium text-gray-700">Nombre de Conyuge</label>
                                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="nombre_completo_del_conyuge" name="nombre_completo_del_conyuge" value="{{ old('nombre_completo_del_conyuge', $user->nombre_completo_del_conyuge) }}" placeholder="Ingrese su Ciudad de Nacimiento">
                                </div>
                            </div>
                            <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <!-- Campo Partida de Nacimiento Simple -->
                                <div style="flex: 1;" class="mb-3">
                                    <label for="partida_de_nacimiento_simple" class="block text-sm font-medium text-gray-700">Partida de Nacimiento Simple</label>
                                    <input
                                        type="text"
                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        id="partida_de_nacimiento_simple"
                                        name="partida_de_nacimiento_simple"
                                        value="{{ old('partida_de_nacimiento_simple', $user->partida_de_nacimiento_simple ?? '') }}"
                                        placeholder="Ingrese información sobre la partida de nacimiento">
                                </div>
                            </div>
                            <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <!-- Campo Número de IBAN -->
                                <div style="flex: 1;" class="mb-3">
                                    <label for="no_de_iban" class="block text-sm font-medium text-gray-700">Número de IBAN</label>
                                    <input
                                        type="text"
                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        id="no_de_iban"
                                        name="no_de_iban"
                                        value="{{ old('no_de_iban', $user->no_de_iban ?? '') }}"
                                        placeholder="Ingrese el número de IBAN">
                                </div>

                                <!-- Campo Número de Identificación Nacional (NIF) -->
                                <div style="flex: 1;" class="mb-3">
                                    <label for="no_de_identificacion_nacional__nif_" class="block text-sm font-medium text-gray-700">Número de Identificación Nacional (NIF)</label>
                                    <input
                                        type="text"
                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        id="no_de_identificacion_nacional__nif_"
                                        name="no_de_identificacion_nacional__nif_"
                                        value="{{ old('no_de_identificacion_nacional__nif_', $user->no_de_identificacion_nacional__nif_ ?? '') }}"
                                        placeholder="Ingrese el número de identificación nacional (NIF)">
                                </div>
                            </div>

                            <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <!-- Campo Vínculo con Antepasados -->
                                <div style="flex: 1;" class="mb-3">
                                    <label class="block text-sm font-medium text-gray-700">Vínculo con Antepasados</label>
                                    @php
                                        $vinculosSeleccionados = explode(';', $user->vinculo_antepasados ?? '');
                                    @endphp
                                    <div class="mt-1">
                                        <label class="flex items-center">
                                            <input
                                                type="checkbox"
                                                name="vinculo_antepasados[]"
                                                value="Padre/Madre"
                                                {{ in_array('Padre/Madre', $vinculosSeleccionados) ? 'checked' : '' }}
                                                class="mr-2">
                                            Padre/Madre
                                        </label>
                                        <label class="flex items-center">
                                            <input
                                                type="checkbox"
                                                name="vinculo_antepasados[]"
                                                value="Abuelo(a)"
                                                {{ in_array('Abuelo(a)', $vinculosSeleccionados) ? 'checked' : '' }}
                                                class="mr-2">
                                            Abuelo(a)
                                        </label>
                                        <label class="flex items-center">
                                            <input
                                                type="checkbox"
                                                name="vinculo_antepasados[]"
                                                value="Bisabuelo(a)"
                                                {{ in_array('Bisabuelo(a)', $vinculosSeleccionados) ? 'checked' : '' }}
                                                class="mr-2">
                                            Bisabuelo(a)
                                        </label>
                                        <label class="flex items-center">
                                            <input
                                                type="checkbox"
                                                name="vinculo_antepasados[]"
                                                value="Tatarabuelo(a)"
                                                {{ in_array('Tatarabuelo(a)', $vinculosSeleccionados) ? 'checked' : '' }}
                                                class="mr-2">
                                            Tatarabuelo(a)
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                                <span class="ctvSefar block text-indigo-600">AIV</span>
                            </h2>
                            <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n6__aiv_recibido_en_espana" class="block text-sm font-medium text-gray-700">Fecha AIV recibido en España</label>
                                    <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n6__aiv_recibido_en_espana" name="n6__aiv_recibido_en_espana" value="{{ old('n6__aiv_recibido_en_espana', $user->n6__aiv_recibido_en_espana) }}" placeholder="Fecha AIV Recibido en España">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n2__aiv_notificacion_aprobado" class="block text-sm font-medium text-gray-700">AIV Notificación Aprobado</label>
                                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n2__aiv_notificacion_aprobado" name="n2__aiv_notificacion_aprobado" value="{{ old('n2__aiv_notificacion_aprobado', $user->n2__aiv_notificacion_aprobado) }}" placeholder="Ingrese AIV Notificación Aprobado">
                                </div>
                            </div>

                            <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                                <span class="ctvSefar block text-indigo-600">AACS</span>
                            </h2>
                            <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n1__aacs_introducido_asociacion" class="block text-sm font-medium text-gray-700">AACS Introducido Asociacion</label>
                                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n1__aacs_introducido_asociacion" name="n1__aacs_introducido_asociacion" value="{{ old('n1__aacs_introducido_asociacion', $user->n1__aacs_introducido_asociacion) }}" placeholder="Ingrese AACS INTRODUCIDO ASOCIACIÓN">
                                </div>
                            </div>
                            <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n2__aacs_notificacion_aprobado" class="block text-sm font-medium text-gray-700">AACS Notificacion Aprobado</label>
                                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n2__aacs_notificacion_aprobado" name="n2__aacs_notificacion_aprobado" value="{{ old('n2__aacs_notificacion_aprobado', $user->n2__aacs_notificacion_aprobado) }}" placeholder="Ingrese AACS Notificación Aprobado">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n2__fecha_aacs_notificacion_aprobado" class="block text-sm font-medium text-gray-700">Fecha AACS Notificacion Aprobado</label>
                                    <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n2__fecha_aacs_notificacion_aprobado" name="n2__fecha_aacs_notificacion_aprobado" value="{{ old('n2__fecha_aacs_notificacion_aprobado', $user->n2__fecha_aacs_notificacion_aprobado) }}" placeholder="Ingrese Fecha AACS Notificacion Aprobado">
                                </div>
                            </div>
                            <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n4__aacs_retirado_asociacion" class="block text-sm font-medium text-gray-700">AACS Retirado Asociacion</label>
                                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__aacs_retirado_asociacion" name="n4__aacs_retirado_asociacion" value="{{ old('n4__aacs_retirado_asociacion', $user->n4__aacs_retirado_asociacion) }}" placeholder="Ingrese aacs retirado asociacion">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n6__aacs_recibido_en_espana" class="block text-sm font-medium text-gray-700">AACS Recibido en España</label>
                                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n6__aacs_recibido_en_espana" name="n6__aacs_recibido_en_espana" value="{{ old('n6__aacs_recibido_en_espana', $user->n6__aacs_recibido_en_espana) }}" placeholder="AACS Recibido en España">
                                </div>
                            </div>

                            <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                                <span class="ctvSefar block text-indigo-600">CCSE</span>
                            </h2>
                            <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="ccse_archivado_espana" class="block text-sm font-medium text-gray-700">CCSE Archivado España</label>
                                    <input type="text" value="{{ old('ccse_archivado_espana', $user->ccse_archivado_espana) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="ccse_archivado_espana" name="ccse_archivado_espana" placeholder="CCSE Archivado España">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="ccse_resultado" class="block text-sm font-medium text-gray-700">CCSE Resultado</label>
                                    <input type="text" value="{{ old('ccse_resultado', $user->ccse_resultado) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="ccse_resultado" name="ccse_resultado">
                                </div>
                            </div>

                            <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                                <span class="ctvSefar block text-indigo-600">CIF</span>
                            </h2>
                            <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="cif" class="block text-sm font-medium text-gray-700">CIF</label>
                                    <input type="text" value="{{ old('cif', $user->cif) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="cif" name="cif" placeholder="CIF">
                                </div>
                            </div>

                            <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                                <span class="ctvSefar block text-indigo-600">Otros datos</span>
                            </h2>
                            <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n1__acta_notarial" class="block text-sm font-medium text-gray-700">Acta Notarial</label>
                                    <input type="text" value="{{ old('n1__acta_notarial', $user->n1__acta_notarial) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n1__acta_notarial" name="n1__acta_notarial" placeholder="Ingrese Acta Notarial">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n1__f__peticion_por_genealogia" class="block text-sm font-medium text-gray-700">Fecha Petición por Genealogía</label>
                                    <input type="date" value="{{ old('n1__f__peticion_por_genealogia', $user->n1__f__peticion_por_genealogia) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n1__f__peticion_por_genealogia" name="n1__f__peticion_por_genealogia">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n1__f__solicitado_por_genealogia" class="block text-sm font-medium text-gray-700">Fecha Solicitado por Genealogía</label>
                                    <input type="date" value="{{ old('n1__f__solicitado_por_genealogia', $user->n1__f__solicitado_por_genealogia) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n1__f__solicitado_por_genealogia" name="n1__f__solicitado_por_genealogia">
                                </div>
                            </div>

                            <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n2__f_solicitud_mayor_info" class="block text-sm font-medium text-gray-700">Fecha Solicitud Mayor Información</label>
                                    <input type="date" value="{{ old('n2__f_solicitud_mayor_info', $user->n2__f_solicitud_mayor_info) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n2__f_solicitud_mayor_info" name="n2__f_solicitud_mayor_info">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n2__f__de_solicitud_al_cliente" class="block text-sm font-medium text-gray-700">Fecha Solicitud al Cliente</label>
                                    <input type="date" value="{{ old('n2__f__de_solicitud_al_cliente', $user->n2__f__de_solicitud_al_cliente) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n2__f__de_solicitud_al_cliente" name="n2__f__de_solicitud_al_cliente">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n3__estatus_de_nacionalidad" class="block text-sm font-medium text-gray-700">Estatus de Nacionalidad</label>
                                    <select class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n3__estatus_de_nacionalidad" name="n3__estatus_de_nacionalidad">
                                        <option value="" {{ old('n3__estatus_de_nacionalidad', $user->n3__estatus_de_nacionalidad ?? '') === '' ? 'selected' : '' }}></option>
                                        <option value="Concedida" {{ old('n3__estatus_de_nacionalidad', $user->n3__estatus_de_nacionalidad ?? '') === 'Concedida' ? 'selected' : '' }}>Concedida</option>
                                        <option value="En Tramitación" {{ old('n3__estatus_de_nacionalidad', $user->n3__estatus_de_nacionalidad ?? '') === 'En Tramitación' ? 'selected' : '' }}>En Tramitación</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n3__f___recordatorio_filiacion" class="block text-sm font-medium text-gray-700">Fecha Recordatorio Filiación</label>
                                    <input type="date" value="{{ old('n3__f___recordatorio_filiacion', $user->n3__f___recordatorio_filiacion) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n3__f___recordatorio_filiacion" name="n3__f___recordatorio_filiacion">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n3__fcje_registro" class="block text-sm font-medium text-gray-700">FCJE Registro</label>
                                    <input type="date" value="{{ old('n3__fcje_registro', $user->n3__fcje_registro) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n3__fcje_registro" name="n3__fcje_registro">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n3__fecha_de_recordatorio" class="block text-sm font-medium text-gray-700">Fecha de Recordatorio</label>
                                    <input type="date" value="{{ old('n3__fecha_de_recordatorio', $user->n3__fecha_de_recordatorio) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n3__fecha_de_recordatorio" name="n3__fecha_de_recordatorio">
                                </div>
                            </div>

                            <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n4__aacs_retirado_asociacion" class="block text-sm font-medium text-gray-700">AACS Retirado Asociación</label>
                                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__aacs_retirado_asociacion" name="n4__aacs_retirado_asociacion" value="{{ old('n4__aacs_retirado_asociacion', $user->n4__aacs_retirado_asociacion ?? '') }}" placeholder="Ingrese AACS Retirado Asociación">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n4__f__entregado_genealogia" class="block text-sm font-medium text-gray-700">Fecha Entregado Genealogía</label>
                                    <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__f__entregado_genealogia" name="n4__f__entregado_genealogia" value="{{ old('n4__f__entregado_genealogia', $user->n4__f__entregado_genealogia ?? '') }}">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n4__f__enviada_a_genealogia" class="block text-sm font-medium text-gray-700">Fecha Enviada a Genealogía</label>
                                    <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__f__enviada_a_genealogia" name="n4__f__enviada_a_genealogia" value="{{ old('n4__f__enviada_a_genealogia', $user->n4__f__enviada_a_genealogia ?? '') }}">
                                </div>
                            </div>

                            <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n4__fcje_certifi__descargado" class="block text-sm font-medium text-gray-700">FCJE Certificado Descargado</label>
                                    <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__fcje_certifi__descargado" name="n4__fcje_certifi__descargado" value="{{ old('n4__fcje_certifi__descargado', $user->n4__fcje_certifi__descargado ?? '') }}">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n4__otros_nombres" class="block text-sm font-medium text-gray-700">Otros Nombres</label>
                                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__otros_nombres" name="n4__otros_nombres" value="{{ old('n4__otros_nombres', $user->n4__otros_nombres ?? '') }}" placeholder="Ingrese Otros Nombres">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n5__fecha_de_registro" class="block text-sm font-medium text-gray-700">Fecha de Registro</label>
                                    <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n5__fecha_de_registro" name="n5__fecha_de_registro" value="{{ old('n5__fecha_de_registro', $user->n5__fecha_de_registro ?? '') }}">
                                </div>
                            </div>


                            <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="estado_de_datos_y_documentos_de_los_antepasados" class="block text-sm font-medium text-gray-700">Estado de datos y documentos de antepasados</label>
                                    <select class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="estado_de_datos_y_documentos_de_los_antepasados" name="estado_de_datos_y_documentos_de_los_antepasados">
                                        <option value="" {{ old('estado_de_datos_y_documentos_de_los_antepasados', $user->estado_de_datos_y_documentos_de_los_antepasados ?? '') === '' ? 'selected' : '' }}></option>
                                        <option value="Conoce los datos y tiene los documentos" {{ old('estado_de_datos_y_documentos_de_los_antepasados', $user->estado_de_datos_y_documentos_de_los_antepasados ?? '') === 'Conoce los datos y tiene los documentos' ? 'selected' : '' }}>Conoce los datos y tiene los documentos</option>
                                        <option value="Conoce los datos, pero no tiene los documentos" {{ old('estado_de_datos_y_documentos_de_los_antepasados', $user->estado_de_datos_y_documentos_de_los_antepasados ?? '') === 'Conoce los datos, pero no tiene los documentos' ? 'selected' : '' }}>Conoce los datos, pero no tiene los documentos</option>
                                        <option value="No conoce los datos" {{ old('estado_de_datos_y_documentos_de_los_antepasados', $user->estado_de_datos_y_documentos_de_los_antepasados ?? '') === 'No conoce los datos' ? 'selected' : '' }}>No conoce los datos</option>
                                    </select>
                                </div>
                            </div>

                        @endif

                        @if(auth()->user()->roles[0]->id == 1 || auth()->user()->roles[0]->id == 15 || auth()->user()->roles[0]->id == 4 || auth()->user()->roles[0]->id == 17)
                            <button type="button" id="guardar-datos" class="cfrSefar btn btn-primary mt-3">Guardar</button>
                        @endif
                    </form>
                </div>

                <div class="tab-pane fade" id="mypassword" role="tabpanel" aria-labelledby="mypassword-tab">
                    <form id="clientChangePasswordForm">
                        @csrf
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                                <input class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="password" name="password" type="password" placeholder="Ingrese su contraseña">
                            </div>
                        </div>
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="repeat_password" class="block text-sm font-medium text-gray-700">Repetir Contraseña</label>
                                <input class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="repeat_password" name="repeat_password" type="password" placeholder="Repite tu contraseña">
                            </div>
                        </div>
                        <button type="button" id="clientSubmitButton" class="cfrSefar btn btn-primary mt-3">Guardar</button>
                    </form>
                </div>

                <div class="tab-pane fade" id="adminchangepassword" role="tabpanel" aria-labelledby="adminchangepassword-tab">
                    <form id="adminChangePasswordForm">
                        @csrf
                        <input type="hidden" id="id" name="id" value="{{ $user->id }}">
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                                <input class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="password" name="password" type="password" placeholder="Ingrese su contraseña">
                            </div>
                        </div>
                        <button type="button" id="submitButton" class="cfrSefar btn btn-primary mt-3">Guardar</button>
                    </form>
                </div>

                <div class="tab-pane fade" id="familiars" role="tabpanel" aria-labelledby="familiars-tab">
                    <center>
                        @if(auth()->user()->roles[0]->id == 5)
                        <a href="/tree/" class="btn btn-primary mb-3">
                            Ir al Arbol
                        </a>
                        @else
                        <a href="/tree/{{$user->passport}}" class="btn btn-primary mb-3">
                            Ir al Arbol
                        </a>
                        @endif
                    </center>

                    @if(isset($columnasparatabla) && count($columnasparatabla) > 0)
                        <table id="familiarsTable" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Parentesco</th>
                                    <th>Generación</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $totalMostrados = 0; @endphp
                                @foreach ($columnasparatabla as $generacion => $grupo)
                                    @foreach ($grupo as $persona)
                                        @php
                                            // Condiciones para mostrar:
                                            // 1. showbtn debe ser 1 o 2 (no 0)
                                            // 2. Debe tener nombre (no "Sin nombre")
                                            $tieneNombre = !empty($persona['Nombres']) &&
                                                        $persona['Nombres'] !== 'Sin nombre';

                                            $showbtnValido = isset($persona['showbtn']) &&
                                                            in_array($persona['showbtn'], [1, 2]);

                                            $mostrar = $tieneNombre && $showbtnValido;
                                        @endphp

                                        @if($mostrar)
                                            <tr>
                                                <td>{{ $persona['Nombres'] ?? '' }} {{ $persona['Apellidos'] ?? '' }}</td>
                                                <td>{{ $persona['parentesco'] ?? 'N/A' }}</td>
                                                <td>{{ $generacion + 1 }}</td>
                                            </tr>
                                            @php $totalMostrados++; @endphp
                                        @endif
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>

                        <div class="alert alert-info mt-3">
                            Total de familiares registrados: {{ $totalMostrados }}
                        </div>
                    @else
                        <div class="alert alert-warning">
                            No se encontraron datos de familiares.
                        </div>
                    @endif
                </div>

                <div class="tab-pane fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">
                    <table id="documentsTable" class="min-w-full divide-y divide-gray-200 w-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col">Nombre del Archivo</th>
                                <th scope="col">Ver Archivo</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ( $archivos as $archivo )
                                <tr>
                                    <td>{{$archivo["file"]}}</td>
                                    <td>
                                        <a href="/viewfile/{{$archivo["id"]}}" target="_blank" class="btn btn-primary">
                                            <i class="fas fa-file"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="payments" role="tabpanel" aria-labelledby="payments-tab">

                    <table id="paymentsTable" class="min-w-full divide-y divide-gray-200 w-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"># de Comprobante</th>
                                <th scope="col">Método de pago</th>
                                <th scope="col">Servicios contratados</th>
                                <th scope="col">Monto pagado</th>
                                <th scope="col">Ver Comprobante</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ( $facturas as $num => $factura )
                                <tr>
                                    <td>{{$num + 1}}</td>
                                    <td>
                                        @if ($factura["met"] == "stripe")
                                            Tarjeta de Crédito/Débito
                                        @elseif ($factura["met"] == "cupon")
                                            Cupón
                                        @elseif ($factura["met"] == "paypal")
                                            PayPal
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $monto = 0;
                                            $totalCompras = count($factura["compras"]);
                                        @endphp
                                        @foreach($factura["compras"] as $index => $compra)
                                            @php
                                                $monto += $compra["monto"];
                                            @endphp
                                            @if($compra["servicio_hs_id"])
                                                {{$compra["servicio_hs_id"]}}
                                            @else
                                                @if ($compra["phasenum"]<10)
                                                    Pago Fase {{$compra["phasenum"]}}
                                                @elseif($compra["phasenum"]==99)
                                                    Pago FCJE/CIL
                                                @elseif($compra["phasenum"]==98)
                                                    Pago Carta de Naturaleza
                                                @endif
                                            @endif
                                            @if($index < $totalCompras - 1)
                                                <br> <!-- Agregar salto de línea si no es el último -->
                                            @endif
                                        @endforeach
                                    </td>
                                    <td>
                                        {{$monto}} €
                                    </td>
                                    <td>
                                        @if(auth()->user()->roles[0]->id == 1)
                                            <a href="{{ route('viewcomprobante', ['id' => $factura['id']]) }}" target="_blank" class="btn btn-primary">
                                                <i class="fas fa-receipt"></i>
                                            </a>
                                        @elseif(auth()->user()->roles[0]->id == 5)
                                            <a href="{{ route('viewcomprobantecliente', ['id' => $factura['id']]) }}" target="_blank" class="btn btn-primary">
                                                <i class="fas fa-receipt"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="paymentspen" role="tabpanel" aria-labelledby="payments-tab">

                    <table id="paymentsPenTable" class="min-w-full divide-y divide-gray-200 w-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col">Descripción</th>
                                <th scope="col">Monto</th>
                                @if(auth()->user()->roles[0]->id == 5)
                                <th scope="col">Acción</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($comprasSinDealNoPagadas as $compra)
                                <tr>
                                    <td>{{ $compra->descripcion }}</td>
                                    <td>{{ $compra->monto }} €</td>
                                    @if(auth()->user()->roles[0]->id == 5)
                                    <td>
                                        <a href="/pay" class="btn btn-warning">
                                            <i class="fas fa-credit-card"></i> Pagar ahora
                                        </a>
                                    </td>
                                    @endif
                                </tr>
                            @endforeach

                            @foreach($comprasConDealNoPagadas as $compra)
                                <tr>
                                    <td>{{ $compra->descripcion }}</td>
                                    <td>{{ $compra->monto }} €</td>
                                    @if(auth()->user()->roles[0]->id == 5)
                                    <td>
                                        <form action="/payfases" method="POST">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $compra->id }}">
                                            <button type="submit" class="btn btn-danger">
                                                <i class="fas fa-credit-card"></i> Pagar ahora
                                            </button>
                                        </form>
                                    </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="etiquetado" role="tabpanel" aria-labelledby="etiquetado-tab">
                    @if ($boardId != 0)
                    <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4 mb-4">
                        <span class="ctvSefar block text-indigo-600">Tablero actual: {{ $boardName }}</span>
                    </h2>

                    <form id="dynamicForm" method="POST">
                        @csrf

                        <input name='boardId' type="hidden" value='{{$boardId}}'>

                        <input name='user_id' type="hidden" value='{{$user->id}}'>
                        <!-- Ejemplo de grid con máximo 3 columnas -->

                        <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                            @foreach ($mondayFormBuilder as $field)
                                @if (in_array($field['type'], [
                                    "name", "subtasks", "auto_number", "progress", "creation_log", "link", "integration", "item_id", "formula", "board_relation", "mirror", "email"
                                ]))
                                    @continue
                                @endif

                                @if (in_array($field['title'], [
                                    "No. PASAPORTE", "FECHA NACIMIENTO", "PADRE", "MADRE", "Record ID"
                                ]))
                                    @continue
                                @endif

                                @if ($field['type'] === 'long_text')
                                    <!-- Textarea abarca toda la fila -->
                                    <div style="flex: 1 1 100%;" class="mb-3">
                                        <label for="{{ $field['column_id'] }}" class="block text-sm font-medium text-gray-700">
                                            {{ $field['title'] }}
                                        </label>
                                        <textarea
                                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            id="{{ $field['column_id'] }}"
                                            name="{{ $field['column_id'] }}"
                                            rows="3"
                                            placeholder="Ingrese {{ strtolower($field['title']) }}"
                                        >{{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') }}</textarea>
                                    </div>
                                @else
                                    <!-- Otros campos -->
                                    <div style="flex: 1 1 calc(33.33% - 16px);" class="mb-3">
                                        <label for="{{ $field['column_id'] }}" class="block text-sm font-medium text-gray-700">
                                            {{ $field['title'] }}
                                        </label>

                                        @switch($field['type'])
                                            @case('text')
                                                <input
                                                    type="text"
                                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                    id="{{ $field['column_id'] }}"
                                                    name="{{ $field['column_id'] }}"
                                                    value="{{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') }}"
                                                    placeholder="Ingrese {{ strtolower($field['title']) }}"
                                                >
                                                @break

                                            @case('date')
                                                <input
                                                    type="date"
                                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                    id="{{ $field['column_id'] }}"
                                                    name="{{ $field['column_id'] }}"
                                                    value="{{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') }}"
                                                >
                                                @break

                                            @case('people')
                                                <select
                                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                    id="{{ $field['column_id'] }}"
                                                    name="{{ $field['column_id'] }}"
                                                >
                                                    <option value="" disabled selected>Seleccione {{ strtolower($field['title']) }}</option>
                                                    @foreach ($usuariosMonday as $usuario)
                                                        <option value="{{ $usuario['email'] }}"
                                                            {{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') == $usuario['name'] ? 'selected' : '' }}>
                                                            {{ $usuario['name'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @break

                                            @case('dropdown')
                                                @if (!($field['title'] == "ETIQUETAS" || $field['title'] == "ETIQUETA"))
                                                <select
                                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                    id="{{ $field['column_id'] }}"
                                                    name="{{ $field['column_id'] }}"
                                                >
                                                    <option value="" disabled selected>Seleccione {{ strtolower($field['title']) }}</option>
                                                    @foreach ($field['settings']['labels'] ?? [] as $option)
                                                        <option value="{{ $option['id'] }}"
                                                            {{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') == $option['name'] ? 'selected' : '' }}>
                                                            {{ $option['name'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @else
                                                    <!-- Incluir Tagify CSS -->
                                                    <link href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css" rel="stylesheet" type="text/css" />

                                                    <!-- Campo de entrada para Tagify -->
                                                    <input
                                                        style="margin:0;"
                                                        class="block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                        id="{{ $field['column_id'] }}"
                                                        name="{{ $field['column_id'] }}"
                                                        value="{{ isset($dataMonday[$field['column_id']]) ? $dataMonday[$field['column_id']] : '' }}"
                                                    />

                                                    <!-- Incluir Tagify JS -->
                                                    <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.min.js"></script>

                                                    <!-- Inicializar Tagify -->
                                                    <script>
                                                        document.addEventListener('DOMContentLoaded', function() {
                                                            const input = document.getElementById('{{ $field['column_id'] }}');

                                                            // Convertir el input en un componente de etiquetas
                                                            const tagify = new Tagify(input, {
                                                                whitelist: [
                                                                    @foreach ($field['settings']['labels'] ?? [] as $option)
                                                                        { id: "{{ $option['id'] }}", value: "{{ $option['name'] }}" },
                                                                    @endforeach
                                                                ],
                                                                dropdown: {
                                                                    enabled: 1, // Mostrar dropdown con sugerencias
                                                                    maxItems: 10, // Máximo de sugerencias visibles
                                                                },
                                                                enforceWhitelist: true, // Solo permitir opciones de la lista blanca
                                                            });

                                                            // Pre-seleccionar valores si existen
                                                            const selectedValues = "{{ isset($dataMonday[$field['column_id']]) ? $dataMonday[$field['column_id']] : '' }}";
                                                            if (selectedValues) {
                                                                tagify.addTags(selectedValues.split(','));
                                                            }

                                                            // Asegurar que el valor enviado sea una cadena separada por comas
                                                            input.closest('form').addEventListener('submit', function() {
                                                                const tags = tagify.value.map(tag => tag.value).join(',');
                                                                input.value = tags; // Actualizar el valor del input antes de enviar el formulario
                                                            });
                                                        });
                                                    </script>
                                                @endif
                                                @break

                                            @case('status')
                                                <select
                                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                    id="{{ $field['column_id'] }}"
                                                    name="{{ $field['column_id'] }}"
                                                >
                                                    <option value="" disabled selected>Seleccione {{ strtolower($field['title']) }}</option>
                                                    @foreach ($field['settings']['labels'] ?? [] as $key => $label)
                                                        <option value="{{ $label }}"
                                                            {{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') == $label ? 'selected' : '' }}>
                                                            {{ $label }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @break

                                            @default
                                                <input
                                                    type="text"
                                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                    id="{{ $field['column_id'] }}"
                                                    name="{{ $field['column_id'] }}"
                                                    value="{{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') }}"
                                                    placeholder="Ingrese {{ strtolower($field['title']) }}"
                                                >
                                        @endswitch
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <!-- Botón de envío -->
                        <div class="mt-3">
                            <button type="button" id="etiquetadosend" class="bg-indigo-600 text-white px-4 py-2 rounded-md">
                                Guardar
                            </button>
                        </div>
                    </form>
                    @else
                        <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4 mb-4">
                            <span class="ctvSefar block text-indigo-600">Este cliente no se encuentra en Monday</span>
                        </h2>
                    @endif
                </div>

                <div class="tab-pane fade" id="negocios" role="tabpanel" aria-labelledby="negocios-tab">
                    <table id="dealsTable" class="min-w-full divide-y divide-gray-200 w-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col">Nombre del Negocio</th>
                                <th scope="col">Ver info</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ( $negocios as $negocio )
                                <tr>
                                    <td>{{$negocio["servicio_solicitado2"]}}<br>{!!$negocio["hubspot_id"] ? "<small>Se encuentra en <b><a target='_blank' href='https://app.hubspot.com/contacts/20053496/record/0-3/".$negocio['hubspot_id']."'>Hubspot</a></b></small>" : ''!!}{!! $negocio["teamleader_id"] ? "<small> y en <b><a target='_blank' href='https://focus.teamleader.eu/web/projects/".$negocio['teamleader_id']."'>Teamleader</a></b></small>" : '' !!}</td>
                                    <td>
                                        <a href="/deal/{{$negocio['id']}}/edit" target="_blank" class="btn btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($rolId !== 5)
                <div class="tab-pane fade" id="teamleader-migration" role="tabpanel" aria-labelledby="teamleader-migration-tab">
                    @php
                        $tlContact = $teamleaderMigration['contact'] ?? null;
                        $tlSummary = $teamleaderMigration['summary'] ?? [];
                        $tlDeals = $teamleaderMigration['deals'] ?? collect();
                        $tlProjects = $teamleaderMigration['projects'] ?? collect();
                        $tlInvoices = $teamleaderMigration['invoices'] ?? collect();
                        $tlDocuments = $teamleaderMigration['documents'] ?? collect();
                        $tlOtherContacts = $teamleaderMigration['other_contacts'] ?? collect();
                        $tlMatchLabels = $teamleaderMigration['match_labels'] ?? [];
                    @endphp

                    <div class="mb-3">
                        <h3 class="text-xl font-bold text-gray-900 mb-1">Datos migrados de Teamleader</h3>
                        <p class="text-sm text-gray-500 mb-0">Cruce de solo lectura usando ID Teamleader, pasaporte o correo del cliente.</p>
                    </div>

                    @if(! $tlContact)
                        <div class="alert alert-warning d-flex align-items-start gap-2">
                            <i class="fas fa-search mt-1"></i>
                            <div>
                                <strong>No se encontro un contacto migrado de Teamleader para este cliente.</strong>
                                <div class="small">Se intento asociar por el ID Teamleader guardado, por pasaporte y por correo principal/alternativo.</div>
                            </div>
                        </div>
                    @else
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <div class="border rounded p-3 bg-light h-100">
                                    <div class="small text-muted">Deals</div>
                                    <div class="h4 mb-0">{{ $tlSummary['deals'] ?? 0 }}</div>
                                    <small class="text-muted">{{ $tlSummary['open_deals'] ?? 0 }} abiertos</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 bg-light h-100">
                                    <div class="small text-muted">Proyectos</div>
                                    <div class="h4 mb-0">{{ $tlSummary['projects'] ?? 0 }}</div>
                                    <small class="text-muted">{{ $tlSummary['active_projects'] ?? 0 }} activos</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 bg-light h-100">
                                    <div class="small text-muted">Facturas</div>
                                    <div class="h4 mb-0">{{ $tlSummary['invoices'] ?? 0 }}</div>
                                    <small class="text-muted">{{ $tlSummary['outstanding_invoices'] ?? 0 }} pendientes/vencidas</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 bg-light h-100">
                                    <div class="small text-muted">Total facturado</div>
                                    <div class="h5 mb-0">{{ number_format((float) ($tlSummary['total_invoiced'] ?? 0), 2) }}</div>
                                    <small class="text-muted">{{ $tlSummary['currency'] ?? '-' }}</small>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                    <div>
                                        <h4 class="mb-1">{{ $tlContact->full_name ?: '(Sin nombre en Teamleader)' }}</h4>
                                        <div class="text-muted small">
                                            ID TL: <code>{{ $tlContact->id }}</code>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($tlMatchLabels as $label)
                                            <span class="badge bg-success">{{ $label }}</span>
                                        @endforeach
                                        @if(auth()->user()->can('tl.view'))
                                            <a href="{{ route('teamleader.contacts.show', $tlContact->id) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i> Ver ficha TL
                                            </a>
                                        @endif
                                    </div>
                                </div>

                                <div class="row g-3 mt-2">
                                    <div class="col-md-3"><b>Email:</b><br>{{ $tlContact->email ?: '-' }}</div>
                                    <div class="col-md-3"><b>Telefono:</b><br>{{ $tlContact->phone ?: '-' }}</div>
                                    <div class="col-md-3"><b>Pasaporte:</b><br>{{ $tlContact->passport ?: '-' }}</div>
                                    <div class="col-md-3"><b>Status:</b><br>{{ $tlContact->status ?: '-' }}</div>
                                    <div class="col-md-3"><b>Creado TL:</b><br>{{ optional($tlContact->tl_added_at)->format('d/m/Y H:i') ?: '-' }}</div>
                                    <div class="col-md-3"><b>Actualizado TL:</b><br>{{ optional($tlContact->tl_updated_at)->format('d/m/Y H:i') ?: '-' }}</div>
                                    <div class="col-md-3"><b>Sync local:</b><br>{{ optional($tlContact->updated_at)->format('d/m/Y H:i') ?: '-' }}</div>
                                    <div class="col-md-3"><b>Documentos:</b><br>{{ $tlSummary['documents'] ?? 0 }}</div>
                                </div>

                                @if($tlOtherContacts->isNotEmpty())
                                    <div class="alert alert-info mt-3 mb-0">
                                        Tambien hay {{ $tlOtherContacts->count() }} posible(s) coincidencia(s) adicional(es) en Teamleader.
                                    </div>
                                @endif
                            </div>
                        </div>

                        <h5 class="fw-bold mt-4">Deals migrados</h5>
                        <table id="tlDealsTable" class="table table-striped table-hover w-100">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Status</th>
                                    <th>Monto</th>
                                    <th>Cierre estimado</th>
                                    <th>Actualizado TL</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tlDeals as $deal)
                                    <tr>
                                        <td>{{ $deal->title ?: '-' }}</td>
                                        <td>{{ $deal->status ?: '-' }}</td>
                                        <td>{{ $deal->amount ? number_format((float) $deal->amount, 2) . ' ' . $deal->currency : '-' }}</td>
                                        <td>{{ optional($deal->estimated_closing_date)->format('d/m/Y') ?: '-' }}</td>
                                        <td>{{ optional($deal->tl_updated_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <h5 class="fw-bold mt-4">Proyectos migrados</h5>
                        <table id="tlProjectsTable" class="table table-striped table-hover w-100">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Status</th>
                                    <th>Producto</th>
                                    <th>Presupuesto</th>
                                    <th>Vence</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tlProjects as $project)
                                    <tr>
                                        <td>{{ $project->title ?: '-' }}</td>
                                        <td>{{ $project->status ?: '-' }}</td>
                                        <td>{{ $project->custom_field_value ?: '-' }}</td>
                                        <td>{{ $project->budget_amount ? number_format((float) $project->budget_amount, 2) . ' ' . $project->budget_currency : '-' }}</td>
                                        <td>{{ optional($project->due_on)->format('d/m/Y') ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <h5 class="fw-bold mt-4">Facturas migradas</h5>
                        <table id="tlInvoicesTable" class="table table-striped table-hover w-100">
                            <thead>
                                <tr>
                                    <th>Numero</th>
                                    <th>Status</th>
                                    <th>Total</th>
                                    <th>Fecha</th>
                                    <th>Pagada</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tlInvoices as $invoice)
                                    <tr>
                                        <td>{{ $invoice->invoice_number ?: '-' }}</td>
                                        <td>{{ $invoice->status ?: '-' }}</td>
                                        <td>{{ $invoice->total_price_incl_tax ? number_format((float) $invoice->total_price_incl_tax, 2) . ' ' . $invoice->currency : '-' }}</td>
                                        <td>{{ optional($invoice->invoice_date)->format('d/m/Y') ?: '-' }}</td>
                                        <td>{{ optional($invoice->paid_date)->format('d/m/Y') ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <h5 class="fw-bold mt-4">Documentos migrados</h5>
                        <table id="tlDocumentsTable" class="table table-striped table-hover w-100">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Entidad</th>
                                    <th>Tipo</th>
                                    <th>Tamano</th>
                                    <th>Descargado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tlDocuments as $document)
                                    <tr>
                                        <td>{{ \Illuminate\Support\Str::limit($document->name ?: '-', 70) }}</td>
                                        <td>{{ $document->entity_type ?: '-' }}</td>
                                        <td>{{ $document->extension ?: ($document->mime_type ?: '-') }}</td>
                                        <td>{{ $document->readable_size }}</td>
                                        <td>{{ $document->downloaded ? 'Si' : 'No' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                <div class="tab-pane fade" id="client-tasks" role="tabpanel" aria-labelledby="client-tasks-tab">
                    <div class="mb-3">
                        <h3 class="text-xl font-bold text-gray-900 mb-1">Tareas del cliente</h3>
                        <p class="text-sm text-gray-500 mb-0">Historial completo de tareas asociadas a este cliente.</p>
                    </div>

                    <table id="clientTasksTable" class="table table-striped table-hover w-100">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Fecha</th>
                                <th>Asesor</th>
                                <th>Estado</th>
                                <th>Via</th>
                                <th>Venta</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($clientTasks as $clientTask)
                                @php
                                    $taskStatusLabel = [
                                        'pending' => 'Pendiente',
                                        'in_progress' => 'En curso',
                                        'completed' => 'Completada',
                                        'canceled' => 'Cancelada',
                                    ][$clientTask->status] ?? $clientTask->status;
                                    $taskStatusClass = [
                                        'pending' => 'warning',
                                        'in_progress' => 'primary',
                                        'completed' => 'success',
                                        'canceled' => 'danger',
                                    ][$clientTask->status] ?? 'secondary';
                                @endphp
                                <tr>
                                    <td>{{ $clientTask->id }}</td>
                                    <td>{{ optional($clientTask->due_date)->format('d/m/Y') }}</td>
                                    <td>{{ $clientTask->assignee?->name ?? '-' }}</td>
                                    <td><span class="badge bg-{{ $taskStatusClass }}">{{ $taskStatusLabel }}</span></td>
                                    <td>{{ implode(', ', $clientTask->contactMethodLabels()) ?: '-' }}</td>
                                    <td>{{ $clientTask->saleStatusLabel() ?? '-' }}</td>
                                    <td style="min-width: 320px;">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#client-task-detail-{{ $clientTask->id }}">
                                            Ver
                                        </button>

                                        <div class="collapse mt-2 p-3 bg-light border rounded" id="client-task-detail-{{ $clientTask->id }}">
                                            <div class="row g-3">
                                                <div class="col-md-4"><b>Titulo:</b><br>{{ $clientTask->title }}</div>
                                                <div class="col-md-4"><b>Respondio:</b><br>{{ is_null($clientTask->customer_responded) ? 'Sin registrar' : ($clientTask->customer_responded ? 'Si' : 'No / esperando respuesta') }}</div>
                                                <div class="col-md-4"><b>Gestion efectiva:</b><br>{{ is_null($clientTask->call_effective) ? 'Sin registrar' : ($clientTask->call_effective ? 'Si' : 'No') }}</div>
                                                <div class="col-md-4"><b>Interes:</b><br>{{ is_null($clientTask->interest_level) ? 'Sin registrar' : ($clientTask->interest_level ? 'Si' : 'No') }}</div>
                                                <div class="col-md-4"><b>Producto:</b><br>{{ $clientTask->product_of_interest ?: '-' }}</div>
                                                <div class="col-md-4"><b>Seguimiento:</b><br>{{ $clientTask->follow_up_date ? $clientTask->follow_up_date->format('d/m/Y') : '-' }}</div>
                                                <div class="col-md-6"><b>Etiquetas:</b><br>
                                                    @forelse($clientTask->sales_tags ?? [] as $tag)
                                                        @php($tagMeta = \App\Models\Task::salesTagOptions()[$tag] ?? null)
                                                        @if($tagMeta)
                                                            <span class="badge bg-secondary">{{ $tagMeta['label'] }}</span>
                                                        @endif
                                                    @empty
                                                        -
                                                    @endforelse
                                                </div>
                                                <div class="col-md-6"><b>Observaciones:</b><br>{{ $clientTask->reason_no_effective ?: ($clientTask->reason_no_interest ?: '-') }}</div>
                                                <div class="col-md-12"><b>Descripcion:</b><br>{{ $clientTask->description ?: '-' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="client-chat" role="tabpanel" aria-labelledby="client-chat-tab">
                    <div class="mb-3">
                        <h3 class="text-xl font-bold text-gray-900 mb-1">Chat interno</h3>
                        <p class="text-sm text-gray-500 mb-0">Notas internas entre coordinadores y administradores sobre este cliente. El cliente no ve este chat.</p>
                    </div>

                    <div class="border rounded bg-light p-3">
                        <div id="clientInternalChatMessages" style="height:360px; overflow-y:auto; background:#fff; border:1px solid #e5e7eb; border-radius:.5rem; padding:1rem;">
                            @forelse($clientChatMessages as $message)
                                <div class="client-chat-message mb-3" data-message-id="{{ $message->id }}">
                                    <div class="small text-muted">
                                        <b>{{ $message->author?->name ?? 'Usuario eliminado' }}</b>
                                        <span>{{ optional($message->created_at)->format('d/m/Y H:i') }}</span>
                                    </div>
                                    @if($message->message !== '')
                                        <div class="p-2 rounded" style="background:{{ $message->user_id === auth()->id() ? '#e0f2fe' : '#f8fafc' }};">
                                            {{ $message->message }}
                                        </div>
                                    @endif
                                    @if($message->attachments->isNotEmpty())
                                        <div class="mt-2 d-flex flex-wrap gap-2">
                                            @foreach($message->attachments as $attachment)
                                                <a href="{{ route('crud.users.internal-chat.attachments.download', [$user, $attachment]) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                                                    <i class="fas fa-paperclip me-1"></i>{{ $attachment->original_name }}
                                                    <small class="text-muted">({{ $attachment->size >= 1048576 ? round($attachment->size / 1048576, 1) . ' MB' : round($attachment->size / 1024, 1) . ' KB' }})</small>
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div id="clientInternalChatEmpty" class="text-muted text-center py-5">No hay mensajes internos para este cliente.</div>
                            @endforelse
                        </div>

                        <form id="clientInternalChatForm" class="mt-3">
                            @csrf
                            <label class="form-label fw-bold">Nuevo mensaje interno</label>
                            <textarea id="clientInternalChatInput" class="form-control" rows="3" maxlength="2000" placeholder="Escribe una nota interna sobre este cliente..."></textarea>
                            <label class="form-label fw-bold mt-2">Adjuntos</label>
                            <input id="clientInternalChatFiles" type="file" class="form-control" multiple>
                            <small class="text-muted">Puedes subir hasta 5 archivos por mensaje. Maximo 20 MB por archivo.</small>
                            <div class="d-flex justify-content-end mt-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-1"></i>Enviar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                @endif

                <div class="tab-pane fade" id="admin-req" role="tabpanel" aria-labelledby="admin-req-tab">
                    <div class="d-flex justify-content-end mb-3">
                        <button class="cfrSefar inline-flex items-center justify-center px-3 py-2 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700" data-bs-toggle="modal" data-bs-target="#crearSolicitudModal">
                            + Nueva solicitud
                        </button>
                    </div>

                    <!-- Modal para crear solicitud -->
                    <div class="modal fade" id="crearSolicitudModal" tabindex="-1" aria-labelledby="crearSolicitudLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form id="solicitudForm" method="POST">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Nueva solicitud de documento</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Nombre del documento</label>
                                            <input type="text" name="document_name" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Tipo de documento</label>
                                            <select name="document_type" class="form-select" required>
                                                <option value="juridico">Jurídico</option>
                                                <option value="genealogico">Genealógico</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-primary">Crear solicitud</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Modal para editar solicitud -->
                    <div class="modal fade" id="editarSolicitudModal" tabindex="-1" aria-labelledby="editarSolicitudLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form id="editarSolicitudForm" method="POST">
                                <input type="hidden" name="request_id" id="edit_request_id">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Editar solicitud de documento</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Nombre del documento</label>
                                            <input type="text" name="document_name" id="edit_document_name" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Tipo de documento</label>
                                            <select name="document_type" id="edit_document_type" class="form-select" required>
                                                <option value="juridico">Jurídico</option>
                                                <option value="genealogico">Genealógico</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <table class="table table-sm" id="solicitudesTable">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Estatus</th>
                                <th>Archivo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($documentRequests as $req)
                            <tr data-id="{{ $req->id }}">
                                <td>{{ $req->document_name }}</td>
                                <td>{{ ucfirst($req->document_type) }}</td>
                                <td>
                                    <span class="badge
                                        @if($req->status === 'aprobada') bg-success
                                        @elseif($req->status === 'rechazada') bg-danger
                                        @elseif($req->status === 'no_documento') bg-warning text-dark
                                        @else bg-secondary @endif">
                                        {{ ucfirst(str_replace('_',' ', $req->status)) }}
                                    </span>
                                </td>
                                <td>
                                    @if($req->file_path)
                                    <a href="{{ Storage::disk('s3')->url($req->file_path) }}" target="_blank">Ver archivo</a>
                                    @else
                                    —
                                    @endif
                                </td>
                                <td>
                                    @if(!in_array($req->status, ['aprobada', 'rechazada', 'no_documento']))
                                    <button class="btn btn-sm btn-primary editar" data-id="{{ $req->id }}">Editar</button>
                                    <button class="btn btn-sm btn-success aprobar" data-id="{{ $req->id }}">Aprobar</button>
                                    <button class="btn btn-sm btn-danger rechazar" data-id="{{ $req->id }}">Rechazar</button>
                                    @endif
                                    <button class="btn btn-sm btn-outline-danger eliminar" data-id="{{ $req->id }}">Eliminar</button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <script>
                    $(document).ready(function () {
                        // Configuración de DataTables
                        $('#solicitudesTable').DataTable({
                            "language": {
                                "lengthMenu": "Mostrar _MENU_ resultados por página",
                                "zeroRecords": "No hay resultados",
                                "info": "Página _PAGE_ de _PAGES_",
                                "infoEmpty": "No hay resultados"
                            },
                            columnDefs: [
                                { "width": "20%", "targets": 0 }, // Nombre
                                { "width": "15%", "targets": 1 }, // Tipo
                                { "width": "15%", "targets": 2 }, // Estatus
                                { "width": "25%", "targets": 3 }, // Archivo
                                { "width": "25%", "targets": 4 }  // Acciones
                            ]
                        });

                        // Configuración CSRF para AJAX
                        $.ajaxSetup({
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            }
                        });

                        // Crear nueva solicitud - Versión corregida
                        $('#solicitudForm').on('submit', function (e) {
                            e.preventDefault();

                            $.ajax({
                                url: "{{ route('admin.requests.store', $user) }}",
                                method: 'POST',
                                data: $(this).serialize(),
                                success: function (res) {
                                    const table = $('#solicitudesTable').DataTable();

                                    const formattedStatus = res.status.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());

                                    // Crear el nuevo elemento como un array para DataTables
                                    const newRowData = [
                                        res.document_name,
                                        res.document_type.charAt(0).toUpperCase() + res.document_type.slice(1),
                                        '<span class="badge bg-secondary">' + formattedStatus + '</span>',
                                        '—',
                                        '<button class="btn btn-sm btn-primary editar" data-id="'+res.id+'">Editar</button> ' +
                                        '<button class="btn btn-sm btn-success aprobar" data-id="'+res.id+'">Aprobar</button> ' +
                                        '<button class="btn btn-sm btn-danger rechazar" data-id="'+res.id+'">Rechazar</button> ' +
                                        '<button class="btn btn-sm btn-outline-danger eliminar" data-id="'+res.id+'">Eliminar</button>'
                                    ];

                                    // Añadir la nueva fila usando la API de DataTables
                                    table.row.add(newRowData).draw();

                                    // Limpiar y cerrar el modal
                                    $('#crearSolicitudModal').modal('hide');
                                    $('#solicitudForm')[0].reset();

                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Solicitud creada',
                                        text: 'La solicitud se creó correctamente.'
                                    });
                                },
                                error: function () {
                                    alert('Error al crear solicitud');
                                }
                            });
                        });

                        // Editar solicitud - Abrir modal
                        $('#solicitudesTable').on('click', '.editar', function () {
                            const id = $(this).data('id');
                            const row = $(this).closest('tr');

                            $('#edit_request_id').val(id);
                            $('#edit_document_name').val(row.find('td:eq(0)').text());
                            $('#edit_document_type').val(row.find('td:eq(1)').text().toLowerCase());

                            $('#editarSolicitudModal').modal('show');
                        });

                        // Editar solicitud - Enviar formulario
                        $('#editarSolicitudForm').on('submit', function (e) {
                            e.preventDefault();
                            const id = $('#edit_request_id').val();

                            $.ajax({
                                url: `/admin/requests/${id}`,
                                method: 'PUT',
                                data: $(this).serialize(),
                                success: function (res) {
                                    // Verifica que las propiedades necesarias existen
                                    const documentName = res.data.document_name || '';
                                    let documentType = res.data.document_type.charAt(0).toUpperCase() + res.data.document_type.slice(1);

                                    // Actualiza la fila
                                    const row = $(`tr[data-id="${id}"]`);
                                    if (row.length) {
                                        row.find('td:eq(0)').text(documentName);
                                        row.find('td:eq(1)').text(documentType);
                                        $('#editarSolicitudModal').modal('hide');

                                        // Si usas DataTables
                                        if ($.fn.DataTable.isDataTable('#solicitudesTable')) {
                                            $('#solicitudesTable').DataTable().draw();
                                        }

                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Solicitud editada',
                                            text: 'La solicitud se editó correctamente.'
                                        });
                                    } else {
                                        console.error('No se encontró la fila con ID:', id);
                                    }
                                },
                                error: function () {
                                    alert('Error al actualizar solicitud');
                                }
                            });
                        });

                        // Aprobar solicitud
                        $('#solicitudesTable').on('click', '.aprobar', function () {
                            const id = $(this).data('id');
                            const row = $(this).closest('tr');

                            Swal.fire({
                                title: '¿Aprobar solicitud?',
                                text: "¿Estás seguro de aprobar esta solicitud?",
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#3085d6',
                                cancelButtonColor: '#d33',
                                confirmButtonText: 'Sí, aprobar',
                                cancelButtonText: 'Cancelar'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    $.ajax({
                                        url: `/admin/requests/${id}/approve`,
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                        },
                                        success: function (res) {
                                            row.find('td:eq(2)').html('<span class="badge bg-success">Aprobada</span>');
                                            row.find('td:eq(4)').html('<button class="btn btn-sm btn-outline-danger eliminar" data-id="'+id+'">Eliminar</button>');

                                            // Si usas DataTables, actualiza la tabla
                                            if ($.fn.DataTable.isDataTable('#solicitudesTable')) {
                                                $('#solicitudesTable').DataTable().draw(false);
                                            }

                                            Swal.fire('Aprobada!', 'La solicitud ha sido aprobada.', 'success');
                                        },
                                        error: function (xhr) {
                                            let errorMsg = 'Error al aprobar solicitud';
                                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                                errorMsg = xhr.responseJSON.message;
                                                if (xhr.responseJSON.current_status) {
                                                    errorMsg += ` (Estado actual: ${xhr.responseJSON.current_status})`;
                                                }
                                            }
                                            Swal.fire('Error', errorMsg, 'error');
                                        }
                                    });
                                }
                            });
                        });

                        // Rechazar solicitud
                        $('#solicitudesTable').on('click', '.rechazar', function () {
                            const id = $(this).data('id');
                            const row = $(this).closest('tr');

                            Swal.fire({
                                title: '¿Rechazar solicitud?',
                                text: "¿Estás seguro de rechazar esta solicitud?",
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#3085d6',
                                cancelButtonColor: '#d33',
                                confirmButtonText: 'Sí, rechazar',
                                cancelButtonText: 'Cancelar'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    $.ajax({
                                        url: `/admin/requests/${id}/reject`,
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                        },
                                        success: function (res) {
                                            row.find('td:eq(2)').html('<span class="badge bg-danger">Rechazada</span>');
                                            row.find('td:eq(3)').html('');
                                            row.find('td:eq(4)').html('<button class="btn btn-sm btn-outline-danger eliminar" data-id="'+id+'">Eliminar</button>');

                                            // Si usas DataTables, actualiza la tabla
                                            if ($.fn.DataTable.isDataTable('#solicitudesTable')) {
                                                $('#solicitudesTable').DataTable().draw(false);
                                            }

                                            Swal.fire('Rechazada!', 'La solicitud ha sido rechazada.', 'success');
                                        },
                                        error: function (xhr) {
                                            let errorMsg = 'Error al rechazar solicitud';
                                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                                errorMsg = xhr.responseJSON.message;
                                                if (xhr.responseJSON.current_status) {
                                                    errorMsg += ` (Estado actual: ${xhr.responseJSON.current_status})`;
                                                }
                                            }
                                            Swal.fire('Error', errorMsg, 'error');
                                        }
                                    });
                                }
                            });
                        });

                        // Eliminar solicitud
                        $('#solicitudesTable').on('click', '.eliminar', function () {
                            const id = $(this).data('id');
                            const table = $('#solicitudesTable').DataTable();
                            const row = $(this).closest('tr');

                            if(confirm('¿Estás seguro de eliminar esta solicitud?')) {
                                $.ajax({
                                    url: `/admin/requests/${id}`,
                                    method: 'DELETE',
                                    success: function (response) {
                                        if (response.success) {
                                            table.row(row).remove().draw();

                                            Swal.fire({
                                                icon: 'success',
                                                title: 'Solicitud eliminada',
                                                text: 'La solicitud se eliminó correctamente.'
                                            });
                                        } else {
                                            Swal.fire({
                                                icon: 'error',
                                                title: 'Error',
                                                text: response.message || 'Ocurrió un error al eliminar la solicitud.'
                                            });
                                        }
                                    }
                                });
                            }
                        });

                        $('#solicitudesClienteTable').on('click', '.upload-btn', function() {
                            const requestId = $(this).data('request-id');
                            const fileInput = $(this).siblings('.file-input')[0];
                            const file = fileInput.files[0];
                            const row = $(this).closest('tr');

                            if (!file) {
                                Swal.fire('Error', 'Por favor selecciona un archivo', 'error');
                                return;
                            }

                            const formData = new FormData();
                            formData.append('file', file);
                            formData.append('_token', $('meta[name="csrf-token"]').attr('content'));

                            Swal.fire({
                                title: 'Subiendo archivo...',
                                html: 'Por favor espera mientras se sube el archivo',
                                allowOutsideClick: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });

                            $.ajax({
                                url: "client/requests/" + requestId + "/upload",
                                type: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false,
                                success: function(response) {
                                    Swal.fire(
                                        'Éxito!',
                                        'El archivo se ha subido correctamente',
                                        'success'
                                    );

                                    // Actualizar la fila
                                    row.find('td:eq(2)').html('<span class="badge bg-info">En revisión</span>');
                                    row.find('.actions-column').html('<em>Sin acciones disponibles</em>');
                                },
                                error: function(xhr) {
                                    let errorMsg = 'Error al subir el archivo';
                                    if (xhr.responseJSON && xhr.responseJSON.message) {
                                        errorMsg = xhr.responseJSON.message;
                                    }
                                    Swal.fire('Error', errorMsg, 'error');
                                }
                            });
                        });

                        // No tengo documento
                        $('#solicitudesClienteTable').on('click', '.no-doc-btn', function() {
                            const requestId = $(this).data('request-id');
                            const row = $(this).closest('tr');

                            Swal.fire({
                                title: '¿No tienes el documento?',
                                text: "¿Estás seguro de que no dispones de este documento?",
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#3085d6',
                                cancelButtonColor: '#d33',
                                confirmButtonText: 'Sí, no lo tengo',
                                cancelButtonText: 'Cancelar'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    $.ajax({
                                        url: "client/requests/" + requestId + "/no-doc",
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                        },
                                        success: function(response) {
                                            Swal.fire(
                                                'Confirmado!',
                                                'Hemos registrado que no dispones del documento.',
                                                'success'
                                            );

                                            // Actualizar la fila
                                            row.find('td:eq(2)').html('<span class="badge bg-warning">Sin documento</span>');
                                            row.find('.actions-column').html('<em>Sin acciones disponibles</em>');
                                        },
                                        error: function(xhr) {
                                            let errorMsg = 'Error al procesar la solicitud';
                                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                                errorMsg = xhr.responseJSON.message;
                                            }
                                            Swal.fire('Error', errorMsg, 'error');
                                        }
                                    });
                                }
                            });
                        });
                    });
                </script>

                <div class="tab-pane fade" id="client-req" role="tabpanel" aria-labelledby="client-req-tab">
                    <table class="table table-sm" id="solicitudesClienteTable">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Estatus</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($documentRequests as $req)
                            <tr data-id="{{ $req->id }}">
                                <td>{{ $req->document_name }}</td>
                                <td>{{ ucfirst($req->document_type) }}</td>
                                <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_',' ', $req->status)) }}</span></td>
                                <td class="actions-column">
                                    {{-- Subir archivo --}}
                                    @if(in_array($req->status, ['en_espera_cliente', 'rechazada']))
                                    <div class="upload-form d-flex gap-2 mb-2">
                                        <input type="file" class="form-control form-control-sm file-input" data-request-id="{{ $req->id }}">
                                        <button class="btn btn-sm btn-success upload-btn" data-request-id="{{ $req->id }}">Subir</button>
                                    </div>

                                    {{-- No tengo documento --}}
                                    @if(now()->gte($req->no_document_button_at))
                                    <button class="btn btn-sm btn-outline-secondary w-100 no-doc-btn" data-request-id="{{ $req->id }}">No tengo el documento</button>
                                    @endif
                                    @else
                                    <em>Sin acciones disponibles</em>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css">
    <style>
    /* Estilos de la tabla y el switch */
    table.dataTable, .dataTables_scrollHeadInner {
        width: 100% !important;
    }
    table.dataTable th, table.dataTable td {
        font-size: 1rem !important;
        padding: 10px 5px;
    }
    .switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }
    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
    }
    input:checked + .slider {
        background-color: #093143 !important;
    }
    input:checked + .slider:before {
        transform: translateX(26px);
    }
    .slider.round {
        border-radius: 34px;
    }
    .slider.round:before {
        border-radius: 50%;
    }
    div.dt-row {
        margin:10px 0px;
    }
</style>
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables CSS para Bootstrap 4 -->
<script src="https://code.jquery.com/jquery-3.6.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cleave.js/1.6.0/cleave.min.js"></script>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- DataTables CSS para Bootstrap 4 -->

<script type="text/javascript" src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function () {
        $('#etiquetadosend').on('click', function (e) {
            e.preventDefault(); // Previene el comportamiento predeterminado del botón

            // Obtiene los datos del formulario
            var form = $('#dynamicForm');
            var formData = new FormData(form[0]);

            // Realiza la petición AJAX
            $.ajax({
                url: '{{ route("etiquetasgenealogiamonday") }}', // Ruta al backend
                type: 'POST',
                data: formData,
                processData: false, // Evita que jQuery procese los datos
                contentType: false, // Evita que jQuery establezca el tipo de contenido automáticamente
                headers: {
                    'X-CSRF-TOKEN': $('input[name="_token"]').val() // Incluye el token CSRF
                },
                success: function (response) {
                    // Maneja la respuesta exitosa
                    Swal.fire({
                        icon: 'success',
                        title: 'Guardado',
                        text: 'Los cambios fueron guardados correctamente.'
                    });
                },
                error: function (xhr) {
                    // Maneja errores
                    var errors = xhr.responseJSON?.errors || {};
                    var messages = Object.values(errors).flat().join(' ') || 'Ocurrió un error inesperado.';

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: messages
                    });
                }
            });
        });

        $('#guardar-datos').on('click', function(e) {
            e.preventDefault(); // Evita el comportamiento predeterminado del botón

            // Serializa los datos del formulario
            let formData = $('#datos-personales-form').serialize();

            // Envía la petición al backend usando AJAX
            $.ajax({
                url: '/guardar-datos-personales', // URL especificada en el formulario
                type: 'POST',
                data: formData,
                success: function(response) {
                    // Maneja la respuesta exitosa del servidor
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: 'Datos guardados exitosamente.',
                        confirmButtonText: 'Aceptar'
                    });
                },
                error: function(xhr) {
                    // Maneja errores
                    let errors = xhr.responseJSON.errors;
                    let errorMessage = xhr.responseJSON.message || 'Hubo un error al guardar los datos.';

                    // Formatea los errores en una lista para mostrarlos en Swal2
                    let formattedErrors = '';
                    if (errors) {
                        Object.keys(errors).forEach(function(key) {
                            formattedErrors += `<p>${errors[key][0]}</p>`;
                        });
                    }

                    // Muestra el mensaje de error usando Swal2
                    Swal.fire({
                        icon: 'error',
                        title: 'Error al guardar',
                        html: formattedErrors || errorMessage,
                        confirmButtonText: 'Aceptar'
                    });
                }
            });
        });

        $('#datos-personales-form').on('submit', function (e) {
            e.preventDefault();
        });

        // Evita el comportamiento predeterminado del formulario
        $('#dynamicForm').on('submit', function (e) {
            e.preventDefault();
        });

        $('#clientSubmitButton').on('click', function (e) {
            e.preventDefault(); // Evita el comportamiento predeterminado

            // Validación básica en el frontend
            var password = $('#password').val();
            var repeatPassword = $('#repeat_password').val();

            if (!password || password.length < 8) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'La contraseña debe tener al menos 8 caracteres.'
                });
                return;
            }

            if (password !== repeatPassword) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Las contraseñas no coinciden.'
                });
                return;
            }

            // Obtén los datos del formulario
            var form = $('#clientChangePasswordForm');
            var formData = new FormData(form[0]);

            // Realiza la petición AJAX
            $.ajax({
                url: '/changepassword', // Ruta al backend
                type: 'POST',
                data: formData,
                processData: false, // Evita que jQuery procese automáticamente los datos
                contentType: false, // Evita que jQuery establezca automáticamente el Content-Type
                headers: {
                    'X-CSRF-TOKEN': $('input[name="_token"]').val() // Incluye el token CSRF
                },
                success: function (response) {
                    // Maneja una respuesta exitosa
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Contraseña actualizada',
                            text: 'La contraseña se cambió correctamente.'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Ocurrió un error al actualizar la contraseña.'
                        });
                    }
                },
                error: function (xhr) {
                    // Maneja errores en la petición
                    var errors = xhr.responseJSON?.errors || {};
                    var messages = Object.values(errors).flat().join(' ') || 'Ocurrió un error inesperado.';

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: messages
                    });
                }
            });
        });

        // Desactiva el comportamiento predeterminado del formulario en caso de envío accidental
        $('#clientChangePasswordForm').on('submit', function (e) {
            e.preventDefault();
        });

        $('#submitButton').on('click', function (e) {
            e.preventDefault(); // Evita el comportamiento predeterminado

            // Obtén los datos del formulario
            var form = $('#adminChangePasswordForm');
            var formData = new FormData(form[0]); // jQuery para acceder al formulario

            // Realiza la petición AJAX
            $.ajax({
                url: '/adminchangepassword', // Ruta al backend
                type: 'POST',
                data: formData,
                processData: false, // Evita que jQuery procese automáticamente los datos
                contentType: false, // Evita que jQuery establezca automáticamente el Content-Type
                headers: {
                    'X-CSRF-TOKEN': $('input[name="_token"]').val() // Incluye el token CSRF
                },
                success: function (response) {
                    // Maneja una respuesta exitosa
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Contraseña actualizada',
                            text: 'La contraseña se cambió correctamente.'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Ocurrió un error al actualizar la contraseña.'
                        });
                    }
                },
                error: function (xhr) {
                    // Maneja errores en la petición
                    var errors = xhr.responseJSON?.errors || {};
                    var messages = Object.values(errors).flat().join(' ') || 'Ocurrió un error inesperado.';

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: messages
                    });
                }
            });
        });

        // Desactiva el comportamiento predeterminado del formulario en caso de envío accidental
        $('#adminChangePasswordForm').on('submit', function (e) {
            e.preventDefault();
        });

        $('#solicitudesClienteTable').DataTable({
            "language": {
                "lengthMenu": "Mostrar _MENU_ resultados por página",
                "zeroRecords": "No hay resultados",
                "info": "Página _PAGE_ de _PAGES_",
                "infoEmpty": "No hay resultados"
            }
        });

        $('#familiarsTable').DataTable({
            "language": {
                "lengthMenu": "Mostrar _MENU_ resultados por página",
                "zeroRecords": "No hay resultados",
                "info": "Página _PAGE_ de _PAGES_",
                "infoEmpty": "No hay resultados"
            }
        });
        $('#paymentsTable').DataTable({
            "language": {
                "lengthMenu": "Mostrar _MENU_ resultados por página",
                "zeroRecords": "No hay resultados",
                "info": "Página _PAGE_ de _PAGES_",
                "infoEmpty": "No hay resultados"
            }
        });
        $('#paymentsPenTable').DataTable({
            "language": {
                "lengthMenu": "Mostrar _MENU_ resultados por página",
                "zeroRecords": "No hay resultados",
                "info": "Página _PAGE_ de _PAGES_",
                "infoEmpty": "No hay resultados"
            }
        });
        $('#documentsTable').DataTable({
            "language": {
                "lengthMenu": "Mostrar _MENU_ resultados por página",
                "zeroRecords": "No hay resultados",
                "info": "Página _PAGE_ de _PAGES_",
                "infoEmpty": "No hay resultados"
            }
        });
        $('#dealsTable').DataTable({
            "language": {
                "lengthMenu": "Mostrar _MENU_ resultados por página",
                "zeroRecords": "No hay resultados",
                "info": "Página _PAGE_ de _PAGES_",
                "infoEmpty": "No hay resultados"
            }
        });
        if ($('#clientTasksTable').length) {
            $('#clientTasksTable').DataTable({
                "language": {
                    "lengthMenu": "Mostrar _MENU_ resultados por pagina",
                    "zeroRecords": "No hay tareas",
                    "info": "Pagina _PAGE_ de _PAGES_",
                    "infoEmpty": "No hay tareas"
                },
                "order": [[0, "desc"]]
            });
        }
        ['#tlDealsTable', '#tlProjectsTable', '#tlInvoicesTable', '#tlDocumentsTable'].forEach(function (selector) {
            if ($(selector).length) {
                $(selector).DataTable({
                    "language": {
                        "lengthMenu": "Mostrar _MENU_ resultados por pagina",
                        "zeroRecords": "No hay resultados",
                        "info": "Pagina _PAGE_ de _PAGES_",
                        "infoEmpty": "No hay resultados"
                    }
                });
            }
        });

        $('#btnCosReviewTask').on('click', function () {
            const button = $(this);
            const icon = $('#iconCosReviewTask');
            const label = $('#labelCosReviewTask');
            const originalLabel = label.text();

            Swal.fire({
                title: 'Explica el problema del COS',
                input: 'textarea',
                inputLabel: 'Que esta pasando?',
                inputPlaceholder: 'Ej: El servicio aparece duplicado, falta una fase, muestra datos que no corresponden, el boton no carga...',
                inputAttributes: {
                    'aria-label': 'Descripcion del problema del COS',
                    maxlength: 3000
                },
                showCancelButton: true,
                confirmButtonText: 'Crear tarea',
                cancelButtonText: 'Cancelar',
                inputValidator: function (value) {
                    const text = (value || '').trim();

                    if (text.length < 15) {
                        return 'Describe el problema con al menos 15 caracteres.';
                    }

                    return null;
                }
            }).then(function (result) {
                if (!result.isConfirmed) return;

                $.ajax({
                    url: '{{ route("crud.users.cos-review-task", $user) }}',
                    type: 'POST',
                    data: {
                        issue_description: result.value
                    },
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    beforeSend: function () {
                        button.prop('disabled', true).css('opacity', '.75');
                        icon.addClass('fa-spin');
                        label.text('Creando tarea...');
                    },
                    success: function (response) {
                        Swal.fire({
                            icon: response.created ? 'success' : 'info',
                            title: response.created ? 'Tarea creada' : 'Tarea actualizada',
                            text: response.message
                        });
                    },
                    error: function (xhr) {
                        const errors = xhr.responseJSON?.errors || {};
                        const message = errors.issue_description?.[0]
                            || xhr.responseJSON?.message
                            || 'No se pudo solicitar la revision del COS.';

                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: message
                        });
                    },
                    complete: function () {
                        button.prop('disabled', false).css('opacity', '1');
                        icon.removeClass('fa-spin');
                        label.text(originalLabel);
                    }
                });
            });
        });

        $('#btnNotifyCosStatus').on('click', function () {
            const button = $(this);
            const icon = $('#iconNotifyCosStatus');
            const label = $('#labelNotifyCosStatus');
            const originalLabel = label.text();
            const clientFirstName = @json($user->nombres ?: $user->name ?: 'cliente');
            const cosNotifyTemplates = {
                custom: {
                    title: 'Actualizacion de estatus de tu proceso',
                    message: ''
                },
                status: {
                    title: 'Actualizacion de estatus de tu proceso',
                    message: `Hola ${clientFirstName},\n\nQueremos informarte que tu proceso tuvo una actualizacion de estatus. Puedes revisar el detalle desde tu cuenta.\n\nSaludos,\nEquipo Sefar`
                },
                documents: {
                    title: 'Actualizacion sobre documentos',
                    message: `Hola ${clientFirstName},\n\nTenemos una actualizacion relacionada con los documentos de tu expediente. Por favor revisa tu estatus en la plataforma para ver el detalle.\n\nSaludos,\nEquipo Sefar`
                },
                review: {
                    title: 'Tu expediente esta en revision',
                    message: `Hola ${clientFirstName},\n\nTu expediente se encuentra en revision interna. Te notificaremos cuando exista una nueva actualizacion o accion requerida.\n\nSaludos,\nEquipo Sefar`
                },
                payment: {
                    title: 'Actualizacion relacionada con pagos',
                    message: `Hola ${clientFirstName},\n\nTenemos una actualizacion relacionada con pagos o servicios de tu proceso. Puedes revisar el detalle desde tu cuenta.\n\nSaludos,\nEquipo Sefar`
                }
            };

            Swal.fire({
                title: 'Notificar al cliente',
                html: `
                    <select id="cosNotifyTemplate" class="swal2-select">
                        <option value="custom">Mensaje personalizado</option>
                        <option value="status">Actualizacion general</option>
                        <option value="documents">Documentos</option>
                        <option value="review">Expediente en revision</option>
                        <option value="payment">Pagos o servicios</option>
                    </select>
                    <input id="cosNotifyTitle" class="swal2-input" placeholder="Titulo" value="Actualizacion de estatus de tu proceso">
                    <textarea id="cosNotifyMessage" class="swal2-textarea" placeholder="Mensaje para el cliente"></textarea>
                    <label style="display:flex;align-items:center;gap:.5rem;justify-content:center;font-size:.9rem;">
                        <input type="checkbox" id="cosNotifyEmail" checked>
                        Enviar tambien por correo
                    </label>
                `,
                showCancelButton: true,
                confirmButtonText: 'Enviar',
                cancelButtonText: 'Cancelar',
                focusConfirm: false,
                didOpen: function () {
                    $('#cosNotifyTemplate').on('change', function () {
                        const selectedTemplate = cosNotifyTemplates[$(this).val()] || cosNotifyTemplates.custom;

                        $('#cosNotifyTitle').val(selectedTemplate.title);
                        $('#cosNotifyMessage').val(selectedTemplate.message);
                    });
                },
                preConfirm: function () {
                    const title = ($('#cosNotifyTitle').val() || '').trim();
                    const message = ($('#cosNotifyMessage').val() || '').trim();

                    if (message.length < 10) {
                        Swal.showValidationMessage('Escribe un mensaje de al menos 10 caracteres.');
                        return false;
                    }

                    return {
                        title: title,
                        message: message,
                        send_email: $('#cosNotifyEmail').is(':checked') ? 1 : 0
                    };
                }
            }).then(function (result) {
                if (!result.isConfirmed) return;

                $.ajax({
                    url: '{{ route("crud.users.notify-cos-status", $user) }}',
                    type: 'POST',
                    data: result.value,
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    beforeSend: function () {
                        button.prop('disabled', true).css('opacity', '.75');
                        icon.addClass('fa-spin');
                        label.text('Enviando...');
                    },
                    success: function (response) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Notificacion enviada',
                            text: response.message
                        });
                    },
                    error: function (xhr) {
                        const errors = xhr.responseJSON?.errors || {};
                        const message = errors.message?.[0]
                            || xhr.responseJSON?.message
                            || 'No se pudo enviar la notificacion.';

                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: message
                        });
                    },
                    complete: function () {
                        button.prop('disabled', false).css('opacity', '1');
                        icon.removeClass('fa-spin');
                        label.text(originalLabel);
                    }
                });
            });
        });

        const chatBox = $('#clientInternalChatMessages');
        const chatForm = $('#clientInternalChatForm');
        const chatInput = $('#clientInternalChatInput');
        const chatFiles = $('#clientInternalChatFiles');
        const chatTab = $('#client-chat-tab');
        const chatBadge = $('#clientInternalChatBadge');
        const originalDocumentTitle = document.title;
        let lastClientChatId = 0;
        let unreadClientChatMessages = 0;
        let clientChatLoading = false;

        function escapeHtml(value) {
            return $('<div>').text(value || '').html();
        }

        function isClientChatActive() {
            return chatTab.hasClass('active') || $('#client-chat').hasClass('active');
        }

        function setClientChatUnread(count) {
            unreadClientChatMessages = Math.max(0, count);

            if (!chatBadge.length) return;

            if (unreadClientChatMessages > 0) {
                chatBadge.removeClass('d-none').text(unreadClientChatMessages);
                document.title = `(${unreadClientChatMessages}) ${originalDocumentTitle}`;
                return;
            }

            chatBadge.addClass('d-none').text('0');
            document.title = originalDocumentTitle;
        }

        function scrollClientChat() {
            if (!chatBox.length) return;
            chatBox.scrollTop(chatBox[0].scrollHeight);
        }

        function notifyClientChatMessage(message) {
            if (isClientChatActive()) return;

            setClientChatUnread(unreadClientChatMessages + 1);

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'info',
                title: 'Nuevo mensaje interno',
                text: message.author,
                showConfirmButton: false,
                timer: 3500,
                timerProgressBar: true
            });
        }

        function appendClientChatMessage(message, options = {}) {
            if (!message || !message.id || chatBox.find(`[data-message-id="${message.id}"]`).length) {
                return false;
            }

            $('#clientInternalChatEmpty').remove();

            const bg = message.is_mine ? '#e0f2fe' : '#f8fafc';
            const messageBody = message.message
                ? `<div class="p-2 rounded" style="background:${bg};">${escapeHtml(message.message)}</div>`
                : '';
            const attachments = (message.attachments || []).map(function (attachment) {
                return `
                    <a href="${escapeHtml(attachment.download_url)}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                        <i class="fas fa-paperclip me-1"></i>${escapeHtml(attachment.name)}
                        <small class="text-muted">(${escapeHtml(attachment.size_label)})</small>
                    </a>
                `;
            }).join('');
            const attachmentsBlock = attachments
                ? `<div class="mt-2 d-flex flex-wrap gap-2">${attachments}</div>`
                : '';

            chatBox.append(`
                <div class="client-chat-message mb-3" data-message-id="${message.id}">
                    <div class="small text-muted">
                        <b>${escapeHtml(message.author)}</b>
                        <span>${escapeHtml(message.created_at)}</span>
                    </div>
                    ${messageBody}
                    ${attachmentsBlock}
                </div>
            `);

            lastClientChatId = Math.max(lastClientChatId, message.id);
            scrollClientChat();

            if (options.notify && !message.is_mine) {
                notifyClientChatMessage(message);
            }

            return true;
        }

        function loadClientChatMessages(options = {}) {
            if (!chatBox.length || clientChatLoading) return;

            clientChatLoading = true;

            $.ajax({
                url: '{{ route("crud.users.internal-chat.index", $user) }}',
                type: 'GET',
                data: { after_id: lastClientChatId },
                cache: false
            })
                .done(function (response) {
                    (response.messages || []).forEach(function (message) {
                        appendClientChatMessage(message, { notify: options.notify === true });
                    });
                })
                .always(function () {
                    clientChatLoading = false;
                });
        }

        if (chatBox.length) {
            chatBox.find('.client-chat-message').each(function () {
                lastClientChatId = Math.max(lastClientChatId, parseInt($(this).data('message-id'), 10) || 0);
            });

            scrollClientChat();
            setInterval(function () {
                loadClientChatMessages({ notify: true });
            }, 3000);

            chatTab.on('shown.bs.tab click', function () {
                setClientChatUnread(0);
                loadClientChatMessages({ notify: false });
                scrollClientChat();
            });

            $(window).on('focus', function () {
                loadClientChatMessages({ notify: true });
            });

            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    loadClientChatMessages({ notify: true });
                }
            });
        }

        chatForm.on('submit', function (e) {
            e.preventDefault();

            const message = $.trim(chatInput.val());
            const selectedFiles = chatFiles.length ? Array.from(chatFiles[0].files) : [];
            if (!message && selectedFiles.length === 0) return;

            if (selectedFiles.length > 5) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Demasiados archivos',
                    text: 'Puedes subir hasta 5 archivos por mensaje.'
                });
                return;
            }

            const formData = new FormData();
            formData.append('message', message);
            selectedFiles.forEach(function (file) {
                formData.append('attachments[]', file);
            });

            $.ajax({
                url: '{{ route("crud.users.internal-chat.store", $user) }}',
                type: 'POST',
                data: formData,
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                processData: false,
                contentType: false,
                beforeSend: function () {
                    chatForm.find('button[type="submit"]').prop('disabled', true);
                },
                success: function (response) {
                    chatInput.val('');
                    chatFiles.val('');
                    appendClientChatMessage(response.message, { notify: false });
                },
                error: function (xhr) {
                    const message = xhr.responseJSON?.message || 'No se pudo enviar el mensaje interno.';
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: message
                    });
                },
                complete: function () {
                    chatForm.find('button[type="submit"]').prop('disabled', false);
                }
            });
        });
    });
</script>

@stop
