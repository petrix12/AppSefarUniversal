@extends('adminlte::page')

@section('title', 'Comprobantes de Pago')

@section('content_header')

@stop

@section('content')

<style>
    table.dataTable, .dataTables_scrollHeadInner {
        width: 100% !important;
    }
    table.dataTable th {
        font-size: 1rem !important;
        margin: auto;
        padding: 10px 5px;
        font-weight: 400;
    }
    table.dataTable td {
        font-size: 0.9rem !important;
        padding: 10px 5px;
        margin: auto;
    }
    /* The switch - the box around the slider */
    .switch {
      position: relative;
      display: inline-block;
      width: 60px;
      height: 34px;
    }

    /* Hide default HTML checkbox */
    .switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    /* The slider */
    .slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #ccc;
      -webkit-transition: .4s;
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
      -webkit-transition: .4s;
      transition: .4s;
    }

    input:checked + .slider {
      background-color: rgb(121,22,15) !important;
    }

    input:focus + .slider {
      box-shadow: 0 0 1px rgb(121,22,15) !important;
    }

    input:checked + .slider:before {
      -webkit-transform: translateX(26px);
      -ms-transform: translateX(26px);
      transform: translateX(26px);
    }

    /* Rounded sliders */
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
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                {{-- Inicio --}}
                <div class="bg-gray-50">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                        <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                            <span class="ctvSefar block text-indigo-600">Comprobantes de Pago</span>
                        </h2>
                    </div>
                </div>
                {{-- Fin --}}
            </div>
        </div>
    </div>
    <div class="card p-6">
    @if (!is_null($datos_factura))
        <table class="min-w-full divide-y divide-gray-200 w-100" id="example">
            <thead class="bg-gray-50">
            <tr>
                <th scope="col" class="" style="width: 10%;">
                    ID
                </th>
                <th scope="col" class="" style="width: 50%;">
                    Nombre y Pasaporte
                </th>
                <th scope="col" class="" style="width: 30%;">
                    Fecha de Emisión
                </th>
                <th scope="col" class="" style="width: 10%;">
                    Ver Comprobante
                </th>
            </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">

            @foreach ($datos_factura as $factura)
            <tr>
                <td class="">
                    {{ $factura["id"] }}
                </td>
                <td class="">
                    <b>{{ $factura["name"] }}</b><br>{{ $factura["passport"] }}
                </td>
                <td class="">
                    <?php
                        echo(date("d-m-Y", strtotime($factura["created_at"])));
                    ?>
                </td>
                <td class="">
                    <a href="{{ route('viewcomprobante', ['id' => $factura['id']]) }}" target="_blank" class="btn btn-primary" title="Ver Comprobante"><i class="fas fa-eye"></i></a>
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
    <script type="text/javascript" language="javascript" src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" language="javascript" src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>

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
    $(document).on("change", ".disablecoupon", function(){
        var test = $( "#disablecouponcheck_"+this.id ).prop( "checked");

        $.ajaxSetup({

            headers: {
                'X-CSRF-TOKEN': $("input[name='_token']").val()
            }

        });

        $.ajax({
            url: '{{ route("cuponenable") }}',
            data: {id: this.id},
            method: 'POST'
        });
    })
</script>
@stop