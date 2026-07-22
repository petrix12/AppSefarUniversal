@extends('adminlte::page')

@section('title', 'Notificaciones')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h1 class="mb-0">Notificaciones</h1>

        @if(($notificationsReady ?? false) && auth()->user()->unreadNotifications()->count() > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-check-double mr-1"></i>
                    Marcar todas como leidas
                </button>
            </form>
        @endif
    </div>
@endsection

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(!($notificationsReady ?? false))
        <div class="alert alert-warning">
            Las notificaciones internas aun no estan activas. Los avisos por correo pueden enviarse sin migrar la base de datos.
        </div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            @forelse($notifications as $notification)
                @php
                    $data = \App\Support\NotificationViewData::normalize($notification);
                    $title = $data['title'] ?? 'Notificacion';
                    $body = $data['body'] ?? '';
                    $category = $data['category'] ?? 'general';
                    $actionUrl = $data['action_url'] ?? null;
                    $actionText = $data['action_text'] ?? ($actionUrl ? 'Ver detalle' : 'Marcar como leida');
                    $isUnread = is_null($notification->read_at);
                    $icon = match($category) {
                        'cos_status' => 'fa-route',
                        'banca_online' => 'fa-credit-card',
                        'internal_chat' => 'fa-comments',
                        'document_request' => 'fa-file-alt',
                        'tasks' => 'fa-tasks',
                        default => 'fa-bell',
                    };
                @endphp

                <div class="d-flex align-items-start p-3 border-bottom {{ $isUnread ? 'bg-light' : '' }}">
                    <div class="mr-3 text-primary">
                        <i class="fas {{ $icon }} fa-lg"></i>
                    </div>

                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <h5 class="mb-1">
                                    {{ $title }}
                                    @if($isUnread)
                                        <span class="badge badge-primary ml-1">Nueva</span>
                                    @endif
                                </h5>
                                <p class="mb-1 text-muted">{{ $body }}</p>
                                <small class="text-muted">{{ optional($notification->created_at)->format('d/m/Y H:i') }}</small>
                            </div>

                            <div class="text-right">
                                @if($isUnread)
                                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm {{ $actionUrl ? 'btn-primary' : 'btn-outline-secondary' }}">
                                            {{ $actionText }}
                                        </button>
                                    </form>
                                @elseif($actionUrl)
                                    <a href="{{ $actionUrl }}" class="btn btn-sm btn-outline-secondary">
                                        {{ $actionText }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center text-muted py-5">
                    <i class="far fa-bell fa-2x mb-2"></i>
                    <div>No tienes notificaciones.</div>
                </div>
            @endforelse
        </div>
    </div>

    {{ $notifications->links() }}
@endsection
