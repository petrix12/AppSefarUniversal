@extends('adminlte::page')

@section('title', 'Vista Tree')

@section('content_header')

@stop

@section('content')
<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                @livewire('vistas.arbol.tree-vista', ['IDCliente' => $IDCliente])
            </div>
        </div>
    </div>
</x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="/css/tree.css">
@stop

@section('js')
@stop