@extends('adminlte::page')

@section('title', 'HubSpot Owners (manual)')

@section('content_header')
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <h1 class="m-0 text-dark">
        <i class="fas fa-users-cog mr-2 text-primary"></i>
        HubSpot Owners
        <small class="text-muted" style="font-size: 0.55em; vertical-align: middle;">manual</small>
      </h1>
    </div>
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
      <button type="button" class="close" data-dismiss="alert">
        <span>&times;</span>
      </button>
    </div>
  @endif

  {{-- Stats bar --}}
  @php
    $totalLinked   = $owners->getCollection()->filter(fn($o) => $o->ownerUserLink?->user)->count();
    $totalUnlinked = $owners->getCollection()->filter(fn($o) => !$o->ownerUserLink?->user)->count();
  @endphp
  <div class="row mb-3 g-2">
    <div class="col-auto">
      <div class="badge badge-pill px-3 py-2" style="background:#d4edda; color:#155724; font-size:.85rem;">
        <i class="fas fa-link mr-1"></i> Asociados: <strong>{{ $totalLinked }}</strong>
      </div>
    </div>
    <div class="col-auto">
      <div class="badge badge-pill px-3 py-2" style="background:#f8d7da; color:#721c24; font-size:.85rem;">
        <i class="fas fa-unlink mr-1"></i> Sin asociar: <strong>{{ $totalUnlinked }}</strong>
      </div>
    </div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-header border-bottom bg-white py-2 px-3">
      <div class="d-flex align-items-center">
        <i class="fas fa-table text-muted mr-2"></i>
        <span class="font-weight-semibold text-muted" style="font-size:.85rem;">
          {{ $owners->total() }} owners en total — página {{ $owners->currentPage() }} de {{ $owners->lastPage() }}
        </span>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle owners-table">
        <thead>
          <tr class="bg-light">
            <th class="text-muted border-0 pl-3" style="width:60px; font-size:.75rem; letter-spacing:.05em;">#ID</th>
            <th class="border-0" style="font-size:.75rem; letter-spacing:.05em;">NOMBRE</th>
            <th class="border-0" style="font-size:.75rem; letter-spacing:.05em;">EMAIL</th>
            <th class="border-0 text-center" style="width:70px; font-size:.75rem; letter-spacing:.05em;">ACTIVO</th>
            <th class="border-0" style="min-width:340px; font-size:.75rem; letter-spacing:.05em;">USUARIO ASOCIADO</th>
            <th class="border-0 text-right pr-3" style="width:160px; font-size:.75rem; letter-spacing:.05em;">ACCIONES</th>
          </tr>
        </thead>
        <tbody>
          @foreach($owners as $o)
            @php
              $linkedUser = $o->ownerUserLink?->user;
              $isLinked   = !!$linkedUser;
            @endphp

            <tr class="owner-row {{ $isLinked ? 'row-linked' : 'row-unlinked' }}"
                data-owner-id="{{ $o->id }}">

              {{-- ID --}}
              <td class="pl-3">
                <span class="text-muted" style="font-size:.8rem; font-family: monospace;">{{ $o->id }}</span>
              </td>

              {{-- Nombre --}}
              <td>
                <div class="d-flex align-items-center">
                  <div class="owner-avatar mr-2">
                    {{ strtoupper(substr($o->name, 0, 1)) }}
                  </div>
                  <span class="font-weight-semibold">{{ $o->name }}</span>
                </div>
              </td>

              {{-- Email --}}
              <td>
                @if($o->email)
                  <span class="text-muted" style="font-size:.875rem;">
                    <i class="fas fa-envelope mr-1" style="opacity:.45; font-size:.75rem;"></i>{{ $o->email }}
                  </span>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>

              {{-- Activo --}}
              <td class="text-center">
                @if($o->active)
                  <span class="badge badge-success px-2 py-1" style="font-size:.75rem; border-radius:20px;">Sí</span>
                @else
                  <span class="badge badge-secondary px-2 py-1" style="font-size:.75rem; border-radius:20px;">No</span>
                @endif
              </td>

              {{-- Select usuario --}}
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="flex-grow-1">
                    <select class="form-control js-user-select"
                            data-owner-id="{{ $o->id }}"
                            data-initial-user-id="{{ $linkedUser?->id ?? '' }}"
                            data-initial-user-text="{{ $linkedUser ? trim(($linkedUser->name ?? '').' — '.($linkedUser->email ?? '')) : '' }}">
                      @if($linkedUser)
                        <option value="{{ $linkedUser->id }}" selected>
                          {{ trim(($linkedUser->name ?? '').' — '.($linkedUser->email ?? '')) }}
                        </option>
                      @endif
                    </select>
                  </div>
                  <div class="link-status-icon ml-2">
                    @if($isLinked)
                      <i class="fas fa-link text-success" title="Asociado"></i>
                    @else
                      <i class="fas fa-unlink text-danger" title="Sin asociar"></i>
                    @endif
                  </div>
                </div>
                <small class="js-link-label text-muted mt-1 d-block" style="font-size:.75rem;">
                  {{ $isLinked ? '✓ Usuario asociado' : '✗ Sin asociar' }}
                </small>
              </td>

              {{-- Acciones --}}
              <td class="text-right pr-3">
                <a class="btn btn-sm btn-outline-secondary"
                   href="{{ route('hubspot-owners.edit', $o) }}"
                   title="Editar">
                  <i class="fas fa-pencil-alt"></i>
                </a>
                <form class="d-inline" method="POST"
                      action="{{ route('hubspot-owners.destroy', $o) }}"
                      onsubmit="return confirm('¿Eliminar este owner?');">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger" title="Eliminar">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="card-footer bg-white border-top d-flex justify-content-center py-3">
      {{ $owners->links() }}
    </div>
  </div>

</div>
@stop

@section('css')
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap4-theme/1.5.2/select2-bootstrap4.min.css">
  <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
  <style>
    /* ── Filas ── */
    .owners-table tbody tr {
      transition: background .15s ease, box-shadow .15s ease;
    }
    .owners-table tbody tr:hover {
      box-shadow: inset 4px 0 0 #4e73df;
    }
    .row-linked {
      background-color: #f0fff4 !important;
    }
    .row-unlinked {
      background-color: #fff5f5 !important;
    }

    /* ── Avatar inicial ── */
    .owner-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: #4e73df;
      color: #fff;
      font-size: .8rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    /* ── Select2 ajustes ── */
    .select2-container {
      width: 100% !important;
      max-width: 300px;
    }
    .select2-container--default .select2-selection--single {
      height: 34px;
      border-color: #ced4da;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
      line-height: 32px !important;
      font-size: .875rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 32px;
    }

    /* ── Ícono de estado link ── */
    .link-status-icon i {
      font-size: 1rem;
    }

    /* ── Thead ── */
    .owners-table thead th {
      text-transform: uppercase;
      font-weight: 600;
      color: #6c757d;
    }

    /* ── Card header ── */
    .card-header {
      background: #f8f9fa !important;
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
        data:           params  => ({ q: params.term || '' }),
        processResults: data    => data,
      }
    });

    $select.on('change', function () {
      const userId = $select.val();
      const $row   = $select.closest('tr.owner-row');
      const $icon  = $row.find('.link-status-icon i');
      const $label = $row.find('.js-link-label');

      $.ajax({
        url:    "{{ url('/hubspot-owners') }}/" + encodeURIComponent(ownerId) + "/assign-user",
        method: "POST",
        data:   { _token: "{{ csrf_token() }}", user_id: userId },

        success (res) {
          if (res.assigned) {
            $row.removeClass('row-unlinked').addClass('row-linked');
            $icon.removeClass('fa-unlink text-danger').addClass('fa-link text-success');
            $label.text('✓ Usuario asociado');
          } else {
            $row.removeClass('row-linked').addClass('row-unlinked');
            $icon.removeClass('fa-link text-success').addClass('fa-unlink text-danger');
            $label.text('✗ Sin asociar');
          }
        },

        error (xhr) {
          console.error(xhr.responseText || xhr);
          alert('No se pudo guardar la asociación.');
        }
      });
    });
  });

});
</script>
@stop
