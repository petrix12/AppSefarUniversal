<?php

namespace App\Services;

use App\Models\Agcliente;
use App\Models\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

class ClientRestrictionTargets
{
    public function tree(?string $treeId): Collection
    {
        if (blank($treeId)) {
            return new Collection();
        }
        return User::query()->where(function ($query) use ($treeId): void {
            $query->where('passport', $treeId);
            if (Schema::hasTable('user_genealogy_tree_links')) {
                $query->orWhereHas('genealogyTreeLink', fn ($q) => $q->where('tree_id', $treeId));
            }
        })->get();
    }

    public function assertTreeReadable(?string $treeId, ?User $actor): void
    {
        foreach ($this->tree($treeId) as $client) {
            app(ClientRestrictionService::class)->assertReadable($client, $actor);
        }
    }

    public function assertTreeDeliverable(?string $treeId): void
    {
        foreach ($this->tree($treeId) as $client) {
            app(ClientRestrictionService::class)->assertDeliverable($client);
        }
    }

    public function assertFileDeliverable(File $file): void
    {
        if ($file->user_id && ($client = User::find($file->user_id))) {
            app(ClientRestrictionService::class)->assertDeliverable($client);
        }
        $this->assertTreeDeliverable($file->IDCliente);
    }

    public function teamleader(string $type, string $id): Collection
    {
        $ids = [];
        if ($type === 'contact') {
            $ids[] = $id;
        } else {
            $model = match ($type) {
                'deal' => \App\Models\TlDeal::class, 'project' => \App\Models\TlProject::class,
                'invoice' => \App\Models\TlInvoice::class, default => null,
            };
            if ($model && Schema::hasTable((new $model)->getTable())) {
                $record = $model::find($id);
                if ($record && $record->customer_type === 'contact') {
                    $ids[] = $record->customer_id;
                }
            }
        }
        return Schema::hasColumn('users', 'tl_id') ? User::whereIn('tl_id', $ids)->get() : new Collection();
    }
}
