@props([
    'title' => 'Acceso seguro al ecosistema Sefar',
    'copy' => 'Tu historia familiar puede abrirte las puertas de Europa. Sigue aqui cada expediente, documento y avance hacia tu pasaporte europeo.',
    'kicker' => 'App Sefar Universal',
    'cardTitle' => 'Iniciar sesion',
    'cardEyebrow' => 'Bienvenido de vuelta',
    'cardId' => 'sefar-auth-title',
])

<div class="sefar-login-shell" data-sefar-login>
    <canvas class="sefar-login-canvas" aria-hidden="true"></canvas>
    <div class="sefar-login-overlay" aria-hidden="true"></div>

    <main class="sefar-login-layout">
        <section class="sefar-login-hero" aria-label="App Sefar Universal">
            <img class="sefar-login-hero-logo" src="{{ asset('img/logo2.png') }}" alt="Sefar Universal">

            <p class="sefar-login-kicker">{{ $kicker }}</p>
            <h1>{{ $title }}</h1>
            <p class="sefar-login-copy">{{ $copy }}</p>

            <div class="sefar-login-pulse" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </section>

        <section class="sefar-login-card" aria-labelledby="{{ $cardId }}">
            <div class="sefar-login-card-head">
                <img src="{{ asset('img/logo2.png') }}" alt="Sefar Universal">
                <div>
                    <p>{{ $cardEyebrow }}</p>
                    <h2 id="{{ $cardId }}">{{ $cardTitle }}</h2>
                </div>
            </div>

            {{ $slot }}
        </section>
    </main>
</div>
