@php
  // Puedes ajustar el “preheader” (texto que se ve en la vista previa del inbox)
  $preheader = 'Tu registro está pendiente de pago. Completa el último paso para activar tu proceso de nacionalidad.';
@endphp
<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $subjectLine ?? 'Sefar Universal' }}</title>
  </head>

  <body style="margin:0; padding:0; background-color:#F3F4F6;">
    <!-- Preheader (oculto) -->
    <div style="display:none; font-size:1px; color:#F3F4F6; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
      {{ $preheader }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F3F4F6; padding:24px 0;">
      <tr>
        <td align="center" style="padding:0 12px;">

          <!-- Container -->
          <table role="presentation" width="600" cellpadding="0" cellspacing="0"
                 style="width:600px; max-width:600px; background:#FFFFFF; border-radius:16px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.08);">

            <!-- Header -->
            <tr>
                <td style="padding:28px 24px; background:linear-gradient(135deg, #0F3D5E 0%, #0B2F49 100%);" align="center">

                    <!-- Logo -->
                    <img
                    src="https://app.sefaruniversal.com/img/logo2.png"
                    alt="Sefar Universal"
                    width="220"
                    style="display:block; max-width:220px; width:100%; height:auto; margin:0 auto 12px auto;"
                    />

                </td>
            </tr>


            <!-- Body -->
            <tr>
              <td style="padding:24px 24px 8px 24px; font-family:Arial, sans-serif; color:#111827;">
                <div style="font-size:14px; color:#6B7280; margin-bottom:10px;">
                  Hola {{ $fullName }},
                </div>

                <div style="font-size:20px; font-weight:800; margin:0 0 10px 0; line-height:1.25;">
                  Activa tu proceso: pago de registro pendiente
                </div>

                <div style="font-size:14px; line-height:1.7; color:#374151;">
                  En <strong>Sefar Universal</strong> te acompañamos en tu proceso de <strong>obtención de nacionalidades</strong>,
                  con orientación profesional y seguimiento en cada etapa. Para avanzar, necesitamos que completes el
                  <strong>pago de tu registro</strong>.
                </div>
              </td>
            </tr>

            <!-- Benefit cards -->
            <tr>
              <td style="padding:12px 24px 0 24px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td style="background:#F9FAFB; border:1px solid #E5E7EB; border-radius:14px; padding:16px;">
                      <div style="font-family:Arial, sans-serif; font-size:14px; font-weight:700; color:#111827; margin-bottom:8px;">
                        ¿Qué obtienes al completar el pago?
                      </div>
                      <ul style="margin:0; padding-left:18px; font-family:Arial, sans-serif; font-size:14px; line-height:1.7; color:#374151;">
                        <li>Activación formal de tu registro y validación inicial</li>
                        <li>Orientación personalizada según tu caso</li>
                        <li>Acompañamiento y soporte durante el proceso</li>
                      </ul>

                      <div style="margin-top:12px; font-family:Arial, sans-serif; font-size:13px; color:#6B7280;">
                        Si ya realizaste el pago recientemente, por favor ignora este mensaje.
                      </div>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            <!-- Video CTA: Cómo registrarse -->
            <tr>
                <td style="padding:8px 24px 0 24px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center">
                        <a href="https://www.youtube.com/watch?v=tldBjXVy_P0"
                            target="_blank"
                            style="display:inline-block;
                                    background:#DC2626;
                                    color:#FFFFFF;
                                    text-decoration:none;
                                    font-family:Arial, sans-serif;
                                    font-size:14px;
                                    font-weight:700;
                                    padding:12px 18px;
                                    border-radius:12px;">
                            ▶ Ver video: cómo registrarte en nuestra plataforma
                        </a>
                        </td>
                    </tr>
                    <tr>
                        <td align="center"
                            style="padding-top:8px;
                                font-family:Arial, sans-serif;
                                font-size:12px;
                                color:#6B7280;">
                        Aprende paso a paso cómo completar tu registro correctamente
                        </td>
                    </tr>
                    </table>
                </td>
            </tr>

            <!-- CTA -->
            <tr>
              <td style="padding:18px 24px 6px 24px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td align="center" style="padding:6px 0 0 0;">
                      <!-- Botón “dummy”: reemplaza el href por tu link real de pago o panel -->
                      <a href="{{ $paymentUrl ?? 'https://www.sefaruniversal.com' }}"
                         style="display:inline-block; background:#0F3D5E; color:#FFFFFF; text-decoration:none; font-family:Arial, sans-serif;
                                font-size:14px; font-weight:700; padding:12px 18px; border-radius:12px;">
                        Completar pago y activar registro
                      </a>
                    </td>
                  </tr>
                  <tr>
                    <td align="center" style="padding-top:10px; font-family:Arial, sans-serif; font-size:12px; color:#6B7280;">
                      ¿Tienes dudas? Estamos para ayudarte.
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            <!-- Contact box -->
            <tr>
              <td style="padding:14px 24px 0 24px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="background:#FFFFFF; border:1px solid #E5E7EB; border-radius:14px;">
                  <tr>
                    <td style="padding:16px;">
                      <div style="font-family:Arial, sans-serif; font-size:14px; font-weight:800; color:#111827; margin-bottom:10px;">
                        Contacto directo
                      </div>

                      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-family:Arial, sans-serif;">
                        <tr>
                          <td style="font-size:13px; color:#374151; padding:2px 0;">Canadá: <a style="color:#0F3D5E; text-decoration:none;" href="tel:+16135182710">+1 613 518 2710</a></td>
                          <td style="font-size:13px; color:#374151; padding:2px 0;">México: <a style="color:#0F3D5E; text-decoration:none;" href="tel:+525585262017">+52 55 8526 2017</a></td>
                        </tr>
                        <tr>
                          <td style="font-size:13px; color:#374151; padding:2px 0;">Argentina: <a style="color:#0F3D5E; text-decoration:none;" href="tel:+541152738138">+54 11 5273 8138</a></td>
                          <td style="font-size:13px; color:#374151; padding:2px 0;">Brasil: <a style="color:#0F3D5E; text-decoration:none;" href="tel:+556131426728">+55 61 3142 6728</a></td>
                        </tr>
                        <tr>
                          <td style="font-size:13px; color:#374151; padding:2px 0;">Colombia: <a style="color:#0F3D5E; text-decoration:none;" href="tel:+576053195843">+57 605 319 5843</a></td>
                          <td style="font-size:13px; color:#374151; padding:2px 0;">Venezuela: <a style="color:#0F3D5E; text-decoration:none;" href="tel:+582127201170">+58 212 720 1170</a></td>
                        </tr>
                        <tr>
                          <td style="font-size:13px; color:#374151; padding:2px 0;">United States: <a style="color:#0F3D5E; text-decoration:none;" href="tel:+16032621727">+1 603 262 1727</a></td>
                          <td style="font-size:13px; color:#374151; padding:2px 0;">España: <a style="color:#0F3D5E; text-decoration:none;" href="tel:+34911980993">+34 911 980 993</a></td>
                        </tr>
                      </table>

                      <div style="margin-top:12px; font-family:Arial, sans-serif; font-size:13px; color:#374151;">
                        Correo: <a href="mailto:info@sefaruniversal.com" style="color:#0F3D5E; text-decoration:none; font-weight:700;">info@sefaruniversal.com</a>
                      </div>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            <!-- Footer -->
            <tr>
              <td style="padding:18px 24px 22px 24px; font-family:Arial, sans-serif;">
                <div style="font-size:12px; color:#6B7280; line-height:1.6;">
                  Este es un recordatorio automático asociado a tu registro.
                  <span style="white-space:nowrap;">Referencia interna: seguimiento #{{ $sequence }}</span>
                </div>
                <div style="margin-top:10px; font-size:12px; color:#9CA3AF;">
                  © {{ date('Y') }} Sefar Universal ·
                  <a href="https://www.sefaruniversal.com" style="color:#0F3D5E; text-decoration:none;">sefaruniversal.com</a>
                </div>
              </td>
            </tr>

          </table>
          <!-- /Container -->

          <!-- Mobile width -->
          <div style="font-size:0; line-height:0;">&nbsp;</div>

        </td>
      </tr>
    </table>
  </body>
</html>
