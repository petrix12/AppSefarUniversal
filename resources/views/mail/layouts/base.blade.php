@php
    $theme = app(\App\Services\AppEmailTheme::class)->colors();
    $baseTitle = $title ?? 'App Sefar Universal';
    $baseUrl = rtrim((string) config('app.url', 'https://app.sefaruniversal.com'), '/');
    $brandLogo = $logoUrl ?? $baseUrl.'/img/logo2.png';
    $supportEmail = config('app.client_access_support_email', 'info@sefaruniversal.com');
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light dark">
    <title>{{ $baseTitle }}</title>
    <style>
        {!! app(\App\Services\AppEmailTheme::class)->css() !!}
        @media only screen and (max-width:600px){.sefar-email-outer{padding:16px 8px!important}.sefar-email-card{width:100%!important}.sefar-email-content{padding:24px 20px!important}.sefar-email-content-rows{padding:0!important}.sefar-email-header{padding:24px 20px!important}.sefar-email-content h1{font-size:23px!important}.sefar-email-stack{display:block!important;width:100%!important}.sefar-email-action{display:block!important;text-align:center!important}.sefar-email-content table{width:100%!important}}
        @media (prefers-color-scheme:dark){.sefar-email-page{background:#000d1a!important}.sefar-email-card,.sefar-email-content{background:#071522!important;color:#eef6f8!important}.sefar-email-content h1,.sefar-email-content h2,.sefar-email-content h3,.sefar-email-content a{color:#dbba72!important}.sefar-email-footer{color:#a9bdc8!important}.sefar-email-action{background:#dbba72!important;color:#001b27!important}.sefar-email-contact{background:#0c2230!important;color:#eef6f8!important}}
        @media (prefers-color-scheme:dark){.sefar-email-content p,.sefar-email-content div,.sefar-email-content li,.sefar-email-content td,.sefar-email-content th,.sefar-email-content span,.sefar-email-content strong{color:#eef6f8!important}.sefar-email-content [style*="background"]{background-color:#0c2230!important}.sefar-email-content a.sefar-email-action,.sefar-email-content a.button,.sefar-email-content a[style*="background"]{background:#dbba72!important;color:#001b27!important}.sefar-email-contact a{color:#eef6f8!important}}
    </style>
</head>
<body class="sefar-email-page" data-sefar-email-template="base-v1" style="margin:0;padding:0;background:{{ $theme['page'] }};font-family:{{ $theme['font-family'] }};color:{{ $theme['text'] }};">
    @if(!empty($preheader))<div data-sefar-preheader="true" style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">{{ $preheader }}</div>@endif
    <table data-sefar-email-template="base-v1" role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;background:{{ $theme['page'] }};">
        <tr><td class="sefar-email-outer" align="center" style="padding:32px 16px;">
            <!--[if mso]><table role="presentation" width="640" align="center"><tr><td><![endif]-->
            <table class="sefar-email-card" role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;max-width:640px;margin:0 auto;background:{{ $theme['surface'] }};border:1px solid {{ $theme['primary-soft'] }};border-top:4px solid {{ $theme['accent'] }};border-radius:{{ $theme['radius'] }};overflow:hidden;">
                <tr><td class="sefar-email-header" style="padding:26px 28px;background:{{ $theme['primary'] }};">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>
                        <td align="left"><a href="{{ $baseUrl }}"><img src="{{ $brandLogo }}" alt="Sefar Universal" width="154" style="display:block;width:154px;max-width:100%;height:auto;border:0;"></a></td>
                        <td align="right" style="font-family:{{ $theme['font-family'] }};font-size:11px;letter-spacing:1px;color:{{ $theme['accent-light'] }};font-weight:700;">@isset($badge){{ $badge }}@else APP SEFAR<br>UNIVERSAL @endisset</td>
                    </tr></table>
                </td></tr>
                <tr><td data-sefar-email-slot="true" class="sefar-email-content {{ !empty($contentRows) ? 'sefar-email-content-rows' : '' }}" style="padding:{{ !empty($contentRows) ? '0' : '32px 28px 24px' }};font-family:{{ $theme['font-family'] }};font-size:16px;line-height:1.65;color:{{ $theme['text'] }};background:{{ $theme['surface'] }};overflow-wrap:break-word;">
                    {{ $slot ?? '' }}
                    {{ $subcopy ?? '' }}
                </td></tr>
                <tr><td class="sefar-email-contact" style="padding:20px 28px;background:{{ $theme['primary-soft'] }};font-family:{{ $theme['font-family'] }};font-size:13px;line-height:1.7;color:{{ $theme['text'] }};border-top:1px solid {{ $theme['primary-soft'] }};">
                    <strong>Atención al Representado</strong><br>
                    <a href="mailto:{{ $supportEmail }}" style="color:{{ $theme['primary'] }};text-decoration:none;">{{ $supportEmail }}</a><br>
                    <a href="tel:+34911980993" style="color:{{ $theme['primary'] }};text-decoration:none;">+34 911 980 993</a>
                </td></tr>
            </table>
            <!--[if mso]></td></tr></table><![endif]-->
            <p class="sefar-email-footer" style="margin:20px auto 0;max-width:600px;font-family:{{ $theme['font-family'] }};font-size:11px;line-height:1.7;color:{{ $theme['muted'] }};text-align:center;">© {{ date('Y') }} Sefar Universal · Derecho Genealogista<br><a href="https://sefaruniversal.com/" style="color:{{ $theme['muted'] }};text-decoration:none;">sefaruniversal.com</a></p>
        </td></tr>
    </table>
</body>
</html>
