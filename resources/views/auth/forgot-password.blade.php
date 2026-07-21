<x-guest-layout>
    <x-sefar-auth-shell
        title="Recupera el acceso a tu cuenta"
        copy="Te enviaremos un enlace seguro para restablecer tu contrasena y volver a consultar tus procesos, documentos y avances."
        card-id="sefar-forgot-title"
        card-title="Contrasena olvidada"
        card-eyebrow="Acceso seguro"
    >
        <p class="sefar-login-text">
            Escribe el correo asociado a tu usuario. Si existe una cuenta, recibiras un enlace para crear una nueva contrasena.
        </p>

        @if (session('status'))
            <div class="sefar-login-status">
                {{ session('status') }}
            </div>
        @endif

        <x-validation-errors class="sefar-login-errors" />

        <form class="sefar-login-form" method="POST" action="{{ route('password.email') }}">
            @csrf

            <label for="email" class="sefar-login-field">
                <span>Email</span>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </label>

            <button class="sefar-login-button" type="submit">
                <span>Enviar enlace de recuperacion</span>
                <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                    <path d="M11.2 4.7a.8.8 0 0 1 1.1 0l4.2 4.2a.8.8 0 0 1 0 1.2l-4.2 4.2a.8.8 0 1 1-1.1-1.2l2.8-2.8H4.1a.8.8 0 1 1 0-1.6H14l-2.8-2.8a.8.8 0 0 1 0-1.2Z" fill="currentColor"/>
                </svg>
            </button>

            <p class="sefar-login-register">
                Ya recordaste tu contrasena?
                <a href="{{ route('login') }}">Volver al login</a>
            </p>
        </form>
    </x-sefar-auth-shell>
</x-guest-layout>
