@props(['user'])

@if($user?->isSuperadmin())
    <span {{ $attributes->class(['sefar-superadmin-indicator']) }} role="img" aria-label="Superadmin" title="Superadmin">
        <i class="fas fa-crown" aria-hidden="true"></i>
    </span>
@endif
