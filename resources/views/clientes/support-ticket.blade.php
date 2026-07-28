@extends('adminlte::page')

@section('title', 'Solicitud de soporte')

@section('content_header')
@stop

@section('content')
<x-app-layout>
    <style>
        .hidden, .border-gray-100 {
            display: none!important;
        }
    </style>

    <div class="flex flex-col">
        <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
            <div class="bg-gray-50">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                    <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                        <span class="ctvSefar block text-indigo-600">Solicitud de soporte</span>
                    </h2>
                    <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                        <div class="inline-flex rounded-md shadow">
                            <a href="{{ route('clientes.status') }}" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                Volver a mi estatus
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                @if(session('support_success'))
                    <div class="alert alert-success">
                        {{ session('support_success') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('clientes.support-ticket.store') }}">
                    @csrf
                    <input type="hidden" name="source" value="Menu AdminLTE - Solicitud de soporte">

                    <div class="form-group">
                        <label for="request_description">Describe tu solicitud</label>
                        <textarea
                            id="request_description"
                            name="request_description"
                            class="form-control"
                            rows="8"
                            minlength="15"
                            maxlength="3000"
                            required
                            placeholder="Cuéntanos que necesitas revisar o que ayuda requieres..."
                        >{{ old('request_description') }}</textarea>
                    </div>

                    <div class="form-group">
                        <label>Correo del cliente</label>
                        <input class="form-control" value="{{ $user->email }}" readonly>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-ticket-alt mr-1"></i>
                        Enviar solicitud
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop
