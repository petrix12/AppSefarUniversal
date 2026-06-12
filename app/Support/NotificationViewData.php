<?php

namespace App\Support;

use App\Notifications\UnclosedTasksNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class NotificationViewData
{
    public static function normalize(DatabaseNotification $notification): array
    {
        $data = $notification->data ?? [];
        $type = (string) $notification->type;

        if ($type === UnclosedTasksNotification::class) {
            return self::normalizeUnclosedTasks($data);
        }

        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        $actionUrl = $data['action_url'] ?? null;

        return [
            'title' => $title !== '' ? $title : self::fallbackTitle($type),
            'body' => $body,
            'category' => $data['category'] ?? 'general',
            'action_url' => filled($actionUrl) ? $actionUrl : null,
            'action_text' => $data['action_text'] ?? (filled($actionUrl) ? 'Ver detalle' : 'Marcar como leida'),
        ];
    }

    private static function normalizeUnclosedTasks(array $data): array
    {
        $total = (int) ($data['total'] ?? 0);
        $date = (string) ($data['date'] ?? '');
        $byAdvisor = collect($data['by_advisor'] ?? [])
            ->map(function ($row) {
                $advisor = trim((string) ($row['advisor'] ?? 'Asesor'));
                $count = (int) ($row['count'] ?? 0);

                return "{$advisor}: {$count}";
            })
            ->filter()
            ->values();

        $body = $date !== ''
            ? "Resumen del {$date}."
            : 'Resumen de tareas sin cerrar.';

        if ($byAdvisor->isNotEmpty()) {
            $body .= ' ' . Str::limit($byAdvisor->implode(' | '), 220);
        }

        return [
            'title' => "{$total} tarea(s) sin cerrar",
            'body' => $body,
            'category' => 'tasks',
            'action_url' => Route::has('tasks.admin.summary')
                ? route('tasks.admin.summary', array_filter(['date' => $date ?: null]))
                : null,
            'action_text' => 'Ver panel de tareas',
        ];
    }

    private static function fallbackTitle(string $type): string
    {
        $shortType = class_basename($type);

        return $shortType !== ''
            ? Str::headline($shortType)
            : 'Notificacion';
    }
}
