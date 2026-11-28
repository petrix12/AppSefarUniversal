@if($user->email_suppression_reason)
    <div class="mt-1"><span class="badge badge-danger">Correo bloqueado · {{ \App\Models\EmailSuppression::REASONS[$user->email_suppression_reason] ?? 'Revisar entrega' }}</span></div>
@endif
@if($user->registration_followup_stop_reason)
    <div class="mt-1"><span class="badge badge-secondary">{{ $user->registration_followup_stop_reason === 'limit_reached' ? 'Recordatorios finalizados · 3 envíos' : 'Recordatorios detenidos · representado respondió' }}</span></div>
@endif
@can('integrations.manage')
    @if($user->email_suppression_reason || $user->registration_followup_stop_reason)
        <a class="small" href="{{ route('admin.integrations.app-mail.index', ['recipient' => $user->email]) }}">Revisar estado del correo</a>
    @endif
@endcan
