@extends('adminlte::page')

@section('title', 'MCP privado')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <h1>MCP privado</h1>
        @include('admin.integrations._tabs')
    </div>
@stop

@section('content')
    @php
        $mcpEndpoint = url('/mcp');
        $restEndpoint = url('/api/mcp/v1');
        $createdToken = session('created_token');
    @endphp

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @include('admin.integrations._created-token')

    <div class="row">
        <div class="col-lg-4">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-plus-circle mr-1"></i> Crear token MCP
                    </h3>
                </div>
                <form method="POST" action="{{ route('admin.integrations.mcp.tokens.store') }}">
                    @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <label for="user_id">Usuario interno</label>
                            <select name="user_id" id="user_id" class="form-control" required>
                                <option value="">Seleccionar usuario</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>
                                        {{ $user->name }} - {{ $user->email }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="name">Nombre del token</label>
                            <input type="text" name="name" id="name" class="form-control" maxlength="80" value="{{ old('name') }}" placeholder="MCP privado - integracion">
                        </div>

                        <div class="callout callout-info mb-0">
                            <strong>Permiso:</strong> <code>mcp:read</code>
                            <div class="small mt-1">Solo usuarios internos. Los usuarios con rol Cliente quedan excluidos.</div>
                        </div>
                    </div>
                    <div class="card-footer text-right">
                        <button class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Crear token MCP
                        </button>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-link mr-1"></i> Endpoint
                    </h3>
                </div>
                <div class="card-body">
                    <label class="small text-muted mb-1">MCP remoto para Codex</label>
                    <div class="integration-endpoint mb-3" data-mcp-endpoint>{{ $mcpEndpoint }}</div>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-copy-value="{{ $mcpEndpoint }}">
                        <i class="fas fa-copy mr-1"></i> Copiar endpoint MCP
                    </button>
                    <hr>
                    <label class="small text-muted mb-1">API REST privada</label>
                    <div class="integration-endpoint integration-endpoint-muted">{{ $restEndpoint }}</div>
                    <div class="small text-muted mt-2">La API REST se mantiene para integraciones internas; Codex usa el endpoint MCP.</div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-desktop mr-1"></i> Configurar Codex Desktop
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-7">
                            <div class="form-group">
                                <label for="codex_mcp_token">Token MCP</label>
                                <textarea id="codex_mcp_token" class="form-control integration-token-input" rows="3" autocomplete="off" spellcheck="false" data-codex-token placeholder="Pega aqui el token MCP que acabas de crear">{{ $createdToken }}</textarea>
                                <div class="small text-muted mt-1">El token se usa solo en tu navegador para probar o generar el instalador. No se envia al servidor salvo cuando presionas Probar conexion.</div>
                            </div>

                            <div class="d-flex flex-wrap align-items-center integration-actions">
                                <button type="button" class="btn btn-primary" data-download-codex-installer>
                                    <i class="fas fa-download mr-1"></i> Descargar instalador Windows
                                </button>
                                <button type="button" class="btn btn-outline-primary" data-test-mcp>
                                    <i class="fas fa-plug mr-1"></i> Probar conexion
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-copy-toml>
                                    <i class="fas fa-copy mr-1"></i> Copiar TOML
                                </button>
                            </div>

                            <div class="integration-status mt-3 d-none" data-mcp-status></div>
                        </div>

                        <div class="col-md-5">
                            <div class="integration-codex-preview">
                                <div class="integration-preview-header">
                                    <span>config.toml</span>
                                    <span class="badge badge-primary">Codex</span>
                                </div>
                                <pre data-codex-toml>[mcp_servers.sefar]
url = "{{ $mcpEndpoint }}"
bearer_token_env_var = "SEFAR_MCP_TOKEN"
tool_timeout_sec = 120</pre>
                            </div>
                            <div class="small text-muted mt-2">Despues de ejecutar el instalador, cierra y abre Codex Desktop para que lea la variable del token.</div>
                        </div>
                    </div>
                </div>
            </div>

            @include('admin.integrations._tokens-table', ['tokens' => $tokens])
        </div>
    </div>
@stop

@section('css')
    <style>
        .integration-endpoint {
            border: 1px solid var(--sefar-border);
            border-radius: var(--sefar-radius);
            background: var(--sefar-primary-soft);
            color: var(--sefar-text);
            font-family: monospace;
            padding: .75rem;
            word-break: break-word;
        }

        .integration-endpoint-muted {
            background: var(--sefar-surface);
        }

        .integration-token-input,
        .integration-codex-preview pre {
            border: 1px solid var(--sefar-border);
            border-radius: var(--sefar-radius);
            color: var(--sefar-text);
            font-family: Consolas, Monaco, monospace;
        }

        .integration-token-input {
            min-height: 92px;
            resize: vertical;
        }

        .integration-actions {
            gap: .5rem;
        }

        .integration-codex-preview {
            border: 1px solid var(--sefar-border);
            border-radius: var(--sefar-radius);
            background: var(--sefar-surface);
            overflow: hidden;
        }

        .integration-preview-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--sefar-border);
            background: var(--sefar-primary-soft);
            color: var(--sefar-text);
            padding: .65rem .8rem;
            font-weight: 600;
        }

        .integration-codex-preview pre {
            border: 0;
            margin: 0;
            background: transparent;
            min-height: 132px;
            padding: .85rem;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .integration-status {
            border: 1px solid var(--sefar-border);
            border-radius: var(--sefar-radius);
            padding: .75rem;
        }

        .integration-status.is-ok {
            background: rgba(40, 167, 69, .08);
            border-color: rgba(40, 167, 69, .35);
        }

        .integration-status.is-error {
            background: rgba(172, 38, 48, .08);
            border-color: rgba(172, 38, 48, .35);
        }
    </style>
@stop

@section('js')
    <script>
        (function () {
            const endpoint = @json($mcpEndpoint);
            const tokenInput = document.querySelector('[data-codex-token]');
            const statusBox = document.querySelector('[data-mcp-status]');
            const tomlPreview = document.querySelector('[data-codex-toml]');

            function token() {
                return (tokenInput?.value || '').trim();
            }

            function toml() {
                return [
                    '[mcp_servers.sefar]',
                    'url = "' + endpoint + '"',
                    'bearer_token_env_var = "SEFAR_MCP_TOKEN"',
                    'tool_timeout_sec = 120',
                    '',
                    '[mcp_servers.sefar.tools.refrescar_cos_cliente]',
                    'approval_mode = "prompt"',
                ].join('\n');
            }

            function setStatus(kind, message) {
                if (! statusBox) return;

                statusBox.classList.remove('d-none', 'is-ok', 'is-error');
                statusBox.classList.add(kind === 'ok' ? 'is-ok' : 'is-error');
                statusBox.textContent = message;
            }

            function copyText(value, okMessage) {
                if (! value) return;

                if (navigator.clipboard) {
                    navigator.clipboard.writeText(value).then(function () {
                        setStatus('ok', okMessage || 'Copiado.');
                    }).catch(function () {
                        fallbackCopy(value, okMessage);
                    });
                    return;
                }

                fallbackCopy(value, okMessage);
            }

            function fallbackCopy(value, okMessage) {
                const textarea = document.createElement('textarea');
                textarea.value = value;
                textarea.setAttribute('readonly', 'readonly');
                textarea.style.position = 'fixed';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                setStatus('ok', okMessage || 'Copiado.');
            }

            function encodeUtf16LeBase64(value) {
                const bytes = [];

                for (let i = 0; i < value.length; i++) {
                    const code = value.charCodeAt(i);
                    bytes.push(code & 0xff, code >> 8);
                }

                let binary = '';
                const chunkSize = 0x8000;

                for (let i = 0; i < bytes.length; i += chunkSize) {
                    binary += String.fromCharCode.apply(null, bytes.slice(i, i + chunkSize));
                }

                return btoa(binary);
            }

            function windowsInstaller() {
                const currentToken = token();

                if (! currentToken) {
                    throw new Error('Pega el token MCP antes de generar el instalador.');
                }

                if (/[\r\n]/.test(currentToken)) {
                    throw new Error('El token no debe tener saltos de linea.');
                }

                const ps = [
                    "$ErrorActionPreference = 'Stop'",
                    "$token = @'",
                    currentToken,
                    "'@",
                    "$endpoint = @'",
                    endpoint,
                    "'@",
                    "[Environment]::SetEnvironmentVariable('SEFAR_MCP_TOKEN', $token.Trim(), 'User')",
                    "$dir = Join-Path $env:USERPROFILE '.codex'",
                    "New-Item -ItemType Directory -Force -Path $dir | Out-Null",
                    "$path = Join-Path $dir 'config.toml'",
                    "$block = @\"",
                    '# BEGIN SEFAR MCP',
                    '[mcp_servers.sefar]',
                    'url = "' + endpoint + '"',
                    'bearer_token_env_var = "SEFAR_MCP_TOKEN"',
                    'tool_timeout_sec = 120',
                    '',
                    '[mcp_servers.sefar.tools.refrescar_cos_cliente]',
                    'approval_mode = "prompt"',
                    '# END SEFAR MCP',
                    '"@',
                    "$content = if (Test-Path -LiteralPath $path) { Get-Content -Raw -LiteralPath $path } else { '' }",
                    "$content = [regex]::Replace($content, '(?ms)^# BEGIN SEFAR MCP.*?# END SEFAR MCP\\r?\\n?', '')",
                    "Set-Content -LiteralPath $path -Encoding UTF8 -Value ($content.TrimEnd() + [Environment]::NewLine + [Environment]::NewLine + $block + [Environment]::NewLine)",
                    "Write-Host ''",
                    "Write-Host 'Sefar MCP configurado en Codex.'",
                    "Write-Host 'Cierra y abre Codex Desktop para cargar SEFAR_MCP_TOKEN.'",
                ].join('\r\n');

                return [
                    '@echo off',
                    'title Sefar MCP para Codex',
                    'powershell -NoProfile -ExecutionPolicy Bypass -EncodedCommand ' + encodeUtf16LeBase64(ps),
                    'echo.',
                    'pause',
                    '',
                ].join('\r\n');
            }

            document.querySelectorAll('[data-copy-token]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const input = document.querySelector('[data-created-token]');
                    if (! input) return;

                    input.select();
                    copyText(input.value, 'Token copiado.');
                });
            });

            document.querySelectorAll('[data-copy-value]').forEach(function (button) {
                button.addEventListener('click', function () {
                    copyText(button.getAttribute('data-copy-value'), 'Endpoint copiado.');
                });
            });

            document.querySelector('[data-copy-toml]')?.addEventListener('click', function () {
                copyText(toml(), 'Configuracion TOML copiada.');
            });

            document.querySelector('[data-download-codex-installer]')?.addEventListener('click', function () {
                try {
                    const blob = new Blob([windowsInstaller()], { type: 'application/x-msdownload' });
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = 'instalar-sefar-mcp-codex.cmd';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(link.href);
                    setStatus('ok', 'Instalador descargado. Ejecutalo una vez y reinicia Codex Desktop.');
                } catch (error) {
                    setStatus('error', error.message || 'No se pudo generar el instalador.');
                }
            });

            document.querySelector('[data-test-mcp]')?.addEventListener('click', async function () {
                const currentToken = token();

                if (! currentToken) {
                    setStatus('error', 'Pega el token MCP antes de probar la conexion.');
                    return;
                }

                setStatus('ok', 'Probando conexion MCP...');

                try {
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Authorization': 'Bearer ' + currentToken,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json, text/event-stream',
                            'MCP-Protocol-Version': '2026-07-28',
                            'Mcp-Method': 'tools/list',
                        },
                        body: JSON.stringify({
                            jsonrpc: '2.0',
                            id: Date.now(),
                            method: 'tools/list',
                            params: {
                                _meta: {
                                    'io.modelcontextprotocol/protocolVersion': '2026-07-28',
                                    'io.modelcontextprotocol/clientInfo': {
                                        name: 'sefar-adminlte-test',
                                        version: '1.0.0',
                                    },
                                    'io.modelcontextprotocol/clientCapabilities': {},
                                },
                            },
                        }),
                    });

                    const data = await response.json();

                    if (! response.ok || data.error) {
                        throw new Error(data.error?.message || 'El endpoint respondio con error HTTP ' + response.status + '.');
                    }

                    const count = data.result?.tools?.length || 0;
                    setStatus('ok', 'Conexion MCP lista. Herramientas disponibles: ' + count + '.');
                } catch (error) {
                    setStatus('error', error.message || 'No se pudo probar la conexion MCP.');
                }
            });

            if (tomlPreview) {
                tomlPreview.textContent = toml();
            }
        })();
    </script>
@stop
