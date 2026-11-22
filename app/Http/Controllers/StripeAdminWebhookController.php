<?php
namespace App\Http\Controllers;

use App\Services\{PlatformCatalog, PlatformEventProcessor, StripeAdminApi};
use Illuminate\Http\Request;

class StripeAdminWebhookController extends Controller
{
    public function __invoke(Request $request, string $account, PlatformCatalog $catalog, StripeAdminApi $api)
    {
        abort_unless($account==='main',404);
        $secret=(string) config('services.stripe.admin_webhook_secret');
        if ($secret==='') return response()->json(['received'=>false],503);
        if (strlen($request->getContent())>1048576) return response()->json(['received'=>false],413);
        try { $event=\Stripe\Webhook::constructEvent($request->getContent(),(string)$request->header('Stripe-Signature'),$secret)->toArray(); }
        catch (\Throwable) { return response()->json(['received'=>false],400); }
        if (!empty($event['account'])) return response()->json(['received'=>true,'ignored'=>'connected_account']);
        if ($api->mode($account)===null) return response()->json(['received'=>false],503);
        if (($event['livemode']??null)!==$api->mode($account)) return response()->json(['received'=>true,'ignored'=>'mode']);
        $type=(string)($event['type']??''); $resource=$catalog->stripeEvent($type); $data=$event['data']['object']??[];
        if (!$resource || !in_array($type,PlatformCatalog::STRIPE_EVENTS,true)) return response()->json(['received'=>true,'ignored'=>'event_type']);
        if (!$catalog->validId('stripe',$resource,$data['id']??null)
            || !is_string($event['id']??null) || strlen($event['id'])>190 || !preg_match('/^evt_[A-Za-z0-9]+$/D',$event['id']) || strlen($type)>100) {
            return response()->json(['received'=>false],422);
        }
        if (!\Illuminate\Support\Facades\Schema::hasTable('platform_events')) return response()->json(['received'=>false],503);
        $created=PlatformEventProcessor::receive('stripe',$account,$event['id'],$type,$resource,$data['id']);
        // The administrative mirror never grants services, creates App receipts or charges a card.
        return response()->json(['received'=>true,'duplicate'=>!$created],$created ? 202 : 200)->header('Cache-Control','no-store');
    }
}
