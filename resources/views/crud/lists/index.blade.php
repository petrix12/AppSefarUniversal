@extends('adminlte::page')

@section('title', 'Listas')

@section('content_header')
  <div class="d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
      <div class="ch-icon-badge">
        <i class="fas fa-layer-group"></i>
      </div>
      <div>
        <h1 class="m-0 font-weight-bold" style="font-size:1.4rem;">Listas</h1>
        <small class="text-muted">Gestión de listas de contacto</small>
      </div>
    </div>

    @can('lists.create')
      <a href="{{ route('crud.lists.create') }}" class="btn btn-primary btn-sm px-3 shadow-sm">
        <i class="fas fa-plus mr-1"></i> Crear lista
      </a>
    @endcan
  </div>
@stop

@section('content')

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
      <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
      <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
  @endif

  {{-- ── Card principal ── --}}
  <div class="card shadow-sm border-0">

    <div class="card-header bg-white border-bottom py-3 px-4">
      <div class="section-block__header" style="border:none;background:none;padding:0;">
        <i class="fas fa-search mr-2 text-primary"></i>
        <span class="font-weight-semibold">Buscar listas</span>
      </div>
    </div>

    <div class="card-body px-4 pt-3 pb-2">

      {{-- Buscador --}}
      <form method="GET" action="{{ route('crud.lists.index') }}" class="row g-2 align-items-end mb-4">
        <div class="col-md-8">
          <label class="form-label text-muted small mb-1">Nombre de la lista</label>
          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text bg-light border-right-0">
                <i class="fas fa-search text-muted"></i>
              </span>
            </div>
            <input type="text"
                   name="q"
                   value="{{ $q ?? '' }}"
                   class="form-control border-left-0 pl-0"
                   placeholder="Buscar lista por nombre...">
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label text-muted small mb-1">&nbsp;</label>
          <div class="d-flex gap-2">
            <button class="btn btn-primary w-100" type="submit">
              <i class="fas fa-filter mr-1"></i> Buscar
            </button>
            @if(!empty($q))
              <a href="{{ route('crud.lists.index') }}" class="btn btn-outline-secondary w-100">
                <i class="fas fa-times mr-1"></i> Limpiar
              </a>
            @endif
          </div>
        </div>
      </form>

      {{-- Tabla --}}
      <div class="table-responsive rounded border">
        <table class="table table-hover table-lists mb-0">
          <thead>
            <tr class="bg-light">
              <th class="pl-3"><i class="fas fa-layer-group mr-1 text-muted"></i> Lista</th>
              <th class="text-center" style="width:110px;"><i class="fas fa-users mr-1 text-muted"></i> Miembros</th>
              <th><i class="fas fa-user-tie mr-1 text-muted"></i> Owner</th>
              <th class="text-center" style="width:140px;">Acciones</th>
            </tr>
          </thead>

          <tbody>
            @forelse($lists as $lista)
              <tr class="list-row">

                {{-- Lista --}}
                <td class="pl-3">
                  <div class="d-flex align-items-center gap-2">
                    <div class="list-avatar">
                      {{ strtoupper(substr($lista->name, 0, 1)) }}
                    </div>
                    <div>
                      <div class="font-weight-semibold text-dark">{{ $lista->name }}</div>
                      @if($lista->description)
                        <small class="text-muted">
                          {{ \Illuminate\Support\Str::limit($lista->description, 80) }}
                        </small>
                      @endif
                    </div>
                  </div>
                </td>

                {{-- Miembros --}}
                <td class="text-center">
                  @php $count = $lista->users_count ?? $lista->users()->count(); @endphp
                  <span class="members-pill {{ $count > 0 ? 'members-pill--active' : 'members-pill--empty' }}">
                    <i class="fas fa-user mr-1"></i>{{ $count }}
                  </span>
                </td>

                {{-- Owner --}}
                <td>
                  @if($lista->owner)
                    <div class="d-flex align-items-center gap-2">
                      <div class="owner-avatar">
                        {{ strtoupper(substr($lista->owner->name, 0, 1)) }}
                      </div>
                      <div>
                        <div class="font-weight-semibold small text-dark">{{ $lista->owner->name }}</div>
                        <small class="text-muted">{{ $lista->owner->email ?? '' }}</small>
                      </div>
                    </div>
                  @else
                    <span class="text-muted small">Sin owner</span>
                  @endif
                </td>

                {{-- Acciones --}}
                <td class="text-center">
                  <div class="d-flex gap-1 justify-content-center">
                    @can('lists.view')
                      <a class="action-btn action-btn--blue"
                         href="{{ route('crud.lists.show', $lista) }}"
                         title="Ver lista">
                        <i class="fas fa-eye"></i>
                      </a>
                    @endcan

                    @can('lists.edit')
                      <a class="action-btn action-btn--yellow"
                         href="{{ route('crud.lists.edit', $lista) }}"
                         title="Editar lista">
                        <i class="fas fa-edit"></i>
                      </a>
                    @endcan

                    @can('lists.delete')
                      <form method="POST"
                            action="{{ route('crud.lists.destroy', $lista) }}"
                            onsubmit="return confirm('¿Eliminar la lista «{{ addslashes($lista->name) }}»?')"
                            style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <button class="action-btn action-btn--red" title="Eliminar lista">
                          <i class="fas fa-trash"></i>
                        </button>
                      </form>
                    @endcan
                  </div>
                </td>

              </tr>
            @empty
              <tr>
                <td colspan="4" class="text-center py-5">
                  <div class="empty-state">
                    <i class="fas fa-layer-group fa-3x text-muted mb-3 d-block"></i>
                    <p class="text-muted mb-1">
                      No hay listas{{ !empty($q) ? ' para «' . e($q) . '»' : '' }}.
                    </p>
                    @can('lists.create')
                      <a href="{{ route('crud.lists.create') }}" class="btn btn-sm btn-primary mt-2">
                        <i class="fas fa-plus mr-1"></i> Crear primera lista
                      </a>
                    @endcan
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- ── Paginación inline sin Bootstrap ── --}}
      @php
        $paged   = method_exists($lists, 'appends') ? $lists->appends(request()->query()) : null;
        $current = $paged ? $paged->currentPage() : 1;
        $last    = $paged ? $paged->lastPage()    : 1;

        $visible = collect(range(1, $last))
          ->filter(fn($p) => $p === 1 || $p === $last || abs($p - $current) <= 2)
          ->values();
      @endphp

      <div class="sf-pager-wrapper mt-3 mb-1">

        {{-- Contador --}}
        <span class="sf-pager-info">
          @if($paged && $paged->total())
            Mostrando
            <strong>{{ $paged->firstItem() }}</strong>–<strong>{{ $paged->lastItem() }}</strong>
            de <strong>{{ $paged->total() }}</strong> listas
          @endif
        </span>

        {{-- Paginador --}}
        @if($paged && $paged->hasPages())
        <div class="sf-pager">

          {{-- ← --}}
          @if($paged->onFirstPage())
            <span class="sf-pager__btn sf-pager__btn--disabled"><i class="fas fa-chevron-left"></i></span>
          @else
            <a class="sf-pager__btn" href="{{ $paged->previousPageUrl() }}" rel="prev">
              <i class="fas fa-chevron-left"></i>
            </a>
          @endif

          {{-- Números --}}
          @php $prev = null; @endphp
          @foreach($visible as $page)
            @if($prev !== null && $page - $prev > 1)
              <span class="sf-pager__dots">…</span>
            @endif

            @if($page === $current)
              <span class="sf-pager__btn sf-pager__btn--active" aria-current="page">{{ $page }}</span>
            @else
              <a class="sf-pager__btn" href="{{ $paged->url($page) }}">{{ $page }}</a>
            @endif

            @php $prev = $page; @endphp
          @endforeach

          {{-- → --}}
          @if($paged->hasMorePages())
            <a class="sf-pager__btn" href="{{ $paged->nextPageUrl() }}" rel="next">
              <i class="fas fa-chevron-right"></i>
            </a>
          @else
            <span class="sf-pager__btn sf-pager__btn--disabled"><i class="fas fa-chevron-right"></i></span>
          @endif

        </div>
        @endif

      </div>
      {{-- /paginación --}}

    </div>
  </div>

@stop

@section('css')
  <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
  <style>
    /* ── Utilidades ─────────────────────────────────────── */
    .gap-1  { gap: .25rem !important; }
    .gap-2  { gap: .5rem  !important; }
    .font-weight-semibold { font-weight: 600 !important; }
    .form-label { display: block; }

    /* ── Content-header icon ────────────────────────────── */
    .ch-icon-badge {
      width: 42px; height: 42px;
      background: linear-gradient(135deg, #4f8ef7, #2563eb);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      color: #fff; font-size: 1.1rem;
      flex-shrink: 0;
    }

    /* ── Stat cards ─────────────────────────────────────── */
    .stat-card {
      border-radius: 10px;
      padding: 1rem 1.25rem;
      color: #fff;
      box-shadow: 0 2px 8px rgba(0,0,0,.08);
    }
    .stat-card__value { font-size: 1.8rem; font-weight: 700; line-height: 1; }
    .stat-card__label { font-size: .78rem; opacity: .88; margin-top: .25rem; }
    .stat-card--blue   { background: linear-gradient(135deg, #4f8ef7, #2563eb); }
    .stat-card--green  { background: linear-gradient(135deg, #34d399, #059669); }
    .stat-card--orange { background: linear-gradient(135deg, #fbbf24, #d97706); }
    .stat-card--purple { background: linear-gradient(135deg, #a78bfa, #7c3aed); }

    /* ── Input group fix ────────────────────────────────── */
    .input-group .border-left-0      { border-left:  0 !important; }
    .input-group-text.border-right-0 { border-right: 0 !important; }

    /* ── Tabla ──────────────────────────────────────────── */
    .table-lists thead th {
      font-size: .78rem;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: #6b7280;
      font-weight: 600;
      border-top: 0;
      padding-top: .75rem;
      padding-bottom: .75rem;
      white-space: nowrap;
    }
    .table-lists tbody td {
      vertical-align: middle;
      padding-top: .7rem;
      padding-bottom: .7rem;
    }
    .list-row { transition: background .15s; }
    .list-row:hover { background: #f8faff !important; }

    /* ── Avatar de lista ────────────────────────────────── */
    .list-avatar {
      width: 34px; height: 34px; border-radius: 9px;
      background: linear-gradient(135deg, #4f8ef7, #2563eb);
      color: #fff; font-size: .85rem; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }

    /* ── Avatar de owner ────────────────────────────────── */
    .owner-avatar {
      width: 28px; height: 28px; border-radius: 50%;
      background: linear-gradient(135deg, #818cf8, #4f46e5);
      color: #fff; font-size: .75rem; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }

    /* ── Pill de miembros ───────────────────────────────── */
    .members-pill {
      display: inline-flex; align-items: center;
      padding: .28em .75em; border-radius: 999px;
      font-size: .78rem; font-weight: 600;
    }
    .members-pill--active { background: #dbeafe; color: #1e40af; }
    .members-pill--empty  { background: #f3f4f6; color: #9ca3af; }

    /* ── Botones de acción ──────────────────────────────── */
    .action-btn {
      display: inline-flex; align-items: center; justify-content: center;
      width: 30px; height: 30px; border-radius: 7px;
      border: 1px solid transparent;
      font-size: .8rem; cursor: pointer;
      text-decoration: none;
      transition: transform .12s, box-shadow .12s, opacity .12s;
      background: none;
    }
    .action-btn:hover { transform: translateY(-1px); box-shadow: 0 3px 8px rgba(0,0,0,.15); text-decoration: none; }

    .action-btn--blue   { background: #dbeafe; color: #1d4ed8; border-color: #bfdbfe; }
    .action-btn--yellow { background: #fef9c3; color: #92400e; border-color: #fde68a; }
    .action-btn--red    { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }

    .action-btn--blue:hover   { background: #2563eb; color: #fff; border-color: #2563eb; }
    .action-btn--yellow:hover { background: #d97706; color: #fff; border-color: #d97706; }
    .action-btn--red:hover    { background: #dc2626; color: #fff; border-color: #dc2626; }

    /* ── Estado vacío ───────────────────────────────────── */
    .empty-state { padding: 1rem 0; }

    /* ══════════════════════════════════════════════════════
       Paginación — sin Bootstrap
    ══════════════════════════════════════════════════════ */
    .sf-pager-wrapper {
      display: flex; align-items: center;
      justify-content: space-between;
      flex-wrap: wrap; gap: .75rem;
    }
    .sf-pager-info { font-size: .82rem; color: #6b7280; }
    .sf-pager-info strong { color: #374151; }

    .sf-pager {
      display: flex; align-items: center;
      flex-wrap: wrap; gap: .3rem;
      list-style: none; margin: 0; padding: 0;
    }
    .sf-pager__btn {
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 34px; height: 34px; padding: 0 .55rem;
      border-radius: 8px; border: 1px solid #e5e7eb;
      background: #fff; color: #374151;
      font-size: .83rem; font-weight: 500;
      text-decoration: none; cursor: pointer;
      transition: background .14s, border-color .14s, color .14s, box-shadow .14s;
      user-select: none; line-height: 1;
    }
    .sf-pager__btn:hover {
      background: #eff6ff; border-color: #93c5fd;
      color: #1d4ed8; text-decoration: none;
      box-shadow: 0 1px 4px rgba(37,99,235,.12);
    }
    .sf-pager__btn--active {
      background: linear-gradient(135deg, #4f8ef7, #2563eb);
      border-color: transparent; color: #fff !important;
      box-shadow: 0 2px 8px rgba(37,99,235,.32);
      pointer-events: none; cursor: default;
    }
    .sf-pager__btn--disabled {
      background: #f9fafb; border-color: #e5e7eb;
      color: #d1d5db; pointer-events: none; cursor: not-allowed;
    }
    .sf-pager__dots {
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 34px; height: 34px;
      color: #9ca3af; font-size: .9rem; letter-spacing: .05em;
    }
  </style>
@stop
