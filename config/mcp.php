<?php

use App\Mcp\McpConfig;

/*
|--------------------------------------------------------------------------
| Config do servidor MCP (PHP)
|--------------------------------------------------------------------------
|
| ERP_API_BASE_URL: base da API do MiniERP (sem barra final). Sem essa env
| setada, cai pro próprio APP_URL local + "/api" — esse servidor já mora
| dentro do backend, então rodar local sem configurar nada aponta pra ele
| mesmo. Pra apontar pra produção:
|   ERP_API_BASE_URL=https://laravel-production-4c67.up.railway.app/api
|
| ERP_API_TOKEN: token de acesso Sanctum (Bearer). Ver docs/MCP.md "Como
| obter um token".
|
*/

return [
    'base_url' => McpConfig::resolveBaseUrl(env('ERP_API_BASE_URL'), env('APP_URL')),
    'token' => McpConfig::resolveToken(env('ERP_API_TOKEN')),
];
