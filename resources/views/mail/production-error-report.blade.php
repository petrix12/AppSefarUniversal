<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Error de producción {{ $report['reference'] }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #202124; line-height: 1.45;">
    <h2>Error interno en {{ config('app.name') }}</h2>
    <p><strong>Referencia:</strong> {{ $report['reference'] }}</p>
    <p><strong>Fecha:</strong> {{ $report['occurred_at']->format('d/m/Y H:i:s T') }}</p>
    <p><strong>Resultado del servidor:</strong> HTTP {{ $report['response']['status_code'] }} ({{ $report['response']['content_type'] ?: 'sin content-type' }})</p>

    <h3>Petición</h3>
    <ul>
        <li><strong>Método y URL:</strong> {{ $report['request']['method'] }} {{ $report['request']['url'] }}</li>
        <li><strong>Ruta:</strong> {{ $report['request']['route_name'] ?: 'sin nombre' }}</li>
        <li><strong>IP:</strong> {{ $report['request']['ip_address'] ?: 'no disponible' }}</li>
        <li><strong>Usuario:</strong> {{ $report['user']['id'] ?: 'anónimo' }} — {{ $report['user']['email'] ?: 'sin correo' }}</li>
        <li><strong>User-Agent:</strong> {{ $report['request']['user_agent'] ?: 'no disponible' }}</li>
    </ul>

    <h3>Datos sanitizados</h3>
    <pre style="white-space: pre-wrap; background: #f5f5f5; padding: 12px;">{{ json_encode($report['request']['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>

    @if(! empty($report['request']['files']))
        <h3>Archivos (solo metadatos)</h3>
        <pre style="white-space: pre-wrap; background: #f5f5f5; padding: 12px;">{{ json_encode($report['request']['files'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    @endif

    <h3>Excepción</h3>
    <p><strong>{{ $report['exception']['class'] }}</strong></p>
    <p>{{ $report['exception']['message'] }}</p>
    <p>{{ $report['exception']['file'] }}:{{ $report['exception']['line'] }}</p>

    <h3>Respuesta generada originalmente</h3>
    <pre style="white-space: pre-wrap; background: #f5f5f5; padding: 12px;">{{ $report['response']['body'] ?: '(sin cuerpo)' }}</pre>

    <h3>Traza</h3>
    <pre style="white-space: pre-wrap; background: #f5f5f5; padding: 12px;">{{ $report['exception']['trace'] }}</pre>
</body>
</html>
