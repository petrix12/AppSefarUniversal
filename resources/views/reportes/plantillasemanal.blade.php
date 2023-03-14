<?php

	$normal = 0;

	$normalarray = [];

	$Amigos = 0;
	$Bufetes = 0;
	$Coordinadores = 0;
	$Google = 0;
	$OtrasRedesSociales = 0;
	$Otros = 0;
	$Ventas = 0;

	foreach ($data["usuarios"] as $key => $usuario) {
		switch ($usuario["tipo_referido"]) {
			case 'Google':
				$Google++;
				break;
			case 'Coordinadores':
				$Coordinadores++;
				break;
			case 'Amigos':
				$Amigos++;
				break;
			case 'Bufetes':
				$Bufetes++;
				break;
			case 'Otras redes sociales':
				$OtrasRedesSociales++;
				break;
			case 'Otros':
				$Otros++;
				break;
			case 'Ventas':
				$Ventas++;
				break;
		}
	}

	$Amigos_c = 0;
	$Bufetes_c = 0;
	$Coordinadores_c = 0;
	$Google_c = 0;
	$OtrasRedesSociales_c = 0;
	$Otros_c = 0;
	$Ventas_c = 0;

	foreach ($data["users_ftb"] as $key => $usuario) {
		switch ($usuario["tipo_referido"]) {
			case 'Google':
				$Google_c++;
				break;
			case 'Coordinadores':
				$Coordinadores_c++;
				break;
			case 'Amigos':
				$Amigos_c++;
				break;
			case 'Bufetes':
				$Bufetes_c++;
				break;
			case 'Otras redes sociales':
				$OtrasRedesSociales_c++;
				break;
			case 'Otros':
				$Otros_c++;
				break;
			case 'Ventas':
				$Ventas_c++;
				break;
		}
	}
	

?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Reporte Semanal - {{ $data['fechatexto'] }}</title>
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

		table,thead,tbody, th, td,tr {
			border: 1px solid black;
			border-collapse: collapse;
		}

		td {
			font-size: 18px;
		}

		th {
			color: white;
			font-size: 20px;
		}

		header, .headerlmd {
			position: fixed;
            width: 100%;
            top: 0cm;
            left: 0cm;
            right: 0cm;
		}
		
	</style>
</head>
<body>
	<div class="background-pages">
		<img width="100%" height="100%" src="{{ public_path('/img/reportes/semanal.png') }}">
	</div>
	<header>
		<img width="100%" src="{{ public_path('/img/reportes/header.png') }}">
	</header>
	<main>
		<div class="contenido">
			<center>
				<h1><a>CLIENTES</a> <a style="color:rgb(121, 22, 15);">REFERIDOS</a></h1>
				<h3 style="color:rgb(121, 22, 15);">Semana #{{$data["semananum"]}}</h3>
				<h3>Clientes registrados en la semana: <?php count($data['usuarios']); ?> </h3>
				
				<h1><br><br></h1>

				Clientes Referidos desde la semana 1 hasta la semana {{$data["semananum"]}}

				<table style="margin-top: 7px; width:60%; margin-left: auto; margin-right: auto;">
					<thead>
						<tr style="background-color: rgb(121, 22, 15); width: 100%; font-style: bold; text-align: center;">
							<th style="padding: 4px;">Referido</th>
							<th style="padding: 4px;">Clientes</th>
				       	</tr>
				   	<thead>
				   	<tbody>

						<tr>
							<td>Google</td>
							<td>{{ $Google_c; }}</td>
						</tr>
						<tr>
							<td>Coordinadores</td>
							<td>{{ $Coordinadores_c; }}</td>
						</tr>
						<tr>
							<td>Amigos</td>
							<td>{{ $Amigos_c; }}</td>
						</tr>
						<tr>
							<td>Bufetes</td>
							<td>{{ $Bufetes_c; }}</td>
						</tr>
						<tr>
							<td>Otras redes sociales</td>
							<td>{{ $OtrasRedesSociales_c; }}</td>
						</tr>
						<tr>
							<td>Otros</td>
							<td>{{ $Otros_c; }}</td>
						</tr>
						<tr>
							<td>Ventas</td>
							<td>{{ $Ventas_c; }}</td>
						</tr>

					</tbody>

				</table>

				<h1><br></h1>

				Clientes Referidos en la semana {{$data["semananum"]}}

				<table style="margin-top: 7px; width:60%; margin-left: auto; margin-right: auto;">
					<thead>
						<tr style="background-color: rgb(121, 22, 15); width: 100%; font-style: bold; text-align: center;">
							<th style="padding: 4px;">Referido</th>
							<th style="padding: 4px;">Clientes</th>
				       	</tr>
				   	<thead>
				   	<tbody>

						<tr>
							<td>Google</td>
							<td>{{ $Google; }}</td>
						</tr>
						<tr>
							<td>Coordinadores</td>
							<td>{{ $Coordinadores; }}</td>
						</tr>
						<tr>
							<td>Amigos</td>
							<td>{{ $Amigos; }}</td>
						</tr>
						<tr>
							<td>Bufetes</td>
							<td>{{ $Bufetes; }}</td>
						</tr>
						<tr>
							<td>Otras redes sociales</td>
							<td>{{ $OtrasRedesSociales; }}</td>
						</tr>
						<tr>
							<td>Otros</td>
							<td>{{ $Otros; }}</td>
						</tr>
						<tr>
							<td>Ventas</td>
							<td>{{ $Ventas; }}</td>
						</tr>

					</tbody>

				</table>
			</center>
		</div>
	</main>

	<div class="page_break"></div>
</body>
</html>

