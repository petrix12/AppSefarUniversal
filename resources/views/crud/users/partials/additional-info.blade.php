<div class="additional-info-section" style="text-align: center; border-bottom: #DEE2E6 solid 1px; background: rgba(0,0,0,0.05);">
    <div class="py-4">
        <h4 class="mb-4"><b>Información Adicional</b></h4>
        <div class="accordion accordion-flush" id="{{ $accordionId }}" style="max-width: 800px; margin: 0 auto;">
            @foreach($textos as $texto)
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading-{{ $accordionId }}-{{ $loop->index }}">
                        <button class="accordion-button collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#collapse-{{ $accordionId }}-{{ $loop->index }}"
                                aria-expanded="false"
                                aria-controls="collapse-{{ $accordionId }}-{{ $loop->index }}">
                            {{ $texto['nombre'] ?? 'Sin título' }}
                        </button>
                    </h2>
                    <div id="collapse-{{ $accordionId }}-{{ $loop->index }}"
                         class="accordion-collapse collapse"
                         aria-labelledby="heading-{{ $accordionId }}-{{ $loop->index }}"
                         data-bs-parent="#{{ $accordionId }}">
                        <div class="accordion-body text-start">
                            {!! $texto['texto'] ?? '' !!}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
