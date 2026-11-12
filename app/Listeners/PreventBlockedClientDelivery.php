<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\ClientRestrictionService;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Log;

class PreventBlockedClientDelivery
{
    public function mail(MessageSending $event): ?bool
    {
        foreach ([...$event->message->getTo(), ...$event->message->getCc(), ...$event->message->getBcc()] as $address) {
            try {
                app(ClientRestrictionService::class)->assertEmailDeliverable($address->getAddress());
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                Log::notice('Correo suprimido por bloqueo de cliente', ['reason' => 'client_restricted']);
                return false;
            }
        }
        return null;
    }

    public function notification(NotificationSending $event): ?bool
    {
        return $event->notifiable instanceof User && app(ClientRestrictionService::class)->isRestricted($event->notifiable) ? false : null;
    }
}
