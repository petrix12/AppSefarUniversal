@php
    $md = \Carbon\Carbon::now()->format('m-d');
    $showSnow = ($md >= '12-21' || $md <= '01-07');
@endphp

<aside class="main-sidebar {{ config('adminlte.classes_sidebar', 'sidebar-dark-primary elevation-4') }}
     @if($showSnow) sidebar-snow @endif">

    {{-- Sidebar brand logo --}}
    @if(config('adminlte.logo_img_xl'))
        @include('adminlte::partials.common.brand-logo-xl')
    @else
        @include('adminlte::partials.common.brand-logo-xs')
    @endif

    {{-- Sidebar menu --}}
    <div class="sidebar">
        @php($u = auth()->user())
        @if($u)
            <div class="user-panel mt-3 pb-3 mb-2 d-flex align-items-center">
                <div class="image">
                    <img
                        src="{{ method_exists($u,'adminlte_image') ? $u->adminlte_image() : asset('img/default-avatar.png') }}"
                        class="img-circle elevation-2"
                        alt="User Image"
                        style="width:34px;height:34px;object-fit:cover;"
                    >
                </div>

                <div class="info" style="line-height:1.1;">
                    <a href="{{ url('user/profile') }}" class="d-block" style="white-space:normal;">
                        {{ trim(($u->name ?? '').' '.($u->lastname ?? '')) ?: $u->email }}
                    </a>
                    <small class="text-muted" style="display:block;white-space:normal;">
                        {{ $u->email }}
                    </small>
                </div>
            </div>
        @endif
        <nav class="pt-2">
            <ul class="nav nav-pills nav-sidebar flex-column {{ config('adminlte.classes_sidebar_nav', '') }}"
                data-widget="treeview" role="menu"
                @if(config('adminlte.sidebar_nav_animation_speed') != 300)
                    data-animation-speed="{{ config('adminlte.sidebar_nav_animation_speed') }}"
                @endif
                @if(!config('adminlte.sidebar_nav_accordion'))
                    data-accordion="false"
                @endif>
                {{-- Configured sidebar links --}}
                @each('adminlte::partials.sidebar.menu-item', $adminlte->menu('sidebar'), 'item')
            </ul>
        </nav>
    </div>

    {{-- CONTENEDOR DE NIEVE - SOLO SI ES TEMPORADA --}}
    @if($showSnow)
    <div class="sidebar-snow-container">
        @for($i = 0; $i < 25; $i++)
            <div class="sidebar-snowflake"
                 style="
                    left: {{ rand(0, 100) }}%;
                    --delay: {{ rand(0, 30) / 10 }}s;
                    --duration: {{ rand(8, 15) }}s;
                    --size: {{ rand(6, 14) }}px;
                    --opacity: {{ rand(50, 90) / 100 }};
                 ">
                {{ collect(['❄','❅','❆','✻'])->random() }}
            </div>
        @endfor
    </div>
    @endif
</aside>

@if($showSnow)
<style>
/* NIEVE SOLO EN SIDEBAR */
.sidebar-snow-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 15;
    overflow: hidden;
}

.sidebar-snowflake {
    position: absolute;
    top: -20px;
    font-size: var(--size, 10px);
    opacity: var(--opacity, 0.8);
    color: #e3f2fd;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
    animation: sidebarSnowfall var(--duration, 10s) linear infinite;
    animation-delay: var(--delay, 0s);
    will-change: transform;
}

/* Animación suave dentro del sidebar */
@keyframes sidebarSnowfall {
    0% {
        transform: translateY(-30px) rotate(0deg) translateX(0px);
        opacity: var(--opacity, 0.8);
    }
    50% {
        transform: translateY(50vh) rotate(180deg) translateX(20px);
        opacity: calc(var(--opacity, 0.8) * 0.7);
    }
    100% {
        transform: translateY(100vh) rotate(360deg) translateX(-10px);
        opacity: 0;
    }
}

/* Responsive: menos nieve en mobile */
@media (max-width: 768px) {
    .sidebar-snowflake {
        --size: 8px !important;
    }
    .sidebar-snow-container {
        display: none; /* Ocultar en mobile colapsado */
    }
}
</style>
@endif
