@php
    $context = $cosContext ?? [];
    $entries = collect($context['entries'] ?? []);
@endphp

@if(($context['visible'] ?? false) && $entries->isNotEmpty())
    <section class="bo-cos-context" aria-label="Contexto del expediente">
        <div class="bo-cos-context-head">
            <div>
                <span class="bo-section-kicker">Estatus del expediente</span>
                <h2>COS disponible</h2>
                <p>{{ $context['summary'] ?? 'Puedes revisar el avance publicado de tu proceso.' }}</p>
            </div>
            <a class="bo-button bo-button-secondary" href="{{ route('clientes.tree') }}">
                Ver estatus <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        <div class="bo-cos-context-grid">
            @foreach($entries as $entry)
                <article class="bo-cos-context-card">
                    <strong>{{ $entry['service'] }}</strong>
                    <span>{{ $entry['current_step'] }}</span>
                    <div class="bo-cos-progress">
                        @if($entry['progress_genealogic'] !== null)
                            <small>Genealogico {{ $entry['progress_genealogic'] }}%</small>
                        @endif
                        @if($entry['progress_legal'] !== null)
                            <small>Juridico {{ $entry['progress_legal'] }}%</small>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
