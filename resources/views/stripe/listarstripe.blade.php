@extends('adminlte::page')

@section('title', 'Histórico Stripe')

@section('content_header')

@stop

@section('content')

<div id="ajaxload" style="background-color: rgba(0, 0, 0, 0.4); position: fixed; z-index: 1000; top: 0; left: 0; display: none; width: 100%; height: 100%;"></div>

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
		                        		if ($charge["status"] == 'succeeded') {
		                        			$total = $total + $charge["amount"]/100;
		                        		}
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
					<select name="yearstripe" id="yearstripe" style="width:10%; margin-right: 20px;">
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
					<a id="sendAjaxStripe" class="cfrSefar border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700" style="    padding-top: 0.5rem;
    padding-right: 0.75rem;
    padding-bottom: 0.5rem;
    padding-left: 0.75rem;">
                        Actualizar Tabla
                    </a>
				</div>
			</div>


		</center>

	</form>

	<input type="hidden" id="actualmonth" value="{{ intval(date('m')) }}">
	<input type="hidden" id="actualyear" value="{{ date('Y') }}">

	<div id="tablecontainer" style="width: 100%;">
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
					@if ($charge["status"] == 'succeeded')
						<tr>
							<td style="vertical-align: center;">
								{{ $charge["receipt_email"] }}
							</td>
							<td style="vertical-align: center;">
								{{ $charge["amount"]/100 }}€
							</td>
							<td style="vertical-align: center;">
								<p style="display: inline-flex;"><img src="https://flagdownload.com/wp-content/uploads/Flag_of_Spain_Flat_Round.png" style="width:18px; height:18px;">{{ date('d/m/Y H:i:s', $charge["created"] + 2 * 60 * 60) }}</p><br>
								<p style="display: inline-flex;"><img src="https://static.vecteezy.com/system/resources/previews/011/571/444/original/circle-flag-of-venezuela-free-png.png" style="width:18px; height:18px;"> {{ date('d/m/Y H:i:s', $charge["created"] - 4 * 60 * 60) }}</p>
							</td>
							<td style="vertical-align: center;">
								{{ $charge["id"] }}
							</td>
						</tr>
					@endif
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
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

    $('#monthstripe, #yearstripe').on('change', function(){

    	if ($("#yearstripe").val()>$("#actualyear").val()){
    		Swal.fire({
                icon: 'error',
                title: '¡Aviso!',
                html: '<p>No hay ningún resultado para esta fecha en Stripe.</p>',
                showDenyButton: false,
                confirmButtonText: 'Entendido',
                denyButtonText: 'Volver a la Página Principal',
            });

    		return false;
    	}

    	if ($("#actualyear").val()==$("#yearstripe").val() && $("#monthstripe").val()>$("#actualmonth").val()){
    		Swal.fire({
                icon: 'error',
                title: '¡Aviso!',
                html: '<p>No hay ningún resultado para esta fecha en Stripe.</p>',
                showDenyButton: false,
                confirmButtonText: 'Entendido',
                denyButtonText: 'Volver a la Página Principal',
            });

    		return false;
    	} 

    	$("#ajaxload").show();

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $("input[name='_token']").val()
            }
        });

        $.ajax({
	        url: '{{ route("getStripeAJAX") }}',
	        method: 'POST',
	        data: {
	            monthstripe: $('#monthstripe').val(),
				yearstripe: $('#yearstripe').val()
	        },
	        success: function(response){
	            var data = JSON.parse(response);

	            var table = '<table id="example" class="table table-striped" style="width: 100%;"><thead><tr><th>Correo cliente</th><th>Monto</th><th>Fecha (España, Venezuela)</th><th>Pago ID</th></tr></thead><tbody>';

	            for (var i = 0; i < data.length; i++) {
	            	var table = table + '<tr><td style="vertical-align: center;">' + data[i][3] + '</td><td style="vertical-align: center;">' + data[i][1] + '€</td><td style="vertical-align: center;"><p style="display: inline-flex;"><img src="https://flagdownload.com/wp-content/uploads/Flag_of_Spain_Flat_Round.png" style="width:18px; height:18px;">'+ data[i][5] + '</p><br><p style="display: inline-flex;"><img src="https://static.vecteezy.com/system/resources/previews/011/571/444/original/circle-flag-of-venezuela-free-png.png" style="width:18px; height:18px;">' + data[i][4] + '</p></td><td style="vertical-align: center;">' + data[i][0] + '</td></tr>';
	            }


	            var table = table + '</tbody></table>';

	            $('#tablecontainer').html(table);

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
		        $("#ajaxload").hide();
	        }
	    });
    });
</script>

@stop
