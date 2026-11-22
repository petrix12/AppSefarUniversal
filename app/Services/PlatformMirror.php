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
    public function queue(string $provider, string $account, string $resource, bool $restart = false): bool
    {
        $this->catalog->definition($provider, $resource);
        DB::table('platform_sync_states')->insertOrIgnore(PlatformIdentity::where($provider,$account)+['resource'=>$resource,'created_at'=>now(),'updated_at'=>now()]);
        return DB::transaction(function () use ($provider,$account,$resource,$restart) {
            $state = DB::table('platform_sync_states')->where(PlatformIdentity::where($provider,$account))->where('resource',$resource)->lockForUpdate()->first();
            if ($state->requested || ($state->leased_until && now()->lt($state->leased_until))) return false;
            $changes = ['requested'=>true,'error_code'=>null,'updated_at'=>now()];
            if ($restart || !$state->has_more) $changes += ['cursor'=>null,'page'=>1];
            DB::table('platform_sync_states')->where('id',$state->id)->update($changes);
            return true;
        });
    }
    /** Process bounded pages, resuming failed imports and sharing work across resources. */
    public function sync(int $limit = 2): array
    {
        $counts = ['records'=>0,'pages'=>0,'errors'=>0];
        $visited = [];
        for ($i=0; $i<$limit; $i++) {
            $state = DB::transaction(function () use ($visited) {
                $query = DB::table('platform_sync_states')->where('requested',true)
                    ->where(fn($q)=>$q->whereNull('leased_until')->orWhere('leased_until','<=',now()))
                    ->orderBy('updated_at')->orderBy('id');
                $row = (clone $query)->whereNotIn('id',$visited)->lockForUpdate()->first()
                    ?? $query->lockForUpdate()->first();
                if (!$row) return null;
                $row->lease = (string) Str::uuid();
                DB::table('platform_sync_states')->where('id',$row->id)->update(['lease'=>$row->lease,'leased_until'=>now()->addMinute()]);
                return $row;
            });
            if (!$state) break;
            $visited[] = $state->id;
            try {
                if ($state->mode!==PlatformIdentity::mode($state->provider,$state->account)) throw new PlatformApiException('mode_changed');
                $path = $this->catalog->definition($state->provider,$state->resource)[2];
                $inactivePrices = $state->provider==='stripe' && $state->resource==='prices'
                    && str_starts_with((string)$state->cursor,'inactive:');
                $requestPage=(int)$state->page;
                if ($state->provider === 'stripe') {
                    $query = ['limit'=>100];
                    $cursor = $inactivePrices ? substr($state->cursor,9) : $state->cursor;
                    if ($state->resource==='subscriptions') {
                        // Older imports used the active-only list; restart that filter once to include canceled entries.
                        $cursor=str_starts_with((string)$cursor,'all:') ? substr($cursor,4) : null;
                        $query['status'] = 'all';
                    }
                    if ($cursor) $query['starting_after'] = $cursor;
                    if ($state->resource==='prices') $query['active'] = !$inactivePrices;
                    $response = $this->stripe->request($state->account,'GET',$path,$query);
                    $items = $response['data'] ?? null;
                    if (!is_bool($response['has_more']??null)) throw new PlatformApiException('invalid_page');
                    $apiMore = $response['has_more'];
                    if ($apiMore && !$items) throw new PlatformApiException('invalid_page');
                    // Stripe lists active prices by default; an additional pass includes archived prices.
                    $more = $apiMore || ($state->resource==='prices' && !$inactivePrices);
                } else {
                    // A page number from an older page size cannot be reused without skipping records.
                    if (!str_starts_with((string)$state->cursor,'p100:')) $requestPage=1;
                    $response = $this->kalendia->request('GET',$path,['page'=>$requestPage,'per_page'=>100]);
                    $items = $response['items'] ?? null;
                    $pages = data_get($response,'pagination.pages',data_get($response,'pagination.total_pages'));
                    if ($pages!==null) {
                        if (filter_var($pages,FILTER_VALIDATE_INT)===false || (int)$pages<0) throw new PlatformApiException('invalid_page');
                        if ((int)$pages===0 && $items) throw new PlatformApiException('invalid_page');
                        $more = $requestPage < (int)$pages;
                    } elseif (is_bool($response['has_more']??null)) $more = $response['has_more'];
                    // Some providers cap page size. An empty page, not a short one, ends an uncounted list.
                    else $more = is_array($items) && count($items)>0;
                    if ($more && !$items) throw new PlatformApiException('invalid_page');
                }
                if (!is_array($items) || !array_is_list($items) || count($items)>100) throw new PlatformApiException('invalid_page');
                foreach ($items as $item) if (!is_array($item)) throw new PlatformApiException('invalid_page');
                $lastId = $items ? end($items)['id'] ?? ($state->resource==='inventory' ? end($items)['product_id']??null : null) : null;
                if ($more && $items && !$lastId) throw new PlatformApiException('invalid_page');
                $nextCursor = $more ? $lastId : null;
                if ($state->provider==='stripe' && $state->resource==='prices' && $more) {
                    $nextCursor = !$apiMore && !$inactivePrices ? 'inactive:' : ($inactivePrices ? 'inactive:'.$lastId : $lastId);
                }
                if ($state->provider==='stripe' && $state->resource==='subscriptions' && $more) $nextCursor='all:'.$lastId;
                if ($state->provider==='kalendia' && $more) $nextCursor='p100:'.hash('sha256',$lastId);
                if (strlen((string)$nextCursor)>190) throw new PlatformApiException('pagination_stalled');
                if ($more && $nextCursor===$state->cursor) throw new PlatformApiException('pagination_stalled');
                $count = DB::transaction(function () use ($state,$items,$more,$nextCursor,$requestPage) {
                    $current=DB::table('platform_sync_states')->where('id',$state->id)->lockForUpdate()->first();
                    if ($current->lease !== $state->lease) throw new PlatformApiException('lease_expired');
                    foreach ($items as $item) $this->save($state->provider,$state->account,$state->resource,$item);
                    DB::table('platform_sync_states')->where('id',$state->id)->update(['has_more'=>$more,'requested'=>$more,
                        'page'=>$more ? $requestPage+1 : 1,'cursor'=>$nextCursor,'checked_at'=>now(),'error_code'=>null,
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
