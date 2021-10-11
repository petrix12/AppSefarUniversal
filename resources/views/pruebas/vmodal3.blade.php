@extends('adminlte::page')

@section('title', 'Ventana Modal')

@section('content_header')
    <h1>Ventana Modal bbbb</h1>
@stop

@section('content')

<button onclick="document.getElementById('myModal').showModal()" id="btn" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
    Abrir ventana modal
</button>


<dialog id="myModal" class="h-auto w-11/12 md:w-1/2 p-5  bg-white rounded-md ">      
    <div class="flex flex-col w-full h-auto ">
        <!-- Título -->
        <div class="flex w-full h-auto justify-center items-center">
            <div class="flex w-10/12 h-auto py-3 justify-center items-center text-2xl font-bold">
                Título
            </div>
            <div onclick="document.getElementById('myModal').close();" class="flex w-1/12 h-auto justify-center cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </div>
        </div>
        <!-- Contenido-->
        <div class="flex w-full h-auto py-10 px-2 justify-center items-center bg-gray-200 rounded text-center text-gray-500">
            Contenido
        </div>
        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
            <button type="submit" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Aceptar
            </button>
            <button onclick="document.getElementById('myModal').close();" class="cfgSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Cancelar
            </button>
        </div>
    </div>
</dialog>
@stop

@section('css')
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