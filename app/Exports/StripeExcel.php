<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Stripe;

class StripeExcel implements FromCollection, WithHeadings
{
    use Exportable;

    protected $startOfMonth;
    protected $endOfMonth;

    public function __construct($startOfMonth, $endOfMonth)
    {
        $this->startOfMonth = $startOfMonth;
        $this->endOfMonth = $endOfMonth;
    }

    public function collection() {
        Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

        $mycharges = Stripe\Charge::all([
            'created' => [
                'gte' => $this->startOfMonth,
                'lte' => $this->endOfMonth,
            ],
            'limit' => 100
        ]);

        $charges = [];

        foreach ($mycharges->data as $charge) {
            $charges[] = $charge;
        }

        $verify = 1;

        while ($mycharges->has_more) {
            $lastCharge = end($mycharges->data);
            $mycharges = Stripe\Charge::all([
                'created' => [
                    'gte' => $this->startOfMonth,
                    'lte' => $this->endOfMonth,
                ],
                'limit' => 100,
                'starting_after' => $lastCharge->id,
            ]);

            foreach ($mycharges->data as $charge) {
                $charges[] = $charge;
            }
        }

        foreach ($charges as $charge) {
            if ($charge->status == 'succeeded'){
                $data[] = [
                    $charge->id,
                    ($charge->amount / 100),
                    $charge->currency,
                    $charge->receipt_email,
                    date('d/m/Y H:i:s', $charge["created"] - 4 * 60 * 60),
                    date('d/m/Y H:i:s', $charge["created"] + 2 * 60 * 60),
                ];
            }
        }

        return collect([$data]);
    }

    public function headings(): array
    {
        return ['ID', 'Monto', 'Moneda', 'Cliente', 'Fecha y Hora (Venezuela)', 'Fecha y Hora (España)'];
    }
}