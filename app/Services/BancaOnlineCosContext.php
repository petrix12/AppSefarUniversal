<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BancaOnlineCosContext
{
    public function forUser(?User $user): array
    {
        if (! $user) {
            return $this->emptyContext();
        }

        $items = collect($user->arraycos ?? [])
            ->filter(fn ($item) => is_array($item) && ! empty($item['servicio']))
            ->values();

        $entries = $items
            ->map(fn (array $item) => $this->publicEntry($item))
            ->filter()
            ->take(3)
            ->values();

        $expiresAt = $user->arraycos_expire instanceof Carbon ? $user->arraycos_expire : null;
        $available = ((int) ($user->cosready ?? 0) === 1) || $entries->isNotEmpty();

        return [
            'visible' => $available,
            'available' => $available,
            'fresh' => $expiresAt ? $expiresAt->isFuture() : false,
            'expires_at' => $expiresAt,
            'entries' => $entries->all(),
            'summary' => $this->summary($entries, $available),
        ];
    }

    private function publicEntry(array $item): ?array
    {
        $service = trim((string) ($item['servicio'] ?? ''));

        if ($service === '') {
            return null;
        }

        return [
            'service' => $service,
            'current_step' => $this->cleanText($item['currentStepName'] ?? null) ?: 'Estatus en revision',
            'progress_genealogic' => $this->percentage($item['progressPercentageGen'] ?? null),
            'progress_legal' => $this->percentage($item['progressPercentageJur'] ?? null),
            'has_public_detail' => ! empty($item['currentStepDetails']['promesa']),
        ];
    }

    private function summary(Collection $entries, bool $available): string
    {
        if (! $available) {
            return 'Aun no hay un COS publicado para este cliente.';
        }

        if ($entries->isEmpty()) {
            return 'El cliente tiene COS disponible, pero el detalle debe actualizarse desde el expediente.';
        }

        $first = $entries->first();

        return 'Proceso activo: ' . $first['service'] . '. Estatus: ' . $first['current_step'] . '.';
    }

    private function cleanText($value): ?string
    {
        $text = trim(strip_tags((string) $value));

        return $text !== '' ? $text : null;
    }

    private function percentage($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, min(100, (int) round((float) $value)));
    }

    private function emptyContext(): array
    {
        return [
            'visible' => false,
            'available' => false,
            'fresh' => false,
            'expires_at' => null,
            'entries' => [],
            'summary' => 'Sin usuario autenticado.',
        ];
    }
}
