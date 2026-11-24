@extends('adminlte::page')

@section('title', 'Usuarios')

@section('content_header')

@stop

@section('content')
<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if(session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <livewire:crud.users-table :entity-type="$entityType ?? 'clients'" />
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
