<div>
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                <div class="bg-gray-50">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                        <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                            <span class="ctvSefar block text-indigo-600">{{ __('Genealogical database') }}</span>
                        </h2>
                        @can('crud.agclientes.create')
                        <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                            <div class="inline-flex rounded-md shadow">
                                <a href="{{ route('crud.agclientes.create') }}" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                    {{ __('Add person') }}
                                </a>
                            </div>
                        </div>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
        <div class="px-4 py-2">
            {{-- SOLO CLIENTES --}}
            <div class="flex justify-between items-center">
                <div>
                    <label for="solo_clientes" class="px-3 block text-sm font-medium text-gray-700" title="Ver solo clientes">Ver</label>
                    <select wire:model="solo_clientes" name="solo_clientes"class="w-44 mt-1 block py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="{{ true }}">Solo clientes</option>
                        <option value="{{ false }}">Clientes y ancestros</option>
                    </select>
                </div>
                <div>
                    <label wire:click="forma_ordenar" for="ordenar" class="px-3 block text-sm font-medium text-gray-700" title="Haga clic aquí para invertir el orden">Ordenar por</label>
                    <select wire:model="ordenar" name="ordenar"class="w-44 mt-1 block py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="FRegistro">Fecha de registro</option>
                        <option value="IDCliente">ID Cliente</option>
                        <option value="Apellidos">Apellidos</option>
                        <option value="AnhoNac">Año de nacimiento</option>
                        <option value="AnhoDef">Año de defunción</option>
                        <option value="PNacimiento">País de nacimiento</option>
                        <option value="LNacimiento">Ciudad de nacimiento</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
                    <div class="flex bg-white px-4 py-3 sm:px-6">
                        <input
                            wire:keydown="limpiar_page"
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
                    @if ($agclientes->count())
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-2 py-2 text-left text-md text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-globe-americas"></i>
                                </th>
                                <th scope="col" class="px-1 py-2 text-left text-xs text-gray-500 uppercase tracking-wider">
                                    <span title="Fecha de registro">F. Registro</span>
                                </th>
                                <th scope="col" class="px-1 py-2 text-left text-xs text-gray-500 uppercase tracking-wider">
                                    {{ __('Person') }}
                                </th>
                                <th scope="col" class="px-1 py-2 text-left text-xs text-gray-500 uppercase tracking-wider">
                                    {{-- {{ __('Place of birth') }} --}}
                                    <span title="Lugar de nacimiento">L. Nac.</span>
                                </th>
                                <th scope="col" class="px-1 py-2 text-center text-xs text-gray-500 uppercase tracking-wider">
                                    {{-- {{ __('Year of birth') }} --}}
                                    <span title="Año de nacimiento">Año Nac.</span>
                                </th>
                                <th scope="col" class="px-1 py-2 text-center text-xs text-gray-500 uppercase tracking-wider">
                                    {{-- {{ __('Client id') }} --}}
                                    <span title="ID Cliente">IDC</span>
                                </th>
                                @if (!$solo_clientes)
                                <th scope="col" class="px-1 py-2 text-center text-xs text-gray-500 uppercase tracking-wider">
                                    {{ __('Propinquity') }}
                                </th>
                                @endif
                                <th scope="col" class="px-1 py-2 text-center text-xs text-gray-500 uppercase tracking-wider">
                                    <span title="Visualizar árbol genealógico">
                                        <i class="fas fa-eye"></i> Vistas
                                    </span>
                                </th>
                                @can('crud.agclientes.edit')
                                <th scope="col" class="py-2 text-center text-xs text-gray-500 uppercase tracking-wider">
                                    Acción
                                </th>
                                @endcan
                                <!--
                                @can('crud.users.status')
                                <th scope="col" class="py-2 text-center text-xs text-gray-500 uppercase tracking-wider">
                                    Estatus
                                </th>
                                @endcan
                                -->
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($agclientes as $agcliente)
                        <tr>
                            <td class="px-2 py-2">
                                @if(!is_null($agcliente->PaisNac) and (!empty($agcliente->PaisNac)))
                                    {{-- <img src="{{ config('app.url').'/storage/imagenes/paises/'.$agcliente->PaisNac .'.png' }}" alt="{{ $agcliente->PaisNac }}" width="33" height="25" > --}}
                                    <img
                                        src="{{ Storage::disk('s3')->url('imagenes/paises/'.$agcliente->PaisNac .'.png') }}"
                                        onerror="this.src='{{ Storage::disk('s3')->url('imagenes/paises/default.png') }}'"
                                        alt="'{{ $agcliente->PaisNac }}" width="33" height="25"
                                    >
                                @else
                                    <i class="fas fa-flag"></i>
                                @endif
                            </td>
                            <td class="px-1 py-2 text-xs">
                                @if ($agcliente->FRegistro)
                                    {{ $agcliente->FRegistro }}
                                @else
                                    <i class="far fa-calendar-times"></i>
                                @endif
                            </td>
                            <td class="px-1 py-2 text-xs">
                                <span title="{{ $agcliente->Nombres . ', ' . $agcliente->Apellidos }}">{{ Str::limit($agcliente->Nombres . ', ' . $agcliente->Apellidos, 30) }}</span>
                            </td>
                            <td class="px-1 py-2 whitespace-nowrap text-xs">
                                <small title="{{ $agcliente->LNacimiento }}">{{ Str::limit($agcliente->LNacimiento, 15) }}</small>
                            </td>
                            <td class="px-1 py-2 text-xs text-center">
                                @if ($agcliente->AnhoNac==0 or is_null($agcliente->AnhoNac))
                                    <i class="fas fa-question-circle"></i>
                                @else
                                    {{ $agcliente->AnhoNac }}
                                @endif
                            </td>
                            <td class="px-1 py-2 text-sm text-center">
                                <small>{{ $agcliente->IDCliente }}</small>
                            </td>
                            @if (!$solo_clientes)
                            <td class="px-1 py-2 text-xs text-center">
                                <small>{{ GetPersona($agcliente->IDPersona) }}</small>
                            </td>
                            @endif
                            <td class="px-1 py-2 text-sm text-center">
                                {{-- <a href="{{ route('arboles.olivo.index', $agcliente->IDCliente) }}" target="_blank" title="Vista Vertical"><i class="fas fa-cubes mx-1"></i></a> --}}
                                <a href="{{ route('arboles.tree.index', $agcliente->IDCliente) }}" target="_blank" title="Vista Horizontal"><i class="fab fa-pagelines mx-2"></i></a>
                                <a href="{{ route('arboles.albero.index', $agcliente->IDCliente) }}" target="_blank" title="Vista Lineal"><i class="fas fa-bezier-curve mx-1"></i></a>
                            </td>
                            @can('crud.agclientes.edit')
                            <td class="flex px-4 py-2 text-center">
                                @can('crud.agclientes.edit')
                                    <a href="{{ route('crud.agclientes.edit', $agcliente ) }}" class="mx-12 text-grey-600 hover:text-indigo-900" title="Editar"><i class="fas fa-edit"></i></a>
                                @endcan
                                @can('crud.agclientes.destroy')
                                <form action="{{ route('crud.agclientes.destroy', $agcliente) }}" method="POST">
                                    @csrf
                                    @method('delete')
                                    <button
                                        type="submit"
                                        class="text-red-600 hover:text-red-900"
                                        onclick="return confirm('¿Está seguro que desea eliminar este registro?')"><i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                @endcan
                            </td>
                            @endcan
                            <!--
                            @can('crud.users.status')
                            <td class="px-1 py-2 text-sm text-center">
                                @if($agcliente->NPasaporte == $agcliente->IDCliente)
                                <a href="{{ route('getuserstatus_ventas', $agcliente ) }}"  class="mx-12 text-grey-600 hover:text-indigo-900" title="Estatus"><i class="fas fa-exclamation"></i></a>
                                @endif
                            </td>
                            @endcan
                            -->
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <div class="container">
                            {{ $agclientes->links() }}
                        </div>
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
