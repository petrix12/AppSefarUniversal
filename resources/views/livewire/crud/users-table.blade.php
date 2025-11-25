<div>
    <!-- This example requires Tailwind CSS v2.0+ -->
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                {{-- Inicio --}}
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
                {{-- Fin --}}
            </div>
        </div>
    </div>

    {{-- Estilos para tooltips --}}
    <style>
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
    </style>

    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
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
                            <i class="far fa-window-close"></i> <span class="sm:hidden">Limpiar</span>
                        </button>
                        @endif
                    </div>

                    <div class="flex bg-white px-4 sm:px-6 pb-2">
                        <small>Para una búsqueda más exacta, busca por <b>correo</b> o <b>número de pasaporte</b></small>
                    </div>

                    {{-- FILTROS: Columna vertical en móvil, 2-3 columnas en desktop --}}
                    <div class="bg-white px-4 py-3 sm:px-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            {{-- Servicio contratado --}}
                            <div class="w-full">
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

                            {{-- Contrato firmado --}}
                            <div class="w-full">
                                <label for="filterContrato" class="block text-xs font-medium text-gray-700 mb-1">Contrato</label>
                                <select wire:model.live="filterContrato" id="filterContrato" class="block w-full border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Todos</option>
                                    <option value="1">Firmado</option>
                                    <option value="0">No firmado</option>
                                </select>
                            </div>

                            {{-- Pago --}}
                            <div class="w-full">
                                <label for="filterPago" class="block text-xs font-medium text-gray-700 mb-1">Estado de Pago</label>
                                <select wire:model.live="filterPago" id="filterPago" class="block w-full border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Todos</option>
                                    <option value="2">Pagó y completó información</option>
                                    <option value="1">Pagó pero no completó información</option>
                                    <option value="0">No pagó</option>
                                </select>
                            </div>

                            {{-- Botón limpiar - Segunda fila, alineado a la derecha --}}
                            @if ($filterServicio || $filterContrato !== '' || $filterPago !== '')
                            <div class="w-full sm:col-span-2 lg:col-span-3 flex justify-start sm:justify-end">
                                <button wire:click="clearFilters" class="w-full sm:w-auto py-2 px-4 border border-transparent rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
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

                    {{-- ✅ VISTA MÓVIL (cards) - Solo móviles y tablets pequeñas --}}
                    <div class="lg:hidden">
                        @foreach ($users as $user)
                        <div class="bg-white border-b border-gray-200 p-4">
                            {{-- Nombre y datos principales --}}
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

                            {{-- Servicios y pago --}}
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

                            {{-- Fecha e ID --}}
                            <div class="mb-3 flex justify-between text-sm text-gray-500">
                                <span><i class="fas fa-calendar mr-1"></i> {{ date_format($user->created_at,"Y-m-d") }}</span>
                                <span><i class="fas fa-id-card mr-1"></i> ID: {{ $user->id }}</span>
                            </div>

                            {{-- Botones de acción CON TOOLTIPS --}}
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

                                @can('crud.users.destroy')
                                    <div class="tooltip-container flex-1">
                                        <form action="{{ route('crud.users.destroy', $user) }}" method="POST" class="w-full">
                                            @csrf
                                            @method('delete')
                                            <button
                                                type="submit"
                                                class="btn btn-danger w-full"
                                                onclick="return confirm('¿Está seguro que desea eliminar a este usuario?')">
                                                <i class="fas fa-trash fa-fw"></i>
                                            </button>
                                        </form>
                                        <span class="tooltip-text">Eliminar Usuario</span>
                                    </div>
                                @endcan

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

                    {{-- ✅ VISTA DESKTOP (tabla) - Solo pantallas grandes --}}
                    <div class="hidden lg:block">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Nombre, Correo y Pasaporte
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Servicios Solicitados / Pago
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Fecha Registro / ID
                                        </th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Opciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($users as $user)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <p class="font-semibold">{{ Str::limit($user->name, 25) }}</p>
                                        <p class="text-sm text-gray-500">{{ $user->email }}</p>
                                        <p class="text-sm text-gray-500">{{ $user->passport }}</p>
                                    </td>
                                    <td class="px-6 py-4">
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
                                                    <p class="text-sm"><b>{{ $compra['servicio_hs_id'] }}</b> - {{ $compra->pagado == 0 ? 'No ha pagado' : 'Pagó' }}</p>
                                                @endif
                                            @endforeach
                                            <p class="text-sm">{{ $user->pay == 2 ? 'El usuario completó información' : 'El usuario NO completó información' }}<b>{{ $user->pay == 3 ? ' - Estatus 3 activo' : '' }}</b></p>
                                        @else
                                            @if (auth()->user()->roles[0]->id == 1)
                                                <p class="text-sm">{{ $user->servicio == null ? $user->getRoleNames()[0] ?? 'Sin rol' : $user->servicio }}</p>
                                            @else
                                                <p class="text-sm">{{ $user->servicio != null ? $user->servicio : "Usuario App" }}</p>
                                            @endif
                                            <p class="text-sm">{{ $user->pay == 0 ? 'No ha pagado' : ($user->pay == 1 ? 'Pagó' : 'Pagó y completó información') }}<b>{{ $user->pay == 3 ? ' - Estatus 3 activo' : '' }}</b></p>
                                        @endif
                                        @if($user->contrato)
                                            <p class="text-sm">El usuario ya firmó su contrato</p>
                                        @else
                                            <p class="text-sm">El usuario <b>NO</b> ha firmado su contrato</p>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <p class="text-sm">{{ date_format($user->created_at,"Y-m-d") }}</p>
                                        <p class="text-sm text-gray-500">ID: {{ $user->id }}</p>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex justify-center items-center space-x-2">
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
                                            @can('crud.users.destroy')
                                                <div class="tooltip-container">
                                                    <form action="{{ route('crud.users.destroy', $user) }}" method="POST" class="inline-block">
                                                        @csrf
                                                        @method('delete')
                                                        <button
                                                            type="submit"
                                                            class="btn btn-danger"
                                                            onclick="return confirm('¿Está seguro que desea eliminar a este usuario?')">
                                                            <i class="fas fa-trash fa-fw"></i>
                                                        </button>
                                                    </form>
                                                    <span class="tooltip-text">Eliminar Usuario</span>
                                                </div>
                                            @endcan
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
