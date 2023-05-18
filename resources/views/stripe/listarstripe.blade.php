@extends('adminlte::page')

@section('title', 'Buscar en Stripe')

@section('content_header')

@stop

@section('content')

<x-app-layout>
	<form action="{{ route('exportdatastripeexcel') }}" method="POST">
		<div class="flex flex-col">
		    <div class="">
		        <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
		            {{-- Inicio --}}
		            <div >
		                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
		                    <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
		                        <span class="ctvSefar block text-indigo-600">Histórico - Pagos mensuales en Stripe</span>
		                        <?php
		                        	$total = 0;
		                        	foreach ($charges as $charge){
		                        		$total = $total + $charge["amount"]/100;
		                        	}
		                        ?>
		                    	<div id="total" style="font-size: 24px;">
									<small>Total recaudado en el mes de mayo: {{$total}}€</small>
								</div>
								<div style="font-size: 24px;">
									<small>Saldo disponible en Stripe: {{$balance["available"][0]["amount"]}}€</small>
								</div>
								<div style="font-size: 24px;">
									<small>Saldo pendiente en Stripe: {{$balance["pending"][0]["amount"]}}€</small>
								</div>
		                    </h2>
		                    <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
	                            <div class="inline-flex rounded-md shadow">
	                                <button type="submit" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
	                                    Descargar Excel
	                                </button>
	                            </div>
	                        </div>
		                </div>
		            </div>
		            {{-- Fin --}}
		        </div>
		    </div>
		</div>

		<center>

			@csrf

			<div style="padding: 20px;">
				<div class="card" style="padding:20px 40px; display: inline-block; width: 100%;">
					<select name="monthstripe" id="monthstripe" style="width:20%; margin-right: 20px;">
						<?php 
							$meses = array(
							    1 => 'Enero',
							    2 => 'Febrero',
							    3 => 'Marzo',
							    4 => 'Abril',
							    5 => 'Mayo',
							    6 => 'Junio',
							    7 => 'Julio',
							    8 => 'Agosto',
							    9 => 'Septiembre',
							    10 => 'Octubre',
							    11 => 'Noviembre',
							    12 => 'Diciembre'
							);

							$mes_actual = date('n');

							foreach ($meses as $numero => $nombre) {
							    $selected = ($numero == $mes_actual) ? 'selected' : '';
							    echo "<option value=\"$numero\" $selected>$nombre</option>";
							}
						?>
					</select>
					<select name="yearstripe" id="yearstripe" style="width:10%;">
						<?php 
							for($i=2019; $i<date('Y')+1; $i++){
								if ($i == date('Y')){
									echo('<option selected value="'.$i.'">'.$i.'</option>');
								} else {
									echo('<option value="'.$i.'">'.$i.'</option>');
								}
							}
						?>
					</select>
				</div>
			</div>


		</center>

	</form>

	<div id="ajaxload" style="background-color: rgba(0, 0, 0, 0.4); position: fixed; z-index: 1000; display: none; width: 100%; height: 100%;"></div>

	<table id="example" class="table table-striped" style="width: 100%;">
		<thead>
			<tr>
				<th>
					Correo cliente
				</th>
				<th>
					Monto
				</th>
				<th>
					Fecha (España, Venezuela)
				</th>
				<th>
					Pago ID
				</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($charges as $charge)
				<tr>
					<td style="vertical-align: center;">
						{{ $charge["receipt_email"] }}
					</td>
					<td style="vertical-align: center;">
						{{ $charge["amount"]/100 }}
					</td>
					<td style="vertical-align: center;">
						<p style="display: inline-flex;"><img src="https://flagdownload.com/wp-content/uploads/Flag_of_Spain_Flat_Round.png" style="width:18px; height:18px;">{{ date('d/m/Y H:i:s', $charge["created"] + 2 * 60 * 60) }}</p><br>
						<p style="display: inline-flex;"><img src="https://static.vecteezy.com/system/resources/previews/011/571/444/original/circle-flag-of-venezuela-free-png.png" style="width:18px; height:18px;"> {{ date('d/m/Y H:i:s', $charge["created"] - 4 * 60 * 60) }}</p>
					</td>
					<td style="vertical-align: center;">
						{{ $charge["id"] }}
					</td>
				</tr>
			@endforeach
		</tbody>
	</table>
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
</script>

@stop
