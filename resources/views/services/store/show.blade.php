@extends('adminlte::page')

@section('title', $servicio->nombre)

@section('content_header')
    <h1>{{ $servicio->nombre }}</h1>
@stop

@section('content')
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <p>{{ $servicio->descripcion_publica ?: 'Servicio Sefar Universal.' }}</p>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Tipo</dt>
                        <dd class="col-sm-8">{{ $servicio->tipo }}</dd>
                        <dt class="col-sm-4">Precio</dt>
                        <dd class="col-sm-8">{{ number_format((float) $servicio->precio, 2) }} {{ $servicio->moneda ?? 'EUR' }}</dd>
                        @if($servicio->requiere_agenda || $servicio->tipo === 'consulta')
                            <dt class="col-sm-4">Duracion</dt>
                            <dd class="col-sm-8">{{ $servicio->duracion_minutos ?? 60 }} minutos</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('service-store.purchase', $servicio) }}" method="POST">
                        @csrf

                        @if($servicio->requiere_agenda || $servicio->tipo === 'consulta')
                            <div class="form-group">
                                <label>Horario disponible</label>
                                @if(empty($slotsByCalendar))
                                    <div class="alert alert-warning mb-0">
                                        No hay horarios disponibles para este servicio.
                                    </div>
                                @else
                                    <select name="slot" class="form-control" required>
                                        <option value="">Selecciona un horario</option>
                                        @foreach($slotsByCalendar as $calendarSlots)
                                            @foreach($calendarSlots['slots'] as $date => $slots)
                                                <optgroup label="{{ $calendarSlots['calendar']->nombre }} - {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}">
                                                    @foreach($slots as $slot)
                                                        <option value="{{ $slot['token'] }}" {{ old('slot') === $slot['token'] ? 'selected' : '' }}>
                                                            {{ $slot['label'] }}
                                                        </option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                        @endif

                        <button type="submit" class="btn btn-primary btn-block" {{ ($servicio->requiere_agenda || $servicio->tipo === 'consulta') && empty($slotsByCalendar) ? 'disabled' : '' }}>
                            Agregar al pago
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop
