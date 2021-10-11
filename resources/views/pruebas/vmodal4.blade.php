@extends('adminlte::page')

@section('title', 'Ventana Modal')

@section('content_header')
    <h1>Ventana Modal bbbb</h1>
@stop

@section('content')

{{-- Ventana vieja --}}
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


{{-- Ventana nueva --}}
<button onclick="openModal(true)" 
    class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
    Abrir ventana modal
</button>

<!-- overlay -->
<div id="modal_overlay" class="hidden absolute inset-0 bg-black bg-opacity-30 h-screen w-full flex justify-center items-start md:items-center pt-10 md:pt-0">
    <!-- modal -->
    <div id="modal" class="pacity-0 transform -translate-y-full scale-150  relative w-10/12 md:w-1/2 h-1/2 md:h-3/4 bg-white rounded shadow-lg transition-opacity transition-transform duration-300">

        <!-- button close -->
        <button 
        onclick="openModal(false)"
        class="absolute -top-3 -right-3 bg-red-500 hover:bg-red-600 text-2xl w-10 h-10 rounded-full focus:outline-none text-white">
        &cross;
        </button>

        <!-- header -->
        <div class="px-4 py-3 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-600">Title</h2>
        </div>

        <!-- body -->
        <div class="w-full p-3">
        Lorem ipsum, dolor sit amet consectetur adipisicing elit. Asperiores, quis tempora! Similique, explicabo quaerat maxime corrupti tenetur blanditiis voluptas molestias totam? Quaerat laboriosam suscipit repellat aliquam blanditiis eum quos nihil.
        </div>

        <!-- footer -->
        <div class="absolute bottom-0 left-0 px-4 py-3 border-t border-gray-200 w-full flex justify-end items-center gap-3">
        <button class="bg-green-500 hover:bg-green-600 px-4 py-2 rounded text-white focus:outline-none">Save</button>
        <button 
            onclick="openModal(false)"
            class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded text-white focus:outline-none"
        >Close</button>
        </div>
    </div>
</div>
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
<script>
    const modal_overlay = document.querySelector('#modal_overlay');
    const modal = document.querySelector('#modal');
    
    function openModal (value){
        const modalCl = modal.classList
        const overlayCl = modal_overlay
    
        if(value){
        overlayCl.classList.remove('hidden')
        setTimeout(() => {
            modalCl.remove('opacity-0')
            modalCl.remove('-translate-y-full')
            modalCl.remove('scale-150')
        }, 100);
        } else {
        modalCl.add('-translate-y-full')
        setTimeout(() => {
            modalCl.add('opacity-0')
            modalCl.add('scale-150')
        }, 100);
        setTimeout(() => overlayCl.classList.add('hidden'), 300);
        }
    }
    openModal(true)
    </script>
@stop








