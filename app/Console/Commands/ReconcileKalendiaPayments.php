<?php
namespace App\Console\Commands;
use App\Models\KalendiaConsultationOrder;
use App\Services\KalendiaConsultationCheckout;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
class ReconcileKalendiaPayments extends Command
{
    protected $signature = 'kalendia:reconcile-payments';
    protected $description = 'Verifica pagos pendientes con Stripe sin realizar nuevos cobros';
    public function handle(KalendiaConsultationCheckout $checkout): int
    {
        if (! Schema::hasTable('kalendia_consultation_orders')) return self::SUCCESS;
        $orders = KalendiaConsultationOrder::whereNotNull('payment_intent_id')->where('status', '!=', 'paid')
            ->where('created_at', '>=', now()->subDays(30))->oldest('updated_at')->limit(10)->get();
        foreach ($orders as $order) {
            try { $checkout->verify($order); } catch (\Throwable) { $this->warn('Pago pendiente de revisión: '.$order->id); }
            $order->refresh()->touch();
        }
        return self::SUCCESS;
    }
}
