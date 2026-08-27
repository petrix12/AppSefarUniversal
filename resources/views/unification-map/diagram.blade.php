@extends('adminlte::page')

@section('title', 'Diagrama ER')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="mb-0"><i class="fas fa-project-diagram mr-2 text-primary"></i>Diagrama ER</h1>
            <small class="text-muted">Modelo de datos unificado y relaciones de auditoría aprobadas.</small>
        </div>
        <div class="btn-group">
            <a href="{{ route('unification-map.index') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i>Mapa</a>
            <a href="{{ route('unification-map.diagram', ['format' => 'svg', 'download' => 1]) }}" class="btn btn-primary"><i class="fas fa-download mr-1"></i>Descargar SVG</a>
        </div>
    </div>
@stop

@section('content')
    <div class="row">
        <div class="col-md-6 col-lg-3">
            <div class="small-box bg-primary"><div class="inner"><h3>{{ $diagram['approved_relations'] }}</h3><p>Relaciones aprobadas</p></div><div class="icon"><i class="fas fa-check-double"></i></div></div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="small-box bg-warning"><div class="inner"><h3>{{ $diagram['cross_board_monday_relations'] }}</h3><p>Monday entre tableros</p></div><div class="icon"><i class="fab fa-trello"></i></div></div>
        </div>
    </div>

    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title">Entidades y relaciones</h3>
            <div class="card-tools"><span class="text-muted small">Generado {{ $diagram['generated_at'] }}</span></div>
        </div>
        <div class="card-body p-2 er-diagram-wrap">
            <img src="{{ route('unification-map.diagram', ['format' => 'svg']) }}" class="img-fluid" alt="Diagrama ER de unificación">
        </div>
        <div class="card-footer text-muted small">Solo incluye las relaciones de auditoría aprobadas. Las propuestas pendientes no modifican la estructura ni activan sincronizaciones.</div>
    </div>
@stop

@section('css')
<style>
    .er-diagram-wrap { overflow: auto; background: #f8fafc; }
    .er-diagram-wrap img { min-width: 1000px; display: block; }
</style>
@stop
