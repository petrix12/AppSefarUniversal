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
    // (respetando reglas de Carta Naturaleza y Certificado descargado)
    // ==========================================
    $ultimoActivoGen = null;

    foreach (($cos[$proceso['servicio']]['genealogico'] ?? []) as $s) {
        $esElUltimoPaso = ($s['paso'] == $totalPasosGen);

        if ($esCartaNaturaleza) {
            $isActiveTmp = ($currentGen + 1) >= $s['paso'];
        } elseif ($esElUltimoPaso) {
            // Certificado: verde solo si descargado
            $certDescargado = ($proceso['certificadoDescargado'] ?? 1) == 0;
            $isActiveTmp = (($currentGen + 1) >= $s['paso']) && $certDescargado;
        } else {
            $isActiveTmp = ($currentGen + 1) >= $s['paso'];
        }

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
                $esElUltimoPaso = ($step['paso'] == $totalPasosGen);

                // ========== CALCULAR SI ESTÁ ACTIVO ==========
                if ($esCartaNaturaleza) {
                    $isActive = ($currentGen + 1) >= $step['paso'];
                } elseif ($esElUltimoPaso) {
                    // Certificado: verde solo si descargado
                    $certDescargado = ($proceso['certificadoDescargado'] ?? 1) == 0;
                    $isActive = ($currentGen + 1) >= $step['paso'] && $certDescargado;
                } else {
                    $isActive = ($currentGen + 1) >= $step['paso'];
                }

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

                @if($isActive)
                    data-nombre="{{ $step['nombre_corto'] }}"
                    data-descripcion="{{ $step['promesa_pasado'] ?? '' }}"
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

                @if($isActive)
                    data-nombre="{{ $step['nombre_corto'] }}"
                    data-descripcion="{{ isset($step['promesa_pasado']) && trim($step['promesa_pasado'])!="" ? $step['promesa_pasado'] : $step['promesa'] }}"
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
