<?php

namespace App\Mcp;

/**
 * Lançada pra qualquer resposta não-2xx da API. Carrega o corpo/status reais
 * (JSON parseado quando possível, texto cru senão) pra quem chamar poder
 * expor a mensagem de validação/auth real em vez de uma falha genérica.
 */
class ErpApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly string $url,
        public readonly mixed $body,
    ) {
        parent::__construct($message);
    }
}
