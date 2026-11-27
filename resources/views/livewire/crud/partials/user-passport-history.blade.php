@if($user->relationLoaded('passportHistories') && $user->passportHistories->isNotEmpty())
    <details class="mt-2 text-sm" data-user-passport-history>
        <summary class="text-primary" style="cursor:pointer">Pasaportes anteriores ({{ $user->passportHistories->count() }})</summary>
        <ul class="mt-1 pl-3">
            @foreach($user->passportHistories as $history)
                <li>{{ $history->passport }} <span class="text-muted">· {{ $history->created_at?->format('d/m/Y') }}</span></li>
            @endforeach
        </ul>
    </details>
@endif
