@php
    $context = $expedienteContext ?? [];
    $documents = $context['documents'] ?? [];
    $nextAction = $context['next_action'] ?? null;
    $responsibilities = $context['responsibilities'] ?? ['client' => [], 'sefar' => []];
    $cosEntries = collect($context['cos']['entries'] ?? []);
    $documentSupport = $context['document_support'] ?? [];
@endphp

@if($context['visible'] ?? false)
    <section class="bo-expediente-context" aria-label="Mi expediente">
        <div class="bo-expediente-head">
            <div>
                <span class="bo-section-kicker">Mi expediente</span>
                <h2>{{ $context['stage_label'] ?? 'Estatus del expediente' }}</h2>
                <p>{{ $context['summary'] ?? 'Revisamos tu contexto para recomendar el siguiente alcance.' }}</p>
            </div>
            @if($nextAction)
                <div class="bo-expediente-action bo-expediente-action-{{ $nextAction['tone'] ?? 'info' }}">
                    <span>{{ ($nextAction['owner'] ?? 'client') === 'sefar' ? 'Gestion Sefar' : 'Siguiente paso' }}</span>
                    <strong>{{ $nextAction['title'] ?? 'Continuar' }}</strong>
                    <p>{{ $nextAction['description'] ?? '' }}</p>
                    @if(!empty($nextAction['url']))
                        <a class="bo-button bo-button-primary" href="{{ $nextAction['url'] }}">
                            {{ $nextAction['label'] ?? 'Continuar' }} <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    @endif
                </div>
            @endif
        </div>

        <div class="bo-expediente-grid">
            <article class="bo-expediente-card">
                <span class="bo-expediente-card-kicker">Estatus</span>
                <strong>{{ $context['recommendation']['plan_title'] ?? 'Estrategia por definir' }}</strong>
                <p>{{ $context['recommendation']['reason'] ?? 'La recomendacion se ajusta segun tu etapa, documentos y estatus publicado.' }}</p>
            </article>

            <article class="bo-expediente-card">
                <span class="bo-expediente-card-kicker">Documentos</span>
                <strong>
                    {{ (int) ($documents['pending_count'] ?? 0) + (int) ($documents['missing_count'] ?? 0) }}
                    accion(es) detectada(s)
                </strong>
                <p>
                    {{ (int) ($documents['pending_count'] ?? 0) }} pendiente(s) por cliente,
                    {{ (int) ($documents['missing_count'] ?? 0) }} sin documento disponible.
                </p>
            </article>

            <article class="bo-expediente-card">
                <span class="bo-expediente-card-kicker">COS</span>
                <strong>{{ ($context['cos']['available'] ?? false) ? 'Disponible' : 'En revision' }}</strong>
                <p>{{ $context['cos']['summary'] ?? 'El avance publicado aparecera cuando el equipo lo active.' }}</p>
            </article>
        </div>

        <div class="bo-responsibility-grid">
            <article class="bo-responsibility-card">
                <h3><i class="fas fa-user-check" aria-hidden="true"></i> Depende de ti</h3>
                <ul>
                    @foreach($responsibilities['client'] ?? [] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </article>

            <article class="bo-responsibility-card">
                <h3><i class="fas fa-briefcase" aria-hidden="true"></i> Lo gestiona Sefar</h3>
                <ul>
                    @foreach($responsibilities['sefar'] ?? [] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </article>
        </div>

        @if(!empty($documents['pending']) || !empty($documents['missing']) || $cosEntries->isNotEmpty())
            <div class="bo-expediente-detail-grid">
                @if(!empty($documents['pending']) || !empty($documents['missing']))
                    <article class="bo-document-card">
                        <div class="bo-document-card-head">
                            <h3>Documentos que requieren atencion</h3>
                            @if($documentSupport['available'] ?? false)
                                <a href="{{ $documentSupport['url'] }}" class="bo-document-support-link">
                                    {{ $documentSupport['label'] ?? 'Ver apoyo documental' }}
                                </a>
                            @endif
                        </div>
                        <ul class="bo-document-list">
                            @foreach(collect($documents['pending'] ?? [])->merge($documents['missing'] ?? [])->take(5) as $document)
                                <li>
                                    <i class="fas {{ ($document['status'] ?? '') === 'no_documento' ? 'fa-search' : 'fa-file-upload' }}" aria-hidden="true"></i>
                                    <span>
                                        <strong>{{ $document['name'] }}</strong>
                                        <small>{{ $document['type_label'] }} · {{ $document['status_label'] }}</small>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </article>
                @endif

                @if($cosEntries->isNotEmpty())
                    <article class="bo-document-card">
                        <div class="bo-document-card-head">
                            <h3>Procesos publicados</h3>
                            <a href="{{ route('clientes.tree') }}" class="bo-document-support-link">Ver COS</a>
                        </div>
                        <ul class="bo-document-list">
                            @foreach($cosEntries->take(4) as $entry)
                                <li>
                                    <i class="fas fa-route" aria-hidden="true"></i>
                                    <span>
                                        <strong>{{ $entry['service'] }}</strong>
                                        <small>{{ $entry['current_step'] }}</small>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </article>
                @endif
            </div>
        @endif
    </section>
@endif
