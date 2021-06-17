@extends('adminlte::page')

@section('title', 'Base de datos genealógica')

@section('content_header')

@stop

@section('content')
<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                @if (session('info'))
                    <div class="alert alert-danger">
                        {{ session('info') }}
                    </div>
                @endif
                @livewire('crud.agclientes-table')
            </div>
        </div>
    </div>
</x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
@stop