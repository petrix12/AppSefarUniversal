<div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
    <div class="flex bg-white px-4 py-3 sm:px-6">
        <!-- Filtros de Cupones -->
        <div class="mr-2">
            <select wire:change="setFiltro($event.target.value)" class="mr-2 mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                <option value="por_aprobar" {{ $filtro === 'por_aprobar' ? 'selected' : '' }}>Por Aprobar</option>
                <option value="aprobados" {{ $filtro === 'aprobados' ? 'selected' : '' }}>Aprobados</option>
                <option value="rechazados" {{ $filtro === 'rechazados' ? 'selected' : '' }}>Rechazados</option>
            </select>
        </div>

        <input
            wire:model="search"
            type="text"
            placeholder="Buscar por nombre, apellidos, correo o pasaporte..."
            class="mr-2 mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
        />
        <div class="col-span-6 sm:col-span-3">
            <select wire:model="perPage" class="py-2 px-2 mt-1 mr-10 block w-full border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                <option value="5">5 por pág.</option>
                <option value="10">10 por pág.</option>
                <option value="15">15 por pág.</option>
                <option value="25">25 por pág.</option>
                <option value="50">50 por pág.</option>
                <option value="100">100 por pág.</option>
            </select>
        </div>
        @if ($search !== '')
        <button wire:click="clear" class="py-1 px-2 mt-1 ml-2 border border-transparent rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            <i class="far fa-window-close"></i>
        </button>
        @endif
    </div>

    @if ($cupones->count())
    <div style="overflow-x: auto;">
        <table class="min-w-full divide-y divide-gray-200" style="max-width: 100%;">
            <thead class="bg-gray-50">
                <tr>
                    <th style="padding: 10px 15px;">Solicitante</th>
                    <th style="padding: 10px 15px;">Cliente</th>
                    <th style="padding: 10px 15px;">Motivo</th>
                    <th style="padding: 10px 15px;">Detalles del Cupón</th>
                    <th style="padding: 10px 15px;">Comprobante</th>
                    <th style="padding: 10px 15px;">Fecha Registro</th>
                    <th style="padding: 10px 15px;">Opciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
            @foreach ($cupones as $cupon)
            <tr>
                <!-- Columna del Solicitante -->
                <td class="whitespace-nowrap" style="padding: 5px 15px;">
                    <p class="mb-2"><b>{{ $cupon->nombre_solicitante }} {{ $cupon->apellidos_solicitante }}</b></p>
                    <p><small>{{ $cupon->correo_solicitante }}</small></p>
                </td>

                <!-- Columna del Cliente -->
                <td class="whitespace-nowrap" style="padding: 5px 15px;">
                    <p class="mb-2"><b>{{ $cupon->nombre_cliente }} {{ $cupon->apellidos_cliente }}</b></p>
                    <p class="mb-2"><small>{{ $cupon->correo_cliente }}</small></p>
                    <p><small>Pasaporte: {{ $cupon->pasaporte_cliente }}</small></p>
                </td>

                <td class="whitespace-nowrap" style="padding: 5px 15px;">
                    <p>{{ $cupon->motivo_solicitud }}</p>
                </td>

                <td class="whitespace-nowrap" style="padding: 5px 15px;">
                    <p class="mb-2"><b>Tipo:</b> {{ $cupon->tipo_cupon }}</p>
                    @if ($cupon->porcentaje_descuento)
                        <p><b>Descuento:</b> {{ $cupon->porcentaje_descuento }}%</p>
                    @else
                        <p><b>Descuento:</b> N/A</p>
                    @endif
                </td>

                <td class="whitespace-nowrap" style="padding: 5px 15px;">
                    @if(isset($cupon->comprobante_pago))
                    <a href="{{$cupon->comprobante_pago}}" style="text-align: center; align-content: center;" class="cfrSefar mr-2 mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md px-2 py-2">Ver Comprobante</a>
                    @endif
                </td>

                <td class="whitespace-nowrap" style="padding: 5px 15px;">
                    <p><small>{{ $cupon->created_at->format('Y-m-d') }}</small></p>
                </td>

                <!-- Columna de Fecha de Registro -->


                <!-- Columna de Opciones -->
                <td class="whitespace-nowrap" style="padding: 5px 15px;">
                    @if($cupon->estatus_cupon == 0)
                    <div style="display: flex; justify-content:center;">
                        <!-- Botón Aprobar -->
                        <button wire:click="approve({{ $cupon->id }})" class="bg-green-500 px-2 mr-2 mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md py-2">Aprobar</button>

                        <!-- Botón Rechazar -->
                        <button wire:click="reject({{ $cupon->id }})" class="bg-red-500 px-2 mr-2 mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md py-2">Rechazar</button>
                    </div>
                    @else
                    <div style="display: flex; justify-content:center;">
                        @if($cupon->aprobado == 1)
                        <!-- Botón Aprobar -->
                        <button class="bg-green-500 mr-2 mt-1 focus:ring-indigo-500 px-2 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md py-2">El cupón fue aprobado</button>
                        @else
                        <!-- Botón Rechazar -->
                        <button class="bg-red-500 mr-2 mt-1 focus:ring-indigo-500 px-2 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md py-2">El cupón fue rechazado</button>
                        @endif
                    </div>
                    @endif
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
        {{ $cupones->links() }}
    </div>
    @else
    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6 text-gray-500">
        No hay resultados para la búsqueda "{{ $search }}" en la página {{ $page }} al mostrar {{ $perPage }} por página.
    </div>
    @endif
</div>
