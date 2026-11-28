@component('mail.layouts.base')
<h1 style="box-sizing: border-box;  position: relative;  font-size: 18px; font-weight: bold; margin-top: 0; text-align: left;">
                                            ¡Cupón rechazado!
                                        </h1>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            Lamento informarte que se ha rechazado tu solicitud por un cupón.
                                        </p>

                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            <strong>Representado:</strong> {{ $nombreCliente }}
                                        </p>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            <strong>Solicitante:</strong> {{ $nombreSolicitante }}
                                        </p>
                                        <br>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            Atentamente,
                                            <br>
                                            Departamento de Sistemas.
                                            <br>
                                            <img src="https://app.sefaruniversal.com/img/logonormal.png" alt="Logo Sefar" height="60">
                                        </p>
@endcomponent
