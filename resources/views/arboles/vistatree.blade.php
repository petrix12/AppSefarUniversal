@php
    $key = 0;
    $sizeheight = 1;
    $varcalc = 0;
    $columnWidth = 300; // ancho de cada columna
    $nodeHeight = 130; // altura de cada nodo
    $nodeSpacing = 10; // espacio entre nodos
@endphp

@foreach ($columnasparatabla as $key1 => $columna)

@php
    $tienePersonaConShowBtn2 = array_reduce($columna, function ($carry, $persona) {
        return $carry || $persona["showbtn"] === 2;
    }, false);

    // Calcular posición X de la columna
    $posX = ($key1 * $columnWidth) + 20;

    // Calcular altura total necesaria para esta columna
    $totalNodes = count($columna);
    $totalHeight = ($totalNodes * ($nodeHeight + $nodeSpacing));
@endphp

<div class="cliente" style="left: {{$posX}}px; top: 0; width: {{$columnWidth}}px; height: {{$totalHeight}}px;" data-column="{{$key1}}">
    @foreach ($columna as $key2 => $persona)
        @php
            // Calcular posición Y de cada nodo
            $posY = $key2 * ($nodeHeight + $nodeSpacing);
        @endphp

        @if ($persona["showbtn"]==2)
            <div class="contnodo" style="height: {{$nodeHeight}}px; position: relative; width: 100%;" data-node-y="{{$posY}}">
                <div class="cajapernew_min min_persona_id_{{ $persona['id'] }} min_padre_id_{{ $persona['idPadreNew'] ?? 'no' }} min_madre_id_{{ $persona['idMadreNew'] ?? 'no' }}"
                     id="min_{{ $persona['id'] }}_{{ $persona['idPadreNew'] ?? 'no' }}_{{ $persona['idMadreNew'] ?? 'no' }}"
                     data-person-id="{{ $persona['id'] }}"
                     data-column="{{$key1}}"
                     data-row="{{$key2}}">
                    <!-- Contenido del nodo igual que antes -->
                    <div class="encabezadonew_min">
                        {{$persona["Nombres"] . ' ' . $persona["Apellidos"]}}<br>
                        @if($checkBtn == "si")
                            @if ($key1+$generacionBase == 1)
                                @if ($key2 == 0)
                                    (Padre)
                                @else
                                    (Madre)
                                @endif
                            @else
                                ({{$parentescos[$key1-2+$generacionBase][$persona['PersonaIDNew']]}})
                            @endif
                        @else
                            @if ($key1 == 0)
                                (Cliente)
                            @elseif ($key1 == 1)
                                @if ($key2 == 0)
                                    (Padre)
                                @else
                                    (Madre)
                                @endif
                            @else
                                @if(isset($parentescos[$key1 - 2 + $generacionBase][$persona['PersonaIDNew']]))
                                    ({{ $parentescos[$key1 - 2 + $generacionBase][$persona['PersonaIDNew']] }})
                                @else
                                    <script>
                                        window.location.reload();
                                    </script>
                                @endif
                            @endif
                        @endif
                    </div>
                    <div id="datacopy_{{ $persona['id'] }}" style="display: none;">
                            @if (!empty($persona['Nombres']))
                            <p>
                                {{ $persona['Nombres'] }}{{!empty($persona['Apellidos']) ? " ".$persona['Apellidos'] : "" }}|
                            </p>
                        @endif
                        @if (!empty($persona['AnhoNac']))
                            <p>
                                <strong>n </strong>
                                @if (!empty($persona['LugarNac']))
                                    en {{ $persona['LugarNac'] }}
                                @endif
                                {{ !empty($persona['DiaNac']) ? $persona['DiaNac'] : '' }}{{ !empty($persona['DiaNac']) ? '/' : '' }}
                                {{ !empty($persona['MesNac']) ? $persona['MesNac'] : '' }}{{ !empty($persona['MesNac']) ? '/' : '' }}
                                {{ $persona['AnhoNac'] }}|
                            </p>
                        @endif

                        @if (!empty($persona['AnhoBtzo']))
                            <p>
                                <strong>b </strong>
                                @if (!empty($persona['LugarBtzo']))
                                    en {{ $persona['LugarBtzo'] }}
                                @endif
                                {{ !empty($persona['DiaBtzo']) ? $persona['DiaBtzo'] : '' }}{{ !empty($persona['DiaBtzo']) ? '/' : '' }}
                                {{ !empty($persona['MesBtzo']) ? $persona['MesBtzo'] : '' }}{{ !empty($persona['MesBtzo']) ? '/' : '' }}
                                {{ $persona['AnhoBtzo'] }}|
                            </p>
                        @endif

                        @if (!empty($persona['AnhoMatr']))
                            <p>
                                <strong>m </strong>
                                @if (!empty($persona['LugarMatr']))
                                    en {{ $persona['LugarMatr'] }}
                                @endif
                                {{ !empty($persona['DiaMatr']) ? $persona['DiaMatr'] : '' }}{{ !empty($persona['DiaMatr']) ? '/' : '' }}
                                {{ !empty($persona['MesMatr']) ? $persona['MesMatr'] : '' }}{{ !empty($persona['MesMatr']) ? '/' : '' }}
                                {{ $persona['AnhoMatr'] }}|
                            </p>
                        @endif

                        @if (!empty($persona['AnhoDef']))
                            <p>
                                <strong>f </strong>
                                @if (!empty($persona['LugarDef']))
                                    en {{ $persona['LugarDef'] }}
                                @endif
                                {{ !empty($persona['DiaDef']) ? $persona['DiaDef'] : '' }}{{ !empty($persona['DiaDef']) ? '/' : '' }}
                                {{ !empty($persona['MesDef']) ? $persona['MesDef'] : '' }}{{ !empty($persona['MesDef']) ? '/' : '' }}
                                {{ $persona['AnhoDef'] }}|
                            </p>
                        @endif

                        @if (!empty($persona['LugarDef']))
                            <p><strong>Lugar de Defunción:</strong> {{ $persona['LugarDef'] }}|</p>
                        @endif


                    </div>
                    <div class="continfo">
                        @if (!empty($persona['AnhoNac']))
                            <p>
                                <strong>○ </strong>
                                {{ !empty($persona['DiaNac']) ? $persona['DiaNac'] : '' }}{{ !empty($persona['DiaNac']) ? '/' : '' }}
                                {{ !empty($persona['MesNac']) ? $persona['MesNac'] : '' }}{{ !empty($persona['MesNac']) ? '/' : '' }}
                                {{ $persona['AnhoNac'] }} {{!empty($persona['LugarNac']) ? '(' . $persona['LugarNac'] . ')' : '' }}
                            </p>
                        @endif

                        @if (!empty($persona['AnhoDef']))
                            <p>
                                <strong>✟ </strong>
                                {{ !empty($persona['DiaDef']) ? $persona['DiaDef'] : '' }}{{ !empty($persona['DiaDef']) ? '/' : '' }}
                                {{ !empty($persona['MesDef']) ? $persona['MesDef'] : '' }}{{ !empty($persona['MesDef']) ? '/' : '' }}
                                {{ $persona['AnhoDef'] }} {{!empty($persona['LugarDef']) ? '(' . $persona['LugarDef'] . ')' : '' }}
                            </p>
                        @endif

                        <div style="width: 100%; height:0.5rem; border-bottom: #093143 1px solid ; margin-bottom:0.5rem;"></div>

                        @if(auth()->user() && auth()->user()->hasRole(['Administrador', 'Genealogista', 'Documentalista']))
                        <button class="editperson" onclick="callEdit('{{!isset($persona['Nombres']) ? '' : $persona['Nombres']}}','{{!isset($persona['Apellidos']) ? '' : $persona['Apellidos']}}','{{!isset($persona['AnhoNac']) ? '' : $persona['AnhoNac']}}','{{!isset($persona['MesNac']) ? '' : $persona['MesNac']}}','{{!isset($persona['DiaNac']) ? '' : $persona['DiaNac']}}','{{!isset($persona['LugarNac']) ? '' : $persona['LugarNac']}}','{{!isset($persona['PaisNac']) ? '' : $persona['PaisNac']}}','{{!isset($persona['AnhoBtzo']) ? '' : $persona['AnhoBtzo']}}','{{!isset($persona['MesBtzo']) ? '' : $persona['MesBtzo']}}','{{!isset($persona['DiaBtzo']) ? '' : $persona['DiaBtzo']}}','{{!isset($persona['LugarBtzo']) ? '' : $persona['LugarBtzo']}}','{{!isset($persona['PaisBtzo']) ? '' : $persona['PaisBtzo']}}','{{!isset($persona['AnhoMatr']) ? '' : $persona['AnhoMatr']}}','{{!isset($persona['MesMatr']) ? '' : $persona['MesMatr']}}','{{!isset($persona['DiaMatr']) ? '' : $persona['DiaMatr']}}','{{!isset($persona['LugarMatr']) ? '' : $persona['LugarMatr']}}','{{!isset($persona['PaisMatr']) ? '' : $persona['PaisMatr']}}','{{!isset($persona['AnhoDef']) ? '' : $persona['AnhoDef']}}','{{!isset($persona['MesDef']) ? '' : $persona['MesDef']}}','{{!isset($persona['DiaDef']) ? '' : $persona['DiaDef']}}','{{!isset($persona['LugarDef']) ? '' : $persona['LugarDef']}}','{{!isset($persona['PaisDef']) ? '' : $persona['PaisDef']}}','{{!isset($persona['Observaciones']) ? '' : json_encode($persona['Observaciones'])}}','{{$persona['id']}}','{{!isset($persona['NPasaporte']) ? '' : $persona['NPasaporte']}}','{{!isset($persona['PaisPasaporte']) ? '' : $persona['PaisPasaporte']}}','{{!isset($persona['NDocIdent']) ? '' : $persona['NDocIdent']}}','{{!isset($persona['PaisDocIdent']) ? '' : $persona['PaisDocIdent']}}')">Editar</button>
                        <button class="editperson" onclick="callFiles('{{$persona["IDCliente"]}}', '{{$persona["id"]}}')">Archivos</button>

                        <button class="copydata" onclick="copydata('datacopy_{{ $persona['id'] }}')">Copiar</button>
                        <button class="copydata" onclick="window.location.href='/tree/{{$persona["IDCliente"]}}/{{$persona["id"]}}/{{$key1+$generacionBase}}/{{$key2}}'">Extender</button>
                        @elseif(auth()->user() && auth()->user()->hasRole(['Cliente']))
                            <button class="editperson" onclick="callEdit('{{!isset($persona['Nombres']) ? '' : $persona['Nombres']}}','{{!isset($persona['Apellidos']) ? '' : $persona['Apellidos']}}','{{!isset($persona['AnhoNac']) ? '' : $persona['AnhoNac']}}','{{!isset($persona['MesNac']) ? '' : $persona['MesNac']}}','{{!isset($persona['DiaNac']) ? '' : $persona['DiaNac']}}','{{!isset($persona['LugarNac']) ? '' : $persona['LugarNac']}}','{{!isset($persona['PaisNac']) ? '' : $persona['PaisNac']}}','{{!isset($persona['AnhoBtzo']) ? '' : $persona['AnhoBtzo']}}','{{!isset($persona['MesBtzo']) ? '' : $persona['MesBtzo']}}','{{!isset($persona['DiaBtzo']) ? '' : $persona['DiaBtzo']}}','{{!isset($persona['LugarBtzo']) ? '' : $persona['LugarBtzo']}}','{{!isset($persona['PaisBtzo']) ? '' : $persona['PaisBtzo']}}','{{!isset($persona['AnhoMatr']) ? '' : $persona['AnhoMatr']}}','{{!isset($persona['MesMatr']) ? '' : $persona['MesMatr']}}','{{!isset($persona['DiaMatr']) ? '' : $persona['DiaMatr']}}','{{!isset($persona['LugarMatr']) ? '' : $persona['LugarMatr']}}','{{!isset($persona['PaisMatr']) ? '' : $persona['PaisMatr']}}','{{!isset($persona['AnhoDef']) ? '' : $persona['AnhoDef']}}','{{!isset($persona['MesDef']) ? '' : $persona['MesDef']}}','{{!isset($persona['DiaDef']) ? '' : $persona['DiaDef']}}','{{!isset($persona['LugarDef']) ? '' : $persona['LugarDef']}}','{{!isset($persona['PaisDef']) ? '' : $persona['PaisDef']}}','{{!isset($persona['Observaciones']) ? '' : json_encode($persona['Observaciones'])}}','{{$persona['id']}}','{{!isset($persona['NPasaporte']) ? '' : $persona['NPasaporte']}}','{{!isset($persona['PaisPasaporte']) ? '' : $persona['PaisPasaporte']}}','{{!isset($persona['NDocIdent']) ? '' : $persona['NDocIdent']}}','{{!isset($persona['PaisDocIdent']) ? '' : $persona['PaisDocIdent']}}')">Editar</button>
                            <button class="editperson" onclick="callFiles('{{$persona["IDCliente"]}}', '{{$persona["id"]}}')">Archivos</button>
                        @endif
                    </div>
                </div>
            </div>
        @elseif ($persona["showbtn"]==1)
            <div class="cajabtn_min" style="min-height: {{$nodeHeight}}px!important; position: relative; width: 100%; display: flex; align-items: center;" data-node-y="{{$posY}}">
                <button id="{{ $persona["showbtnsex"] == "m" ? "M" : "F" }}_{{$persona["id_hijo"]}}_{{$columnasparatabla[0][0]["IDCliente"]}}"
                        class="addbtn {{ $persona["showbtnsex"] == "m" ? "M" : "F" }}_{{$persona["id_hijo"]}}"
                        data-column="{{$key1}}"
                        data-row="{{$key2}}">+</button>
                <span class="addbtntext">Agregar {{ $persona["showbtnsex"] == "m" ? "Padre" : "Madre" }}</span>
            </div>
        @else
            <div class="cajaperemptynew_min" style="min-height: {{$nodeHeight}}px!important; position: relative; width: 100%;" data-node-y="{{$posY}}">
            </div>
        @endif
    @endforeach
</div>

@php $key++; $sizeheight = $sizeheight * 2; @endphp
@endforeach
