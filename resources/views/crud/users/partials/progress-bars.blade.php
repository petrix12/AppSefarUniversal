{{-- Determinar tipo de servicio --}}
@php
    $esCartaNaturaleza = in_array($proceso['servicio'], [
        'Española - Carta de Naturaleza General',
        'Nacionalidad por Carta de Naturaleza'
    ]);

    $totalPasosGen = count($cos[$proceso['servicio']]['genealogico'] ?? []);
@endphp

{{-- Progreso Genealógico --}}
<h4 class="mb-4 mt-4"><b>Progreso Genealógico</b></h4>
<div class="progress-scroll-container mb-4">
    <div class="progress-container" id="progressContainerGen-{{ $index }}">
        <div class="progress-line-full"></div>
        <div class="progress-line" style="width: {{ $proceso['progressPercentageGen'] ?? 0 }}%;"></div>

        @foreach ($cos[$proceso['servicio']]['genealogico'] ?? [] as $step)
            @php
                $currentGen = $proceso['currentStepGen'] ?? -1;

                if ($esCartaNaturaleza) {
                    // Carta de Naturaleza: sin lógica de certificado
                    $isActive = ($currentGen + 1) >= $step['paso'];
                    $totalPasoActual = $currentGen + ($proceso['currentStepJur'] ?? -1) + 1;
                } else {
                    // Otros servicios: el ÚLTIMO paso es el certificado
                    $esElUltimoPaso = ($step['paso'] == $totalPasosGen);

                    if ($esElUltimoPaso) {
                        // Este es el paso del certificado
                        // Solo verde si certificadoDescargado == 0 (descargado)
                        $certDescargado = ($proceso['certificadoDescargado'] ?? 1) == 0;
                        $isActive = ($currentGen + 1) >= $step['paso'] && $certDescargado;
                    } else {
                        // Cualquier otro paso genealógico
                        $isActive = ($currentGen + 1) >= $step['paso'];
                    }

                    $totalPasoActual = $currentGen + ($proceso['certificadoDescargado'] ?? 0) + ($proceso['currentStepJur'] ?? -1) + 1;
                }

                $isWarning = isset($proceso['warning']) && $totalPasoActual == $step['paso'];
            @endphp

            <div class="progress-step {{ $isActive ? ($isWarning ? 'warningesfera' : 'active') : '' }}"
                 data-step="{{ $step['paso'] }}">
                <i class="fas fa-{{ $isActive ? ($isWarning ? 'exclamation' : 'check-circle') : 'check-circle' }}"></i>
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
        <div class="progress-line" style="width: {{ $proceso['progressPercentageJur'] ?? 0 }}%;"></div>

        @foreach ($cos[$proceso['servicio']]['juridico'] ?? [] as $step)
            @php
                $currentGen = $proceso['currentStepGen'] ?? -1;
                $currentJur = $proceso['currentStepJur'] ?? -1;

                if ($esCartaNaturaleza) {
                    $totalPasoActual = $currentGen + $currentJur + 1;
                } else {
                    $totalPasoActual = $currentGen + ($proceso['certificadoDescargado'] ?? 0) + $currentJur + 1;
                }

                $isActive = $totalPasoActual >= $step['paso'];
                $isWarning = isset($proceso['warning']) && $totalPasoActual == $step['paso'];
            @endphp

            <div class="progress-step {{ $isActive ? ($isWarning ? 'warningesfera' : 'active') : '' }}">
                <i class="fas fa-{{ $isActive ? ($isWarning ? 'exclamation' : 'check-circle') : 'check-circle' }}"></i>
                <span class="step-label">{{ $step['nombre_corto'] }}</span>
            </div>
        @endforeach
    </div>
</div>
