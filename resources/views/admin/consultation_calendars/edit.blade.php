@extends('adminlte::page')

@section('title', 'Editar Calendario')

@section('content_header')
    <h1>Editar Calendario</h1>
@stop

@section('content')
    <form action="{{ route('admin.consultation-calendars.update', $calendar) }}" method="POST">
        @csrf
        @method('PUT')
        @include('admin.consultation_calendars.partials.form')
    </form>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop
