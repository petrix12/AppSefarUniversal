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

                        <div class="integration-note">
                            <i class="fas fa-shield-alt mr-1"></i> Permiso <code>mcp:read</code>. Solo usuarios internos.
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
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <strong><i class="fas fa-link mr-1"></i> Endpoint MCP</strong>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-copy-value="{{ $mcpEndpoint }}" title="Copiar endpoint">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="integration-endpoint" data-mcp-endpoint>{{ $mcpEndpoint }}</div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-desktop mr-1"></i> Configurar clientes IA
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-7">
                            <div class="form-group">
                                <label for="codex_mcp_token">Token MCP</label>
                                <textarea id="codex_mcp_token" class="form-control integration-token-input" rows="2" autocomplete="off" spellcheck="false" data-codex-token data-mcp-token placeholder="Pega aqui el token MCP">{{ $createdToken }}</textarea>
                            </div>

                            <div class="d-flex flex-wrap align-items-center integration-actions">
                                <button type="button" class="btn btn-primary" data-download-codex-installer>
                                    <i class="fas fa-download mr-1"></i> Codex Windows
                                </button>
                                <button type="button" class="btn btn-outline-primary" data-test-mcp>
                                    <i class="fas fa-plug mr-1"></i> Probar
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-copy-toml>
                                    <i class="fas fa-copy mr-1"></i> TOML
                                </button>
                            </div>

                            <div class="integration-section-label mt-4 mb-2">Descargar configuracion</div>

                            <div class="integration-ai-grid">
                                <button type="button" class="integration-ai-tile" data-download-ai-config="vscode" style="--app-color: #007acc;">
                                    <span class="integration-app-logo"><i class="fas fa-code"></i></span>
                                    <span>
                                        <span class="integration-app-name">VS Code</span>
                                        <span class="integration-app-meta">Copilot</span>
                                    </span>
                                </button>
                                <button type="button" class="integration-ai-tile" data-download-ai-config="copilot-cli" style="--app-color: #24292f;">
                                    <span class="integration-app-logo"><i class="fab fa-github"></i></span>
                                    <span>
                                        <span class="integration-app-name">Copilot</span>
                                        <span class="integration-app-meta">CLI</span>
                                    </span>
                                </button>
                                <button type="button" class="integration-ai-tile" data-download-ai-config="cursor" style="--app-color: #111111;">
                                    <span class="integration-app-logo"><i class="fas fa-mouse-pointer"></i></span>
                                    <span>
                                        <span class="integration-app-name">Cursor</span>
                                        <span class="integration-app-meta">mcp.json</span>
                                    </span>
                                </button>
                                <button type="button" class="integration-ai-tile" data-download-ai-config="claude" style="--app-color: #d97757;">
                                    <span class="integration-app-logo"><i class="fas fa-terminal"></i></span>
                                    <span>
                                        <span class="integration-app-name">Claude</span>
                                        <span class="integration-app-meta">Code</span>
                                    </span>
                                </button>
                                <button type="button" class="integration-ai-tile" data-download-ai-config="windsurf" style="--app-color: #00a7c8;">
                                    <span class="integration-app-logo"><i class="fas fa-wind"></i></span>
                                    <span>
                                        <span class="integration-app-name">Windsurf</span>
                                        <span class="integration-app-meta">MCP</span>
                                    </span>
                                </button>
                                <button type="button" class="integration-ai-tile" data-download-ai-config="opencode" style="--app-color: #111827;">
                                    <span class="integration-app-logo">&lt;/&gt;</span>
                                    <span>
                                        <span class="integration-app-name">OpenCode</span>
                                        <span class="integration-app-meta">remote</span>
                                    </span>
                                </button>
                                <button type="button" class="integration-ai-tile" data-download-ai-config="openclaw" style="--app-color: #7c3aed;">
                                    <span class="integration-app-logo">OC</span>
                                    <span>
                                        <span class="integration-app-name">OpenClaw</span>
                                        <span class="integration-app-meta">HTTP</span>
                                    </span>
                                </button>
                                <button type="button" class="integration-ai-tile" data-download-ai-config="generic" style="--app-color: #6c757d;">
                                    <span class="integration-app-logo"><i class="fas fa-plug"></i></span>
                                    <span>
                                        <span class="integration-app-name">Generico</span>
                                        <span class="integration-app-meta">MCP HTTP</span>
                                    </span>
                                </button>
                            </div>

                            <div class="integration-note mt-3">
                                <i class="fas fa-lock mr-1"></i> Los archivos incluyen el token. No los subas a Git.
                            </div>

                            <div class="integration-status mt-3 d-none" data-mcp-status></div>
                        </div>

                        <div class="col-md-5">
                            <button class="integration-preview-toggle" type="button" data-toggle="collapse" data-target="#mcp_config_preview" aria-expanded="false" aria-controls="mcp_config_preview">
                                <span><i class="fas fa-file-code mr-1"></i> Vista previa</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>

                            <div class="collapse mt-2" id="mcp_config_preview">
                                <div class="integration-codex-preview">
                                    <div class="integration-preview-header">
                                        <span data-preview-title>config.toml</span>
                                        <span class="badge badge-primary" data-preview-badge>Codex</span>
                                    </div>
                                    <pre data-codex-toml>[mcp_servers.sefar]
url = "{{ $mcpEndpoint }}"
bearer_token_env_var = "SEFAR_MCP_TOKEN"
tool_timeout_sec = 120</pre>
                                </div>
                            </div>
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
            background: var(--sefar-surface);
            color: var(--sefar-text);
            font-family: monospace;
            font-size: .86rem;
            padding: .6rem .7rem;
            word-break: break-word;
        }

        .integration-token-input,
        .integration-codex-preview pre {
            border: 1px solid var(--sefar-border);
            border-radius: var(--sefar-radius);
            color: var(--sefar-text);
            font-family: Consolas, Monaco, monospace;
        }

        .integration-token-input {
            min-height: 68px;
            resize: vertical;
        }

        .integration-actions {
            gap: .5rem;
        }

        .integration-note {
            border: 1px solid var(--sefar-border);
            border-radius: var(--sefar-radius);
            background: var(--sefar-surface);
            color: var(--sefar-muted);
            font-size: .86rem;
            padding: .55rem .65rem;
        }

        .integration-section-label {
            color: var(--sefar-muted);
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .integration-ai-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));
            gap: .65rem;
        }

        .integration-ai-tile {
            display: flex;
            align-items: center;
            width: 100%;
            min-height: 64px;
            border: 1px solid var(--sefar-border);
            border-radius: var(--sefar-radius);
            background: var(--sefar-surface);
            color: var(--sefar-text);
            padding: .65rem;
            text-align: left;
            transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
        }

        .integration-ai-tile:hover,
        .integration-ai-tile:focus {
            border-color: var(--app-color);
            box-shadow: inset 3px 0 0 var(--app-color), 0 6px 14px rgba(0, 0, 0, .08);
            color: var(--sefar-text);
            outline: 0;
            transform: translateY(-1px);
        }

        .integration-app-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 36px;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: var(--app-color);
            color: #fff;
            font-size: .85rem;
            font-weight: 700;
            margin-right: .65rem;
        }

        .integration-app-name,
        .integration-app-meta {
            display: block;
            line-height: 1.15;
        }

        .integration-app-name {
            font-weight: 700;
        }

        .integration-app-meta {
            color: var(--sefar-muted);
            font-size: .76rem;
            margin-top: .15rem;
        }

        .integration-codex-preview {
            border: 1px solid var(--sefar-border);
            border-radius: var(--sefar-radius);
            background: var(--sefar-surface);
            overflow: hidden;
        }

        .integration-preview-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            min-height: 42px;
            border: 1px solid var(--sefar-border);
            border-radius: var(--sefar-radius);
            background: var(--sefar-surface);
            color: var(--sefar-text);
            padding: .6rem .75rem;
            font-weight: 600;
        }

        .integration-preview-toggle:hover,
        .integration-preview-toggle:focus {
            border-color: var(--sefar-primary);
            color: var(--sefar-text);
            outline: 0;
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
            const previewTitle = document.querySelector('[data-preview-title]');
            const previewBadge = document.querySelector('[data-preview-badge]');

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

            function configToken() {
                const currentToken = token();

                if (! currentToken) {
                    throw new Error('Pega el token MCP antes de generar la configuracion.');
                }

                if (/[\r\n]/.test(currentToken)) {
                    throw new Error('El token no debe tener saltos de linea.');
                }

                return currentToken;
            }

            function authHeaders() {
                return {
                    Authorization: 'Bearer ' + configToken(),
                };
            }

            function prettyJson(value) {
                return JSON.stringify(value, null, 2);
            }

            function setPreview(title, badge, color) {
                if (previewTitle) {
                    previewTitle.textContent = title;
                }

                if (previewBadge) {
                    previewBadge.textContent = badge;
                    previewBadge.style.backgroundColor = color || '';
                }
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

            function downloadText(filename, content, type) {
                const blob = new Blob([content], { type: type || 'text/plain' });
                const link = document.createElement('a');

                link.href = URL.createObjectURL(blob);
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href);
            }

            function aiConfig(kind) {
                const headers = authHeaders();
                const configs = {
                    'vscode': {
                        label: 'VS Code / Copilot',
                        color: '#007acc',
                        filename: 'sefar-vscode-copilot-mcp.json',
                        content: prettyJson({
                            servers: {
                                sefar: {
                                    type: 'http',
                                    url: endpoint,
                                    headers: headers,
                                },
                            },
                        }),
                        message: 'Configuracion VS Code / Copilot descargada.',
                    },
                    'copilot-cli': {
                        label: 'Copilot CLI',
                        color: '#24292f',
                        filename: 'sefar-copilot-mcp-config.json',
                        content: prettyJson({
                            mcpServers: {
                                sefar: {
                                    type: 'http',
                                    url: endpoint,
                                    headers: headers,
                                    tools: ['*'],
                                },
                            },
                        }),
                        message: 'Configuracion Copilot CLI descargada.',
                    },
                    'cursor': {
                        label: 'Cursor',
                        color: '#111111',
                        filename: 'sefar-cursor-mcp.json',
                        content: prettyJson({
                            mcpServers: {
                                sefar: {
                                    url: endpoint,
                                    headers: headers,
                                },
                            },
                        }),
                        message: 'Configuracion Cursor descargada.',
                    },
                    'claude': {
                        label: 'Claude Code',
                        color: '#d97757',
                        filename: 'sefar-claude-code.mcp.json',
                        content: prettyJson({
                            mcpServers: {
                                sefar: {
                                    type: 'http',
                                    url: endpoint,
                                    headers: headers,
                                },
                            },
                        }),
                        message: 'Configuracion Claude Code descargada.',
                    },
                    'windsurf': {
                        label: 'Windsurf',
                        color: '#00a7c8',
                        filename: 'sefar-windsurf-mcp_config.json',
                        content: prettyJson({
                            mcpServers: {
                                sefar: {
                                    serverUrl: endpoint,
                                    headers: headers,
                                },
                            },
                        }),
                        message: 'Configuracion Windsurf descargada.',
                    },
                    'opencode': {
                        label: 'OpenCode',
                        color: '#111827',
                        filename: 'sefar-opencode.json',
                        content: prettyJson({
                            '$schema': 'https://opencode.ai/config.json',
                            mcp: {
                                sefar: {
                                    type: 'remote',
                                    url: endpoint,
                                    enabled: true,
                                    headers: headers,
                                },
                            },
                        }),
                        message: 'Configuracion OpenCode descargada.',
                    },
                    'openclaw': {
                        label: 'OpenClaw',
                        color: '#7c3aed',
                        filename: 'sefar-openclaw-mcp.json',
                        content: prettyJson({
                            url: endpoint,
                            transport: 'streamable-http',
                            headers: headers,
                            requestTimeoutMs: 120000,
                            connectionTimeoutMs: 20000,
                        }),
                        message: 'Configuracion OpenClaw descargada.',
                    },
                    'generic': {
                        label: 'MCP HTTP generico',
                        color: '#6c757d',
                        filename: 'sefar-mcp-http-generico.json',
                        content: prettyJson({
                            mcpServers: {
                                sefar: {
                                    type: 'http',
                                    url: endpoint,
                                    headers: headers,
                                },
                            },
                        }),
                        message: 'Configuracion generica MCP descargada.',
                    },
                };

                if (! configs[kind]) {
                    throw new Error('Cliente IA no soportado.');
                }

                return configs[kind];
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
                if (tomlPreview) {
                    tomlPreview.textContent = toml();
                }

                setPreview('config.toml', 'Codex', '');

                copyText(toml(), 'Configuracion TOML copiada.');
            });

            document.querySelector('[data-download-codex-installer]')?.addEventListener('click', function () {
                try {
                    downloadText('instalar-sefar-mcp-codex.cmd', windowsInstaller(), 'application/x-msdownload');
                    setStatus('ok', 'Instalador descargado. Ejecutalo una vez y reinicia Codex Desktop.');
                } catch (error) {
                    setStatus('error', error.message || 'No se pudo generar el instalador.');
                }
            });

            document.querySelectorAll('[data-download-ai-config]').forEach(function (button) {
                button.addEventListener('click', function () {
                    try {
                        const config = aiConfig(button.getAttribute('data-download-ai-config'));

                        downloadText(config.filename, config.content, 'application/json');

                        if (tomlPreview) {
                            tomlPreview.textContent = config.content;
                        }

                        setPreview(config.filename, config.label, config.color);

                        setStatus('ok', config.message);
                    } catch (error) {
                        setStatus('error', error.message || 'No se pudo generar la configuracion.');
                    }
                });
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
