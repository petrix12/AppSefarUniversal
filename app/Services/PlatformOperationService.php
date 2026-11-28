<?php
namespace App\Services;

use App\Models\{Compras, KalendiaConsultationOrder, PlatformOperation, PlatformRecord, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformOperationService
{
    public function __construct(private StripeAdminApi $stripe, private KalendiaApi $kalendia, private PlatformMirror $mirror) {}

    public function run(PlatformRecord $record, User $actor, string $uuid, string $action, array $input): PlatformOperation
    {
        abort_unless($actor->hasRole('Administrador') && !$actor->hasRole('Cliente') && $actor->can($record->provider.'.manage'),403);
        abort_if(PlatformIdentity::readOnly($record->provider, $record->account), 403, 'Esta cuenta es de solo consulta.');
        abort_unless($record->mode===PlatformIdentity::mode($record->provider,$record->account),409);
        if (in_array($action,['refund','cancel_payment'],true)) abort_unless($actor->isSuperadmin(),403);
        $replaying=false;
        $operation=DB::transaction(function () use ($record,$actor,$uuid,$action,$input,&$replaying) {
            PlatformRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            $row=PlatformOperation::where('uuid',$uuid)->lockForUpdate()->first();
            if ($row) {
                $replaying=in_array($row->status,['uncertain','processing'],true);
                abort_unless((int)$row->record_id===(int)$record->id && $row->action===$action && data_get($row->payload,'input')===$input,409);
                if ($row->status==='succeeded' || $row->status==='pending') return $row;
                if ($row->status==='rejected') throw ValidationException::withMessages(['operation'=>'Esta operación fue rechazada. Abre una nueva solicitud con los datos corregidos.']);
                // Stripe may prune idempotency keys after 24h. Never blindly replay an uncertain transaction after that window.
                if ($record->provider==='stripe' && $row->created_at->lt(now()->subHours(23))) {
                    throw ValidationException::withMessages(['operation'=>'Operación sin confirmar de más de 23 horas. Revisa el pago y los reembolsos en Stripe antes de continuar.']);
                }
                if ($row->leased_until?->isFuture()) throw ValidationException::withMessages(['operation'=>'La operación sigue en curso.']);
            } else {
                if (PlatformOperation::where('record_id',$record->id)->whereIn('status',['processing','uncertain','pending'])->exists()) {
                    throw ValidationException::withMessages(['operation'=>'Resuelve primero la operación pendiente de este registro.']);
                }
                $row=new PlatformOperation(['uuid'=>$uuid,'record_id'=>$record->id,'actor_id'=>$actor->id,'action'=>$action,'payload'=>['input'=>$input]]);
            }
            $row->forceFill(['status'=>'processing','lease'=>(string)Str::uuid(),'leased_until'=>now()->addMinutes(2)])->save();
            return $row;
        });
        if (in_array($operation->status,['succeeded','pending'],true)) return $operation;
        $sent=false;
        try {
            $prepared=$operation->payload['request']??null;
            if (!$prepared) {
                $prepared=$this->prepare($record,$action,$input);
                if ($record->provider==='stripe' && $action==='refund') $prepared['data']['metadata']['app_operation']=$uuid;
                $operation->forceFill(['payload'=>['input'=>$input,'request'=>$prepared]])->save();
            }
            $sent=true;
            $response=$record->provider==='stripe'
                ? $this->stripe->request($record->account,$prepared['method'],$prepared['path'],$prepared['data'],'sefar-admin-'.$uuid)
                : $this->kalendia->request($prepared['method'],$prepared['path'],$prepared['data'],'sefar-admin-'.$uuid);
            $data=$response[$prepared['response_key']??'']??$response;
            if (in_array($action,['customer_create','customer_update','submission_create','submission_update'],true)) {
                $data=array_replace($prepared['data'],$data);
            }
            $id=$data['id']??null;
            if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{1,190}$/D',$id)) throw new PlatformApiException('unconfirmed_response');
            if (isset($prepared['expected_id']) && $id!==$prepared['expected_id']) throw new PlatformApiException('resource_mismatch');
            if ($action==='refund' && (($data['charge']??null)!==$record->remote_id
                || ($data['amount']??null)!==$prepared['data']['amount'] || ($data['currency']??null)!==($record->data['currency']??null))) {
                throw new PlatformApiException('refund_mismatch');
            }
            if ($action==='cancel_payment' && ($data['status']??null)!=='canceled') throw new PlatformApiException('cancellation_unconfirmed');
            if ($action==='create_appointment' && !isset($data['client_id'])) {
                $read=$this->kalendia->request('GET','appointments',['id'=>$id]);
                if (count($read['items']??[])!==1 || ($read['items'][0]['id']??null)!==$id) throw new PlatformApiException('resource_mismatch');
                $data=$read['items'][0];
            }
            if ($action==='create_appointment' && ($data['client_id']??null)!==$record->remote_id) throw new PlatformApiException('customer_mismatch');
            DB::transaction(function () use ($record,$action,$prepared,$data,$operation,$id) {
                $current=PlatformOperation::whereKey($operation->id)->lockForUpdate()->firstOrFail();
                if ($current->lease!==$operation->lease) throw new PlatformApiException('lease_expired');
                if (!in_array($action,['note_create','note_update'],true)) {
                    $saved=$this->mirror->save($record->provider,$record->account,$prepared['resource'],$data);
                    if ($action==='customer_create') $current->record_id=$saved->id;
                }
                $status=$action==='refund' ? match($data['status']??null) {'succeeded'=>'succeeded','failed','canceled'=>'rejected',default=>'pending'} : 'succeeded';
                $current->forceFill(['status'=>$status,'remote_id'=>$id,'error_code'=>null,'lease'=>null,'leased_until'=>null])->save();
            });
            // A successful refund refreshes the original charge; it never creates a second App purchase or invoice.
            if ($action==='refund') {
                try { $this->mirror->fetch($record->provider,$record->account,$record->resource,$record->remote_id); } catch (\Throwable) {}
            }
            return $operation->fresh();
        } catch (\Throwable $e) {
            $status=$e instanceof PlatformApiException ? $e->httpStatus : ($e instanceof KalendiaApiException ? $e->httpStatus : null);
            $rejected=!$replaying && (!$sent || in_array($status,[400,401,403,404,422],true));
            PlatformOperation::whereKey($operation->id)->where('lease',$operation->lease)->update(['status'=>$rejected ? 'rejected' : 'uncertain',
                'error_code'=>PlatformMirror::errorCode($e),'lease'=>null,'leased_until'=>null]);
            if ($e instanceof ValidationException) throw $e;
            throw ValidationException::withMessages(['operation'=>$rejected ? 'La plataforma rechazó la operación. Revisa los datos y los permisos de la conexión.'
                : 'La plataforma no confirmó el resultado. Reintenta esta misma operación para evitar duplicados.']);
        }
    }

    private function prepare(PlatformRecord $record, string $action, array $input): array
    {
        if ($action==='customer_create' && $record->resource==='customers'
            && str_contains($record->remote_id,'_request_') && $record->status==='draft') {
            $fields=$record->provider==='stripe' ? ['name','email','phone','description'] : ['name','email','phone','address','timezone'];
            return ['method'=>'POST','path'=>'customers','resource'=>'customers','response_key'=>$record->provider==='kalendia' ? 'customer' : '',
                'data'=>array_filter(array_intersect_key($input,array_flip($fields)),fn($v)=>$v!==null && $v!=='')];
        }
        $fresh=$this->mirror->fetch($record->provider,$record->account,$record->resource,$record->remote_id);
        $data=$fresh->data;
        if ($record->provider==='stripe') {
            if ($action==='refund' && $record->resource==='charges') {
                $amount=StripeMoney::minor((string)$input['amount'],(string)($data['currency']??''));
                if (($data['paid']??false)!==true || ($data['captured']??false)!==true || !isset($data['amount'],$data['amount_refunded'])
                    || $amount > (int)$data['amount']-(int)$data['amount_refunded']) throw ValidationException::withMessages(['amount'=>'El importe supera el saldo reembolsable del pago.']);
                return ['method'=>'POST','path'=>'refunds','resource'=>'refunds','data'=>['charge'=>$record->remote_id,'amount'=>$amount,'reason'=>$input['reason'],
                    'metadata'=>['app_operation'=>$input['operation_reference']??'admin']]];
            }
            if ($action==='cancel_payment' && $record->resource==='payment_intents') {
                if (!in_array($data['status']??null,['requires_payment_method','requires_confirmation','requires_action','requires_capture'],true)) {
                    throw ValidationException::withMessages(['operation'=>'Este intento ya no se puede cancelar. Los pagos cobrados se gestionan mediante reembolso.']);
                }
                return ['method'=>'POST','path'=>'payment_intents/'.$record->remote_id.'/cancel','resource'=>'payment_intents','expected_id'=>$record->remote_id,
                    'data'=>['cancellation_reason'=>$input['reason']]];
            }
            if ($action==='customer_update' && $record->resource==='customers') return ['method'=>'POST','path'=>'customers/'.$record->remote_id,
                'resource'=>'customers','expected_id'=>$record->remote_id,'data'=>array_intersect_key($input,array_flip(['name','email','phone','description']))];
        } else {
            if ($action==='customer_update' && $record->resource==='customers') {
                // Kalendia's public upsert has no customer ID parameter. Keep the original match keys to avoid creating another identity.
                $body=array_intersect_key($input,array_flip(['name','address','timezone','properties']));
                foreach (['email','phone'] as $field) if (!empty($data[$field])) $body[$field]=$data[$field];
                if (empty($body['email']) && empty($body['phone'])) throw new PlatformApiException('missing_identity');
                return ['method'=>'POST','path'=>'customers','resource'=>'customers','response_key'=>'customer','expected_id'=>$record->remote_id,'data'=>$body];
            }
            if (in_array($action,['note_create','note_update'],true) && $record->resource==='customers') {
                $path='customers/'.$record->remote_id.'/notes';
                if ($action==='note_update') $path.='/'.$input['note_id'];
                return ['method'=>$action==='note_create' ? 'POST' : 'PATCH','path'=>$path,'response_key'=>'note',
                    'data'=>['text'=>$input['text'],'is_pinned'=>(bool)($input['is_pinned']??false)]];
            }
            if ($action==='submission_update' && $record->resource==='submissions') {
                return ['method'=>'PATCH','path'=>'forms/submissions/'.$record->remote_id,'resource'=>'submissions',
                    'response_key'=>'submission','expected_id'=>$record->remote_id,'data'=>['values'=>$input['values']]];
            }
            if ($action==='submission_create' && $record->resource==='forms') {
                $read=$this->kalendia->request('GET','appointments',['id'=>$input['appointment_id']]);
                $appointment=$read['items'][0]??[];
                if (count($read['items']??[])!==1 || ($appointment['id']??null)!==$input['appointment_id']
                    || ($appointment['client_id']??null)!==$input['customer_id'] || ($appointment['service_id']??null)!==$input['service_id']) {
                    throw ValidationException::withMessages(['appointment_id'=>'La cita, el representado y el servicio deben corresponder al mismo registro de Kalendia.']);
                }
                return ['method'=>'POST','path'=>'forms/submissions','resource'=>'submissions','response_key'=>'submission','data'=>[
                    'form_id'=>$record->remote_id,'appointment_id'=>$input['appointment_id'],'customer_id'=>$input['customer_id'],'service_id'=>$input['service_id'],'values'=>$input['values']]];
            }
            if ($action==='create_appointment' && $record->resource==='customers') {
                app(ClientRestrictionService::class)->assertContactDeliverable($data);
                $service=$this->kalendia->serviceById($input['service_id']);
                if (!$this->kalendia->isFreeService($service)) throw ValidationException::withMessages(['service_id'=>'Para un servicio con precio, utiliza la contratación de consultas y el pago desde la App.']);
                $purchase=new Compras(['source'=>KalendiaConsultationOrder::SOURCE,'metadata'=>['kalendia_consultation'=>[
                    'remote_service_id'=>$input['service_id'],'service_name'=>$service['name']]]]);
                $slot=collect(app(KalendiaRegistrationBookingService::class)->availableSlots($purchase,$input['date'],$input['timezone']))->firstWhere('key',$input['slot']);
                if (!$slot) throw ValidationException::withMessages(['slot'=>'El horario ya no está disponible. Consulta nuevamente la agenda.']);
                $body=['service_id'=>$input['service_id'],'date'=>$slot['date'],'time'=>$slot['time'],'timezone'=>'America/Bogota',
                    'customer'=>array_filter(array_intersect_key($data,array_flip(['name','email','phone','address']))),
                    'notes'=>$input['notes']??'Cita creada por el equipo desde la App.'];
                if ($slot['staff_id']) $body['staff_id']=$slot['staff_id'];
                return ['method'=>'POST','path'=>'appointments','resource'=>'appointments','response_key'=>'appointment','data'=>$body];
            }
        }
        throw new PlatformApiException('unsupported_operation');
    }
}
