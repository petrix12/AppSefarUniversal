<div>
    {{-- Estilos CSS puros para responsive --}}
    @if($proveedorModalOpen)
        {{-- Backdrop --}}
        <div class="modal-backdrop" wire:click="closeProveedorModal"></div>

        {{-- Caja centrada --}}
        <div class="modal-box">
            <div class="modal-content">

                {{-- Header --}}
                <div style="padding: 16px 24px; border-bottom: 1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between;">
                    <h3 style="font-size:1.1rem; font-weight:600; color:#111827; margin:0;">
                        <i class="fas fa-store mr-2 text-indigo-600"></i>
                        Información de registro (Proveedor)
                    </h3>
                    <button wire:click="closeProveedorModal"
                            style="background:none; border:none; font-size:1.25rem; color:#6b7280; cursor:pointer; line-height:1;"
                            aria-label="Cerrar">
                        ✕
                    </button>
                </div>

                {{-- Body --}}
                <div class="modal-body" style="padding: 24px;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">

                        <div>
                            <div style="font-size:0.7rem; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin-bottom:2px;">Nombre</div>
                            <div style="font-size:0.875rem; font-weight:500; color:#111827;">{{ $proveedorModalData['name'] ?? '-' }}</div>
                        </div>

                        <div>
                            <div style="font-size:0.7rem; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin-bottom:2px;">Email</div>
                            <div style="font-size:0.875rem; font-weight:500; color:#111827; word-break:break-all;">{{ $proveedorModalData['email'] ?? '-' }}</div>
                        </div>

                        <div>
                            <div style="font-size:0.7rem; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin-bottom:2px;">Email secundario</div>
                            <div style="font-size:0.875rem; font-weight:500; color:#111827; word-break:break-all;">{{ $proveedorModalData['email_2'] ?? '-' }}</div>
                        </div>

                        <div>
                            <div style="font-size:0.7rem; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin-bottom:2px;">Teléfono</div>
                            <div style="font-size:0.875rem; font-weight:500; color:#111827;">{{ $proveedorModalData['phone'] ?? '-' }}</div>
                        </div>

                        <div>
                            <div style="font-size:0.7rem; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin-bottom:2px;">País de residencia</div>
                            <div style="font-size:0.875rem; font-weight:500; color:#111827;">{{ $proveedorModalData['pais'] ?? '-' }}</div>
                        </div>

                        <div>
                            <div style="font-size:0.7rem; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin-bottom:2px;">Ciudad</div>
                            <div style="font-size:0.875rem; font-weight:500; color:#111827;">{{ $proveedorModalData['city'] ?? '-' }}</div>
                        </div>

                    </div>

                    <div style="margin-bottom:16px;">
                        <div style="font-size:0.7rem; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin-bottom:2px;">Dirección</div>
                        <div style="font-size:0.875rem; font-weight:500; color:#111827; white-space:pre-line;">{{ $proveedorModalData['address'] ?? '-' }}</div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <div style="font-size:0.7rem; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin-bottom:2px;">Método de pago preferido</div>
                            <div style="font-size:0.875rem; font-weight:500; color:#111827;">{{ $proveedorModalData['metodo_pago'] ?? '-' }}</div>
                        </div>

                        <div>
                            <div style="font-size:0.7rem; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin-bottom:2px;">Tiene contactos sociales</div>
                            <div style="font-size:0.875rem; font-weight:500; color:#111827;">
                                @php
                                    $rawSocial = $proveedorModalData['social'] ?? null;
                                    // Viene como int desde el controller: null BD → '' aquí
                                    $socialLabel = match(true) {
                                        $rawSocial === null || $rawSocial === '' => '-',
                                        (int)$rawSocial === 1                   => 'Sí',
                                        default                                 => 'No',
                                    };
                                @endphp
                                {{ $socialLabel }}
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom:16px;">
                        <div style="font-size:0.7rem; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin-bottom:2px;">Motivo (coordinador)</div>
                        <div style="font-size:0.875rem; font-weight:500; color:#111827; white-space:pre-line; background:#f9fafb; border-radius:6px; padding:10px;">
                            {{ $proveedorModalData['motivo'] ?? '-' }}
                        </div>
                    </div>

                    <div style="font-size:0.75rem; color:#9ca3af;">
                        Registrado: {{ $proveedorModalData['created_at'] ?? '-' }}
                    </div>
                </div>

                {{-- Footer --}}
                <div style="padding: 12px 24px; border-top: 1px solid #e5e7eb; display:flex; justify-content:flex-end;">
                    <button class="btn btn-secondary" wire:click="closeProveedorModal">
                        Cerrar
                    </button>
                </div>

            </div>
        </div>
    @endif
    <style>
        /* ========== MODAL ========== */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9998;
        }

        .modal-box {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            pointer-events: none;
        }

        .modal-box > .modal-content {
            pointer-events: all;
            background: white;
            width: 100%;
            max-width: 640px;
            border-radius: 8px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        .modal-body {
            overflow-y: auto;
            flex: 1;
        }

        /* ========== FILTROS RESPONSIVE ========== */
        .filters-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            align-items: start; /* evita que se estiren verticalmente */
        }

        .filters-grid select,
        .filters-grid input {
            height: 38px; /* altura uniforme en todos los selects */
            padding: 0 8px;
        }

        /* Excepción: multi-select de roles */
        .filters-grid select[multiple] {
            height: auto;
            min-height: 42px;
            padding: 4px 8px;
        }

        .filters-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Desktop: 3 columnas */
        @media (min-width: 1024px) {
            .filters-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 16px;
            }
        }

        /* Móvil: botón ancho completo */
        @media (max-width: 1023px) {
            .filters-actions {
                justify-content: stretch;
            }
            .filters-actions .btn-clear {
                width: 100%;
            }
        }

        /* ========== TABLA vs CARDS ========== */
        .table-view {
            display: block;
        }

        .cards-view {
            display: none;
        }

        @media (max-width: 1023px) {
            .table-view {
                display: none;
            }
            .cards-view {
                display: block;
            }
        }

        /* ========== TOOLTIPS ========== */
        .tooltip-container {
            position: relative;
            display: inline-block;
        }

        .tooltip-container .tooltip-text {
            visibility: hidden;
            opacity: 0;
            background-color: #1f2937;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px 10px;
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            font-size: 0.75rem;
            transition: opacity 0.3s, visibility 0.3s;
        }

        .tooltip-container .tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #1f2937 transparent transparent transparent;
        }

        .tooltip-container:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        /* ========== ESTILOS DE TABLA ========== */
        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table thead {
            background-color: #f9fafb;
        }

        .users-table th {
            padding: 12px 24px;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 500;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #e5e7eb;
        }

        .users-table tbody tr {
            border-bottom: 1px solid #e5e7eb;
            transition: background-color 0.15s;
        }

        .users-table tbody tr:hover {
            background-color: #f9fafb;
        }

        .users-table td {
            padding: 16px 24px;
            vertical-align: top;
        }

        .user-name {
            font-weight: 600;
            color: #111827;
            margin-bottom: 4px;
        }

        .user-info {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 2px;
        }

        .service-item {
            font-size: 0.875rem;
            margin-bottom: 4px;
        }

        .badge-paid {
            display: inline-block;
            padding: 2px 8px;
            font-size: 0.75rem;
            border-radius: 4px;
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-unpaid {
            display: inline-block;
            padding: 2px 8px;
            font-size: 0.75rem;
            border-radius: 4px;
            background-color: #fee2e2;
            color: #991b1b;
        }

        .actions-cell {
            text-align: center;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
        }
    </style>

    <!-- Header -->
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                <div class="bg-gray-50">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                        <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                            <span class="ctvSefar block text-indigo-600">{{ __('Users') }}</span>
                        </h2>
                        @can('crud.users.create')
                            <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                                <div class="inline-flex rounded-md shadow">
                                    <a href="{{ route('crud.users.create') }}"
                                       class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                        {{ __('Create user') }}
                                    </a>
                                </div>
                            </div>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros y búsqueda -->
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">

                    <!-- Barra de búsqueda -->
                    <div class="flex flex-col sm:flex-row bg-white px-4 py-3 sm:px-6 gap-2">
                        <input
                            wire:model.live="search"
                            type="text"
                            placeholder="Buscar por nombre, correo o pasaporte..."
                            class="flex-1 mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                        />
                        <div class="w-full sm:w-auto">
                            <select
                                wire:model.live="perPage"
                                class="py-2 px-2 mt-1 block w-full border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            >
                                <option value="5">5 por pág.</option>
                                <option value="10">10 por pág.</option>
                                <option value="15">15 por pág.</option>
                                <option value="25">25 por pág.</option>
                                <option value="50">50 por pág.</option>
                                <option value="100">100 por pág.</option>
                            </select>
                        </div>

                        @if ($search !== '')
                            <button wire:click="clear"
                                    class="py-1 px-4 mt-1 border border-transparent rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <i class="far fa-window-close"></i>
                            </button>
                        @endif
                    </div>

                    <div class="flex bg-white px-4 sm:px-6 pb-2">
                        <small>Para una búsqueda más exacta, busca por <b>correo</b> o <b>número de pasaporte</b></small>
                    </div>

                    <!-- Filtros -->
                    <div class="bg-white px-4 py-3 sm:px-6">
                        <div class="filters-container">

                            {{-- Grid de filtros --}}
                            <div class="filters-grid">

                                <!-- Filtro Servicio -->
                                <div>
                                    <label for="filterServicio" class="block text-xs font-medium text-gray-700 mb-1">Servicio</label>
                                    <select wire:model.live="filterServicio" id="filterServicio"
                                            class="block w-full border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        <option value="">Todos los servicios</option>
                                        @foreach ($listaServicios as $categoria => $servicios)
                                            @if(count($servicios) > 0)
                                                <optgroup label="{{ $categoria }}">
                                                    @foreach ($servicios as $servicio)
                                                        <option value="{{ $servicio }}">{{ $servicio }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Owner (solo si NO es rol 15/17) --}}
                                @if(!auth()->user()->roles->pluck('id')->intersect([15,17])->count())
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Coordinador</label>
                                        <select wire:model.live="filterOwner"
                                                class="block w-full border border-gray-300 rounded-md shadow-sm sm:text-sm">
                                            <option value="">Todos</option>
                                            @foreach($owners as $owner)
                                                <option value="{{ $owner->id }}">{{ $owner->name }} ({{ $owner->email }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                <!-- Contrato -->
                                <div>
                                    <label for="filterContrato" class="block text-xs font-medium text-gray-700 mb-1">Contrato</label>
                                    <select wire:model.live="filterContrato" id="filterContrato"
                                            class="block w-full border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        <option value="">Todos</option>
                                        <option value="1">Firmado</option>
                                        <option value="0">No firmado</option>
                                    </select>
                                </div>

                                <!-- Pago -->
                                <div>
                                    <label for="filterPago" class="block text-xs font-medium text-gray-700 mb-1">Estado de Pago</label>
                                    <select wire:model.live="filterPago" id="filterPago"
                                            class="block w-full border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        <option value="">Todos</option>
                                        <option value="2">Pagó Registro y completó información</option>
                                        <option value="1">Pagó Registro pero no completó información</option>
                                        <option value="0">No pagó Registro</option>
                                    </select>
                                </div>

                                <!-- Proveedores -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Pendientes por aprobar</label>
                                    <select wire:model.live="filterProveedor"
                                            class="block w-full border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        <option value="">Todos</option>
                                        <option value="pendiente">Pendientes</option>
                                    </select>
                                </div>

                                <!-- Roles (multi) -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Roles</label>
                                    <select wire:model.live="filterRoles"
                                            multiple
                                            class="block w-full border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            style="min-height: 42px;">
                                        @foreach($rolesList as $r)
                                            <option value="{{ $r['id'] }}">{{ $r['name'] }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-gray-500">Puedes seleccionar varios con Ctrl/⌘</small>
                                </div>

                            </div>{{-- /filters-grid --}}

                            {{-- Botón limpiar filtros --}}
                            @php
                                $hasAnyFilter =
                                    ($filterServicio !== '')
                                    || ($filterContrato !== '')
                                    || ($filterPago !== '')
                                    || ($filterProveedor !== '')
                                    || ($filterOwner !== '')
                                    || (!empty($filterRoles));
                            @endphp

                            @if ($hasAnyFilter)
                                <div class="filters-actions">
                                    <button wire:click="clearFilters"
                                            class="btn-clear py-2 px-4 border border-transparent rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 text-sm">
                                        Limpiar filtros <i class="fas fa-times ml-1"></i>
                                    </button>
                                </div>
                            @endif

                        </div>{{-- /filters-container --}}
                    </div>

                    @if ($users->count())

                        {{-- ========== VISTA CARDS (Móvil) ========== --}}
                        <div class="cards-view">
                            @foreach ($users as $user)
                                @php
                                    // ✅ Decide cliente SIN queries extra (ya viene with roles)
                                    $isCliente = $user->roles->contains(fn($r) => $r->name === 'Cliente');
                                @endphp

                                <div class="bg-white border-b border-gray-200 p-4">
                                    <div class="mb-3">
                                        @if($user->estado_vendedor === 'Pendiente')
                                            <span class="badge badge-warning ml-2">
                                                <i class="fas fa-clock mr-1"></i> Pendiente
                                            </span>
                                        @endif

                                        <h3 class="text-lg font-semibold text-gray-900">{{ \Illuminate\Support\Str::limit($user->name, 30) }}</h3>

                                        <p class="text-sm text-gray-600 mt-1">
                                            <i class="fas fa-envelope mr-1"></i> {{ $user->email }}
                                        </p>

                                        @if($user->passport)
                                            <p class="text-sm text-gray-600">
                                                <i class="fas fa-passport mr-1"></i> {{ $user->passport }}
                                            </p>
                                        @endif
                                    </div>

                                    <div class="mb-3 p-3 bg-gray-50 rounded-md">
                                        <h4 class="text-xs font-semibold text-gray-700 uppercase mb-2">Servicios y Pago</h4>

                                        @php
                                            $helperc = 0;
                                            foreach ($user->compras as $compra) {
                                                if ($compra['id_user'] == $user->id){
                                                    $helperc = 1;
                                                    break;
                                                }
                                            }
                                        @endphp

                                        @if ($helperc==1)
                                            @foreach ($user->compras as $compra)
                                                @if ($compra['id_user'] == $user->id && $compra['servicio_hs_id'])
                                                    <p class="text-sm mb-1">
                                                        <span class="font-semibold">{{ $compra['servicio_hs_id'] }}</span>
                                                        <span class="ml-2 px-2 py-1 text-xs rounded {{ $compra->pagado == 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                                            {{ $compra->pagado == 0 ? 'No pagó Registro' : 'Pagó Registro' }}
                                                        </span>
                                                    </p>
                                                @endif
                                            @endforeach

                                            <p class="text-sm text-gray-600">
                                                {{ $user->pay == 2 ? '✓ Completó información' : '✗ NO completó información' }}
                                                <b>{{ $user->pay == 3 ? ' - Estatus 3 activo' : '' }}</b>
                                            </p>
                                        @else
                                            @if (auth()->user()->roles[0]->id == 1)
                                                <p class="text-sm mb-1">{{ $user->servicio == null ? ($user->getRoleNames()[0] ?? 'Cliente') : $user->servicio }}</p>
                                            @else
                                                <p class="text-sm mb-1">{{ $user->servicio != null ? $user->servicio : "Usuario App" }}</p>
                                            @endif

                                            <p class="text-sm">
                                                <span class="px-2 py-1 rounded {{ $user->pay == 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                                    {{ $user->pay == 0 ? 'No pagó Registro' : ($user->pay == 1 ? 'Pagó Registro' : 'Pagó Registro y completó información') }}
                                                </span>
                                                <b>{{ $user->pay == 3 ? ' - Estatus 3' : '' }}</b>
                                            </p>
                                        @endif

                                        <p class="text-sm mt-2 {{ $user->contrato ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $user->contrato ? '✓ Contrato firmado' : '✗ Contrato NO firmado' }}
                                        </p>
                                    </div>

                                    @if(auth()->user()->email == 'sistemasccs@sefarvzla.com')
                                        <div class="mb-3 flex justify-between text-sm text-gray-500">
                                            <span><i class="fas fa-calendar mr-1"></i> {{ date_format($user->created_at,"Y-m-d") }}</span>
                                            <span><i class="fas fa-id-card mr-1"></i> ID: {{ $user->id }}</span>
                                        </div>
                                    @endif

                                    {{-- ✅ Acciones --}}
                                    <div class="flex flex-wrap gap-2 justify-center pt-3 border-t border-gray-200">

                                        {{-- NO cliente: solo editBasic + eliminar --}}
                                        @if(!$isCliente)
                                            @role('Administrador')
                                                <div class="tooltip-container flex-1">
                                                    <a href="{{ route('crud.users.editBasic', $user) }}"
                                                       class="btn btn-info w-full text-center">
                                                        <i class="fas fa-user-cog fa-fw"></i>
                                                    </a>
                                                    <span class="tooltip-text">Edición básica (sin COS)</span>
                                                </div>
                                            @endrole

                                            @if(auth()->user()->roles[0]->id == 1)
                                                <div class="tooltip-container flex-1">
                                                    <form action="{{ route('crud.users.destroy', $user) }}" method="POST" class="w-full">
                                                        @csrf
                                                        @method('delete')
                                                        <button type="submit" class="btn btn-danger w-full"
                                                                onclick="return confirm('¿Está seguro que desea eliminar a este usuario?')">
                                                            <i class="fas fa-trash fa-fw"></i>
                                                        </button>
                                                    </form>
                                                    <span class="tooltip-text">Eliminar Usuario</span>
                                                </div>
                                            @endif

                                            <div class="tooltip-container flex-1">
                                                <button type="button"
                                                        class="btn btn-secondary w-full"
                                                        wire:click="showProveedorModal({{ $user->id }})">
                                                    <i class="fas fa-eye fa-fw"></i>
                                                </button>
                                                <span class="tooltip-text">Ver registro del proveedor</span>
                                            </div>
                                        @else
                                            {{-- Cliente: botones normales --}}
                                            @can('crud.users.edit')
                                                <div class="tooltip-container flex-1">
                                                    <a href="{{ route('crud.users.edit', $user) }}"
                                                       class="btn btn-primary edit-user-btn btn_status_loader w-full text-center"
                                                       data-href="{{ route('crud.users.edit', $user) }}">
                                                        <i class="fas fa-edit fa-fw"></i>
                                                    </a>
                                                    <span class="tooltip-text">Editar Usuario</span>
                                                </div>
                                            @endcan

                                            @role('Administrador')
                                                <div class="tooltip-container flex-1">
                                                    <a href="{{ route('crud.users.editBasic', $user) }}"
                                                       class="btn btn-info w-full text-center">
                                                        <i class="fas fa-user-cog fa-fw"></i>
                                                    </a>
                                                    <span class="tooltip-text">Edición básica (sin COS)</span>
                                                </div>
                                            @endrole

                                            @if(auth()->user()->roles[0]->id == 1)
                                                <div class="tooltip-container flex-1">
                                                    <form action="{{ route('crud.users.destroy', $user) }}" method="POST" class="w-full">
                                                        @csrf
                                                        @method('delete')
                                                        <button type="submit" class="btn btn-danger w-full"
                                                                onclick="return confirm('¿Está seguro que desea eliminar a este usuario?')">
                                                            <i class="fas fa-trash fa-fw"></i>
                                                        </button>
                                                    </form>
                                                    <span class="tooltip-text">Eliminar Usuario</span>
                                                </div>
                                            @endif

                                            <div class="tooltip-container flex-1">
                                                <a style="color:white!important;"
                                                   href="{{ route('crud.users.edit', $user) }}"
                                                   class="btn btn-warning edit-user-btn btn_status_loader w-full text-center"
                                                   data-href="{{ route('crud.users.edit', $user) }}">
                                                    <i class="fas fa-exclamation fa-fw"></i>
                                                </a>
                                                <span class="tooltip-text">Estatus del Cliente</span>
                                            </div>

                                            @if (isset($user->passport))
                                                <div class="tooltip-container flex-1">
                                                    <a style="color:white!important;"
                                                       href="{{ route('arboles.tree.index', $user->passport) }}"
                                                       class="btn btn-success w-full text-center">
                                                        <i class="fab fa-pagelines fa-fw"></i>
                                                    </a>
                                                    <span class="tooltip-text">Ver Árbol Genealógico</span>
                                                </div>
                                            @endif
                                        @endif

                                        {{-- ✅ Pendiente proveedor: SweetAlert + Modal --}}
                                        @if(auth()->user()->roles[0]->id == 1 && $user->estado_vendedor === 'Pendiente')
                                            <div class="tooltip-container flex-1">
                                                <button type="button"
                                                        class="btn btn-success w-full"
                                                        wire:click="$dispatch('swal:confirm-approve', { id: {{ $user->id }} })">
                                                    <i class="fas fa-check fa-fw"></i>
                                                </button>
                                                <span class="tooltip-text">Aprobar proveedor</span>
                                            </div>

                                            <div class="tooltip-container flex-1">
                                                <button type="button"
                                                        class="btn btn-danger w-full"
                                                        wire:click="$dispatch('swal:confirm-reject', { id: {{ $user->id }} })">
                                                    <i class="fas fa-times fa-fw"></i>
                                                </button>
                                                <span class="tooltip-text">Rechazar y eliminar</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- ========== VISTA TABLA (Desktop) ========== --}}
                        <div class="table-view">
                            <div style="overflow-x: auto;">
                                <table class="users-table">
                                    <thead>
                                    <tr>
                                        <th>Nombre, Correo y Pasaporte</th>
                                        <th>Servicios Solicitados / Pago</th>
                                        @if(auth()->user()->email == 'sistemasccs@sefarvzla.com')
                                            <th>Fecha Registro / ID</th>
                                        @endif
                                        <th style="text-align:center;">Opciones</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    @foreach ($users as $user)
                                        @php
                                            $isCliente = $user->roles->contains(fn($r) => $r->name === 'Cliente');
                                        @endphp

                                        <tr>
                                            <td>
                                                @if($user->estado_vendedor === 'Pendiente')
                                                    <span class="badge badge-warning ml-2">
                                                        <i class="fas fa-clock mr-1"></i> Pendiente
                                                    </span>
                                                @endif

                                                <p class="user-name">{{ \Illuminate\Support\Str::limit($user->name, 25) }}</p>
                                                <p class="user-info">{{ $user->email }}</p>
                                                <p class="user-info">{{ $user->passport }}</p>
                                            </td>

                                            <td>
                                                @php
                                                    $helperc = 0;
                                                    foreach ($user->compras as $compra) {
                                                        if ($compra['id_user'] == $user->id){
                                                            $helperc = 1;
                                                            break;
                                                        }
                                                    }
                                                @endphp

                                                @if ($helperc==1)
                                                    @foreach ($user->compras as $compra)
                                                        @if ($compra['id_user'] == $user->id && $compra['servicio_hs_id'])
                                                            <p class="service-item">
                                                                <b>{{ $compra['servicio_hs_id'] }}</b>
                                                                <span class="{{ $compra->pagado == 0 ? 'badge-unpaid' : 'badge-paid' }}">
                                                                    {{ $compra->pagado == 0 ? 'No pagó Registro' : 'Pagó Registro' }}
                                                                </span>
                                                            </p>
                                                        @endif
                                                    @endforeach

                                                    <p class="service-item">
                                                        {{ $user->pay == 2 ? 'Completó información' : 'NO completó información' }}
                                                        <b>{{ $user->pay == 3 ? ' - Estatus 3' : '' }}</b>
                                                    </p>
                                                @else
                                                    @if (auth()->user()->roles[0]->id == 1)
                                                        <p class="service-item">{{ $user->servicio == null ? ($user->getRoleNames()[0] ?? 'Cliente') : $user->servicio }}</p>
                                                    @else
                                                        <p class="service-item">{{ $user->servicio != null ? $user->servicio : "Usuario App" }}</p>
                                                    @endif

                                                    <p class="service-item">
                                                        <span class="{{ $user->pay == 0 ? 'badge-unpaid' : 'badge-paid' }}">
                                                            {{ $user->pay == 0 ? 'No pagó Registro' : ($user->pay == 1 ? 'Pagó Registro' : 'Pagó Registro y completó información') }}
                                                        </span>
                                                        <b>{{ $user->pay == 3 ? ' - Estatus 3' : '' }}</b>
                                                    </p>
                                                @endif

                                                @if($user->contrato)
                                                    <p class="service-item" style="color:#065f46;">✓ Contrato firmado</p>
                                                @else
                                                    <p class="service-item" style="color:#991b1b;">✗ NO firmó contrato</p>
                                                @endif
                                            </td>

                                            @if(auth()->user()->email == 'sistemasccs@sefarvzla.com')
                                                <td>
                                                    <p class="service-item">{{ date_format($user->created_at,"Y-m-d") }}</p>
                                                    <p class="user-info">ID: {{ $user->id }}</p>
                                                </td>
                                            @endif

                                            <td class="actions-cell">
                                                <div class="action-buttons">

                                                    {{-- NO cliente: solo editBasic + eliminar --}}
                                                    @if(!$isCliente)
                                                        @role('Administrador')
                                                            <div class="tooltip-container">
                                                                <a href="{{ route('crud.users.editBasic', $user) }}"
                                                                   class="btn btn-info">
                                                                    <i class="fas fa-user-cog fa-fw"></i>
                                                                </a>
                                                                <span class="tooltip-text">Edición básica (sin COS)</span>
                                                            </div>
                                                        @endrole

                                                        @if(auth()->user()->roles[0]->id == 1)
                                                            <div class="tooltip-container">
                                                                <form action="{{ route('crud.users.destroy', $user) }}" method="POST" style="display:inline-block;">
                                                                    @csrf
                                                                    @method('delete')
                                                                    <button type="submit" class="btn btn-danger"
                                                                            onclick="return confirm('¿Está seguro que desea eliminar a este usuario?')">
                                                                        <i class="fas fa-trash fa-fw"></i>
                                                                    </button>
                                                                </form>
                                                                <span class="tooltip-text">Eliminar Usuario</span>
                                                            </div>
                                                        @endif

                                                        <button type="button"
                                                                class="btn btn-secondary"
                                                                wire:click="showProveedorModal({{ $user->id }})">
                                                            <i class="fas fa-eye fa-fw"></i>
                                                        </button>
                                                    @else
                                                        {{-- Cliente: botones normales --}}
                                                        @can('crud.users.edit')
                                                            <div class="tooltip-container">
                                                                <a href="{{ route('crud.users.edit', $user) }}"
                                                                   class="btn btn-primary edit-user-btn btn_status_loader"
                                                                   data-href="{{ route('crud.users.edit', $user) }}">
                                                                    <i class="fas fa-edit fa-fw"></i>
                                                                </a>
                                                                <span class="tooltip-text">Editar Usuario</span>
                                                            </div>
                                                        @endcan

                                                        @role('Administrador')
                                                            <div class="tooltip-container">
                                                                <a href="{{ route('crud.users.editBasic', $user) }}"
                                                                   class="btn btn-info">
                                                                    <i class="fas fa-user-cog fa-fw"></i>
                                                                </a>
                                                                <span class="tooltip-text">Edición básica (sin COS)</span>
                                                            </div>
                                                        @endrole

                                                        @if(auth()->user()->roles[0]->id == 1)
                                                            <div class="tooltip-container">
                                                                <form action="{{ route('crud.users.destroy', $user) }}" method="POST" style="display:inline-block;">
                                                                    @csrf
                                                                    @method('delete')
                                                                    <button type="submit" class="btn btn-danger"
                                                                            onclick="return confirm('¿Está seguro que desea eliminar a este usuario?')">
                                                                        <i class="fas fa-trash fa-fw"></i>
                                                                    </button>
                                                                </form>
                                                                <span class="tooltip-text">Eliminar Usuario</span>
                                                            </div>
                                                        @endif

                                                        <div class="tooltip-container">
                                                            <a style="color:white!important;"
                                                               href="{{ route('crud.users.edit', $user) }}"
                                                               class="btn btn-warning edit-user-btn btn_status_loader"
                                                               data-href="{{ route('crud.users.edit', $user) }}">
                                                                <i class="fas fa-exclamation fa-fw"></i>
                                                            </a>
                                                            <span class="tooltip-text">Estatus del Cliente</span>
                                                        </div>

                                                        @if (isset($user->passport))
                                                            <div class="tooltip-container">
                                                                <a style="color:white!important;"
                                                                   href="{{ route('arboles.tree.index', $user->passport) }}"
                                                                   class="btn btn-success">
                                                                    <i class="fab fa-pagelines fa-fw"></i>
                                                                </a>
                                                                <span class="tooltip-text">Ver Árbol Genealógico</span>
                                                            </div>
                                                        @endif
                                                    @endif
                                                </div>

                                                <div class="action-buttons mt-1">
                                                    {{-- ✅ Pendiente proveedor: SweetAlert + Modal --}}
                                                    @if(auth()->user()->roles[0]->id == 1 && $user->estado_vendedor === 'Pendiente')
                                                        <button type="button"
                                                                class="btn btn-success"
                                                                wire:click="$dispatch('swal:confirm-approve', { id: {{ $user->id }} })">
                                                            <i class="fas fa-check fa-fw"></i>
                                                        </button>

                                                        <button type="button"
                                                                class="btn btn-danger"
                                                                wire:click="$dispatch('swal:confirm-reject', { id: {{ $user->id }} })">
                                                            <i class="fas fa-trash fa-fw"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                            {{ $users->links() }}
                        </div>
                    @else
                        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6 text-gray-500">
                            No hay resultado para la búsqueda "{{ $search }}" en la página {{ $page ?? 1 }} al mostrar {{ $perPage }} por página
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ✅ SweetAlert2 confirm + toast --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('livewire:init', () => {

            window.addEventListener('swal:confirm-approve', async (e) => {
                const id = e.detail.id;

                const res = await Swal.fire({
                    title: '¿Aprobar proveedor?',
                    text: 'El estado_vendedor pasará a NULL y se guardará la fecha de activación.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, aprobar',
                    cancelButtonText: 'Cancelar',
                });

                if (res.isConfirmed) {
                    @this.call('approveProveedor', id);
                }
            });

            window.addEventListener('swal:confirm-reject', async (e) => {
                const id = e.detail.id;

                const res = await Swal.fire({
                    title: '¿Rechazar proveedor?',
                    text: 'Se eliminará de la base de datos.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                });

                if (res.isConfirmed) {
                    @this.call('rejectProveedor', id);
                }
            });

            Livewire.on('swal:toast', (payload) => {
                const data = payload?.[0] ?? {};
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    timer: 2500,
                    showConfirmButton: false,
                    icon: data.icon ?? 'success',
                    title: data.title ?? 'Listo',
                });
            });

        });
    </script>
</div>
