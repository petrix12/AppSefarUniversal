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
        if (! app(\App\Services\EmailDeliveryPolicy::class)->filterRecipients($event->message)) {
            Log::notice('Correo suprimido: no quedan destinatarios con una dirección entregable.');
            return false;
        }
        foreach ([...$event->message->getTo(), ...$event->message->getCc(), ...$event->message->getBcc()] as $address) {
            try {
                app(ClientRestrictionService::class)->assertEmailDeliverable($address->getAddress());
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                Log::notice('Correo suprimido por una restricción de entrega.', ['reason' => 'delivery_restricted']);
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
