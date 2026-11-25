@php
    $cosDisplayName = trim(($user->nombres ?? '').' '.($user->apellidos ?? '')) ?: $user->name;
    $cosInitials = collect(preg_split('/\s+/u', trim($cosDisplayName)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
@endphp
<header class="card cos-client-header">
    <div class="card-body cos-client-header-body">
        <div class="cos-client-identity">
            <span class="cos-client-monogram" aria-hidden="true">{{ $cosInitials }}</span>
            <div class="cos-client-heading">
                <p class="cos-client-eyebrow">{{ $cosViewRoleId == 5 ? 'Tu expediente' : 'Ficha del representado' }}</p>
                <h1>{{ $cosDisplayName }}</h1>
                <div class="cos-client-contact">
                    @if($user->email)<span><i class="far fa-envelope" aria-hidden="true"></i>{{ $user->email }}</span>@endif
                    @if($user->phone)<span><i class="fas fa-phone-alt" aria-hidden="true"></i>{{ $user->phone }}</span>@endif
                </div>
                @if($user->servicio)<p class="cos-client-service">{{ $user->servicio }}</p>@endif
            </div>
        </div>
        @if($cosViewRoleId != 5)
        <div class="cos-client-actions">
            <a href="{{ route('crud.users.index') }}" class="btn btn-outline-primary"><i class="fas fa-arrow-left mr-1" aria-hidden="true"></i>Volver a representados</a>
            <details class="cos-external-profiles"><summary class="btn btn-outline-secondary">Contactos vinculados</summary>
                <div class="cos-external-profile-links">
                @php
                    $teamleaderContactId = filled($user->tl_id) ? $user->tl_id : data_get($teamleaderMigration ?? [], 'contact.id');
                    $externalProfiles = [
                        'HubSpot' => filled($user->hs_id) ? 'https://app.hubspot.com/contacts/20053496/record/0-1/'.rawurlencode(trim((string)$user->hs_id)) : null,
                        'Teamleader' => filled($teamleaderContactId) ? 'https://focus.teamleader.eu/contacts/'.rawurlencode(trim((string)$teamleaderContactId)) : null,
                    ];
                @endphp
                @foreach($externalProfiles as $platform=>$profileUrl)
                    @if($profileUrl)<a href="{{ $profileUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary"><i class="fas fa-external-link-alt mr-1" aria-hidden="true"></i>Ver en {{ $platform }}</a>
                    @else<span class="text-muted small">{{ $platform }} · sin contacto vinculado</span>@endif
                @endforeach
                </div>
            </details>
        </div>
        @endif
    </div>
</header>
