<div>
    <!-- This example requires Tailwind CSS v2.0+ -->
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                {{-- Inicio --}}
                <div class="bg-gray-50">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                        <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                            <span class="ctvSefar block text-indigo-600">{{ __('Countries') }}</span>
                        </h2>
                        @can('crud.countries.create')
                        <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                            <div class="inline-flex rounded-md shadow">
                                <a href="{{ route('crud.countries.create') }}" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                    {{ __('Add country') }}
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
                <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
                    <div class="flex bg-white px-4 py-3 sm:px-6">
                        <input
                            wire:model="search"
                            type="text"
                            placeholder="Buscar..."
                            class="mr-2 mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                            {{-- class="form-input rounded-md shadow-sm mt-1 block w-full" --}}
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
                    @if ($countries->count())
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-9 py-2 text-left text-md font-medium text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-globe-americas"></i>
                            </th>
                            <th scope="col" class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ID
                            </th>
                            <th scope="col" class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Country') }}
                            </th>
                            <th scope="col" class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Storage') }}
                            </th>
                            @can('crud.roles.edit')
                            <th scope="col" class="px-9 y-2 text-xs font-medium text-gray-500 uppercase tracking-wider text-right">
                                {{ __('Edit') }}
                            </th>
                            @endcan
                            @can('crud.roles.destroy')
                            <th scope="col" class="px-3 py-2 text-xs font-medium text-gray-500 uppercase tracking-wider text-right">
                                {{ __('Remove') }}
                            </th>
                            @endcan
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($countries as $country)
                        <tr>
                            <td class="px-6 py-2 whitespace-nowrap">
                                {{-- <img src="{{ config('app.url').'/storage/'.$country->store }}" alt="{{ $country->id }}" width="33" height="25" > --}}
                                <img src="{{ Storage::disk('s3')->url($country->store) }}" alt="{{ $country->id }}" width="33" height="25" >
                            </td>
                            <td class="px-6 py-2 whitespace-nowrap">
                                {{ $country->id }}
                                {{-- {{ config('app.url').'/storage/'.$country->store }} --}}
                            </td>
                            <td class="px-6 py-2 whitespace-nowrap">
                                {{ $country->pais }}
                            </td>
                            <td class="px-6 py-2 whitespace-nowrap">
                                {{ $country->store }}
                            </td>
                            @can('crud.countries.edit')
                            <td class="py-2 whitespace-nowrap text-right font-medium">
                                <a href="{{ route('crud.countries.edit', $country) }}" class="mx-12 text-grey-600 hover:text-indigo-900" title="Editar"><i class="fas fa-edit"></i></a>
                            </td>
                            @endcan
                            @can('crud.countries.destroy')
                            <td class="px-3 py-2 whitespace-nowrap text-right font-medium">
                                <form action="{{ route('crud.countries.destroy', $country) }}" method="POST">
                                    @csrf
                                    @method('delete')
                                    <button
                                        type="submit"
                                        class="text-red-600 hover:text-red-900"
                                        onclick="return confirm('¿Está seguro que desea eliminar el país?')"><i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                            @endcan
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    {{ $countries->links() }}
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
