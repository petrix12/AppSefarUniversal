<x-guest-layout>
    <x-sefar-auth-shell
        title="Define una nueva contrasena"
        copy="Protege tu cuenta antes de volver a tu expediente. El enlace de recuperacion solo debe usarse desde tu correo personal."
        card-id="sefar-reset-title"
        card-title="Cambiar contrasena"
        card-eyebrow="Recuperacion segura"
    >
        <x-validation-errors class="sefar-login-errors" />

        <form class="sefar-login-form" method="POST" action="{{ route('password.update') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <label for="email" class="sefar-login-field">
                <span>Email</span>
                <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
            </label>

            <label for="password" class="sefar-login-field">
                <span>Nueva contrasena</span>
                <input id="password" type="password" name="password" required autocomplete="new-password">
            </label>

            <label for="password_confirmation" class="sefar-login-field">
                <span>Confirmar nueva contrasena</span>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </label>

            <button class="sefar-login-button" type="submit">
                <span>Guardar nueva contrasena</span>
                <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                    <path d="M11.2 4.7a.8.8 0 0 1 1.1 0l4.2 4.2a.8.8 0 0 1 0 1.2l-4.2 4.2a.8.8 0 1 1-1.1-1.2l2.8-2.8H4.1a.8.8 0 1 1 0-1.6H14l-2.8-2.8a.8.8 0 0 1 0-1.2Z" fill="currentColor"/>
                </svg>
            </button>
        </form>
    </x-sefar-auth-shell>
</x-guest-layout>
