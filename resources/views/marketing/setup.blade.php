@extends('adminlte::page')

@section('title', 'Configurar Amazon SES')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center"><h1 class="mb-0"><i class="fab fa-aws text-warning mr-2"></i>Configurar Amazon SES</h1><a href="{{ route('marketing.dashboard') }}" class="btn btn-outline-secondary">Panel</a></div>
@stop

@section('content')
    <div class="alert alert-{{ $configured ? 'success' : 'warning' }}"><i class="fas fa-{{ $configured ? 'check-circle' : 'exclamation-triangle' }} mr-1"></i>{{ $configured ? 'Las credenciales y el remitente de campañas están configurados.' : 'Faltan las credenciales o el remitente de Amazon SES para campañas.' }}</div>
    <div class="card card-outline card-primary"><div class="card-header"><h3 class="card-title">Variables de entorno</h3></div><div class="card-body"><p>Configura estas variables en el archivo <code>.env</code> de producción. Son independientes de <code>MAIL_*</code> y de las credenciales AWS usadas por otros módulos.</p><pre class="bg-light border rounded p-3 mb-0">MARKETING_SES_ACCESS_KEY_ID=
MARKETING_SES_SECRET_ACCESS_KEY=
MARKETING_SES_REGION=us-east-1
MARKETING_SES_FROM_EMAIL=campanas@tu-dominio.com
MARKETING_SES_FROM_NAME="Tu empresa"
MARKETING_SES_REPLY_TO=contacto@tu-dominio.com
MARKETING_SES_CONFIGURATION_SET=sefar-marketing
MARKETING_QUEUE_CONNECTION=database
MARKETING_QUEUE_NAME=marketing
MARKETING_QUEUE_WORKER_ENABLED=false
MARKETING_SES_SNS_TOPIC_ARNS=arn:aws:sns:us-east-1:CUENTA:sefar-marketing-events</pre></div></div>
    <div class="card card-outline card-secondary">
        <div class="card-header"><h3 class="card-title">Eventos y analíticas de SES</h3></div>
        <div class="card-body">
            <ol>
                <li>Verifica el dominio o remitente <code>MARKETING_SES_FROM_EMAIL</code> en SES y solicita acceso de producción antes de enviar a toda la lista.</li>
                <li>Crea el conjunto de configuración indicado y publica los eventos <strong>Delivery, Bounce, Complaint, Open y Click</strong> en un tópico SNS.</li>
                <li>Agrega su ARN a <code>MARKETING_SES_SNS_TOPIC_ARNS</code> y ejecuta <code>php artisan config:clear</code>. Actualmente hay {{ $topicsConfigured }} tópico(s) permitido(s).</li>
                <li>Suscribe este endpoint HTTPS al tópico SNS: <code>{{ $webhookUrl }}</code>. Mantén desactivada la entrega de mensajes sin procesar.</li>
                <li>Revisa la confirmación recibida con <code>php artisan marketing:confirm-sns</code> y complétala con <code>php artisan marketing:confirm-sns --write</code>. Estos comandos no muestran el token de confirmación.</li>
                <li>Cuando AWS apruebe el acceso de producción, activa <code>MARKETING_QUEUE_WORKER_ENABLED=true</code> y ejecuta <code>php artisan config:clear</code>. El cron del scheduler procesará la cola de campañas cada minuto, en segundo plano y sin solapar ejecuciones.</li>
            </ol>
            <p class="mb-0"><strong>Procesamiento programado de campañas:</strong> {{ config('marketing.worker_enabled') ? 'activado en la configuración.' : 'desactivado en la configuración.' }}</p>
        </div>
    </div>
@stop
