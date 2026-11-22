@extends('adminlte::page')

@section('title', 'Histórico PayPal España')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/platform-management.css') }}">
@stop

@section('content_header')
    <h1>Histórico PayPal España</h1>
@stop

@section('content')
    <div class="platform-admin"><div class="card">
        <div class="card-body">
            <p>Consulta de solo lectura de la cuenta histórica. PayPal permite buscar hasta tres años atrás y por periodos de máximo 31 días.</p>
            @include('kalendia.partials.timezone', ['timezoneContextHelp'=>'La fecha de la operación determina el horario de verano.'])
            <p class="text-muted small">Los filtros Desde y Hasta corresponden a fechas UTC. Las horas de cada operación se adaptan a tu zona horaria.</p>
            @if (! $configured)
                <div class="alert alert-info">Consulta pendiente: faltan las credenciales dedicadas de PayPal España.</div>
            @endif
            @foreach ($apiErrors as $error)
                <div class="alert alert-warning">{{ $error }}</div>
            @endforeach
            @if ($errors->any())
                <div class="alert alert-danger">Revisa las fechas o el identificador indicado.</div>
            @endif

            <form method="GET" action="{{ route('admin.paypal.history') }}" class="row mb-4">
                <div class="col-md-3 mb-3">
                    <label for="start_date">Desde</label>
                    <input class="form-control" id="start_date" name="start_date" type="date" required value="{{ old('start_date', $startDate) }}">
                    @error('start_date')<small class="text-danger">{{ $message }}</small>@enderror
                </div>
                <div class="col-md-3 mb-3">
                    <label for="end_date">Hasta</label>
                    <input class="form-control" id="end_date" name="end_date" type="date" required value="{{ old('end_date', $endDate) }}">
                    @error('end_date')<small class="text-danger">{{ $message }}</small>@enderror
                </div>
                <div class="col-md-3 mb-3">
                    <label for="transaction_id">ID de transacción (opcional)</label>
                    <input class="form-control" id="transaction_id" name="transaction_id" maxlength="19" value="{{ old('transaction_id', $transactionId) }}">
                    @error('transaction_id')<small class="text-danger">ID PayPal no válido.</small>@enderror
                </div>
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <button class="btn btn-primary" type="submit" @disabled(! $configured)>Consultar</button>
                </div>
            </form>

            @if ($result && $result['ok'])
                @php($transactions = $result['transactions'] ?? [])
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead><tr><th>Fecha</th><th>ID</th><th>Estado</th><th>Importe</th><th>Concepto</th><th>Pagador</th></tr></thead>
                        <tbody>
                        @forelse ($transactions as $row)
                            @php($info = data_get($row, 'transaction_info', []))
                            @php($payer = data_get($row, 'payer_info', []))
                            <tr>
                                <td><span data-kalendia-instant="{{ data_get($info, 'transaction_initiation_date') }}">{{ data_get($info, 'transaction_initiation_date', '—') }}</span></td>
                                <td>{{ data_get($info, 'transaction_id', '—') }}</td>
                                <td>{{ ['S'=>'Completado','P'=>'Pendiente','D'=>'Denegado','V'=>'Revertido'][data_get($info, 'transaction_status')] ?? data_get($info, 'transaction_status', '—') }}</td>
                                <td>{{ data_get($info, 'transaction_amount.value', '—') }} {{ data_get($info, 'transaction_amount.currency_code', '') }}</td>
                                <td>{{ data_get($info, 'transaction_subject') ?: data_get($info, 'transaction_note', '—') }}</td>
                                <td>{{ data_get($payer, 'payer_name.full_name') ?: data_get($payer, 'payer_name.alternate_full_name') ?: trim(data_get($payer, 'payer_name.given_name', '').' '.data_get($payer, 'payer_name.surname', '')) ?: data_get($payer, 'email_address', '—') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center">Sin transacciones para esos filtros.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if (($result['total_pages'] ?? 1) > 1)
                    <nav class="d-flex justify-content-between" aria-label="Paginación de transacciones">
                        @if ($result['page'] > 1)
                            <a class="btn btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['page' => $result['page'] - 1]) }}">Anterior</a>
                        @else <span></span> @endif
                        <span>Página {{ $result['page'] }} de {{ $result['total_pages'] }}</span>
                        @if ($result['page'] < $result['total_pages'])
                            <a class="btn btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['page' => $result['page'] + 1]) }}">Siguiente</a>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </div></div>
@stop
