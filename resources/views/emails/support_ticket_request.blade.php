<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitud de soporte</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:720px; background:#ffffff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="background:#0f766e; padding:22px 26px; color:#ffffff;">
                            <div style="font-size:12px; line-height:18px; text-transform:uppercase; letter-spacing:.08em; opacity:.9;">
                                App Sefar
                            </div>
                            <h1 style="margin:6px 0 0; font-size:24px; line-height:32px; font-weight:700;">
                                {{ $isTest ? 'Solicitud de soporte - PRUEBA' : 'Solicitud de soporte' }}
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 26px 8px;">
                            <p style="margin:0 0 16px; font-size:15px; line-height:23px; color:#374151;">
                                Se registro una solicitud desde la plataforma. El correo del cliente queda destacado abajo para responderle o ubicar su contacto.
                            </p>

                            @if(! empty($ticket['id']))
                                <div style="margin:0 0 18px; padding:12px 14px; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:6px; color:#065f46; font-size:14px; line-height:21px;">
                                    Ticket HubSpot creado por API: <strong>{{ $ticket['id'] }}</strong>
                                </div>
                            @elseif($copyHubspotInbox)
                                <div style="margin:0 0 18px; padding:12px 14px; background:#fff7ed; border:1px solid #fed7aa; border-radius:6px; color:#9a3412; font-size:14px; line-height:21px;">
                                    No se pudo crear el ticket por API. Este correo fue enviado a {{ $hubspotInboxEmail }} como fallback.
                                </div>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 26px 18px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:6px; overflow:hidden;">
                                <tr>
                                    <td colspan="2" style="padding:12px 14px; background:#f9fafb; border-bottom:1px solid #e5e7eb; font-size:13px; font-weight:700; color:#111827;">
                                        Cliente
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width:34%; padding:10px 14px; border-bottom:1px solid #f3f4f6; font-size:13px; color:#6b7280;">Nombre</td>
                                    <td style="padding:10px 14px; border-bottom:1px solid #f3f4f6; font-size:14px; color:#111827;">{{ $clientName }}</td>
                                </tr>
                                <tr>
                                    <td style="width:34%; padding:10px 14px; border-bottom:1px solid #f3f4f6; font-size:13px; color:#6b7280;">Correo del cliente</td>
                                    <td style="padding:10px 14px; border-bottom:1px solid #f3f4f6; font-size:14px; color:#111827;">
                                        <a href="mailto:{{ $clientEmail }}" style="color:#0f766e; font-weight:700; text-decoration:none;">{{ $clientEmail }}</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width:34%; padding:10px 14px; border-bottom:1px solid #f3f4f6; font-size:13px; color:#6b7280;">Pasaporte</td>
                                    <td style="padding:10px 14px; border-bottom:1px solid #f3f4f6; font-size:14px; color:#111827;">{{ $client->passport ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="width:34%; padding:10px 14px; border-bottom:1px solid #f3f4f6; font-size:13px; color:#6b7280;">ID App</td>
                                    <td style="padding:10px 14px; border-bottom:1px solid #f3f4f6; font-size:14px; color:#111827;">{{ $client->id }}</td>
                                </tr>
                                <tr>
                                    <td style="width:34%; padding:10px 14px; font-size:13px; color:#6b7280;">Contacto HubSpot</td>
                                    <td style="padding:10px 14px; font-size:14px; color:#111827;">{{ $contactId ?: '-' }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 26px 18px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:6px; overflow:hidden;">
                                <tr>
                                    <td colspan="2" style="padding:12px 14px; background:#f9fafb; border-bottom:1px solid #e5e7eb; font-size:13px; font-weight:700; color:#111827;">
                                        Seguimiento
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width:34%; padding:10px 14px; border-bottom:1px solid #f3f4f6; font-size:13px; color:#6b7280;">Propietario del contacto</td>
                                    <td style="padding:10px 14px; border-bottom:1px solid #f3f4f6; font-size:14px; color:#111827;">
                                        {{ $ownerName ?: '-' }}
                                        @if(! empty($owner['email']))
                                            &lt;{{ $owner['email'] }}&gt;
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width:34%; padding:10px 14px; border-bottom:1px solid #f3f4f6; font-size:13px; color:#6b7280;">Solicitante</td>
                                    <td style="padding:10px 14px; border-bottom:1px solid #f3f4f6; font-size:14px; color:#111827;">
                                        {{ $requester ? ($requester->name ?: 'Usuario #' . $requester->id) : '-' }}
                                        @if($requester && $requester->email)
                                            &lt;{{ $requester->email }}&gt;
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width:34%; padding:10px 14px; border-bottom:1px solid #f3f4f6; font-size:13px; color:#6b7280;">Tema</td>
                                    <td style="padding:10px 14px; border-bottom:1px solid #f3f4f6; font-size:14px; color:#111827;">{{ $topic ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="width:34%; padding:10px 14px; font-size:13px; color:#6b7280;">Origen</td>
                                    <td style="padding:10px 14px; font-size:14px; color:#111827;">{{ $source ?: '-' }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 26px 24px;">
                            <div style="border:1px solid #e5e7eb; border-radius:6px; overflow:hidden;">
                                <div style="padding:12px 14px; background:#f9fafb; border-bottom:1px solid #e5e7eb; font-size:13px; font-weight:700; color:#111827;">
                                    Detalle de la solicitud
                                </div>
                                <div style="padding:14px; font-size:15px; line-height:23px; color:#1f2937; white-space:pre-line;">{{ $description }}</div>
                            </div>

                            @if($ticketError)
                                <p style="margin:14px 0 0; font-size:12px; line-height:18px; color:#6b7280;">
                                    Diagnostico HubSpot: {{ $ticketError }}
                                </p>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 26px; background:#f9fafb; border-top:1px solid #e5e7eb; font-size:12px; line-height:18px; color:#6b7280;">
                            Mensaje generado automaticamente por App Sefar. Para responder al cliente, usa el correo {{ $clientEmail }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
