<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformEventProcessor
{
    public function __construct(private PlatformMirror $mirror) {}
    public function process(int $limit=4): array
    {
        $counts=['synced'=>0,'retry'=>0,'review'=>0];
        for ($i=0;$i<$limit;$i++) {
            $row=DB::transaction(function () {
                $row=DB::table('platform_events')->whereIn('status',['pending','retry','processing'])
                    ->where(fn($q)=>$q->whereNull('next_attempt_at')->orWhere('next_attempt_at','<=',now()))
                    ->where(fn($q)=>$q->whereNull('leased_until')->orWhere('leased_until','<=',now()))->orderBy('id')->lockForUpdate()->first();
                if (!$row) return null;
                $row->lease=(string) Str::uuid(); $row->attempts++;
                DB::table('platform_events')->where('id',$row->id)->update(['status'=>'processing','lease'=>$row->lease,
                    'leased_until'=>now()->addMinute(),'attempts'=>$row->attempts,'updated_at'=>now()]);
                return $row;
            });
            if (!$row) break;
            try {
                if ($row->mode!==PlatformIdentity::mode($row->provider,$row->account)) throw new PlatformApiException('mode_changed');
                $fence=fn()=>DB::table('platform_events')->where('id',$row->id)->lockForUpdate()->value('lease')===$row->lease;
                try { $this->mirror->fetch($row->provider,$row->account,$row->resource,$row->remote_id,$fence); }
                catch (PlatformApiException $e) {
                    if ($row->provider!=='stripe' || $e->httpStatus!==404 || !str_ends_with($row->event_type,'.deleted')) throw $e;
                    $this->mirror->save($row->provider,$row->account,$row->resource,['id'=>$row->remote_id,'deleted'=>true],$fence);
                }
                DB::table('platform_events')->where('id',$row->id)->where('lease',$row->lease)->update([
                    'status'=>'synced','lease'=>null,'leased_until'=>null,'next_attempt_at'=>null,'error_code'=>null,'updated_at'=>now()]);
                $counts['synced']++;
            } catch (\Throwable $e) {
                $status=$row->attempts >= 8 || in_array(PlatformMirror::errorCode($e),['mode_changed','account_disabled'],true) ? 'review' : 'retry';
                DB::table('platform_events')->where('id',$row->id)->where('lease',$row->lease)->update([
                    'status'=>$status,'lease'=>null,'leased_until'=>null,'error_code'=>PlatformMirror::errorCode($e),
                    'next_attempt_at'=>$status==='retry' ? now()->addSeconds(min(3600,60*2**min($row->attempts-1,6))) : null,'updated_at'=>now()]);
                $counts[$status]++;
            }
        }
        return $counts;
    }
    public static function receive(string $provider, string $account, string $eventId, string $type, string $resource, string $id): bool
    {
        $created=DB::table('platform_events')->insertOrIgnore(PlatformIdentity::where($provider,$account)+['event_id'=>$eventId,
            'event_type'=>$type,'resource'=>$resource,'remote_id'=>$id,'status'=>'pending','next_attempt_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $stored=DB::table('platform_events')->where(PlatformIdentity::where($provider,$account))->where('event_id',$eventId)->first();
        abort_if(!$stored || $stored->event_type!==$type || $stored->remote_id!==$id || $stored->resource!==$resource,409);
        return (bool) $created;
    }
}
