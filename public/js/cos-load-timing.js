(function () {
    'use strict';
    const panel = document.getElementById('cos-load-timing');
    const payload = document.getElementById('cos-timing-data');
    if (!panel || !payload) return;
    const report = JSON.parse(payload.textContent);
    let refreshObserved = null;
    const milliseconds = value => Number.isFinite(value) ? new Intl.NumberFormat('es', { maximumFractionDigits: 1 }).format(value) + ' ms' : 'No disponible';
    const metric = (name, value) => { panel.querySelector('[data-cos-metric="' + name + '"]').textContent = value; };
    const rows = (selector, values) => {
        const body = panel.querySelector(selector);
        body.replaceChildren();
        values.forEach(values => {
            const row = document.createElement('tr');
            values.forEach(value => {
                const cell = document.createElement('td');
                cell.textContent = value;
                row.appendChild(cell);
            });
            body.appendChild(row);
        });
    };
    metric('server', milliseconds(report.server_ms));
    metric('database', milliseconds(report.query_ms) + ' · ' + report.queries + ' consultas');
    rows('[data-cos-stages]', report.stages.map(stage => [stage.label, milliseconds(stage.started_ms), milliseconds(stage.duration_ms), stage.queries, milliseconds(stage.query_ms)]));
    rows('[data-cos-external]', report.external.length
        ? report.external.map(call => [call.label, milliseconds(call.duration_ms), call.calls, call.failures])
        : [['Sin llamadas instrumentadas en esta apertura', '—', '—', '—']]);
    rows('[data-cos-external-calls]', report.external_calls.map(call => [call.label, milliseconds(call.started_ms), milliseconds(call.duration_ms), call.failed ? 'Excepción' : 'Completado']));
    rows('[data-cos-http]', report.http_transfers.length ? report.http_transfers.map(call => [
        call.service, call.method + ' ' + (call.status ?? 'sin respuesta'), milliseconds(call.started_ms), milliseconds(call.duration_ms),
        milliseconds(call.dns_ms), milliseconds(call.tcp_ms), milliseconds(call.tls_ms), milliseconds(call.waiting_ms), milliseconds(call.download_ms)
    ]) : [['Sin transferencias HTTP instrumentadas en esta apertura', '—', '—', '—', '—', '—', '—', '—', '—']]);
    panel.querySelector('[data-cos-trace]').textContent = 'Referencia de medición: ' + report.trace_id;
    const refreshMessage = () => {
        report.snapshot_ready_observed_ms = refreshObserved;
        panel.querySelector('[data-cos-timing-status]').textContent = 'Se observó la actualización en segundo plano a los ' + milliseconds(refreshObserved)
            + ' desde la navegación. Se conserva este informe; usa «Volver a medir» para abrir el COS actualizado.';
    };
    document.addEventListener('cos:refresh-ready', event => {
        refreshObserved = event.detail.observed_ms;
        refreshMessage();
    });

    function complete() {
        const navigation = performance.getEntriesByType('navigation')[0];
        const paint = performance.getEntriesByName('first-contentful-paint')[0];
        const status = panel.querySelector('[data-cos-timing-status]');
        if (navigation) {
            report.browser = {
                ttfb_ms: navigation.responseStart - navigation.startTime,
                html_download_ms: navigation.responseEnd - navigation.responseStart,
                dom_ready_ms: navigation.domContentLoadedEventEnd - navigation.startTime,
                load_ms: navigation.loadEventEnd > 0 ? navigation.loadEventEnd - navigation.startTime : null,
                first_contentful_paint_ms: paint ? paint.startTime : null
            };
            metric('ttfb', milliseconds(report.browser.ttfb_ms));
            metric('load', milliseconds(report.browser.load_ms));
            panel.querySelector('[data-cos-browser-details]').textContent = 'Descarga del HTML: ' + milliseconds(report.browser.html_download_ms)
                + ' · DOM listo: ' + milliseconds(report.browser.dom_ready_ms)
                + ' · Primer contenido visible: ' + milliseconds(report.browser.first_contentful_paint_ms);
        }
        report.resources = performance.getEntriesByType('resource')
            .filter(resource => resource.duration > 0 && /^https?:/.test(resource.name))
            .sort((a, b) => b.duration - a.duration).slice(0, 8)
            .map(resource => {
                const url = new URL(resource.name);
                // Query strings and fragments can carry credentials or personal data.
                return { resource: url.hostname + '/' + (url.pathname.split('/').pop() || '/'), type: resource.initiatorType, duration_ms: Math.round(resource.duration * 100) / 100 };
            });
        rows('[data-cos-resources]', report.resources.map(resource => [resource.resource, resource.type, milliseconds(resource.duration_ms)]));
        const slowest = report.stages.reduce((max, stage) => stage.duration_ms > max.duration_ms ? stage : max, { label: 'Sin etapas', duration_ms: 0 });
        status.textContent = 'Etapa más lenta del servidor: ' + slowest.label + ' (' + milliseconds(slowest.duration_ms) + ').'
            + (report.refresh_pending ? ' Hay una actualización del COS en segundo plano pendiente; su espera no está incluida en estos tiempos.' : '');
        if (refreshObserved !== null) refreshMessage();
        const copy = panel.querySelector('[data-cos-copy]');
        copy.disabled = false;
        copy.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(JSON.stringify(report, null, 2));
                copy.textContent = 'Medición copiada';
            } catch (error) {
                copy.textContent = 'No se pudo copiar';
            }
        });
    }
    if (document.readyState === 'complete') window.setTimeout(complete, 0);
    else window.addEventListener('load', () => window.setTimeout(complete, 0), { once: true });
})();
