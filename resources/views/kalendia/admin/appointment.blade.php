@extends('adminlte::page')
@section('title', 'Gestionar cita')
@section('content_header')<h1>Gestionar cita</h1>@stop
@section('content')
    @include('kalendia.partials.messages')
    <div class="card"><div class="card-body">
        @include('kalendia.partials.timezone')
        @include('kalendia.admin.partials.appointment-details')
    </div><div class="card-footer"><a href="{{ route('admin.kalendia.appointments', ['client'=>$appointment->user_id]) }}" class="btn btn-outline-secondary">Volver a reuniones</a></div></div>
@stop
