@php($actionTheme = app(\App\Services\AppEmailTheme::class)->colors())
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:24px 0;"><tr><td align="center">
    <a class="sefar-email-action" href="{{ $url }}" style="display:inline-block;padding:14px 24px;min-height:20px;background:{{ $actionTheme['primary'] }};border-bottom:3px solid {{ $actionTheme['accent'] }};border-radius:{{ $actionTheme['radius'] }};font-family:{{ $actionTheme['font-family'] }};font-size:16px;line-height:1.4;font-weight:700;color:#ffffff;text-decoration:none;">{{ $label }}</a>
</td></tr></table>
