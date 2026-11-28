<?php

namespace App\Services;

use App\Models\EmailSuppression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EmailDeliveryPolicy
{
    public function normalize(string $email): string { return Str::lower(trim($email)); }

    public function blocked(string $email): bool
    {
        return Schema::hasTable('email_suppressions') && EmailSuppression::where('email', $this->normalize($email))->whereNull('cleared_at')->exists();
    }

    public function assertDeliverable(string $email): void
    {
        abort_if($this->blocked($email), 423, 'Correo suspendido por rebote permanente o queja por spam.');
    }

    public function filterRecipients(\Symfony\Component\Mime\Email $message): bool
    {
        $count = 0;
        foreach (['To', 'Cc', 'Bcc'] as $type) {
            $addresses = $message->{'get'.$type}();
            $allowed = array_values(array_filter($addresses, fn ($address) => ! $this->blocked($address->getAddress())));
            $count += count($allowed);
            if (count($allowed) === count($addresses)) continue;
            $message->getHeaders()->remove($type);
            if ($allowed) $message->{strtolower($type)}(...$allowed);
        }
        return $count > 0;
    }

    public function recordSesEvent(array $event, array $allowedRecipients): void
    {
        if (! Schema::hasTable('email_suppressions')) return;
        $type = strtolower((string) ($event['eventType'] ?? $event['notificationType'] ?? ''));
        if ($type === 'bounce' && data_get($event, 'bounce.bounceType') === 'Permanent') {
            $reason = 'hard_bounce'; $recipients = data_get($event, 'bounce.bouncedRecipients', []);
        } elseif ($type === 'complaint') {
            $reason = 'complaint'; $recipients = data_get($event, 'complaint.complainedRecipients', []);
        } else return;
        $allowed = array_map(fn ($email) => $this->normalize($email), $allowedRecipients);
        try { $at = Carbon::parse(data_get($event, $type.'.timestamp') ?: data_get($event, 'mail.timestamp')); }
        catch (\Throwable) { $at = now(); }
        foreach (is_array($recipients) ? $recipients : [] as $recipient) {
            $email = $this->normalize((string) ($recipient['emailAddress'] ?? ''));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! in_array($email, $allowed, true)) continue;
            DB::transaction(function () use ($email, $reason, $event, $at) {
                $existing = EmailSuppression::where('email', $email)->lockForUpdate()->first();
                // An old event must not undo a later, deliberate review.
                if ($existing?->cleared_at && $existing->cleared_at->gte($at)) return;
                EmailSuppression::updateOrCreate(['email' => $email], [
                    'reason' => $reason, 'source_reference' => data_get($event, 'mail.messageId'),
                    'blocked_at' => $at, 'cleared_at' => null, 'cleared_by' => null,
                ]);
            });
        }
    }
}
