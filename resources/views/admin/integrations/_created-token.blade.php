@if(session('created_token'))
    <div class="alert alert-warning">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div class="mb-2 mb-md-0">
                <strong>Token creado:</strong>
                <span>{{ session('created_token_name') }}</span>
                <div class="small mt-1">Copialo ahora; no se volvera a mostrar completo.</div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-dark" data-copy-token>
                <i class="fas fa-copy mr-1"></i> Copiar
            </button>
        </div>
        <input type="text" class="form-control mt-3 font-monospace" readonly value="{{ session('created_token') }}" data-created-token>
    </div>
@endif
