<?php

	$normal = 0;

	$normalarray = [];

	foreach ($data["usuarios"] as $i => $user){
		$normal = $normal + 1;
		$normalarray[] = $user;
	}

?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Reporte - {{ $data['fechatexto'] }}</title>
	<style type="text/css">

		@page {
            margin: 0cm 0cm;
        }

		body {
			margin: 85px 60px 60px 60px!important;
        }

		.page_break { 
			page-break-after:always;
		}

		.background-pages{
			position: fixed;
			margin: 0;
			padding: 0;
			border: 0;
			width: 100%;
			height: 100%;
			z-index: -1;
			margin: 0cm 0cm;
			top: 0cm;
            left: 0cm;
            right: 0cm;
		}

		h1,h2,h3,h4,h5 {
			margin: 0;
			padding: 0;
			border: 0;
		}

		table, th, td {
			border: 1px solid black;
			border-collapse: collapse;
		}

		header, .headerlmd {
			position: fixed;
            width: 100%;
            top: 0cm;
            left: 0cm;
            right: 0cm;
		}
		
	</style>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.2.1/chart.min.js" integrity="sha512-v3ygConQmvH0QehvQa6gSvTE2VdBZ6wkLOlmK7Mcy2mZ0ZF9saNbbk19QeaoTHdWIEiTlWmrwAL4hS8ElnGFbA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
</head>
<body>
	<div class="background-pages">
		<img width="100%" height="100%" src="{{ public_path('/img/reportes/diario.png') }}">
	</div>
	<header>
		<img width="100%" src="{{ public_path('/img/reportes/header.png') }}">
	</header>
	<main>
		<div class="contenido">
			<center>
				<h1><a>PERSONAS</a> <a style="color:#f7b034;">REGISTRADAS</a></h1>
				<h3 style="color:#f7b034;">{{ $data['fechatexto'] }}</h3>
				<h3>Clientes registrados en el día: <?php echo($normal); ?> </h3>
				<table style="margin-top: 7px; width:100%; font-size: 12px;">
					<thead>
						<tr style="background-color: #f7b034; width: 100%; font-style: bold; text-align: center;">
							<th style="padding: 4px;">Nombre</th>
							<th style="padding: 4px;">Referido Por</th>
							<th style="padding: 4px;">Servicio Solicitado</th>
							<th style="padding: 4px;">Pago</th>
				       	</tr>
				   	<thead>
				   	<tbody>

					@foreach ($normalarray as $i => $user)

						<tr>
							<td style="padding: 4px;">{{ ucwords(strtolower($user["name"])) }}</td>
							<td style="padding: 4px;">{{ $user["nombre_referido"] }}</td>
							<td style="padding: 4px;">{{ $user["servicio"] }}</td>
							@if ($user["pay"]>0)
								<td style="padding: 4px;">Si</td>
							@else
								<td style="padding: 4px;">NO</td>
							@endif
						</tr>

					@endforeach

					</tbody>

				</table>
			</center>
		</div>
	</main>

	<div class="page_break"></div>

	<main>
		<div class="contenido">
			<center>
				<h1><a>REPORTE</a> <a style="color:#f7b034;">DIARIO</a></h1>
				<h3 style="color:#f7b034;">{{ $data['fechatexto'] }}</h3>
				<h3>Clientes registrados en el día: <?php echo($normal); ?> </h3>
				<table style="margin-top: 7px; width:100%; font-size: 12px;">
					<thead>
						<tr style="background-color: #f7b034; width: 100%; font-style: bold; text-align: center;">
							<th style="padding: 4px;">Nombre</th>
							<th style="padding: 4px;">Referido Por</th>
							<th style="padding: 4px;">Servicio Solicitado</th>
							<th style="padding: 4px;">Pago</th>
				       	</tr>
				   	<thead>
				   	<tbody>

					@foreach ($normalarray as $i => $user)

						<tr>
							<td style="padding: 4px;">{{ ucwords(strtolower($user["name"])) }}</td>
							<td style="padding: 4px;">{{ $user["nombre_referido"] }}</td>
							<td style="padding: 4px;">{{ $user["servicio"] }}</td>
							@if ($user["pay"]>0)
								<td style="padding: 4px;">Si</td>
							@else
								<td style="padding: 4px;">NO</td>
							@endif
						</tr>

					@endforeach

					</tbody>

				</table>
			</center>
		</div>
	</main>
</body>
</html>

