<?php

namespace App\Services;

class CrmMirrorSource
{
    public function __construct(private HubspotService $hubspot, private TeamleaderService $teamleader) {}

    public function dealContacts(string $provider, string $id): array
    {
        if ($provider === 'hubspot') return $this->hubspot->webhookObjectExists('deals', $id)
            ? $this->hubspot->webhookAssociations('deals', $id, 'contacts') : [];
        $deal = $this->teamleader->webhookObject('deal', $id);
        $customer = data_get($deal, 'lead.customer');
        return ($customer['type'] ?? null) === 'contact' ? [(string) $customer['id']] : [];
    }

    public function contactDeals(string $provider, string $id): array
    {
        if ($provider === 'hubspot') {
            return array_map(fn ($dealId) => $this->hubspot->getDealById($dealId)
                ?? throw new \UnexpectedValueException('Missing CRM deal.'),
                $this->hubspot->webhookAssociations('contacts', $id, 'deals'));
        }
        $deals = [];
        $page = 1;
        $seenPages = [];
        do {
            $response = $this->teamleader->listDealsByContactId($id, $page++, 100);
            if (! isset($response['data']) || ! is_array($response['data'])) throw new \UnexpectedValueException('Missing CRM deals.');
            $pageKey = hash('sha256', json_encode(array_column($response['data'], 'id'), JSON_THROW_ON_ERROR));
            if ($response['data'] !== [] && isset($seenPages[$pageKey])) throw new \UnexpectedValueException('Repeated CRM page.');
            $seenPages[$pageKey] = true;
            foreach ($response['data'] as $row) {
                $deal = $this->teamleader->webhookObject('deal', $row['id']);
                if ($deal !== null) $deals[] = $deal;
            }
        } while (count($response['data']) === 100);
        return $deals;
    }

    public function contact(string $provider, string $id): array
    {
        $contact = $provider === 'hubspot'
            ? $this->hubspot->getContactById($id, true) : $this->teamleader->getContactById($id);
        if (! is_array($contact) || empty($contact['id'])) throw new \UnexpectedValueException('Missing CRM contact.');
        return $contact;
    }

    public function contactExists(string $provider, string $id): bool
    {
        return $provider === 'hubspot' ? $this->hubspot->webhookObjectExists('contacts', $id)
            : $this->teamleader->webhookObject('contact', $id) !== null;
    }
}
