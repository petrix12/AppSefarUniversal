<x-guest-layout>
    <x-sefar-auth-shell
        card-id="sefar-login-title"
        card-title="Iniciar sesion"
        card-eyebrow="Bienvenido de vuelta"
    >
        <x-validation-errors class="sefar-login-errors" />

        @if (session('status'))
            <div class="sefar-login-status">
                {{ session('status') }}
            </div>
        @endif

        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        @if(session()->has('warning'))
            <script>
                Swal.fire({
                  icon: 'warning',
                  title: 'Aviso',
                  html: '{{ session("warning") }}'
                })
            </script>
        @endif

        @if(request()->query('alert') === 'existe')
            <script>
                Swal.fire({
                    icon: 'info',
                    title: 'Bienvenido de vuelta',
                    html: 'Ya te encuentras registrado en la App de Sefar.<br>Si no recuerdas tu usuario o contrasena, puedes <a href="/forgot-password">recuperarla aqui</a>.',
                    confirmButtonText: 'Entendido'
                })
            </script>
        @endif

        <form class="sefar-login-form" method="POST" action="{{ route('login') }}">
            @csrf

            <label for="email" class="sefar-login-field">
                <span>Email</span>
                <input id="email" type="email" name="email" value="{{ old('email', session('email')) }}" required autofocus autocomplete="username">
            </label>

            <label for="password" class="sefar-login-field">
                <span>Password</span>
                <input id="password" type="password" name="password" required autocomplete="current-password">
            </label>

            <div class="sefar-login-options">
                <label for="remember_me" class="sefar-login-check">
                    <input id="remember_me" type="checkbox" name="remember">
                    <span>Recordarme</span>
                </label>

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}">Olvide mi password</a>
                @endif
            </div>

            <button class="sefar-login-button" type="submit">
                <span>Entrar a la app</span>
                <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                    <path d="M11.2 4.7a.8.8 0 0 1 1.1 0l4.2 4.2a.8.8 0 0 1 0 1.2l-4.2 4.2a.8.8 0 1 1-1.1-1.2l2.8-2.8H4.1a.8.8 0 1 1 0-1.6H14l-2.8-2.8a.8.8 0 0 1 0-1.2Z" fill="currentColor"/>
                </svg>
            </button>
        </form>
    </x-sefar-auth-shell>
</x-guest-layout>
