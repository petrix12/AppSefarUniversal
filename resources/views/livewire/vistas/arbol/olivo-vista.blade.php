<div>
    <div class="p-2 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="lg:text-center">
            <h2 class="text-sm font-bold ctaSefar cfrSefar tracking-wide pt-2 rounded-lg opacity-75 flex h-8 justify-center items-center">
                Cliente: {{ $agclientes[0]->Nombres.', '.$agclientes[0]->Apellidos.' / '.$agclientes[0]->IDCliente}}
            </h2>
            <p class="mt-2 text-lg leading-8 font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                ÁRBOL GENEALÓGICO (VISTA VERTICAL) 
            </p>
        </div>
    </div>
    <div class="container overflow-x-scroll">
        <div class="flex justify-between">
            <div class="px-4 py-2 m-2">
                {{-- FAMILIARES --}}
                <div class="justify-center">
                    <label for="Familiares" class="px-3 block text-sm font-medium text-gray-700" title="Familiares en el proceso">Familiares</label>
                    <select wire:model.live="IDFamiliar" style="width:450px" name="Familiares" class="w-44 mt-1 block py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="{{ null }}">-</option>
                        @foreach ($families as $family)
                            <option value="{{ $family->IDFamiliar }}">{{ $family->Familiar.' - '.$family->Parentesco }}</option>
                        @endforeach
                    </select>
                    @if($IDFamiliar)
                    <div class="pt-2">
                        <div class="px-4 py-3 bg-gray-50 text-left sm:px-6">
                            <a href="{{ route('arboles.tree.index', $IDFamiliar) }}" target="_blank" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Ir a familiar seleccionado
                            </a>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    
    <div style="height:40rem width:400rem" class="container relative overflow-scroll">
        <div id="organigrama" name="organigrama" class="organigrama">
            <ul id="ul1" name="ul1">
                <li>
                    {{-- CLIENTE --}}
                    <div>{!! GetBoxPerson($agclientes, 1) !!}</div>
                    <ul>
                        <li>
                            {{-- PADRE --}}
                            <div>{!! GetBoxPerson($agclientes, 2) !!}</div>
                            <ul>
                                <li>
                                    {{-- ABUELO P --}}
                                    <div>{!! GetBoxPerson($agclientes, 4) !!}</div>
                                    <ul>
                                        <li>
                                            {{-- BISABUELO PP --}}
                                            <div>{!! GetBoxPerson($agclientes, 8) !!}</div>
                                            <ul>
                                                <li>
                                                    {{-- TATARABUELO PPP --}}
                                                    <div>{!! GetBoxPerson($agclientes, 16) !!}</div>
                                                </li>
                                                <li>
                                                    {{-- TATRABUELA PPP --}}
                                                    <div>{!! GetBoxPerson($agclientes, 17) !!}</div>
                                                </li>
                                            </ul>
                                        </li>
                                        <li>
                                            {{-- BISABUELA PP --}}
                                            <div>{!! GetBoxPerson($agclientes, 9) !!}</div>
                                            <ul>
                                                <li>
                                                    {{-- TATARABUELO PPM --}}
                                                    <div>{!! GetBoxPerson($agclientes, 18) !!}</div>
                                                </li>
                                                <li>
                                                    {{-- TATRABUELA PPM --}}
                                                    <div>{!! GetBoxPerson($agclientes, 19) !!}</div>
                                                </li>
                                            </ul>
                                        </li>
                                    </ul>
                                </li>
                                <li>
                                    {{-- ABUELA P --}}
                                    <div>{!! GetBoxPerson($agclientes, 5) !!}</div>
                                    <ul>
                                        <li>
                                            {{-- BISABUELO PM --}}
                                            <div>{!! GetBoxPerson($agclientes, 10) !!}</div>
                                            <ul>
                                                <li>
                                                    {{-- TATARABUELO PMP --}}
                                                    <div>{!! GetBoxPerson($agclientes, 20) !!}</div>
                                                </li>
                                                <li>
                                                    {{-- TATRABUELA PMP --}}
                                                    <div>{!! GetBoxPerson($agclientes, 21) !!}</div>
                                                </li>
                                            </ul>        
                                        </li>
                                        <li>
                                            {{-- BISABUELA PM --}}
                                            <div>{!! GetBoxPerson($agclientes, 11) !!}</div>
                                            <ul>
                                                <li>
                                                    {{-- TATARABUELO PMM --}}
                                                    <div>{!! GetBoxPerson($agclientes, 22) !!}</div>
                                                </li>
                                                <li>
                                                    {{-- TATRABUELA PMM --}}
                                                    <div>{!! GetBoxPerson($agclientes, 23) !!}</div>
                                                </li>
                                            </ul>        
                                        </li>
                                    </ul>   
                                </li>
                            </ul>
                        </li>
                        <li>
                            {{-- MADRE --}}
                            <div>{!! GetBoxPerson($agclientes, 3) !!}</div>
                            <ul>
                                <li>
                                    {{-- ABUELO M --}}
                                    <div>{!! GetBoxPerson($agclientes, 6) !!}</div>
                                    <ul>
                                        <li>
                                            {{-- BISABUELO MP --}}
                                            <div>{!! GetBoxPerson($agclientes, 12) !!}</div>
                                            <ul>
                                                <li>
                                                    {{-- TATARABUELO MPP --}}
                                                    <div>{!! GetBoxPerson($agclientes, 24) !!}</div>
                                                </li>
                                                <li>
                                                    {{-- TATRABUELA MPP --}}
                                                    <div>{!! GetBoxPerson($agclientes, 25) !!}</div>
                                                </li>
                                            </ul>        
                                        </li>
                                        <li>
                                            {{-- BISABUELA MP --}}
                                            <div>{!! GetBoxPerson($agclientes, 13) !!}</div>
                                            <ul>
                                                <li>
                                                    {{-- TATARABUELO MPM --}}
                                                    <div>{!! GetBoxPerson($agclientes, 26) !!}</div>
                                                </li>
                                                <li>
                                                    {{-- TATRABUELA MPM --}}
                                                    <div>{!! GetBoxPerson($agclientes, 27) !!}</div>
                                                </li>
                                            </ul>
                                        </li>
                                    </ul>   
                                </li>
                                <li>
                                    {{-- ABUELA M --}}
                                    <div>{!! GetBoxPerson($agclientes, 7) !!}</div>
                                    <ul>
                                        <li>
                                            {{-- BISABUELO MM --}}
                                            <div>{!! GetBoxPerson($agclientes, 14) !!}</div>
                                            <ul>
                                                <li>
                                                    {{-- TATARABUELO MMP --}}
                                                    <div>{!! GetBoxPerson($agclientes, 28) !!}</div>
                                                </li>
                                                <li>
                                                    {{-- TATRABUELA MMP --}}
                                                    <div>{!! GetBoxPerson($agclientes, 29) !!}</div>
                                                </li>
                                            </ul>
                                        </li>
                                        <li>
                                            {{-- BISABUELA MM --}}
                                            <div>{!! GetBoxPerson($agclientes, 15) !!}</div>
                                            <ul>
                                                <li>
                                                    {{-- TATARABUELO MMM --}}
                                                    <div>{!! GetBoxPerson($agclientes, 30) !!}</div>
                                                </li>
                                                <li>
                                                    {{-- TATRABUELA MMM --}}
                                                    <div>{!! GetBoxPerson($agclientes, 31) !!}</div>
                                                </li>
                                            </ul>
                                        </li>
                                    </ul>   
                                </li>
                            </ul>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</div>