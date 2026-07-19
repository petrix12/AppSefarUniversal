<x-guest-layout>
    <div class="sefar-login-shell" data-sefar-login>
        <canvas class="sefar-login-canvas" aria-hidden="true"></canvas>
        <div class="sefar-login-overlay" aria-hidden="true"></div>

        <main class="sefar-login-layout">
            <section class="sefar-login-hero" aria-label="App Sefar Universal">
                <img class="sefar-login-hero-logo" src="{{ asset('img/logo2.png') }}" alt="Sefar Universal">

                <p class="sefar-login-kicker">App Sefar Universal</p>
                <h1>Acceso seguro al ecosistema Sefar</h1>
                <p class="sefar-login-copy">
                    Tu historia familiar puede abrirte las puertas de Europa. Sigue aqui cada expediente, documento y avance hacia tu pasaporte europeo.
                </p>

                <div class="sefar-login-pulse" aria-hidden="true">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </section>

            <section class="sefar-login-card" aria-labelledby="sefar-login-title">
                <div class="sefar-login-card-head">
                    <img src="{{ asset('img/logo2.png') }}" alt="Sefar Universal">
                    <div>
                        <p>Bienvenido de vuelta</p>
                        <h2 id="sefar-login-title">Iniciar sesion</h2>
                    </div>
                </div>

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

                    @if (!Route::has('register'))
                        <p class="sefar-login-register">
                            No tienes cuenta?
                            <a href="{{ route('register') }}">Crear registro</a>
                        </p>
                    @endif
                </form>
            </section>
        </main>
    </div>
</x-guest-layout>
