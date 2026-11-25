<?php

namespace App\Http\Controllers;

use App\Services\{PlatformCatalog, PlatformIdentity, PlatformMirror, StripePaymentAudit};
use Illuminate\Http\Request;

class StripePaymentAuditController extends Controller
{
    public function index(Request $request, string $account, StripePaymentAudit $audit)
    {
        abort_unless(PlatformIdentity::supported('stripe', $account), 404);

        $filters = $request->validate([
            'category' => ['nullable', 'in:all,failures,payments,refunds,disputes'],
            'starting_after' => ['nullable', 'regex:/^evt_[A-Za-z0-9]+$/D', 'max:190'],
        ]);
        $category = $filters['category'] ?? 'failures';
        $auditError = null;
        $events = [];
        $hasMore = false;

        try {
            $result = $audit->list($account, $category, $filters['starting_after'] ?? null);
            $events = $result['events'];
            $hasMore = $result['has_more'];
        } catch (\Throwable $e) {
            $auditError = PlatformMirror::errorCode($e);
        }

        return response()->view('platforms.payment-audit', [
            'provider' => 'stripe',
            'account' => $account,
            'readOnly' => PlatformIdentity::readOnly('stripe', $account),
            'resources' => app(PlatformCatalog::class)->resources('stripe'),
            'platformName' => 'Stripe',
            'events' => $events,
            'hasMore' => $hasMore,
            'category' => $category,
            'auditError' => $auditError,
        ])->header('Cache-Control', 'private, no-store');
    }
}
