<?php

return [
    'category' => 'banca_online_2026',
    'source' => 'banca_online_2026',

    'countries' => [
        'espana' => [
            'label' => 'Espana',
            'service_name' => 'Española Sefardi',
            'stripe_account' => 'default',
            'seed_catalog' => true,
            'public_enabled' => true,
        ],
        'portugal' => [
            'label' => 'Portugal',
            'service_name' => 'Portuguesa Sefardi',
            'stripe_account' => 'portugal',
            'seed_catalog' => false,
            'public_enabled' => false,
        ],
        'italia' => [
            'label' => 'Italia',
            'service_name' => 'Italiana',
            'stripe_account' => 'default',
            'seed_catalog' => false,
            'public_enabled' => false,
        ],
    ],

    'plans' => [
        'solicitud-estrategica' => [
            'title' => 'Solicitud estrategica de nacionalidad',
            'short_title' => 'Solicitud estrategica',
            'summary' => 'Fase legal y fase genealogica configurables en una sola contratacion.',
            'service_scope' => ['espana', 'portugal', 'italia'],
            'sections' => [
                [
                    'title' => 'Fase legal',
                    'summary' => 'Solicitud ante el Estado correspondiente y armado juridico del expediente.',
                    'items' => [
                        ['slug' => 'creacion-expediente-juridico', 'name' => 'Creacion del expediente juridico', 'price' => 0, 'required' => true, 'default_selected' => true],
                        ['slug' => 'creacion-expediente-familiar', 'name' => 'Creacion del expediente familiar', 'price' => 0, 'required' => true, 'default_selected' => true],
                        ['slug' => 'analisis-intuitu-personae', 'name' => 'Analisis Intuitu Personae del linaje', 'price' => 0, 'required' => true, 'default_selected' => true],
                        ['slug' => 'desarrollo-argumental', 'name' => 'Desarrollo argumental de la solicitud juridico-administrativa', 'price' => 0, 'required' => true, 'default_selected' => true],
                        ['slug' => 'revision-cotejo-probatorio', 'name' => 'Revision y cotejo del material probatorio', 'price' => 0, 'required' => true, 'default_selected' => true],
                        ['slug' => 'formalizacion-expediente', 'name' => 'Formalizacion del expediente', 'price' => 0],
                        ['slug' => 'formalizacion-express-expediente', 'name' => 'Formalizacion express del expediente', 'price' => 0, 'group' => 'formalizacion'],
                        ['slug' => 'una-subsanacion-legal', 'name' => '1 subsanacion', 'price' => 575, 'group' => 'subsanaciones-legales'],
                        ['slug' => 'dos-subsanaciones-legales', 'name' => '2 subsanaciones', 'price' => 0, 'group' => 'subsanaciones-legales'],
                        ['slug' => 'tres-subsanaciones-legales', 'name' => '3 subsanaciones', 'price' => 0, 'group' => 'subsanaciones-legales'],
                        ['slug' => 'una-consulta-abogado', 'name' => '1 consulta con un abogado', 'price' => 275, 'group' => 'consultas-abogado'],
                        ['slug' => 'dos-consultas-abogado', 'name' => '2 consultas con un abogado', 'price' => 550, 'group' => 'consultas-abogado'],
                        ['slug' => 'tres-consultas-abogado', 'name' => '3 consultas con un abogado', 'price' => 725, 'group' => 'consultas-abogado'],
                    ],
                ],
                [
                    'title' => 'Fase genealogica',
                    'summary' => 'Trabajo ante la FCJE y soporte documental genealogico.',
                    'items' => [
                        ['slug' => 'creacion-expediente-genealogico', 'name' => 'Creacion del expediente genealogico', 'price' => 0],
                        ['slug' => 'revision-linea-documentacion', 'name' => 'Revision y cotejo de la linea y documentacion genealogica probatoria', 'price' => 0],
                        ['slug' => 'redaccion-informe-genealogico', 'name' => 'Redaccion del informe genealogico', 'price' => 0],
                        ['slug' => 'perfil-digital-fcje', 'name' => 'Creacion del perfil digital en la plataforma de la FCJE', 'price' => 0],
                        ['slug' => 'carga-informe-fcje', 'name' => 'Carga del informe genealogico y sus documentos ante la FCJE', 'price' => 0],
                        ['slug' => 'donativo-inicial', 'name' => 'Pago del donativo inicial', 'price' => 0],
                        ['slug' => 'documento-tramitacion-fcje', 'name' => 'Documento de tramitacion de la FCJE', 'price' => 0],
                        ['slug' => 'defensa-tres-requerimientos', 'name' => 'Defensa de genealogia ante la FCJE: 3 requerimientos', 'price' => 0, 'group' => 'requerimientos-fcje'],
                        ['slug' => 'defensa-seis-requerimientos', 'name' => 'Defensa de genealogia ante la FCJE: 6 requerimientos', 'price' => 0, 'group' => 'requerimientos-fcje'],
                        ['slug' => 'defensa-doce-requerimientos', 'name' => 'Defensa de genealogia ante la FCJE: 12 requerimientos', 'price' => 0, 'group' => 'requerimientos-fcje'],
                        ['slug' => 'aprobacion-certificado-donativo-final', 'name' => 'Obtencion de aprobacion del certificado y pago del donativo final', 'price' => 0],
                        ['slug' => 'carga-certificado-expediente', 'name' => 'Recepcion y carga del certificado al expediente juridico', 'price' => 0],
                        ['slug' => 'copia-certificado-fcje', 'name' => 'Copia del certificado de la FCJE', 'price' => 0],
                    ],
                ],
            ],
        ],

        'administrativo' => [
            'title' => 'Plan estrategico administrativo',
            'short_title' => 'Administrativo',
            'summary' => 'Subsanaciones, mejoras y recursos administrativos despues de la formalizacion.',
            'service_scope' => ['espana', 'portugal', 'italia'],
            'sections' => [
                [
                    'title' => 'Despues de la formalizacion',
                    'summary' => 'Acciones administrativas para sostener o reforzar el expediente.',
                    'items' => [
                        ['slug' => 'una-subsanacion-expediente', 'name' => '1 subsanacion del expediente', 'price' => 0, 'group' => 'subsanaciones-expediente'],
                        ['slug' => 'dos-subsanaciones-expediente', 'name' => '2 subsanaciones del expediente', 'price' => 0, 'group' => 'subsanaciones-expediente'],
                        ['slug' => 'tres-subsanaciones-expediente', 'name' => '3 subsanaciones del expediente', 'price' => 0, 'group' => 'subsanaciones-expediente'],
                        ['slug' => 'una-mejora-expediente', 'name' => '1 mejora del expediente', 'price' => 0, 'group' => 'mejoras-expediente'],
                        ['slug' => 'dos-mejoras-expediente', 'name' => '2 mejoras del expediente', 'price' => 0, 'group' => 'mejoras-expediente'],
                        ['slug' => 'tres-mejoras-expediente', 'name' => '3 mejoras del expediente', 'price' => 0, 'group' => 'mejoras-expediente'],
                        ['slug' => 'recurso-resolucion-expresa', 'name' => 'Recurso de solicitud de resolucion expresa', 'price' => 0],
                        ['slug' => 'recurso-alzada-silencio', 'name' => 'Recurso de alzada por silencio administrativo', 'price' => 0],
                    ],
                ],
            ],
        ],

        'judicial' => [
            'title' => 'Plan estrategico judicial',
            'short_title' => 'Judicial',
            'summary' => 'Recurso contencioso administrativo, pendiente de desglose operativo.',
            'service_scope' => ['espana', 'portugal', 'italia'],
            'sections' => [
                [
                    'title' => 'Accion judicial',
                    'summary' => 'Recurso judicial asociado al expediente.',
                    'items' => [
                        ['slug' => 'recurso-contencioso-administrativo', 'name' => 'Recurso contencioso administrativo', 'price' => 0],
                    ],
                ],
            ],
        ],

        'reforzamiento-seguro' => [
            'title' => 'Plan estrategico de reforzamiento y seguro',
            'short_title' => 'Reforzamiento y seguro',
            'summary' => 'Analisis preventivo para cubrir un eventual cambio de linea.',
            'service_scope' => ['espana', 'portugal', 'italia'],
            'sections' => [
                [
                    'title' => 'Cambio de linea',
                    'summary' => 'Reserva estrategica para escenarios donde el caso requiera cambio de linea.',
                    'items' => [
                        ['slug' => 'un-analisis-cambio-linea', 'name' => '1 analisis de cambio de linea', 'price' => 0, 'group' => 'cambio-linea'],
                        ['slug' => 'dos-analisis-cambio-linea', 'name' => '2 analisis de cambio de linea', 'price' => 0, 'group' => 'cambio-linea'],
                        ['slug' => 'tres-analisis-cambio-linea', 'name' => '3 analisis de cambio de linea', 'price' => 0, 'group' => 'cambio-linea'],
                    ],
                ],
            ],
        ],
    ],
];
