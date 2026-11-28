@component('mail.layouts.base')
<h1 style="box-sizing: border-box;  position: relative;  font-size: 18px; font-weight: bold; margin-top: 0; text-align: left;">
                                            ¡Tienes una solicitud de cupón pendiente por aprobar!
                                        </h1>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                        Se ha recibido una nueva solicitud para aprobar un cupón.
                                        </p>

                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            <strong>Nombre del Solicitante:</strong> {{ $solicitud->nombre_solicitante }} {{ $solicitud->apellidos_solicitante }}
                                        </p>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            <strong>Correo del Solicitante:</strong> {{ $solicitud->correo_solicitante }}
                                        </p>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            <strong>Motivo de la Solicitud:</strong> {{ $solicitud->motivo_solicitud }}
                                        </p>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            <strong>Tipo de Cupón:</strong> {{ $solicitud->tipo_cupon }}
                                        </p>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            <strong>Porcentaje de Descuento:</strong> {{ $solicitud->porcentaje_descuento }}%
                                        </p>

                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            <a href="{{ $aprobarUrl }}" target="_blank" style="padding: 10px; color: white; background-color: green; text-decoration: none;">Aprobar</a>
                                            <a href="{{ $rechazarUrl }}" target="_blank" style="padding: 10px; color: white; background-color: red; text-decoration: none;">Rechazar</a>
                                        </p>

                                        @if (!empty($solicitud->comprobante_pago))
                                            <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                                <strong>Comprobante de Pago:</strong>
                                                @foreach (explode(',', $solicitud->comprobante_pago) as $fileUrl)
                                                    <a href="{{ $fileUrl }}" style="color: blue; text-decoration: underline;">Ver Comprobante</a>
                                                @endforeach
                                            </p>
                                        @endif
                                        <br>
                                        <br>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            Atentamente,
                                            <br>
                                            Departamento de Sistemas.
                                            <br>
                                            <img src="https://app.sefaruniversal.com/img/logonormal.png" alt="Logo Sefar" height="60">
                                        </p>
@endcomponent
