<?php

namespace App\Enums;

/**
 * Abilities dos tokens Sanctum emitidos pela API. Cada par de tokens (login,
 * registro, refresh) carrega exatamente uma dessas: `Access` autentica as
 * rotas normais da API, `Refresh` só autentica o endpoint `/api/refresh`.
 */
enum TokenAbility: string
{
    case Access = 'access';
    case Refresh = 'refresh';
}
