<div class="carousel-wrapper" style="text-align: center; border-bottom: #DEE2E6 solid 1px; height: 220px; overflow: hidden;">
    <div id="{{ $carouselId }}" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">
            @foreach($imageUrls as $url)
                <div class="carousel-item {{ $loop->first ? 'active' : '' }}">
                    <img class="d-block w-100"
                         src="{{ $url }}"
                         alt="Imagen {{ $loop->iteration }}"
                         style="object-fit: cover; height: 220px; width: 100%;"
                         loading="lazy">
                </div>
            @endforeach
        </div>
    </div>
</div>
