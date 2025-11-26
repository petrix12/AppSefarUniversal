<div>
    {{-- Estilos CSS puros para responsive --}}
    <style>
        /* ========== FILTROS RESPONSIVE ========== */
        .filters-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* En pantallas horizontales (mayor a 1023px) - 2 FILAS */
        @media (min-width: 1024px) {
            .filters-container {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                grid-template-rows: auto auto;
                gap: 16px;
            }

            .filter-item {
                grid-row: 1;
            }

            .filter-button {
                grid-column: 1 / -1;
                grid-row: 2;
                justify-self: end;
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
                                <a href="{{ route('crud.users.create') }}" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
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
                        <button wire:click="clear" class="py-1 px-4 mt-1 border border-transparent rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="far fa-window-close"></i>
                        </button>
                        @endif
                    </div>

                    <div class="flex bg-white px-4 sm:px-6 pb-2">
                        <small>Para una búsqueda más exacta, busca por <b>correo</b> o <b>número de pasaporte</b></small>
                    </div>

                    <!-- Filtros en 2 líneas -->
                    <div class="bg-white px-4 py-3 sm:px-6">
                        <div class="filters-container">
                            <!-- Filtro 1: Servicio -->
                            <div class="filter-item">
                                <label for="filterServicio" class="block text-xs font-medium text-gray-700 mb-1">Servicio</label>
                                <select wire:model.live="filterServicio" id="filterServicio" class="block w-full border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
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

                            <!-- Filtro 2: Contrato -->
                            <div class="filter-item">
                                <label for="filterContrato" class="block text-xs font-medium text-gray-700 mb-1">Contrato</label>
                                <select wire:model.live="filterContrato" id="filterContrato" class="block w-full border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Todos</option>
                                    <option value="1">Firmado</option>
                                    <option value="0">No firmado</option>
                                </select>
                            </div>

                            <!-- Filtro 3: Pago -->
                            <div class="filter-item">
                                <label for="filterPago" class="block text-xs font-medium text-gray-700 mb-1">Estado de Pago</label>
                                <select wire:model.live="filterPago" id="filterPago" class="block w-full border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Todos</option>
                                    <option value="2">Pagó y completó información</option>
                                    <option value="1">Pagó pero no completó información</option>
                                    <option value="0">No pagó</option>
                                </select>
                            </div>

                            <!-- Botón limpiar - Segunda fila en desktop -->
                            @if ($filterServicio || $filterContrato !== '' || $filterPago !== '')
                            <div class="filter-button">
                                <button wire:click="clearFilters" class="w-full py-2 px-4 border border-transparent rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" style="max-width: 200px;">
                                    Limpiar filtros <i class="fas fa-times ml-1"></i>
                                </button>
                            </div>
                            @endif
                        </div>
                    </div>

                    <script>
                        document.addEventListener('livewire:init', () => {
                            Livewire.on('filtersCleared', () => {
                                const select = document.getElementById('filterServicio');
                                if (select) {
                                    select.value = '';
                                }
                            });
                        });
                    </script>

                    @if ($users->count())

                    {{-- ========== VISTA CARDS (Móvil/Vertical) ========== --}}
                    <div class="cards-view">
                        @foreach ($users as $user)
                        <div class="bg-white border-b border-gray-200 p-4">
                            <div class="mb-3">
                                <h3 class="text-lg font-semibold text-gray-900">{{ Str::limit($user->name, 30) }}</h3>
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
                                <?php
                                    $helperc = 0;
                                    foreach ($compras as $compra) {
                                        if ($compra['id_user'] == $user->id){
                                            $helperc = 1;
                                            break;
                                        }
                                    }
                                ?>
                                @if ($helperc==1)
                                    @foreach ($compras as $compra)
                                        @if ($compra['id_user'] == $user->id && $compra['servicio_hs_id'])
                                            <p class="text-sm mb-1">
                                                <span class="font-semibold">{{ $compra['servicio_hs_id'] }}</span>
                                                <span class="ml-2 px-2 py-1 text-xs rounded {{ $compra->pagado == 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                                    {{ $compra->pagado == 0 ? 'No pagó' : 'Pagó' }}
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
                                        <p class="text-sm mb-1">{{ $user->servicio == null ? $user->getRoleNames()[0] ?? 'Sin rol' : $user->servicio }}</p>
                                    @else
                                        <p class="text-sm mb-1">{{ $user->servicio != null ? $user->servicio : "Usuario App" }}</p>
                                    @endif
                                    <p class="text-sm">
                                        <span class="px-2 py-1 rounded {{ $user->pay == 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                            {{ $user->pay == 0 ? 'No pagó' : ($user->pay == 1 ? 'Pagó' : 'Pagó y completó info') }}
                                        </span>
                                        <b>{{ $user->pay == 3 ? ' - Estatus 3' : '' }}</b>
                                    </p>
                                @endif

                                <p class="text-sm mt-2 {{ $user->contrato ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $user->contrato ? '✓ Contrato firmado' : '✗ Contrato NO firmado' }}
                                </p>
                            </div>

                            <div class="mb-3 flex justify-between text-sm text-gray-500">
                                <span><i class="fas fa-calendar mr-1"></i> {{ date_format($user->created_at,"Y-m-d") }}</span>
                                <span><i class="fas fa-id-card mr-1"></i> ID: {{ $user->id }}</span>
                            </div>

                            <div class="flex flex-wrap gap-2 justify-center pt-3 border-t border-gray-200">
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

                                @if(auth()->user()->roles[0]->id == 1)
                                    <div class="tooltip-container flex-1">
                                        <form action="{{ route('crud.users.destroy', $user) }}" method="POST" class="w-full">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="btn btn-danger w-full" onclick="return confirm('¿Está seguro que desea eliminar a este usuario?')">
                                                <i class="fas fa-trash fa-fw"></i>
                                            </button>
                                        </form>
                                        <span class="tooltip-text">Eliminar Usuario</span>
                                    </div>
                                @endif

                                @if($user->getRoleNames()->first() == "Cliente")
                                    <div class="tooltip-container flex-1">
                                        <a style="color:white!important;"
                                           href="{{ route('crud.users.edit', $user) }}"
                                           class="btn btn-warning edit-user-btn btn_status_loader w-full text-center"
                                           data-href="{{ route('crud.users.edit', $user) }}">
                                           <i class="fas fa-exclamation fa-fw"></i>
                                        </a>
                                        <span class="tooltip-text">Estatus del Cliente</span>
                                    </div>
                                @endif

                                @if ($user->getRoleNames()->first() == "Cliente" && isset($user->passport))
                                    <div class="tooltip-container flex-1">
                                        <a style="color:white!important;"
                                           href="{{ route('arboles.tree.index', $user->passport) }}"
                                           class="btn btn-success w-full text-center">
                                           <i class="fab fa-pagelines fa-fw"></i>
                                        </a>
                                        <span class="tooltip-text">Ver Árbol Genealógico</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>

                    {{-- ========== VISTA TABLA (Desktop/Horizontal) ========== --}}
                    <div class="table-view">
                        <div style="overflow-x: auto;">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>Nombre, Correo y Pasaporte</th>
                                        <th>Servicios Solicitados / Pago</th>
                                        <th>Fecha Registro / ID</th>
                                        <th style="text-align: center;">Opciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach ($users as $user)
                                <tr>
                                    <td>
                                        <p class="user-name">{{ Str::limit($user->name, 25) }}</p>
                                        <p class="user-info">{{ $user->email }}</p>
                                        <p class="user-info">{{ $user->passport }}</p>
                                    </td>
                                    <td>
                                        <?php
                                            $helperc = 0;
                                            foreach ($compras as $compra) {
                                                if ($compra['id_user'] == $user->id){
                                                    $helperc = 1;
                                                    break;
                                                }
                                            }
                                        ?>
                                        @if ($helperc==1)
                                            @foreach ($compras as $compra)
                                                @if ($compra['id_user'] == $user->id && $compra['servicio_hs_id'])
                                                    <p class="service-item">
                                                        <b>{{ $compra['servicio_hs_id'] }}</b>
                                                        <span class="{{ $compra->pagado == 0 ? 'badge-unpaid' : 'badge-paid' }}">
                                                            {{ $compra->pagado == 0 ? 'No pagó' : 'Pagó' }}
                                                        </span>
                                                    </p>
                                                @endif
                                            @endforeach
                                            <p class="service-item">{{ $user->pay == 2 ? 'Completó información' : 'NO completó información' }}<b>{{ $user->pay == 3 ? ' - Estatus 3' : '' }}</b></p>
                                        @else
                                            @if (auth()->user()->roles[0]->id == 1)
                                                <p class="service-item">{{ $user->servicio == null ? $user->getRoleNames()[0] ?? 'Sin rol' : $user->servicio }}</p>
                                            @else
                                                <p class="service-item">{{ $user->servicio != null ? $user->servicio : "Usuario App" }}</p>
                                            @endif
                                            <p class="service-item">
                                                <span class="{{ $user->pay == 0 ? 'badge-unpaid' : 'badge-paid' }}">
                                                    {{ $user->pay == 0 ? 'No pagó' : ($user->pay == 1 ? 'Pagó' : 'Pagó y completó info') }}
                                                </span>
                                                <b>{{ $user->pay == 3 ? ' - Estatus 3' : '' }}</b>
                                            </p>
                                        @endif
                                        @if($user->contrato)
                                            <p class="service-item" style="color: #065f46;">✓ Contrato firmado</p>
                                        @else
                                            <p class="service-item" style="color: #991b1b;">✗ NO firmó contrato</p>
                                        @endif
                                    </td>
                                    <td>
                                        <p class="service-item">{{ date_format($user->created_at,"Y-m-d") }}</p>
                                        <p class="user-info">ID: {{ $user->id }}</p>
                                    </td>
                                    <td class="actions-cell">
                                        <div class="action-buttons">
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
                                            @if(auth()->user()->roles[0]->id == 1)
                                                <div class="tooltip-container">
                                                    <form action="{{ route('crud.users.destroy', $user) }}" method="POST" style="display: inline-block;">
                                                        @csrf
                                                        @method('delete')
                                                        <button type="submit" class="btn btn-danger" onclick="return confirm('¿Está seguro que desea eliminar a este usuario?')">
                                                            <i class="fas fa-trash fa-fw"></i>
                                                        </button>
                                                    </form>
                                                    <span class="tooltip-text">Eliminar Usuario</span>
                                                </div>
                                            @endif
                                            @if($user->getRoleNames()->first() == "Cliente")
                                                <div class="tooltip-container">
                                                    <a style="color:white!important;"
                                                       href="{{ route('crud.users.edit', $user) }}"
                                                       class="btn btn-warning edit-user-btn btn_status_loader"
                                                       data-href="{{ route('crud.users.edit', $user) }}">
                                                       <i class="fas fa-exclamation fa-fw"></i>
                                                    </a>
                                                    <span class="tooltip-text">Estatus del Cliente</span>
                                                </div>
                                            @endif
                                            @if ($user->getRoleNames()->first() == "Cliente" && isset($user->passport))
                                                <div class="tooltip-container">
                                                    <a style="color:white!important;"
                                                       href="{{ route('arboles.tree.index', $user->passport) }}"
                                                       class="btn btn-success">
                                                       <i class="fab fa-pagelines fa-fw"></i>
                                                    </a>
                                                    <span class="tooltip-text">Ver Árbol Genealógico</span>
                                                </div>
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
</div>
