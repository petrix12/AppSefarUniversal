@extends('adminlte::page')

@section('title', 'Servicios')

@section('content_header')
    <h1>Servicios disponibles</h1>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($services->isEmpty())
        <div class="alert alert-info">No hay servicios disponibles en este momento.</div>
    @endif

    @foreach($services as $category => $items)
        <h4 class="mt-3 mb-3">{{ ucfirst($category) }}</h4>
        <div class="row">
            @foreach($items as $servicio)
                <div class="col-md-6 col-xl-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start">
                                <h5 class="card-title mb-2">{{ $servicio->nombre }}</h5>
                                <span class="badge badge-light">{{ $servicio->tipo }}</span>
                            </div>
                            <p class="text-muted flex-grow-1">
                                {{ $servicio->descripcion_publica ?: 'Servicio Sefar Universal.' }}
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>{{ number_format((float) $servicio->precio, 2) }} {{ $servicio->moneda ?? 'EUR' }}</strong>
                                <a href="{{ route('service-store.show', $servicio) }}" class="btn btn-primary">
                                    Ver
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop
