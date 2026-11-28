@extends('adminlte::page')
@section('title', 'Revisión de pagos')
@section('content_header')<h1>Revisión de pagos</h1>@stop
@section('content')
    <div class="card"><div class="card-body">
        <form method="GET" action="{{ route('crud.finance.payment-reviews') }}">
            <div class="form-group"><label for="payment-review-client">Buscar representado por nombre, correo, pasaporte o ID</label>
                <input id="payment-review-client" name="q" class="form-control" maxlength="175" value="{{ $q }}" required>
            </div>
            <button class="btn sefar-button cfrSefar">Buscar</button>
        </form>
        @if($q !== '')
            <div class="table-responsive mt-3"><table class="table table-hover"><thead><tr><th>Representado</th><th>Correo</th><th>Documento</th><th>Acción</th></tr></thead><tbody>
                @forelse($clients as $client)<tr><td>{{ $client->name }}</td><td>{{ $client->email }}</td><td>{{ $client->passport }}</td><td><a class="btn btn-sm sefar-button cfrSefar" href="{{ route('crud.users.overpayments', $client) }}">Abrir revisión</a></td></tr>
                @empty<tr><td colspan="4" class="text-center text-muted py-4">No se encontraron representados accesibles con esa búsqueda.</td></tr>@endforelse
            </tbody></table></div>
            <p class="small text-muted">Se muestran hasta 20 coincidencias. Puedes precisar la búsqueda con el correo, pasaporte o ID del representado.</p>
        @endif
    </div></div>
@stop
