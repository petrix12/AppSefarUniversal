<x-guest-layout>
    <x-sefar-auth-shell
        title="Acceso restringido"
        copy="Tu sesión está activa, pero el acceso a las ventanas de la aplicación está bloqueado."
        card-title="Acceso restringido"
        card-eyebrow="Cuenta de cliente"
        card-id="client-access-blocked-title"
    >
        @php($blockedUser = auth()->user())

        @if($blockedUser?->is_blocked)
            <div role="alert" style="margin:18px 0; padding:16px; border:1px solid #fecaca; border-radius:10px; background:#fef2f2; color:#7f1d1d;">
                <strong>Motivo del bloqueo</strong>
                <p style="margin:6px 0 0; white-space:pre-line;">{{ $blockedUser->clientBlockReasonDescription() }}</p>
            </div>
        @else
            <div role="alert" style="margin:18px 0; padding:16px; border:1px solid #fed7aa; border-radius:10px; background:#fff7ed; color:#7c2d12;">
                <strong>Acceso temporalmente restringido</strong>
                <p style="margin:6px 0 0;">En este momento las ventanas de la aplicación no están habilitadas para clientes.</p>
            </div>
        @endif

        <div style="margin:18px 0; padding:16px; border:1px solid #dbe5e9; border-radius:10px; color:#526b77;">
            <strong style="color:#243b45;">¿Necesitas aclarar este bloqueo?</strong>
            <p style="margin:6px 0 10px;">Comunícate con atención al cliente para revisar tu caso.</p>
            <p style="margin:0 0 8px;">
                Correo: <a href="mailto:{{ config('app.client_access_support_email') }}">{{ config('app.client_access_support_email') }}</a>
            </p>
            <ul style="margin:0; padding-left:20px;">
                @foreach(config('app.client_access_support_phones', []) as $phone)
                    <li>{{ $phone['label'] }}: <a href="tel:{{ $phone['link'] }}">{{ $phone['number'] }}</a></li>
                @endforeach
            </ul>
        </div>

        <form class="sefar-login-form" method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="sefar-login-button" type="submit">
                <span>Cerrar sesión</span>
            </button>
        </form>
    </x-sefar-auth-shell>
</x-guest-layout>
