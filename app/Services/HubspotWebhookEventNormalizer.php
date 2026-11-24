<?php

namespace App\Services;

/** Normalizes HubSpot's expanded-object webhook envelope to CRM object API names. */
class HubspotWebhookEventNormalizer
{
    private const OBJECT_TYPES = [
        '0-1' => 'contact', '0-2' => 'company', '0-3' => 'deal', '0-5' => 'ticket',
        '0-7' => 'product', '0-8' => 'line_item', '0-14' => 'quote', '0-18' => 'communication',
        '0-19' => 'feedback_submission', '0-27' => 'task', '0-46' => 'note', '0-47' => 'meeting',
        '0-48' => 'call', '0-49' => 'email', '0-53' => 'invoice', '0-54' => 'marketing_event',
        '0-69' => 'subscription', '0-74' => 'goal', '0-84' => 'discount', '0-85' => 'fee',
        '0-86' => 'tax', '0-101' => 'commerce_payment', '0-115' => 'user', '0-116' => 'postal_mail',
        '0-123' => 'order', '0-136' => 'lead', '0-142' => 'cart', '0-162' => 'service',
        '0-410' => 'course', '0-420' => 'listing', '0-421' => 'appointment', '0-970' => 'project',
    ];

    private const OBJECT_ALIASES = [
        'appointment' => 'appointment', 'appointments' => 'appointment',
        'call' => 'call', 'calls' => 'call', 'cart' => 'cart', 'carts' => 'cart',
        'commerce_payment' => 'commerce_payment', 'commerce_payments' => 'commerce_payment',
        'communication' => 'communication', 'communications' => 'communication',
        'company' => 'company', 'companies' => 'company', 'contact' => 'contact', 'contacts' => 'contact',
        'course' => 'course', 'courses' => 'course', 'deal' => 'deal', 'deals' => 'deal',
        'discount' => 'discount', 'discounts' => 'discount', 'email' => 'email', 'emails' => 'email',
        'feedback_submission' => 'feedback_submission', 'feedback_submissions' => 'feedback_submission',
        'fee' => 'fee', 'fees' => 'fee', 'goal' => 'goal', 'goals' => 'goal',
        'invoice' => 'invoice', 'invoices' => 'invoice', 'lead' => 'lead', 'leads' => 'lead',
        'line_item' => 'line_item', 'line_items' => 'line_item', 'listing' => 'listing', 'listings' => 'listing',
        'marketing_event' => 'marketing_event', 'marketing_events' => 'marketing_event',
        'meeting' => 'meeting', 'meetings' => 'meeting', 'note' => 'note', 'notes' => 'note',
        'order' => 'order', 'orders' => 'order', 'payment' => 'commerce_payment',
        'postal_mail' => 'postal_mail', 'product' => 'product', 'products' => 'product',
        'project' => 'project', 'projects' => 'project', 'quote' => 'quote', 'quotes' => 'quote',
        'service' => 'service', 'services' => 'service', 'subscription' => 'subscription',
        'subscriptions' => 'subscription', 'task' => 'task', 'tasks' => 'task',
        'tax' => 'tax', 'taxes' => 'tax', 'ticket' => 'ticket', 'tickets' => 'ticket',
        'user' => 'user', 'users' => 'user',
    ];

    public static function normalize(array $event): array
    {
        $subscriptionType = (string) ($event['subscriptionType'] ?? '');
        if (! str_starts_with($subscriptionType, 'object.')) return $event;

        $eventName = substr($subscriptionType, strlen('object.'));
        $objectType = self::objectType($event['objectTypeId'] ?? null);
        if (! in_array($eventName, ['creation', 'propertyChange', 'deletion', 'archived', 'restore', 'associationChange', 'merge'], true)
            || $objectType === null) {
            throw new \InvalidArgumentException('Unsupported HubSpot generic webhook event.');
        }

        $event['genericWebhook'] = true;
        $event['subscriptionType'] = $objectType.'.'.$eventName;
        return $event;
    }

    /** Return the CRM object API name, or a numeric ID for standard/custom objects. */
    public static function objectType(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') return null;
        $value = strtolower(trim($value));
        if (isset(self::OBJECT_TYPES[$value])) return self::OBJECT_TYPES[$value];
        if (isset(self::OBJECT_ALIASES[$value])) return self::OBJECT_ALIASES[$value];
        if (preg_match('/^(?:0|2)-[0-9]{1,10}$/', $value) === 1) return $value;
        return null;
    }
}
