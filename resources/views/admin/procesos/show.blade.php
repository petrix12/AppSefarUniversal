@extends('adminlte::page')

@section('title', 'Editor Proceso')

@section('content_header')
  <h1>{{ $cos->nombre }}</h1>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
@endsection

@push('js')
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
<script>
  function initTinyMCE() {
    tinymce.init({
      selector: 'textarea[id^="promesa-"]',
      height: 220,
      menubar: false,
      branding: false,
      plugins: 'lists link code',
      toolbar: 'undo redo | bold italic underline | bullist numlist | link | removeformat | code',
      content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }'
    });
  }

  document.addEventListener('DOMContentLoaded', initTinyMCE);

  // Asegura que el HTML se copie al textarea antes del submit
  document.addEventListener('submit', function () {
    if (window.tinymce) {
      tinymce.triggerSave();
    }
  }, true);
</script>
@endpush

@section('content')
    @if($errors->any())
        <div class="alert alert-danger mt-2">
            <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
            </ul>
        </div>
    @endif

  @foreach($cos->fases as $fase)
    <div class="card">
      <div class="card-header">
        <b>Fase {{ $fase->titulo }}</b>
      </div>

      <div class="card-body">
        <div class="accordion" id="accordion-fase-{{ $fase->id }}">
          @foreach($fase->pasos as $paso)
            <div class="card">
              <div class="card-header" id="heading-{{ $paso->id }}">
                <h2 class="mb-0 d-flex justify-content-between align-items-center">
                  <button class="btn btn-link" type="button" data-toggle="collapse"
                          data-target="#collapse-{{ $paso->id }}" aria-expanded="false">
                    <b>#{{ $paso->numero }}</b> — {{ $paso->titulo }}
                    @if($paso->nombre_corto)
                      <span class="badge badge-info ml-2">{{ $paso->nombre_corto }}</span>
                    @endif
                  </button>
                </h2>
              </div>

              <div id="collapse-{{ $paso->id }}" class="collapse"
                   data-parent="#accordion-fase-{{ $fase->id }}">
                <div class="card-body">

                  <form method="POST" action="{{ route('admin.procesos.pasos.updateFull', $paso) }}">
                    @csrf
                    @method('PUT')

                    {{-- Campos del paso --}}
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
                        <textarea class="form-control" id="promesa-{{ $paso->id }}" name="promesa" rows="6">{!! $paso->promesa !!}</textarea>
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

                    <hr>

                    {{-- CTAs --}}
                    <div class="d-flex justify-content-between align-items-center">
                      <h5 class="mb-2">CTAs</h5>
                      <button type="button" class="btn btn-sm btn-outline-primary"
                              onclick="addRow('ctas-{{ $paso->id }}', 'cta')">+ Agregar CTA</button>
                    </div>

                    <div id="ctas-{{ $paso->id }}">
                      @php $ctas = $paso->items->where('tipo','cta')->values(); @endphp
                      @foreach($ctas as $i => $cta)
                        <div class="border rounded p-2 mb-2 row-item" data-kind="cta">
                          <input type="hidden" name="ctas[{{ $i }}][id]" value="{{ $cta->id }}">

                          <div class="form-row">
                            <div class="form-group col-md-2">
                              <label>Orden</label>
                              <input class="form-control" name="ctas[{{ $i }}][orden]" value="{{ $cta->orden }}">
                            </div>
                            <div class="form-group col-md-5">
                              <label>Texto</label>
                              <input class="form-control" name="ctas[{{ $i }}][texto]" value="{{ $cta->texto }}">
                            </div>
                            <div class="form-group col-md-4">
                              <label>URL</label>
                              <input class="form-control" name="ctas[{{ $i }}][url]" value="{{ $cta->url }}">
                            </div>
                            <div class="form-group col-md-1 d-flex align-items-end">
                              <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button>
                            </div>
                          </div>
                        </div>
                      @endforeach
                    </div>

                    <hr>

                    {{-- Subfases --}}
                    <div class="d-flex justify-content-between align-items-center">
                      <h5 class="mb-2">Subfases</h5>
                      <button type="button" class="btn btn-sm btn-outline-primary"
                              onclick="addRow('subfases-{{ $paso->id }}', 'subfase')">+ Agregar Subfase</button>
                    </div>

                    <div id="subfases-{{ $paso->id }}">
                      @foreach($paso->subfases->values() as $i => $sf)
                        <div class="border rounded p-2 mb-2 row-item" data-kind="subfase">
                          <input type="hidden" name="subfases[{{ $i }}][id]" value="{{ $sf->id }}">

                          <div class="form-row">
                            <div class="form-group col-md-2">
                              <label>Orden</label>
                              <input class="form-control" name="subfases[{{ $i }}][orden]" value="{{ $sf->orden }}">
                            </div>
                            <div class="form-group col-md-9">
                              <label>Título</label>
                              <input class="form-control" name="subfases[{{ $i }}][titulo]" value="{{ $sf->titulo }}">
                            </div>
                            <div class="form-group col-md-1 d-flex align-items-end">
                              <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button>
                            </div>
                          </div>
                        </div>
                      @endforeach
                    </div>

                    <hr>

                    {{-- Textos adicionales --}}
                    <div class="d-flex justify-content-between align-items-center">
                      <h5 class="mb-2">Textos adicionales</h5>
                      <button type="button" class="btn btn-sm btn-outline-primary"
                              onclick="addRow('textos-{{ $paso->id }}', 'texto')">+ Agregar Texto</button>
                    </div>

                    <div id="textos-{{ $paso->id }}">
                      @foreach($paso->textosAdicionales->values() as $i => $t)
                        <div class="border rounded p-2 mb-2 row-item" data-kind="texto">
                          <input type="hidden" name="textos[{{ $i }}][id]" value="{{ $t->id }}">

                          <div class="form-row">
                            <div class="form-group col-md-2">
                              <label>Orden</label>
                              <input class="form-control" name="textos[{{ $i }}][orden]" value="{{ $t->orden }}">
                            </div>
                            <div class="form-group col-md-4">
                              <label>Nombre</label>
                              <input class="form-control" name="textos[{{ $i }}][nombre]" value="{{ $t->nombre }}">
                            </div>
                            <div class="form-group col-md-5">
                              <label>Texto</label>
                              <input class="form-control" name="textos[{{ $i }}][texto]" value="{{ $t->texto }}">
                            </div>
                            <div class="form-group col-md-1 d-flex align-items-end">
                              <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button>
                            </div>
                          </div>
                        </div>
                      @endforeach
                    </div>

                    <button class="btn btn-success btn-block mt-3">Guardar este paso</button>
                  </form>

                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  @endforeach
@endsection

@push('js')
<script>
  function removeRow(btn) {
    btn.closest('.row-item').remove();
  }

  function addRow(containerId, kind) {
    const container = document.getElementById(containerId);
    const index = container.querySelectorAll(`.row-item[data-kind="${kind}"]`).length;

    let html = '';

    if (kind === 'cta') {
      html = `
        <div class="border rounded p-2 mb-2 row-item" data-kind="cta">
          <div class="form-row">
            <div class="form-group col-md-2">
              <label>Orden</label>
              <input class="form-control" name="ctas[${index}][orden]" value="${index+1}">
            </div>
            <div class="form-group col-md-5">
              <label>Texto</label>
              <input class="form-control" name="ctas[${index}][texto]" value="">
            </div>
            <div class="form-group col-md-4">
              <label>URL</label>
              <input class="form-control" name="ctas[${index}][url]" value="">
            </div>
            <div class="form-group col-md-1 d-flex align-items-end">
              <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button>
            </div>
          </div>
        </div>
      `;
    }

    if (kind === 'subfase') {
      html = `
        <div class="border rounded p-2 mb-2 row-item" data-kind="subfase">
          <div class="form-row">
            <div class="form-group col-md-2">
              <label>Orden</label>
              <input class="form-control" name="subfases[${index}][orden]" value="${index+1}">
            </div>
            <div class="form-group col-md-9">
              <label>Título</label>
              <input class="form-control" name="subfases[${index}][titulo]" value="">
            </div>
            <div class="form-group col-md-1 d-flex align-items-end">
              <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button>
            </div>
          </div>
        </div>
      `;
    }

    if (kind === 'texto') {
      html = `
        <div class="border rounded p-2 mb-2 row-item" data-kind="texto">
          <div class="form-row">
            <div class="form-group col-md-2">
              <label>Orden</label>
              <input class="form-control" name="textos[${index}][orden]" value="${index+1}">
            </div>
            <div class="form-group col-md-4">
              <label>Nombre</label>
              <input class="form-control" name="textos[${index}][nombre]" value="">
            </div>
            <div class="form-group col-md-5">
              <label>Texto</label>
              <input class="form-control" name="textos[${index}][texto]" value="">
            </div>
            <div class="form-group col-md-1 d-flex align-items-end">
              <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button>
            </div>
          </div>
        </div>
      `;
    }

    container.insertAdjacentHTML('beforeend', html);
  }
</script>
@endpush

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop
