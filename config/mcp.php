<?php

return [
    'audit_log' => env('SEFAR_MCP_AUDIT_LOG', storage_path('logs/sefar-mcp-audit.jsonl')),
    'audit_secret' => env('SEFAR_MCP_AUDIT_SECRET'),
];
