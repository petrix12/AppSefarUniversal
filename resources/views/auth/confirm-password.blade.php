<x-guest-layout>
    <x-sefar-auth-shell
        title="Confirma que eres tu"
        copy="Algunas acciones del expediente requieren una verificacion adicional para proteger tu informacion personal y documental."
        card-id="sefar-confirm-title"
        card-title="Confirmar contrasena"
        card-eyebrow="Area protegida"
    >
        <p class="sefar-login-text">
            Ingresa tu contrasena actual para continuar con esta accion segura dentro de la app.
        </p>

        <x-validation-errors class="sefar-login-errors" />

        <form class="sefar-login-form" method="POST" action="{{ route('password.confirm') }}">
            @csrf

            <label for="password" class="sefar-login-field">
                <span>Contrasena</span>
                <input id="password" type="password" name="password" required autocomplete="current-password" autofocus>
            </label>

            <button class="sefar-login-button" type="submit">
                <span>Confirmar acceso</span>
                <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                    <path d="M11.2 4.7a.8.8 0 0 1 1.1 0l4.2 4.2a.8.8 0 0 1 0 1.2l-4.2 4.2a.8.8 0 1 1-1.1-1.2l2.8-2.8H4.1a.8.8 0 1 1 0-1.6H14l-2.8-2.8a.8.8 0 0 1 0-1.2Z" fill="currentColor"/>
                </svg>
            </button>
        </form>
    </x-sefar-auth-shell>
</x-guest-layout>
