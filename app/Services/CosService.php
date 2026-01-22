<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\DocumentRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Customer Order Status Service
 *
 * Gestiona el cálculo del estado del proceso de nacionalidad
 * tanto en la fase genealógica como en la jurídica.
 *
 * @version 2.1
 */
class CosService
{
    // ============ PROPIEDADES ============

    private $negocio;
    private $user;
    private $negocios;
    private $mondayData;
    private $totalStepsGen;
    private $totalStepsJur;
    private $cos;
    private $serviceName;
    private $currentStepJur = -1;

    /**
     * Constructor del servicio
     */
    public function __construct($negocio, $user, $negocios, $mondayData = [])
    {
        $this->negocio = $negocio;
        $this->user = $user;
        $this->negocios = $negocios;
        $this->mondayData = $mondayData;
        $this->cos = array_cos();
        $this->negocio->servicio_solicitado2 = $this->getServiceName();
        $this->calculateTotalSteps();
        $this->logServiceInitialization();
    }

    // ============ MÉTODO PRINCIPAL ============

    /**
     * Calcula el estado completo del negocio
     */
    public function calculateStatus(): array
    {
        $certificadoDescargado = $this->calculateCertificadoStatus();

        Log::info("COS: Calculando estado", [
            'negocio_id' => $this->negocio->hubspot_id ?? 'unknown',
            'user_id' => $this->user->id,
            'certificado_descargado' => $certificadoDescargado
        ]);

        //dd($this->negocio->servicio_solicitado2);

        return $this->calculateCOS($certificadoDescargado);
    }

    // ============ MÉTODOS DE CÁLCULO DE ESTADO ============

    /**
     * Calcula el estado del proceso jurídico
     */
    private function calculateCOS($certificadoDescargado): array
    {
        $hoy = Carbon::now();

        $isJuridico = $this->isJuridicoProcess();

        $resultadoIA = $this->getIAResults();

        $rules = [
            // PASO 8: NACIONALIDAD CONCEDIDA (FINAL)
            [
                'name' => 'Nacionalidad Concedida',
                'condition' => fn() => $this->hasNacionalidadConcedida(),
                'stepJur' => $this->totalStepsJur - 1,
                'stepGen' => $this->totalStepsGen - 1,
                'warning' => null,
            ],

            // PASO 7: VÍA JUDICIAL ACTIVA
            [
                'name' => 'Vía Judicial',
                'condition' => fn() => $this->hasViaJudicialActiva(),
                'stepJur' => 7,
                'stepGen' => $this->getLastGenStep($certificadoDescargado),
                'warning' => null,
            ],

            [
                'name' => 'Vía Judicial Elegible',
                'condition' => fn() => $this->isViaJudicialElegible($hoy),
                'stepJur' => 7,
                'stepGen' => $this->getLastGenStep($certificadoDescargado),
                'warning' => fn() => $this->getViaJudicialWarning(),
            ],

            // 🔄 PASO 6: RECURSO DE ALZADA (+12 MESES) - EVALUAR PRIMERO
            [
                'name' => 'Recurso de Alzada Elegible',
                'condition' => fn() => $this->isRecursoAlzadaElegible($hoy),
                'stepJur' => 6,
                'stepGen' => $this->getLastGenStep($certificadoDescargado),
                'warning' => fn() => $this->getRecursoAlzadaWarning(),
            ],

            // 🔄 PASO 5: RESOLUCIÓN EXPRESA (+6 MESES) - EVALUAR SEGUNDO
            [
                'name' => 'Resolución Expresa Elegible',
                'condition' => fn() => $this->isResolucionExpresaElegible($hoy),
                'stepJur' => 5,
                'stepGen' => $this->getLastGenStep($certificadoDescargado),
                'warning' => fn() => $this->getResolucionExpresaWarning(),
            ],

            // 🔄 PASO 4: SUBSANACIÓN (+1 MES) - EVALUAR ÚLTIMO
            [
                'name' => 'Subsanación Elegible',
                'condition' => fn() => $this->isSubsanacionElegible($hoy),
                'stepJur' => 4,
                'stepGen' => $this->getLastGenStep($certificadoDescargado),
                'warning' => fn() => $this->getSubsanacionWarning(),
            ],

            // PASO 3: FORMALIZADO
            [
                'name' => 'Formalizado',
                'condition' => fn() => $this->isFormalizado(),
                'stepJur' => 3,
                'stepGen' => $this->getLastGenStep($certificadoDescargado),
                'warning' => null,
            ],

            // PASO 2: TASA PAGADA
            [
                'name' => 'Tasa Pagada',
                'condition' => fn() => isset($this->negocio->tasa_pagada),
                'stepJur' => 2,
                'stepGen' => $this->getLastGenStep($certificadoDescargado),
                'warning' => null,
            ],

            // PASO 1: ENVIADO A PAGO DE TASAS
            [
                'name' => 'Enviado a Pago de Tasas',
                'condition' => fn() => isset($this->negocio->enviado_a_pago_de_tasas),
                'stepJur' => 1,
                'stepGen' => $this->getLastGenStep($certificadoDescargado),
                'warning' => null,
            ],

            // PASO 0: FASE 3 PAGADA
            [
                'name' => 'Fase 3 Pagada',
                'condition' => fn() => $this->hasFase3Pagada(),
                'stepJur' => 0,
                'stepGen' => $this->getLastGenStep($certificadoDescargado),
                'warning' => null,
            ],

            // PASO 0: FASE 3 PAGADA
            [
                'name' => 'Fase 3 Pagada',
                'condition' => fn() => $this->hasFase3Pagada(),
                'stepJur' => 0,
                'stepGen' => $this->getLastGenStep($certificadoDescargado),
                'warning' => null,
            ],

            [
                'name' => 'Esperando Pago Fase 3',
                'condition' => fn() => $this->hasFase3Preestablecida(),
                'stepGen' => $this->totalStepsGen - 1 - $certificadoDescargado,
                'stepJur' => -1,
                'warning' => "<b>Realiza el pago para la formalización del expediente</b> y aseguremos juntos el siguiente gran paso hacia tu ciudadanía española.",
            ],

            // PASO 17: INFORME CARGADO (< 1 MES)
            [
                'name' => 'Informe Cargado Recientemente',
                'condition' => fn() => $this->isInformeCargadoReciente(),
                'stepGen' => 17,
                'stepJur' => -1,
                'warning' => null,
            ],

            // PASO 16: INFORME CARGADO (> 1 MES)
            [
                'name' => 'Informe Cargado - En Revisión',
                'condition' => fn() => isset($this->negocio->n3__informe_cargado),
                'stepGen' => 16,
                'stepJur' => -1,
                'warning' => null,
            ],

            // PASO 15: ESPERANDO PAGO FASE 2
            [
                'name' => 'Esperando Pago Fase 2',
                'condition' => fn() => $this->hasFase2Preestablecida(),
                'stepGen' => 15,
                'stepJur' => -1,
                'warning' => "Para continuar con el proceso y proceder con el envío del informe y las pruebas correspondientes a la institución mencionada, <b>es necesario que realices el siguiente pago.</b>",
            ],

            // PASO 14: ENVIADO A DTO JURÍDICO
            [
                'name' => 'Enviado al Departamento Jurídico',
                'condition' => fn() => isset($this->negocio->n7__enviado_al_dto_juridico),
                'stepGen' => 14,
                'stepJur' => -1,
                'warning' => null,
            ],

            // PASO 11: DERIVADO A OTROS PROCESOS
            [
                'name' => 'Derivado a Otros Procesos',
                'condition' => fn() => $resultadoIA['otrosProcesos'] ?? false,
                'stepGen' => 11,
                'stepJur' => -1,
                'warning' => "<b>Tu caso ha sido derivado a otro proceso.</b> Recibirás seguimiento personalizado.",
            ],

            // PASO 10: DOCUMENTOS APROBADOS
            [
                'name' => 'Documentos Aprobados',
                'condition' => fn() => $this->hasApprovedDocuments(),
                'stepGen' => 10,
                'stepJur' => -1,
                'warning' => null,
            ],

            // PASO 9: DOCUMENTOS EN REVISIÓN
            [
                'name' => 'Documentos en Revisión',
                'condition' => fn() => $this->hasDocumentsInReview(),
                'stepGen' => 9,
                'stepJur' => -1,
                'warning' => null,
            ],

            // PASO 8: DOCUMENTOS PENDIENTES
            [
                'name' => 'Documentos Pendientes',
                'condition' => fn() => $this->hasPendingDocuments(),
                'stepGen' => 8,
                'stepJur' => -1,
                'warning' => "Tienes solicitudes de documentos pendientes. Para resolverlas, dirígete a la pestaña de 'Mis solicitudes de documentos'",
            ],

            // PASO 8: FASE 1 PAGADA (alternativa)
            [
                'name' => 'Fase 1 Pagada',
                'condition' => fn() => $this->hasFase1Pagada(),
                'stepGen' => 8,
                'stepJur' => -1,
                'warning' => null,
            ],

            // PASO 7: ESPERANDO PAGO FASE 1
            [
                'name' => 'Esperando Pago Fase 1',
                'condition' => fn() => $this->hasFase1Preestablecida(),
                'stepGen' => 7,
                'stepJur' => -1,
                'warning' => "Para continuar con el proceso y proceder con la redacción del informe, <b>es necesario que realices el siguiente pago.</b>",
            ],

            // PASOS 2-6: BASADOS EN IA
            [
                'name' => 'Genealogía en Proceso',
                'condition' => fn() => $resultadoIA['genealogia'] ?? false,
                'stepGen' => 5,
                'stepJur' => -1,
                'warning' => null,
            ],

            [
                'name' => 'Inicio de Investigación',
                'condition' => fn() => $resultadoIA['inicioInvestigacion'] ?? false,
                'stepGen' => 4,
                'stepJur' => -1,
                'warning' => null,
            ],

            [
                'name' => 'Investigación Profunda',
                'condition' => fn() => $resultadoIA['investigacionProfunda'] ?? false,
                'stepGen' => 3,
                'stepJur' => -1,
                'warning' => null,
            ],

            [
                'name' => 'Investigación In Situ',
                'condition' => fn() => $resultadoIA['investigacionInSitu'] ?? false,
                'stepGen' => 2,
                'stepJur' => -1,
                'warning' => null,
            ],

            // PASO 1: ANÁLISIS INICIAL
            [
                'name' => 'Investigación Intuitu Personae',
                'condition' => fn() => $resultadoIA['investigacionIntuituPersonae'] ?? false,
                'stepGen' => 1,
                'stepJur' => -1,
                'subproceso' => 1,
                'warning' => null,
            ],

            [
                'name' => 'Análisis y Corrección',
                'condition' => fn() => $resultadoIA['analisisYCorreccion'] ?? false,
                'stepGen' => 1,
                'stepJur' => -1,
                'subproceso' => 0,
                'warning' => null,
            ],
        ];

        return $this->evaluateRules($rules, $certificadoDescargado, false);
    }

    // ============ EVALUACIÓN DE REGLAS ============

    /**
     * Evalúa las reglas y construye el resultado con detalles del paso
     */
    private function evaluateRules(array $rules, int $certificadoDescargado, bool $isJuridico): array
    {
        foreach ($rules as $rule) {
            try {
                if ($rule['condition']()) {
                    $warning = is_callable($rule['warning'])
                        ? $rule['warning']()
                        : $rule['warning'];

                    Log::info("COS: Regla encontrada", [
                        'rule_name' => $rule['name'],
                        'stepGen' => $rule['stepGen'],
                        'stepJur' => $rule['stepJur'],
                        'has_warning' => !empty($warning)
                    ]);

                    // Construir resultado con detalles del paso actual
                    return $this->buildStatusResult(
                        $rule['stepGen'],
                        $rule['stepJur'],
                        $certificadoDescargado,
                        $warning,
                        $rule['subproceso'] ?? null,
                        $rule['name']
                    );
                }
            } catch (\Exception $e) {
                Log::error("COS: Error evaluando regla", [
                    'rule_name' => $rule['name'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Regla por defecto
        Log::warning("COS: Ninguna regla coincidió, usando default", [
            'negocio_id' => $this->negocio->hubspot_id ?? 'unknown'
        ]);

        return $this->buildStatusResult(1, -1, $certificadoDescargado, null, -1, 'Registro Inicial');
    }

    /**
     * Construye el resultado completo del estado con detalles del paso actual
     */
    private function buildStatusResult(
        int $currentStepGen,
        int $currentStepJur,
        int $certificadoDescargado,
        ?string $warning = null,
        ?int $subproceso = null,
        string $description = ''
    ): array {
        // ========== GUARDAR currentStepJur EN LA CLASE ==========
        $this->currentStepJur = $currentStepJur;

        // ========== EVALUAR WARNING AUTOMÁTICO ==========
        if (empty($warning)) {
            $warning = $this->getWarning();
        }

        $result = [
            'servicio' => $this->getServicioDisplay(),
            'warning' => $warning,
            'certificadoDescargado' => $certificadoDescargado,
            'currentStepGen' => $currentStepGen,
            'currentStepJur' => $currentStepJur,
            'subproceso' => $subproceso,
            'description' => $description,
        ];

        // Obtener detalles del paso actual desde array_cos()
        $stepDetails = $this->getCurrentStepDetails(
            $currentStepGen,
            $currentStepJur,
            $certificadoDescargado
        );

        if ($stepDetails) {
            $result['currentStepDetails'] = $stepDetails;
            $result['currentStepName'] = $stepDetails['nombre_corto'];
        } else {
            $result['currentStepName'] = 'No iniciado';
        }

        return $result;
    }

    /**
     * Obtiene los detalles del paso actual desde array_cos()
     */
    private function getCurrentStepDetails(int $gen, int $jur, int $cert): ?array
    {
        // Si ambos son -1, no ha iniciado
        if ($gen == -1 && $jur == -1) {
            return null;
        }

        // Verificar que existe el servicio en array_cos
        if (!isset($this->cos[$this->negocio->servicio_solicitado2])) {
            Log::warning("COS: Servicio no encontrado en array_cos", [
                'servicio' => $this->negocio->servicio_solicitado2
            ]);
            return null;
        }

        // ========== PRIORIDAD ABSOLUTA: SI ESTÁ EN JURÍDICA, BUSCAR AHÍ ==========
        if ($jur >= 0) {
            $pasoJuridicoBuscado = $jur + 1; // Los pasos son base 1

            Log::info("COS: Cliente en fase jurídica, buscando paso", [
                'paso_buscado' => $pasoJuridicoBuscado,
                'jur_index' => $jur,
                'servicio' => $this->negocio->servicio_solicitado2
            ]);

            // Buscar en rama jurídica
            if (isset($this->cos[$this->negocio->servicio_solicitado2]['juridico'])) {
                foreach ($this->cos[$this->negocio->servicio_solicitado2]['juridico'] as $paso) {
                    if ($paso['paso'] == $pasoJuridicoBuscado) {
                        Log::info("COS: ✅ Paso jurídico encontrado", [
                            'paso_numero' => $pasoJuridicoBuscado,
                            'paso_nombre' => $paso['nombre_corto']
                        ]);
                        return $paso; // ← RETORNAR INMEDIATAMENTE
                    }
                }
            }

            Log::error("COS: ❌ CRÍTICO - Paso jurídico no encontrado", [
                'paso_buscado' => $pasoJuridicoBuscado,
                'jur' => $jur,
                'servicio' => $this->negocio->servicio_solicitado2,
                'pasos_disponibles' => array_column($this->cos[$this->negocio->servicio_solicitado2]['juridico'] ?? [], 'paso')
            ]);
        }

        // ========== SOLO SI NO ESTÁ EN JURÍDICA, BUSCAR EN GENEALÓGICA ==========
        if ($gen >= 0 && $jur == -1) { // ← CAMBIO: SOLO SI jur === -1
            $pasoGenealogicoBuscado = $gen + 1;

            Log::info("COS: Cliente en fase genealógica, buscando paso", [
                'paso_buscado' => $pasoGenealogicoBuscado,
                'gen_index' => $gen,
                'servicio' => $this->negocio->servicio_solicitado2
            ]);

            if (isset($this->cos[$this->negocio->servicio_solicitado2]['genealogico'])) {
                foreach ($this->cos[$this->negocio->servicio_solicitado2]['genealogico'] as $paso) {
                    if ($paso['paso'] == $pasoGenealogicoBuscado) {
                        Log::info("COS: ✅ Paso genealógico encontrado", [
                            'paso_numero' => $pasoGenealogicoBuscado,
                            'paso_nombre' => $paso['nombre_corto']
                        ]);
                        return $paso;
                    }
                }
            }

            Log::warning("COS: ❌ Paso genealógico no encontrado", [
                'paso_buscado' => $pasoGenealogicoBuscado,
                'pasos_disponibles' => array_column($this->cos[$this->negocio->servicio_solicitado2]['genealogico'] ?? [], 'paso')
            ]);
        }

        return null;
    }

    // ============ CONDICIONES DE ESTADO JURÍDICO ============

    private function hasNacionalidadConcedida(): bool
    {
        return isset($this->negocio->nacionalidad_concedida)
            || isset($this->negocio->n7__fecha_de_resolucion);
    }

    private function hasViaJudicialActiva(): bool
    {
        return $this->verificarNegocioActivo(
            $this->negocios,
            'Demanda Judicial',
            ['Demanda', 'Judicial']
        );
    }

    private function isViaJudicialElegible(Carbon $hoy): bool
    {
        // Debe existir fecha de recurso de alzada
        if (!isset($this->negocio->n13__fecha_recurso_alzada)) {
            return false;
        }

        // Regla: +3 meses desde la fecha del recurso de alzada
        $fechaRecurso = Carbon::parse($this->negocio->n13__fecha_recurso_alzada);
        $fechaLimite = $fechaRecurso->copy()->addMonths(3);

        return $hoy->greaterThan($fechaLimite);
    }

    private function getViaJudicialWarning(): ?string
    {
        // Si ya la tiene activa o solicitada, no mostrar
        $tieneViaJudicial = $this->hasViaJudicialActiva()
            || isset($this->negocio->fecha_solicitud_viajudicial);

        if ($tieneViaJudicial) {
            return null;
        }

        return '<b>¡Ya puedes solicitar la Vía Judicial!</b>';
    }


    private function isRecursoAlzadaElegible($hoy): bool
    {
        // Si ya tiene Recurso de Alzada en progreso (dentro de 3 meses)
        if (isset($this->negocio->n13__fecha_recurso_alzada)) {
            $fechaRecurso = Carbon::parse($this->negocio->n13__fecha_recurso_alzada);
            $fechaLimite = $fechaRecurso->copy()->addMonths(3);

            if ($fechaLimite->greaterThan($hoy)) {
                return true; // Ya está en proceso de Recurso
            }
        }

        // Si pasaron +12 meses desde formalización → Elegible para Recurso
        $fechaFormalizacion = $this->getFechaFormalizacion();
        if (!$fechaFormalizacion) {
            return false;
        }

        $fechaLimite = $fechaFormalizacion->copy()->addMonths(12);
        return $hoy->greaterThan($fechaLimite);
    }

    private function getRecursoAlzadaWarning(): ?string
    {
        $serviceName = $this->negocio->servicio_solicitado2;

        // ================= PORTUGUESA SEFARDÍ =================
        if ($this->isPortuguesaSefardi() || $serviceName == "Portuguesa Sefardi") {

            // Si ya tiene Recurso de Urgencia, no mostrar warning
            $tieneRecursoUrgencia = $this->verificarNegocioActivo(
                $this->negocios,
                'Recurso de Urgencia',
                ['Recurso', 'Urgencia']
            );

            if ($tieneRecursoUrgencia || isset($this->negocio->fecha_solicitud_recurso_urgencia)) {
                return null;
            }

            // Si tiene Vía Judicial, no mostrar warning
            $tieneViajudicial = $this->verificarNegocioActivo(
                $this->negocios,
                'Demanda Judicial',
                ['Demanda', 'Judicial']
            );

            if ($tieneViajudicial || isset($this->negocio->fecha_solicitud_viajudicial)) {
                return null;
            }

            return '<b>¡Solicita tu Recurso de Urgencia!</b>
                <a style="border:0!important;"
                href="https://sefaruniversal.com/bancaonline/"
                class="cfrSefar inline-flex items-center justify-center px-3 py-1 ml-2 text-decoration-none text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                Solicita el Recurso de Urgencia
                </a>';
        }

        // ================= RESTO DE SERVICIOS (ALZADA) =================

        // Si ya tiene Recurso de Alzada solicitado, no mostrar warning
        $tieneRecursoAlzada = $this->verificarNegocioActivo(
            $this->negocios,
            'Recurso de Alzada',
            ['Recurso', 'Alzada']
        );

        if ($tieneRecursoAlzada || isset($this->negocio->fecha_solicitud_recursoalzada)) {
            return null;
        }

        // Si tiene Vía Judicial, no mostrar warning de Recurso
        $tieneViajudicial = $this->verificarNegocioActivo(
            $this->negocios,
            'Demanda Judicial',
            ['Demanda', 'Judicial']
        );

        if ($tieneViajudicial || isset($this->negocio->fecha_solicitud_viajudicial)) {
            return null;
        }

        return '<b>¡Solicita tu Recurso de Alzada!</b>
            <a style="border:0!important;"
            href="https://sefaruniversal.com/landing-email-de-recurso-de-alzada/"
            class="cfrSefar inline-flex items-center justify-center px-3 py-1 ml-2 text-decoration-none text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
            Solicita el Recurso de Alzada
            </a>';
    }

    private function isResolucionExpresaElegible($hoy): bool
    {
        $fechaFormalizacion = $this->getFechaFormalizacion();
        if (!$fechaFormalizacion) {
            return false;
        }

        $fechaLimite = $fechaFormalizacion->copy()->addMonths(6); // ← CAMBIO: era 12
        return $hoy->greaterThan($fechaLimite);
    }

    private function getResolucionExpresaWarning(): ?string
    {
        $tieneResolucionExpresa = $this->verificarNegocioActivo(
            $this->negocios,
            'SOLICITUD DE DOCUMENTO DE RESOLUCIÓN EXPRESA',
            ['Resolución', 'Expresa']
        );

        if ($tieneResolucionExpresa || isset($this->negocio->fecha_solicitud_resolucionexpresa)) {
            return null;
        }

        return '<b>¡Solicita tu resolución expresa!</b><a href="https://sefaruniversal.com/resolucion-expresa/" style="border:0!important;" class="cfrSefar inline-flex items-center justify-center px-3 py-1 ml-2 text-decoration-none text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Solicita tu Resolución Expresa</a>';
    }

    private function isSubsanacionElegible($hoy): bool
    {
        $fechaFormalizacion = $this->getFechaFormalizacion();
        if (!$fechaFormalizacion) {
            return false;
        }

        $fechaLimite = $fechaFormalizacion->copy()->addMonths(1); // ← CAMBIO: era 6
        return $hoy->greaterThan($fechaLimite);
    }

    private function getSubsanacionWarning(): ?string
    {
        $tieneSubsanacion = $this->verificarNegocioActivo(
            $this->negocios,
            'Subsanación de Expediente',
            ['Subsanación']
        );

        if ($tieneSubsanacion) {
            return null;
        }

        return '<b>¡Consulta si requieres subsanación o mejora de expediente!</b><a style="border:0!important;" href="https://sefaruniversal.com/landing-registro-subsanacion-de-la-nacionalidad-espanola-sefardi/" class="cfrSefar inline-flex items-center justify-center px-3 py-1 ml-2 text-decoration-none text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">¡Consulta ahora!</a>';
    }

    /**
     * Obtiene el warning apropiado según servicio y paso actual
     * Compatible con múltiples tipos de recursos administrativos
     */
    private function getWarning(): ?string
    {
        // Si no está en jurídica, no hay warning
        if ($this->currentStepJur < 0) {
            return null;
        }

        $currentStepNumber = $this->currentStepJur + 1;
        $serviceName = $this->negocio->servicio_solicitado2;

        // ========== SERVICIOS ESPAÑOLES (TODOS USAN RECURSO DE ALZADA) ==========
        $serviciosEspanoles = [
            'Española Sefardi',
            'Subsanación de Expediente',
            'Nacionalidad por Carta de Naturaleza',
            'Española - Carta de Naturaleza General'
        ];

        if (in_array($serviceName, $serviciosEspanoles)) {
            // Paso 7 = Recurso de Alzada
            if ($currentStepNumber === 7) {
                $tieneRecurso = $this->negocios->contains(function ($negocio) {
                    $servicio = strtolower($negocio->servicio_solicitado2 ?? '');
                    $titulo = strtolower($negocio->titulo ?? '');
                    $dealname = strtolower($negocio->dealname ?? '');

                    return $servicio === 'recurso de alzada'
                        || str_contains($titulo, 'alzada')
                        || str_contains($dealname, 'alzada')
                        || str_contains($titulo, 'recurso alzada');
                });

                if (!$tieneRecurso) {
                    return '¡Solicita tu Recurso de Alzada!';
                }
            }
        }

        // ========== PORTUGUESA SEFARDÍ (TIENE 2 RECURSOS) ==========
        if ($serviceName === 'Portuguesa Sefardi') {
            // Paso 7 = Recurso de Urgencia
            if ($currentStepNumber === 7) {
                $tieneRecurso = $this->negocios->contains(function ($negocio) {
                    $servicio = strtolower($negocio->servicio_solicitado2 ?? '');
                    $titulo = strtolower($negocio->titulo ?? '');
                    $dealname = strtolower($negocio->dealname ?? '');

                    return $servicio === 'recurso de urgencia'
                        || str_contains($titulo, 'urgencia')
                        || str_contains($dealname, 'urgencia')
                        || str_contains($titulo, 'recurso urgencia');
                });

                if (!$tieneRecurso) {
                    return '¡Solicita tu Recurso de Urgencia!';
                }
            }

            // Paso 8 = Recurso Jerárquico
            if ($currentStepNumber === 8) {
                $tieneRecurso = $this->negocios->contains(function ($negocio) {
                    $servicio = strtolower($negocio->servicio_solicitado2 ?? '');
                    $titulo = strtolower($negocio->titulo ?? '');
                    $dealname = strtolower($negocio->dealname ?? '');

                    return $servicio === 'recurso jerárquico'
                        || $servicio === 'recurso jerarquico'
                        || str_contains($titulo, 'jerárquico')
                        || str_contains($titulo, 'jerarquico')
                        || str_contains($dealname, 'jerarquico')
                        || str_contains($dealname, 'jerárquico');
                });

                if (!$tieneRecurso) {
                    return '¡Solicita tu Recurso Jerárquico!';
                }
            }
        }

        return null;
    }

    private function isFormalizado(): bool
    {
        return isset($this->negocio->n5__fecha_de_formalizacion)
            || (isset($this->negocio->codigo_de_proceso)
                && $this->negocio->codigo_de_proceso == "FORMALIZADO 2024");
    }

    private function hasFase3Pagada(): bool
    {
        return isset($this->negocio->fase_3_pagado)
            || isset($this->negocio->fase_3_pagado__teamleader_);
    }

    // ============ CONDICIONES DE ESTADO GENEALÓGICO ============

    private function hasFase3Preestablecida(): bool
    {
        Log::info("COS: negocio attributes keys", [
            'hubspot_id' => $this->negocio->hubspot_id ?? null,
            'attr_keys' => array_keys($this->negocio->getAttributes()),
            'fase_3_preestab' => $this->negocio->getAttribute('fase_3_preestab'),
            'fase_3_preestablecida' => $this->negocio->getAttribute('fase_3_preestablecida'),
            'fase_3_pagado' => $this->negocio->getAttribute('fase_3_pagado'),
        ]);

        return isset($this->negocio->fase_3_preestab);
    }

    private function isInformeCargadoReciente(): bool
    {
        if (!isset($this->negocio->n3__informe_cargado)) {
            return false;
        }

        $fechaInforme = Carbon::parse($this->negocio->n3__informe_cargado);
        $fechaLimite = $fechaInforme->copy()->addMonths(1);

        return $fechaLimite->greaterThan(Carbon::now());
    }

    private function hasFase2Preestablecida(): bool
    {
        return isset($this->negocio->fase_2_preestab);
    }

    private function hasFase1Pagada(): bool
    {
        return isset($this->negocio->fase_1_pagado)
            || isset($this->negocio->fase_1_pagado__teamleader_);
    }

    private function hasFase1Preestablecida(): bool
    {
        return isset($this->negocio->fase_1_preestab);
    }

    // ============ CONDICIONES DE DOCUMENTOS ============

    private function hasApprovedDocuments(): bool
    {
        return DocumentRequest::where('user_id', $this->user->id)
            ->whereIn('status', ['no_documento', 'aprobada'])
            ->exists();
    }

    private function hasDocumentsInReview(): bool
    {
        return DocumentRequest::where('user_id', $this->user->id)
            ->where('status', 'resuelto')
            ->exists();
    }

    private function hasPendingDocuments(): bool
    {
        return DocumentRequest::where('user_id', $this->user->id)
            ->whereIn('status', ['en_espera_cliente', 'rechazada'])
            ->exists();
    }

    // ============ MÉTODOS AUXILIARES ============

    private function calculateCertificadoStatus(): int
    {
        // Carta de Naturaleza: sin certificado en genealógica
        if ($this->isCartaNaturaleza()) {
            // Si ya empezó jurídica pero NO tiene n7__enviado_al_dto_juridico
            if ($this->isJuridicoProcess() && !isset($this->negocio->n7__enviado_al_dto_juridico)) {
                return 1; // Restar 1 a genealógica
            }
            return 0;
        }

        // Portuguesa Sefardí: certificado descargado O CIL pre-aprobado
        if ($this->isPortuguesaSefardi()) {
            // Si tiene certificado descargado o CIL pre-aprobado, paso completo (0)
            if (isset($this->negocio->n4__certificado_descargado)/* || isset($this->negocio->n6__cil_preaprobado)*/) {
                return 0;
            }
            // Si tiene fase jurídica pero ninguno de los dos, restar 1
            if ($this->isJuridicoProcess()) {
                return 1;
            }
            return 1; // Por defecto, no descargado
        }

        //dd($this->negocio->n4__certificado_descargado);

        // Española Sefardí: lógica original
        return is_null($this->negocio->n4__certificado_descargado) ? 0 : 1;
    }

    private function isCartaNaturaleza(): bool
    {
        return in_array($this->negocio->servicio_solicitado2, [
            'Española - Carta de Naturaleza General',
            'Nacionalidad por Carta de Naturaleza'
        ]);
    }

    private function isPortuguesaSefardi(): bool
    {
        return in_array($this->negocio->servicio_solicitado2, [
            'Portuguesa Sefardí',
            'Portuguesa - Sefardí'
        ]);
    }

    private function isJuridicoProcess(): bool
    {
        return isset($this->negocio->n7__enviado_al_dto_juridico)
            || isset($this->negocio->fase_3_pagado)
            || isset($this->negocio->fase_3_pagado__teamleader_)
            || $this->negocio->servicio_solicitado == "Española - Carta de Naturaleza General"
            || $this->negocio->servicio_solicitado == "Nacionalidad por Carta de Naturaleza";
    }

    private function getLastGenStep($certificadoDescargado): int
    {
        if ($this->isJuridicoProcess()) {
            return $this->totalStepsGen - 1;
        }
        return $this->totalStepsGen - 1 - $certificadoDescargado;
    }

    private function getFechaFormalizacion(): ?Carbon
    {
        if (isset($this->negocio->codigo_de_proceso)
            && $this->negocio->codigo_de_proceso == "FORMALIZADO 2024") {
            return Carbon::parse('2024-01-01');
        }

        if (isset($this->negocio->n5__fecha_de_formalizacion)) {
            return Carbon::parse($this->negocio->n5__fecha_de_formalizacion);
        }

        return null;
    }

    private function getIAResults(): array
    {
        // Si ya pasó la fase genealógica, no analizar IA
        if (
            isset($this->negocio->fase_2_pagado) || isset($this->negocio->fase_2_pagado__teamleader_) ||
            isset($this->negocio->fase_3_pagado) || isset($this->negocio->fase_3_pagado__teamleader_) ||
            isset($this->negocio->n5__fecha_de_formalizacion) || isset($this->negocio->n7__enviado_al_dto_juridico) ||
            isset($this->negocio->n4__certificado_descargado)
        ) {
            return array_fill_keys([
                'otrosProcesos', 'pericial', 'genealogiaAprobada', 'genealogia',
                'investigacionProfunda', 'investigacionInSitu', 'analisisYCorreccion',
                'investigacionIntuituPersonae', 'inicioInvestigacion'
            ], false);
        }

        return $this->analizarEtiquetasYDevolverJSON($this->mondayData);
    }

    private function verificarNegocioActivo($negocios, $nombreCompleto, $palabrasClave = []): bool
    {
        foreach ($negocios as $negocio) {
            $servicio = $negocio->servicio_solicitado ?? '';
            $servicio2 = $negocio->servicio_solicitado2 ?? '';

            // Buscar por servicio_solicitado exacto
            if ($servicio === $nombreCompleto || $servicio2 === $nombreCompleto) {
                return true;
            }

            // Buscar por palabras clave en servicio_solicitado
            foreach ($palabrasClave as $palabra) {
                if (stripos($servicio, $palabra) !== false || stripos($servicio2, $palabra) !== false) {
                    return true;
                }
            }

            // 🆕 Buscar por palabras clave en dealname
            if (isset($negocio->dealname)) {
                $dealnameLower = mb_strtolower($negocio->dealname);
                $todasCoinciden = true;

                foreach ($palabrasClave as $palabra) {
                    if (stripos($dealnameLower, mb_strtolower($palabra)) === false) {
                        $todasCoinciden = false;
                        break;
                    }
                }

                if ($todasCoinciden && count($palabrasClave) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getServiceName(): string
    {
        return $this->negocio->servicio_solicitado2
            ?? $this->negocio->servicio_solicitado
            ?? 'Española Sefardi';
    }

    private function getServicioDisplay(): string
    {
        return $this->negocio->servicio_solicitado2 ?? $this->negocio->servicio_solicitado ?? '';
    }

    private function calculateTotalSteps(): void
    {
        if (isset($this->cos[$this->negocio->servicio_solicitado2])) {
            $this->totalStepsGen = count($this->cos[$this->negocio->servicio_solicitado2]['genealogico'] ?? []);
            $this->totalStepsJur = count($this->cos[$this->negocio->servicio_solicitado2]['juridico'] ?? []);
        } else {
            $this->totalStepsGen = 18;
            $this->totalStepsJur = 9;

            Log::warning("COS: Servicio no encontrado en COS helper", [
                'servicio' => $this->negocio->servicio_solicitado2,
                'usando_defaults' => true
            ]);
        }
    }

    private function logServiceInitialization(): void
    {
        Log::info("COS Service inicializado", [
            'servicio' => $this->negocio->servicio_solicitado2,
            'total_gen_steps' => $this->totalStepsGen,
            'total_jur_steps' => $this->totalStepsJur,
            'negocio_id' => $this->negocio->hubspot_id ?? 'unknown',
            'user_id' => $this->user->id
        ]);
    }

    // ============ ANÁLISIS DE IA (MONDAY) ============

    private function analizarEtiquetasYDevolverJSON($mondaydataforAI): array
    {
        $defaultResult = [
            'otrosProcesos' => false,
            'pericial' => false,
            'genealogiaAprobada' => false,
            'genealogia' => false,
            'inicioInvestigacion' => false,
            'investigacionProfunda' => false,
            'investigacionInSitu' => false,
            'analisisYCorreccion' => false,
            'investigacionIntuituPersonae' => false
        ];

        if (empty($mondaydataforAI) || empty($mondaydataforAI['tablero'])) {
            Log::info("COS IA: Sin datos de Monday, usando valores por defecto");
            return $defaultResult;
        }

        $cacheKey = $this->generateAICacheKey($mondaydataforAI);
        $cachedResult = Cache::get($cacheKey);

        if ($cachedResult !== null) {
            Log::info("COS IA: Usando resultado cacheado", ['cache_key' => $cacheKey]);
            return $cachedResult;
        }

        try {
            $aiResult = $this->callOpenRouterAI($mondaydataforAI);
            $validatedResult = $this->validateAndMergeAIResult($aiResult, $defaultResult);
            Cache::put($cacheKey, $validatedResult, 1800);

            Log::info("COS IA: Análisis completado exitosamente", [
                'tablero' => $mondaydataforAI['tablero'] ?? 'unknown',
                'resultado' => $validatedResult
            ]);

            return $validatedResult;
        } catch (\Exception $e) {
            Log::error("COS IA: Error en llamada a IA, usando fallback manual", [
                'error' => $e->getMessage(),
                'tablero' => $mondaydataforAI['tablero'] ?? 'unknown'
            ]);

            return $this->fallbackManualAnalysis($mondaydataforAI);
        }
    }

    private function generateAICacheKey($mondaydataforAI): string
    {
        $keyData = [
            'tablero' => $mondaydataforAI['tablero'] ?? '',
            'etiquetas' => $mondaydataforAI['etiquetas'] ?? '',
            'info_gen' => $mondaydataforAI['información_genealogia'] ?? '',
        ];

        return 'cos_ia_' . md5(json_encode($keyData));
    }

    private function callOpenRouterAI($mondaydataforAI): array
    {
        $apiKey = env('OPENROUTER_API_KEY');

        if (empty($apiKey)) {
            throw new \Exception("OPENROUTER_API_KEY no configurada");
        }

        $tablero = $mondaydataforAI['tablero'] ?? 'Sin tablero';
        $etiquetas = $mondaydataforAI['etiquetas'] ?? 'NO TIENE ETIQUETAS TODAVIA';

        $mensaje = [
            [
                "role" => "system",
                "content" => "Eres una IA especializada en genealogía legal para procesos de nacionalidad española sefardí. Evalúa el siguiente objeto y responde SOLO con un JSON válido con claves booleanas. No agregues explicación, markdown, ni texto adicional. Solo el JSON puro."
            ],
            [
                "role" => "user",
                "content" => $this->buildAIPrompt($tablero, $etiquetas)
            ]
        ];

        $response = Http::timeout(15)
            ->retry(2, 100)
            ->withHeaders([
                'Authorization' => "Bearer $apiKey",
                'Content-Type' => 'application/json',
            ])
            ->post("https://openrouter.ai/api/v1/chat/completions", [
                'model' => 'openai/gpt-4o-mini',
                'messages' => $mensaje,
                'temperature' => 0.1,
                'max_tokens' => 200,
            ]);

        if (!$response->successful()) {
            throw new \Exception("OpenRouter API error: " . $response->status() . " - " . $response->body());
        }

        $data = $response->json();

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new \Exception("Formato de respuesta inválido de OpenRouter");
        }

        $jsonContent = $data['choices'][0]['message']['content'];
        $jsonContent = $this->cleanAIResponse($jsonContent);
        $resultado = json_decode($jsonContent, true);

        if (!is_array($resultado)) {
            throw new \Exception("La IA no retornó un JSON válido: " . $jsonContent);
        }

        return $resultado;
    }

    private function buildAIPrompt($tablero, $etiquetas): string
    {
        return "
**INPUT:**

- **Nombre del tablero:** {$tablero}
- **Etiquetas:** {$etiquetas}

**INSTRUCCIONES:**

Analiza los datos y devuelve un JSON con las siguientes claves booleanas:

**REGLAS:**

1. **otrosProcesos**: `true` si las etiquetas incluyen 'no apto', 'apto para otros procesos', 'italiana', 'portuguesa', 'carta de naturaleza', 'MemDem', 'ley de nietos', o similares.

2. **pericial**: `true` si alguna etiqueta contiene 'Informe Pericial', 'Defensa Jurídica', 'peritaje', o similares.

3. **genealogiaAprobada**: `true` si alguna etiqueta contiene 'aprobado', 'aceptado', 'genealogía aprobada', o indica aprobación explícita de genealogía.

4. **genealogia**: `true` si `genealogiaAprobada` es `true` o si hay evidencia de proceso genealógico activo.

5. **investigacionProfunda**: `true` si hay una etiqueta con 'Investigación más profunda', 'investigación avanzada', 'análisis profundo', o similares.

6. **investigacionInSitu**: `true` si hay una etiqueta con 'Investigación in situ', 'investigación presencial', 'archivo físico', 'visita a archivo', o similares.

7. **analisisYCorreccion**: `true` si el tablero es 'Analisis preliminar' o 'Preanálisis'.

8. **investigacionIntuituPersonae**: `true` si el tablero es exactamente 'Análisis' pero NO 'Analisis preliminar'.

9. **inicioInvestigacion**: `true` si el tablero es 'Análisis' pero NO 'Analisis preliminar'.

**FORMATO DE SALIDA:**

{
    \"otrosProcesos\": false,
    \"pericial\": false,
    \"genealogiaAprobada\": false,
    \"genealogia\": false,
    \"inicioInvestigacion\": false,
    \"investigacionProfunda\": false,
    \"investigacionInSitu\": false,
    \"analisisYCorreccion\": false,
    \"investigacionIntuituPersonae\": false
}
";
    }

    private function cleanAIResponse($content): string
    {
        $content = preg_replace('/```json\s*/i', '', $content);
        $content = preg_replace('/```\s*/', '', $content);
        $content = trim($content);

        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $content = $matches[0];
        }

        return $content;
    }

    private function validateAndMergeAIResult($aiResult, $defaultResult): array
    {
        $validated = $defaultResult;

        foreach ($defaultResult as $key => $defaultValue) {
            if (isset($aiResult[$key])) {
                if (is_string($aiResult[$key])) {
                    $validated[$key] = in_array(strtolower($aiResult[$key]), ['true', '1', 'yes', 'si']);
                } else {
                    $validated[$key] = (bool) $aiResult[$key];
                }
            }
        }

        return $this->applyConsistencyRules($validated);
    }

    private function applyConsistencyRules($result): array
    {
        if ($result['otrosProcesos']) {
            $result['genealogia'] = false;
            $result['investigacionProfunda'] = false;
            $result['investigacionInSitu'] = false;
            $result['analisisYCorreccion'] = false;
            $result['investigacionIntuituPersonae'] = false;
            $result['inicioInvestigacion'] = false;
        }

        if ($result['genealogiaAprobada']) {
            $result['genealogia'] = true;
        }

        if ($result['investigacionProfunda'] || $result['investigacionInSitu']) {
            $result['analisisYCorreccion'] = false;
            $result['investigacionIntuituPersonae'] = false;
        }

        return $result;
    }

    private function fallbackManualAnalysis($mondaydataforAI): array
    {
        $resultado = [
            'otrosProcesos' => false,
            'pericial' => false,
            'genealogiaAprobada' => false,
            'genealogia' => false,
            'inicioInvestigacion' => false,
            'investigacionProfunda' => false,
            'investigacionInSitu' => false,
            'analisisYCorreccion' => false,
            'investigacionIntuituPersonae' => false
        ];

        $tablero = mb_strtolower($mondaydataforAI['tablero'] ?? '');
        $etiquetas = mb_strtolower($mondaydataforAI['etiquetas'] ?? '');
        $combinedText = $tablero . ' ' . $etiquetas;

        if (stripos($tablero, 'analisis preliminar') !== false || stripos($tablero, 'preanálisis') !== false) {
            $resultado['analisisYCorreccion'] = true;
        }

        if (preg_match('/^análisis$/i', $tablero) || preg_match('/^analisis$/i', $tablero)) {
            if (stripos($tablero, 'preliminar') === false && stripos($tablero, 'preanálisis') === false) {
                $resultado['investigacionIntuituPersonae'] = true;
                $resultado['inicioInvestigacion'] = true;
            }
        }

        if (stripos($combinedText, 'no apto') !== false
            || stripos($combinedText, 'otros procesos') !== false
            || stripos($combinedText, 'italiana') !== false
            || stripos($combinedText, 'portuguesa') !== false
            || stripos($combinedText, 'carta de naturaleza') !== false) {
            $resultado['otrosProcesos'] = true;
        }

        if (stripos($combinedText, 'pericial') !== false
            || stripos($combinedText, 'defensa jurídica') !== false) {
            $resultado['pericial'] = true;
        }

        if (stripos($combinedText, 'aprobado') !== false
            || stripos($combinedText, 'genealogía aprobada') !== false) {
            $resultado['genealogiaAprobada'] = true;
            $resultado['genealogia'] = true;
        }

        if (stripos($combinedText, 'investigación profunda') !== false
            || stripos($combinedText, 'investigacion profunda') !== false) {
            $resultado['investigacionProfunda'] = true;
        }

        if (stripos($combinedText, 'in situ') !== false
            || stripos($combinedText, 'investigación presencial') !== false) {
            $resultado['investigacionInSitu'] = true;
        }

        $resultado = $this->applyConsistencyRules($resultado);

        Log::info("COS IA Fallback: Análisis manual completado", [
            'tablero' => $tablero,
            'resultado' => $resultado
        ]);

        return $resultado;
    }

    // ============ CÁLCULO DE PROGRESO ============

    /**
     * Calcula los porcentajes de progreso para ambas fases
     */
    public function calculateProgress(array $status): array
    {
        $status['progressPercentageGen'] = $status['currentStepGen'] >= 0
            ? round(($status['currentStepGen'] / $this->totalStepsGen) * 100)
            : 0;

        $status['progressPercentageJur'] = $status['currentStepJur'] >= 0
            ? round(($status['currentStepJur'] / $this->totalStepsJur) * 100)
            : 0;

        return $status;
    }
}
