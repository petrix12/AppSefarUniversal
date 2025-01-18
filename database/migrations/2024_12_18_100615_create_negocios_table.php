<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNegociosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('negocios', function (Blueprint $table) {
            $table->id();
            $table->text('id_negocio_hubspot')->nullable();
            $table->text('id_negocio_teamleader')->nullable();
            $table->text('n1__aacs_introducido_asociacion')->nullable();
            $table->text('n1__acta_notarial')->nullable();
            $table->text('n1__f__peticion_por_genealogia')->nullable();
            $table->text('n1__f__solicitado_por_genealogia')->nullable();
            $table->text('n2__aacs_notificacion_aprobado')->nullable();
            $table->text('n2__aiv_notificacion_aprobado')->nullable();
            $table->text('marital_status')->nullable();
            $table->text('n2__f_solicitud_mayor_info')->nullable();
            $table->text('n2__f__de_solicitud_al_cliente')->nullable();
            $table->text('n3__estatus_de_nacionalidad')->nullable();
            $table->text('n3__f___recordatorio_filiacion')->nullable();
            $table->text('n3__fcje_registro')->nullable();
            $table->text('n3__fecha_de_recordatorio')->nullable();
            $table->text('n4__aacs_retirado_asociacion')->nullable();
            $table->text('n4__f__entregado_genealogia')->nullable();
            $table->text('n4__f__enviada_a_genealogia')->nullable();
            $table->text('n4__fcje_certifi__descargado')->nullable();
            $table->text('n4__otros_nombres')->nullable();
            $table->text('n5__fecha_de_registro')->nullable();
            $table->text('n6__aacs_recibido_en_espana')->nullable();
            $table->text('n6__aiv_recibido_en_espana')->nullable();
            $table->text('anos_en_residencia_actual')->nullable();
            $table->text('captador')->nullable();
            $table->text('categoria_segun_la_edad')->nullable();
            $table->text('ccse_archivado_espana')->nullable();
            $table->text('ccse_resultado')->nullable();
            $table->text('ciudad_de_nacimiento')->nullable();
            $table->text('documentos')->nullable();
            $table->text('email_2')->nullable();
            $table->text('fase_actual')->nullable();
            $table->text('grupo_familiar')->nullable();
            $table->text('linea_de_venezuela')->nullable();
            $table->text('linaje')->nullable();
            $table->text('numero_pasaporte_responsable_pago')->nullable();
            $table->text('nombre_en_pasaporte')->nullable();
            $table->text('nombres_y_apellidos_de_madre')->nullable();
            $table->text('nombres_y_apellidos_del_padre')->nullable();
            $table->text('nombre_proyecto')->nullable();
            $table->text('nombre_completo_del_tutor_o_representante_legal')->nullable();
            $table->text('pais_de_nacimiento')->nullable();
            $table->text('partida_de_matrimonio_espana')->nullable();
            $table->text('partida_de_matrimonio_simple')->nullable();
            $table->text('partida_de_nacimiento_simple')->nullable();
            $table->text('partida_nacimiento_en_espana')->nullable();
            $table->text('fecha_caducidad_pasaporte')->nullable();
            $table->text('pasaporte_nacionalidad')->nullable();
            $table->text('registro_de_nacimiento')->nullable();
            $table->text('n1__enviada_al_cliente')->nullable();
            $table->text('n1__lugar_del_expediente')->nullable();
            $table->text('n1__monto_preestablecido')->nullable();
            $table->text('n10__fecha_asignacion_de_juez')->nullable();
            $table->text('n11__envio_redaccion_abogada')->nullable();
            $table->text('n12__notas___no__expediente')->nullable();
            $table->text('n13__fecha_recurso_alzada')->nullable();
            $table->text('n2__firmado_por_el_cliente')->nullable();
            $table->text('n2__antecedentes_penales')->nullable();
            $table->text('n2__ciudad_formalizacion')->nullable();
            $table->text('n2__enviado_a_redaccion_informe')->nullable();
            $table->text('n2__monto_pagado')->nullable();
            $table->text('n3__gestionado___entregado')->nullable();
            $table->text('n3__contratos_y_permisos')->nullable();
            $table->text('n3__f__vencimiento_ant__penal')->nullable();
            $table->text('n3__informe_cargado')->nullable();
            $table->text('n4__certificado_descargado')->nullable();
            $table->text('n4__pago_tasa')->nullable();
            $table->text('n5___f_solicitud_documentos')->nullable();
            $table->text('n5__fecha_de_formalizacion')->nullable();
            $table->text('n5__notas_genealogia')->nullable();
            $table->text('n6__cil_preaprobado')->nullable();
            $table->text('n6__fecha_acta_remitida_')->nullable();
            $table->text('n7__enviado_al_dto_juridico')->nullable();
            $table->text('n7__fecha_caducidad_pasaporte')->nullable();
            $table->text('n7__fecha_de_resolucion')->nullable();
            $table->text('n4__notario___abogado')->nullable();
            $table->text('n8__f_rec__solicitud_doc')->nullable();
            $table->text('n9__enviado_a_legales')->nullable();
            $table->text('n9__notif__1__int__subsanar_')->nullable();
            $table->text('n91__recepcion_recaudos_fisico')->nullable();
            $table->text('carta_nat_pagado')->nullable();
            $table->text('carta_nat_preestab')->nullable();
            $table->text('cil___fcje_pagado')->nullable();
            $table->text('cil___fcje_preestab')->nullable();
            $table->text('codigo_de_proceso')->nullable();
            $table->text('argumento_de_ventas__new_')->nullable();
            $table->text('fase_0_pagado__teamleader_')->nullable();
            $table->text('fase_1_pagado__teamleader_')->nullable();
            $table->text('fase_1_preestab')->nullable();
            $table->text('fase_2_pagado__teamleader_')->nullable();
            $table->text('fase_2_preestab')->nullable();
            $table->text('fase_3_pagado__teamleader_')->nullable();
            $table->text('fase_3_preestab')->nullable();
            $table->text('fecha_de_aceptacion')->nullable();
            $table->text('fecha_de_cobro')->nullable();
            $table->text('date_of_birth')->nullable();
            $table->text('mas_informacion')->nullable();
            $table->text('numero_de_pasaporte')->nullable();
            $table->text('pais_de_residencia')->nullable();
            $table->text('pago_registro')->nullable();
            $table->text('servicio_solicitado')->nullable();
            $table->text('representante_legal')->nullable();
            $table->integer('id_user')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('negocios');
    }
}
