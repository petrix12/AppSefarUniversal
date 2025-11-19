@extends('adminlte::page')

@section('title', 'WhatsApp Bot URL - Configuración')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>
            <i class="fab fa-whatsapp text-success"></i> Configuración de URL del Bot
        </h1>
    </div>
@stop

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8 col-md-10 mx-auto">

            <!-- Card Principal -->
            <div class="card card-success card-outline shadow-lg">
                <div class="card-header bg-success">
                    <h3 class="card-title">
                        <i class="fas fa-cog"></i> Endpoint de Envío de Mensajes
                    </h3>
                </div>

                <div class="card-body">
                    <!-- Alert de Éxito -->
                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle mr-3"></i>
                                <div>
                                    <strong>¡Éxito!</strong>
                                    <p class="mb-0">{{ session('success') }}</p>
                                </div>
                            </div>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    <!-- Alertas de Error -->
                    @if ($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-exclamation-triangle mr-3 mt-1"></i>
                                <div class="flex-grow-1">
                                    <strong>Errores de Validación</strong>
                                    <ul class="mb-0 mt-2 pl-3">
                                        @foreach ($errors->all() as $error)
                                            <li class="small">{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    <!-- Descripción -->
                    <div class="alert alert-info alert-dismissible">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Información:</strong> Define la URL base para la comunicación con tu servicio de mensajería de WhatsApp.
                        Esta configuración se almacena usando el patrón <strong>Singleton</strong>.
                    </div>

                    <!-- Formulario -->
                    <form method="POST" action="{{ route('whatsapp.url.store') }}" class="needs-validation" novalidate>
                        @csrf

                        <div class="form-group">
                            <label for="url" class="font-weight-bold text-dark">
                                <i class="fas fa-link text-success mr-2"></i>URL Base del Bot de WhatsApp
                            </label>

                            <div class="input-group input-group-lg">
                                <input
                                    type="url"
                                    id="url"
                                    name="url"
                                    value="{{ old('url', $urlRecord->url ?? '') }}"
                                    placeholder="https://api.tudominio.com/webhook"
                                    required
                                    class="form-control form-control-lg @error('url') is-invalid @enderror"
                                >
                                <div class="input-group-append">
                                    <span class="input-group-text font-weight-bold">/send</span>
                                </div>
                                @error('url')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <small class="form-text text-muted d-block mt-3">
                                <i class="fas fa-lightbulb text-warning mr-2"></i>
                                <strong>Endpoint completo:</strong> <code class="bg-light p-2 rounded">{{ old('url', $urlRecord->url ?? 'URL_INGRESADA') }}/send</code>
                            </small>

                            <small class="form-text text-muted d-block mt-2">
                                <i class="fas fa-check text-success mr-2"></i>
                                Asegúrate de que la URL sea accesible y valide el protocolo HTTPS en producción.
                            </small>
                        </div>

                        <!-- Divider -->
                        <hr class="my-4">

                        <!-- Botones -->
                        <div class="form-group d-flex gap-2">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-save mr-2"></i> Guardar Configuración
                            </button>
                            <button type="reset" class="btn btn-secondary btn-lg">
                                <i class="fas fa-redo mr-2"></i> Limpiar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
@stop

@section('css')
    <!-- Asegúrate de tener Font Awesome si usas íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">

    <style>
        .card-header {
            border-bottom: 2px solid rgba(0,0,0,.125);
        }

        .input-group-text {
            background-color: #f8f9fa;
            border-color: #ced4da;
        }

        code {
            font-size: 0.875em;
            word-break: break-word;
        }

        .btn-lg {
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
        }

        .gap-2 {
            gap: 0.5rem;
        }

        .alert {
            border-radius: 0.25rem;
        }

        .form-control-lg {
            font-size: 1rem;
            padding: 0.75rem 1rem;
        }

        .alert-dismissible .close {
            padding: 0.5rem 0.75rem;
        }

        /* Mejoras de Responsividad */
        @media (max-width: 768px) {
            .form-group d-flex {
                flex-direction: column;
            }

            .btn-lg {
                width: 100%;
            }
        }
    </style>
@stop

@section('js')
    <script>
        // Validación Bootstrap
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                const forms = document.querySelectorAll('.needs-validation');
                Array.prototype.slice.call(forms).forEach(function(form) {
                    form.addEventListener('submit', function(event) {
                        if (!form.checkValidity()) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            });
        })();

        // Auto-close alertas
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    </script>
@stop
