@component('mail.layouts.base', [
    'title' => 'Tu registro está pendiente de pago',
    'preheader' => 'Ya estás registrado en App Sefar Universal. Completa el pago para iniciar tu análisis genealógico.',
    'logoUrl' => $logoUrl,
])
<p>Hola {{ $fullName }},</p>
<h1>Tu registro está pendiente de pago</h1>
<p>Ya estás registrado en <strong>App Sefar Universal</strong> y tienes pendiente el <strong>pago de tu registro</strong>.</p>
<p>Al completarlo, podremos iniciar el análisis genealógico de tu caso para optar por una <strong>nacionalidad por origen sefardí</strong>.</p>
@include('mail.partials.action', ['url' => $paymentUrl, 'label' => 'Ingresar y pagar mi registro'])
<p>Si tienes alguna duda, contacta con <strong>Atención al Representado</strong>. Estamos para ayudarte.</p>
@endcomponent
