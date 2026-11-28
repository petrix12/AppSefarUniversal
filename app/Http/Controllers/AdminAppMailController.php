<?php

namespace App\Http\Controllers;

use App\Models\AppMailLog;
use App\Models\{EmailSuppression, RegistrationFollowupState, User};
use App\Services\AppMailSettings;
use Illuminate\Http\Request;
use Illuminate\Mail\MailManager;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;

class AdminAppMailController extends Controller
{
    public function show(Request $request, AppMailSettings $settings)
    {
        $data = $request->validate(['recipient' => ['nullable', 'string', 'max:254']]);
        $ready = $settings->ready();
        $ses = $settings->ses();
        return view('admin.integrations.app-mail', [
            'provider' => $settings->provider(), 'originalMailer' => $settings->originalMailer(),
            'schemaReady' => $ready, 'sesReady' => $settings->sesReady(), 'testProof' => $settings->tested(),
            'sesFrom' => $ses['from_email'] ?? '', 'sesRegion' => $ses['region'] ?? '',
            'eventsConfigured' => filled($ses['configuration_set'] ?? null) && filled(config('app_mail.ses_sns_topic_arns')),
            'suppressions' => Schema::hasTable('email_suppressions') ? EmailSuppression::whereNull('cleared_at')
                ->when(filled($data['recipient'] ?? null), fn ($query) => $query->where('email', 'like', '%'.addcslashes($data['recipient'], '%_\\').'%'))
                ->latest('blocked_at')->limit(50)->get() : collect(),
            'followupStates' => Schema::hasTable('registration_followup_states') ? RegistrationFollowupState::with('user:id,name,email')
                ->whereNotNull('stopped_at')->when(filled($data['recipient'] ?? null), fn ($query) => $query->whereHas('user',
                    fn ($users) => $users->where('email', 'like', '%'.addcslashes($data['recipient'], '%_\\').'%')))
                ->latest('stopped_at')->limit(50)->get() : collect(),
            'logs' => $ready ? AppMailLog::query()->when(filled($data['recipient'] ?? null),
                fn ($query) => $query->where('recipients', 'like', '%'.addcslashes($data['recipient'], '%_\\').'%'))
                ->latest('id')->paginate(25)->withQueryString() : null,
        ]);
    }

    public function update(Request $request, AppMailSettings $settings)
    {
        $data = $request->validate(['provider' => ['required', Rule::in(['default', 'ses'])]]);
        if ($data['provider'] === 'ses' && $settings->provider() !== 'ses') {
            $request->validate(['received_test' => ['accepted']]);
        }
        if ($data['provider'] !== $settings->provider()) $settings->activate($data['provider'], $request->user()->id);
        return redirect()->route('admin.integrations.app-mail.index')->with('success', 'Proveedor de correo de la app actualizado.');
    }

    public function test(Request $request, AppMailSettings $settings, MailManager $manager)
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(['default', 'ses'])],
            'recipient' => ['required', 'email:rfc', 'max:254'],
        ]);
        if (! $settings->ready()) throw ValidationException::withMessages(['provider' => 'Primero aplica la migración de correo de la app.']);
        if ($data['provider'] === 'ses' && ! $settings->sesReady()) {
            throw ValidationException::withMessages(['provider' => 'Completa la configuración de Amazon SES en el servidor antes de probarlo.']);
        }
        $from = $data['provider'] === 'ses' ? $settings->ses()['from_email'] : config('mail.from.address');
        $mailer = $manager->build(['transport' => 'app-mail', 'provider_override' => $data['provider'], 'is_test' => true]);
        $mailer->alwaysFrom($from, config('mail.from.name'));
        try {
            $sent = $mailer->raw("Este es un correo de prueba de App Sefar Universal.\n\nComprueba si lo recibes en la bandeja de entrada o en spam antes de activar el proveedor.",
                fn ($message) => $message->to($data['recipient'])->subject('Prueba de correo · App Sefar Universal'));
            $log = $mailer->getSymfonyTransport()->lastLog?->fresh();
            if (! $sent || ! $log || ! in_array($log->status, ['accepted', 'delivered'], true)) throw new \RuntimeException('Prueba sin confirmar.');
        } catch (\Throwable) {
            throw ValidationException::withMessages(['test' => 'No se pudo completar la prueba. Revisa el estado del envío y la configuración del proveedor.']);
        }
        if ($data['provider'] === 'ses') $settings->recordTest($log);
        return redirect()->route('admin.integrations.app-mail.index')->with('success',
            'El proveedor aceptó el correo de prueba. Comprueba su recepción; el proveedor activo no ha cambiado.');
    }

    public function clearSuppression(Request $request, EmailSuppression $suppression)
    {
        $suppression->update(['cleared_at' => now(), 'cleared_by' => $request->user()->id]);
        return back()->with('success', 'Bloqueo de correo levantado. Los próximos envíos volverán a comprobar la dirección.');
    }

    public function recordResponse(Request $request, User $user, \App\Services\RegistrationFollowupService $followups)
    {
        abort_unless($user->hasRole('Cliente'), 404);
        $followups->stopForResponse($user, $request->user()->id);
        return back()->with('success', 'Respuesta registrada. Se detuvieron los recordatorios de pago de este representado.');
    }
}
