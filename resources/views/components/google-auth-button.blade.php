@props(['href', 'label' => 'Continuar con Google'])
@once
<style>
    .sefar-google-auth-button { display:flex;align-items:center;justify-content:center;gap:.7rem;width:100%;min-height:2.8rem;padding:.7rem 1rem;background:#fff;border:1px solid #d1d5db;border-radius:.55rem;color:#1f2937;font-weight:600;text-decoration:none; }
    .sefar-google-auth-button:hover { color:#111827;background:#f9fafb;border-color:#9ca3af;text-decoration:none; }
    .sefar-google-auth-button:focus-visible { outline:3px solid rgba(37,99,235,.3);outline-offset:3px; }
    .sefar-google-divider { display:flex;align-items:center;gap:.75rem;margin:1.25rem 0;color:#6b7280;font-size:.85rem; }
    .sefar-google-divider::before,.sefar-google-divider::after { content:'';height:1px;flex:1;background:#e5e7eb; }
</style>
@endonce
{{-- Google authorization must leave an embedded registration frame. Starting
     at the app in the top window also establishes the OAuth session there. --}}
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'sefar-google-auth-button', 'target' => '_top']) }}>
    <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.55-.21-2.27H12v4.3h6.44a5.5 5.5 0 0 1-2.39 3.61v2.79h3.88c2.27-2.09 3.56-5.17 3.56-8.43Z"/>
        <path fill="#34A853" d="M12 24c3.24 0 5.96-1.07 7.94-2.91l-3.88-2.79c-1.07.72-2.45 1.14-4.06 1.14-3.12 0-5.76-2.11-6.7-4.94H1.3v2.88A12 12 0 0 0 12 24Z"/>
        <path fill="#FBBC05" d="M5.3 14.5A7.22 7.22 0 0 1 4.93 12c0-.87.15-1.71.37-2.5V6.62H1.3A12 12 0 0 0 0 12c0 1.94.47 3.77 1.3 5.38l4-2.88Z"/>
        <path fill="#EA4335" d="M12 4.56c1.76 0 3.34.61 4.58 1.81l3.43-3.43C17.95 1.02 15.24 0 12 0A12 12 0 0 0 1.3 6.62l4 2.88c.94-2.83 3.58-4.94 6.7-4.94Z"/>
    </svg>
    <span>{{ $label }}</span>
</a>
