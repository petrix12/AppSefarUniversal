@component('mail.layouts.base')
<h1 style="box-sizing: border-box;  position: relative;  font-size: 18px; font-weight: bold; margin-top: 0; text-align: left;">
                                            Reporte del año {{$anio}}
                                        </h1>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            Estimado(a).
                                        </p>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            Se ha generado el reporte del año {{$anio}}
                                        </p>
                                        <p style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            En este correo encontrarás adjunto un pdf con el reporte en cuestión.
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
