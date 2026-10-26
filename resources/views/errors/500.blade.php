<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Error 500 | {{ config('app.name') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f5f7fa; color: #1f2937; font-family: Arial, sans-serif; }
        main { width: min(92%, 620px); padding: 40px; background: #fff; border-radius: 12px; box-shadow: 0 12px 35px rgba(15, 23, 42, .08); text-align: center; }
        h1 { margin: 0 0 12px; font-size: 34px; }
        p { margin: 10px 0; color: #4b5563; }
        code { display: inline-block; margin-top: 16px; padding: 8px 12px; border-radius: 6px; background: #eef2f7; color: #374151; }
    </style>
</head>
<body>
    <main>
        <h1>Error 500</h1>
        <p>Ocurrió un error interno. El equipo técnico recibió el reporte automáticamente.</p>
        @if(! empty($reference))
            <p>Si necesitas contactarnos, incluye esta referencia:</p>
            <code>{{ $reference }}</code>
        @endif
    </main>
</body>
</html>
