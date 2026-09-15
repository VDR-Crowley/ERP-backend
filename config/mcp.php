<?php

use App\Mcp\McpConfig;

/*
|--------------------------------------------------------------------------
| Config do servidor MCP (PHP)
|--------------------------------------------------------------------------
|
| MCP_HTTP_TOKEN: token dedicado da rota HTTP (POST /api/mcp) — quem pode
| falar com o servidor MCP. Ver docs/MCP.md "Transporte HTTP" pra como gerar
| e setar no Railway.
|
| Não existe mais ERP_API_TOKEN/ERP_API_BASE_URL: as tools leem direto via
| Eloquent (mesmo processo, mesma conexão de banco), sem round-trip HTTP pra
| própria API nem token Sanctum de usuário. Ver ADR em docs/MCP.md.
|
*/

return [
    'http_token' => McpConfig::resolveToken(env('MCP_HTTP_TOKEN')),
];
