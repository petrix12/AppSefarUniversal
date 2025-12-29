<?php

namespace App\Console\Commands;

use App\Mail\RegistrationPaymentReminder;
use App\Models\RegistrationFollowup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendRegistrationPaymentFollowups extends Command
{
    protected $signature = 'followups:registration-payment {--dry-run}';
    protected $description = 'Envía correos cada 15 días desde created_at a clientes (Spatie) con pay=0';

    public function handle(): int
    {
        $today = Carbon::today();

        $users = User::query()
            ->whereDate('created_at', '>=', '2024-01-01')
            ->where('pay', 0)
            ->role('cliente') // ✅ Spatie
            ->select(['id', 'email', 'nombres', 'apellidos', 'created_at'])
            ->get();

        $queued = 0;
        $skipped = 0;

        foreach ($users as $user) {
            if (empty($user->email)) { $skipped++; continue; }

            $created = Carbon::parse($user->created_at)->startOfDay();
            if ($created->gt($today)) { $skipped++; continue; }

            $daysSince = $created->diffInDays($today);

            // Día 0, 15, 30, 45...
            if ($daysSince % 15 !== 0) { $skipped++; continue; }

            $sequence = intdiv($daysSince, 15) + 1;
            $scheduledFor = $created->copy()->addDays(($sequence - 1) * 15)->toDateString();

            // Anti-duplicado (si ya se envió/registró para ese día)
            $exists = RegistrationFollowup::query()
                ->where('user_id', $user->id)
                ->whereDate('scheduled_for', $scheduledFor)
                ->exists();

            if ($exists) { $skipped++; continue; }

            $fullName = trim(($user->nombres ?? '').' '.($user->apellidos ?? ''));
            $subject = $this->makeUniqueSubject($sequence, $user->id, $scheduledFor);

            if ($this->option('dry-run')) {
                $this->line("[DRY] {$user->email} | {$scheduledFor} | seq {$sequence} | {$subject}");
                $queued++;
                continue;
            }

            $logoUrl = config('app.sefar_logo_email', 'https://www.sefaruniversal.com/assets/email/logo.png');
            $paymentUrl = 'https://app.sefaruniversal.com';

            Mail::to($user->email)->queue(
                new RegistrationPaymentReminder(
                    fullName: ($fullName !== '' ? $fullName : 'Cliente'),
                    sequence: $sequence,
                    subjectLine: $subject,
                    paymentUrl: $paymentUrl
                )
            );

            RegistrationFollowup::create([
                'user_id' => $user->id,
                'sequence' => $sequence,
                'scheduled_for' => $scheduledFor,
                'sent_at' => now(),
                'subject' => $subject,
            ]);

            $queued++;
        }

        $this->info("Listo. Encolados: {$queued}. Saltados: {$skipped}.");

        return self::SUCCESS;
    }

    private function makeUniqueSubject(int $sequence, int $userId, string $scheduledFor): string
    {
        $templates = [
            'Completa tu registro: pago pendiente',
            'Tu registro está en pausa: finaliza el pago',
            'Último paso para activar tu registro',
            'Recordatorio: pago de registro pendiente',
            'Activa tu registro hoy: completa el pago',
            'Evita retrasos: finaliza tu pago de registro',
            'Sefar Universal: tu registro sigue pendiente',
            '¿Necesitas ayuda para pagar tu registro?',
        ];

        $base = $templates[($sequence - 1) % count($templates)];
        $token = strtoupper(Str::random(4));

        return "{$base} | #{$sequence}-{$scheduledFor}-U{$userId}-{$token}";
    }
}
