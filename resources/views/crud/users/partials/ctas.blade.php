<div class="card-body text-center ctas-section" style="border-bottom: #DEE2E6 solid 1px; background: rgba(0,0,0,0.05);">
    <h2 class="card-title mb-4">
        Contrata nuestros servicios adicionales y<br>
        <b>asegura tu ciudadanía europea:</b>
    </h2>
</div>

<div class="card-body text-center ctas-section" style="border-bottom: #DEE2E6 solid 1px; background: rgba(0,0,0,0.05);">
    <div class="ctas-container d-flex flex-wrap justify-content-center gap-2">
        @foreach ($ctas as $cta)
            <a href="{{ $cta['url'] }}"
               target="_blank"
               rel="noopener noreferrer"
               class="btn btn-primary cfrSefar">
                {{ $cta['text'] }}
            </a>
        @endforeach
    </div>
</div>
