<?php
namespace App\Services;

use App\Models\PlatformRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformMirror
{
    public function __construct(private PlatformCatalog $catalog, private StripeAdminApi $stripe, private KalendiaApi $kalendia) {}

    public function save(string $provider, string $account, string $resource, array $data, ?\Closure $fence=null): PlatformRecord
    {
        PlatformIdentity::mode($provider,$account);
        if ($provider==='kalendia' && $resource==='inventory' && !isset($data['id'])) $data['id']=$data['product_id']??null;
        if (!$this->catalog->validId($provider, $resource, $data['id'] ?? null)) throw new PlatformApiException('resource_mismatch');
        if ($provider === 'stripe' && isset($data['livemode']) && $this->stripe->mode($account) !== null
            && $data['livemode'] !== $this->stripe->mode($account)) throw new PlatformApiException('mode_mismatch');
        $projection = $this->catalog->project($provider, $resource, $data);
        return DB::transaction(function () use ($provider, $account, $resource, $data, $projection, $fence) {
            if ($fence && !$fence()) throw new PlatformApiException('lease_expired');
            if ($provider === 'kalendia' && $resource === 'appointments' && !app(KalendiaAppointmentService::class)->record($data, true)) {
                throw new PlatformApiException('appointment_conflict');
            }
            $row = PlatformRecord::firstOrNew(PlatformIdentity::where($provider,$account)+['resource'=>$resource,'remote_id'=>$data['id']]);
            $row->forceFill(['name'=>Str::limit((string) ($data['name'] ?? $data['customer_name'] ?? $data['description'] ?? $data['title'] ?? $data['number'] ?? $data['id']), 255, ''),
                'email'=>is_string($data['email'] ?? $data['customer_email'] ?? null) ? Str::limit($data['email'] ?? $data['customer_email'], 255, '') : null,
                'status'=>($data['deleted'] ?? false) ? 'deleted' : ($provider==='stripe' && $resource==='charges' && !empty($data['refunded']) ? 'refunded'
                    : ($provider==='stripe' && $resource==='charges' && ($data['amount_refunded']??0)>0 ? 'partially_refunded'
                    : Str::limit((string) ($data['status'] ?? $data['payment_status'] ?? (isset($data['active']) ? ($data['active'] ? 'active' : 'inactive') : '')), 80, ''))),
                'data'=>$projection, 'checked_at'=>now()])->save();
            if ($provider==='stripe' && $resource==='refunds' && !PlatformIdentity::readOnly($provider,$account)) $this->reconcileRefund($account,$data);
            return $row;
        });
    }
    public function fetch(string $provider, string $account, string $resource, string $id, ?\Closure $fence=null): PlatformRecord
    {
        PlatformIdentity::mode($provider,$account);
        $definition = $this->catalog->definition($provider, $resource);
        if (!$this->catalog->validId($provider, $resource, $id)) throw new PlatformApiException('invalid_id');
        if ($provider === 'stripe') $data = $this->stripe->request($account, 'GET', $definition[2].'/'.$id);
        else {
            $response = $this->kalendia->request('GET', $definition[2], [$resource==='inventory' ? 'product_id' : 'id'=>$id, 'per_page'=>100]);
            $items = $response['items'] ?? [];
            if ($resource==='inventory' && isset($items[0]['product_id']) && !isset($items[0]['id'])) $items[0]['id']=$items[0]['product_id'];
            if (count($items) !== 1 || ($items[0]['id'] ?? null) !== $id) throw new PlatformApiException('resource_mismatch');
            $data = $items[0];
        }
        if (($data['id'] ?? null) !== $id) throw new PlatformApiException('resource_mismatch');
        return $this->save($provider, $account, $resource, $data,$fence);
    }
    private function reconcileRefund(string $account,array $data): void
    {
        $uuid=data_get($data,'metadata.app_operation');
        $query=\App\Models\PlatformOperation::where('action','refund')->whereIn('status',['pending','uncertain','processing'])
            ->whereHas('record',fn($q)=>$q->where(PlatformIdentity::where('stripe',$account)));
        if (is_string($uuid) && Str::isUuid($uuid)) $query->where('uuid',$uuid);
        else $query->where('remote_id',$data['id']);
        $op=$query->lockForUpdate()->first();
        if (!$op || $op->leased_until?->isFuture()) return;
        $body=data_get($op->payload,'request.data',[]); $record=$op->record;
        if (($data['charge']??null)!==$record->remote_id || ($body['charge']??null)!==$record->remote_id
            || ($body['amount']??null)!==($data['amount']??null) || ($record->data['currency']??null)!==($data['currency']??null)) return;
        $status=match($data['status']??null) {'succeeded'=>'succeeded','failed','canceled'=>'rejected',default=>'pending'};
        $op->forceFill(['status'=>$status,'remote_id'=>$data['id'],'error_code'=>null,'lease'=>null,'leased_until'=>null])->save();
    }
    public function queue(string $provider, string $account, string $resource, bool $restart = false): void
    {
        $this->catalog->definition($provider, $resource);
        DB::table('platform_sync_states')->insertOrIgnore(PlatformIdentity::where($provider,$account)+['resource'=>$resource,'created_at'=>now(),'updated_at'=>now()]);
        DB::transaction(function () use ($provider,$account,$resource,$restart) {
            $state = DB::table('platform_sync_states')->where(PlatformIdentity::where($provider,$account))->where('resource',$resource)->lockForUpdate()->first();
            if ($state->leased_until && now()->lt($state->leased_until)) return;
            $changes = ['requested'=>true,'error_code'=>null,'updated_at'=>now()];
            if ($restart || !$state->has_more) $changes += ['cursor'=>null,'page'=>1];
            DB::table('platform_sync_states')->where('id',$state->id)->update($changes);
        });
    }
    /** One bounded page. Requests and pagination survive a slow provider or interrupted worker. */
    public function sync(int $limit = 2): array
    {
        $counts = ['records'=>0,'pages'=>0,'errors'=>0];
        for ($i=0; $i<$limit; $i++) {
            $state = DB::transaction(function () {
                $row = DB::table('platform_sync_states')->where('requested',true)
                    ->where(fn($q)=>$q->whereNull('leased_until')->orWhere('leased_until','<=',now()))->orderBy('updated_at')->lockForUpdate()->first();
                if (!$row) return null;
                $row->lease = (string) Str::uuid();
                DB::table('platform_sync_states')->where('id',$row->id)->update(['lease'=>$row->lease,'leased_until'=>now()->addMinute()]);
                return $row;
            });
            if (!$state) break;
            try {
                if ($state->mode!==PlatformIdentity::mode($state->provider,$state->account)) throw new PlatformApiException('mode_changed');
                $path = $this->catalog->definition($state->provider,$state->resource)[2];
                if ($state->provider === 'stripe') {
                    $query = ['limit'=>50]; if ($state->cursor) $query['starting_after'] = $state->cursor;
                    $response = $this->stripe->request($state->account,'GET',$path,$query);
                    $items = $response['data'] ?? null; $more = (bool) ($response['has_more'] ?? false);
                } else {
                    $response = $this->kalendia->request('GET',$path,['page'=>$state->page,'per_page'=>50]);
                    $items = $response['items'] ?? null;
                    $more = isset($response['pagination']['pages']) ? $state->page < (int) $response['pagination']['pages'] : count($items ?? []) === 50;
                }
                if (!is_array($items) || count($items)>100 || ($more && !$items)) throw new PlatformApiException('invalid_page');
                $count = DB::transaction(function () use ($state,$items,$more) {
                    $current=DB::table('platform_sync_states')->where('id',$state->id)->lockForUpdate()->first();
                    if ($current->lease !== $state->lease) return 0;
                    foreach ($items as $item) $this->save($state->provider,$state->account,$state->resource,$item);
                    $cursor = $items ? end($items)['id'] ?? null : null;
                    if ($more && $cursor === $state->cursor) throw new PlatformApiException('pagination_stalled');
                    DB::table('platform_sync_states')->where('id',$state->id)->update(['has_more'=>$more,'requested'=>$more,
                        'page'=>$more ? $state->page+1 : 1,'cursor'=>$more ? $cursor : null,'checked_at'=>now(),'error_code'=>null,
                        'lease'=>null,'leased_until'=>null,'updated_at'=>now()]);
                    return count($items);
                });
                $counts['records'] += $count; $counts['pages']++;
            } catch (\Throwable $e) {
                DB::table('platform_sync_states')->where('id',$state->id)->where('lease',$state->lease)->update([
                    'error_code'=>self::errorCode($e),'requested'=>false,'lease'=>null,'leased_until'=>null,'updated_at'=>now()]);
                $counts['errors']++;
            }
        }
        return $counts;
    }
    public static function errorCode(\Throwable $e): string
    {
        return $e instanceof PlatformApiException ? $e->errorCode
            : ($e instanceof KalendiaApiException ? $e->errorCode ?? 'http_'.($e->httpStatus ?? 'unavailable') : 'processing_error');
    }
}
