@extends('adminlte::page')

@section('title', 'Pagos Pendientes')

@section('content_header')

@stop

@section('content')

<x-app-layout>

    <style>
        .hidden, .border-gray-100 {
            display: none!important;
        }
    </style>
    <div class="flex flex-col">
        <div class="">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                {{-- Inicio --}}
                <div class="bg-gray-50">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                        <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                            <span class="ctvSefar block text-indigo-600">Pagos Pendientes</span>
                        </h2>
                        <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                            <div class="inline-flex rounded-md shadow">
                                <a href="/pay" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                    Regresar a Ventana de Pago
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Fin --}}
                <div class="card p-6">
                    @if (!is_null($compras))
                        <table class="min-w-full divide-y divide-gray-200 w-100" id="example">
                            <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-3 py-1" style="width: 75%;">
                                    Factura
                                </th>
                                <th scope="col" class="px-3 py-1" style="width: 15%;">
                                    Fecha de Emisión
                                </th>
                                <th scope="col" class="px-3 py-1" style="width: 10%;">
                                    Acciones
                                </th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">

                            @foreach ($compras as $compra)
                            <tr>
                                <td class="py-2 px-3" >
                                    <b>{{ $compra["descripcion"] }}
                                </td>
                                <td class="py-2 px-3">
                                    <?php
                                        echo(date("d-m-Y", strtotime($compra["created_at"])));
                                    ?>
                                </td>
                                <td class="py-2 px-3" style="text-align: center;">
                                    <form action="{{ route('gotopayfases') }}" method="POST" style="display: inline;">
                                        @csrf <!-- Token de seguridad para Laravel -->
                                        <input type="hidden" name="id" value="{{ $compra['id'] }}">
                                        <button type="submit" class="btn btn-primary" title="Ir a pagar">
                                            <i class="fas fa-credit-card"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @else
                        <h5>No hay pagos pendientes en este momento</h5>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</x-app-layout>

@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css">
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables CSS para Bootstrap 4 -->
<script src="https://code.jquery.com/jquery-3.6.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cleave.js/1.6.0/cleave.min.js"></script>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- DataTables CSS para Bootstrap 4 -->

<script type="text/javascript" src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function () {
        $('#example').DataTable({
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
