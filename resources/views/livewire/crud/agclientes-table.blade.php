<div><div class="flex flex-col">
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
    </div>
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
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
                    @if ($agclientes->count())
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-2 py-2 text-left text-md text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-globe-americas"></i>
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
                            <th scope="col" class="px-1 py-2 text-center text-xs text-gray-500 uppercase tracking-wider">
                                {{ __('Propinquity') }}
                            </th>
                            <th scope="col" class="px-1 py-2 text-center text-xs text-gray-500 uppercase tracking-wider">
                                <span title="Visualizar árbol genealógico">
                                    <i class="fas fa-eye"></i> Vistas
                                </span>
                            </th>
                            @can('crud.agclientes.edit')
                            <th scope="col" class="py-2 text-center text-xs text-gray-500 uppercase tracking-wider">
                                {{ __('Edit') }}
                            </th>
                            @endcan
                            @can('crud.agclientes.destroy')
                            <th scope="col" class="px-4 py-2 text-center text-xs text-gray-500 uppercase tracking-wider">
                                {{ __('Remove') }}
                            </th>
                            @endcan
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($agclientes as $agcliente)
                        <tr>   
                            <td class="px-2 py-2">
                                @if(!is_null($agcliente->PaisNac) and (!empty($agcliente->PaisNac)))
                                    <img src="{{ config('app.url').'/storage/imagenes/paises/'.$agcliente->PaisNac .'.png' }}" alt="{{ $agcliente->PaisNac }}" width="33" height="25" >
                                @else
                                    <i class="fas fa-flag"></i>
                                @endif
                            </td>   
                            <td class="px-1 py-2 text-xs">
                                {{ $agcliente->Nombres . ', ' . $agcliente->Apellidos }}
                            </td>
                            <td class="px-1 py-2 whitespace-nowrap text-xs">
                                <small>{{ $agcliente->LNacimiento }}</small>
                            </td>
                            <td class="px-1 py-2 text-xs text-center">
                                @if ($agcliente->AnhoNac==0 or is_null($agcliente->AnhoNac))
                                    <i class="fas fa-question-circle"></i>
                                @else 
                                    {{ $agcliente->AnhoNac }}   
                                @endif
                            </td>
                            <td class="px-1 py-2 text-xs text-center">
                                <small>{{ $agcliente->IDCliente }}</small>
                            </td>
                            <td class="px-1 py-2 text-xs text-center">
                                <small>{{ GetPersona($agcliente->IDPersona) }}</small>
                            </td>
                            <td class="px-1 py-2 text-sm text-center">
                                <a href=""><i class="fas fa-cubes mx-1"></i></a>
                                <a href=""><i class="fab fa-pagelines mx-2"></i></a>
                                <a href="{{ route('arboles.albero.index', $agcliente->IDCliente) }}" target="_blank" title="Vista Arbelo"><i class="fas fa-bezier-curve mx-1"></i></a>
                            </td>
                            @can('crud.agclientes.edit')
                            <td class="py-2 text-center">
                                <a href="{{ route('crud.agclientes.edit', $agcliente ) }}" class="mx-12 text-grey-600 hover:text-indigo-900" title="Editar"><i class="fas fa-edit"></i></a>
                            </td>
                            @endcan
                            @can('crud.agclientes.destroy')
                            <td class="px-4 py-2 text-center">
                                <form action="{{ route('crud.agclientes.destroy', $agcliente) }}" method="POST">
                                    @csrf
                                    @method('delete')
                                    <button 
                                        type="submit" 
                                        class="text-red-600 hover:text-red-900" 
                                        onclick="return confirm('¿Está seguro que desea eliminar este registro?')"><i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                            @endcan
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