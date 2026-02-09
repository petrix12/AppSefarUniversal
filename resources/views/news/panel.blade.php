@extends('adminlte::page')

@section('title', 'Panel de Noticias')

@section('content_header')
  <div class="d-flex justify-content-between align-items-center">
    <h1>Panel de Noticias</h1>

    @if(session('status'))
      <div class="alert alert-success mb-0">{{ session('status') }}</div>
    @endif
  </div>
@endsection

@section('content')

<div class="row">
  {{-- COLUMNA IZQUIERDA: LISTADO --}}
  <div class="col-md-4">
    <div class="card">
      <div class="card-header">
        <b>Noticias</b>
        <small class="text-muted">(editar / previsualizar / eliminar)</small>
      </div>

      <div class="card-body">

        {{-- Buscador --}}
        <form method="GET" action="{{ route('news.admin') }}" class="mb-3">
          <div class="input-group">
            <input type="text" name="q" class="form-control" placeholder="Buscar..." value="{{ request('q') }}">
            <div class="input-group-append">
              <button class="btn btn-primary"><i class="fas fa-search"></i></button>
            </div>
          </div>

          @if(request('q'))
            <a href="{{ route('news.admin') }}" class="btn btn-link px-0 mt-1">Limpiar</a>
          @endif
        </form>

        <ul class="list-group">
          @forelse($news as $n)

            {{-- Item --}}
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div style="min-width:0;">
                <b class="d-block text-truncate" style="max-width: 240px;">
                  {{ $n->title }}
                </b>
                <small class="text-muted">
                  {{ \Illuminate\Support\Str::limit($n->description, 60) }}
                </small>
              </div>

              <div class="d-flex" style="gap:6px;">
                <button class="btn btn-sm btn-outline-primary"
                        data-toggle="collapse"
                        data-target="#news-preview-{{ $n->id }}">
                  Ver
                </button>

                <button class="btn btn-sm btn-outline-secondary"
                        data-toggle="collapse"
                        data-target="#news-edit-{{ $n->id }}">
                  Editar
                </button>

                <form method="POST"
                      action="{{ route('news.destroy', $n) }}"
                      onsubmit="return confirm('¿Eliminar esta noticia?');">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </div>
            </li>

            {{-- PREVIEW (collapse) --}}
            <li class="list-group-item collapse" id="news-preview-{{ $n->id }}">
              <div class="small text-muted mb-2">
                Vista previa (como se vería en el panel de ventas)
              </div>

              @if($n->header_image)
                <img src="{{ $n->header_image }}" class="news-thumb mb-2" alt="header">
              @endif

              <div class="mb-1"><b>{{ $n->title }}</b></div>
              <div style="white-space: pre-wrap;">{{ $n->description }}</div>

              <hr>
              <small class="text-muted">
                Creada: {{ optional($n->created_at)->format('d/m/Y H:i') }}
              </small>
            </li>

            {{-- EDITAR (collapse) --}}
            <li class="list-group-item collapse" id="news-edit-{{ $n->id }}">
              <form method="POST" action="{{ route('news.update', $n) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="form-group">
                  <label>Título</label>
                  <input class="form-control" name="title" value="{{ old('title', $n->title) }}" required>
                </div>

                <div class="form-group">
                  <label>Descripción</label>
                  <textarea class="form-control" name="description" rows="5" required>{{ old('description', $n->description) }}</textarea>
                </div>

                <div class="form-group">
                  <label>Imagen header (opcional)</label>

                  @if($n->header_image)
                    <div class="mb-2">
                      <img src="{{ $n->header_image }}" class="news-thumb" alt="header">
                    </div>
                  @endif

                  <input type="file" name="header_image" accept="image/*" class="form-control">
                  <small class="text-muted">Sube una nueva para reemplazar (S3).</small>
                </div>

                <button class="btn btn-primary btn-block">
                  Guardar cambios
                </button>
              </form>
            </li>

          @empty
            <li class="list-group-item text-center text-muted">
              No hay noticias
            </li>
          @endforelse
        </ul>

      </div>

      @if($news->hasPages())
        <div class="card-footer">
          {{ $news->links() }}
        </div>
      @endif
    </div>
  </div>


  {{-- COLUMNA DERECHA: CREAR NUEVA + INSTRUCCIONES --}}
  <div class="col-md-8">

    <div class="card">
      <div class="card-header">
        <b>Crear noticia</b>
        <small class="text-muted">(solo admins)</small>
      </div>

      <div class="card-body">
        <form method="POST" action="{{ route('news.store') }}" enctype="multipart/form-data">
          @csrf

          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Título</label>
              <input type="text"
                     name="title"
                     class="form-control @error('title') is-invalid @enderror"
                     required
                     value="{{ old('title') }}">
              @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="form-group col-md-6">
              <label>Imagen header (opcional)</label>
              <input type="file"
                     name="header_image"
                     accept="image/*"
                     class="form-control @error('header_image') is-invalid @enderror">
              @error('header_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
              <small class="text-muted">Se guarda en S3. Máx 4MB.</small>
            </div>
          </div>

          <div class="form-group">
            <label>Descripción</label>
            <textarea name="description"
                      rows="8"
                      class="form-control @error('description') is-invalid @enderror"
                      placeholder="Escribe el comunicado interno aquí..."
                      required>{{ old('description') }}</textarea>
            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <button class="btn btn-primary">
            <i class="fas fa-save"></i> Guardar noticia
          </button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><b>Notas</b></div>
      <div class="card-body">
        <ul class="mb-0">
          <li>Las imágenes se almacenan en S3 y se guarda la URL en la BD.</li>
          <li>La previsualización usa exactamente el formato del panel de ventas.</li>
        </ul>
      </div>
    </div>

  </div>
</div>

@endsection

@section('css')
<link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
<style>
  .news-thumb{
    width: 100%;
    height: 160px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    background: #fff;
    display:block;
  }
</style>
@endsection

@section('js')
@endsection
