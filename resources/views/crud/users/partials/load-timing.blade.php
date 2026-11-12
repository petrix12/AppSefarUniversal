@if(\App\Support\CosPageTiming::authorized(request()))
    @if(\App\Support\CosPageTiming::current())
        <details class="card mb-3 cos-load-timing" id="cos-load-timing" open>
            <summary class="card-header font-weight-bold">Cronómetro de carga del COS</summary>
            <div class="card-body">
                <p class="small text-muted">Diagnóstico privado de tu cuenta. Cada inicio se cuenta desde que la solicitud entra en la App. Los tiempos de SQL y API están incluidos en sus etapas.</p>
                <div class="row">
                    <div class="col-6 col-lg-3 mb-3"><span class="small text-muted d-block">Servidor</span><strong data-cos-metric="server">Midiendo…</strong></div>
                    <div class="col-6 col-lg-3 mb-3"><span class="small text-muted d-block">Base de datos</span><strong data-cos-metric="database">Midiendo…</strong></div>
                    <div class="col-6 col-lg-3 mb-3"><span class="small text-muted d-block">Primer byte (TTFB)</span><strong data-cos-metric="ttfb">Midiendo…</strong></div>
                    <div class="col-6 col-lg-3 mb-3"><span class="small text-muted d-block">Carga del navegador</span><strong data-cos-metric="load">Midiendo…</strong></div>
                </div>
                <p class="small" data-cos-timing-status aria-live="polite">Esperando a que termine de cargar la página…</p>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Etapa del servidor</th><th>Inicio</th><th>Duración</th><th>Consultas SQL</th><th>Tiempo SQL</th></tr></thead>
                        <tbody data-cos-stages></tbody>
                    </table>
                </div>
                <details class="mb-3"><summary>Ver llamadas externas y recursos del navegador</summary>
                    <p class="small text-muted mt-2">Las llamadas externas instrumentadas ya están incluidas en sus etapas del servidor. Los recursos del navegador pueden descargarse en paralelo.</p>
                    <div class="table-responsive"><table class="table table-sm">
                        <thead><tr><th>Llamada externa instrumentada</th><th>Tiempo</th><th>Intentos</th><th>Excepciones</th></tr></thead><tbody data-cos-external></tbody>
                    </table></div>
                    <div class="table-responsive"><table class="table table-sm">
                        <thead><tr><th>Intento / espera local</th><th>Inicio</th><th>Duración</th><th>Resultado</th></tr></thead><tbody data-cos-external-calls></tbody>
                    </table></div>
                    <p class="small text-muted">Detalle de cada transferencia HTTP: DNS, conexión TCP, TLS, espera de respuesta y descarga. «No disponible» significa que el transporte no proporcionó ese dato. La espera incluye el tiempo de respuesta y la latencia de red.</p>
                    <div class="table-responsive"><table class="table table-sm">
                        <thead><tr><th>API</th><th>HTTP</th><th>Inicio</th><th>Total</th><th>DNS</th><th>TCP</th><th>TLS</th><th>Espera</th><th>Descarga</th></tr></thead><tbody data-cos-http></tbody>
                    </table></div>
                    <div class="table-responsive"><table class="table table-sm">
                        <thead><tr><th>Recurso más lento</th><th>Tipo</th><th>Tiempo</th></tr></thead><tbody data-cos-resources></tbody>
                    </table></div>
                    <p class="small text-muted" data-cos-browser-details></p>
                </details>
                <div class="d-flex flex-wrap align-items-center" style="gap: .5rem">
                    <a class="btn btn-sm sefar-button cfrSefar" href="{{ request()->fullUrlWithQuery(['cos_timing' => 1]) }}">Volver a medir</a>
                    <button type="button" class="btn btn-sm sefar-button cfrSefar" data-cos-copy disabled>Copiar medición</button>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['cos_timing' => 0]) }}">Cerrar cronómetro</a>
                </div>
                <p class="small text-muted mt-2 mb-0" data-cos-trace></p>
            </div>
        </details>
        <!--COS_TIMING_PAYLOAD-->
        <script src="{{ asset('js/cos-load-timing.js') }}" defer></script>
    @else
        <div class="mb-3">
            <a class="btn btn-sm sefar-button cfrSefar" href="{{ request()->fullUrlWithQuery(['cos_timing' => 1]) }}">
                <i class="fas fa-stopwatch mr-1" aria-hidden="true"></i> Cronometrar carga
            </a>
        </div>
    @endif
@endif
