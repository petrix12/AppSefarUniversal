@component('mail.layouts.base')
<h1 style="box-sizing: border-box;  position: relative;  font-size: 18px; font-weight: bold; margin-top: 0; text-align: left;">
                                            ¡Bienvenido a Sefar Universal!
                                        </h1>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            Estimado(a) <strong>{{ $user->name }}</strong> hemos recibido el inicio de su proceso.
                                        </p>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            En estos momentos su información será remitida al departamento de genealogía e historia para el respectivo análisis de su árbol genealógico.
                                        </p>
                                        @if ($user->servicio == "Española LMD")
                                            <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                                Para acelerar la gestión de alguno de los supuestos que establece la
                                                Ley de Memoria Democrática y preparar un expediente
                                                <strong>completamente blindado</strong> que nos permita tener así la certeza de
                                                obtener su nacionalidad española, necesitamos que entre al siguiente enlace y complete
                                                la mayor cantidad de información posible.
                                            </p>
                                            <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                                <a href="https://survey.hsforms.com/19Zypb99_QFyCrbM8-V0fzQbxtdk" target="_blank">FORMULARIO LEY DE MEMORIA DEMOCRÁTICA</a>
                                            </p>
                                        @endif
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            Si no ha podido cargar los datos de su árbol, puede hacerlo entrando a nuestra plataforma en este momento o cuando lo desee, mediante el siguiente enlace:
                                            <a href="https://app.sefaruniversal.com/login" target="_blank">https://app.sefaruniversal.com/login</a>
                                            con su correo {{ $user->email }}, y cuando finalice presione en <strong>finalizar carga</strong>.
                                        </p>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            Mientras más datos proporciones más fácil y rápido será la realización de tu estudio genealógico.
                                        </p>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            Una vez tengamos respuesta sobre el análisis de sus datos le comunicaremos.
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
