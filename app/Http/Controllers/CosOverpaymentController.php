<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CosOverpaymentReport;
use Illuminate\Http\Request;

class CosOverpaymentController extends Controller
{
    public function listing(Request $request)
    {
        abort_if($request->user()->hasRole('Cliente'), 403);
        $input = $request->validate(['q' => ['nullable', 'string', 'max:175']]);
        $q = trim($input['q'] ?? '');
        $clients = collect();
        if ($q !== '') {
            $query = User::role('Cliente')->where(function ($query) use ($q) {
                $query->where('name', 'like', '%'.$q.'%')->orWhere('email', $q)->orWhere('passport', $q);
                if (ctype_digit($q)) $query->orWhere('id', $q);
            });
            if (! $request->user()->hasRole('Administrador') && $request->user()->hasAnyRole(['Ventas', 'Coord. de Nacionalidad y Genealogía'])) {
                $query->where('owner_id', $request->user()->id);
            }
            $clients = app(\App\Services\ClientRestrictionService::class)->scopeVisible($query, $request->user())
                ->orderBy('name')->limit(20)->get(['id', 'name', 'email', 'passport']);
        }
        return response()->view('finance.payment-review-search', compact('q', 'clients'))->header('Cache-Control', 'private, no-store');
    }

    public function __invoke(Request $request, User $user, CosOverpaymentReport $reports)
    {
        $actor = $request->user();
        abort_if($actor->hasRole('Cliente'), 403);
        if (! $actor->hasRole('Administrador') && $actor->hasAnyRole(['Ventas', 'Coord. de Nacionalidad y Genealogía'])) {
            abort_unless((int) $user->owner_id === (int) $actor->id, 403);
        }
        app(\App\Services\ClientRestrictionService::class)->assertReadable($user, $actor);
        $report = $reports->get($user);
        return response()->view('finance.overpayments', compact('user', 'report'))->header('Cache-Control', 'private, no-store');
    }
}
