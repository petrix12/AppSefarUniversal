@extends('adminlte::page')

@section('title', 'Mi Perfil')

@section('content_header')
@stop

@section('content')
@php
    $user = auth()->user();
    $roleName = $user?->getRoleNames()->first() ?? 'Cliente';
    $location = $user?->city ?: '-';
    $phone = $user?->phone ?: '-';
@endphp

<div class="client-profile-shell">
    <section class="client-profile-card">
        <div class="client-profile-body">
            <div class="client-profile-top">
                <img
                    src="{{ $user->profile_photo_url }}"
                    class="client-profile-avatar"
                    alt="Foto de perfil"
                >

                <button type="button" class="client-profile-edit" onclick="openEditProfile()">
                    <i class="fas fa-edit" aria-hidden="true"></i>
                    <span>Editar perfil</span>
                </button>
            </div>

            <h1>{{ $user->name }}</h1>

            <p class="client-profile-role">
                <i class="fas fa-briefcase" aria-hidden="true"></i>
                <span>{{ $roleName }}</span>
            </p>

            <div class="client-profile-meta">
                <span>
                    <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                    {{ $location }}
                </span>

                <span class="client-profile-dot" aria-hidden="true"></span>

                <span>
                    <i class="fas fa-envelope" aria-hidden="true"></i>
                    <span class="client-profile-email">{{ $user->email }}</span>
                </span>

                <span class="client-profile-dot" aria-hidden="true"></span>

                <span>
                    <i class="fas fa-phone" aria-hidden="true"></i>
                    {{ $phone }}
                </span>
            </div>
        </div>
    </section>
</div>

<div id="editProfileModal" class="client-profile-modal hidden" aria-hidden="true">
    <div class="client-profile-modal-panel">
        <div class="client-profile-modal-head">
            <h2>Editar perfil</h2>
            <button type="button" onclick="closeEditProfile()" aria-label="Cerrar">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>

        <div class="client-profile-modal-body">
            @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                @livewire('profile.update-profile-information-form')
            @endif
        </div>

        <div class="client-profile-modal-foot">
            <button type="button" onclick="closeEditProfile()">Cerrar</button>
        </div>
    </div>
</div>
@stop

@section('css')
<link rel="stylesheet" href="/css/sefar.css">
<link rel="stylesheet" href="/css/app.css">
<style>
    .client-profile-shell {
        width: min(100%, 1490px);
        margin: 0 auto;
        padding: 18px 18px 32px;
    }

    .client-profile-card {
        overflow: hidden;
        min-height: 340px;
        background: #ffffff;
        box-shadow: 0 18px 38px rgba(9, 49, 67, 0.12);
    }

    .client-profile-body {
        padding: 60px 60px 56px;
    }

    .client-profile-top {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 34px;
    }

    .client-profile-avatar {
        width: 88px;
        height: 88px;
        border: 5px solid #ffffff;
        border-radius: 999px;
        background: #eef5ff;
        object-fit: cover;
        box-shadow: 0 18px 28px rgba(9, 49, 67, 0.18);
    }

    .client-profile-edit {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        min-height: 45px;
        border: 0;
        border-radius: 8px;
        padding: 0 1.35rem;
        background: #a8843e;
        color: #ffffff;
        font-weight: 800;
        box-shadow: 0 12px 22px rgba(168, 132, 62, 0.22);
    }

    .client-profile-edit:hover {
        background: #8f7137;
        color: #ffffff;
    }

    .client-profile-card h1 {
        margin: 0 0 4px;
        color: #001b27;
        font-size: 1.85rem;
        font-weight: 900;
        line-height: 1.1;
    }

    .client-profile-role,
    .client-profile-meta {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.45rem;
        color: #162337;
        font-size: 1rem;
    }

    .client-profile-role {
        margin: 0 0 10px;
        font-weight: 700;
    }

    .client-profile-meta span {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .client-profile-dot {
        width: 5px;
        height: 5px;
        border-radius: 999px;
        background: #162337;
    }

    .client-profile-email {
        word-break: break-word;
    }

    .client-profile-modal {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(0, 0, 0, 0.62);
    }

    .client-profile-modal.hidden {
        display: none;
    }

    .client-profile-modal-panel {
        width: min(100%, 720px);
        max-height: min(720px, 92vh);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border-radius: 8px;
        background: #ffffff;
        box-shadow: 0 26px 70px rgba(0, 0, 0, 0.32);
    }

    .client-profile-modal-head,
    .client-profile-modal-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 20px;
        border-bottom: 1px solid rgba(9, 49, 67, 0.12);
    }

    .client-profile-modal-head h2 {
        margin: 0;
        color: #001b27;
        font-size: 1.1rem;
        font-weight: 900;
    }

    .client-profile-modal-head button,
    .client-profile-modal-foot button {
        border: 0;
        border-radius: 8px;
        padding: 0.55rem 0.8rem;
        background: #eef5f8;
        color: #15384a;
        font-weight: 800;
    }

    .client-profile-modal-body {
        overflow-y: auto;
        padding: 20px;
    }

    .client-profile-modal-foot {
        justify-content: flex-end;
        border-top: 1px solid rgba(9, 49, 67, 0.12);
        border-bottom: 0;
        background: #f7fafc;
    }

    body.modal-open {
        overflow: hidden;
    }

    @media (max-width: 767.98px) {
        .client-profile-shell {
            padding: 12px;
        }

        .client-profile-body {
            padding: 0 22px 36px;
        }

        .client-profile-top {
            align-items: flex-start;
            flex-direction: column;
            margin-bottom: 24px;
        }

        .client-profile-edit {
            width: 100%;
        }
    }
</style>
@stop

@section('js')
<script>
    function openEditProfile() {
        const modal = document.getElementById('editProfileModal');
        if (!modal) return;

        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function closeEditProfile() {
        const modal = document.getElementById('editProfileModal');
        if (!modal) return;

        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }

    document.addEventListener('click', function (event) {
        const modal = document.getElementById('editProfileModal');
        if (!modal || modal.classList.contains('hidden')) return;

        if (event.target === modal) {
            closeEditProfile();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeEditProfile();
        }
    });
</script>
@stop
