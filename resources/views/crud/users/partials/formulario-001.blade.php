@php
    $formulario001Prefill = [
        'firstname' => $user->nombres ?? '',
        'lastname' => $user->apellidos ?? '',
        'phone' => $user->phone ?? '',
        'email' => $user->email ?? '',
        'numero_de_pasaporte' => $user->passport ?? '',
        'pais_de_nacimiento' => $user->pais_de_nacimiento ?? '',
        'nacionalidad_solicitada' => $user->servicio ?? '',
    ];
@endphp

<div class="tab-pane fade" id="formulario-001" role="tabpanel" aria-labelledby="formulario-001-tab">
    <div class="alert alert-info mt-3" role="status">
        <i class="fas fa-info-circle me-1" aria-hidden="true"></i>
        Este es el Formulario 001 vigente. Se vincula al correo del solicitante mostrado en este COS.
    </div>

    <div id="formulario-001-target" class="pb-3" aria-live="polite">
        <div class="text-muted py-3">Abre la pestaña para cargar el formulario.</div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tab = document.getElementById('formulario-001-tab');
        const target = document.getElementById('formulario-001-target');

        if (!tab || !target) {
            return;
        }

        const prefill = {{ \Illuminate\Support\Js::from($formulario001Prefill) }};
        let started = false;
        let scriptLoading = false;

        function applyPrefill(scope) {
            if (!scope || !scope.querySelectorAll) {
                return;
            }

            Object.entries(prefill).forEach(function ([name, value]) {
                if (value === null || value === '') {
                    return;
                }

                scope.querySelectorAll('[name="' + name + '"]').forEach(function (field) {
                    field.value = value;
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
        }

        function prefillForm(form) {
            applyPrefill(form && form[0] ? form[0] : form);

            const iframe = target.querySelector('iframe');
            if (iframe && iframe.contentDocument) {
                applyPrefill(iframe.contentDocument);
            }
        }

        function renderForm() {
            if (!window.hbspt || !window.hbspt.forms) {
                target.innerHTML = '<div class="alert alert-danger">No se pudo cargar el Formulario 001. Recarga la página e inténtalo de nuevo.</div>';
                return;
            }

            target.innerHTML = '';
            window.hbspt.forms.create({
                region: 'na1',
                portalId: '20053496',
                formId: 'ae73e323-14a8-40f4-a20c-4a33a30aabde',
                target: '#formulario-001-target',
                onFormReady: function (form) {
                    window.setTimeout(function () {
                        prefillForm(form);
                    }, 0);
                }
            });
        }

        function loadForm() {
            if (started) {
                return;
            }

            started = true;
            target.innerHTML = '<div class="text-muted py-3">Cargando Formulario 001...</div>';

            if (window.hbspt && window.hbspt.forms) {
                renderForm();
                return;
            }

            const existingScript = document.querySelector('script[data-formulario-001-hubspot]');
            if (existingScript || scriptLoading) {
                existingScript?.addEventListener('load', renderForm, { once: true });
                return;
            }

            scriptLoading = true;
            const script = document.createElement('script');
            script.src = 'https://js.hsforms.net/forms/embed/v2.js';
            script.async = true;
            script.dataset.formulario001Hubspot = 'true';
            script.addEventListener('load', renderForm, { once: true });
            script.addEventListener('error', function () {
                target.innerHTML = '<div class="alert alert-danger">No se pudo cargar el Formulario 001. Verifica tu conexión e inténtalo de nuevo.</div>';
            }, { once: true });
            document.head.appendChild(script);
        }

        tab.addEventListener('shown.bs.tab', loadForm);
        tab.addEventListener('click', loadForm, { once: true });
    });
</script>
