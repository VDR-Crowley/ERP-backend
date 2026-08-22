<?php

namespace App\Services\Auth;

use App\Enums\TokenAbility;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Emite e revoga os pares de token Sanctum (access + refresh).
 *
 * Sanctum não tem refresh token nativo, então cada login/registro/refresh
 * cria DOIS tokens ligados por um id de sessão embutido no `name`
 * ("access:{sessionId}" / "refresh:{sessionId}"). Isso permite:
 * - Revogar o par inteiro de uma vez (logout, reset de senha), sem precisar
 *   de coluna extra na tabela `personal_access_tokens`.
 * - Rotacionar o par no `/api/refresh`: o refresh token antigo é revogado e
 *   um par novo (access + refresh) é emitido — nunca reaproveita o mesmo
 *   refresh token duas vezes.
 */
class TokenService
{
    private const ACCESS_TTL_HOURS = 2;

    private const REFRESH_TTL_DAYS = 30;

    /**
     * Cria um par novo de tokens para o usuário e devolve os textos planos
     * (só existem neste momento — depois disso só o hash fica no banco).
     *
     * @return array{access_token: string, access_token_expires_at: Carbon, refresh_token: string, refresh_token_expires_at: Carbon}
     */
    public function issuePairFor(User $user): array
    {
        $sessionId = (string) Str::uuid();

        $accessExpiresAt = now()->addHours(self::ACCESS_TTL_HOURS);
        $refreshExpiresAt = now()->addDays(self::REFRESH_TTL_DAYS);

        $access = $user->createToken("access:{$sessionId}", [TokenAbility::Access->value], $accessExpiresAt);
        $refresh = $user->createToken("refresh:{$sessionId}", [TokenAbility::Refresh->value], $refreshExpiresAt);

        return [
            'access_token' => $access->plainTextToken,
            'access_token_expires_at' => $accessExpiresAt,
            'refresh_token' => $refresh->plainTextToken,
            'refresh_token_expires_at' => $refreshExpiresAt,
        ];
    }

    /**
     * Revoga os dois tokens (access + refresh) da mesma sessão a que
     * `$token` pertence. Usado no logout e depois de reset de senha.
     */
    public function revokeSessionOf(PersonalAccessToken $token): void
    {
        $sessionId = Str::after($token->name, ':');

        $token->tokenable->tokens()
            ->where(fn ($query) => $query->where('name', "access:{$sessionId}")
                ->orWhere('name', "refresh:{$sessionId}"))
            ->delete();
    }

    /**
     * Revoga todas as sessões (todos os pares access+refresh) do usuário.
     * Usado depois de um reset de senha bem-sucedido.
     */
    public function revokeAllSessionsFor(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Rotaciona a sessão: revoga o par atual e emite um par novo. Espera
     * receber o token de refresh que autenticou a requisição.
     *
     * @return array{access_token: string, access_token_expires_at: Carbon, refresh_token: string, refresh_token_expires_at: Carbon}
     */
    public function rotate(PersonalAccessToken $refreshToken): array
    {
        $user = $refreshToken->tokenable;

        $this->revokeSessionOf($refreshToken);

        return $this->issuePairFor($user);
    }
}
