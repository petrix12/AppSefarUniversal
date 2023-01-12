@extends('adminlte::page')

@section('title', 'Buscar en Stripe')

@section('content_header')
    <h1>Buscar en Stripe</h1>
@stop

<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.2/jquery.min.js" integrity="sha512-tWHlutFnuG0C6nQRlpvrEhE4QpkG1nn2MOUMWmUeRePl4e3Aki0VB6W1v3oLjFtd0hVOtRQ9PHpSfN6u6/QXkQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script>
	$(document).on("click", "#buscar", function () {
		var info = $("#find").val();
		if (info==""){
			return false;
		}
		$("#ajaxload").show();
		$.ajaxSetup({

			headers: {
				'X-CSRF-TOKEN': $("input[name='_token']").val()
			}

		});
		$.ajax({
			url: '{{ route("stripefind") }}',
			method: 'POST',
			data: {
				info: info
			},
			success: function(data){
				$("#ajaxload").hide();
				if (data!="none"){
					var table = '<table class="table"><thead><tr><th scope="col">Correo</th><th scope="col">Nombre</th><th scope="col">Pasaporte</th><th scope="col">Revisar</th></tr></thead><tbody>';

					for (var i = 0; i < data.length; i++) {
						var table = table + '<tr><th scope="row">' + data[i]["data"]["email"] + '</th><td>' + data[i]["datadb"]["name"] + '</td><td>' + data[i]["datadb"]["passport"] + '</td><td><input type="button" class="getinfo btn btn-danger" value="Revisar" id="'+ data[i]["data"]["email"] +'"></td></tr>';
					}

					var table = table + '</tbody></table>';
					$(".tablecontainer").html(table);
				}
			}

		});
	})

	$(document).on("click",".getinfo",function(){
		var info = this.id;
		$.ajaxSetup({

			headers: {
				'X-CSRF-TOKEN': $("input[name='_token']").val()
			}

		});
		$.ajax({
			url: '{{ route("stripegetidpago") }}',
			method: 'POST',
			data: {
				info: info
			},
			success: function(data){
				$("#ajaxload").hide();
				if (data!="none"){
					$("#showdata").show();
					$("#nombrepago").html(data[0]["datadb"]["name"]);
					$("#correopago").html(data[0]["datadb"]["email"]);
					$("#pasaportepago").html(data[0]["datadb"]["passport"]);
					$("#idstripedb").html(data[0]["datadb"]["id_pago"]);
					var table = '<table class="table"><thead><tr><th scope="col">Monto pagado</th><th scope="col">Servicio</th><th scope="col">Data Stripe (id de pago)</th><th scope="col">Fecha (Hora venezolana)</th><th scope="col">Fecha (Hora española)</th></tr></thead><tbody>';
					for (var i = 0; i < data.length; i++) {
						var table = table + '<tr><td>' + (data[i]["datapago"]["amount"]/100) + ' €</td><td>'+data[i]["datapago"]["description"]+'</td><td>'+data[i]["datapago"]["id"]+'</td><td>'+data[i]["datevenezuela"]+'</td><td>'+data[i]["datespain"]+'</td></tr>';
					}
					var table = table + '</tbody></table>';
					$("#pagosrealiz").html(table);
				}
			}

		});
	})

	$(document).on("click","#updatedata",function(){
		$('.btn-disablecheck').prop('disabled', true);
		$.ajaxSetup({

			headers: {
				'X-CSRF-TOKEN': $("input[name='_token']").val()
			}

		});
		$.ajax({
			url: '{{ route("stripeupdatedata") }}',
			method: 'POST',
			data: {
				correopago: $("#correopago").html(),
				stripeidpago: $("#stripeidpago").html()
			},
			success: function(data){
				$('.btn-disablecheck').prop('disabled', false);
			}

		});
	})

	$(document).on("click","#closemodal",function(){
		$("#showdata").hide();
	});
</script>

<div id="showdata" style="background-color: rgba(0, 0, 0, 0.6); top: 0;position: fixed; z-index: 9000; display: none; width: 100%; height: 100%; margin: auto;">
	<div style="width: 70%; height: auto; margin: auto; padding: 20px; background-color: white; top: 50%; left: 50%; transform: translate(-50%, -50%);position: absolute;">
		<center>
			<h2>Datos a revisar de Stripe</h2>
		</center>
		<table class="table">
			<thead>
				<tr>
					<th scope="col">Nombre</th>
					<th scope="col">Correo</th>
					<th scope="col">Pasaporte</th>
					<th scope="col">ID de Pago (registrado en db)</th>
				</tr>
			</thead>
			<tbody>
				<tr>
				  <td id="nombrepago"></td>
				  <td id="correopago"></td>
				  <td id="pasaportepago"></td>
				  <td id="idstripedb"></td>
				</tr>
			</tbody>
		</table>
		<center>
			<h4>Pagos registrados en Stripe</h4>
		</center>
		<div id="pagosrealiz">
		</div>
		<center>
			<input type="button" class="btn btn-disablecheck btn-danger" value="Cerrar Ventana" id="closemodal">
		</center>
	</div>
</div>

<div id="ajaxload" style="background-color: rgba(0, 0, 0, 0.4); position: fixed; z-index: 1000; display: none; width: 100%; height: 100%;"></div>

<style>
	
	.containerstripe{
		width: 90%;
		height: auto;
		margin: auto;
	}

	.findercontainer{
		width: 100%;
		display: inline-flex;
	}

	#find{
		margin: 5px;
		width: 80%;
	}
	#buscar{
		margin: 5px;
		width: 20%;
	}

	.table td, .table th {
		vertical-align: middle !important;
	}
</style>

@section('content')

	<div class="containerstripe">
		<div class="findercontainer">
			@csrf
			<input type="text" class="control-label" placeholder="Nombre o Correo" id="find"><input type="button" class="btn btn-primary" value="Buscar en Stripe" id="buscar">
		</div>
		<div class="tablecontainer">

		</div>
	</div>

@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')

@stop
