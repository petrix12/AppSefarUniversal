<?php

namespace App\Http\Controllers;

use App\Services\{PlatformApiException, PlatformIdentity, StripeAdminApi, StripeAdminWorkspace};
use Illuminate\Http\Request;

class StripeAdminWorkspaceController extends Controller
{
    public function __construct()
    {
        $this->middleware(function (Request $request, \Closure $next) {
            $user = $request->user();
            abort_unless($user?->hasRole('Administrador') && !$user->hasRole('Cliente'), 403);

            return $next($request);
        });
    }

    public function workspace(Request $request, StripeAdminWorkspace $stripe, StripeAdminApi $api)
    {
        $filters = $request->validate([
            'account' => ['nullable', 'in:main,spain_legacy'],
            'dataset' => ['nullable', 'in:events,charges,payment_intents,invoices,refunds,disputes'],
            'category' => ['nullable', 'in:all,failures,payments,refunds,disputes'],
            'q' => ['nullable', 'regex:/^(ch|pi|in|re|du|dp|evt|cus)_[A-Za-z0-9_-]+$/D', 'max:184'],
            'status' => ['nullable', 'regex:/^[a-z_]+$/D', 'max:80'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => array_merge(['nullable', 'date_format:Y-m-d'], $request->filled('date_from') ? ['after_or_equal:date_from'] : []),
            'starting_after' => ['nullable', 'regex:/^(ch|pi|in|re|du|dp|evt)_[A-Za-z0-9_-]+$/D', 'max:184'],
        ]);

        $account = $filters['account'] ?? 'main';
        $dataset = $stripe->datasetFor($filters['dataset'] ?? 'charges', $filters['q'] ?? null);
        $category = $filters['category'] ?? 'failures';
        $query = array_replace($filters, compact('account', 'dataset', 'category'));
        $apiError = null;
        $result = ['rows' => [], 'next_cursor' => null];

        try {
            $result = $stripe->query($account, $query);
        } catch (PlatformApiException $e) {
            $apiError = $e->errorCode;
        }

        return response()->view('platforms.stripe-workspace', [
            'provider' => 'stripe',
            'account' => $account,
            'readOnly' => PlatformIdentity::readOnly('stripe', $account),
            'platformName' => 'Stripe',
            'singleWorkspace' => true,
            'dataset' => $dataset,
            'category' => $category,
            'liveMode' => $api->mode($account),
            'filters' => $query,
            'rows' => $result['rows'],
            'nextCursor' => $result['next_cursor'],
            'listLabel' => $stripe->label($dataset),
            'statuses' => $stripe->statuses($dataset),
            'apiError' => $apiError,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function detail(string $account, string $id, StripeAdminWorkspace $stripe)
    {
        try {
            $row = $stripe->retrieve($account, $id);
        } catch (PlatformApiException $e) {
            return response()->json(['message' => $e->httpStatus === 404
                ? 'Stripe no encontró este registro en la cuenta seleccionada.'
                : 'No se pudo consultar el detalle en Stripe.'], $e->httpStatus === 404 ? 404 : 422)
                ->header('Cache-Control', 'private, no-store');
        }

        return response()->json(['html' => view('platforms.partials.stripe-live-detail', [
            'row' => $row,
            'account' => $account,
            'canOperate' => $account === 'main' && request()->user()->isSuperadmin(),
        ])->render()])->header('Cache-Control', 'private, no-store');
    }

    public function legacyAudit(Request $request, string $account)
    {
        return redirect()->route('admin.stripe.workspace', [
            'account' => $account,
            'dataset' => 'events',
            'category' => $request->query('category', 'failures'),
        ]);
    }
}
