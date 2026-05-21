@extends('adminlte::page')

@section('title', 'Herramientas GEDCOM')

@section('content_header')
@stop

@section('content')
<x-app-layout>
    <div class="sefar-page gedcom-page">
        <div class="sefar-page-header">
            <div>
                <p class="sefar-eyebrow">Genealogia</p>
                <h1>Herramientas GEDCOM</h1>
                <p>Exporta arboles completos, valida archivos GEDCOM y conviertelos al modelo actual de agclientes.</p>
            </div>
        </div>

        <div class="sefar-grid sefar-grid-2">
            <section class="sefar-card">
                <div class="sefar-card-header">
                    <div>
                        <h2>Exportar</h2>
                        <p>Genera un GEDCOM 5.5.5 con individuos, familias, eventos principales y enlaces padre/madre.</p>
                    </div>
                    <i class="fas fa-file-export"></i>
                </div>

                @can('descargarGedcom')
                    <a href="{{ route('getGedcomGlobal') }}" class="sefar-button sefar-button-primary">
                        <i class="fas fa-download"></i>
                        Descargar GEDCOM global
                    </a>
                @else
                    <div class="sefar-empty">No tienes permiso para descargar el GEDCOM global.</div>
                @endcan
            </section>

            <section class="sefar-card">
                <div class="sefar-card-header">
                    <div>
                        <h2>Importar y validar</h2>
                        <p>Primero valida el archivo. Si todo esta bien, puedes importarlo a un IDCliente destino.</p>
                    </div>
                    <i class="fas fa-file-import"></i>
                </div>

                <form action="{{ route('gedcom.import') }}" method="POST" enctype="multipart/form-data" class="sefar-form">
                    @csrf

                    <label>
                        <span>Archivo GEDCOM</span>
                        <input type="file" name="gedcom_file" accept=".ged,.gedcom,text/*" required>
                    </label>

                    <label>
                        <span>IDCliente destino</span>
                        <input type="text" name="IDCliente" value="{{ old('IDCliente') }}" placeholder="Pasaporte o IDCliente para guardar el arbol">
                    </label>

                    <label class="sefar-check">
                        <input type="checkbox" name="replace_existing" value="1" @checked(old('replace_existing'))>
                        <span>Reemplazar arbol existente para ese IDCliente</span>
                    </label>

                    <div class="sefar-actions">
                        <button type="submit" name="mode" value="validate" class="sefar-button sefar-button-secondary">
                            <i class="fas fa-check-circle"></i>
                            Validar
                        </button>
                        <button type="submit" name="mode" value="import" class="sefar-button sefar-button-primary">
                            <i class="fas fa-database"></i>
                            Importar
                        </button>
                    </div>
                </form>
            </section>
        </div>

        @if($gedcomResult || !empty($gedcomErrors) || !empty($gedcomWarnings))
            <section class="sefar-card gedcom-result">
                <div class="sefar-card-header">
                    <div>
                        <h2>Resultado</h2>
                        <p>Resumen de la ultima validacion o importacion.</p>
                    </div>
                    <i class="fas fa-list-check"></i>
                </div>

                @if($gedcomResult)
                    <div class="sefar-metrics">
                        <div>
                            <strong>{{ $gedcomResult['individuals_count'] ?? 0 }}</strong>
                            <span>Individuos</span>
                        </div>
                        <div>
                            <strong>{{ $gedcomResult['families_count'] ?? 0 }}</strong>
                            <span>Familias</span>
                        </div>
                        <div>
                            <strong>{{ $gedcomResult['created'] ?? 0 }}</strong>
                            <span>Importados</span>
                        </div>
                        <div>
                            <strong>{{ $gedcomResult['errors_count'] ?? count($gedcomErrors) }}</strong>
                            <span>Errores</span>
                        </div>
                    </div>
                @endif

                @if(!empty($gedcomErrors))
                    <div class="sefar-alert sefar-alert-error">
                        <strong>Errores</strong>
                        <ul>
                            @foreach($gedcomErrors as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(!empty($gedcomWarnings))
                    <div class="sefar-alert sefar-alert-warning">
                        <strong>Advertencias</strong>
                        <ul>
                            @foreach($gedcomWarnings as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(!empty($gedcomPreview))
                    <div class="sefar-table-wrap">
                        <table class="sefar-table">
                            <thead>
                                <tr>
                                    <th>XREF</th>
                                    <th>Nombre</th>
                                    <th>Sexo</th>
                                    <th>Nacimiento</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($gedcomPreview as $person)
                                    <tr>
                                        <td>{{ $person['xref'] }}</td>
                                        <td>{{ $person['name'] ?: 'Sin nombre' }}</td>
                                        <td>{{ $person['sex'] }}</td>
                                        <td>{{ $person['birth'] ?: 'N/D' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop
