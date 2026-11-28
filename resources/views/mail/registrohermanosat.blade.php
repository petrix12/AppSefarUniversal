@component('mail.layouts.base')
<h1 style="box-sizing: border-box;  position: relative;  font-size: 18px; font-weight: bold; margin-top: 0; text-align: left;">
                                            ¡Bienvenido a Sefar Universal!
                                        </h1>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            Estimado(a) <b>{{ auth()->user()->name }}</b>.
                                        </p>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            Has registrado a tu hermano <b>{{ $user->name }}</b> satisfactoriamente.
                                        </p>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            También hemos notificado a tu hermano al respecto, y hemos enviado sus credenciales para acceder a nuestra App.
                                        </p>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            Puede continuar con su proceso ingresando con su usuario y contraseña mediante el siguiente enlace: <a href="https://app.sefaruniversal.com/login" target="_blank">https://app.sefaruniversal.com/login</a>
                                        </p>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            En caso de requerir alguna información adicional relacionada con este asunto
                                            o cualquier otro relacionado con el proceso, por favor no dude en comunicarse con nosotros por el
                                            correo electrónico <strong><a href="mailto:info@sefaruniversal.com">info@sefaruniversal.com</a></strong> y/o por los siguientes números de teléfono de atención al representado:
                                            <br>
                                            <ul>
                                                <li>USA: <a href="tel:+16032621727">+1 603 2621727</a></li>
                                                <li>España: <a href="tel:+34911980993">+34 911 980993</a></li>
                                                <li>Venezuela: <a href="tel:+582127201170">+58 212 7201170</a></li>
                                                <li>Colombia: <a href="tel:+570353195843">+57 035 3195843</a></li>
                                            </ul>
                                        </p>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            Atención de lunes a sábado las 24hrs.
                                        </p>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            Huso horario Colombia (GMT-5).
                                        </p>
                                        <br>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            Atentamente,
                                            <br>
                                            Departamento de Atención al Representado.
                                            <br>
                                            <img src="https://app.sefaruniversal.com/img/logonormal.png" alt="Logo Sefar" height="60">
                                        </p>
@endcomponent
