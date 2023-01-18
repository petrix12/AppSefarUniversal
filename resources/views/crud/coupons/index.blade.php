@extends('adminlte::page')

@section('title', 'Cupones')

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
    <div class="card p-6">
        <table class="min-w-full divide-y divide-gray-200 w-100" id="example">
            <thead class="bg-gray-50">
            <tr>
                <th scope="col" class="">
                    Cupón
                </th>
                <th scope="col" class="">
                    %
                </th>
                <th scope="col" class="">
                    Creado por
                </th>
                <th scope="col" class="">
                    Solicita
                </th>
                <th scope="col" class="">
                    Cliente
                </th>
                <th scope="col" class="">
                    Motivo
                </th>
                <th scope="col" class="">
                    Vence
                </th>
                <th scope="col" class="">
                    Mas info
                </th>
                @can('crud.coupons.edit')
                <th scope="col" class="">
                    {{ __('Edit') }}
                </th>
                @endcan
                @can('crud.coupons.enable')
                <th scope="col" class="">
                    Habilitar
                </th>
                @endcan
                @can('crud.coupons.destroy')
                <th scope="col" class="">
                    {{ __('Remove') }}
                </th>
                @endcan
            </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
            @foreach ($coupons as $coupon)
            <tr>
                <td class="">
                    {{ $coupon->couponcode }}
                </td>
                <td class="">
                    {{ $coupon->percentage }}
                </td>
                <td class="">
                    {{ $coupon->name }}
                </td>
                <td class="">
                    {{ $coupon->solicitante }}
                </td>
                <td class="">
                    {{ $coupon->cliente }}
                </td>
                <td class="">
                    {{ $coupon->motivo }}
                </td>
                <td class="">
                    {{ $coupon->expire }}
                </td>
                <td class="text-center">
                    <i class="fas fa-eye getCouponInfo" id="{{ $coupon->couponcode }}"></i>
                </td>
                @can('crud.coupons.edit')
                <td class="text-center">
                    <a href="{{ route('crud.coupons.edit', $coupon) }}" title="Editar"><i class="fas fa-edit"></i></a>
                </td>
                @endcan
                @can('crud.coupons.enable')
                <td class="">
                    <label class="switch disablecoupon" id="{{ $coupon->id }}" >
                        <input type="checkbox" <?php if($coupon->enabled==1) echo "checked"; ?> id="disablecouponcheck_{{ $coupon->id }}">
                        <span class="slider round"></span>
                    </label>
                </td>
                @endcan
                @can('crud.coupons.destroy')
                <td class="text-center">
                    <form action="{{ route('crud.coupons.destroy', $coupon) }}" method="POST">
                        @csrf
                        @method('delete')
                        <button
                            type="submit"
                            class="text-red-600 hover:text-red-900"
                            onclick="return confirm('¿Está seguro que desea eliminar el cupón?')"><i class="fas fa-trash"></i>
                        </button>
                    </form>
                </td>
                @endcan
            </tr>
            @endforeach
            </tbody>
        </table>
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