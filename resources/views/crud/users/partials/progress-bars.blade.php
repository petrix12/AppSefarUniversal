{{-- Determinar tipo de servicio --}}
@php
    $esCartaNaturaleza = in_array($proceso['servicio'], [
        'Española - Carta de Naturaleza General',
        'Nacionalidad por Carta de Naturaleza'
    ]);

    $esPortuguesaSefardi = in_array($proceso['servicio'], [
        'Portuguesa Sefardí',
        'Portuguesa - Sefardí'
    ]);

    $totalPasosGen = count($cos[$proceso['servicio']]['genealogico'] ?? []);
    $totalPasosJur = count($cos[$proceso['servicio']]['juridico'] ?? []);

    $currentGen = $proceso['currentStepGen'] ?? -1;
    $currentJur = $proceso['currentStepJur'] ?? -1;

    $hayWarning = isset($proceso['warning']) && !empty($proceso['warning']);

    // ==========================================
    // CALCULAR ÚLTIMO PASO ACTIVO (GENEALÓGICO)
    // ==========================================
    $ultimoActivoGen = null;

    foreach (($cos[$proceso['servicio']]['genealogico'] ?? []) as $s) {
        $isActiveTmp = ($currentGen + 1) >= $s['paso'];

        if ($isActiveTmp) {
            $ultimoActivoGen = $s['paso'];
        }
    }

    // ==========================================
    // CALCULAR ÚLTIMO PASO ACTIVO (JURÍDICO)
    // ==========================================
    $ultimoActivoJur = null;

    foreach (($cos[$proceso['servicio']]['juridico'] ?? []) as $s) {
        if ($currentJur === -1) {
            $isActiveTmp = false;
        } else {
            $isActiveTmp = ($currentJur + 1) >= $s['paso'];
        }

        if ($isActiveTmp) {
            $ultimoActivoJur = $s['paso'];
        }
    }

    // ==========================================
    // REGLAS DE NARANJA (WARNING)
    // 1) Si jurídica está vacía (<0) => naranja solo último activo gen
    // 2) Si jurídica tiene progreso (>=0) => naranja solo último activo jur
    // ==========================================
    $pintarEn = ($currentJur >= 0) ? 'jur' : 'gen';
@endphp

{{-- Progreso Genealógico --}}
<h4 class="mb-4 mt-4"><b>Progreso Genealógico</b></h4>
<div class="progress-scroll-container mb-4">
    <div class="progress-container" id="progressContainerGen-{{ $index }}">
        <div class="progress-line-full"></div>
        {{-- OJO: ya NO ponemos progress-line-warning para todo; el warning es solo en el último icono --}}
        <div class="progress-line"
             style="width: {{ $proceso['progressPercentageGen'] ?? 0 }}%;"></div>

        @foreach ($cos[$proceso['servicio']]['genealogico'] ?? [] as $step)
            @php
                // ========== CALCULAR SI ESTÁ ACTIVO ==========
                $isActive = ($currentGen + 1) >= $step['paso'];

                // ========== WARNING SOLO EN EL ÚLTIMO ACTIVO (SEGÚN REGLAS) ==========
                $esUltimoActivoNaranja =
                    $hayWarning &&
                    $pintarEn === 'gen' &&
                    $isActive &&
                    $ultimoActivoGen !== null &&
                    $step['paso'] == $ultimoActivoGen;

                // ========== CLASES ==========
                $claseEsfera = '';
                if ($isActive) {
                    $claseEsfera = $esUltimoActivoNaranja ? 'warningesfera' : 'active';
                }

                $iconoEsfera = $isActive
                    ? ($esUltimoActivoNaranja ? 'exclamation' : 'check-circle')
                    : 'check-circle';
            @endphp

            <div class="progress-step {{ $claseEsfera }}"
                data-step="{{ $step['paso'] }}"

                @if($isActive && $claseEsfera != 'warningesfera')
                    data-nombre="{{ $step['nombre_corto'] }}"
                    data-descripcion="{{ $step['promesa_pasado'] ?? $step['promesa'] ?? '' }}"
                    title="Haz clic para ver resumen de esta fase"
                    data-bs-toggle="tooltip"
                @else
                    data-nombre="{{ $step['nombre_corto'] }}"
                    data-descripcion="{{ $step['promesa'] ?? $step['promesa'] ?? '' }}"
                    title="Haz clic para ver resumen de esta fase"
                    data-bs-toggle="tooltip"
                @endif
            >
                <i class="fas fa-{{ $iconoEsfera }}"></i>
                <span class="step-label">{{ $step['nombre_corto'] }}</span>
            </div>
        @endforeach
    </div>
</div>

{{-- Progreso Jurídico --}}
<h4 class="mb-4"><b>Progreso Jurídico</b></h4>
<div class="progress-scroll-container">
    <div class="progress-container" id="progressContainerJur-{{ $index }}">
        <div class="progress-line-full"></div>
        {{-- OJO: ya NO ponemos progress-line-warning para todo; el warning es solo en el último icono --}}
        <div class="progress-line"
             style="width: {{ $proceso['progressPercentageJur'] ?? 0 }}%;"></div>

        @foreach ($cos[$proceso['servicio']]['juridico'] ?? [] as $step)
            @php
                // ========== EVALUAR SOLO PROGRESO JURÍDICO ==========
                // Si currentJur es -1, ningún paso jurídico está activo
                if ($currentJur === -1) {
                    $isActive = false;
                } else {
                    // Activar pasos hasta currentJur + 1 (los pasos son base 1)
                    $isActive = ($currentJur + 1) >= $step['paso'];
                }

                // ========== WARNING SOLO EN EL ÚLTIMO ACTIVO (SEGÚN REGLAS) ==========
                $esUltimoActivoNaranja =
                    $hayWarning &&
                    $pintarEn === 'jur' &&
                    $isActive &&
                    $ultimoActivoJur !== null &&
                    $step['paso'] == $ultimoActivoJur;

                // ========== CLASES ==========
                $claseEsfera = '';
                if ($isActive) {
                    $claseEsfera = $esUltimoActivoNaranja ? 'warningesfera' : 'active';
                }

                $iconoEsfera = $isActive
                    ? ($esUltimoActivoNaranja ? 'exclamation' : 'check-circle')
                    : 'check-circle';
            @endphp

            <div class="progress-step {{ $claseEsfera }}"
                data-step="{{ $step['paso'] }}"

                @if($isActive && $claseEsfera != 'warningesfera')
                    data-nombre="{{ $step['nombre_corto'] }}"
                    data-descripcion="{{ $step['promesa_pasado'] ?? $step['promesa'] ?? '' }}"
                    title="Haz clic para ver resumen de esta fase"
                    data-bs-toggle="tooltip"
                @else
                    data-nombre="{{ $step['nombre_corto'] }}"
                    data-descripcion="{{ $step['promesa'] ?? $step['promesa'] ?? '' }}"
                    title="Haz clic para ver resumen de esta fase"
                    data-bs-toggle="tooltip"
                @endif
            >
                <i class="fas fa-{{ $iconoEsfera }}"></i>
                <span class="step-label">{{ $step['nombre_corto'] }}</span>
            </div>
        @endforeach
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    function scrollToLastActive(containerId) {

        const container = document.getElementById(containerId);
        if (!container) return;

        // buscar el último verde o amarillo
        const steps = container.querySelectorAll('.progress-step.active, .progress-step.warningesfera');

        if (!steps.length) return;

        const lastStep = steps[steps.length - 1];

        // contenedor que realmente hace overflow
        const scrollParent = container.closest('.progress-scroll-container');
        if (!scrollParent) return;

        // calcular posición para centrarlo
        const offsetLeft = lastStep.offsetLeft;
        const stepWidth = lastStep.offsetWidth;
        const parentWidth = scrollParent.offsetWidth;

        const scrollPosition = offsetLeft - (parentWidth / 2) + (stepWidth / 2);

        scrollParent.scrollTo({
            left: scrollPosition,
            behavior: 'smooth'
        });
    }

    // Genealógico
    scrollToLastActive("progressContainerGen-{{ $index }}");

    // Jurídico
    scrollToLastActive("progressContainerJur-{{ $index }}");

});
</script>

<style>
/* =========================
   CONTENEDOR SCROLL
   ========================= */
.progress-scroll-container{
    overflow-x:auto;
    white-space:nowrap;
    width:100%;
    padding-bottom:12px;
    -webkit-overflow-scrolling:touch;
    height:150px;

    /* fade lateral sutil para look premium */
    mask-image: linear-gradient(to right, transparent 0, black 20px, black calc(100% - 20px), transparent 100%);
    -webkit-mask-image: linear-gradient(to right, transparent 0, black 20px, black calc(100% - 20px), transparent 100%);
}

/* =========================
   FILA PRINCIPAL
   ========================= */
.progress-container{
    display:inline-flex;
    justify-content:space-between;
    align-items:center;
    min-width:100%;
    position:relative;
    padding:0 20px 54px 20px;
    box-sizing:border-box;
}

/* =========================
   BARRA (TRACK + FILL)
   ========================= */
.progress-line-full,
.progress-line{
    position:absolute;
    height:14px;
    left:20px;
    right:20px;
    z-index:0;
    border-radius:1000px;
    margin:0 61px;
}

/* Track (gris) */
.progress-line-full{
    background: linear-gradient(180deg, #f2f2f2, #dcdcdc) !important;
    box-shadow:
        inset 0 2px 4px rgba(0,0,0,.10),
        0 1px 0 rgba(255,255,255,.70);
}

/* Fill (progreso) */
.progress-line{
    z-index:1;
    transition: width .45s cubic-bezier(.2,.8,.2,1);
    width:0; /* se pisa inline */
    border-radius:1000px;
    overflow:hidden;

    background: linear-gradient(90deg, #06C2CC, #1CE56D) !important;

    box-shadow:
        0 6px 14px rgba(6,194,204,.18),
        0 3px 10px rgba(28,229,109,.14);
}

/* Shine animado dentro del fill */
.progress-line::after{
    content:"";
    position:absolute;
    top:-18px;
    left:-35%;
    width:35%;
    height:64px;
    transform:skewX(-18deg);
    background: linear-gradient(90deg, transparent, rgba(255,255,255,.60), transparent);
    opacity:.50;
    animation: progressShine 3.1s ease-in-out infinite;
    pointer-events:none;
}

@keyframes progressShine{
    0%   { left:-35%; opacity:0; }
    12%  { opacity:.50; }
    70%  { opacity:.28; }
    100% { left:120%; opacity:0; }
}

/* Si el último es warning, puedes cambiar el gradiente del fill (opcional)
   Se activa con JS agregando .has-warning al .progress-container */
.progress-container.has-warning .progress-line{
    background: linear-gradient(90deg, #06C2CC, #FFB84D) !important;
    box-shadow:
        0 6px 14px rgba(255,184,77,.18),
        0 3px 10px rgba(6,194,204,.12);
}

/* =========================
   PASOS (CÍRCULOS)
   ========================= */
.progress-step{
    width:52px;
    height:52px;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
    z-index:2;
    flex-shrink:0;
    margin:0 35px;

    background: linear-gradient(180deg, #f0f0f0, #d8d8d8);
    color:#ffffff;
    font-size:24px;

    box-shadow:
        0 8px 18px rgba(0,0,0,.12),
        inset 0 1px 0 rgba(255,255,255,.75);

    transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
    cursor:pointer;
}

/* Brillo interno suave (NO halo afuera) */
.progress-step .fas{
    filter: drop-shadow(0 1px 0 rgba(0,0,0,.12));
}

.progress-step::after{
    content:"";
    position:absolute;
    top:8px;
    left:10px;
    width:26px;
    height:14px;
    border-radius:999px;
    background: rgba(255,255,255,.35);
    opacity:.55;
    pointer-events:none;
}

/* Hover lift */
.progress-step:hover{
    transform: translateY(2px);
    box-shadow:
        0 12px 26px rgba(0,0,0,.18),
        inset 0 1px 0 rgba(255,255,255,.78);
    filter: saturate(1.05);
}

/* Activo (verde) */
.progress-step.active{
    background: linear-gradient(180deg, #34ef7a, #10c95d);
    box-shadow:
        0 10px 22px rgba(28,229,109,.22),
        0 3px 10px rgba(0,0,0,.08),
        inset 0 1px 0 rgba(255,255,255,.35);
}

/* Warning (naranja/amarillo) */
.progress-step.warningesfera{
    background: linear-gradient(180deg, #ffc14a, #ff9700) !important;
    box-shadow:
        0 10px 22px rgba(255,151,0,.22),
        0 3px 10px rgba(0,0,0,.08),
        inset 0 1px 0 rgba(255,255,255,.35);
}

/* =========================
   PULSO NOTORIO (SOLO ÚLTIMO ACTIVO)
   Requiere clase .is-last en el último step activo/warning
   ========================= */
.progress-step.is-last{
    position:relative;
}

/* ripple 1 */
.progress-step.is-last::before{
    content:"";
    position:absolute;
    inset:0;
    border-radius:50%;
    background: inherit;
    z-index:-1;
    animation: ripplePulse 1.45s ease-out infinite;
}

/* ripple 2 (delay) */
.progress-step.is-last{
    /* ligera presencia extra sin halo fijo */
    box-shadow:
        0 12px 28px rgba(0,0,0,.18),
        inset 0 1px 0 rgba(255,255,255,.35);
}

.progress-step.is-last span,
.progress-step.is-last i{
    position:relative;
    z-index:3;
}

/* segundo pulso como “onda fantasma” */
.progress-step.is-last ._dummy{} /* no hace nada, solo evita warnings de minifier */

.progress-step.is-last::marker{ content:""; } /* no-op */

.progress-step.is-last[data-step]{} /* no-op */

/* segunda onda usando un pseudo extra con box-shadow (sin halo fijo) */
.progress-step.is-last::after{
    /* mantenemos el brillo interno, pero aquí lo reutilizamos como segunda onda:
       para no perder el highlight interno, lo recreamos dentro del ripple con mezcla */
    content:"";
    position:absolute;
    inset:0;
    border-radius:50%;
    background: inherit;
    z-index:-2;
    opacity:.0;
    animation: ripplePulse2 1.45s ease-out infinite;
    animation-delay:.42s;
}

/* pulso 1 */
@keyframes ripplePulse{
    0%{
        transform:scale(1);
        opacity:.55;
        filter:blur(0px);
    }
    70%{
        transform:scale(1.65);
        opacity:.18;
        filter:blur(2px);
    }
    100%{
        transform:scale(1.9);
        opacity:0;
        filter:blur(3px);
    }
}

/* pulso 2 (un pelo más grande y suave) */
@keyframes ripplePulse2{
    0%{
        transform:scale(1);
        opacity:.35;
        filter:blur(0px);
    }
    70%{
        transform:scale(1.85);
        opacity:.12;
        filter:blur(2.5px);
    }
    100%{
        transform:scale(2.1);
        opacity:0;
        filter:blur(3.5px);
    }
}

/* Si el usuario hace hover al último, desacelera (se siente controlado) */
.progress-step.is-last:hover::before,
.progress-step.is-last:hover::after{
    animation-duration:2.15s;
}

/* =========================
   LABELS
   ========================= */
.step-label{
    position:absolute;
    top:60px;
    width:120px;
    font-size:11.5px;
    font-weight:800;
    color:#2b2b2b;
    line-height:16px;
    text-align:center;
    white-space:normal;
    text-shadow: 0 1px 0 rgba(255,255,255,.70);
}

/* =========================
   TIP: ICONOS CUANDO NO ACTIVO
   (si quieres, puedes bajarle opacidad a los no activos)
   ========================= */
.progress-step:not(.active):not(.warningesfera){
    color:#ffffff;
    opacity:.92;
}
</style>
