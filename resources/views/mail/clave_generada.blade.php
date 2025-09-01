<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <style>
        @media only screen and (max-width: 600px) {
            .inner-body { width: 100% !important; }
            .footer { width: 100% !important; }
        }
        @media only screen and (max-width: 500px) {
            .button { width: 100% !important; }
        }
    </style>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #ffffff; color: #718096; margin: 0; padding: 0; width: 100% !important;">
    <table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color: #edf2f7; width: 100%;">
        <tr>
            <td align="center">
                <table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                        <td class="header" style="padding: 25px 0; text-align: center;">
                            <a href="https://app.sefaruniversal.com" style="font-size: 19px; font-weight: bold; text-decoration: none; color: #3d4852;">
                                <img src="https://app.sefaruniversal.com/img/logo.png" alt="Logo Sefar" style="height: 75px; width: 75px;">
                                <hr>
                                App Sefar Universal
                            </a>
                        </td>
                    </tr>

                    <!-- Email Body -->
                    <tr>
                        <td class="body" width="100%" style="background-color: #edf2f7; border-top: 1px solid #edf2f7; border-bottom: 1px solid #edf2f7;">
                            <table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation" style="background-color: #ffffff; border: 1px solid #e8e5ef; border-radius: 2px; box-shadow: 0 2px 0 rgba(0,0,150,0.025), 2px 4px 0 rgba(0,0,150,0.015); width: 570px;">
                                <tr>
                                    <td class="content-cell" style="padding: 32px;">
                                        <h1 style="color: #3d4852; font-size: 18px; font-weight: bold; margin-top: 0;">
                                            ¡Bienvenido a Sefar Universal!
                                        </h1>
                                        <p style="font-size: 16px; line-height: 1.5em;">
                                            Estimado(a) <b>{{ $user->name }}</b>.
                                        </p>
                                        <p style="font-size: 16px; line-height: 1.5em;">
                                            Su usuario ha sido creado exitosamente en nuestra plataforma.
                                        </p>
                                        <p style="font-size: 16px; line-height: 1.5em;">
                                            Sus credenciales son las siguientes:
                                        </p>
                                        <ul style="font-size: 16px; line-height: 1.5em;">
                                            <li><b>Email:</b> {{ $user->email }}</li>
                                            <li><b>Contraseña temporal:</b> {{ $password }}</li>
                                        </ul>
                                        <p style="font-size: 16px; line-height: 1.5em;">
                                            Puede iniciar sesión aquí:
                                            <a href="https://app.sefaruniversal.com/login" target="_blank">https://app.sefaruniversal.com/login</a>
                                        </p>
                                        <p style="font-size: 16px; line-height: 1.5em;">
                                            En caso de requerir información adicional, contáctenos en
                                            <a href="mailto:info@sefaruniversal.com">info@sefaruniversal.com</a>
                                            o a nuestros números de atención:
                                            <br>
                                            <ul>
                                                <li>USA: <a href="tel:+16032621727">+1 603 2621727</a></li>
                                                <li>España: <a href="tel:+34911980993">+34 911 980993</a></li>
                                                <li>Venezuela: <a href="tel:+582127201170">+58 212 7201170</a></li>
                                                <li>Colombia: <a href="tel:+570353195843">+57 035 3195843</a></li>
                                            </ul>
                                        </p>
                                        <p style="font-size: 16px; line-height: 1.5em;">
                                            Atención 24/7 – Huso horario Colombia (GMT-5).
                                        </p>
                                        <br>
                                        <p style="font-size: 16px; line-height: 1.5em;">
                                            Atentamente,<br>
                                            Departamento de Atención al Cliente.<br>
                                            <img src="https://app.sefaruniversal.com/img/logonormal.png" alt="Logo Sefar" height="60">
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td class="content-cell" align="center" style="padding: 32px;">
                                        <p style="color: #b0adc5; font-size: 12px; text-align: center;">
                                            © {{ date('Y') }} App Sefar Universal. Todos los derechos reservados.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
