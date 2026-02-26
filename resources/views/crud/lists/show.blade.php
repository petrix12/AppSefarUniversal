@extends('adminlte::page')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
      <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
      <button type="button" class="close" data-dismiss="alert">
        <span>&times;</span>
      </button>
    </div>
  @endif

  <div class="card shadow-sm border-0">

    <div class="card-header bg-white border-bottom py-3 px-4">
      <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">

        <div class="d-flex align-items-center gap-3">
          <div class="list-icon-badge">
            <i class="fas fa-list-ul"></i>
          </div>
          <div>
            <h4 class="mb-0 font-weight-bold text-dark">{{ $lista->name }}</h4>
            @if($lista->description)
              <small class="text-muted">{{ $lista->description }}</small>
            @endif
          </div>
        </div>

        <div class="d-flex gap-2 align-items-center">
          @can('lists.edit')
            <a class="btn btn-warning btn-sm px-3 shadow-sm" href="{{ route('crud.lists.edit', $lista) }}">
              <i class="fas fa-edit mr-1"></i> Editar lista
            </a>
          @endcan
          <a class="btn btn-outline-secondary btn-sm px-3" href="{{ route('crud.lists.index') }}">
            <i class="fas fa-arrow-left mr-1"></i> Volver
          </a>
        </div>

      </div>
    </div>

    <div class="card-body px-4 pt-4">

      {{-- Estadísticas --}}
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="stat-card stat-card--blue">
            <div class="stat-card__value">{{ $members->total() }}</div>
            <div class="stat-card__label"><i class="fas fa-users mr-1"></i>Total miembros</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card stat-card--green">
            <div class="stat-card__value">
              {{ $members->getCollection()->where('pivot.contacted', true)->count() }}
            </div>
            <div class="stat-card__label"><i class="fas fa-check mr-1"></i>Contactados (pág.)</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card stat-card--orange">
            <div class="stat-card__value">
              {{ $members->getCollection()->where('pivot.contacted', false)->count() }}
            </div>
            <div class="stat-card__label"><i class="fas fa-clock mr-1"></i>Pendientes (pág.)</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card stat-card--purple">
            <div class="stat-card__value">{{ $members->lastPage() }}</div>
            <div class="stat-card__label"><i class="fas fa-file mr-1"></i>Páginas</div>
          </div>
        </div>
      </div>

      {{-- Buscar + Filtro --}}
      <div class="section-block mb-4">
        <div class="section-block__header">
          <i class="fas fa-search mr-2 text-primary"></i>
          <span class="font-weight-semibold">Buscar y filtrar</span>
        </div>
        <div class="section-block__body">
          <form class="row g-2 align-items-end" method="GET" action="{{ route('crud.lists.show', $lista) }}">
            <div class="col-md-6">
              <label class="form-label text-muted small mb-1">Nombre, correo o pasaporte</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text bg-light border-right-0">
                    <i class="fas fa-search text-muted"></i>
                  </span>
                </div>
                <input class="form-control border-left-0 pl-0"
                       name="q"
                       value="{{ $q ?? '' }}"
                       placeholder="Buscar usuario...">
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label text-muted small mb-1">Estado de contacto</label>
              <select class="form-control" name="filter">
                <option value="">Todos los estados</option>
                <option value="contacted"     {{ ($filter ?? '') === 'contacted'     ? 'selected' : '' }}>✅ Contactados</option>
                <option value="not_contacted" {{ ($filter ?? '') === 'not_contacted' ? 'selected' : '' }}>🕐 No contactados</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label text-muted small mb-1">&nbsp;</label>
              <div class="d-flex gap-2">
                <button class="btn btn-primary w-100">
                  <i class="fas fa-filter mr-1"></i> Filtrar
                </button>
                <a class="btn btn-outline-secondary w-100" href="{{ route('crud.lists.show', $lista) }}">
                  <i class="fas fa-times mr-1"></i> Limpiar
                </a>
              </div>
            </div>
          </form>
        </div>
      </div>

      {{-- Añadir miembros --}}
      @can('lists.manage_members')
      <div class="section-block section-block--success mb-4">
        <div class="section-block__header">
          <i class="fas fa-user-plus mr-2 text-success"></i>
          <span class="font-weight-semibold">Añadir usuarios a la lista</span>
        </div>
        <div class="section-block__body">
          <form method="POST" action="{{ route('crud.lists.members.add', $lista) }}">
            @csrf
            <div class="row align-items-end g-2">
              <div class="col-md-8">
                <label class="form-label text-muted small mb-1">IDs de usuario</label>
                <input class="form-control"
                       name="user_ids_raw"
                       placeholder="Ej: 12, 55, 108  — separados por coma"
                       value="">
              </div>
              <div class="col-md-4">
                <button class="btn btn-success w-100">
                  <i class="fas fa-plus mr-1"></i> Añadir usuarios
                </button>
              </div>
            </div>
            <small class="text-muted d-block mt-2">
              <i class="fas fa-info-circle mr-1"></i>
              Ingresa los IDs numéricos separados por coma. Puedes añadir varios a la vez.
            </small>
          </form>
        </div>
      </div>

      <script>
        document.addEventListener('submit', function(e){
          const form = e.target;
          if(form && form.action && form.action.includes('/members/add')) {
            const raw = form.querySelector('[name="user_ids_raw"]');
            if(!raw) return;
            const ids = raw.value.split(',').map(s=>s.trim()).filter(Boolean).map(Number).filter(n=>!Number.isNaN(n)&&n>0);
            form.querySelectorAll('input[name="user_ids[]"]').forEach(n=>n.remove());
            ids.forEach(id=>{
              const inp=document.createElement('input');
              inp.type='hidden'; inp.name='user_ids[]'; inp.value=String(id);
              form.appendChild(inp);
            });
            if(ids.length===0){ e.preventDefault(); alert('Ingresa al menos un ID válido.'); }
          }
        }, true);
      </script>
      @endcan

      {{-- Tabla --}}
      <div class="table-responsive rounded border">
        <table class="table table-hover table-members mb-0">
          <thead>
            <tr class="bg-light">
              <th class="pl-3"><i class="fas fa-user mr-1 text-muted"></i> Usuario</th>
              <th><i class="fas fa-envelope mr-1 text-muted"></i> Correo</th>
              <th><i class="fas fa-passport mr-1 text-muted"></i> Pasaporte</th>
              <th class="text-center">Estado</th>
              <th><i class="fas fa-calendar mr-1 text-muted"></i> Fecha contacto</th>
              <th><i class="fas fa-sticky-note mr-1 text-muted"></i> Nota</th>
              @can('lists.manage_members')
                <th class="text-center">Acciones</th>
              @endcan
            </tr>
          </thead>
          <tbody>
          @forelse($members as $u)
            <tr class="member-row {{ $u->pivot->contacted ? 'member-row--contacted' : '' }}">
              <td class="pl-3">
                <div class="d-flex align-items-center gap-2">
                  <div class="member-avatar">{{ strtoupper(substr($u->name, 0, 1)) }}</div>
                  <span class="font-weight-semibold text-dark">{{ $u->name }}</span>
                </div>
              </td>
              <td class="text-muted">{{ $u->email }}</td>
              <td>
                @if($u->passport)
                  <code class="small">{{ $u->passport }}</code>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="text-center">
                @if($u->pivot->contacted)
                  <span class="badge-status badge-status--success">
                    <i class="fas fa-check-circle mr-1"></i> Contactado
                  </span>
                @else
                  <span class="badge-status badge-status--pending">
                    <i class="fas fa-clock mr-1"></i> Pendiente
                  </span>
                @endif
              </td>
              <td class="small text-muted">
                @if($u->pivot->contacted_at)
                  <div>{{ \Carbon\Carbon::parse($u->pivot->contacted_at)->format('d/m/Y') }}</div>
                  <div>{{ \Carbon\Carbon::parse($u->pivot->contacted_at)->format('H:i') }}</div>
                @else
                  —
                @endif
              </td>
              <td style="max-width:260px;">
                @if($u->pivot->contact_note)
                  <span class="text-dark small" title="{{ $u->pivot->contact_note }}">
                    {{ Str::limit($u->pivot->contact_note, 60) }}
                  </span>
                @else
                  <span class="text-muted small">Sin nota</span>
                @endif
              </td>
              @can('lists.manage_members')
              <td class="text-center">
                <div class="d-flex gap-2 justify-content-center">
                  <form method="POST" action="{{ route('crud.lists.members.contacted', [$lista, $u]) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="contacted" value="{{ $u->pivot->contacted ? 0 : 1 }}">
                    <button class="btn btn-sm {{ $u->pivot->contacted ? 'btn-outline-warning' : 'btn-outline-success' }}"
                            title="{{ $u->pivot->contacted ? 'Marcar como no contactado' : 'Marcar como contactado' }}">
                      <i class="fas {{ $u->pivot->contacted ? 'fa-undo' : 'fa-check' }}"></i>
                    </button>
                  </form>
                  <form method="POST"
                        action="{{ route('crud.lists.members.remove', [$lista, $u]) }}"
                        onsubmit="return confirm('¿Quitar a {{ addslashes($u->name) }} de la lista?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" title="Quitar de la lista">
                      <i class="fas fa-user-minus"></i>
                    </button>
                  </form>
                </div>
              </td>
              @endcan
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center py-5">
                <div class="empty-state">
                  <i class="fas fa-users-slash fa-3x text-muted mb-3 d-block"></i>
                  <p class="text-muted mb-2">No hay miembros en esta lista.</p>
                  @can('lists.manage_members')
                    <small class="text-muted">Usa el formulario de arriba para añadir usuarios.</small>
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
        $paged   = $members->appends(request()->query());
        $current = $paged->currentPage();
        $last    = $paged->lastPage();

        // Genera los números visibles: siempre primera, última y ±2 alrededor de la actual
        $visible = collect(range(1, $last))
          ->filter(fn($p) => $p === 1 || $p === $last || abs($p - $current) <= 2)
          ->values();
      @endphp

      <div class="sf-pager-wrapper mt-3">

        {{-- Contador --}}
        @if($paged->total())
          <span class="sf-pager-info">
            Mostrando
            <strong>{{ $paged->firstItem() }}</strong>–<strong>{{ $paged->lastItem() }}</strong>
            de <strong>{{ $paged->total() }}</strong> miembros
          </span>
        @endif

        {{-- Paginador --}}
        @if($paged->hasPages())
        <div class="sf-pager">

          {{-- ← Anterior --}}
          @if($paged->onFirstPage())
            <span class="sf-pager__btn sf-pager__btn--disabled" aria-disabled="true">
              <i class="fas fa-chevron-left"></i>
            </span>
          @else
            <a class="sf-pager__btn" href="{{ $paged->previousPageUrl() }}" rel="prev">
              <i class="fas fa-chevron-left"></i>
            </a>
          @endif

          {{-- Números con puntos suspensivos --}}
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

          {{-- → Siguiente --}}
          @if($paged->hasMorePages())
            <a class="sf-pager__btn" href="{{ $paged->nextPageUrl() }}" rel="next">
              <i class="fas fa-chevron-right"></i>
            </a>
          @else
            <span class="sf-pager__btn sf-pager__btn--disabled" aria-disabled="true">
              <i class="fas fa-chevron-right"></i>
            </span>
          @endif

        </div>
        @endif

      </div>
      {{-- /paginación --}}

    </div>
  </div>
</div>
@endsection

@section('css')
  <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
  <style>
    /* ── Utilidades ─────────────────────────────────────── */
    .gap-2  { gap: .5rem !important; }
    .gap-3  { gap: .75rem !important; }
    .font-weight-semibold { font-weight: 600 !important; }
    .form-label { display: block; }

    /* ── Ícono de cabecera ──────────────────────────────── */
    .list-icon-badge {
      width: 42px; height: 42px;
      background: linear-gradient(135deg, #4f8ef7, #2563eb);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      color: #fff; font-size: 1.1rem;
      flex-shrink: 0;
    }

    /* ── Tarjetas de estadística ────────────────────────── */
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

    /* ── Bloques de sección ─────────────────────────────── */
    .section-block { border: 1px solid #e9ecef; border-radius: 10px; overflow: hidden; }
    .section-block--success { border-color: #d1fae5; }
    .section-block__header {
      background: #f8f9fa; padding: .65rem 1rem;
      font-size: .9rem; border-bottom: 1px solid #e9ecef;
      display: flex; align-items: center;
    }
    .section-block--success .section-block__header {
      background: #ecfdf5; border-bottom-color: #d1fae5;
    }
    .section-block__body { padding: 1rem; }

    /* ── Input group fix ────────────────────────────────── */
    .input-group .border-left-0  { border-left: 0 !important; }
    .input-group-text.border-right-0 { border-right: 0 !important; }

    /* ── Tabla ──────────────────────────────────────────── */
    .table-members thead th {
      font-size: .78rem; text-transform: uppercase;
      letter-spacing: .04em; color: #6b7280; font-weight: 600;
      border-top: 0; padding-top: .75rem; padding-bottom: .75rem;
      white-space: nowrap;
    }
    .table-members tbody td { vertical-align: middle; padding-top: .7rem; padding-bottom: .7rem; }
    .member-row { transition: background .15s; }
    .member-row--contacted { background: #f0fdf4; }
    .member-row:hover { background: #f8faff !important; }

    /* ── Avatar ─────────────────────────────────────────── */
    .member-avatar {
      width: 32px; height: 32px; border-radius: 50%;
      background: linear-gradient(135deg, #818cf8, #4f46e5);
      color: #fff; font-size: .8rem; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }

    /* ── Badges ─────────────────────────────────────────── */
    .badge-status {
      display: inline-flex; align-items: center;
      padding: .3em .75em; border-radius: 999px;
      font-size: .75rem; font-weight: 600; white-space: nowrap;
    }
    .badge-status--success { background: #d1fae5; color: #065f46; }
    .badge-status--pending  { background: #f3f4f6; color: #6b7280; }

    /* ── Estado vacío ───────────────────────────────────── */
    .empty-state { padding: 1rem 0; }

    /* ── Botones outline ────────────────────────────────── */
    .btn-outline-success:hover { color: #fff !important; }
    .btn-outline-warning:hover { color: #fff !important; }
    .btn-outline-danger:hover  { color: #fff !important; }

    /* ══════════════════════════════════════════════════════
       Paginación — CSS puro, sin Bootstrap
    ══════════════════════════════════════════════════════ */
    .sf-pager-wrapper {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: .75rem;
    }

    .sf-pager-info {
      font-size: .82rem;
      color: #6b7280;
    }
    .sf-pager-info strong { color: #374151; }

    .sf-pager {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: .3rem;
      list-style: none;
      margin: 0;
      padding: 0;
    }

    /* Botón base */
    .sf-pager__btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 34px;
      height: 34px;
      padding: 0 .55rem;
      border-radius: 8px;
      border: 1px solid #e5e7eb;
      background: #fff;
      color: #374151;
      font-size: .83rem;
      font-weight: 500;
      text-decoration: none;
      cursor: pointer;
      transition: background .14s, border-color .14s, color .14s, box-shadow .14s;
      user-select: none;
      line-height: 1;
    }

    .sf-pager__btn:hover {
      background: #eff6ff;
      border-color: #93c5fd;
      color: #1d4ed8;
      text-decoration: none;
      box-shadow: 0 1px 4px rgba(37,99,235,.12);
    }

    /* Activo */
    .sf-pager__btn--active {
      background: linear-gradient(135deg, #4f8ef7, #2563eb);
      border-color: transparent;
      color: #fff !important;
      box-shadow: 0 2px 8px rgba(37,99,235,.32);
      pointer-events: none;
      cursor: default;
    }

    /* Deshabilitado */
    .sf-pager__btn--disabled {
      background: #f9fafb;
      border-color: #e5e7eb;
      color: #d1d5db;
      pointer-events: none;
      cursor: not-allowed;
    }

    /* Puntos suspensivos */
    .sf-pager__dots {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 34px;
      height: 34px;
      color: #9ca3af;
      font-size: .9rem;
      letter-spacing: .05em;
    }
  </style>
@endsection
