@php
    $total = $tasks->count();
    $dateLabel = $date->format('d/m/Y');
    $preheader = "Tienes {$total} tarea(s) comerciales asignadas para gestionar hoy.";
@endphp
<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>Tareas asignadas</title>
  </head>
  <body style="margin:0; padding:0; background:#EEF2F7;">
    <div style="display:none; font-size:1px; color:#EEF2F7; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
      {{ $preheader }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EEF2F7; padding:24px 0;">
      <tr>
        <td align="center" style="padding:0 12px;">
          <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px; max-width:640px; background:#FFFFFF; border-radius:18px; overflow:hidden; box-shadow:0 18px 45px rgba(15,61,94,0.16);">
            <tr>
              <td style="background:#0B3D4F; padding:28px 26px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td align="left" style="vertical-align:middle;">
                      <img src="https://app.sefaruniversal.com/img/logo2.png" alt="Sefar Universal" width="170" style="display:block; width:170px; max-width:170px; height:auto;">
                    </td>
                    <td align="right" style="font-family:Arial, sans-serif; color:#C9F4DF; font-size:13px; font-weight:700; vertical-align:middle;">
                      {{ $dateLabel }}
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            <tr>
              <td style="padding:28px 28px 8px 28px; font-family:Arial, sans-serif; color:#0F172A;">
                <div style="font-size:14px; color:#64748B; margin-bottom:10px;">
                  Hola {{ $advisor->name }},
                </div>
                <div style="font-size:25px; line-height:1.25; font-weight:800; letter-spacing:-0.2px; margin-bottom:10px;">
                  Tienes {{ $total }} tarea(s) comerciales para gestionar hoy
                </div>
                <div style="font-size:15px; line-height:1.7; color:#334155;">
                  Estas tareas fueron asignadas para dar respuesta rapida a los clientes. Recuerda registrar la gestion realizada en la app: llamada, WhatsApp o email/correo.
                </div>
              </td>
            </tr>

            <tr>
              <td style="padding:14px 28px 0 28px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ECFDF5; border:1px solid #BBF7D0; border-radius:14px;">
                  <tr>
                    <td style="padding:15px 16px; font-family:Arial, sans-serif;">
                      <div style="font-size:13px; color:#047857; font-weight:800; text-transform:uppercase; letter-spacing:0.4px;">
                        Prioridad del dia
                      </div>
                      <div style="font-size:14px; line-height:1.6; color:#065F46; margin-top:4px;">
                        Si una tarea vence y queda abierta, el sistema la revisa al dia siguiente. La idea es mover rapido los casos y no dejar clientes sin seguimiento.
                      </div>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            <tr>
              <td style="padding:18px 28px 2px 28px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                  @foreach($tasks as $task)
                    @php
                        $contact = $task->contact;
                        $taskUrl = route('tasks.show', $task);
                    @endphp
                    <tr>
                      <td style="padding:0 0 10px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E2E8F0; border-radius:14px; background:#FFFFFF;">
                          <tr>
                            <td style="padding:16px 16px 12px 16px; font-family:Arial, sans-serif;">
                              <div style="font-size:15px; font-weight:800; color:#0F172A; line-height:1.35;">
                                {{ $contact?->name ?? $task->title }}
                              </div>
                              @if($contact?->email)
                                <div style="font-size:13px; color:#64748B; margin-top:5px;">
                                  {{ $contact->email }}
                                </div>
                              @endif
                              <div style="font-size:13px; color:#475569; margin-top:8px; line-height:1.5;">
                                {{ $task->description ?: 'Sin descripcion adicional.' }}
                              </div>
                              <div style="margin-top:12px;">
                                <a href="{{ $taskUrl }}" style="display:inline-block; background:#2563EB; color:#FFFFFF; text-decoration:none; font-size:13px; font-weight:800; padding:9px 13px; border-radius:10px;">
                                  Abrir tarea
                                </a>
                              </div>
                            </td>
                          </tr>
                        </table>
                      </td>
                    </tr>
                  @endforeach
                </table>
              </td>
            </tr>

            <tr>
              <td align="center" style="padding:12px 28px 26px 28px;">
                <a href="{{ $tasksUrl }}" style="display:inline-block; background:#0B3D4F; color:#FFFFFF; text-decoration:none; font-family:Arial, sans-serif; font-size:15px; font-weight:800; padding:13px 20px; border-radius:12px;">
                  Ver todas mis tareas
                </a>
                <div style="font-family:Arial, sans-serif; font-size:12px; line-height:1.6; color:#94A3B8; margin-top:14px;">
                  Este correo fue generado automaticamente por App Sefar - COS.
                </div>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
