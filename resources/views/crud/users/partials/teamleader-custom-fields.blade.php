@php
    $tlCustomGroups = [
        ['title' => 'Campos personalizados', 'open' => true, 'fields' => [
            ['Acta notarial', 'n1__acta_notarial'],
            ['Estado civil', 'edo_civil', 'select', ['SOLTERO (A)', 'CASADO (A)', 'DIVORCIADO (A)', 'VIUDO (A)']],
            ['Estatus de nacionalidad', 'n3__estatus_de_nacionalidad', 'select', ['Concedida', 'En Tramitación']],
            ['Otros nombres', 'n4__otros_nombres'],
            ['Fecha de registro', 'n5__fecha_de_registro', 'date'],
            ['Apto reunión Italia', 'apto_otros_procesos'],
        ]],
        ['title' => '1.- Proyecto', 'fields' => [
            ['Captador', 'captador'], ['Coordinador M&E', 'coordinador'], ['Documentos', 'documentos'],
            ['Fase actual', 'fase_actual'], ['Nombre proyecto', 'nombre_proyecto'],
        ]],
        ['title' => '2.- Filiaciones por producción', 'fields' => [
            ['1. F. Petición por genealogía', 'n1__f__peticion_por_genealogia', 'date'],
            ['2. F. de solicitud al cliente', 'n2__f__de_solicitud_al_cliente', 'date'],
            ['3. F. recordatorio filiación', 'n3__f___recordatorio_filiacion', 'date'],
            ['4. F. entregado genealogía', 'n4__f__entregado_genealogia', 'date'],
        ]],
        ['title' => '21.- Mayor información', 'fields' => [
            ['1. F. solicitado x genealogía', 'n1__f__solicitado_por_genealogia', 'date'],
            ['2. F. solicitud mayor info', 'n2__f_solicitud_mayor_info', 'date'],
            ['3. Fecha de recordatorio', 'n3__fecha_de_recordatorio', 'date'],
            ['4. F. enviada a genealogía', 'n4__f__enviada_a_genealogia', 'date'],
        ]],
        ['title' => '3.- Pasaporte', 'fields' => [
            ['N° pasaporte', 'passport'], ['Nombre en pasaporte', 'nombre_en_pasaporte'],
            ['Pasaporte fecha de caducidad', 'fecha_de_caducidad_del_pasaporte', 'date'],
            ['Pasaporte nacionalidad', 'pasaporte_nacionalidad'],
        ]],
        ['title' => '4.- Familia', 'fields' => [
            ['Grupo familiar', 'grupo_familiar'], ['Linaje', 'linaje'], ['Línea de Venezuela', 'linea_de_venezuela'],
            ['Nombre madre', 'nombres_y_apellidos_de_madre'], ['Nombre padre', 'nombres_y_apellidos_del_padre'],
        ]],
        ['title' => '5.- Nacimiento', 'fields' => [
            ['Ciudad de nacimiento', 'ciudad_de_nacimiento'], ['País de nacimiento', 'pais_de_nacimiento'],
            ['Partida de nacimiento simple', 'partida_de_nacimiento_simple'],
            ['Partida nacimiento en España', 'partida_nacimiento_en_espana'], ['Registro de nacimiento', 'registro_de_nacimiento'],
        ]],
        ['title' => '6.- A.I.V.', 'fields' => [
            ['2. AIV notificación aprobado', 'n2__aiv_notificacion_aprobado', 'date'],
            ['6. AIV recibido en España', 'n6__aiv_recibido_en_espana'],
        ]],
        ['title' => '7.- AACS', 'fields' => [
            ['1. AACS introducido asociación', 'n1__aacs_introducido_asociacion'],
            ['2. AACS notificación aprobado', 'n2__aacs_notificacion_aprobado'],
            ['4. AACS retirado asociación', 'n4__aacs_retirado_asociacion'],
            ['6. AACS recibido en España', 'n6__aacs_recibido_en_espana'],
        ]],
        ['title' => '8.- FCJE', 'fields' => [
            ['3. FCJE registro', 'n3__fcje_registro', 'date'],
            ['4. FCJE certifi. descargado', 'n4__fcje_certifi__descargado', 'date'],
        ]],
        ['title' => '90.- CCSE', 'fields' => [
            ['CCSE archivado España', 'ccse_archivado_espana'], ['CCSE resultado', 'ccse_resultado'],
        ]],
        ['title' => '93.- Matrimonio', 'fields' => [
            ['Partida de matrimonio España', 'partida_de_matrimonio_espana'],
            ['Partida de matrimonio simple', 'partida_de_matrimonio_simple'],
        ]],
        ['title' => '96.- General', 'fields' => [
            ['Años en residencia actual', 'anos_en_residencia_actual'], ['Categoría según la edad', 'categoria_segun_la_edad'],
        ]],
        ['title' => 'Representante legal', 'fields' => [
            ['Nombre representante legal', 'nombre_completo_del_tutor_o_representante_legal'],
        ]],
        ['title' => 'Responsable del pago', 'fields' => [
            ['N° pasaporte responsable pago', 'numero_pasaporte_responsable_pago'],
        ]],
    ];
@endphp

<section class="cos-custom-groups" aria-label="Campos personalizados de Teamleader">
    <h2 class="h4 font-weight-bold text-gray-900 mb-2">Campos personalizados</h2>
    <p class="text-muted mb-3">Organizados con los mismos grupos y el mismo orden de Teamleader.</p>

    @foreach($tlCustomGroups as $group)
        <details @if($group['open'] ?? false) open @endif>
            <summary>{{ $group['title'] }}</summary>
            <div class="cos-group-body">
                <div class="row">
                    @foreach($group['fields'] as $field)
                        @php
                            [$label, $name, $type, $options] = array_pad($field, 4, null);
                            $type = $type ?: 'text';
                            $storedValue = ($teamleaderProfileCustomValues ?? [])[$name] ?? data_get($user, $name);
                            $value = old($name, $storedValue);
                            $inputId = 'tl-custom-' . $name;
                        @endphp
                        <div class="col-md-6 col-xl-4 mb-3">
                            <label for="{{ $inputId }}" class="block text-sm font-medium text-gray-700">{{ $label }}</label>
                            @if($type === 'select')
                                <select id="{{ $inputId }}" name="{{ $name }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm sm:text-sm">
                                    <option value=""></option>
                                    @foreach($options ?? [] as $option)
                                        <option value="{{ $option }}" @selected((string) $value === (string) $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="{{ $type }}" id="{{ $inputId }}" name="{{ $name }}" value="{{ $value }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm sm:text-sm">
                            @endif
                        </div>
                    @endforeach
                </div>
                @if($group['title'] === '3.- Pasaporte')
                    @can('genealogista')
                        <div class="row">
                            <div class="col-md-6 col-xl-4 mb-3">
                                <label for="tl-custom-genealogy-tree-id" class="block text-sm font-medium text-gray-700">ID secundario del árbol</label>
                                <input type="text" id="tl-custom-genealogy-tree-id" name="genealogy_tree_id" value="{{ old('genealogy_tree_id', $user->genealogy_tree_id) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm sm:text-sm">
                            </div>
                        </div>
                    @endcan
                @endif
            </div>
        </details>
    @endforeach
</section>
