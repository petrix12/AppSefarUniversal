<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StripeExcel implements FromQuery, WithHeadings
{
    protected $startOfMonth;
    protected $endOfMonth;

    public function __construct($startOfMonth, $endOfMonth)
    {
        $this->startOfMonth = $startOfMonth;
        $this->endOfMonth = $endOfMonth;
    }

    public function query()
    {
        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

        $mycharges = \Stripe\Charge::all([
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
            $mycharges = \Stripe\Charge::all([
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

        $data = collect($charges)->map(function ($charge) {
            return [
                'ID' => $charge->id,
                'Monto' => $charge->amount / 100,
                'Moneda' => $charge->currency,
                'Cliente' => $charge->receipt_email,
            ];
        });

        return $data;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Monto',
            'Moneda',
            'Cliente',
        ];
    }
}