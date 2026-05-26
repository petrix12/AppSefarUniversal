<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ClientAppNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly ?string $actionUrl = null,
        private readonly ?string $actionText = null,
        private readonly string $category = 'general',
        private readonly bool $sendEmail = false,
        private readonly bool $storeInApp = true,
    ) {}

    public function via($notifiable): array
    {
        $channels = [];

        if ($this->storeInApp && $this->notificationsTableExists()) {
            $channels[] = 'database';
        }

        if ($this->sendEmail && filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->actionUrl,
            'action_text' => $this->actionText,
            'category' => $this->category,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Hola ' . trim((string) ($notifiable->nombres ?: $notifiable->name)) . ',')
            ->line($this->body);

        if ($this->actionUrl) {
            $mail->action($this->actionText ?: 'Ver en la app', $this->actionUrl);
        }

        return $mail->line('Tambien puedes revisar esta notificacion dentro de tu cuenta.');
    }

    private function notificationsTableExists(): bool
    {
        try {
            return Schema::hasTable('notifications');
        } catch (Throwable) {
            return false;
        }
    }
}
