<?php

namespace App\Exceptions;

use RuntimeException;

/** Faltam variáveis de ambiente pra consultar a Railway API. */
class RailwayNotConfiguredException extends RuntimeException
{
    /** @param  list<string>  $missing */
    public function __construct(public readonly array $missing)
    {
        parent::__construct('Integração com a Railway não configurada: falta '.implode(', ', $missing).'.');
    }
}
