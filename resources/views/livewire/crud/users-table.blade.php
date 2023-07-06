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
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg" style="max-width: 93%;">
                    <div class="flex bg-white px-4 py-3 sm:px-6">
                        <input
                            wire:model="search"
                            type="text"
                            placeholder="Buscar..."
                            class="mr-2 mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                        >
                        <div class="col-span-6 sm:col-span-3">
                            <select wire:model="perPage" class="py-2 px-2 mt-1 mr-10 block w-full border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="5">5 por pág. </option>
                                <option value="10">10 por pág.</option>
                                <option value="15">15 por pág.</option>
                                <option value="25">25 por pág.</option>
                                <option value="50">50 por pág.</option>
                                <option value="100">100 por pág.</option>
                            </select>
                        </div>
                        @if ($search !== '')
                        <button wire:click="clear" class="py-1 px-2 mt-1 ml-2 border border-transparent rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"><i class="far fa-window-close"></i></button>
                        @endif
                    </div>
                    @if ($users->count())
                    <table class="min-w-full divide-y divide-gray-200" style="max-width: 100%;">
                        <thead class="bg-gray-50">
                            <tr>
                                <th style="padding: 10px 15px;">
                                    Nombre, Correo y Pasaporte
                                </th>
                                <th style="padding: 10px 15px;">
                                    Servicios Solicitados / Pago
                                </th>
                                <th style="padding: 10px 15px;">
                                    Fecha Registro / ID
                                </th>
                                <th style="padding: 10px 15px;">
                                    Opciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($users as $user)
                        <tr>
                            <td class="whitespace-nowrap" style="padding: 5px 15px;">
                                <p><b>{{ Str::limit($user->name, 25) }}</b></p>
                                <p><small>{{ $user->email }}</small></p>
                                <p><small>{{ $user->passport }}</small></p>
                            </td>
                            <td class="whitespace-nowrap" style="padding: 5px 15px;">
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
                                        @if ($compra['id_user'] == $user->id)
                                            <p><small><b>{{ $compra['servicio_hs_id'] }}</b> - {{ $compra->pagado == 0 ? 'No ha pagado' : 'Pagó' }}</small></p>
                                        @endif
                                    @endforeach
                                    <p><small>{{ $user->pay == 2 ? 'El usuario completó información' : 'El usuario NO completó información' }}</p>
                                @else
                                    <p><small>{{ $user->servicio == null ? $user->getRoleNames()[0] ?? 'Sin rol' : $user->servicio }}</small></p>
                                    <p><small>{{ $user->pay == 0 ? 'No ha pagado' : ($user->pay == 1 ? 'Pagó' : 'Pagó y completó información') }}</small></p>
                                @endif
                                
                            </td>
                            <td class="whitespace-nowrap" style="padding: 5px 15px;">
                                <p><small>{{ date_format($user->created_at,"Y-m-d") }}</small></p>
                                <p><small>{{ $user->id }}</small></p>
                            </td>
                            <td class="whitespace-nowrap" style="padding: 5px 15px;">
                                <div style="display: flex; justify-content:center;">
                                    @can('crud.users.edit')
                                        <a href="{{ route('crud.users.edit', $user) }}" class="btn btn-primary" title="Editar Usuario"><i class="fas fa-edit fa-fw"></i></a>&#160;
                                    @endcan
                                    @can('crud.users.destroy')
                                        <form action="{{ route('crud.users.destroy', $user) }}" method="POST">
                                            @csrf
                                            @method('delete')
                                            <button
                                                type="submit"
                                                class="btn btn-danger"
                                                title="Eliminar Usuario"
                                                onclick="return confirm('¿Está seguro que desea eliminar a este usuario?')"><i class="fas fa-trash fa-fw"></i>
                                            </button>
                                        </form>&#160;
                                    @endcan
                                    @can('crud.users.status')
                                        <a style="color:white!important;" href="{{ route('crud.users.show', $user) }}" class="btn btn-warning" title="Estatus del Cliente"><i class="fas fa-exclamation fa-fw"></i></a>&#160;
                                    @endcan
                                    <a style="color:white!important;" href="{{ route('arboles.tree.index', $user->passport) }}" title="Ver Arbol - Vista Horizontal" class="btn btn-success" ><i class="fab fa-pagelines fa-fw"></i></a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    {{ $users->links() }}
                    </div>
                    @else
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6 text-gray-500">
                        No hay resultado para la búsqueda {{ $search }} en la página {{ $page }} al mostrar {{ $perPage }} por página
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
