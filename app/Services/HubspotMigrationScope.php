<?php

namespace App\Services;

/** HubSpot record types included in the agreed initial Sefar CRM migration. */
final class HubspotMigrationScope
{
    public const OBJECT_TYPES = [
        'contacts',
        'deals',
        'tickets',
        'line_items',
        'quotes',
        'invoices',
        'commerce_payments',
        'subscriptions',
        'orders',
        'calls',
        'emails',
        'meetings',
        'notes',
        'tasks',
    ];

    /**
     * HubSpot association pairs with operational value for the agreed CRM
     * scope. Keep this list explicit: probing every possible object pair
     * creates hundreds of low-value API calls and predictable 4xx responses.
     * Pairs are directed because HubSpot exposes type labels per direction.
     */
    public const ASSOCIATION_PAIRS = [
        ['contacts', 'deals'], ['deals', 'contacts'],
        ['contacts', 'tickets'], ['tickets', 'contacts'],
        ['contacts', 'calls'], ['calls', 'contacts'],
        ['contacts', 'emails'], ['emails', 'contacts'],
        ['contacts', 'meetings'], ['meetings', 'contacts'],
        ['contacts', 'notes'], ['notes', 'contacts'],
        ['contacts', 'tasks'], ['tasks', 'contacts'],
        ['contacts', 'quotes'], ['quotes', 'contacts'],
        ['contacts', 'invoices'], ['invoices', 'contacts'],
        ['contacts', 'commerce_payments'], ['commerce_payments', 'contacts'],
        ['contacts', 'subscriptions'], ['subscriptions', 'contacts'],
        ['contacts', 'orders'], ['orders', 'contacts'],
        ['deals', 'tickets'], ['tickets', 'deals'],
        ['deals', 'line_items'], ['line_items', 'deals'],
        ['deals', 'quotes'], ['quotes', 'deals'],
        ['deals', 'invoices'], ['invoices', 'deals'],
        ['deals', 'commerce_payments'], ['commerce_payments', 'deals'],
        ['deals', 'subscriptions'], ['subscriptions', 'deals'],
        ['deals', 'orders'], ['orders', 'deals'],
        ['deals', 'calls'], ['calls', 'deals'],
        ['deals', 'emails'], ['emails', 'deals'],
        ['deals', 'meetings'], ['meetings', 'deals'],
        ['deals', 'notes'], ['notes', 'deals'],
        ['deals', 'tasks'], ['tasks', 'deals'],
        ['tickets', 'calls'], ['calls', 'tickets'],
        ['tickets', 'emails'], ['emails', 'tickets'],
        ['tickets', 'meetings'], ['meetings', 'tickets'],
        ['tickets', 'notes'], ['notes', 'tickets'],
        ['tickets', 'tasks'], ['tasks', 'tickets'],
        ['line_items', 'quotes'], ['quotes', 'line_items'],
        ['line_items', 'invoices'], ['invoices', 'line_items'],
        ['line_items', 'orders'], ['orders', 'line_items'],
        ['quotes', 'invoices'], ['invoices', 'quotes'],
        ['invoices', 'commerce_payments'], ['commerce_payments', 'invoices'],
        ['orders', 'commerce_payments'], ['commerce_payments', 'orders'],
        ['orders', 'subscriptions'], ['subscriptions', 'orders'],
    ];
}
