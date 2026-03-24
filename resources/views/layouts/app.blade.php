<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=UA-189067277-1"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', 'UA-189067277-1');
        </script>

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="shortcut icon" type="image/x-icon" href="{{ asset('/favicon.ico') }}">

        {{-- Inicio - Personalización URL --}}
        <meta property="fb:app_id" content="APPID">
        <meta data-react-helmet="true" property="og:url" content="https://app.sefaruniversal.com/"/>
        <meta data-react-helmet="true" property="og:type" content="website"/>
        <meta data-react-helmet="true" property="og:title" content="Sefar Universal | Tus antepasados te quieren libre."/>
        <meta data-react-helmet="true" property="og:description" content="Abogados y genealogistas expertos en inmigración. Conseguimos tu pasaporte español, portugues e italiano, para que seas libre, trascendiendo fronteras."/>

        <meta data-react-helmet="true" property="og:image" content="https://app.sefaruniversal.com/vendor/adminlte/dist/img/LogoSefar.png" />
        <meta data-react-helmet="true" property="twitter:title" content="Sefar Universal | Tus antepasados te quieren libre."/>
        <meta data-react-helmet="true" property="twitter:description" content="Abogados y genealogistas expertos en inmigración. Conseguimos tu pasaporte español, portugues e italiano, para que seas libre, trascendiendo fronteras."/>
        <meta data-react-helmet="true" property="twitter:image:src" content="https://app.sefaruniversal.com/vendor/adminlte/dist/img/LogoSefar.png" />
        <meta data-react-helmet="true" property="twitter:image" content="https://app.sefaruniversal.com/vendor/adminlte/dist/img/LogoSefar.png" />
        <meta data-react-helmet="true" property="twitter:card" content="summary" />
        <meta data-react-helmet="true" name="robots" content="noindex, nofollow" />
        {{-- Fin - Personalización URL --}}

                <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap">

        <!-- Styles -->
        <link rel="stylesheet" href="{{ asset('/css/app.css') }}">
        <link rel="stylesheet" href="{{ asset('/css/sefar.css') }}">

        @livewireStyles

        <!-- Scripts -->
        <script src="{{ asset('/js/app.js') }}" defer></script>
        <script type="text/javascript" id="hs-script-loader" async defer src="//js.hs-scripts.com/20053496.js"></script>

        <script async src="//www.googletagmanager.com/gtag/js?id=UA-189067277-1"></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());

          gtag('config', 'UA-189067277-1');
        </script>

    </head>
    <body class="font-sans antialiased">
        <x-banner />

        <div class="min-h-screen bg-gray-100">

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

        <!-- Start of HubSpot Embed Code -->
        <script type="text/javascript" id="hs-script-loader" async defer src="//js.hs-scripts.com/20053496.js"></script>
        <!-- End of HubSpot Embed Code -->
        @if(auth()->user() && auth()->user()->hasRole('Administrador'))
            <x-chat-bubble />
        @endif

    </body>
</html>
