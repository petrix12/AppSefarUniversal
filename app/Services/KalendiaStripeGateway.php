<?php
namespace App\Services;
class KalendiaStripeGateway
{
    private function client(): \Stripe\StripeClient
    {
        return new \Stripe\StripeClient((string) config('services.stripe.secret'));
    }
    public function create(array $payload, string $key): array
    {
        return $this->client()->paymentIntents->create($payload, ['idempotency_key' => $key])->toArray();
    }
    public function retrieve(string $id): array { return $this->client()->paymentIntents->retrieve($id)->toArray(); }
}
