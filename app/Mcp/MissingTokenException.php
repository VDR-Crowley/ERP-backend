<?php

namespace App\Mcp;

/** Lançada quando ERP_API_TOKEN não está configurado, antes de qualquer tentativa de request. */
class MissingTokenException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'ERP_API_TOKEN não está configurado. Esse servidor precisa de um token de acesso Sanctum '.
            'válido pra chamar a API do MiniERP — veja docs/MCP.md "Como obter um token".',
        );
    }
}
