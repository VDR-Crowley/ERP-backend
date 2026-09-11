<?php

namespace App\Mcp;

/** Lançada quando a API do MiniERP não pôde ser alcançada (DNS, conexão recusada, timeout, ...). */
class ApiUnreachableException extends \RuntimeException
{
    public function __construct(string $url, \Throwable $cause)
    {
        parent::__construct(
            "Não foi possível alcançar a API do MiniERP em {$url}: {$cause->getMessage()}",
            previous: $cause,
        );
    }
}
