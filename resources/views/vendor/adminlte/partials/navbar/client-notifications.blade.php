@php
    $notificationsReady = false;
    $unreadCount = 0;
    $latestNotifications = collect();

    try {
        $notificationsReady = auth()->check()
            && \Illuminate\Support\Facades\Schema::hasTable('notifications');

        if ($notificationsReady) {
            $unreadCount = auth()->user()->unreadNotifications()->count();
            $latestNotifications = auth()->user()
                ->notifications()
                ->latest()
                ->limit(5)
                ->get();
        }
    } catch (\Throwable $e) {
        $notificationsReady = false;
    }
@endphp

    <li class="nav-item dropdown" id="appNotificationsMenu">
        <a class="nav-link text-white" data-toggle="dropdown" href="#" aria-label="Notificaciones" style="color:#fff !important;">
            <i class="far fa-bell fa-lg"></i>
            @if($unreadCount > 0)
                <span class="badge badge-warning navbar-badge">{{ $unreadCount }}</span>
            @endif
        </a>

        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
            <span class="dropdown-item dropdown-header">
                {{ $unreadCount }} notificacion(es) sin leer
            </span>
            <div class="dropdown-divider"></div>

            @if(!$notificationsReady)
                <a href="{{ route('notifications.index') }}" class="dropdown-item text-muted text-center py-3">
                    Notificaciones pendientes de activar
                </a>
                <div class="dropdown-divider"></div>
            @else
            @forelse($latestNotifications as $notification)
                @php
                    $data = $notification->data ?? [];
                    $title = $data['title'] ?? 'Notificacion';
                    $body = $data['body'] ?? '';
                    $category = $data['category'] ?? 'general';
                    $icon = match($category) {
                        'cos_status' => 'fa-route',
                        'internal_chat' => 'fa-comments',
                        'document_request' => 'fa-file-alt',
                        default => 'fa-bell',
                    };
                @endphp

                <a href="{{ route('notifications.index') }}" class="dropdown-item {{ is_null($notification->read_at) ? 'font-weight-bold' : '' }}">
                    <i class="fas {{ $icon }} mr-2"></i>
                    {{ \Illuminate\Support\Str::limit($title, 34) }}
                    <span class="float-right text-muted text-sm">{{ optional($notification->created_at)->diffForHumans() }}</span>
                    @if($body)
                        <div class="text-muted text-sm mt-1">{{ \Illuminate\Support\Str::limit($body, 58) }}</div>
                    @endif
                </a>
                <div class="dropdown-divider"></div>
            @empty
                <span class="dropdown-item text-muted text-center py-3">Sin notificaciones</span>
                <div class="dropdown-divider"></div>
            @endforelse
            @endif

            <a href="{{ route('notifications.index') }}" class="dropdown-item dropdown-footer">
                Ver todas
            </a>
        </div>
    </li>

@once
    <style>
        #appNotificationsMenu > .nav-link,
        #appNotificationsMenu > .nav-link:hover,
        #appNotificationsMenu > .nav-link:focus,
        #appNotificationsMenu > .nav-link:active {
            color: #fff !important;
        }
    </style>
@endonce
