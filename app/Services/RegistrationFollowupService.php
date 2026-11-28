<?php

namespace App\Services;

use App\Mail\RegistrationPaymentReminder;
use App\Models\{RegistrationFollowup, RegistrationFollowupState, User};
use Carbon\Carbon;
use Illuminate\Support\Facades\{DB, Mail, Schema};

class RegistrationFollowupService
{
    public function stopForResponse(User $user, ?int $actorId = null): void
    {
        RegistrationFollowupState::updateOrCreate(['user_id' => $user->id], [
            'responded_at' => now(), 'stopped_at' => now(), 'stop_reason' => 'responded', 'updated_by' => $actorId,
        ]);
    }

    public function eligible(User $user): bool
    {
        if ((int) $user->pay !== 0 || ! $user->hasRole('Cliente') || blank($user->email)
            || app(EmailDeliveryPolicy::class)->blocked($user->email)
            || $user->isClientAppAccessBlocked()) return false;
        $state = RegistrationFollowupState::where('user_id', $user->id)->first();
        if ($state?->stopped_at) return false;
        if (Schema::hasColumn('tasks', 'customer_responded') && DB::table('tasks')->where('contact_id', $user->id)
            ->where('customer_responded', true)->where('updated_at', '>=', $user->created_at)->exists()) {
            return false;
        }
        return $this->attemptCount($user) < 3;
    }

    private function attemptCount(User $user): int
    {
        return RegistrationFollowup::where('user_id', $user->id)->where(function ($query) {
            $query->where('status', 'sending')->orWhere(function ($sent) {
                $sent->where('status', 'sent')->whereNotNull('sent_at');
            });
        })->count();
    }

    public function send(User $user, Carbon $scheduledFor): bool
    {
        RegistrationFollowupState::firstOrCreate(['user_id' => $user->id]);
        $followup = DB::transaction(function () use ($user, $scheduledFor) {
            $state = RegistrationFollowupState::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $fresh = $user->fresh();
            if (! $fresh || ! $this->eligible($fresh)) return null;
            $attempts = $this->attemptCount($fresh);
            if ($attempts >= 3 || RegistrationFollowup::where('user_id', $user->id)->whereDate('scheduled_for', $scheduledFor)->exists()) return null;
            $sequence = $attempts + 1;
            return RegistrationFollowup::create([
                'user_id' => $user->id, 'sequence' => $sequence, 'scheduled_for' => $scheduledFor->toDateString(),
                'status' => 'sending', 'subject' => [
                    1 => 'Tu registro en Sefar: pago pendiente',
                    2 => 'Recuerda completar el pago de tu registro',
                    3 => 'Último recordatorio de tu pago de registro',
                ][$sequence],
            ]);
        });
        if (! $followup) return false;
        try {
            $sent = Mail::to($user->email)->send(new RegistrationPaymentReminder(
                fullName: trim(($user->nombres ?? '').' '.($user->apellidos ?? '')) ?: $user->name ?: 'Representado',
                sequence: $followup->sequence, subjectLine: $followup->subject,
                paymentUrl: rtrim(config('app.url'), '/'), logoUrl: rtrim(config('app.url'), '/').'/img/logo2.png', videoUrl: ''
            ));
        } catch (\Throwable) {
            $followup->update(['status' => 'failed']);
            return false;
        }
        if (! $sent) { $followup->update(['status' => 'skipped']); return false; }
        $followup->update(['status' => 'sent', 'sent_at' => now()]);
        if (RegistrationFollowup::where('user_id', $user->id)->where('status', 'sent')->whereNotNull('sent_at')->count() >= 3) {
            RegistrationFollowupState::where('user_id', $user->id)->whereNull('stopped_at')
                ->update(['stop_reason' => 'limit_reached', 'stopped_at' => now(), 'updated_at' => now()]);
        }
        return true;
    }
}
