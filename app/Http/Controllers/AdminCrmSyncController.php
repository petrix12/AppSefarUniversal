<?php

namespace App\Http\Controllers;

use App\Models\CrmContactSnapshot;
use App\Models\CrmWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminCrmSyncController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()->hasRole('Administrador') && ! $request->user()->hasRole('Cliente'), 403);
        $installed = Schema::hasTable('crm_webhook_events') && Schema::hasTable('crm_contact_snapshots');
        $counts = $installed ? CrmContactSnapshot::selectRaw('provider, classification, count(*) as total')->groupBy('provider', 'classification')->get() : collect();
        $events = $installed ? CrmWebhookEvent::orderByDesc('id')->limit(30)->get(['id', 'provider', 'status', 'attempts', 'error_class', 'created_at', 'processed_at']) : collect();
        return response()->view('admin.crm-sync', compact('installed', 'counts', 'events'))->header('Cache-Control', 'private, no-store');
    }
}
