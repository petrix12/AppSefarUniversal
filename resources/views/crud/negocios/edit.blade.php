@extends('adminlte::page')

@section('title', 'Negocio')

@section('content_header')
    {{-- <h1><strong>{{ __('Permisos de usuarios') }}</strong></h1> --}}
@stop

@section('content')
<x-app-layout>
    <div>
        <div class="flex flex-col">
            <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                    {{-- Inicio --}}
                    <div class="bg-gray-50">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                            <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                                <span class="ctvSefar block text-indigo-600">Editar Negocio</span>
                            </h2>
                            <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                                <div class="inline-flex rounded-md shadow">
                                    <a href="/users/{{$deal_db->user_id}}/edit" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                        Volver a información del Usuario
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Fin --}}
                </div>
            </div>
        </div>

        <div class="card p-4">
            <ul class="nav nav-tabs" id="formTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="deal-data-tab" data-bs-toggle="tab" data-bs-target="#deal_data" type="button" role="tab" aria-controls="personal_data" aria-selected="true">
                        Datos principales del Negocio
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="deal-payments-tab" data-bs-toggle="tab" data-bs-target="#deal_payments" type="button" role="tab" aria-controls="personal_data" aria-selected="true">
                        Generar Pagos
                    </button>
                </li>
            </ul>
            <div class="tab-content mt-4" id="formTabsContent">
                <div class="tab-pane fade show active" id="deal_data" role="tabpanel" aria-labelledby="deal-data-tab">
                    <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                        <span class="ctvSefar block text-indigo-600">DATOS GENERALES:</span>
                    </h2>

                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="dealname" class="block text-sm font-medium text-gray-700">Nombre del Negocio</label>
                            <input type="text" id="dealname" name="dealname"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('dealname', $deal_db->dealname) }}">
                        </div>
                        <div style="flex: 1;" class="mb-3">
                            <label for="teamleader_id" class="block text-sm font-medium text-gray-700">Proyecto (Teamleader)</label>
                            <select class="select2 mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="teamleader_id" name="teamleader_id">
                                <option value="" {{ old('teamleader_id', $deal_db->teamleader_id) == '' ? 'selected' : '' }}>Seleccione un proyecto</option>
                                @foreach ($TLdeals as $tldeal)
                                    <option value='{{$tldeal["id"]}}' {{ old('teamleader_id', $deal_db->teamleader_id) == $tldeal["id"] ? 'selected' : '' }}>{{$tldeal["title"]}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div style="flex: 1;" class="mb-3">
                            <label for="servicio_solicitado2" class="block text-sm font-medium text-gray-700">Servicio Solicitado</label>
                            <input list="listadatos" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="servicio_solicitado2" name="servicio_solicitado2" value="{{ old('servicio_solicitado2', $deal_db->servicio_solicitado2) }}">
                            <datalist id="listadatos">
                                <option value="Subsanación de Expediente">
                                <option value="Nacionalidad Portuguesa por origen Sefardí">
                                <option value="Nacionalidad Española por origen Sefardí">
                                <option value="Nacionalidad por Carta de Naturaleza">
                                <option value="Nacionalidad Italiana">
                                <option value="Nacionalidad Portuguesa por Cónyuge">
                                <option value="Nacionalidad Portuguesa por ser nieto de Portugués">
                                <option value="Nacionalidad por otras vías">
                                <option value="Residencia no lucrativa">
                                <option value="Residencia Temporal por Arraigo Familiar">
                                <option value="Estancia por Estudios">
                                <option value="Residencia por Reagrupación Familiar">
                                <option value="Residencia para familiares de ciudadanos de la Unión Europea">
                                <option value="Residencia para familiares de la Unión Europea">
                                <option value="Paso de Estancia por Estudios a otro tipo de permisos">
                                <option value="Autorizaciones relacionadas con el autoempleo (autónomos)">
                                <option value="Otros servicios (No Serfardíes)">
                                <option value="Golden Visas (España)">
                                <option value="Ley de extranjería">
                                <option value="Nacionalidad Española por Residencia">
                                <option value="Visas Americanas">
                                <option value="Visas Colombianas">
                                <option value="Visas Canadienses">
                                <option value="Ley de Extranjería Portugal">
                                <option value="Golden Visas Portugal">
                                <option value="Memorándum administrativos">
                                <option value="Inscripción de nacimiento fuera de plazo">
                                <option value="Acompañamiento notarial para la juramentación de la nacionalidad española">
                                <option value="Traducción al Portugués">
                                <option value="Traducción al Italiano">
                                <option value="Alquileres">
                                <option value="Extranjería y Nacionalidad">
                                <option value="Hipotecas e Impuestos">
                                <option value="Inversión de Inmuebles (Real State)">
                                <option value="Oportunidades de Negocio">
                                <option value="Cartas de deseo">
                                <option value="Otros">
                                <option value="Mercantiles">
                                <option value="Parafiscales">
                                <option value="Registros Inmobiliarios">
                                <option value="De Notaria">
                                <option value="Tribunales Civiles">
                                <option value="Protección al Menor">
                                <option value="Tránsito Terrestre">
                                <option value="Visas/Residencias">
                                <option value="Documentos en Colombia">
                                <option value="Otros servicios">
                                <option value="Actas de estado civil (naci, matr - divorcio y defunción)">
                                <option value="Certificados de registro civil">
                                <option value="Apostillado de documentos deben añadirse 80 euros)">
                                <option value="Rectificación de actas de registro civil">
                                <option value="Permisos de residencia electiva (Golden visa)">
                                <option value="Permisos de residencias no lucrativas">
                                <option value="Declaración de sucesiones">
                                <option value="Asesoría tasación internacional">
                                <option value="Constitución de sociedades">
                                <option value="Otros tramites">
                                <option value="TPS Venezolanos en USA">
                                <option value="ASISTENCIA DOCUMENTAL">
                                <option value="SOLICITUD DE HOMOLOGACIÓN Y/O EQUIVALENCIA DE TÍTULOS ACADÉMICOS EN ESPAÑA">
                                <option value="Certificado de nacimiento">
                                <option value="Certificado de matrimonio">
                                <option value="Certificado de defunción">
                                <option value="Registro mercantil (Acta constitutiva)">
                                <option value="SOLICITUD DE DOCUMENTO DE RESOLUCIÓN EXPRESA">
                                <option value="Ley de Memoria Democrática">
                                <option value="Permanencia Temporal para Venezolanos">
                                <option value="Española LMD">
                                <option value="Española Sefardi">
                                <option value="Portuguesa Sefardi">
                                <option value="Italiana">
                                <option value="Portuguesa Sefardi – Subsanación">
                                <option value="Española Sefardi – Subsanación">
                                <option value="Española - Carta de Naturaleza">
                                <option value="Ley de Extranjería Italia">
                                <option value="VISA ESTADOUNIDENSE DE TURISMO">
                                <option value="VISA ESTADOUNIDENSE PARA ESTUDIANTE">
                                <option value="Cooperativa 5 años">
                                <option value="Recurso de Alzada">
                                <option value="Cooperativa 10 años">
                                <option value="Árbol genealógico de Deslinde">
                            </datalist>
                            <input type="hidden" id="servicio_solicitado" name="servicio_solicitado" value="{{ old('servicio_solicitado', $deal_db->servicio_solicitado) }}" />
                        </div>
                    </div>

                    <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                        <span class="ctvSefar block text-indigo-600">CERTIFICADOS GENEALOGICOS</span>
                    </h2>

                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="n2__enviado_a_redaccion_informe" class="block text-sm font-medium text-gray-700">Enviado a Redacción Informe</label>
                            <input type="date" id="n2__enviado_a_redaccion_informe" name="n2__enviado_a_redaccion_informe"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n2__enviado_a_redaccion_informe', $deal_db->n2__enviado_a_redaccion_informe) }}">
                        </div>
                        <div style="flex: 1;" class="mb-3">
                            <label for="n3__informe_cargado" class="block text-sm font-medium text-gray-700">Informe Cargado</label>
                            <input type="date" id="n3__informe_cargado" name="n3__informe_cargado"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n3__informe_cargado', $deal_db->n3__informe_cargado) }}">
                        </div>
                    </div>
                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="n4__certificado_descargado" class="block text-sm font-medium text-gray-700">Certificado Descargado</label>
                            <input type="date" id="n4__certificado_descargado" name="n4__certificado_descargado"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n4__certificado_descargado', $deal_db->n4__certificado_descargado) }}">
                        </div>
                        <div style="flex: 1;" class="mb-3">
                            <label for="n6__cil_preaprobado" class="block text-sm font-medium text-gray-700">CIL Preaprobado</label>
                            <input type="date" id="n6__cil_preaprobado" name="n6__cil_preaprobado"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n6__cil_preaprobado', $deal_db->n6__cil_preaprobado) }}">
                        </div>
                    </div>
                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="n7__enviado_al_dto_juridico" class="block text-sm font-medium text-gray-700">Enviado al Dto Jurídico</label>
                            <input type="date" id="n7__enviado_al_dto_juridico" name="n7__enviado_al_dto_juridico"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n7__enviado_al_dto_juridico', $deal_db->n7__enviado_al_dto_juridico) }}">
                        </div>
                        <div style="flex: 1 1 100%;" class="mb-3">
                            <label for="n5__notas_genealogia" class="block text-sm font-medium text-gray-700">Notas de Genealogía</label>
                            <textarea id="n5__notas_genealogia" name="n5__notas_genealogia" rows="4"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">{{ old('n5__notas_genealogia', $deal_db->n5__notas_genealogia) }}</textarea>
                        </div>
                    </div>

                    <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                        <span class="ctvSefar block text-indigo-600">DOCUMENTOS</span>
                    </h2>

                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="n3__f__vencimiento_ant__penal" class="block text-sm font-medium text-gray-700">Vencimiento de Antecedentes Penales</label>
                            <input type="date" id="n3__f__vencimiento_ant__penal" name="n3__f__vencimiento_ant__penal"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n3__f__vencimiento_ant__penal', $deal_db->n3__f__vencimiento_ant__penal) }}">
                        </div>
                        <div style="flex: 1;" class="mb-3">
                            <label for="n5___f_solicitud_documentos" class="block text-sm font-medium text-gray-700">Fecha de Solicitud de Documentos</label>
                            <input type="date" id="n5___f_solicitud_documentos" name="n5___f_solicitud_documentos"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n5___f_solicitud_documentos', $deal_db->n5___f_solicitud_documentos) }}">
                        </div>
                    </div>
                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="n7__fecha_caducidad_pasaporte" class="block text-sm font-medium text-gray-700">Fecha de Caducidad del Pasaporte</label>
                            <input type="date" id="n7__fecha_caducidad_pasaporte" name="n7__fecha_caducidad_pasaporte"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n7__fecha_caducidad_pasaporte', $deal_db->n7__fecha_caducidad_pasaporte) }}">
                        </div>
                        <div style="flex: 1;" class="mb-3">
                            <label for="n9__enviado_a_legales" class="block text-sm font-medium text-gray-700">Enviado a Legales</label>
                            <input type="date" id="n9__enviado_a_legales" name="n9__enviado_a_legales"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n9__enviado_a_legales', $deal_db->n9__enviado_a_legales) }}">
                        </div>
                    </div>
                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="n91__recepcion_recaudos_fisico" class="block text-sm font-medium text-gray-700">Recepción de Recaudos Físicos</label>
                            <input type="date" id="n91__recepcion_recaudos_fisico" name="n91__recepcion_recaudos_fisico"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n91__recepcion_recaudos_fisico', $deal_db->n91__recepcion_recaudos_fisico) }}">
                        </div>
                    </div>

                    <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                        <span class="ctvSefar block text-indigo-600">FORMALIZACIÓN DEL TRÁMITE</span>
                    </h2>

                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="n1__lugar_del_expediente" class="block text-sm font-medium text-gray-700">Lugar del Expediente</label>
                            <select placeholder="" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                id="n1__lugar_del_expediente"
                                name="n1__lugar_del_expediente">

                                @php
                                    $selectedValue = old('n1__lugar_del_expediente', $deal_db->n1__lugar_del_expediente);
                                @endphp

                                <option value="" {{ $selectedValue == '' ? 'selected' : '' }}>Elegir…</option>
                                <option value="SEVILLA" {{ $selectedValue == 'SEVILLA' ? 'selected' : '' }}>SEVILLA</option>
                                <option value="MADRID" {{ $selectedValue == 'MADRID' ? 'selected' : '' }}>MADRID</option>
                                <option value="SE ENVIO A MADRID" {{ $selectedValue == 'SE ENVIO A MADRID' ? 'selected' : '' }}>SE ENVIO A MADRID</option>
                                <option value="SE ENVIO A SEVILLA" {{ $selectedValue == 'SE ENVIO A SEVILLA' ? 'selected' : '' }}>SE ENVIO A SEVILLA</option>
                                <option value="CLIENTE" {{ $selectedValue == 'CLIENTE' ? 'selected' : '' }}>CLIENTE</option>
                                <option value="CON NATHALY" {{ $selectedValue == 'CON NATHALY' ? 'selected' : '' }}>CON NATHALY</option>
                                <option value="ESPAÑA" {{ $selectedValue == 'ESPAÑA' ? 'selected' : '' }}>España</option>
                                <option value="ITALIA" {{ $selectedValue == 'ITALIA' ? 'selected' : '' }}>ITALIA</option>
                                <option value="NOTARIO" {{ $selectedValue == 'NOTARIO' ? 'selected' : '' }}>NOTARIO</option>
                                <option value="PORTUGAL" {{ $selectedValue == 'PORTUGAL' ? 'selected' : '' }}>PORTUGAL</option>
                                <option value="SE ENVIO A ITALIA" {{ $selectedValue == 'SE ENVIO A ITALIA' ? 'selected' : '' }}>SE ENVIO A ITALIA</option>
                                <option value="TELEMATICO" {{ $selectedValue == 'TELEMATICO' ? 'selected' : '' }}>TELEMATICO</option>
                                <option value="OTRO" {{ $selectedValue == 'Otro…' ? 'selected' : '' }}>Otro…</option>
                            </select>

                        </div>
                        <div style="flex: 1;" class="mb-3">
                            <label for="n10__fecha_asignacion_de_juez" class="block text-sm font-medium text-gray-700">FECHA ASIGNACIÓN DE JUEZ</label>
                            <input type="date" id="n10__fecha_asignacion_de_juez" name="n10__fecha_asignacion_de_juez"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n10__fecha_asignacion_de_juez', $deal_db->n10__fecha_asignacion_de_juez) }}">
                        </div>
                    </div>
                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="n11__envio_redaccion_abogada" class="block text-sm font-medium text-gray-700">ENVIO REDACCIÓN ABOGADA</label>
                            <input type="date" id="n11__envio_redaccion_abogada" name="n11__envio_redaccion_abogada"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n11__envio_redaccion_abogada', $deal_db->n11__envio_redaccion_abogada) }}">
                        </div>
                        <div style="flex: 1;" class="mb-3">
                            <label for="n12__notas___no__expediente" class="block text-sm font-medium text-gray-700">NOTAS - No. EXPEDIENTE</label>
                            <input type="text" id="n12__notas___no__expediente" name="n12__notas___no__expediente"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n12__notas___no__expediente', $deal_db->n12__notas___no__expediente) }}">
                        </div>
                    </div>
                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="n13__fecha_recurso_alzada" class="block text-sm font-medium text-gray-700">Fecha Recurso de Alzada</label>
                            <input type="date" id="n13__fecha_recurso_alzada" name="n13__fecha_recurso_alzada"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n13__fecha_recurso_alzada', $deal_db->n13__fecha_recurso_alzada) }}">
                        </div>
                        <div style="flex: 1;" class="mb-3">
                            <label for="n2__ciudad_formalizacion" class="block text-sm font-medium text-gray-700">Ciudad Formalización</label>
                            <select placeholder="" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                id="n2__ciudad_formalizacion"
                                name="n2__ciudad_formalizacion">

                                @php
                                    $selectedValue = old('n2__ciudad_formalizacion', $deal_db->n2__ciudad_formalizacion);
                                @endphp

                                <option value="" {{ $selectedValue == '' ? 'selected' : '' }}>Elegir…</option>
                                <option value="SEVILLA" {{ $selectedValue == 'SEVILLA' ? 'selected' : '' }}>SEVILLA</option>
                                <option value="MADRID" {{ $selectedValue == 'MADRID' ? 'selected' : '' }}>MADRID</option>
                                <option value="No firmará con nosotros" {{ $selectedValue == 'No firmará con nosotros' ? 'selected' : '' }}>No firmará con nosotros</option>
                                <option value="-" {{ $selectedValue == '-' ? 'selected' : '' }}>-</option>
                                <option value="ITALIA" {{ $selectedValue == 'ITALIA' ? 'selected' : '' }}>ITALIA</option>
                                <option value="Lisboa" {{ $selectedValue == 'Lisboa' ? 'selected' : '' }}>Lisboa</option>
                                <option value="Marbella" {{ $selectedValue == 'Marbella' ? 'selected' : '' }}>Marbella</option>
                                <option value="OTRO" {{ $selectedValue == 'Otro…' ? 'selected' : '' }}>Otro…</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="n3__contratos_y_permisos" class="block text-sm font-medium text-gray-700">Contratos y permisos</label>
                            <select placeholder="" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                id="n3__contratos_y_permisos"
                                name="n3__contratos_y_permisos">

                                @php
                                    $selectedValue = old('n3__contratos_y_permisos', $deal_db->n3__contratos_y_permisos);
                                @endphp

                                <option value="" {{ $selectedValue == '' ? 'selected' : '' }}>Elegir…</option>
                                <option value="CONTRATO NO PERMISO FOTO NO" {{ $selectedValue == 'CONTRATO NO PERMISO FOTO NO' ? 'selected' : '' }}>CONTRATO NO PERMISO FOTO NO</option>
                                <option value="CONTRATO NO PERMISO FOTO SI" {{ $selectedValue == 'CONTRATO NO PERMISO FOTO SI' ? 'selected' : '' }}>CONTRATO NO PERMISO FOTO SI</option>
                                <option value="CONTRATO SI PERMISO FOTO NO" {{ $selectedValue == 'CONTRATO SI PERMISO FOTO NO' ? 'selected' : '' }}>CONTRATO SI PERMISO FOTO NO</option>
                                <option value="CONTRATO SI PERMISO FOTO SI" {{ $selectedValue == 'CONTRATO SI PERMISO FOTO SI' ? 'selected' : '' }}>CONTRATO SI PERMISO FOTO SI</option>
                                <option value="-" {{ $selectedValue == '-' ? 'selected' : '' }}>-</option>
                                <option value="OTRO" {{ $selectedValue == 'Otro…' ? 'selected' : '' }}>Otro…</option>
                            </select>
                        </div>
                        <div style="flex: 1;" class="mb-3">
                            <label for="n4__pago_tasa" class="block text-sm font-medium text-gray-700">Pago tasa</label>
                            <select placeholder="" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                id="n4__pago_tasa"
                                name="n4__pago_tasa">

                                @php
                                    $selectedValue = old('n4__pago_tasa', $deal_db->n4__pago_tasa);
                                @endphp

                                <option value="" {{ $selectedValue == '' ? 'selected' : '' }}>Elegir…</option>
                                <option value="TASA IMPRESA" {{ $selectedValue == 'TASA IMPRESA' ? 'selected' : '' }}>TASA IMPRESA</option>
                                <option value="TASA PAGADA POR SEFAR" {{ $selectedValue == 'TASA PAGADA POR SEFAR' ? 'selected' : '' }}>TASA PAGADA POR SEFAR</option>
                                <option value="TASA PAGADA POR CLIENTE" {{ $selectedValue == 'TASA PAGADA POR CLIENTE' ? 'selected' : '' }}>TASA PAGADA POR CLIENTE</option>
                                <option value="Tasa pagada por Sevilla" {{ $selectedValue == 'Tasa pagada por Sevilla' ? 'selected' : '' }}>Tasa pagada por Sevilla</option>
                                <option value="OTRO" {{ $selectedValue == 'Otro…' ? 'selected' : '' }}>Otro…</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="n5__fecha_de_formalizacion" class="block text-sm font-medium text-gray-700">Fecha de Formalización</label>
                            <input type="date" id="n5__fecha_de_formalizacion" name="n5__fecha_de_formalizacion"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n5__fecha_de_formalizacion', $deal_db->n5__fecha_de_formalizacion) }}">
                        </div>
                        <div style="flex: 1;" class="mb-3">
                            <label for="n6__fecha_acta_remitida_" class="block text-sm font-medium text-gray-700">FECHA ACTA ENVIADA AL MJ</label>
                            <input type="date" id="n6__fecha_acta_remitida_" name="n6__fecha_acta_remitida_"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n6__fecha_acta_remitida_', $deal_db->n6__fecha_acta_remitida_) }}">
                        </div>
                    </div>

                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="n7__fecha_de_resolucion" class="block text-sm font-medium text-gray-700">FECHA DE RESOLUCION</label>
                            <input type="date" id="n7__fecha_de_resolucion" name="n7__fecha_de_resolucion"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n7__fecha_de_resolucion', $deal_db->n7__fecha_de_resolucion) }}">
                        </div>
                        <div style="flex: 1;" class="mb-3">
                            <label for="n4__notario___abogado" class="block text-sm font-medium text-gray-700">NOTARIO / ABOGADO</label>
                            <input type="text" id="n4__notario___abogado" name="n4__notario___abogado"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n4__notario___abogado', $deal_db->n4__notario___abogado) }}">
                        </div>
                    </div>

                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="n9__notif__1__int__subsanar_" class="block text-sm font-medium text-gray-700">FECHA DE RESOLUCION</label>
                            <input type="date" id="n9__notif__1__int__subsanar_" name="n9__notif__1__int__subsanar_"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n9__notif__1__int__subsanar_', $deal_db->n9__notif__1__int__subsanar_) }}">
                        </div>
                        <div style="flex: 1;" class="mb-3">
                            <label for="codigo_de_proceso" class="block text-sm font-medium text-gray-700">CODIGO DE PROCESO</label>
                            <input type="text" id="codigo_de_proceso" name="codigo_de_proceso"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('codigo_de_proceso', $deal_db->codigo_de_proceso) }}">
                        </div>
                    </div>

                    <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                        <span class="ctvSefar block text-indigo-600">RESOLUCIÓN EXPRESA / PODERES</span>
                    </h2>

                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="n1__enviada_al_cliente" class="block text-sm font-medium text-gray-700">Enviada al Cliente</label>
                            <input type="date" id="n1__enviada_al_cliente" name="n1__enviada_al_cliente"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n1__enviada_al_cliente', $deal_db->n1__enviada_al_cliente) }}">
                        </div>
                        <div style="flex: 1;" class="mb-3">
                            <label for="n2__firmado_por_el_cliente" class="block text-sm font-medium text-gray-700">Firmado por el Cliente</label>
                            <input type="date" id="n2__firmado_por_el_cliente" name="n2__firmado_por_el_cliente"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n2__firmado_por_el_cliente', $deal_db->n2__firmado_por_el_cliente) }}">
                        </div>
                    </div>

                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="n3__gestionado___entregado" class="block text-sm font-medium text-gray-700">GESTIONADO / ENTREGADO</label>
                            <input type="date" id="n3__gestionado___entregado" name="n3__gestionado___entregado"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n3__gestionado___entregado', $deal_db->n3__gestionado___entregado) }}">
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade show" id="deal_payments" role="tabpanel" aria-labelledby="deal-payments-tab">
                    <!-- FASE 1 -->
                    <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                        <span class="ctvSefar block text-indigo-600">Pago de Fases</span>
                    </h2>
                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="fase_1_preestab" class="block text-sm font-medium text-gray-700">Fase 1 Preestablecido</label>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="text" id="fase_1_preestab" name="fase_1_preestab"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    value="{{ old('fase_1_preestab', $deal_db->fase_1_preestab) }}"
                                    {{ $deal_db->fase_1_enviado ? 'readonly' : ''}}>
                            </div>
                        </div>
                        @if(!$deal_db->fase_1_enviado)
                        <div style="flex: 1; display: flex; align-items: flex-end;">
                            <label class="inline-flex items-center" style="    padding-bottom: 1.1rem;">
                                <input type="checkbox" id="exonerar_fase_1" class="form-checkbox h-4 w-4 text-indigo-600 transition duration-150 ease-in-out">
                                <span class="ml-2 text-sm text-gray-700">Exonerar</span>
                            </label>
                        </div>
                        @endif
                        <div style="flex: 1; display: flex; align-items: flex-end;" class="mb-3">
                            @if(!$deal_db->fase_1_enviado)
                                <button type="button" id="guardarFase1" class="cfrSefar btn btn-primary mt-3">Enviar Pago a Cliente</button>
                            @else
                                @if (!$deal_db->fase_1_pagado)
                                    <div class="w-full">
                                        <label for="fase_1_enviado" class="block text-sm font-medium text-gray-700">Fase 1 (Fecha: Pago enviado)</label>
                                        <input type="date" id="fase_1_enviado" style="background-color:orange!important;" name="fase_1_enviado"
                                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            value="{{ old('fase_1_enviado', $deal_db->fase_1_enviado) }}" readonly>
                                    </div>
                                @else
                                    <div class="w-50">
                                        <label for="fecha_fase_1_pagado" class="block text-sm font-medium text-gray-700">Fase 1 Pagado (Fecha)</label>
                                        <input type="date" id="fecha_fase_1_pagado" name="fecha_fase_1_pagado" style="background-color: #00BB00!important;"
                                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            value="{{ old('fecha_fase_1_pagado', $deal_db->fecha_fase_1_pagado) }}" readonly>
                                    </div>
                                    <div class="w-50">
                                        <label for="monto_fase_1_pagado" class="block text-sm font-medium text-gray-700">(Monto)</label>
                                        <input type="text" id="monto_fase_1_pagado" name="monto_fase_1_pagado" style="background-color: #00BB00!important;"
                                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            value="{{ old('monto_fase_1_pagado', $deal_db->monto_fase_1_pagado) }}" readonly>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>

                    <!-- FASE 2 -->

                    @if ($deal_db->fase_1_enviado)
                        <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="fase_2_preestab" class="block text-sm font-medium text-gray-700">Fase 2 Preestablecido</label>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="text" id="fase_2_preestab" name="fase_2_preestab"
                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        value="{{ old('fase_2_preestab', $deal_db->fase_2_preestab) }}"
                                        {{ $deal_db->fase_2_enviado ? 'readonly' : ''}}>
                                </div>
                            </div>
                            @if(!$deal_db->fase_2_enviado)
                            <div style="flex: 1; display: flex; align-items: flex-end;">
                                <label class="inline-flex items-center" style="    padding-bottom: 1.1rem;">
                                    <input type="checkbox" id="exonerar_fase_2" class="form-checkbox h-4 w-4 text-indigo-600 transition duration-150 ease-in-out">
                                    <span class="ml-2 text-sm text-gray-700">Exonerar</span>
                                </label>
                            </div>
                            @endif
                            <div style="flex: 1; display: flex; align-items: flex-end;" class="mb-3">
                                @if(!$deal_db->fase_2_enviado)
                                    <button type="button" id="guardarFase2" class="cfrSefar btn btn-primary mt-3">Enviar Pago a Cliente</button>
                                @else
                                    @if (!$deal_db->fase_2_pagado)
                                        <div class="w-full">
                                            <label for="fase_2_enviado" class="block text-sm font-medium text-gray-700">Fase 2 (Fecha: Pago enviado)</label>
                                            <input type="date" id="fase_2_enviado" style="background-color:orange!important;" name="fase_2_enviado"
                                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                value="{{ old('fase_2_enviado', $deal_db->fase_2_enviado) }}" readonly>
                                        </div>
                                    @else
                                        <div class="w-50">
                                            <label for="fecha_fase_2_pagado" class="block text-sm font-medium text-gray-700">Fase 2 Pagado (Fecha)</label>
                                            <input type="date" id="fecha_fase_2_pagado" name="fecha_fase_2_pagado" style="background-color: #00BB00!important;"
                                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                value="{{ old('fecha_fase_2_pagado', $deal_db->fecha_fase_2_pagado) }}" readonly>
                                        </div>
                                        <div class="w-50">
                                            <label for="monto_fase_2_pagado" class="block text-sm font-medium text-gray-700">(Monto)</label>
                                            <input type="text" id="monto_fase_2_pagado" name="monto_fase_2_pagado" style="background-color: #00BB00!important;"
                                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                value="{{ old('monto_fase_2_pagado', $deal_db->monto_fase_2_pagado) }}" readonly>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- FASE 3 -->

                    @if ($deal_db->fase_1_enviado && $deal_db->fase_2_enviado)
                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="fase_3_preestab" class="block text-sm font-medium text-gray-700">Fase 3 Preestablecido</label>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="text" id="fase_3_preestab" name="fase_3_preestab"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    value="{{ old('fase_3_preestab', $deal_db->fase_3_preestab) }}"
                                    {{ $deal_db->fase_3_enviado ? 'readonly' : ''}}>
                            </div>
                        </div>
                        @if(!$deal_db->fase_3_enviado)
                        <div style="flex: 1; display: flex; align-items: flex-end;">
                            <label class="inline-flex items-center" style="    padding-bottom: 1.1rem;">
                                <input type="checkbox" id="exonerar_fase_3" class="form-checkbox h-4 w-4 text-indigo-600 transition duration-150 ease-in-out">
                                <span class="ml-2 text-sm text-gray-700">Exonerar</span>
                            </label>
                        </div>
                        @endif
                        <div style="flex: 1; display: flex; align-items: flex-end;" class="mb-3">
                            @if(!$deal_db->fase_3_enviado)
                                <button type="button" id="guardarFase3" class="cfrSefar btn btn-primary mt-3">Enviar Pago a Cliente</button>
                            @else
                                @if (!$deal_db->fase_3_pagado)
                                    <div class="w-full">
                                        <label for="fase_3_enviado" class="block text-sm font-medium text-gray-700">Fase 3 (Fecha: Pago enviado)</label>
                                        <input type="date" id="fase_3_enviado" style="background-color:orange!important;" name="fase_3_enviado"
                                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            value="{{ old('fase_3_enviado', $deal_db->fase_3_enviado) }}" readonly>
                                    </div>
                                @else
                                    <div class="w-50">
                                        <label for="fecha_fase_3_pagado" class="block text-sm font-medium text-gray-700">Fase 3 Pagado (Fecha)</label>
                                        <input type="date" id="fecha_fase_3_pagado" name="fecha_fase_3_pagado" style="background-color: #00BB00!important;"
                                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            value="{{ old('fecha_fase_3_pagado', $deal_db->fecha_fase_3_pagado) }}">
                                    </div>
                                    <div class="w-50">
                                        <label for="monto_fase_3_pagado" class="block text-sm font-medium text-gray-700">(Monto)</label>
                                        <input type="text" id="monto_fase_3_pagado" name="monto_fase_3_pagado" style="background-color: #00BB00!important;"
                                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            value="{{ old('monto_fase_3_pagado', $deal_db->monto_fase_3_pagado) }}">
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                    @endif

                    <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                        <span class="ctvSefar block text-indigo-600">Pagos miscelaneos</span>
                    </h2>

                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="carta_nat_preestab" class="block text-sm font-medium text-gray-700">Carta Nat Preestablecido</label>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="text" id="carta_nat_preestab" name="carta_nat_preestab"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    value="{{ old('carta_nat_preestab', $deal_db->carta_nat_preestab) }}"
                                    {{ $deal_db->carta_nat_enviado ? 'readonly' : ''}}>

                            </div>
                        </div>
                        @if (!$deal_db->carta_nat_enviado)
                        <div style="flex: 1; display: flex; align-items: flex-end;">
                            <label class="inline-flex items-center" style="    padding-bottom: 1.1rem;">
                                <input type="checkbox" id="exonerar_carta_nat" class="form-checkbox h-4 w-4 text-indigo-600 transition duration-150 ease-in-out">
                                    <span class="ml-2 text-sm text-gray-700">Exonerar</span>
                            </label>
                        </div>
                        @endif
                        <div style="flex: 1; display: flex; align-items: flex-end;" class="mb-3">
                            @if(!$deal_db->carta_nat_enviado )
                                <button type="button" id="guardarCartaNat" class="cfrSefar btn btn-primary mt-3">Enviar Pago a Cliente</button>
                            @else
                                @if (!$deal_db->carta_nat_fechapagado)
                                    <div class="w-full">
                                        <label for="carta_nat_enviado" class="block text-sm font-medium text-gray-700">Carta Nat (Fecha: Pago enviado)</label>
                                        <input type="date" id="carta_nat_enviado" style="background-color:orange!important;" name="carta_nat_enviado"
                                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            value="{{ old('carta_nat_enviado', $deal_db->carta_nat_enviado) }}" readonly>
                                    </div>
                                @else
                                    <div class="w-50">
                                        <label for="carta_nat_fechapagado" class="block text-sm font-medium text-gray-700">Carta Nat Pagado (Fecha)</label>
                                        <input type="date" id="carta_nat_fechapagado" name="carta_nat_fechapagado" style="background-color: #00BB00!important;"
                                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            value="{{ old('carta_nat_fechapagado', $deal_db->carta_nat_fechapagado) }}">
                                    </div>
                                    <div class="w-50">
                                        <label for="carta_nat_montopagado" class="block text-sm font-medium text-gray-700">(Monto)</label>
                                        <input type="text" id="carta_nat_montopagado" name="carta_nat_montopagado" style="background-color: #00BB00!important;"
                                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            value="{{ old('carta_nat_montopagado', $deal_db->carta_nat_montopagado) }}">
                                    </div>
                                @endif
                            @endif
                        </div>


                    </div>

                    <!-- CIL/FCJE -->
                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="cil___fcje_preestab" class="block text-sm font-medium text-gray-700">CIL FCJE Preestablecido</label>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="text" id="cil___fcje_preestab" name="cil___fcje_preestab"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    value="{{ old('cil___fcje_preestab', $deal_db->cil___fcje_preestab) }}"
                                    {{ $deal_db->carta_cilfcje_enviado  ? 'readonly' : ''}}>
                            </div>
                        </div>
                        @if (!$deal_db->carta_cilfcje_enviado)
                        <div style="flex: 1; display: flex; align-items: flex-end;">
                            <label class="inline-flex items-center"  style="    padding-bottom: 1.1rem;">
                                <input type="checkbox" id="exonerar_cil_fcje" class="form-checkbox h-4 w-4 text-indigo-600 transition duration-150 ease-in-out">
                                <span class="ml-2 text-sm text-gray-700">Exonerar</span>
                            </label>
                            <label class="inline-flex items-center ml-5"  style="    padding-bottom: 1.1rem;">
                                <input type="checkbox" id="incluido_fase1_cil_fcje" class="form-checkbox h-4 w-4 text-indigo-600 transition duration-150 ease-in-out">
                                <span class="ml-2 text-sm text-gray-700">Monto incluido en Fase 1</span>
                            </label>
                        </div>
                        @endif
                        <div style="flex: 1; display: flex; align-items: flex-end;" class="mb-3">
                            @if(!$deal_db->carta_cilfcje_enviado )
                                <button type="button" id="guardarfcjecil" class="cfrSefar btn btn-primary mt-3">Enviar Pago a Cliente</button>
                            @else
                                @if (!$deal_db->cilfcje_fechapagado)
                                    <label for="carta_cilfcje_enviado" class="block text-sm font-medium text-gray-700">FCJE/CIL (Fecha: Pago enviado)</label>
                                    <input type="date" id="carta_cilfcje_enviado" style="background-color:orange!important;" name="carta_cilfcje_enviado"
                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        value="{{ old('carta_cilfcje_enviado', $deal_db->carta_cilfcje_enviado) }}" readonly>
                                @else
                                    <div class="w-50">
                                        <label for="cilfcje_fechapagado" class="block text-sm font-medium text-gray-700">FCJE/CIL Pagado (Fecha)</label>
                                        <input type="date" id="cilfcje_fechapagado" name="cilfcje_fechapagado" style="background-color: #00BB00!important;"
                                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            value="{{ old('cilfcje_fechapagado', $deal_db->cilfcje_fechapagado) }}">
                                    </div>
                                    <div class="w-50">
                                        <label for="cilfcje_montopagado" class="block text-sm font-medium text-gray-700">(Monto)</label>
                                        <input type="text" id="cilfcje_montopagado" name="cilfcje_montopagado" style="background-color: #00BB00!important;"
                                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            value="{{ old('cilfcje_montopagado', $deal_db->cilfcje_montopagado) }}">
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                    @php $data = false; @endphp
                    @if ($data)
                    <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                        <span class="ctvSefar block text-indigo-600">Montos Totales</span>
                    </h2>

                    <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <div style="flex: 1;" class="mb-3">
                            <label for="n1__monto_preestablecido" class="block text-sm font-medium text-gray-700" readonly>Monto Preestablecido</label>
                            <input type="number" id="n1__monto_preestablecido" name="n1__monto_preestablecido"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n1__monto_preestablecido', $deal_db->n1__monto_preestablecido) }}">
                        </div>

                        <div style="flex: 1;" class="mb-3">
                            <label for="n2__monto_pagado" class="block text-sm font-medium text-gray-700" readonly>Monto Pagado</label>
                            <input type="number" id="n2__monto_pagado" name="n2__monto_pagado"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                value="{{ old('n2__monto_pagado', $deal_db->n2__monto_pagado) }}">
                        </div>
                    </div>
                    @endif

                    <script>
                        $(document).ready(function () {
                            let previousValue = "{{ $deal_db->teamleader_id }}"; // Guarda el valor inicial

                            $('#teamleader_id').on('change', function () {
                                const newValue = $(this).val();

                                // Si el valor seleccionado no está vacío y es diferente al anterior, muestra la alerta
                                if (previousValue !== newValue) {
                                    Swal.fire({
                                        title: '¿Estás seguro?',
                                        text: "El cambio va a sincronizar información entre Hubspot y Teamleader.",
                                        icon: 'warning',
                                        showCancelButton: true,
                                        confirmButtonColor: '#3085d6',
                                        cancelButtonColor: '#d33',
                                        confirmButtonText: 'Continuar',
                                        cancelButtonText: 'Cancelar'
                                    }).then((result) => {
                                        if (result.isConfirmed) {
                                            // Si el usuario elige "Continuar", llama al backend para sincronizar
                                            synchronizeWithBackend(newValue);
                                        } else {
                                            // Si el usuario elige "Cancelar", restaura el valor anterior
                                            $('#teamleader_id').val(previousValue);
                                        }
                                    });
                                }
                            });

                            $(`#exonerar_fase_1`).on('change', function () {
                                if ($(`#exonerar_fase_1`).prop('checked')) {
                                    Swal.fire({
                                        title: '¿Estás seguro?',
                                        text: "El cambio va a exonerar al cliente. Esto solo lo puede deshacer un administrador.",
                                        icon: 'warning',
                                        showCancelButton: true,
                                        confirmButtonColor: '#3085d6',
                                        cancelButtonColor: '#d33',
                                        confirmButtonText: 'Continuar',
                                        cancelButtonText: 'Cancelar'
                                    }).then((result) => {
                                        if (result.isConfirmed) {
                                            $.ajax({
                                                url: '/exonerarfase1',
                                                method: 'POST',
                                                headers: {
                                                    'X-CSRF-TOKEN': '{{ csrf_token() }}' // Asegúrate de incluir el token CSRF si usas Laravel
                                                },
                                                data: JSON.stringify({ fase_1_preestab: "EXONERADO", id: {{$deal_db->id}} }),
                                                contentType: 'application/json',
                                                success: function (data) {
                                                    if (data.success) {
                                                        Swal.fire({
                                                            title: 'Éxito',
                                                            text: 'La sincronización se completó correctamente.',
                                                            icon: 'success',
                                                            confirmButtonText: 'Aceptar'
                                                        }).then(() => {
                                                            // Recarga la página después de cerrar la alerta
                                                            window.location.reload();
                                                        });
                                                    } else {
                                                        Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                                    }
                                                },
                                                error: function () {
                                                    Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                                }
                                            });
                                        }
                                    });
                                } else {
                                    $(`#fase_1_preestab`).val('').prop('disabled', false);
                                }
                            });

                            $(`#exonerar_fase_3`).on('change', function () {
                                if ($(`#exonerar_fase_3`).prop('checked')) {
                                    Swal.fire({
                                        title: '¿Estás seguro?',
                                        text: "El cambio va a exonerar al cliente. Esto solo lo puede deshacer un administrador.",
                                        icon: 'warning',
                                        showCancelButton: true,
                                        confirmButtonColor: '#3085d6',
                                        cancelButtonColor: '#d33',
                                        confirmButtonText: 'Continuar',
                                        cancelButtonText: 'Cancelar'
                                    }).then((result) => {
                                        $.ajax({
                                            url: '/exonerarfase3',
                                            method: 'POST',
                                            headers: {
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}' // Asegúrate de incluir el token CSRF si usas Laravel
                                            },
                                            data: JSON.stringify({ fase_3_preestab: "EXONERADO", id: {{$deal_db->id}} }),
                                            contentType: 'application/json',
                                            success: function (data) {
                                                if (data.success) {
                                                    Swal.fire({
                                                        title: 'Éxito',
                                                        text: 'La sincronización se completó correctamente.',
                                                        icon: 'success',
                                                        confirmButtonText: 'Aceptar'
                                                    }).then(() => {
                                                        // Recarga la página después de cerrar la alerta
                                                        window.location.reload();
                                                    });
                                                } else {
                                                    Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                                }
                                            },
                                            error: function () {
                                                Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                            }
                                        });
                                    });
                                } else {
                                    $(`#fase_3_preestab`).val('').prop('disabled', false);
                                }
                            });

                            $(`#exonerar_fase_2`).on('change', function () {
                                if ($(`#exonerar_fase_2`).prop('checked')) {
                                    Swal.fire({
                                        title: '¿Estás seguro?',
                                        text: "El cambio va a exonerar al cliente. Esto solo lo puede deshacer un administrador.",
                                        icon: 'warning',
                                        showCancelButton: true,
                                        confirmButtonColor: '#3085d6',
                                        cancelButtonColor: '#d33',
                                        confirmButtonText: 'Continuar',
                                        cancelButtonText: 'Cancelar'
                                    }).then((result) => {
                                        $.ajax({
                                            url: '/exonerarfase2',
                                            method: 'POST',
                                            headers: {
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}' // Asegúrate de incluir el token CSRF si usas Laravel
                                            },
                                            data: JSON.stringify({ fase_2_preestab: "EXONERADO", id: {{$deal_db->id}} }),
                                            contentType: 'application/json',
                                            success: function (data) {
                                                if (data.success) {
                                                    Swal.fire({
                                                        title: 'Éxito',
                                                        text: 'La sincronización se completó correctamente.',
                                                        icon: 'success',
                                                        confirmButtonText: 'Aceptar'
                                                    }).then(() => {
                                                        // Recarga la página después de cerrar la alerta
                                                        window.location.reload();
                                                    });
                                                } else {
                                                    Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                                }
                                            },
                                            error: function () {
                                                Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                            }
                                        });
                                    });
                                } else {
                                    $(`#fase_2_preestab`).val('').prop('disabled', false);
                                }
                            });

                            $(`#exonerar_cil_fcje`).on('change', function () {
                                if ($(`#exonerar_cil_fcje`).prop('checked')) {
                                    Swal.fire({
                                        title: '¿Estás seguro?',
                                        text: "El cambio va a exonerar al cliente. Esto solo lo puede deshacer un administrador.",
                                        icon: 'warning',
                                        showCancelButton: true,
                                        confirmButtonColor: '#3085d6',
                                        cancelButtonColor: '#d33',
                                        confirmButtonText: 'Continuar',
                                        cancelButtonText: 'Cancelar'
                                    }).then((result) => {
                                        $.ajax({
                                            url: '/exonerarfcjecil',
                                            method: 'POST',
                                            headers: {
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}' // Asegúrate de incluir el token CSRF si usas Laravel
                                            },
                                            data: JSON.stringify({ cil___fcje_preestab: "EXONERADO", id: {{$deal_db->id}} }),
                                            contentType: 'application/json',
                                            success: function (data) {
                                                if (data.success) {
                                                    Swal.fire({
                                                        title: 'Éxito',
                                                        text: 'La sincronización se completó correctamente.',
                                                        icon: 'success',
                                                        confirmButtonText: 'Aceptar'
                                                    }).then(() => {
                                                        // Recarga la página después de cerrar la alerta
                                                        window.location.reload();
                                                    });
                                                } else {
                                                    Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                                }
                                            },
                                            error: function () {
                                                Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                            }
                                        });
                                    });
                                } else {
                                    $(`#cil___fcje_preestab`).val('').prop('disabled', false);
                                }
                            });

                            $(`#exonerar_carta_nat`).on('change', function () {
                                if ($(`#exonerar_carta_nat`).prop('checked')) {
                                    Swal.fire({
                                        title: '¿Estás seguro?',
                                        text: "El cambio va a exonerar al cliente. Esto solo lo puede deshacer un administrador.",
                                        icon: 'warning',
                                        showCancelButton: true,
                                        confirmButtonColor: '#3085d6',
                                        cancelButtonColor: '#d33',
                                        confirmButtonText: 'Continuar',
                                        cancelButtonText: 'Cancelar'
                                    }).then((result) => {
                                        $.ajax({
                                            url: '/exonerarcartanat',
                                            method: 'POST',
                                            headers: {
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}' // Asegúrate de incluir el token CSRF si usas Laravel
                                            },
                                            data: JSON.stringify({ carta_nat_preestab: "EXONERADO", id: {{$deal_db->id}} }),
                                            contentType: 'application/json',
                                            success: function (data) {
                                                if (data.success) {
                                                    Swal.fire({
                                                        title: 'Éxito',
                                                        text: 'La sincronización se completó correctamente.',
                                                        icon: 'success',
                                                        confirmButtonText: 'Aceptar'
                                                    }).then(() => {
                                                        // Recarga la página después de cerrar la alerta
                                                        window.location.reload();
                                                    });
                                                } else {
                                                    Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                                }
                                            },
                                            error: function () {
                                                Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                            }
                                        });
                                    });
                                } else {
                                    $(`#carta_nat_preestab`).val('').prop('disabled', false);
                                }
                            });

                            $(`#incluido_fase1_cil_fcje`).on('change', function () {
                                if ($(`#incluido_fase1_cil_fcje`).prop('checked')) {
                                    Swal.fire({
                                        title: '¿Estás seguro?',
                                        text: "El cambio va a exonerar al cliente. Esto solo lo puede deshacer un administrador.",
                                        icon: 'warning',
                                        showCancelButton: true,
                                        confirmButtonColor: '#3085d6',
                                        cancelButtonColor: '#d33',
                                        confirmButtonText: 'Continuar',
                                        cancelButtonText: 'Cancelar'
                                    }).then((result) => {
                                        $.ajax({
                                            url: '/incluidofase1cilfcje',
                                            method: 'POST',
                                            headers: {
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}' // Asegúrate de incluir el token CSRF si usas Laravel
                                            },
                                            data: JSON.stringify({ carta_nat_preestab: "INCLUIDO EN FASE 1 - ", id: {{$deal_db->id}} }),
                                            contentType: 'application/json',
                                            success: function (data) {
                                                if (data.success) {
                                                    Swal.fire({
                                                        title: 'Éxito',
                                                        text: 'La sincronización se completó correctamente.',
                                                        icon: 'success',
                                                        confirmButtonText: 'Aceptar'
                                                    }).then(() => {
                                                        // Recarga la página después de cerrar la alerta
                                                        window.location.reload();
                                                    });
                                                } else {
                                                    Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                                }
                                            },
                                            error: function () {
                                                Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                            }
                                        });
                                    });
                                } else {
                                    $(`#carta_nat_preestab`).val('').prop('disabled', false);
                                }
                            });

                            $("#guardarFase1").on('click', function() {
                                if ($("#fase_1_preestab").val()==="") {
                                    Swal.fire('Error', 'Debe preasignar un monto antes de enviar al cliente.', 'error');
                                    return false;
                                }

                                Swal.fire({
                                    title: '¿Estás seguro?',
                                    text: "Al enviar pago, se le notificará al cliente que debe pagar. Esto solo lo podrá deshacer un administrador.",
                                    icon: 'warning',
                                    showCancelButton: true,
                                    confirmButtonColor: '#3085d6',
                                    cancelButtonColor: '#d33',
                                    confirmButtonText: 'Continuar',
                                    cancelButtonText: 'Cancelar'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        $.ajax({
                                            url: '/guardarfase1',
                                            method: 'POST',
                                            headers: {
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}' // Asegúrate de incluir el token CSRF si usas Laravel
                                            },
                                            data: JSON.stringify({ fase_1_preestab: $("#fase_1_preestab").val(), id: {{$deal_db->id}} }),
                                            contentType: 'application/json',
                                            success: function (data) {
                                                if (data.success) {
                                                    Swal.fire({
                                                        title: 'Éxito',
                                                        text: 'La sincronización se completó correctamente.',
                                                        icon: 'success',
                                                        confirmButtonText: 'Aceptar'
                                                    }).then(() => {
                                                        // Recarga la página después de cerrar la alerta
                                                        window.location.reload();
                                                    });
                                                } else {
                                                    Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                                }
                                            },
                                            error: function () {
                                                Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                            }
                                        });
                                    }
                                });
                            });

                            $("#guardarFase2").on('click', function() {
                                if ($("#fase_2_preestab").val()==="") {
                                    Swal.fire('Error', 'Debe preasignar un monto antes de enviar al cliente.', 'error');
                                    return false;
                                }

                                Swal.fire({
                                    title: '¿Estás seguro?',
                                    text: "Al enviar pago, se le notificará al cliente que debe pagar. Esto solo lo podrá deshacer un administrador.",
                                    icon: 'warning',
                                    showCancelButton: true,
                                    confirmButtonColor: '#3085d6',
                                    cancelButtonColor: '#d33',
                                    confirmButtonText: 'Continuar',
                                    cancelButtonText: 'Cancelar'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        $.ajax({
                                            url: '/guardarfase2',
                                            method: 'POST',
                                            headers: {
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}' // Asegúrate de incluir el token CSRF si usas Laravel
                                            },
                                            data: JSON.stringify({ fase_2_preestab: $("#fase_2_preestab").val(), id: {{$deal_db->id}} }),
                                            contentType: 'application/json',
                                            success: function (data) {
                                                if (data.success) {
                                                    Swal.fire({
                                                        title: 'Éxito',
                                                        text: 'La sincronización se completó correctamente.',
                                                        icon: 'success',
                                                        confirmButtonText: 'Aceptar'
                                                    }).then(() => {
                                                        // Recarga la página después de cerrar la alerta
                                                        window.location.reload();
                                                    });
                                                } else {
                                                    Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                                }
                                            },
                                            error: function () {
                                                Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                            }
                                        });
                                    }
                                });


                            });

                            $("#guardarFase3").on('click', function() {
                                if ($("#fase_3_preestab").val()==="") {
                                    Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                    return false;
                                }

                                Swal.fire({
                                    title: '¿Estás seguro?',
                                    text: "Al enviar pago, se le notificará al cliente que debe pagar. Esto solo lo podrá deshacer un administrador.",
                                    icon: 'warning',
                                    showCancelButton: true,
                                    confirmButtonColor: '#3085d6',
                                    cancelButtonColor: '#d33',
                                    confirmButtonText: 'Continuar',
                                    cancelButtonText: 'Cancelar'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        $.ajax({
                                            url: '/guardarfase3',
                                            method: 'POST',
                                            headers: {
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}' // Asegúrate de incluir el token CSRF si usas Laravel
                                            },
                                            data: JSON.stringify({ fase_3_preestab: $("#fase_3_preestab").val(), id: {{$deal_db->id}} }),
                                            contentType: 'application/json',
                                            success: function (data) {
                                                if (data.success) {
                                                    Swal.fire({
                                                        title: 'Éxito',
                                                        text: 'La sincronización se completó correctamente.',
                                                        icon: 'success',
                                                        confirmButtonText: 'Aceptar'
                                                    }).then(() => {
                                                        // Recarga la página después de cerrar la alerta
                                                        window.location.reload();
                                                    });
                                                } else {
                                                    Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                                }
                                            },
                                            error: function () {
                                                Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                            }
                                        });
                                    }
                                });


                            });

                            $("#guardarCartaNat").on('click', function() {
                                if ($("#carta_nat_preestab").val()==="") {
                                    Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                    return false;
                                }

                                $.ajax({
                                    url: '/guardarcartanat',
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}' // Asegúrate de incluir el token CSRF si usas Laravel
                                    },
                                    data: JSON.stringify({ carta_nat_preestab: $("#carta_nat_preestab").val(), id: {{$deal_db->id}} }),
                                    contentType: 'application/json',
                                    success: function (data) {
                                        if (data.success) {
                                            Swal.fire({
                                                title: 'Éxito',
                                                text: 'La sincronización se completó correctamente.',
                                                icon: 'success',
                                                confirmButtonText: 'Aceptar'
                                            }).then(() => {
                                                // Recarga la página después de cerrar la alerta
                                                window.location.reload();
                                            });
                                        } else {
                                            Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                        }
                                    },
                                    error: function () {
                                        Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                    }
                                });
                            });

                            $("#guardarfcjecil").on('click', function() {
                                if ($("#cil___fcje_preestab").val()==="") {
                                    Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                    return false;
                                }

                                $.ajax({
                                    url: '/guardarfcjecil',
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}' // Asegúrate de incluir el token CSRF si usas Laravel
                                    },
                                    data: JSON.stringify({ cil___fcje_preestab: $("#cil___fcje_preestab").val(), id: {{$deal_db->id}} }),
                                    contentType: 'application/json',
                                    success: function (data) {
                                        if (data.success) {
                                            Swal.fire({
                                                title: 'Éxito',
                                                text: 'La sincronización se completó correctamente.',
                                                icon: 'success',
                                                confirmButtonText: 'Aceptar'
                                            }).then(() => {
                                                // Recarga la página después de cerrar la alerta
                                                window.location.reload();
                                            });
                                        } else {
                                            Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                        }
                                    },
                                    error: function () {
                                        Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                    }
                                });
                            });

                            // Función para llamar al backend y sincronizar
                            function synchronizeWithBackend(newValue) {
                                // Aquí puedes hacer una llamada AJAX a tu backend usando jQuery
                                $.ajax({
                                    url: '/sincronizarhsytl',
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}' // Asegúrate de incluir el token CSRF si usas Laravel
                                    },
                                    data: JSON.stringify({ teamleader_id: newValue, id: {{$deal_db->id}} }),
                                    contentType: 'application/json',
                                    success: function (data) {
                                        if (data.success) {
                                            Swal.fire({
                                                title: 'Éxito',
                                                text: 'La sincronización se completó correctamente.',
                                                icon: 'success',
                                                confirmButtonText: 'Aceptar'
                                            }).then(() => {
                                                // Recarga la página después de cerrar la alerta
                                                window.location.reload();
                                            });
                                        } else {
                                            Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                        }
                                    },
                                    error: function () {
                                        Swal.fire('Error', 'Hubo un problema durante la sincronización.', 'error');
                                    }
                                });
                            }
                        });
                        document.addEventListener('DOMContentLoaded', () => {
                            const fase1PreestabInput = document.getElementById('fase_1_preestab');

                            fase1PreestabInput.addEventListener('input', function (event) {
                                const regex = /^\d*\.?\d{0,2}$/;
                                if (!regex.test(this.value)) {
                                    this.value = this.value
                                        .replace(/[^0-9.]/g, '')
                                        .replace(/(\..*)\./g, '$1')
                                        .replace(/(\.\d{2})\d+$/g, '$1');
                                }
                            });

                            const fase2PreestabInput = document.getElementById('fase_2_preestab');

                            fase2PreestabInput.addEventListener('input', function (event) {
                                const regex = /^\d*\.?\d{0,2}$/;
                                if (!regex.test(this.value)) {
                                    this.value = this.value
                                        .replace(/[^0-9.]/g, '')
                                        .replace(/(\..*)\./g, '$1')
                                        .replace(/(\.\d{2})\d+$/g, '$1');
                                }
                            });

                            const fase3PreestabInput = document.getElementById('fase_3_preestab');

                            fase3PreestabInput.addEventListener('input', function (event) {
                                const regex = /^\d*\.?\d{0,2}$/;
                                if (!regex.test(this.value)) {
                                    this.value = this.value
                                        .replace(/[^0-9.]/g, '')
                                        .replace(/(\..*)\./g, '$1')
                                        .replace(/(\.\d{2})\d+$/g, '$1');
                                }
                            });
                        });
                    </script>
                </div>
            </div>

        </div>
    </div>

</x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
<script src="https://code.jquery.com/jquery-3.6.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-theme/0.1.0-beta.10/select2-bootstrap.min.css" rel="stylesheet" />

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables CSS para Bootstrap 4 -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/cleave.js/1.6.0/cleave.min.js"></script>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- DataTables CSS para Bootstrap 4 -->

<script type="text/javascript" src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        $('#servicio_solicitado2').on('change', function() {
            $('#servicio_solicitado').val($(this).val());
        });
    });
</script>

@stop
