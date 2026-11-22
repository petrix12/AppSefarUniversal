@extends('adminlte::page')
@section('title', $platformName.($readOnly ? ' · Histórico de España' : ' · Administración'))
@section('content_header')<h1>{{ $platformName }} · @if($readOnly)Histórico de España @else @yield('platform_title', 'Administración') @endif</h1>@stop
@section('css')<link rel="stylesheet" href="{{ asset('css/platform-management.css') }}?v={{ filemtime(public_path('css/platform-management.css')) }}">@stop
@section('content')
<div class="platform-admin">
    @include('kalendia.partials.messages')
    <nav class="platform-toolbar mb-3" aria-label="Administración de {{ $platformName }}">
        <a class="btn btn-outline-primary" href="{{ route('admin.platforms.index', [$provider,$account]) }}">Resumen</a>
        @unless($readOnly)<a class="btn btn-outline-primary" href="{{ route('admin.platforms.monitor', [$provider,$account]) }}">Webhooks y actividad</a>@endunless
        @if($provider==='stripe')
            <a class="btn {{ $account==='main' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('admin.platforms.index',['stripe','main']) }}">Cuenta principal</a>
            <a class="btn {{ $readOnly ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('admin.platforms.index',['stripe','spain_legacy']) }}">España · Solo consulta</a>
        @else
            <a class="btn btn-outline-secondary" href="{{ route('admin.kalendia.appointments') }}">Calendario</a>
            <a class="btn btn-outline-secondary" href="{{ route('admin.kalendia.offers') }}">Consultas y precios</a>
        @endif
    </nav>
    <div class="card"><div class="card-body pb-0">@include('kalendia.partials.timezone')</div></div>
    @if($readOnly)<div class="alert alert-info" role="status">Histórico de España · Solo consulta. Puedes buscar y actualizar el historial de esta cuenta.</div>@endif
    @yield('platform_content')
</div>
<div class="modal fade platform-admin" id="platform-record-modal" tabindex="-1" role="dialog" aria-labelledby="platform-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title h5" id="platform-modal-title">{{ $readOnly ? 'Consultar registro' : 'Gestionar registro' }} · {{ $platformName }}</h2><button class="close" type="button" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
        <div class="modal-body"><div id="platform-feedback" role="status" hidden></div><div id="platform-record-content"></div></div>
        <div class="modal-footer"><button class="btn btn-primary" type="button" data-dismiss="modal">Cerrar</button></div>
    </div></div>
</div>
@stop
@section('js')<script src="{{ asset('js/platform-management.js') }}?v={{ filemtime(public_path('js/platform-management.js')) }}" defer></script>@stop
