@extends('adminlte::page')

@section('title', 'HubSpot Owners (manual)')

@section('content_header')
  <div class="d-flex justify-content-between align-items-center">
    <h1 class="m-0 text-dark" style="font-size:1.4rem;">
      <i class="fas fa-users-cog mr-2 text-primary"></i>
      HubSpot Owners
      <small class="text-muted ml-1" style="font-size:.6em; vertical-align:middle;">manual</small>
    </h1>
    <a class="btn btn-primary btn-sm px-4" href="{{ route('hubspot-owners.create') }}">
      <i class="fas fa-plus mr-1"></i> Nuevo Owner
    </a>
  </div>
@stop

@section('content')
<div class="container-fluid px-4 py-3">

  @if(session('status'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
      <i class="fas fa-check-circle mr-2"></i>{{ session('status') }}
      <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
  @endif

  {{-- Stats --}}
  @php
    $totalLinked   = $owners->getCollection()->filter(fn($o) => $o->ownerUserLink?->user)->count();
    $totalUnlinked = $owners->getCollection()->filter(fn($o) => !$o->ownerUserLink?->user)->count();
  @endphp
  <div class="d-flex align-items-center gap-2 mb-3" style="gap:.5rem;">
    <span class="badge px-3 py-2" style="background:#d4edda;color:#155724;font-size:.82rem;border-radius:20px;">
      <i class="fas fa-link mr-1"></i> Asociados en esta página: <strong>{{ $totalLinked }}</strong>
    </span>
    <span class="badge px-3 py-2" style="background:#f8d7da;color:#721c24;font-size:.82rem;border-radius:20px;">
      <i class="fas fa-unlink mr-1"></i> Sin asociar en esta página: <strong>{{ $totalUnlinked }}</strong>
    </span>
  </div>

  <div class="card shadow-sm border-0">

    {{-- Card header --}}
    <div class="card-header bg-white border-bottom py-2 px-3">
      <i class="fas fa-table text-muted mr-1"></i>
      <small class="text-muted">
        {{ $owners->total() }} owners en total &mdash;
        página {{ $owners->currentPage() }} de {{ $owners->lastPage() }}
      </small>
    </div>

    {{-- Tabla --}}
    <div class="table-responsive">
      <table class="table table-hover mb-0 owners-table">
        <thead class="thead-light">
          <tr>
            <th style="width:90px;">#ID</th>
            <th style="min-width:160px;">Nombre</th>
            <th style="min-width:200px;">Email</th>
            <th style="width:75px;" class="text-center">Activo</th>
            <th style="min-width:320px;">Usuario asociado</th>
            <th style="width:100px;" class="text-right pr-3">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @foreach($owners as $o)
            @php
              $linkedUser = $o->ownerUserLink?->user;
              $isLinked   = !!$linkedUser;
              $initials   = collect(explode(' ', $o->name))
                              ->filter()->take(2)
                              ->map(fn($w) => strtoupper($w[0]))
                              ->implode('');
            @endphp

            <tr class="owner-row {{ $isLinked ? 'row-linked' : 'row-unlinked' }}"
                data-owner-id="{{ $o->id }}">

              {{-- ID --}}
              <td class="text-muted align-middle pl-3" style="font-family:monospace;font-size:.8rem;">
                {{ $o->id }}
              </td>

              {{-- Nombre --}}
              <td class="align-middle">
                <div class="d-flex align-items-center" style="flex-wrap:nowrap;">
                  <div class="owner-avatar mr-2 flex-shrink-0">{{ $initials }}</div>
                  <span class="font-weight-semibold text-nowrap">{{ $o->name }}</span>
                </div>
              </td>

              {{-- Email --}}
              <td class="align-middle">
                @if($o->email)
                  <span class="text-muted" style="font-size:.85rem;">{{ $o->email }}</span>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>

              {{-- Activo --}}
              <td class="align-middle text-center">
                @if($o->active)
                  <span class="badge badge-success" style="border-radius:20px;font-size:.75rem;">Sí</span>
                @else
                  <span class="badge badge-secondary" style="border-radius:20px;font-size:.75rem;">No</span>
                @endif
              </td>

              {{-- Usuario asociado --}}
              <td class="align-middle" style="padding-top:.5rem;padding-bottom:.5rem;">
                <select class="js-user-select"
                        data-owner-id="{{ $o->id }}"
                        data-initial-user-id="{{ $linkedUser?->id ?? '' }}"
                        data-initial-user-text="{{ $linkedUser ? trim(($linkedUser->name ?? '').' — '.($linkedUser->email ?? '')) : '' }}">
                  @if($linkedUser)
                    <option value="{{ $linkedUser->id }}" selected>
                      {{ trim(($linkedUser->name ?? '').' — '.($linkedUser->email ?? '')) }}
                    </option>
                  @endif
                </select>
                <div class="d-flex align-items-center mt-1" style="gap:.35rem;">
                  @if($isLinked)
                    <i class="fas fa-check-circle text-success js-link-icon" style="font-size:.75rem;"></i>
                    <small class="text-success js-link-label" style="font-size:.72rem;">Usuario asociado</small>
                  @else
                    <i class="fas fa-times-circle text-danger js-link-icon" style="font-size:.75rem;"></i>
                    <small class="text-danger js-link-label" style="font-size:.72rem;">Sin asociar</small>
                  @endif
                </div>
              </td>

              {{-- Acciones --}}
              <td class="align-middle text-right pr-3">
                <a class="btn btn-sm btn-outline-secondary"
                   href="{{ route('hubspot-owners.edit', $o) }}"
                   title="Editar">
                  <i class="fas fa-pencil-alt fa-fw"></i>
                </a>
                <form class="d-inline" method="POST"
                      action="{{ route('hubspot-owners.destroy', $o) }}"
                      onsubmit="return confirm('¿Eliminar este owner?');">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger" title="Eliminar">
                    <i class="fas fa-trash-alt fa-fw"></i>
                  </button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- ── Paginación arreglada ── --}}
    @if($owners->lastPage() > 1)
    <div class="card-footer bg-white border-top">
      <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:.5rem;">

        {{-- Info --}}
        <small class="text-muted">
          Mostrando <strong>{{ $owners->firstItem() }}</strong>–<strong>{{ $owners->lastItem() }}</strong>
          de <strong>{{ $owners->total() }}</strong> resultados
        </small>

        {{-- Paginador manual Bootstrap 4 --}}
        <ul class="pagination pagination-sm mb-0">

          {{-- Anterior --}}
          <li class="page-item {{ $owners->onFirstPage() ? 'disabled' : '' }}">
            <a class="page-link" href="{{ $owners->previousPageUrl() ?? '#' }}">
              <i class="fas fa-chevron-left fa-xs"></i>
            </a>
          </li>

          {{-- Páginas --}}
          @php
            $current = $owners->currentPage();
            $last    = $owners->lastPage();
            $start   = max(1, $current - 2);
            $end     = min($last, $current + 2);
          @endphp

          @if($start > 1)
            <li class="page-item">
              <a class="page-link" href="{{ $owners->url(1) }}">1</a>
            </li>
            @if($start > 2)
              <li class="page-item disabled"><span class="page-link">…</span></li>
            @endif
          @endif

          @for($p = $start; $p <= $end; $p++)
            <li class="page-item {{ $p === $current ? 'active' : '' }}">
              <a class="page-link" href="{{ $owners->url($p) }}">{{ $p }}</a>
            </li>
          @endfor

          @if($end < $last)
            @if($end < $last - 1)
              <li class="page-item disabled"><span class="page-link">…</span></li>
            @endif
            <li class="page-item">
              <a class="page-link" href="{{ $owners->url($last) }}">{{ $last }}</a>
            </li>
          @endif

          {{-- Siguiente --}}
          <li class="page-item {{ !$owners->hasMorePages() ? 'disabled' : '' }}">
            <a class="page-link" href="{{ $owners->nextPageUrl() ?? '#' }}">
              <i class="fas fa-chevron-right fa-xs"></i>
            </a>
          </li>

        </ul>
      </div>
    </div>
    @endif

  </div>{{-- /card --}}
</div>
@stop

@section('css')
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap4-theme/1.5.2/select2-bootstrap4.min.css">
  <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
  <style>
    /* ── Filas ── */
    .owners-table thead th {
      font-size: .72rem;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: #6c757d;
      font-weight: 600;
      border-top: 0;
    }
    .owners-table tbody tr {
      transition: box-shadow .12s ease;
    }
    .owners-table tbody tr:hover {
      box-shadow: inset 4px 0 0 #4e73df;
    }
    .row-linked   { background-color: #f4fff6 !important; }
    .row-unlinked { background-color: #fff8f8 !important; }

    /* ── Avatar ── */
    .owner-avatar {
      width: 32px;
      height: 32px;
      min-width: 32px;
      border-radius: 50%;
      background: linear-gradient(135deg, #4e73df, #224abe);
      color: #fff;
      font-size: .72rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* ── Select2 dentro de tabla ── */
    .select2-container {
      width: 100% !important;
      max-width: 290px;
    }
    .select2-container--bootstrap4 .select2-selection--single {
      height: 31px !important;
      font-size: .85rem;
    }
    .select2-container--bootstrap4 .select2-selection--single
      .select2-selection__rendered {
      line-height: 29px !important;
      padding-right: 24px;
    }
    .select2-container--bootstrap4 .select2-selection--single
      .select2-selection__arrow {
      height: 29px !important;
    }

    /* ── Paginación ── */
    .pagination .page-link {
      font-size: .82rem;
      padding: .3rem .6rem;
      color: #4e73df;
    }
    .pagination .page-item.active .page-link {
      background-color: #4e73df;
      border-color: #4e73df;
    }
    .pagination .page-item.disabled .page-link {
      color: #adb5bd;
    }
  </style>
@stop

@section('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script>
$(function () {

  $('.js-user-select').each(function () {
    const $select = $(this);
    const ownerId = $select.data('owner-id');

    $select.select2({
      theme: 'bootstrap4',
      width: '100%',
      placeholder: 'Buscar usuario…',
      allowClear: true,
      ajax: {
        url: "{{ route('ajax.users.search') }}",
        dataType: 'json',
        delay: 250,
        data:           params => ({ q: params.term || '' }),
        processResults: data   => data,
      }
    });

    $select.on('change', function () {
      const userId = $select.val();
      const $row   = $select.closest('tr.owner-row');
      const $icon  = $row.find('.js-link-icon');
      const $label = $row.find('.js-link-label');

      $.ajax({
        url:    "{{ url('/hubspot-owners') }}/" + encodeURIComponent(ownerId) + "/assign-user",
        method: "POST",
        data:   { _token: "{{ csrf_token() }}", user_id: userId },

        success(res) {
          if (res.assigned) {
            $row.removeClass('row-unlinked').addClass('row-linked');
            $icon.removeClass('fa-times-circle text-danger')
                 .addClass('fa-check-circle text-success');
            $label.removeClass('text-danger').addClass('text-success')
                  .text('Usuario asociado');
          } else {
            $row.removeClass('row-linked').addClass('row-unlinked');
            $icon.removeClass('fa-check-circle text-success')
                 .addClass('fa-times-circle text-danger');
            $label.removeClass('text-success').addClass('text-danger')
                  .text('Sin asociar');
          }
        },

        error(xhr) {
          console.error(xhr.responseText || xhr);
          alert('No se pudo guardar la asociación.');
        }
      });
    });
  });

});
</script>
@stop
