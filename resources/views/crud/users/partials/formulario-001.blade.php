<div class="tab-pane fade" id="formulario-001" role="tabpanel" aria-labelledby="formulario-001-tab">
    @if(($formulario001['status'] ?? null) === 'missing_contact')
        <div class="alert alert-warning mt-3">El solicitante no tiene un contacto de HubSpot asociado.</div>
    @elseif(($formulario001['status'] ?? null) !== 'ok')
        <div class="alert alert-danger mt-3">No se pudieron consultar los datos del Formulario 001 en HubSpot.</div>
    @else
        <div class="alert alert-info mt-3">Datos registrados en HubSpot para el Formulario 001.</div>

        <div class="row g-3 pb-3">
            @foreach($formulario001['fields'] as $field)
                <div class="col-12 col-md-6">
                    <label class="form-label" for="formulario001-{{ $field['name'] }}">{{ $field['label'] }}</label>

                    @if($field['type'] === 'file')
                        @php($files = array_filter(preg_split('/[;,\r\n]+/', $field['value'] ?? '')))
                        <div class="border rounded p-2 bg-light" id="formulario001-{{ $field['name'] }}">
                            @forelse($files as $file)
                                @if(filter_var($file, FILTER_VALIDATE_URL))
                                    <a class="btn btn-sm btn-outline-primary me-1 mb-1" href="{{ $file }}" target="_blank" rel="noopener noreferrer">Ver archivo {{ $loop->iteration }}</a>
                                @else
                                    <span class="text-muted">{{ $file }}</span>
                                @endif
                            @empty
                                <span class="text-muted">Sin informacion</span>
                            @endforelse
                        </div>
                    @else
                        <input id="formulario001-{{ $field['name'] }}" type="text" class="form-control" value="{{ $field['value'] }}" placeholder="Sin informacion" readonly>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
