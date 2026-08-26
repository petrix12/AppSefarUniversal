@if ($errors->any())
    <div class="alert alert-danger mx-3 mt-3" role="alert">
        <h5 class="alert-heading mb-2">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            No se pudo guardar el servicio
        </h5>
        <ul class="mb-0 pl-4">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
