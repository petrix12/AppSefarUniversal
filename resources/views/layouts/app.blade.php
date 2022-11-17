<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="shortcut icon" type="image/x-icon" href="{{ asset('/favicon.ico') }}">

        {{-- Inicio - Personalización URL --}}
        <meta property="fb:app_id" content="APPID">
        <meta data-react-helmet="true" property="og:url" content="https://app.universalsefar.com/"/>
        <meta data-react-helmet="true" property="og:type" content="website"/>
        <meta data-react-helmet="true" property="og:title" content="Sefar Universal | Tus antepasados te quieren libre."/>
        <meta data-react-helmet="true" property="og:description" content="Abogados y genealogistas expertos en inmigración. Conseguimos tu pasaporte español, portugues e italiano, para que seas libre, trascendiendo fronteras."/>

        <meta data-react-helmet="true" property="og:image" content="https://app.universalsefar.com/vendor/adminlte/dist/img/LogoSefar.png" />
        <meta data-react-helmet="true" property="twitter:title" content="Sefar Universal | Tus antepasados te quieren libre."/>
        <meta data-react-helmet="true" property="twitter:description" content="Abogados y genealogistas expertos en inmigración. Conseguimos tu pasaporte español, portugues e italiano, para que seas libre, trascendiendo fronteras."/>
        <meta data-react-helmet="true" property="twitter:image:src" content="https://app.universalsefar.com/vendor/adminlte/dist/img/LogoSefar.png" />
        <meta data-react-helmet="true" property="twitter:image" content="https://app.universalsefar.com/vendor/adminlte/dist/img/LogoSefar.png" />
        <meta data-react-helmet="true" property="twitter:card" content="summary" />
        <meta data-react-helmet="true" name="robots" content="noindex, nofollow" />
        {{-- Fin - Personalización URL --}}

        <!-- Meta tags PWA -->
        <meta name="theme-color" content="#333333">
        <meta name="MobileOptimized" content="width">
        <meta name="HandheldFriendly" content="true">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

        <!-- Iconos PWA -->
        <link rel="shortcut icon" href="{{ asset("./Logo.png") }}" type="image/png">
        <link rel="apple-touch-icon" href="{{ asset("./Logo.png") }}" type="image/png">
        <link rel="apple-touch-startup-image" href="{{ asset("./Logo.png") }}" type="image/png">

        <!-- Manifest PWA -->
        <link rel="manifest" href="{{ asset("./manifest.json") }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap">

        <!-- Styles -->
        <link rel="stylesheet" href="{{ asset('/css/app.css') }}">
        <link rel="stylesheet" href="{{ asset('/css/sefar.css') }}">

        @livewireStyles

        <!-- Scripts -->
        <script src="{{ asset('/js/app.js') }}" defer></script>
        <script src={{ asset("./register.js") }}></script>
    </head>
    <body class="font-sans antialiased">
        <x-jet-banner />

        <div class="min-h-screen bg-gray-100">
            @livewire('navigation-menu')

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main>
                @include('sweetalert::alert', ['cdn' => "https://cdn.jsdelivr.net/npm/sweetalert2@9"])
                {{ $slot }}
            </main>
        </div>

        @stack('modals')

        @livewireScripts
    </body>
</html>
