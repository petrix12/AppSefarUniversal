<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Jotform\Facade\Jotform;
use App\Models\SolicitudCupon;

use App\Mail\SolicitudCuponMailable;
use Illuminate\Support\Facades\Mail;

class ObtenerDatosFormulario extends Command
{
    protected $signature = 'jotform:obtener-datos {formId}';
    protected $description = 'Obtiene los datos de un formulario de JotForm';

    public function handle()
    {
        $formId = $this->argument('formId');

        // Obtener las últimas 20 submissions
        try {
            $submissions = JotForm::getFormSubmissions($formId);
        } catch (\Exception $e) {
            $this->error('Error al obtener submissions: ' . $e->getMessage());
            return;
        }

        if (empty($submissions)) {
            $this->info('No hay submissions para procesar.');
            return;
        }

        foreach ($submissions as $submissionData) {
            $jotformSubmissionId = $submissionData['id'];

            // Verificar si la submission ya existe en la base de datos
            $existingSolicitud = SolicitudCupon::where('jotform_submission_id', $jotformSubmissionId)->first();

            if ($existingSolicitud) {
                $this->info("La submission ID {$jotformSubmissionId} ya existe. Saltando...");
                continue;
            }

            $this->info("Procesando submission ID {$jotformSubmissionId}...");

            // Mapeo de los datos
            $solicitud = new SolicitudCupon();
            $solicitud->jotform_submission_id = $jotformSubmissionId;
            $solicitud->form_id = $submissionData['form_id'];
            $solicitud->ip = $submissionData['ip'];
            $solicitud->estatus_cupon = 0; // Establecer estatus_cupon a 0 para nuevos registros

            // Mapeo de respuestas
            $answers = $submissionData['answers'];

            // Nombre y apellidos del solicitante
            if (isset($answers['21']['answer'])) {
                $solicitud->nombre_solicitante = $answers['21']['answer']['first'] ?? '';
                $solicitud->apellidos_solicitante = $answers['21']['answer']['last'] ?? '';
            }

            // Correo electrónico del solicitante
            $solicitud->correo_solicitante = $answers['22']['answer'] ?? '';

            // Nombre y apellidos del cliente
            $solicitud->nombre_cliente = $answers['43']['answer'] ?? '';
            $solicitud->apellidos_cliente = $answers['44']['answer'] ?? '';

            // Correo electrónico del cliente
            $solicitud->correo_cliente = $answers['24']['answer'] ?? '';

            // Número de pasaporte del cliente
            $solicitud->pasaporte_cliente = $answers['25']['answer'] ?? '';

            // Motivo de la solicitud
            $solicitud->motivo_solicitud = $answers['26']['answer'] ?? '';

            // Tipo de cupón solicitado
            $solicitud->tipo_cupon = $answers['28']['answer'] ?? '';

            // Porcentaje de descuento
            $solicitud->porcentaje_descuento = $answers['28']['answer'] == "Cupones de registro 100% - Gratuitos por pagos en efectivo o transferencia." || $answers['28']['answer'] == "Cupones de registro 100% - Gratuito sin pago realizado." ? "100" : $answers['31']['answer'];

            // ID de cupón
            $solicitud->id_cupon = $answers['48']['answer'] ?? null;

            // Procesar archivo adjunto (comprobante de pago)
            if (isset($answers['32']['answer']) && !empty($answers['32']['answer'])) {
                $fileUrls = $answers['32']['answer'];

                // Guardar las URLs originales en la base de datos
                $solicitud->comprobante_pago = implode(',', $fileUrls);
            }

            // Guardar la solicitud en la base de datos
            $solicitud->save();

            $this->info("Solicitud con submission ID {$jotformSubmissionId} guardada exitosamente.");

            // Definir destinatarios en función del tipo de cupón
            $destinatarios = [];
            if ($answers['28']['answer'] == "Cupones de registro 100% - Gratuitos por pagos en efectivo o transferencia.") {
                $destinatarios = ['admin.sefar@sefarvzla.com']; // Abel Tejada - Administración
            } else {
                $destinatarios = ['veronica.poletto@sefarvzla.com', 'yeinsondiaz@sefarvzla.com']; // Verónica y Yeinson - Ventas
            }

            // Enviar el correo con la información de la solicitud a los destinatarios
            Mail::to($destinatarios)->send(new SolicitudCuponMailable($solicitud));
            $this->info('Correo enviado para la solicitud de cupón.');
        }

        $this->info('Proceso completado.');
    }
}
