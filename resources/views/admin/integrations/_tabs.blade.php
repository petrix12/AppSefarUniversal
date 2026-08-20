<div class="btn-group mt-2 mt-md-0">
    <a href="{{ route('admin.integrations.api-tokens.index') }}" class="btn {{ request()->routeIs('admin.integrations.api-tokens.*') ? 'btn-primary' : 'btn-outline-primary' }}">
        <i class="fas fa-key mr-1"></i> Tokens API
    </a>
    <a href="{{ route('admin.integrations.mcp.index') }}" class="btn {{ request()->routeIs('admin.integrations.mcp.*') ? 'btn-primary' : 'btn-outline-primary' }}">
        <i class="fas fa-shield-alt mr-1"></i> MCP privado
    </a>
    <a href="{{ route('admin.integrations.audit.index') }}" class="btn {{ request()->routeIs('admin.integrations.audit.*') ? 'btn-primary' : 'btn-outline-primary' }}">
        <i class="fas fa-clipboard-list mr-1"></i> Auditoria
    </a>
</div>
