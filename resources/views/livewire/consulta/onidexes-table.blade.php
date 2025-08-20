<div>
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
                    <div class="flex bg-white px-4 py-3 sm:px-6">
                        <input
                            wire:model.live="search"
                            type="text"
                            placeholder="Buscar..."
                            class="mr-2 mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                        >
                        <div class="col-span-6 sm:col-span-3">
                            <select wire:model.live="perPage" class="py-2 px-2 mt-1 mr-10 block w-full border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="5">5 por pág. </option>
                                <option value="10">10 por pág.</option>
                                <option value="15">15 por pág.</option>
                                <option value="25">25 por pág.</option>
                                <option value="50">50 por pág.</option>
                                <option value="100">100 por pág.</option>
                            </select>
                        </div>
                        @if ($search !== '')
                        <button wire:click="clear" class="py-1 px-2 mt-1 ml-2 border border-transparent rounded-md border border-transparent rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"><i class="far fa-window-close"></i></button>
                        @endif
                    </div>
                    @if ($onidexes->count())
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-2 text-left text-xs font-bold text-gray-900 uppercase tracking-wider">
                                Cédula
                            </th>
                            <th scope="col" class="px-6 py-2 text-left text-xs font-bold text-gray-900 uppercase tracking-wider">
                                1er Apallido
                            </th>
                            <th scope="col" class="px-6 py-2 text-left text-xs font-bold text-gray-900 uppercase tracking-wider">
                                2do Apallido
                            </th>
                            <th scope="col" class="px-6 py-2 text-left text-xs font-bold text-gray-900 uppercase tracking-wider">
                                1er Nombre
                            </th>
                            <th scope="col" class="px-6 py-2 text-left text-xs font-bold text-gray-900 uppercase tracking-wider">
                                2do Nombre
                            </th>
                            <th scope="col" class="px-6 py-2 text-center text-xs font-bold text-gray-900 uppercase tracking-wider">
                                Nación
                            </th>
                            <th scope="col" class="px-6 py-2 text-right text-xs font-bold text-gray-900 uppercase tracking-wider">
                                Fecha de nacimiento
                            </th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($onidexes as $onidex)
                        <tr>
                            <td class="px-6 py-2 text-left text-xs whitespace-nowrap">
                                {{ $onidex->cedula }}
                            </td>
                            <td class="px-6 py-2 text-left text-xs whitespace-nowrap">
                                {{ $onidex->apellido1 }}
                            </td>
                            <td class="px-6 py-2 text-left text-xs whitespace-nowrap">
                                {{ $onidex->apellido2 }}
                            </td>
                            <td class="px-6 py-2 text-left text-xs whitespace-nowrap">
                                {{ $onidex->nombre1 }}
                            </td>
                            <td class="px-6 py-2 text-left text-xs whitespace-nowrap">
                                {{ $onidex->nombre2 }}
                            </td>
                            <td class="px-6 py-2 text-center text-xs whitespace-nowrap">
                                {{ $onidex->nacion }}
                            </td>
                            <td class="px-6 py-2 text-right text-xs whitespace-nowrap">
                                {{ date("d/m/Y", strtotime($onidex->fec_nac)) }}
                            </td>
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <div class="text-xs bg-white px-4 py-3 border-t border-gray-200 sm:px-6 text-gray-100">
                    {{ $onidexes->links() }}
                    </div>
                    @else
<div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6 text-gray-500">
    No hay resultado para la búsqueda "{{ $search }}" en la página {{ $currentPage }} al mostrar {{ $perPage }} por página
</div>
@endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
    <form action="{{ route('consultas.onidex.index')}}">
        @csrf
        <button type="submit" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Ir a búsqueda avanzada
        </button>
    </form>
</div>
