<?php

return [
    'login_email' => env('SEFAR_MCP_LOGIN_EMAIL') ?: env('SEFAR_LOGIN_EMAIL'),
    'login_password' => env('SEFAR_MCP_LOGIN_PASSWORD') ?: env('SEFAR_LOGIN_PASSWORD'),
    'audit_log' => env('SEFAR_MCP_AUDIT_LOG', storage_path('logs/sefar-mcp-audit.jsonl')),
    'audit_secret' => env('SEFAR_MCP_AUDIT_SECRET'),
];
