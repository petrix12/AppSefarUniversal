@extends('adminlte::page')

@section('title', 'Nuevo Calendario')

@section('content_header')
    <h1>Nuevo Calendario</h1>
@stop

@section('content')
    <form action="{{ route('admin.consultation-calendars.store') }}" method="POST">
        @csrf
        @include('admin.consultation_calendars.partials.form')
    </form>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop
