<div class="tab-pane fade" id="formulario-001" role="tabpanel" aria-labelledby="formulario-001-tab">
    @php
        $formulario001Status = $formulario001['status'] ?? null;
        $usingDefault = $formulario001['using_default'] ?? false;
    @endphp

    @if($formulario001Status === 'missing_contact')
        <div class="alert alert-warning mt-3">El solicitante no tiene un contacto de HubSpot asociado.</div>
    @elseif($formulario001Status !== 'ok')
        <div class="alert alert-danger mt-3">No se pudieron consultar los datos del Formulario 001 en HubSpot.</div>
    @else
        @if($usingDefault)
            <div class="alert alert-secondary mt-3">
                Mostrando el Formulario 001 predeterminado porque el servicio no tiene un 001 específico configurado.
            </div>
        @endif

        <div class="alert alert-info mt-3">Datos registrados en HubSpot para los Formularios 001 de los servicios pagados.</div>

        @foreach($formulario001['forms'] as $formIndex => $form)
            <section class="card mt-3 mb-4">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <strong>{{ $form['title'] }}</strong>
                    <small class="text-muted">Servicio pagado: {{ $form['service'] }}</small>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach($form['fields'] as $field)
                            @php($fieldId = 'formulario001-' . $formIndex . '-' . $field['name'])
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="{{ $fieldId }}">{{ $field['label'] }}</label>

                                @if($field['type'] === 'file')
                                    @php($files = array_values(array_filter(array_map('trim', preg_split('/[;,\r\n]+/', $field['value'] ?? '')))))
                                    <div class="border rounded p-2 bg-light" id="{{ $fieldId }}">
                                        @forelse($files as $file)
                                            @if(filter_var($file, FILTER_VALIDATE_URL))
                                                <a class="btn btn-sm btn-outline-primary me-1 mb-1" href="{{ $file }}" target="_blank" rel="noopener noreferrer">Ver archivo {{ $loop->iteration }}</a>
                                            @else
                                                <span class="text-muted">{{ $file }}</span>
                                            @endif
                                        @empty
                                            <span class="text-muted">Sin información</span>
                                        @endforelse
                                    </div>
                                @else
                                    <input id="{{ $fieldId }}" type="text" class="form-control" value="{{ $field['value'] }}" placeholder="Sin información" readonly>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endforeach
    @endif
</div>
