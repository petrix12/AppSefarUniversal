<div class="cos-workspace-navigation" data-cos-workspace>
    <nav class="cos-workspace-areas" aria-label="Áreas del expediente" data-cos-areas hidden>
        <button type="button" class="cos-area-button" data-cos-area="overview" aria-pressed="true"><i class="fas fa-stream" aria-hidden="true"></i><span>{{ $cosViewRoleId == 5 ? 'Mi estatus' : 'Estatus del solicitante' }}</span></button>
        <button type="button" class="cos-area-button" data-cos-area="profile" aria-pressed="false"><i class="far fa-user" aria-hidden="true"></i><span>Perfil</span></button>
        <button type="button" class="cos-area-button" data-cos-area="case" aria-pressed="false"><i class="far fa-folder-open" aria-hidden="true"></i><span>Expediente</span></button>
        <button type="button" class="cos-area-button" data-cos-area="finance" aria-pressed="false"><i class="fas fa-receipt" aria-hidden="true"></i><span>Finanzas</span></button>
        <button type="button" class="cos-area-button" data-cos-area="internal" aria-pressed="false"><i class="fas fa-briefcase" aria-hidden="true"></i><span>Gestión interna</span></button>
    </nav>
    <p class="cos-workspace-area-label" data-cos-area-label hidden></p>
            <ul class="nav nav-tabs" id="formTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="mystatus-tab" data-bs-toggle="tab" data-bs-target="#mystatus" type="button" role="tab" aria-controls="mystatus" aria-selected="true">
                        @if($cosViewRoleId == 5)
                        Mi Estatus
                        @else
                        Estatus de Solicitante
                        @endif
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="personal-data-tab" data-bs-toggle="tab" data-bs-target="#personal_data" type="button" role="tab" aria-controls="personal_data" aria-selected="false">
                        Datos personales
                    </button>
                </li>
                @if($canEditCos && !empty($basicCosEditor))
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="basic-edit-tab" data-bs-toggle="tab" data-bs-target="#basic-edit" type="button" role="tab" aria-controls="basic-edit" aria-selected="false">
                        Edición básica
                    </button>
                </li>
                @endif
                @if($canEditCos)
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="change-history-tab" data-bs-toggle="tab" data-bs-target="#change-history" type="button" role="tab" aria-controls="change-history" aria-selected="false">
                        Historial de cambios
                    </button>
                </li>
                @endif
                @if($cosViewRoleId !== 5)
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="formulario-001-tab" data-bs-toggle="tab" data-bs-target="#formulario-001" type="button" role="tab" aria-controls="formulario-001" aria-selected="false">
                        Formulario 001
                    </button>
                </li>
                @endif
                @if($cosViewRoleId == 1)
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="adminchangepassword-tab" data-bs-toggle="tab" data-bs-target="#adminchangepassword" type="button" role="tab" aria-controls="adminchangepassword" aria-selected="false">
                        Contraseña
                    </button>
                </li>
                @elseif($cosViewRoleId == 5)
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="mypassword-tab" data-bs-toggle="tab" data-bs-target="#mypassword" type="button" role="tab" aria-controls="mypassword" aria-selected="false">
                        Cambiar mi Contraseña
                    </button>
                </li>
                @endif
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="familiars-tab" data-bs-toggle="tab" data-bs-target="#familiars" type="button" role="tab" aria-controls="familiars" aria-selected="false">
                        Familiares registrados
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab" aria-controls="payments" aria-selected="false">
                        Pagos realizados
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="paymentspen-tab" data-bs-toggle="tab" data-bs-target="#paymentspen" type="button" role="tab" aria-controls="paymentspen" aria-selected="false">
                        Deuda pendiente
                    </button>
                </li>
                @if($cosViewRoleId == 5)
                <li class="nav-item">
                    <button class="nav-link" id="client-req-tab"
                            data-bs-toggle="tab" data-bs-target="#client-req"
                            type="button" role="tab" aria-controls="client-req" aria-selected="false">
                        Mis solicitudes de documentos
                    </button>
                </li>
                @else
                {{-- === TAB para ADMIN === --}}
                <li class="nav-item">
                    <button class="nav-link" id="admin-req-tab"
                            data-bs-toggle="tab" data-bs-target="#admin-req"
                            type="button" role="tab" aria-controls="admin-req" aria-selected="false">
                        Solicitudes de documentos
                    </button>
                </li>
                @endif
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab" aria-controls="documents" aria-selected="false">
                        Archivos Cargados
                    </button>
                </li>
                @if($cosViewRoleId == 1 || $cosViewRoleId == 2)
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="etiquetado-tab" data-bs-toggle="tab" data-bs-target="#etiquetado" type="button" role="tab" aria-controls="etiquetado" aria-selected="false">
                        Etiquetado
                    </button>
                </li>
                @endif
                @if($cosViewRoleId == 17 || $cosViewRoleId == 1 || $cosViewRoleId == 4 || $cosViewRoleId == 16 || $cosViewRoleId == 15)
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="negocios-tab" data-bs-toggle="tab" data-bs-target="#negocios" type="button" role="tab" aria-controls="negocios" aria-selected="false">
                        Negocios
                    </button>
                </li>
                @endif
                @if($cosViewRoleId != 5)
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="teamleader-migration-tab" data-bs-toggle="tab" data-bs-target="#teamleader-migration" type="button" role="tab" aria-controls="teamleader-migration" aria-selected="false">
                        Teamleader
                        @if(($teamleaderMigration['contact'] ?? null))
                            <span class="badge bg-secondary ms-1">{{ ($teamleaderMigration['summary']['deals'] ?? 0) + ($teamleaderMigration['summary']['projects'] ?? 0) + ($teamleaderMigration['summary']['invoices'] ?? 0) }}</span>
                        @endif
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="client-tasks-tab" data-bs-toggle="tab" data-bs-target="#client-tasks" type="button" role="tab" aria-controls="client-tasks" aria-selected="false">
                        Tareas
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="client-chat-tab" data-bs-toggle="tab" data-bs-target="#client-chat" type="button" role="tab" aria-controls="client-chat" aria-selected="false">
                        Chat interno
                        <span id="clientInternalChatBadge" class="badge bg-danger ms-1 d-none">0</span>
                    </button>
                </li>
                @if(!auth()->user()->hasRole('Cliente') && $user->hasRole('Cliente'))
                <li class="nav-item">
                    <a class="nav-link" id="client-notes-link" href="{{ route('crud.users.notes.index', $user) }}">
                        Notas
                    </a>
                </li>
                @endif
                @endif
            </ul>
</div>
