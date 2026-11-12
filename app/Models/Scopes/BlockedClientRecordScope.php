<?php

namespace App\Models\Scopes;

use App\Services\ClientRestrictionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Schema;

class BlockedClientRecordScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $actor = auth()->user() ?: auth('sanctum')->user();
        if (! $actor || ! Schema::hasTable('user_access_blocks') || app(ClientRestrictionService::class)->canReview($actor)) {
            return;
        }
        $table = $model->getTable();
        $builder->whereNotExists(function ($q) use ($table): void {
            $q->selectRaw('1')->from('users as restricted_client')
                ->join('user_access_blocks as client_hold', 'client_hold.user_id', '=', 'restricted_client.id');
            if (in_array($table, ['files', 'agclientes'], true)) {
                $q->where(function ($ids) use ($table): void {
                    $ids->whereColumn('restricted_client.passport', $table.'.IDCliente');
                    if ($table === 'files') {
                        $ids->orWhereColumn('restricted_client.id', 'files.user_id');
                    }
                    if (Schema::hasTable('user_genealogy_tree_links')) {
                        $ids->orWhereExists(fn ($link) => $link->selectRaw('1')->from('user_genealogy_tree_links')
                            ->whereColumn('user_genealogy_tree_links.user_id', 'restricted_client.id')
                            ->whereColumn('user_genealogy_tree_links.tree_id', $table.'.IDCliente'));
                    }
                });
            } elseif (in_array($table, ['negocios', 'monday_data', 'client_chat_messages'], true)) {
                $column = $table === 'client_chat_messages' ? 'client_id' : 'user_id';
                $q->whereColumn('restricted_client.id', $table.'.'.$column);
            } elseif ($table === 'tl_contacts' && Schema::hasColumn('users', 'tl_id')) {
                $q->whereColumn('restricted_client.tl_id', 'tl_contacts.id');
            } elseif (in_array($table, ['tl_projects', 'tl_deals', 'tl_invoices', 'tl_credit_notes'], true) && Schema::hasColumn('users', 'tl_id')) {
                $q->where($table.'.customer_type', 'contact')->whereColumn('restricted_client.tl_id', $table.'.customer_id');
            } elseif ($table === 'monday_items' && Schema::hasColumn('users', 'monday_id')) {
                $q->where(function ($ids): void {
                    $ids->whereColumn('restricted_client.monday_id', 'monday_items.id');
                    if (Schema::hasTable('monday_service_registrations')) {
                        $ids->orWhereExists(fn ($registration) => $registration->selectRaw('1')->from('monday_service_registrations')
                            ->whereColumn('monday_service_registrations.user_id', 'restricted_client.id')
                            ->whereColumn('monday_service_registrations.monday_item_id', 'monday_items.id'));
                    }
                });
            } elseif ($table === 'tl_documents' && Schema::hasColumn('users', 'tl_id')) {
                $q->where(function ($ids): void {
                    $ids->where(fn ($contact) => $contact->where('tl_documents.entity_type', 'contact')
                        ->whereColumn('restricted_client.tl_id', 'tl_documents.entity_id'));
                    foreach (['deal' => 'tl_deals', 'project' => 'tl_projects'] as $type => $related) {
                        if (Schema::hasTable($related)) {
                            $ids->orWhereExists(fn ($entity) => $entity->selectRaw('1')->from($related)
                                ->where('tl_documents.entity_type', $type)->whereColumn($related.'.id', 'tl_documents.entity_id')
                                ->where($related.'.customer_type', 'contact')->whereColumn($related.'.customer_id', 'restricted_client.tl_id'));
                        }
                    }
                    if (Schema::hasTable('tl_document_user_links')) {
                        $ids->orWhereExists(fn ($link) => $link->selectRaw('1')->from('tl_document_user_links')
                            ->whereColumn('tl_document_user_links.user_id', 'restricted_client.id')
                            ->whereColumn('tl_document_user_links.tl_document_id', 'tl_documents.id'));
                    }
                });
            } else {
                $q->whereRaw('1 = 0');
            }
        });
    }
}
