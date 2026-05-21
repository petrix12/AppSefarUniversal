@extends('adminlte::page')

@section('title', 'Crear grupo familiar')

@section('content_header')
@stop

@section('content')
<x-app-layout>
    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="family-form-header">
                    <div>
                        <div class="family-form-eyebrow">Nuevo grupo calculado</div>
                        <h1 class="family-form-title">Crear grupo familiar</h1>
                    </div>
                    <a href="{{ route('family-groups.index') }}">Volver</a>
                </div>

                <form method="POST" action="{{ route('family-groups.store') }}" class="family-form">
                    @csrf

                    <div class="family-form-grid">
                        <label>
                            <span>IDCliente base</span>
                            <input type="text" name="IDCliente" value="{{ old('IDCliente') }}" required placeholder="Ej. 187739869">
                            @error('IDCliente')
                                <small>{{ $message }}</small>
                            @enderror
                        </label>

                        <label>
                            <span>Nombre del grupo</span>
                            <input type="text" name="name" value="{{ old('name') }}" placeholder="Opcional">
                            @error('name')
                                <small>{{ $message }}</small>
                            @enderror
                        </label>

                        <label class="is-wide">
                            <span>Notas</span>
                            <textarea name="notes" rows="4" placeholder="Notas internas del grupo">{{ old('notes') }}</textarea>
                            @error('notes')
                                <small>{{ $message }}</small>
                            @enderror
                        </label>
                    </div>

                    <div class="family-form-help">
                        Al crear el grupo se agrega el cliente base y se buscan coincidencias iniciales por pasaporte, documento, nombre completo y apellidos compatibles en otros arboles.
                    </div>

                    <div class="family-form-actions">
                        <button type="submit">Crear y calcular coincidencias</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <style>
        .family-form-header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 24px;
            border-bottom: 1px solid rgba(9, 49, 67, 0.12);
        }
        .family-form-header a,
        .family-form-actions button {
            background: #093143;
            color: white;
            border-radius: 8px;
            padding: 9px 14px;
            font-size: 0.85rem;
            font-weight: 700;
            border: 0;
        }
        .family-form-eyebrow {
            color: #607783;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .family-form-title {
            margin: 4px 0 0;
            color: #093143;
            font-size: 1.8rem;
            font-weight: 800;
        }
        .family-form {
            padding: 24px;
        }
        .family-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }
        .family-form-grid label {
            display: flex;
            flex-direction: column;
            gap: 7px;
            color: #093143;
            font-weight: 700;
        }
        .family-form-grid label.is-wide {
            grid-column: 1 / -1;
        }
        .family-form-grid input,
        .family-form-grid textarea {
            border: 1px solid #cbd5dc;
            border-radius: 8px;
            padding: 9px 12px;
            color: #093143;
            font-weight: 400;
        }
        .family-form-grid small {
            color: #a22525;
            font-weight: 600;
        }
        .family-form-help {
            margin-top: 18px;
            padding: 12px 14px;
            border-radius: 8px;
            background: #eef6f8;
            color: #405763;
            font-size: 0.9rem;
        }
        .family-form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 22px;
        }
        @media (max-width: 760px) {
            .family-form-header,
            .family-form-grid {
                grid-template-columns: 1fr;
                flex-direction: column;
            }
        }
    </style>
@stop
