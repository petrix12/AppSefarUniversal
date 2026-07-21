<x-guest-layout>
    <x-sefar-auth-shell
        title="Verifica tu correo"
        copy="Tu correo es la llave principal para recibir accesos, notificaciones y comunicaciones importantes sobre tu proceso."
        card-id="sefar-verify-title"
        card-title="Confirmar email"
        card-eyebrow="Cuenta protegida"
    >
        <p class="sefar-login-text">
            Antes de continuar, revisa tu bandeja de entrada y confirma el enlace que te enviamos. Si no lo recibiste, podemos enviarlo nuevamente.
        </p>

        @if (session('status') == 'verification-link-sent')
            <div class="sefar-login-status">
                Se envio un nuevo enlace de verificacion al correo registrado.
            </div>
        @endif

        <div class="sefar-login-actions sefar-login-actions--split">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf

                <button class="sefar-login-button sefar-login-button--compact" type="submit">
                    <span>Reenviar verificacion</span>
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button class="sefar-login-link-button" type="submit">
                    Cerrar sesion
                </button>
            </form>
        </div>
    </x-sefar-auth-shell>
</x-guest-layout>
