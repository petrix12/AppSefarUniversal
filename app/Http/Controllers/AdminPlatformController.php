<?php
namespace App\Http\Controllers;

use App\Models\{Compras, KalendiaConsultationOrder, PlatformOperation, PlatformRecord};
use App\Services\{KalendiaApi, KalendiaRegistrationBookingService, PlatformCatalog, PlatformIdentity, PlatformMirror, PlatformOperationService, StripeAdminApi};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

class AdminPlatformController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request,$next) {
            $user=$request->user();
            abort_unless($user && $user->hasRole('Administrador') && !$user->hasRole('Cliente'),403);
            $provider=$request->route('provider'); $account=$request->route('account');
            abort_unless(PlatformIdentity::supported($provider,$account),404);
            abort_unless($user->can($provider.'.manage'),403);
            abort_unless(Schema::hasTable('platform_records'),503,'Falta aplicar la migración de administración de plataformas.');
            return $next($request);
        });
    }
    private function context(string $provider,string $account): array
    {
        return ['provider'=>$provider,'account'=>$account,'readOnly'=>PlatformIdentity::readOnly($provider,$account),'resources'=>app(PlatformCatalog::class)->resources($provider),
            'platformName'=>$provider==='stripe' ? 'Stripe' : 'Kalendia'];
    }
    public function dashboard(string $provider,string $account)
    {
        if ($provider === 'stripe') return redirect()->route('admin.stripe.workspace', ['account' => $account]);

        $counts=PlatformRecord::where(PlatformIdentity::where($provider,$account))->where('remote_id','not like','cus_request_%')->where('remote_id','not like','cli_request_%')->selectRaw('resource, count(*) as total')->groupBy('resource')->pluck('total','resource');
        $states=DB::table('platform_sync_states')->where(PlatformIdentity::where($provider,$account))->get()->keyBy('resource');
        $configured=$provider==='stripe' ? app(StripeAdminApi::class)->configured($account) : app(KalendiaApi::class)->configured();
        $mode=$provider==='stripe' ? app(StripeAdminApi::class)->mode($account) : null;
        return response()->view('platforms.index',$this->context($provider,$account)+compact('counts','states','configured','mode'))->header('Cache-Control','private, no-store');
    }
    public function records(Request $request,string $provider,string $account,string $resource)
    {
        $definition=app(PlatformCatalog::class)->definition($provider,$resource);
        $data=$request->validate(['q'=>['nullable','string','max:190'],'status'=>['nullable','string','max:80']]);
        $records=PlatformRecord::where(PlatformIdentity::where($provider,$account))->where('resource',$resource)
            ->where('remote_id','not like','cus_request_%')->where('remote_id','not like','cli_request_%')
            ->when($data['q']??null,fn($q,$value)=>$q->where(fn($q)=>$q->where('remote_id',$value)->orWhere('name','like','%'.$value.'%')->orWhere('email','like','%'.$value.'%')))
            ->when($data['status']??null,fn($q,$value)=>$q->where('status',$value))->orderByDesc('checked_at')->orderByDesc('id')->paginate(25)->withQueryString();
        $state=DB::table('platform_sync_states')->where(PlatformIdentity::where($provider,$account))->where('resource',$resource)->first();
        return response()->view('platforms.records',$this->context($provider,$account)+compact('records','resource','definition','state'))->header('Cache-Control','private, no-store');
    }
    private function scope(PlatformRecord $record,string $provider,string $account): void
    {
        abort_unless($record->provider===$provider && $record->account===$account && $record->mode===PlatformIdentity::mode($provider,$account),404);
    }
    public function show(string $provider,string $account,PlatformRecord $record)
    {
        $this->scope($record,$provider,$account);
        $operations=PlatformOperation::with('actor:id,name')->where('record_id',$record->id)->latest()->limit(20)->get();
        $uuid=(string)Str::uuid();
        return response()->json(['html'=>view('platforms.partials.record',$this->context($provider,$account)+compact('record','operations','uuid'))->render()])->header('Cache-Control','private, no-store');
    }
    public function refresh(string $provider,string $account,PlatformRecord $record,PlatformMirror $mirror)
    {
        $this->scope($record,$provider,$account);
        try {
            $mirror->fetch($provider,$account,$record->resource,$record->remote_id);
            foreach (PlatformOperation::where('record_id',$record->id)->where('status','pending')->where('action','refund')->get() as $op) {
                if ($op->remote_id) $mirror->fetch('stripe',$account,'refunds',$op->remote_id);
            }
            if ($provider==='stripe' && $record->resource==='charges'
                && PlatformOperation::where('record_id',$record->id)->where('action','refund')->where('status','uncertain')->exists()) {
                $refunds=app(StripeAdminApi::class)->request($account,'GET','refunds',['charge'=>$record->remote_id,'limit'=>100]);
                foreach ($refunds['data']??[] as $refund) {
                    if (($refund['charge']??null)===$record->remote_id) $mirror->save('stripe',$account,'refunds',$refund);
                }
            }
        } catch (\Throwable $e) { return response()->json(['message'=>'No se pudo actualizar el registro. Se conserva la última información.','code'=>PlatformMirror::errorCode($e)],422); }
        return $this->show($provider,$account,$record->fresh());
    }
    public function sync(Request $request,string $provider,string $account,string $resource,PlatformMirror $mirror)
    {
        $mirror->queue($provider,$account,$resource,$request->boolean('restart'));
        return back()->with('success','Actualización solicitada. Se procesará por páginas automáticamente; puedes seguir trabajando.');
    }
    public function lookup(Request $request,string $provider,string $account,string $resource,PlatformMirror $mirror)
    {
        $input=$request->validate(['remote_id'=>['required','string','max:190']]);
        try { $record=$mirror->fetch($provider,$account,$resource,$input['remote_id']); }
        catch (\Throwable $e) { return response()->json(['message'=>'No se encontró un registro coincidente o faltan permisos en la conexión.','code'=>PlatformMirror::errorCode($e)],422); }
        return $this->show($provider,$account,$record);
    }
    public function operate(Request $request,string $provider,string $account,PlatformRecord $record,PlatformOperationService $service)
    {
        abort_if(PlatformIdentity::readOnly($provider,$account),403,'Esta cuenta es de solo consulta.');
        $this->scope($record,$provider,$account);
        $data=$request->validate(['operation_id'=>['required','uuid'],'action'=>['required','in:refund,cancel_payment,customer_update,note_create,note_update,submission_create,submission_update,create_appointment'],
            'confirmed'=>['accepted'],'amount'=>['required_if:action,refund','nullable','string','max:20'],
            'reason'=>['required_if:action,refund,cancel_payment','nullable','in:duplicate,fraudulent,requested_by_customer,abandoned'],
            'comment'=>['required_if:action,refund,cancel_payment','nullable','string','max:1000'],
            'name'=>['required_if:action,customer_update','nullable','string','max:190'],'email'=>['nullable','email','max:190'],
            'phone'=>['nullable','string','max:80'],'description'=>['nullable','string','max:1000'],'address'=>['nullable','string','max:1000'],
            'timezone'=>['nullable','string','timezone:all'],'properties'=>['nullable','array','max:100'],
            'properties.*'=>['nullable','string','max:2000'],'text'=>['required_if:action,note_create,note_update','nullable','string','max:20000'],
            'is_pinned'=>['nullable','boolean'],'note_id'=>['required_if:action,note_update','nullable','regex:/^nt_[A-Za-z0-9_-]+$/D'],
            'values'=>['required_if:action,submission_create,submission_update','nullable','array','max:100'],'values.*'=>['nullable','string','max:10000'],
            'appointment_id'=>['required_if:action,submission_create','nullable','regex:/^apt_[A-Za-z0-9_-]+$/D'],
            'customer_id'=>['required_if:action,submission_create','nullable','regex:/^cli_[A-Za-z0-9_-]+$/D'],
            'service_id'=>['required_if:action,create_appointment,submission_create','nullable','regex:/^svc_[A-Za-z0-9_-]+$/D'],
            'date'=>['required_if:action,create_appointment','nullable','date_format:Y-m-d'],'slot'=>['required_if:action,create_appointment','nullable','string','size:64'],
            'notes'=>['nullable','string','max:2000']]);
        if (in_array($data['action'],['refund','cancel_payment'],true)) abort_unless($request->user()->isSuperadmin(),403);
        if ($data['action']==='refund' && !in_array($data['reason'],['duplicate','fraudulent','requested_by_customer'],true)) abort(422);
        if ($data['action']==='cancel_payment' && $data['reason']==='fraudulent') abort(422);
        if ($data['action']==='create_appointment') $data['timezone'] ??= \App\Services\KalendiaTimezone::preferred();
        $input=\Illuminate\Support\Arr::except($data,['action','operation_id','confirmed']);
        $operation=$service->run($record,$request->user(),$data['operation_id'],$data['action'],$input);
        return response()->json(['message'=>$operation->status==='pending' ? 'La plataforma recibió la solicitud; el resultado sigue pendiente.' : 'Operación confirmada.',
            'operation_id'=>$operation->uuid,'status'=>$operation->status,'html'=>view('platforms.partials.record',$this->context($provider,$account)+[
                'record'=>$record->fresh(),'operations'=>PlatformOperation::with('actor:id,name')->where('record_id',$record->id)->latest()->limit(20)->get(),'uuid'=>(string)Str::uuid()])->render()])
            ->header('Cache-Control','private, no-store');
    }
    public function createCustomer(Request $request,string $provider,string $account,PlatformOperationService $service)
    {
        abort_if(PlatformIdentity::readOnly($provider,$account),403,'Esta cuenta es de solo consulta.');
        $data=$request->validate(['operation_id'=>['required','uuid'],'confirmed'=>['accepted'],'name'=>['required','string','max:190'],
            'email'=>['required_without:phone','nullable','email','max:190'],'phone'=>['required_without:email','nullable','string','max:80'],
            'description'=>['nullable','string','max:1000'],'address'=>['nullable','string','max:1000'],'timezone'=>['nullable','string','timezone:all']]);
        $input=\Illuminate\Support\Arr::except($data,['operation_id','confirmed']);
        $prior=PlatformOperation::where('uuid',$data['operation_id'])->first();
        if ($prior) { $record=$prior->record; $this->scope($record,$provider,$account); abort_unless($prior->action==='customer_create',409); }
        else $record=PlatformRecord::firstOrCreate(PlatformIdentity::where($provider,$account)+['resource'=>'customers',
            'remote_id'=>($provider==='stripe'?'cus':'cli').'_request_'.$data['operation_id']],['name'=>'Creación de '.$input['name'],'data'=>[],'status'=>'draft']);
        $operation=$service->run($record,$request->user(),$data['operation_id'],'customer_create',$input);
        return $this->show($provider,$account,$operation->record);
    }
    public function notes(string $provider,string $account,PlatformRecord $record,KalendiaApi $api)
    {
        $this->scope($record,$provider,$account); abort_unless($provider==='kalendia' && $record->resource==='customers',404);
        try { $notes=$api->request('GET','customers/'.$record->remote_id.'/notes')['items']??[]; }
        catch (\Throwable $e) { return response()->json(['message'=>'No se pudieron consultar las notas. Revisa customers.notes.read.'],422); }
        return response()->json(['html'=>view('platforms.partials.notes',$this->context($provider,$account)+compact('record','notes'))->render()])->header('Cache-Control','private, no-store');
    }
    public function retryOperation(Request $request,string $provider,string $account,PlatformOperation $operation,PlatformOperationService $service)
    {
        abort_if(PlatformIdentity::readOnly($provider,$account),403,'Esta cuenta es de solo consulta.');
        $record=$operation->record; $this->scope($record,$provider,$account);
        $request->validate(['confirmed'=>['accepted']]);
        $service->run($record,$request->user(),$operation->uuid,$operation->action,$operation->payload['input']);
        return $this->show($provider,$account,$record->fresh());
    }
    public function bookingOptions(Request $request,string $provider,string $account,PlatformRecord $record,KalendiaApi $api,KalendiaRegistrationBookingService $booking)
    {
        $this->scope($record,$provider,$account); abort_unless($provider==='kalendia' && $record->resource==='customers',404);
        $input=$request->validate(['service_id'=>['nullable','regex:/^svc_[A-Za-z0-9_-]+$/D'],'date'=>['nullable','date_format:Y-m-d'],'timezone'=>['nullable','string','timezone:all']]);
        try {
            $services=collect($api->services())->filter(fn($s)=>$api->isFreeService($s))->values()->all();
            $slots=[];
            if (!empty($input['service_id']) && !empty($input['date'])) {
                abort_unless(collect($services)->contains('id',$input['service_id']),422);
                $purchase=new Compras(['source'=>KalendiaConsultationOrder::SOURCE,'metadata'=>['kalendia_consultation'=>['remote_service_id'=>$input['service_id']]]]);
                $slots=$booking->availableSlots($purchase,$input['date'],$input['timezone']??null);
            }
        } catch (\App\Services\KalendiaApiException) { return response()->json(['message'=>'No se pudo consultar la disponibilidad.'],422); }
        return response()->json(compact('services','slots'))->header('Cache-Control','private, no-store');
    }
    public function monitor(Request $request,string $provider,string $account)
    {
        abort_if(PlatformIdentity::readOnly($provider,$account),404);
        $lastRun=\Illuminate\Support\Facades\Cache::get('platforms.events.last_run');
        $secretConfigured=$provider==='stripe' ? filled(config('services.stripe.admin_webhook_secret')) : filled(config('kalendia.webhook_secret'));
        $events=DB::table('platform_events')->where(PlatformIdentity::where($provider,$account))->orderByDesc('id')->paginate(25);
        $native=$provider==='kalendia' && Schema::hasTable('kalendia_webhook_events') ? DB::table('kalendia_webhook_events')->orderByDesc('id')->limit(25)->get() : collect();
        $operations=PlatformOperation::with(['actor:id,name','record'])->whereHas('record',fn($q)=>$q->where(PlatformIdentity::where($provider,$account)))->latest()->limit(25)->get();
        return response()->view('platforms.monitor',$this->context($provider,$account)+compact('events','native','operations','lastRun','secretConfigured'))->header('Cache-Control','private, no-store');
    }
    public function retry(Request $request,string $provider,string $account,int $event)
    {
        abort_if(PlatformIdentity::readOnly($provider,$account),404);
        $data=$request->validate(['inbox'=>['required','in:platform,native']]);
        if ($data['inbox']==='native') {
            abort_unless($provider==='kalendia',404);
            DB::table('kalendia_webhook_events')->where('id',$event)->whereIn('status',['retry','review'])->update([
                'status'=>'pending','attempts'=>0,'next_attempt_at'=>now(),'lease'=>null,'leased_until'=>null,'last_error_code'=>null,'updated_at'=>now()]);
        } else DB::table('platform_events')->where('id',$event)->where(PlatformIdentity::where($provider,$account))->whereIn('status',['retry','review'])->update([
            'status'=>'pending','attempts'=>0,'next_attempt_at'=>now(),'lease'=>null,'leased_until'=>null,'error_code'=>null,'updated_at'=>now()]);
        return back()->with('success','Se solicitó un nuevo intento.');
    }
    public function check(string $provider,string $account,StripeAdminApi $stripe,KalendiaApi $kalendia)
    {
        try {
            if ($provider==='stripe') {
                $info=$stripe->request($account,'GET','account');
                $balance=$stripe->request($account,'GET','balance');
                $webhooks=PlatformIdentity::readOnly($provider,$account) ? [] : $stripe->request($account,'GET','webhook_endpoints',['limit'=>100]);
                $connection=['Cuenta'=>$info['id']??'','País'=>$info['country']??'','Modo'=>$stripe->mode($account) ? 'Producción' : 'Pruebas',
                    'Saldo disponible'=>collect($balance['available']??[])->map(fn($b)=>\App\Services\StripeMoney::display((int)$b['amount'],$b['currency']))->implode(' · '),
                    'Webhooks'=>collect($webhooks['data']??[])->map(fn($w)=>['url'=>$w['url']??'','status'=>$w['status']??'','events'=>$w['enabled_events']??[]])->all()];
            } else {
                $info=$kalendia->request('GET','connection');
                $connection=\Illuminate\Support\Arr::only($info,['name','platform','business_id','business_name','scopes','permissions','rate_limit','rate_limit_per_minute','expires_at']);
            }
        } catch (\Throwable $e) { return back()->withErrors(['connection'=>'No se pudo completar la comprobación: '.PlatformMirror::errorCode($e).'. Revisa la credencial y sus permisos.']); }
        return back()->with('platform_connection',$connection)->with('success','Conexión comprobada. No se modificaron registros externos.');
    }
}
