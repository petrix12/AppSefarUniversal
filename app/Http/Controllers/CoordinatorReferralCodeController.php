<?php

namespace App\Http\Controllers;

use App\Models\CoordinatorReferralCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CoordinatorReferralCodeController extends Controller
{
    public function index()
    {
        $coordinators = $this->coordinators();
        $tablesReady = $this->tablesReady();

        $codes = $tablesReady
            ? CoordinatorReferralCode::with('coordinator')
                ->withCount('sales')
                ->withSum('sales as sales_amount', 'amount')
                ->orderBy('code')
                ->get()
            : collect();

        $codedCoordinatorIds = $codes->pluck('coordinator_user_id')->filter()->unique();
        $coordinatorsWithoutCode = $coordinators
            ->filter(fn (User $coordinator) => ! $codedCoordinatorIds->contains($coordinator->id))
            ->count();
        $coordinatorsWithoutEmail = $coordinators
            ->filter(fn (User $coordinator) => blank($coordinator->email))
            ->count();

        return view('admin.referral_codes.index', compact('codes', 'coordinatorsWithoutCode', 'coordinatorsWithoutEmail', 'tablesReady'));
    }

    public function sync()
    {
        if (! $this->tablesReady()) {
            return redirect()
                ->route('admin.referral-codes.index')
                ->with('warning', 'Las tablas de referidos aun no estan migradas.');
        }

        $created = $this->ensureCodesForCoordinators();

        return redirect()
            ->route('admin.referral-codes.index')
            ->with('success', "Codigos generados: {$created}.");
    }

    public function sendAll()
    {
        if (! $this->tablesReady()) {
            return redirect()
                ->route('admin.referral-codes.index')
                ->with('warning', 'Las tablas de referidos aun no estan migradas.');
        }

        $this->ensureCodesForCoordinators();

        $sent = 0;
        $failed = 0;

        $codes = CoordinatorReferralCode::with('coordinator')
            ->where('active', true)
            ->get();

        foreach ($codes as $code) {
            $coordinator = $code->coordinator;

            if (! $coordinator || blank($coordinator->email)) {
                $failed++;
                continue;
            }

            try {
                Mail::raw($this->emailBody($coordinator, $code), function ($message) use ($coordinator) {
                    $message
                        ->to($coordinator->email)
                        ->subject('Tu codigo de referido - Sefar Universal');
                });

                $code->forceFill(['last_sent_at' => now()])->save();
                $sent++;
            } catch (\Throwable $exception) {
                $failed++;
                Log::error('No se pudo enviar codigo de referido', [
                    'coordinator_user_id' => $coordinator->id,
                    'email' => $coordinator->email,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $message = "Codigos enviados: {$sent}.";

        if ($failed > 0) {
            $message .= " No enviados: {$failed}.";
        }

        return redirect()
            ->route('admin.referral-codes.index')
            ->with($failed > 0 ? 'warning' : 'success', $message);
    }

    public function validateCode(Request $request)
    {
        $normalized = $this->normalizeCode($request->query('code'));

        if (! $normalized) {
            return response()->json(['valid' => true]);
        }

        if (! $this->tablesReady()) {
            return response()->json([
                'valid' => false,
                'message' => 'Las tablas de referidos aun no estan migradas.',
            ], 422);
        }

        $exists = CoordinatorReferralCode::query()
            ->where('code', $normalized)
            ->where('active', true)
            ->exists();

        return response()->json([
            'valid' => $exists,
            'message' => $exists ? null : 'El codigo de referido no existe o no esta activo.',
        ], $exists ? 200 : 422);
    }

    private function ensureCodesForCoordinators(): int
    {
        $created = 0;

        foreach ($this->coordinators() as $coordinator) {
            $alreadyExists = CoordinatorReferralCode::where('coordinator_user_id', $coordinator->id)->exists();

            if ($alreadyExists) {
                continue;
            }

            CoordinatorReferralCode::create([
                'coordinator_user_id' => $coordinator->id,
                'code' => $this->generateCode($coordinator),
                'active' => true,
            ]);

            $created++;
        }

        return $created;
    }

    private function tablesReady(): bool
    {
        return Schema::hasTable('coordinator_referral_codes')
            && Schema::hasTable('referral_sales');
    }

    private function coordinators()
    {
        return User::whereHas('roles', function ($query) {
                $query->where('name', 'LIKE', 'Coord. de Nacionalidad%');
            })
            ->orderBy('name')
            ->get();
    }

    private function generateCode(User $coordinator): string
    {
        $name = Str::ascii($coordinator->name ?: $coordinator->email ?: 'COORD');
        $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper($name)) ?: 'COORD';
        $prefix = substr($prefix, 0, 4);
        $base = 'SEF' . str_pad((string) $coordinator->id, 4, '0', STR_PAD_LEFT) . $prefix;
        $code = $base;
        $suffix = 1;

        while (CoordinatorReferralCode::where('code', $code)->exists()) {
            $code = $base . $suffix;
            $suffix++;
        }

        return $code;
    }

    private function normalizeCode(?string $code): ?string
    {
        $normalized = strtoupper(trim((string) $code));
        $normalized = preg_replace('/\s+/', '', $normalized);

        return $normalized !== '' ? $normalized : null;
    }

    private function emailBody(User $coordinator, CoordinatorReferralCode $code): string
    {
        $name = $coordinator->name ?: 'Coordinador';

        return "Hola {$name},\n\n"
            . "Este es tu codigo de referido para clientes de Sefar Universal:\n\n"
            . "{$code->code}\n\n"
            . "Cuando un cliente use este codigo en el formulario de pago, la compra quedara asociada a tus comisiones.\n\n"
            . "Saludos,\nSefar Universal";
    }
}
