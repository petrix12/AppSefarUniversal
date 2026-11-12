@if(isset($user) && $user->hasIndividualClientBlock())
    <div class="alert alert-warning" role="alert">
        <strong>Expediente bloqueado · Entregas suspendidas</strong>
        <div>{{ $user->clientBlockReasonDescription() }}</div>
        <div>Solo el personal autorizado puede revisar este caso. Un administrador debe registrar su resolución antes de restablecer el acceso.</div>
    </div>
@endif
