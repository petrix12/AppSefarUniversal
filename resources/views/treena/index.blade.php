@extends('adminlte::page')

@section('title', 'Prompt Treena')

@section('content_header')

@stop

@section('content')

<x-app-layout>

    <style>
        .hidden, .border-gray-100 {
            display: none!important;
        }
        /* Estilos personalizados para el textarea */
        .custom-textarea {
            width: 100%;
            height: 400px;
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 4px;
            resize: vertical; /* Permite redimensionar verticalmente */
        }
        /* Estilos para el botón */
        .btn-guardar {
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-guardar:hover {
            background-color: #45a049;
        }
    </style>
    <div class="flex flex-col">
        <div class="">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                {{-- Inicio --}}
                <div class="bg-gray-50">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                        <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                            <span class="ctvSefar block text-indigo-600">Prompt Treena</span>
                        </h2>
                    </div>
                </div>
                {{-- Fin --}}

                {{-- Formulario para guardar el prompt --}}
                <form action="{{ route('treena.update') }}" method="POST">
                    @csrf <!-- Token de seguridad -->
                    <div class="mt-6">
                        <textarea name="context_prompt" class="custom-textarea" placeholder="Escribe tu prompt aquí...">{{ $treenaprompt->context_prompt }}</textarea>
                    </div>
                    <div>
                        <button type="submit" class="btn-guardar">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</x-app-layout>

@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
    @if (session('success'))
        <script>
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: '{{ session('success') }}',
            });
        </script>
    @endif
@stop
