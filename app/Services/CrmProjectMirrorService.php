<?php

namespace App\Services;

use App\Models\Negocio;
use App\Models\TlProject;
use App\Models\User;

/** Mirrors COS inputs only for projects whose contact has an actual Teamleader deal. */
class CrmProjectMirrorService
{
    public function __construct(private TeamleaderService $teamleader) {}

    public function process(array $event): void
    {
        if (! in_array($event['type'] ?? '', ['project.created', 'project.updated', 'project.deleted'], true)) {
            throw new \UnexpectedValueException('Unsupported project event type.');
        }
        $id = (string) data_get($event, 'subject.id', '');
        // A delayed deletion must not hide a restored project. Only a current 404 is a tombstone.
        $data = $this->teamleader->webhookObject('project', $id);
        $previous = TlProject::find($id);
        $contactId = (string) data_get($data, 'customer.id', data_get($data, 'lead.customer.id', ''));
        $customerType = data_get($data, 'customer.type', data_get($data, 'lead.customer.type'));
        $eligible = false;
        if ($data !== null && $customerType === 'contact' && $contactId !== '') {
            $eligible = app(CrmContactMirrorService::class)->syncContact('teamleader', $contactId)->classification === 'client';
        }

        // An external customer move is not permission to reassign an App expediente.
        $linkedUsers = Negocio::where('teamleader_id', $id)->pluck('user_id')->unique()->all();
        $associationConflict = $eligible && $linkedUsers !== []
            && ! ($previous?->customer_type === 'contact' && (string) $previous->customer_id === $contactId)
            && User::whereIn('id', $linkedUsers)->where(function ($query) use ($contactId) {
                $query->whereNull('tl_id')->orWhere('tl_id', '!=', $contactId);
            })->exists();
        if ($associationConflict) $eligible = false;

        $changed = false;
        if ($eligible) {
            $changed = ! $previous || in_array($previous->status, TlProject::CRM_INACTIVE_STATUSES, true)
                || CosCrmSyncFields::projectState($previous->raw_data ?? []) !== CosCrmSyncFields::projectState($data);
            if ($changed) TlProject::fromTeamleader($data);
        } elseif ($previous) {
            // Retain the history and all invoices/documents; exclude this mirror from active COS input.
            $status = $data === null ? TlProject::CRM_DELETED : TlProject::CRM_EXCLUDED;
            $changed = $previous->status !== $status;
            if ($changed) $previous->forceFill(['status' => $status])->save();
        }
        if (! $changed) {
            if ($associationConflict) throw new \App\Exceptions\CrmProjectAssociationConflictException;
            return;
        }

        $contactIds = array_values(array_unique(array_filter([
            $contactId, $previous?->customer_type === 'contact' ? $previous->customer_id : null,
        ])));
        User::role('Cliente')->where(function ($query) use ($contactIds, $linkedUsers) {
            $query->whereIn('tl_id', $contactIds)->orWhereIn('id', $linkedUsers);
        })->each(fn (User $user) => CrmCosCache::invalidate($user));
        if ($associationConflict) throw new \App\Exceptions\CrmProjectAssociationConflictException;
    }
}
