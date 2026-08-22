<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthTokenResource;
use App\Services\Auth\TokenService;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class RefreshTokenController extends Controller
{
    public function __construct(private readonly TokenService $tokens) {}

    /**
     * Rotaciona a sessão: o token de refresh usado aqui é revogado e um par
     * novo (access + refresh) é emitido. Requer ability `refresh` (garantido
     * pelo middleware `abilities:refresh` na rota).
     */
    public function store(Request $request): AuthTokenResource
    {
        $user = $request->user();

        /** @var PersonalAccessToken $refreshToken */
        $refreshToken = $user->currentAccessToken();

        $pair = $this->tokens->rotate($refreshToken);

        return new AuthTokenResource(['user' => $user, ...$pair]);
    }
}
