@extends('adminlte::page')

@section('title', 'Pruebas flex Tailwind CSS')

@section('content_header')
    <h1>Prueba con flex de Tailwind CSS</h1>
@stop

@section('content')
    <h1 class="text-gray-700 font-bold sticky top-0 m-2">Ejemplo Flexbox 1:</h1>
    <div class="container">
        <div class="bg-gray-300 flex">
            <div class="bg-blue-400 text-gray-700 px-4 py-2 m-2">1</div>
            <div class="bg-blue-400 text-gray-700 px-4 py-2 m-2">2</div>
            <div class="bg-blue-400 text-gray-700 px-4 py-2 m-2">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 2:</h1>
    <div class="container">
        <div class="bg-gray-300 flex flex-row-reverse">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 3:</h1>
    <div class="container">
        <div class="bg-gray-300 flex flex-col">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 4:</h1>
    <div class="container">
        <div class="bg-gray-300 flex flex-col-reverse">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 5:</h1>
    <div class="container">
        <div class="bg-gray-300 flex justify-end">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 6:</h1>
    <div class="container">
        <div class="bg-gray-300 flex justify-center">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 7:</h1>
    <div class="container">
        <div class="bg-gray-300 flex justify-between">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 8:</h1>
    <div class="container">
        <div class="bg-gray-300 flex justify-around">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 9:</h1>
    <div class="container">
        <div class="bg-gray-300 flex flex-col h-64 justify-around">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 11:</h1>
    <div class="container">
        <div class="bg-gray-300 flex flex-col h-64 justify-end">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 12:</h1>
    <div class="container">
        <div class="bg-gray-300 flex flex-col h-64 justify-center">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 13:</h1>
    <div class="container">
        <div class="bg-gray-300 flex h-64 justify-center">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 14:</h1>
    <div class="container">
        <div class="bg-gray-300 flex h-64 justify-center items-start">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 15:</h1>
    <div class="container">
        <div class="bg-gray-300 flex h-64 justify-center items-end">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 16:</h1>
    <div class="container">
        <div class="bg-gray-300 flex h-64 justify-center items-center">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 17:</h1>
    <div class="container">
        <div class="bg-gray-300 flex h-64 justify-center items-baseline">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 text-sm">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 text-lg">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 text-3xl">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 18:</h1>
    <div class="container">
        <div class="bg-gray-300 flex h-64 justify-center items-start">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 text-sm">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 text-lg">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 text-3xl">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 19:</h1>
    <div class="container">
        <div class="bg-gray-300 flex h-64 justify-center">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 20:</h1>
    <div class="container">
        <div class="bg-gray-300 flex h-64 justify-center flex-wrap">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 21:</h1>
    <div class="container">
        <div class="bg-gray-300 flex h-64 justify-center flex-wrap-reverse">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 22:</h1>
    <div class="container">
        <div class="bg-gray-300 flex h-64 justify-center flex-wrap content-between">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 23:</h1>
    <div class="container">
        <div class="bg-gray-300 flex h-64 justify-center flex-wrap content-around">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 24:</h1>
    <div class="container">
        <div class="bg-gray-300 flex h-64 justify-center flex-wrap items-start">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64 self-stretch">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64 self-center">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64 self-end">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 25:</h1>
    <div class="container">
        <div class="bg-gray-300 flex h-64">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 w-64">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 26:</h1>
    <div class="container">
        <div class="bg-gray-300 flex">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 flex-1">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 27:</h1>
    <div class="container">
        <div class="bg-gray-300 flex">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 flex-1">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 flex-1">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2">3</div>
        </div>
    </div>

    <h1 class="text-gray-700 font-bold sticky top-0 m-2 mt-5">Ejemplo Flexbox 28:</h1>
    <div class="container">
        <div class="bg-gray-300 flex">
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 flex-1">1</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 flex-1">2</div>
            <div class="bg-gray-400 text-gray-700 px-4 py-2 m-2 flex-1">3</div>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css\prueba_flex.css') }}">
@stop

@section('js')

@stop