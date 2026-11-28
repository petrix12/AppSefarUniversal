@php($emailColors = app(\App\Services\AppEmailTheme::class)->colors())
<h1 contenteditable="true" style="margin:0 0 20px;color:{{ $emailColors['primary'] }};font-family:{{ $emailColors['font-family'] }};font-size:28px;line-height:1.25;">{{ $draft['headline'] }}</h1>
@foreach($draft['paragraphs'] as $paragraph)
    <p contenteditable="true" style="margin:0 0 18px;color:{{ $emailColors['text'] }};font-family:{{ $emailColors['font-family'] }};font-size:16px;line-height:1.65;">{{ $paragraph }}</p>
@endforeach
@if(!empty($draft['highlight']))
    <div style="margin:24px 0;padding:22px;background:{{ $emailColors['primary-soft'] }};border-left:4px solid {{ $emailColors['accent'] }};border-radius:{{ $emailColors['radius'] }};"><p contenteditable="true" style="margin:0;color:{{ $emailColors['text'] }};font-family:{{ $emailColors['font-family'] }};font-size:16px;line-height:1.6;">{{ $draft['highlight'] }}</p></div>
@endif
@if(!empty($draft['points']))
    <ul style="padding-left:22px;color:{{ $emailColors['text'] }};font-family:{{ $emailColors['font-family'] }};font-size:16px;line-height:1.65;">@foreach($draft['points'] as $point)<li contenteditable="true" style="margin-bottom:10px;">{{ $point }}</li>@endforeach</ul>
@endif
<p style="margin:26px 0;text-align:center;"><a href="{{ $actionUrl }}" class="sefar-email-action" contenteditable="true" style="display:inline-block;padding:14px 24px;max-width:100%;background:{{ $emailColors['primary'] }};border-bottom:3px solid {{ $emailColors['accent'] }};border-radius:{{ $emailColors['radius'] }};color:#ffffff;font-family:{{ $emailColors['font-family'] }};font-size:16px;line-height:1.4;font-weight:700;text-decoration:none;">{{ $draft['button_label'] }}</a></p>
<p contenteditable="true" style="margin:0;color:{{ $emailColors['muted'] }};font-family:{{ $emailColors['font-family'] }};font-size:15px;line-height:1.65;">{{ $draft['closing'] }}</p>
