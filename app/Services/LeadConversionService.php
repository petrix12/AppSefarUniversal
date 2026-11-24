<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LeadConversionService
{
    /**
     * Marks matching leads as converted without deleting their history.
     * Multiple captures for the same person are closed together so a
     * registration cannot leave an active duplicate behind.
     */
    public function convertFor(User $user): int
    {
        if (! Schema::hasTable('leads')) {
            return 0;
        }

        $email = mb_strtolower(trim((string) $user->email));
        $passport = mb_strtolower(trim((string) $user->passport));

        if ($email === '' && $passport === '') {
            return 0;
        }

        $converted = DB::transaction(function () use ($user, $email, $passport): int {
            $leads = Lead::query()
                ->whereNull('converted_at')
                ->where(function ($query) use ($email, $passport): void {
                    if ($email !== '') {
                        $query->whereRaw('LOWER(email) = ?', [$email]);
                    }

                    if ($passport !== '') {
                        $method = $email !== '' ? 'orWhereRaw' : 'whereRaw';
                        $query->{$method}('LOWER(passport) = ?', [$passport]);
                    }
                })
                ->lockForUpdate()
                ->get();

            foreach ($leads as $lead) {
                $lead->forceFill([
                    'estado' => Lead::STATUS_CONVERTED,
                    'converted_user_id' => $user->id,
                    'converted_at' => now(),
                ])->save();
            }

            return $leads->count();
        });

        if ($user->hasRole('Cliente')) {
            app(HubspotOutboxService::class)->queue($user, array_keys($user->getAttributes()));
        }

        return $converted;
    }
}
