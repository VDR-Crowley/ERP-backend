<?php

namespace App\Mcp;

/**
 * Lógica pura de resolução da config do servidor MCP, extraída do
 * config/mcp.php pra ser testável sem depender do boot do Laravel com envs
 * diferentes (config files só rodam uma vez por processo).
 */
final class McpConfig
{
    public static function resolveToken(?string $envToken): ?string
    {
        $trimmed = $envToken !== null ? trim($envToken) : '';

        return $trimmed !== '' ? $trimmed : null;
    }
}
