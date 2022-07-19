<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="shortcut icon" type="image/x-icon" href="{{ asset('/favicon.ico') }}">

        <title>{{ config('app.name', 'Laravel') }}</title>
        {{-- <link rel="shortcut icon" type="image/x-icon" href="{{ Storage::disk('s3')->url('imagenes/auxiliar/favicon.ico') }}"> --}}

        <!-- Fonts -->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap">

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

        <!-- Styles -->
        <link rel="stylesheet" href="{{ asset('/css/app.css') }}">
        <link rel="stylesheet" href="{{ asset('/css/sefar.css') }}">

        <!-- Scripts -->
        <script src="{{ asset('/js/app.js') }}" defer></script>
    </head>
    <body>
        <div class="font-sans text-gray-900 antialiased">
            {{ $slot }}
        </div>
    </body>
</html>
