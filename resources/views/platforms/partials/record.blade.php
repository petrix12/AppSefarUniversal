@php($data=$record->data)
<h3 class="h5">{{ $record->name ?: $record->remote_id }}</h3>
<p class="text-muted platform-value">{{ $record->remote_id }} · {{ \App\Services\PlatformPresentation::status($record->status) }}</p>
@if($record->email)<p class="platform-value">{{ $record->email }}</p>@endif
@if(\App\Services\PlatformPresentation::amount($provider,$data)!=='—')<p class="h4 mb-3">{{ \App\Services\PlatformPresentation::amount($provider,$data) }}</p>@endif
<form action="{{ route('admin.platforms.refresh',[$provider,$account,$record]) }}" method="POST" data-platform-form class="mb-3">@csrf<button class="btn btn-outline-primary" type="submit">Actualizar ficha desde {{ $platformName }}</button></form>
<details class="mb-3"><summary>Datos del registro</summary><dl class="platform-data mt-3">@foreach($data as $key=>$value)
    @if($value!==null && !in_array($key,['object','livemode','virtual_meeting_url'],true))
        <div><dt>{{ \App\Services\PlatformPresentation::label($key) }}</dt><dd class="platform-value" @if($instant=\App\Services\PlatformPresentation::instant($key,$value)) data-kalendia-instant="{{ $instant }}" @endif>{{ \App\Services\PlatformPresentation::value($provider,$key,$value,$data) }}</dd></div>
    @endif
@endforeach</dl></details>
@if(!$readOnly && $provider==='stripe' && auth()->user()->isSuperadmin() && $record->resource==='charges'
    && ($data['paid'] ?? false) && ($data['captured'] ?? false) && ($data['amount'] ?? 0) > ($data['amount_refunded'] ?? 0))
<details class="mb-3"><summary class="text-danger">Reembolsar este pago <i class="fas fa-crown ml-1" aria-label="Solo superadmins"></i></summary>
    <form action="{{ route('admin.platforms.operate',[$provider,$account,$record]) }}" method="POST" data-platform-form class="mt-3">@csrf
        <input type="hidden" name="operation_id" value="{{ $uuid }}"><input type="hidden" name="action" value="refund">
        <p>Saldo reembolsable guardado: <strong>{{ \App\Services\StripeMoney::display(max(0,(int)($data['amount']??0)-(int)($data['amount_refunded']??0)),$data['currency']??'eur') }}</strong>. Se comprobará nuevamente en Stripe antes de enviar la solicitud.</p>
        <div class="form-group"><label for="refund-amount">Importe a devolver · {{ strtoupper($data['currency']??'') }}</label><input class="form-control" id="refund-amount" name="amount" inputmode="decimal" placeholder="50.00" required pattern="[0-9]+([.][0-9]{1,3})?"></div>
        <div class="form-group"><label for="refund-reason">Motivo</label><select class="form-control" id="refund-reason" name="reason"><option value="requested_by_customer">Solicitud del representado</option><option value="duplicate">Pago duplicado</option><option value="fraudulent">Operación fraudulenta</option></select></div>
        <div class="form-group"><label for="refund-comment">Justificación interna</label><textarea class="form-control" id="refund-comment" name="comment" required maxlength="1000"></textarea></div>
        <label class="d-block"><input type="checkbox" name="confirmed" value="1" required> Confirmo el importe, la moneda y la devolución al medio de pago original.</label><button class="btn btn-danger" type="submit">Confirmar reembolso</button>
    </form>
</details>
@endif
@if($provider==='kalendia' && $record->resource==='forms' && is_array($data['fields']??null))
<details class="mb-3"><summary>Registrar respuestas para una cita</summary><form method="POST" action="{{ route('admin.platforms.operate',[$provider,$account,$record]) }}" data-platform-form class="mt-3">@csrf<input type="hidden" name="operation_id" value="{{ $uuid }}"><input type="hidden" name="action" value="submission_create">
    @foreach(['appointment_id'=>'Identificador de cita','customer_id'=>'Identificador de representado','service_id'=>'Identificador de servicio'] as $field=>$label)<div class="form-group"><label for="form-{{ $field }}">{{ $label }}</label><input class="form-control" id="form-{{ $field }}" name="{{ $field }}" required></div>@endforeach
    @foreach($data['fields'] as $field)@if(is_array($field) && is_string($field['id']??null))<div class="form-group"><label for="schema-answer-{{ $loop->index }}">{{ $field['label']??$field['name']??$field['id'] }}</label><textarea class="form-control" id="schema-answer-{{ $loop->index }}" name="values[{{ $field['id'] }}]" @required($field['required']??false) maxlength="10000"></textarea></div>@endif @endforeach
    <label class="d-block"><input type="checkbox" name="confirmed" value="1" required> Confirmo las respuestas y su relación con esta cita.</label><button class="btn btn-primary" type="submit">Registrar respuestas</button>
</form></details>
@endif
@if(!$readOnly && $provider==='stripe' && auth()->user()->isSuperadmin() && $record->resource==='payment_intents' && in_array($record->status,['requires_payment_method','requires_confirmation','requires_action','requires_capture'],true))
<details class="mb-3"><summary class="text-danger">Cancelar intento de pago <i class="fas fa-crown ml-1" aria-label="Solo superadmins"></i></summary>
    <form method="POST" action="{{ route('admin.platforms.operate',[$provider,$account,$record]) }}" data-platform-form class="mt-3">@csrf<input type="hidden" name="operation_id" value="{{ $uuid }}"><input type="hidden" name="action" value="cancel_payment">
        <div class="form-group"><label for="cancel-reason">Motivo</label><select class="form-control" id="cancel-reason" name="reason"><option value="requested_by_customer">Solicitud del representado</option><option value="duplicate">Duplicado</option><option value="abandoned">Abandonado</option></select></div>
        <div class="form-group"><label for="cancel-comment">Justificación interna</label><textarea class="form-control" id="cancel-comment" name="comment" required maxlength="1000"></textarea></div>
        <label class="d-block"><input type="checkbox" name="confirmed" value="1" required> Confirmo la cancelación y la liberación de fondos retenidos, si corresponde.</label><button class="btn btn-danger" type="submit">Confirmar cancelación</button>
    </form>
</details>
@endif
@if(!$readOnly && $record->resource==='customers' && $record->status!=='deleted')
<details class="mb-3"><summary>Editar contacto en {{ $platformName }}</summary>
    <form method="POST" action="{{ route('admin.platforms.operate',[$provider,$account,$record]) }}" data-platform-form class="mt-3">@csrf<input type="hidden" name="operation_id" value="{{ $uuid }}"><input type="hidden" name="action" value="customer_update">
        <div class="form-group"><label for="customer-name">Nombre</label><input class="form-control" id="customer-name" name="name" value="{{ $data['name']??'' }}" required maxlength="190"></div>
        @if($provider==='stripe')
            <div class="form-group"><label for="customer-email">Correo</label><input class="form-control" id="customer-email" name="email" type="email" value="{{ $data['email']??'' }}" maxlength="190"></div>
            <div class="form-group"><label for="customer-phone">Teléfono</label><input class="form-control" id="customer-phone" name="phone" value="{{ $data['phone']??'' }}" maxlength="80"></div>
            <div class="form-group"><label for="customer-description">Descripción</label><textarea class="form-control" id="customer-description" name="description" maxlength="1000">{{ $data['description']??'' }}</textarea></div>
        @else
            <p class="text-muted">Se conserva el correo y teléfono originales para identificar el mismo contacto en Kalendia.</p>
            <div class="form-group"><label for="customer-address">Dirección</label><textarea class="form-control" id="customer-address" name="address" maxlength="1000">{{ $data['address']??'' }}</textarea></div>
            <div class="form-group"><label for="customer-timezone">Zona horaria</label><select class="form-control" id="customer-timezone" name="timezone">@foreach(array_unique([$data['timezone']??'America/Bogota','America/Bogota','America/Caracas','America/Santiago','Europe/Madrid']) as $zone)<option>{{ $zone }}</option>@endforeach</select></div>
            @foreach($data['properties']??[] as $key=>$value)@if(is_scalar($value)||$value===null)<div class="form-group"><label for="property-{{ $loop->index }}">{{ $key }}</label><input class="form-control" id="property-{{ $loop->index }}" name="properties[{{ $key }}]" value="{{ $value }}" maxlength="2000"></div>@endif @endforeach
        @endif
        <label class="d-block"><input type="checkbox" name="confirmed" value="1" required> Confirmo la actualización de este contacto en {{ $platformName }}.</label><button class="btn btn-primary" type="submit">Guardar contacto</button>
    </form>
</details>
@if($provider==='kalendia')
    <div class="mb-3"><button class="btn btn-outline-primary" type="button" data-platform-notes="{{ route('admin.platforms.notes',[$provider,$account,$record]) }}">Consultar y gestionar notas</button><div data-platform-notes-content></div></div>
    @include('platforms.partials.booking')
@endif
@endif
@if($provider==='kalendia' && $record->resource==='submissions' && is_array($data['values']??null))
<details class="mb-3"><summary>Editar respuestas del formulario</summary><form method="POST" action="{{ route('admin.platforms.operate',[$provider,$account,$record]) }}" data-platform-form class="mt-3">@csrf<input type="hidden" name="operation_id" value="{{ $uuid }}"><input type="hidden" name="action" value="submission_update">
    @foreach($data['values'] as $key=>$value)@if(is_scalar($value)||$value===null)<div class="form-group"><label for="answer-{{ $loop->index }}">{{ $key }}</label><textarea class="form-control" id="answer-{{ $loop->index }}" name="values[{{ $key }}]" maxlength="10000">{{ $value }}</textarea></div>@endif @endforeach
    <label class="d-block"><input type="checkbox" name="confirmed" value="1" required> Confirmo los cambios de estas respuestas.</label><button class="btn btn-primary" type="submit">Guardar respuestas</button>
</form></details>
@endif
@if($provider==='kalendia' && $record->resource==='appointments')
    @php($localAppointment=\App\Models\KalendiaAppointment::where('remote_id',$record->remote_id)->first())
    @if($localAppointment)
        @php($localOperation=\App\Models\KalendiaAppointmentAction::where('appointment_id',$localAppointment->id)->whereIn('status',['processing','uncertain'])->latest()->first())
        <div data-platform-appointment-forms>@include('kalendia.admin.partials.appointment-details',['appointment'=>$localAppointment,'operation'=>$localOperation,'operationId'=>$localOperation?->uuid ?: (string)\Illuminate\Support\Str::uuid()])</div>
    @endif
@endif
@if($operations->isNotEmpty())<hr><h3 class="h6">Historial de operaciones</h3>
@foreach($operations as $operation)<div class="mb-3"><strong>{{ \App\Services\PlatformPresentation::status($operation->status) }}</strong> · {{ $operation->actor?->name }} · {{ $operation->created_at->format('d/m/Y H:i') }} UTC
    @if(data_get($operation->payload,'input.comment'))<p class="mb-1">{{ data_get($operation->payload,'input.comment') }}</p>@endif
    <small class="d-block platform-value">{{ $operation->uuid }} @if($operation->remote_id)· {{ $operation->remote_id }}@endif @if($operation->error_code)· {{ $operation->error_code }}@endif</small>
    @if(!$readOnly && in_array($operation->status,['uncertain','processing'],true) && (!in_array($operation->action,['refund','cancel_payment'],true)||auth()->user()->isSuperadmin()))
        <form method="POST" action="{{ route('admin.platforms.operation.retry',[$provider,$account,$operation]) }}" data-platform-form class="mt-2">@csrf<label class="d-block"><input type="checkbox" name="confirmed" value="1" required> Confirmo reintentar la misma operación.</label><button class="btn btn-outline-primary" type="submit">Comprobar operación pendiente</button></form>
    @endif
</div>@endforeach @endif
