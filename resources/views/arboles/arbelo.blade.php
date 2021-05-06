@extends('adminlte::page')

@section('title', 'Vista Arbelo')

@section('content_header')

@stop

@section('content')
<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                @livewire('vistas.arbol.albero-vista', ['IDCliente' => $IDCliente])
            </div>
        </div>
    </div>
</x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css\arbelo.css') }}">
    <link rel="stylesheet" href="{{ asset('css\cdn_tailwind.css') }}">
    <link rel="stylesheet" href="{{ asset('css\sefar.css') }}">
    <style>
        dialog[open] {
            animation: appear .15s cubic-bezier(0, 1.8, 1, 1.8);
        }
    
        dialog::backdrop {
            background: linear-gradient(45deg, rgba(121, 22, 15, 0.5), rgba(63, 61, 61, 0.5));
            backdrop-filter: blur(3px);
        }
        
        @keyframes appear {
            from {
                opacity: 0;
                transform: translateX(-3rem);
            }
    
            to {
                opacity: 1;
                transform: translateX(0);
            }
        } 
    </style>
@stop

@section('js')
@stop