@extends('adminlte::page')

@section('title', 'Documentos')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Biblioteca de Documentos</h1>

        @can('docs.upload')
            <a href="{{ route('docs.admin') }}" class="btn btn-sm btn-primary">
                <i class="fas fa-cog"></i> Administrar
            </a>
        @endcan
    </div>
@stop

@section('content')

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card p-4">
        <form method="GET" action="{{ route('docs.index') }}" class="mb-3">
            <div class="row">
                <div class="col-md-6 mb-2">
                    <div class="input-group">
                        <input type="text" name="q" class="form-control" placeholder="Buscar documento..." value="{{ request('q') }}">
                        <div class="input-group-append">
                            <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-2">
                    <select name="category" class="form-control" onchange="this.form.submit()">
                        <option value="">Todas las categorías</option>
                        @foreach($categories as $c)
                            <option value="{{ $c }}" @selected(request('category')==$c)>{{ ucfirst($c) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 mb-2">
                    <a href="{{ route('docs.index') }}" class="btn btn-outline-secondary btn-block">
                        Limpiar
                    </a>
                </div>
            </div>
        </form>

        @php
            $iconFor = function($mime, $name) {
                $n = strtolower($name ?? '');
                $m = strtolower($mime ?? '');

                if (str_contains($m, 'pdf') || str_ends_with($n, '.pdf')) return 'far fa-file-pdf text-danger';
                if (str_contains($m, 'word') || str_ends_with($n, '.doc') || str_ends_with($n, '.docx')) return 'far fa-file-word text-primary';
                if (str_contains($m, 'excel') || str_ends_with($n, '.xls') || str_ends_with($n, '.xlsx') || str_ends_with($n, '.csv')) return 'far fa-file-excel text-success';
                if (str_contains($m, 'powerpoint') || str_ends_with($n, '.ppt') || str_ends_with($n, '.pptx')) return 'far fa-file-powerpoint text-warning';
                if (str_contains($m, 'image') || preg_match('/\.(png|jpg|jpeg|webp|gif)$/', $n)) return 'far fa-file-image text-info';
                return 'far fa-file-alt';
            };

            $fmtSize = function($bytes) {
                $bytes = (int) $bytes;
                if ($bytes <= 0) return '—';
                if ($bytes < 1024) return $bytes.' B';
                if ($bytes < 1024*1024) return number_format($bytes/1024, 1).' KB';
                return number_format($bytes/(1024*1024), 1).' MB';
            };
        @endphp

        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Categoría</th>
                            <th>Tamaño</th>
                            <th>Fecha</th>
                            <th class="text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($docs as $doc)
                        <tr>
                            <td>
                                <i class="{{ $iconFor($doc->mime_type, $doc->original_name) }} mr-2"></i>
                                <b>{{ $doc->title }}</b><br>
                                <small class="text-muted">{{ $doc->original_name }}</small>
                            </td>
                            <td>{{ ucfirst($doc->category ?? 'general') }}</td>
                            <td>{{ $fmtSize($doc->size) }}</td>
                            <td>{{ optional($doc->created_at)->format('d/m/Y') }}</td>
                            <td class="text-right">
                                <a href="{{ route('docs.download', $doc->id) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-download"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted p-4">
                                No hay documentos disponibles
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($docs->hasPages())
                <div class="card-footer">
                    {{ $docs->links() }}
                </div>
            @endif
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
@stop
