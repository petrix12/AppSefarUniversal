@php($meetingSummary = app(\App\Services\KalendiaAppointmentService::class)->summary($user))
<section class="card mb-4" aria-labelledby="cos-meetings-title">
    <div class="card-header"><h2 id="cos-meetings-title" class="h5 mb-0">Reuniones y consultas</h2></div>
    <div class="card-body">
        @include('kalendia.partials.timezone')
        <strong>{{ $meetingSummary['status'] }}</strong>
        @if($meetingSummary['next'])<p class="mb-2">{{ $meetingSummary['next']->service_name }} · <time data-kalendia-instant="{{ $meetingSummary['next']->starts_at->toIso8601String() }}">{{ $meetingSummary['next']->starts_at->timezone(\App\Services\KalendiaTimezone::preferred())->format('d/m/Y H:i') }}</time></p>@endif
        @if(auth()->id() === $user->id && auth()->user()->hasRole('Cliente'))
            <a href="{{ route('clientes.meetings') }}" class="btn btn-sm btn-outline-primary">Ver mis reuniones</a>
        @elseif(\Illuminate\Support\Facades\Gate::allows('kalendia.manage'))
            <a href="{{ route('admin.kalendia.appointments', ['client' => $user->id]) }}" class="btn btn-sm btn-outline-primary">Ver reuniones del representado</a>
        @endif
        <small class="d-block text-muted mt-2">La agenda se actualiza en segundo plano desde Kalendia.</small>
    </div>
</section>
