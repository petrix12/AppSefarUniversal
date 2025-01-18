<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AssocTlHs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Crear la tabla
        Schema::create('assoc_tl_hs', function (Blueprint $table) {
            $table->string('tl_id')->nullable();
            $table->string('hs_id')->nullable();
            $table->string('modulo')->nullable();
            $table->timestamps();
        });

        // Insertar datos directamente
        $data = [
            ['tl_id' => 'a7d5e90f-b85c-0381-ab53-a023ba531687', 'hs_id' => 'n1__aacs_introducido_asociacion'],
            ['tl_id' => 'f9edf3cc-45f3-01f5-8b59-ae0aeb441085', 'hs_id' => 'n1__acta_notarial'],
            ['tl_id' => '37d437c1-e3be-0f1f-a550-cb708d861d28', 'hs_id' => 'n1__f__peticion_por_genealogia'],
            ['tl_id' => '2550d88e-a178-03b9-855d-418a0ea61fb7', 'hs_id' => 'n1__f__solicitado_por_genealogia'],
            ['tl_id' => '93e9803c-120b-0dd0-bc51-5e7d0e931688', 'hs_id' => 'n2__aacs_notificacion_aprobado'],
            ['tl_id' => '587168d4-898d-0a0b-8757-a39948040e1e', 'hs_id' => 'n2__aiv_notificacion_aprobado'],
            ['tl_id' => '1a3728ff-2ea6-08bb-9c55-6d689543168d', 'hs_id' => 'marital_status'],
            ['tl_id' => '8bbbdc42-a4c2-00aa-8556-a36f3c361fbb', 'hs_id' => 'n2__f_solicitud_mayor_info'],
            ['tl_id' => '86c965fb-6825-06b0-9b5c-fa3384661fb5', 'hs_id' => 'n2__f__de_solicitud_al_cliente'],
            ['tl_id' => 'a0b2039f-4f7c-0637-ad51-fe9665f6242e', 'hs_id' => 'n3__estatus_de_nacionalidad'],
            ['tl_id' => 'f504c8c4-575a-077f-b655-1511b6261fbe', 'hs_id' => 'n3__f___recordatorio_filiacion'],
            ['tl_id' => 'e6214fec-18d9-00d3-9450-9fa85ea4106f', 'hs_id' => 'n3__fcje_registro'],
            ['tl_id' => '39267583-3b34-0d7a-b254-4e7ff3261fbc', 'hs_id' => 'n3__fecha_de_recordatorio'],
            ['tl_id' => 'b6068ca5-04a1-032a-b759-4fac5d231689', 'hs_id' => 'n4__aacs_retirado_asociacion'],
            ['tl_id' => '1f8057a3-3c4a-029d-b854-c086d7561d2a', 'hs_id' => 'n4__f__entregado_genealogia'],
            ['tl_id' => 'eaad9461-0dee-050b-b95e-a31476e61e89', 'hs_id' => 'n4__f__enviada_a_genealogia'],
            ['tl_id' => '3de4f971-72f4-0815-b454-f66638a41075', 'hs_id' => 'n4__fcje_certifi__descargado'],
            ['tl_id' => 'f87474e2-4fa7-0bc6-aa58-7965d9131666', 'hs_id' => 'n4__otros_nombres'],
            ['tl_id' => '88c654aa-ac12-0632-9c52-6b3515962fcd', 'hs_id' => 'n5__fecha_de_registro'],
            ['tl_id' => 'ee610426-9b22-0dcf-b35d-304ade83168b', 'hs_id' => 'n6__aacs_recibido_en_espana'],
            ['tl_id' => '43d80e1a-14c1-0841-975c-ef3f73040f63', 'hs_id' => 'n6__aiv_recibido_en_espana'],
            ['tl_id' => '35819c69-f96b-0925-b35c-d0ca54e3166e', 'hs_id' => 'anos_en_residencia_actual'],
            ['tl_id' => 'ed879c93-9d17-0e98-bc5e-68b447739824', 'hs_id' => 'captador'],
            ['tl_id' => 'e6ad4e86-2770-0dc2-9859-3e8984f31662', 'hs_id' => 'categoria_segun_la_edad'],
            ['tl_id' => 'a01c716a-40c1-0a1a-b958-686bb7831683', 'hs_id' => 'ccse_archivado_espana'],
            ['tl_id' => '3c9d4aa2-6cfd-0331-9f58-54a3d8c31681', 'hs_id' => 'ccse_resultado'],
            ['tl_id' => 'bb1ee070-6b52-037a-8459-e68dbf93166a', 'hs_id' => 'ciudad_de_nacimiento'],
            ['tl_id' => '8e76ecc7-0031-0382-b850-2de9fae38ade', 'hs_id' => 'documentos'],
            ['tl_id' => '7b6071ec-03a1-0af3-a35f-0891a0d442ee', 'hs_id' => 'email_2'],
            ['tl_id' => '0a85b203-67be-00ef-845f-41400be44355', 'hs_id' => 'fase_actual'],
            ['tl_id' => '0b9757f4-e601-06f6-a055-52160163165f', 'hs_id' => 'grupo_familiar'],
            ['tl_id' => '812ab4c0-9a75-07ad-b051-c2376283165e', 'hs_id' => 'linea_de_venezuela'],
            ['tl_id' => '1d754155-f38a-0b07-ad5f-954747d3165d', 'hs_id' => 'linaje'],
            ['tl_id' => '624a9810-53dc-0770-965b-65891c631673', 'hs_id' => 'numero_de_pasaporte'],
            ['tl_id' => '3a3d788e-af7c-0489-b55b-ca86c8431663', 'hs_id' => 'numero_pasaporte_responsable_pago'],
            ['tl_id' => '3cd103b9-0834-01dd-b856-d544fea31661', 'hs_id' => 'nombre_en_pasaporte'],
            ['tl_id' => 'f347e146-26b6-0fd8-945f-db228033166d', 'hs_id' => 'nombres_y_apellidos_de_madre'],
            ['tl_id' => '0f0a8dea-a45a-00de-8f5c-3c2de1a3166c', 'hs_id' => 'nombres_y_apellidos_del_padre'],
            ['tl_id' => 'ce21924a-4ad1-04ee-b350-cd5853131668', 'hs_id' => 'nombre_proyecto'],
            ['tl_id' => '669e5ce7-a9e0-0c9b-be5e-0d19beb31667', 'hs_id' => 'nombre_completo_del_tutor_o_representante_legal'],
            ['tl_id' => 'f75cbd00-5034-087c-b05f-091f47231669', 'hs_id' => 'pais_de_nacimiento'],
            ['tl_id' => 'e7bd21eb-1681-028b-a35d-62ffd3e3167a', 'hs_id' => 'partida_de_matrimonio_espana'],
            ['tl_id' => '342d5c68-a855-0bb8-9257-c7fd8d131679', 'hs_id' => 'partida_de_matrimonio_simple'],
            ['tl_id' => '0d0adb87-0e84-0bdc-b456-15e3a4631677', 'hs_id' => 'partida_de_nacimiento_simple'],
            ['tl_id' => 'ab04fa4b-b1ad-0131-8e5b-b77182d31678', 'hs_id' => 'partida_nacimiento_en_espana'],
            ['tl_id' => '6589efac-fd93-08a7-a755-18342d931675', 'hs_id' => 'fecha_caducidad_pasaporte'],
            ['tl_id' => '1b905ad9-350f-0f7b-a754-4ea282231674', 'hs_id' => 'pasaporte_nacionalidad'],
            ['tl_id' => 'f0f35b94-1de8-0e6e-a050-66488cc3166b', 'hs_id' => 'registro_de_nacimiento'],
            ['tl_id' => '4203d8ab-f1de-0145-af52-1bb278951268', 'hs_id' => 'n1__enviada_al_cliente'],
            ['tl_id' => 'e254d7ed-3c93-097d-b659-852a3b74c5e5', 'hs_id' => 'documentos'],
            ['tl_id' => '4bbfdc08-686d-0a03-8557-bd1d60d46f57', 'hs_id' => 'n1__lugar_del_expediente'],
            ['tl_id' => '6f7a4408-b146-0e58-a35b-8f02fed60887', 'hs_id' => 'n1__monto_preestablecido'],
            ['tl_id' => '497e7359-8b1a-056a-9e5e-28fa0cf5b2f1', 'hs_id' => 'n10__fecha_asignacion_de_juez'],
            ['tl_id' => 'e04af721-8808-0a43-9356-df374565b2fa', 'hs_id' => 'n11__envio_redaccion_abogada'],
            ['tl_id' => '4b822322-17a1-06ba-9b5b-82db70f46f5b', 'hs_id' => 'n12__notas___no__expediente'],
            ['tl_id' => '7c06cfed-87f4-00ac-8a5a-946b0b9643b8', 'hs_id' => 'n13__fecha_recurso_alzada'],
            ['tl_id' => '5f090e48-4a5b-0504-8259-9e945e95126a', 'hs_id' => 'n2__firmado_por_el_cliente'],
            ['tl_id' => '35c68020-1160-068b-b055-1b5e6fe4ca11', 'hs_id' => 'n2__antecedentes_penales'],
            ['tl_id' => 'ad849a21-82b3-0032-995e-6e9dbcd46f53', 'hs_id' => 'n2__ciudad_formalizacion'],
            ['tl_id' => 'ed8167e1-00e2-05fb-8a5c-900699b54d88', 'hs_id' => 'n2__enviado_a_redaccion_informe'],
            ['tl_id' => '4bef5482-f2e4-02da-8653-691944760f84', 'hs_id' => 'n2__monto_pagado'],
            ['tl_id' => 'b0421965-2b39-0c4d-9e51-1e567b05126b', 'hs_id' => 'n3__gestionado___entregado'],
            ['tl_id' => '39085084-d206-073e-8057-ef23ab046f5a', 'hs_id' => 'n3__contratos_y_permisos'],
            ['tl_id' => '578e17da-c01b-0a97-bc5a-7d9255b4c9d5', 'hs_id' => 'n3__f__vencimiento_ant__penal'],
            ['tl_id' => '1c067d8e-1b3b-0b4b-8c5f-436233b4c3f2', 'hs_id' => 'n3__informe_cargado'],
            ['tl_id' => '62a2cd97-1898-00bf-885c-029939e4c40f', 'hs_id' => 'n4__certificado_descargado'],
            ['tl_id' => 'a2d11316-e31b-0b2c-bd5e-0c7ad13491d0', 'hs_id' => 'n4__pago_tasa'],
            ['tl_id' => 'e0919d4b-322a-0c06-9759-0a6607f4c9db', 'hs_id' => 'n5___f_solicitud_documentos'],
            ['tl_id' => '7c87a75b-ce63-01da-9c58-5277f6c40fa9', 'hs_id' => 'n5__fecha_de_formalizacion'],
            ['tl_id' => 'edc41efc-e52f-0c9a-8e5d-41b8fff4c3f3', 'hs_id' => 'n5__notas_genealogia'],
            ['tl_id' => '57535be4-4738-00b5-9251-b53739e607c0', 'hs_id' => 'n6__cil_preaprobado'],
            ['tl_id' => '8091a7fc-3023-0625-8051-de85a4c46f59', 'hs_id' => 'n6__fecha_acta_remitida_'],
            ['tl_id' => 'c3feeebf-21a9-0cac-855e-e6f550260ee0', 'hs_id' => 'n7__enviado_al_dto_juridico'],
            ['tl_id' => '6fb8ef4e-6fdb-0241-8354-bda543e4cbff', 'hs_id' => 'n7__fecha_caducidad_pasaporte'],
            ['tl_id' => '3ef52253-5ac1-025a-8c5b-a9d094c468b8', 'hs_id' => 'n7__fecha_de_resolucion'],
            ['tl_id' => '36fa5b9d-bafd-0e61-9058-72b4ed547197', 'hs_id' => 'n4__notario___abogado'],
            ['tl_id' => 'e255a259-5328-0ee6-ab52-3e4f9604c9de', 'hs_id' => 'n8__f_rec__solicitud_doc'],
            ['tl_id' => '047dc070-6b23-0434-b858-61a1d7e4c9fd', 'hs_id' => 'n9__enviado_a_legales'],
            ['tl_id' => '7918f47c-4097-07e1-af57-d6c435660883', 'hs_id' => 'n9__notif__1__int__subsanar_'],
            ['tl_id' => '8e8ea98b-5137-047b-8157-c44935a4c3f1', 'hs_id' => 'n91__recepcion_recaudos_fisico'],
            ['tl_id' => '4339375f-ed77-02d9-a157-7da9f9e4bfac', 'hs_id' => 'carta_nat_pagado'],
            ['tl_id' => 'a42ed217-b570-0973-9052-fab97214c229', 'hs_id' => 'carta_nat_preestab'],
            ['tl_id' => 'f23fbe3b-5d13-0a41-a857-e9ab1c63dc42', 'hs_id' => 'cil___fcje_pagado'],
            ['tl_id' => 'aa1ce4b9-a410-00f2-a953-5f8c2713dc35', 'hs_id' => 'cil___fcje_preestab'],
            ['tl_id' => 'a42f63f5-d527-0544-ab50-9c03857707f2', 'hs_id' => 'codigo_de_proceso'],
            ['tl_id' => '99e6a164-e415-0df8-8a57-d1b1c104c858', 'hs_id' => 'argumento_de_ventas__new_'],
            ['tl_id' => 'd90b2e44-2e9b-0f29-945a-71c34bb3def0', 'hs_id' => 'fase_0_pagado__teamleader_'],
            ['tl_id' => 'a1b50c58-8175-0d13-9856-f661e783dc08', 'hs_id' => 'fase_1_pagado__teamleader_'],
            ['tl_id' => '73173887-a0e8-0f4f-bb55-b61f33d3c6e9', 'hs_id' => 'fase_1_preestab'],
            ['tl_id' => 'a5b94ccc-3ea8-06fc-b259-0a487073dc0d', 'hs_id' => 'fase_2_pagado__teamleader_'],
            ['tl_id' => 'c66a9c15-c965-0812-ad5b-7e48f183c6f9', 'hs_id' => 'fase_2_preestab'],
            ['tl_id' => '9a1df9b7-c92f-09e5-b156-96af3f83dc0e', 'hs_id' => 'fase_3_pagado__teamleader_'],
            ['tl_id' => 'e41fdbbb-a25a-005b-af56-9f3ca623c700', 'hs_id' => 'fase_3_preestab'],
            ['tl_id' => 'fbe8df81-7225-0c01-b051-7f1032054ffe', 'hs_id' => 'fecha_de_aceptacion'],
            ['tl_id' => '38b2425d-4a9c-0bfb-9955-f241d1f5f36d', 'hs_id' => 'fecha_de_cobro'],
            ['tl_id' => '2ef543c1-e76c-025a-a950-67eec7954d89', 'hs_id' => 'date_of_birth'],
            ['tl_id' => '54f6dba1-ae44-0472-ab5f-d8134c05b0b0', 'hs_id' => 'date_of_birth'],
            ['tl_id' => '570f4219-905c-0ea9-bb5b-132bb594d709', 'hs_id' => 'mas_informacion'],
            ['tl_id' => '891080d2-eeeb-030f-a256-d0ee6095773d', 'hs_id' => 'numero_de_pasaporte'],
            ['tl_id' => 'bd374fc3-39a5-0070-9455-67d94cc6b7f7', 'hs_id' => 'pais_de_residencia'],
            ['tl_id' => '2aaad95c-c06e-06ea-8454-ffeb07b59e9d', 'hs_id' => 'pago_registro'],
            ['tl_id' => 'fcd48891-20f6-049a-a05f-f78a6f951b4d', 'hs_id' => 'servicio_solicitado'],
            ['tl_id' => '360ccd07-627f-0959-9558-52730e95b101', 'hs_id' => 'representante_legal'],
        ];

        DB::table('assoc_tl_hs')->insert($data);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('assoc_tl_hs');
    }
}
