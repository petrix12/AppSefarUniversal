@extends('adminlte::page')

@section('title', 'Cupones Generales')

@section('content_header')
@stop

@section('content')

<style>
    /* Estilos de la tabla y el switch */
    table.dataTable, .dataTables_scrollHeadInner {
        width: 100% !important;
    }
    table.dataTable th, table.dataTable td {
        font-size: 1rem !important;
        padding: 10px 5px;
    }
    .switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }
    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
    }
    input:checked + .slider {
        background-color: #093143 !important;
    }
    input:checked + .slider:before {
        transform: translateX(26px);
    }
    .slider.round {
        border-radius: 34px;
    }
    .slider.round:before {
        border-radius: 50%;
    }
    div.dt-row {
        margin:10px 0px;
    }
</style>

<x-app-layout>
    <script src="https://code.jquery.com/jquery-3.6.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cleave.js/1.6.0/cleave.min.js"></script>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .swal2-popup {
            width: 50em!important;
        }
        .swal2-image {
            min-height: 70vh;
            max-height: 70vh; /* Establecemos un máximo de 80% de la altura de la ventana */
            min-width: 100%;
            max-width: 100%; /* Establecemos un máximo de 80% de la altura de la ventana */
            object-fit: contain; /* Hacemos que la imagen se ajuste al contenedor sin distorsionar */
        }
    </style>

    <script type="text/javascript">
        function previewImage(imageUrl) {
            Swal.fire({
                imageUrl: imageUrl,
                imageAlt: 'Previsualización de la imagen', // Texto alternativo descriptivo
                showConfirmButton: true, // Mostrar el botón de confirmación
                confirmButtonText: 'Cerrar', // Texto del botón de confirmación
            });
        }
    </script>
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                <div class="bg-gray-50">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                        <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                            <span class="ctvSefar block text-indigo-600">Cupones Generales</span>
                        </h2>
                        <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                            <div class="inline-flex rounded-md shadow">
                                <a href="{{ route('generalcoupons.create') }}" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                    Registrar Cupon General
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card p-6">
    @if (!is_null($cupones))
        <table class="min-w-full divide-y divide-gray-200 w-100" id="example">
            <thead class="bg-gray-50">
            <tr>
                <th scope="col">Cupón</th>
                <th scope="col">Desde</th>
                <th scope="col">Hasta</th>
                <th scope="col">Eliminar</th>
            </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
            @foreach ($cupones as $cupon)
            <tr>
                <td>{{ $cupon->title }}</td>
                <td>{{ date("d-m-Y", strtotime($cupon->start_date)) }}</td>
                <td>{{ date("d-m-Y", strtotime($cupon->end_date)) }}</td>
                <td class="text-center">
                    <form action="{{ route('generalcoupons.destroy', $cupon) }}" method="POST">
                        @csrf
                        @method('delete')
                        <button type="submit" class="text-red-600 hover:text-red-900" onclick="return confirm('¿Está seguro que desea eliminar este cupón?')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    @endif
    </div>
</x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css">
<script type="text/javascript" src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>

<script type="text/javascript">
    $(document).ready(function(){
        $('#example').DataTable({
            scrollX: true,
            scroller: true,
            "order": [],
            "language": {
                "lengthMenu": "Mostrar _MENU_ resultados por página",
                "zeroRecords": "No hay resultados",
                "info": "Página _PAGE_ de _PAGES_",
                "infoEmpty": "No hay resultados"
            }
        });
    });
</script>
@stop
