@extends('adminlte::page')

@section('title', 'GEDCOM')

@section('content_header')
@stop

@section('content')
    <div class="gedcom-workbench">
        <header class="gedcom-topbar">
            <div>
                <span class="gedcom-kicker">Genealogia</span>
                <h1>GEDCOM</h1>
            </div>

            @can('descargarGedcom')
                <a href="{{ route('getGedcomGlobal') }}" class="gedcom-button gedcom-button-primary">
                    <i class="fas fa-download"></i>
                    <span>Global</span>
                </a>
            @endcan
        </header>

        <div class="gedcom-shell">
            <section class="gedcom-panel gedcom-import-panel">
                <div class="gedcom-panel-title">
                    <i class="fas fa-file-import"></i>
                    <h2>Importar</h2>
                </div>

                <form action="{{ route('gedcom.import') }}" method="POST" enctype="multipart/form-data" class="gedcom-form">
                    @csrf

                    <div class="gedcom-field-grid">
                        <label class="gedcom-field gedcom-file-field">
                            <span>Archivo</span>
                            <input type="file" name="gedcom_file" accept=".ged,.gedcom,text/*" required>
                        </label>

                        <label class="gedcom-field">
                            <span>IDCliente</span>
                            <input type="text" name="IDCliente" value="{{ old('IDCliente') }}" placeholder="Pasaporte o IDCliente">
                        </label>
                    </div>

                    <div class="gedcom-form-footer">
                        <label class="gedcom-check">
                            <input type="checkbox" name="replace_existing" value="1" @checked(old('replace_existing'))>
                            <span>Reemplazar arbol existente</span>
                        </label>

                        <div class="gedcom-actions">
                            <button type="submit" name="mode" value="validate" class="gedcom-button gedcom-button-secondary">
                                <i class="fas fa-check-circle"></i>
                                <span>Validar</span>
                            </button>
                            <button type="submit" name="mode" value="import" class="gedcom-button gedcom-button-primary">
                                <i class="fas fa-database"></i>
                                <span>Importar</span>
                            </button>
                        </div>
                    </div>
                </form>
            </section>

            <aside class="gedcom-side">
                <section class="gedcom-panel gedcom-mini-panel">
                    <div class="gedcom-panel-title">
                        <i class="fas fa-file-export"></i>
                        <h2>Exportar</h2>
                    </div>

                    @can('descargarGedcom')
                        <a href="{{ route('getGedcomGlobal') }}" class="gedcom-export-row">
                            <span>
                                <strong>Base completa</strong>
                                <small>AppSefarGlobal.ged</small>
                            </span>
                            <i class="fas fa-arrow-down"></i>
                        </a>
                    @else
                        <div class="gedcom-empty">Sin permiso de descarga.</div>
                    @endcan
                </section>

                <section class="gedcom-panel gedcom-mini-panel">
                    <div class="gedcom-panel-title">
                        <i class="fas fa-sitemap"></i>
                        <h2>Mapeo</h2>
                    </div>
                    <div class="gedcom-map-list">
                        <span>INDI</span>
                        <span>FAM</span>
                        <span>BIRT</span>
                        <span>BAPM</span>
                        <span>DEAT</span>
                        <span>MARR</span>
                    </div>
                </section>
            </aside>
        </div>

        @if($gedcomResult || !empty($gedcomErrors) || !empty($gedcomWarnings))
            <section class="gedcom-panel gedcom-result-panel">
                <div class="gedcom-panel-title">
                    <i class="fas fa-list-check"></i>
                    <h2>Resultado</h2>
                </div>

                @if($gedcomResult)
                    <div class="gedcom-metrics">
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
                    <div class="gedcom-alert gedcom-alert-error">
                        <strong>Errores</strong>
                        <ul>
                            @foreach($gedcomErrors as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(!empty($gedcomWarnings))
                    <div class="gedcom-alert gedcom-alert-warning">
                        <strong>Advertencias</strong>
                        <ul>
                            @foreach($gedcomWarnings as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(!empty($gedcomPreview))
                    <div class="gedcom-table-wrap">
                        <table class="gedcom-table">
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
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop
