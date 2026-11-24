@extends('adminlte::page')

@section('title', $activity->exists ? 'Editar actividad CRM' : 'Nueva actividad CRM')

@section('content_header')
    <h1><i class="fas fa-history mr-2 text-primary"></i>{{ $activity->exists ? 'Editar actividad' : 'Registrar actividad' }}</h1>
@stop

@section('content')
    <div class="card card-outline card-primary"><div class="card-body">
        <form method="POST" action="{{ $action }}">
            @csrf @if($method !== 'POST') @method($method) @endif
            @include('crm.partials.contact-picker', ['leadId' => $activity->lead_id, 'clientId' => $activity->client_id])
            <div class="form-row">
                <div class="form-group col-md-4"><label>Tipo</label><select name="type" class="form-control" required>@foreach(\App\Models\CrmActivity::TYPES as $key => $label)<option value="{{ $key }}" @selected(old('type', $activity->type) === $key)>{{ $label }}</option>@endforeach</select>@error('type')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="form-group col-md-8"><label>Asunto</label><input name="subject" class="form-control" value="{{ old('subject', $activity->subject) }}" maxlength="255" required>@error('subject')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="form-group col-md-6"><label>Fecha y hora</label><input type="datetime-local" name="occurred_at" class="form-control" value="{{ old('occurred_at', optional($activity->occurred_at)->format('Y-m-d\TH:i')) }}" required>@error('occurred_at')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="form-group col-md-6"><label>Próximo seguimiento (opcional)</label><input type="datetime-local" name="follow_up_at" class="form-control" value="{{ old('follow_up_at', optional($activity->follow_up_at)->format('Y-m-d\TH:i')) }}">@error('follow_up_at')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="form-group col-12"><label>Detalle</label><textarea name="details" class="form-control" rows="4" maxlength="10000">{{ old('details', $activity->details) }}</textarea>@error('details')<small class="text-danger">{{ $message }}</small>@enderror</div>
            </div>
            <a href="{{ route('crm.activities.index') }}" class="btn btn-outline-secondary">Volver</a>
            <button class="btn btn-primary">Guardar actividad</button>
        </form>
    </div></div>
@stop
