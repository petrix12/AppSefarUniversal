<div class="mb-3">
    <ul class="nav nav-pills monday-admin-nav">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.monday.index', 'admin.monday.boards.*', 'admin.monday.items.*') ? 'active' : '' }}"
               href="{{ route('admin.monday.index') }}">
                <i class="fas fa-table mr-1"></i> Tableros
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.monday.activity') ? 'active' : '' }}"
               href="{{ route('admin.monday.activity') }}">
                <i class="fas fa-exchange-alt mr-1"></i> Actividad
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('mondayreportes') ? 'active' : '' }}"
               href="{{ route('mondayreportes') }}">
                <i class="fas fa-chart-pie mr-1"></i> Reportes
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('mondayregistrar') ? 'active' : '' }}"
               href="{{ route('mondayregistrar') }}">
                <i class="fas fa-plus-circle mr-1"></i> Registrar
            </a>
        </li>
    </ul>
</div>

