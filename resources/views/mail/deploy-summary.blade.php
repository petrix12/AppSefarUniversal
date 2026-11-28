@php
    $appUrl = rtrim((string) config('app.url', 'https://app.sefaruniversal.com'), '/');
    $logoUrl = $appUrl . '/img/logo2.png';
    $dateLabel = $generatedAt->format('d/m/Y H:i');
    $preheader = "Novedades incluidas en la version {$version} de {$appName}.";
    $intro = $notes['intro'] ?? [];
    $sections = $notes['sections'] ?? [];
    $closing = $notes['closing'] ?? [];
@endphp
@component('mail.layouts.base', ['contentRows' => true, 'badge' => 'Versión '.$version])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
<tr>
              <td style="padding:30px 30px 10px 30px;  color:#093143;">
                <div style="font-size:13px; line-height:1.4; color:#607783; font-weight:800; text-transform:uppercase; letter-spacing:0.4px; margin-bottom:10px;">
                  Release notes
                </div>
                <div style="font-size:28px; line-height:1.2; font-weight:900; margin-bottom:12px;">
                  Novedades del despliegue
                </div>
                <div style="font-size:14px; line-height:1.7; color:#445D68;">
                  @foreach($intro as $line)
                    <div style="margin-bottom:6px;">{{ $line }}</div>
                  @endforeach
                </div>
              </td>
            </tr>

            <tr>
              <td style="padding:8px 30px 18px 30px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F8FA; border:1px solid #DCE7EC; border-radius:8px;">
                  <tr>
                    <td width="50%" style="padding:14px 16px;  border-right:1px solid #DCE7EC;">
                      <div style="font-size:11px; color:#607783; font-weight:800; text-transform:uppercase; letter-spacing:0.4px;">Fecha</div>
                      <div style="font-size:14px; color:#093143; font-weight:800; margin-top:4px;">{{ $dateLabel }}</div>
                    </td>
                    <td width="50%" style="padding:14px 16px; ">
                      <div style="font-size:11px; color:#607783; font-weight:800; text-transform:uppercase; letter-spacing:0.4px;">Aplicacion</div>
                      <div style="font-size:14px; color:#093143; font-weight:800; margin-top:4px;">{{ $appName }}</div>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            <tr>
              <td style="padding:0 30px 8px 30px;">
                @foreach($sections as $section)
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 14px 0; border:1px solid #DCE7EC; border-radius:8px; overflow:hidden;">
                    <tr>
                      <td style="background:#E8F2F5; padding:14px 16px; ">
                        <div style="font-size:15px; line-height:1.35; color:#093143; font-weight:900;">
                          {{ $section['title'] }}
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:14px 16px 6px 16px; ">
                        @foreach($section['items'] as $item)
                          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 12px 0;">
                            <tr>
                              <td width="11" style="padding-top:5px; vertical-align:top;">
                                <span style="display:block; width:7px; height:7px; background:#A17F48; border-radius:7px;"></span>
                              </td>
                              <td style="vertical-align:top;">
                                <div style="font-size:14px; line-height:1.45; color:#093143; font-weight:900;">
                                  {{ $item['title'] }}
                                </div>
                                @if(! empty($item['body']))
                                  <div style="font-size:14px; line-height:1.65; color:#445D68; margin-top:4px;">
                                    {{ $item['body'] }}
                                  </div>
                                @endif
                              </td>
                            </tr>
                          </table>
                        @endforeach
                      </td>
                    </tr>
                  </table>
                @endforeach
              </td>
            </tr>

            <tr>
              <td align="center" style="padding:8px 30px 30px 30px;">
                <a href="{{ $appUrl }}" style="display:inline-block; background:#093143; color:#FFFFFF; text-decoration:none;  font-size:14px; font-weight:900; padding:12px 18px; border-radius:8px;">
                  Abrir App Sefar
                </a>

                @if(! empty($closing))
                  <div style=" font-size:13px; line-height:1.7; color:#607783; margin-top:18px;">
                    @foreach($closing as $line)
                      {{ $line }}@if(! $loop->last)<br>@endif
                    @endforeach
                  </div>
                @endif

                <div style=" font-size:11px; line-height:1.6; color:#8CA1AA; margin-top:18px;">
                  Correo generado automaticamente por el flujo de despliegue de App Sefar Universal.
                </div>
              </td>
            </tr>
</table>
@endcomponent
