@component('mail.layouts.base')
<h1
                                            style="box-sizing: border-box;  position: relative;  font-size: 18px; font-weight: bold; margin-top: 0; text-align: left;">
                                            Estimado amigo {{ $user->name }}</h1>
                                        @if ($no_cargadas == '')
                                            <p
                                                style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                                Gracias por actualizar su información genealógica.
                                                Mientras más datos proporciones más fácil y rápido
                                                será la realización de tu estudio genealógico.
                                            </p>
                                        @else
                                            <p
                                                style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                                Un cordial saludo desde Sefar Universal, abogados y genealogistas.
                                                Queremos notificarle que han quedado algunos campos que no han sido
                                                llenados.
                                                Recuerde que para nosotros es muy importante poder tener la mayor
                                                cantidad de datos suyos posibles, para poder avanzar con rapidez.
                                                Los datos faltantes son:
                                            </p>
                                            <p
                                                style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                                {{-- Cliente: {{ $agclientes }} --}}
                                                {{-- <strong>Personas cargadas:</strong>{!! $cargadas !!} <br> --}}
                                                <strong>{!! $no_cargadas !!}</strong>
                                                {{-- Añadir otro parrafo --}}
                                            </p>
                                            <div>
                                                {{-- <img src="{{ asset('img/estructura.png') }}" alt="Estructura de árbol genealógico" width="500"> --}}
                                                <img src="https://i.imgur.com/EDBJbiG.png" alt="Estructura de árbol genealógico" width="500">
                                            </div>
                                        @endif
                                        <p
                                            style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            Si no posee la documentación, le podemos ofrecer el siguiente servicio
                                            <br>
                                            <a href="https://www.sefaruniversal.com/asistencia-documental"
                                                target="_blank">
                                                Asistencia documental de Sefar Universal
                                            </a>
                                        </p>
                                        <p
                                            style="box-sizing: border-box;  position: relative; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;">
                                            Saludos,
                                            <br>
                                            App Sefar Universal
                                        </p>
@endcomponent
