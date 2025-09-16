@extends('adminlte::page')

@section('title', 'Visitas COS')

@section('content_header')
    <h1>Visitas COS</h1>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@stop

@section('content')
    <div class="mb-3">
        <label for="filtro">Filtrar por:</label>
        <select id="filtro" class="form-control w-auto d-inline-block">
            <option value="semana">Semana</option>
            <option value="mes" selected>Mes</option>
            <option value="año">Año</option>
            <option value="todo">Todo</option>
        </select>
    </div>

    <div class="row">
        <div class="col-md-8">
            <canvas id="visitasChart"></canvas>
        </div>
        <div class="col-md-4">
            <h4 id="totalVisitas"></h4>
        </div>
    </div>

    <hr>

    <div class="table-responsive">
        <table class="table table-striped" id="tablaVisitas">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Usuario</th>
                    <th>Cliente</th>
                    <th>Fecha de visita</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
@stop

@section('js')
<script>
    // Laravel pasa todos los registros al frontend
    const todasLasVisitas = @json($visitas);

    // Inicializar gráfico
    const ctx = document.getElementById('visitasChart').getContext('2d');
    let visitasChart = new Chart(ctx, {
        type: 'bar',
        data: { labels: [], datasets: [] },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    // Filtrar visitas según el rango elegido
    function filtrarVisitas(filtro) {
        const hoy = new Date();
        let inicio;

        if (filtro === "semana") {
            inicio = new Date();
            inicio.setDate(hoy.getDate() - 7);
        } else if (filtro === "mes") {
            inicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        } else if (filtro === "año") {
            inicio = new Date(hoy.getFullYear(), 0, 1);
        } else {
            inicio = new Date(0); // trae todo
        }

        return todasLasVisitas.filter(v => {
            const fecha = new Date(v.fecha_visita);
            return fecha >= inicio;
        });
    }

    // Renderizar gráfico y tabla
    function renderizar(filtro) {
        const visitas = filtrarVisitas(filtro);

        // Agrupar por usuario
        const visitasPorUsuario = {};
        visitas.forEach(v => {
            const nombre = v.user?.nombres || v.user?.name || "Usuario desconocido";
            visitasPorUsuario[nombre] = (visitasPorUsuario[nombre] || 0) + 1;
        });

        // Actualizar gráfico
        visitasChart.data.labels = Object.keys(visitasPorUsuario);
        visitasChart.data.datasets = [{
            label: 'Visitas por usuario',
            data: Object.values(visitasPorUsuario),
            backgroundColor: 'rgba(75, 192, 192, 0.6)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
        }];
        visitasChart.update();

        // Total
        document.getElementById('totalVisitas').innerText = "Total visitas: " + visitas.length;

        // Tabla de visitas
        const tbody = document.querySelector("#tablaVisitas tbody");
        tbody.innerHTML = "";
        visitas.forEach((v, index) => {
            const tr = `
                <tr>
                    <td>${index + 1}</td>
                    <td>${v.user?.nombres + " " + v.user?.apellidos || "Usuario desconocido"}</td>
                    <td>${v.cliente?.nombres + " " + v.cliente?.apellidos || v.cliente?.name || "Cliente desconocido"}</td>
                    <td>${new Date(v.fecha_visita).toLocaleString()}</td>
                </tr>
            `;
            tbody.innerHTML += tr;
        });
    }

    // Inicializar filtros
    document.getElementById("filtro").addEventListener("change", (e) => {
        renderizar(e.target.value);
    });

    // Render inicial
    renderizar("todo");
</script>
@stop
