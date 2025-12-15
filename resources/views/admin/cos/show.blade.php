@extends('adminlte::page')

@section('title', 'Editor COS')

@section('content_header')
  <h1>{{ $cos->nombre }}</h1>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
@endsection

@section('content')
<div class="row">
  {{-- Columna Fases --}}
  <div class="col-md-4">
    <div class="card">
      <div class="card-header"><b>Fases</b> (arrastra para reordenar)</div>
      <div class="card-body">
        <ul id="fases-list" class="list-group">
          @foreach($cos->fases as $fase)
            <li class="list-group-item d-flex justify-content-between align-items-center"
                data-id="{{ $fase->id }}">
              <span>
                <small class="text-muted">Orden: {{ $fase->orden }}</small><br>
                <b>{{ $fase->titulo }}</b>
              </span>
              <button class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#fase-edit-{{ $fase->id }}">
                Editar
              </button>
            </li>

            <li class="list-group-item collapse" id="fase-edit-{{ $fase->id }}">
              <form method="POST" action="{{ route('admin.cos.fases.update', $fase) }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                  <label>Título</label>
                  <input class="form-control" name="titulo" value="{{ $fase->titulo }}">
                </div>

                <div class="form-group">
                  <label>Número (opcional)</label>
                  <input class="form-control" name="numero" value="{{ $fase->numero }}">
                </div>

                <button class="btn btn-primary btn-block">Guardar fase</button>
              </form>
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  </div>

  {{-- Columna Pasos por Fase --}}
  <div class="col-md-8">
    @foreach($cos->fases as $fase)
      <div class="card">
        <div class="card-header">
          <b>Pasos - {{ $fase->titulo }}</b> (arrastra para reordenar)
        </div>
        <div class="card-body">
          <ul class="list-group pasos-list" data-fase-id="{{ $fase->id }}">
            @foreach($fase->pasos as $paso)
              <li class="list-group-item" data-id="{{ $paso->id }}">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <small class="text-muted">#{{ $paso->numero }}</small>
                    <b>{{ $paso->titulo }}</b>
                    @if($paso->nombre_corto)
                      <span class="badge badge-info">{{ $paso->nombre_corto }}</span>
                    @endif
                  </div>

                  <button class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#paso-edit-{{ $paso->id }}">
                    Editar
                  </button>
                </div>

                <div class="collapse mt-3" id="paso-edit-{{ $paso->id }}">
                  <form method="POST" action="{{ route('admin.cos.pasos.update', $paso) }}">
                    @csrf
                    @method('PUT')

                    <div class="form-row">
                      <div class="form-group col-md-2">
                        <label>Número</label>
                        <input class="form-control" name="numero" value="{{ $paso->numero }}">
                      </div>
                      <div class="form-group col-md-10">
                        <label>Título</label>
                        <input class="form-control" name="titulo" value="{{ $paso->titulo }}">
                      </div>
                    </div>

                    <div class="form-group">
                      <label>Nombre corto</label>
                      <input class="form-control" name="nombre_corto" value="{{ $paso->nombre_corto }}">
                    </div>

                    <div class="form-group">
                      <label>Promesa</label>
                      <textarea class="form-control" name="promesa" rows="2">{{ $paso->promesa }}</textarea>
                    </div>

                    <div class="form-row">
                      <div class="form-group col-md-6">
                        <label>Main CTA texto</label>
                        <input class="form-control" name="main_cta_texto" value="{{ $paso->main_cta_texto }}">
                      </div>
                      <div class="form-group col-md-6">
                        <label>Main CTA url</label>
                        <input class="form-control" name="main_cta_url" value="{{ $paso->main_cta_url }}">
                      </div>
                    </div>

                    <button class="btn btn-primary">Guardar paso</button>
                  </form>
                </div>
              </li>
            @endforeach
          </ul>
        </div>
      </div>
    @endforeach
  </div>
</div>
@endsection

@push('js')
  {{-- SortableJS (CDN). Si prefieres local, lo metemos en public/vendor --}}
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

  <script>
    /*
    const csrf = @json(csrf_token());

    // Reordenar fases
    new Sortable(document.getElementById('fases-list'), {
      animation: 150,
      onEnd: async function () {
        const ids = Array.from(document.querySelectorAll('#fases-list > li[data-id]')).map(li => li.dataset.id);

        await fetch(@json(route('admin.cos.reorder.fases')), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
          body: JSON.stringify({ cos_id: {{ $cos->id }}, ids })
        });
      }
    });

    // Reordenar pasos por fase
    document.querySelectorAll('.pasos-list').forEach((ul) => {
      new Sortable(ul, {
        animation: 150,
        onEnd: async function () {
          const faseId = ul.dataset.faseId;
          const ids = Array.from(ul.querySelectorAll('li[data-id]')).map(li => li.dataset.id);

          await fetch(@json(route('admin.cos.reorder.pasos')), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ fase_id: parseInt(faseId), ids })
          });
        }
      });
    });

    */
  </script>
@endpush
