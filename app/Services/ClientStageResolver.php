<?php

namespace App\Services;

use App\Models\User;

class ClientStageResolver
{
    public function resolve(?User $user, array $context = []): array
    {
        if (! $user) {
            return $this->stage(
                'visitor',
                'visitor',
                'Visitante',
                'Explorar servicios disponibles',
                ['has_user' => false]
            );
        }

        $pay = $this->basePayStatus($user->pay ?? null);
        $contractSigned = (int) ($user->contrato ?? 0) === 1;
        $hasService = trim((string) ($user->servicio ?? '')) !== '';
        $hasCos = $this->hasCos($user, $context);

        if ($this->truthy($context['has_pending_purchase'] ?? false)) {
            return $this->stage(
                'candidate_activation_pending_payment',
                'candidate',
                'Activacion pendiente de pago',
                'Completar pago de activacion',
                compact('pay', 'contractSigned', 'hasService', 'hasCos')
            );
        }

        if ($pay === null || $pay === 0) {
            return $this->stage(
                'candidate_registered',
                'candidate',
                'Candidato registrado',
                'Activar analisis inicial',
                compact('pay', 'contractSigned', 'hasService', 'hasCos')
            );
        }

        if (in_array($pay, [1, 3], true)) {
            return $this->stage(
                'candidate_paid_pending_info',
                'candidate',
                'Pago completado, informacion pendiente',
                'Completar formulario de informacion',
                compact('pay', 'contractSigned', 'hasService', 'hasCos')
            );
        }

        if ($pay === 2 && ! $contractSigned) {
            return $this->stage(
                'candidate_pending_contract',
                'candidate',
                'Informacion completada, contrato pendiente',
                'Firmar contrato',
                compact('pay', 'contractSigned', 'hasService', 'hasCos')
            );
        }

        if ($pay === 2 && $contractSigned) {
            if ($this->truthy($context['has_pending_document'] ?? false)) {
                return $this->stage(
                    'represented_pending_document',
                    'represented',
                    'Representado con documento pendiente',
                    'Completar documentacion requerida',
                    compact('pay', 'contractSigned', 'hasService', 'hasCos')
                );
            }

            if ($this->truthy($context['has_pending_professional_activation'] ?? false)) {
                return $this->stage(
                    'represented_pending_professional_activation',
                    'represented',
                    'Servicio profesional pendiente de activacion',
                    'Formalizar activacion del servicio',
                    compact('pay', 'contractSigned', 'hasService', 'hasCos')
                );
            }

            if ($this->truthy($context['has_active_professional_service'] ?? false)) {
                return $this->stage(
                    'represented_service_active',
                    'represented',
                    'Servicio profesional activo',
                    'Consultar avance del servicio',
                    compact('pay', 'contractSigned', 'hasService', 'hasCos')
                );
            }

            if ($hasCos) {
                return $this->stage(
                    'represented_with_cos',
                    'represented',
                    'Representado con COS disponible',
                    'Consultar estatus del expediente',
                    compact('pay', 'contractSigned', 'hasService', 'hasCos')
                );
            }

            return $this->stage(
                'represented_initial',
                'represented',
                'Representado',
                'Consultar expediente',
                compact('pay', 'contractSigned', 'hasService', 'hasCos')
            );
        }

        return $this->stage(
            'candidate_registered',
            'candidate',
            'Candidato registrado',
            'Revisar estado de activacion',
            compact('pay', 'contractSigned', 'hasService', 'hasCos')
        );
    }

    public function isRepresented(?User $user, array $context = []): bool
    {
        return $this->resolve($user, $context)['profile'] === 'represented';
    }

    public function isCandidate(?User $user, array $context = []): bool
    {
        return $this->resolve($user, $context)['profile'] === 'candidate';
    }

    private function stage(string $stage, string $profile, string $label, string $nextAction, array $signals): array
    {
        return [
            'stage' => $stage,
            'profile' => $profile,
            'label' => $label,
            'next_action' => $nextAction,
            'is_candidate' => $profile === 'candidate',
            'is_transition' => $profile === 'transition',
            'is_represented' => $profile === 'represented',
            'is_visitor' => $profile === 'visitor',
            'signals' => $signals,
        ];
    }

    private function basePayStatus($pay): ?int
    {
        if ($pay === null || $pay === '') {
            return null;
        }

        $pay = (int) $pay;

        while ($pay >= 10) {
            $pay -= 10;
        }

        return $pay;
    }

    private function hasCos(User $user, array $context): bool
    {
        if ($this->truthy($context['has_cos'] ?? false)) {
            return true;
        }

        $arrayCos = $user->arraycos ?? null;

        return ((int) ($user->cosready ?? 0) === 1)
            || (is_array($arrayCos) && $arrayCos !== []);
    }

    private function truthy($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
