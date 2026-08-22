<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Envelope de resposta pro login/registro/refresh: usuário + par de tokens.
 *
 * @property array{user: User, access_token: string, access_token_expires_at: Carbon, refresh_token: string, refresh_token_expires_at: Carbon} $resource
 */
class AuthTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this->resource['user']),
            'token_type' => 'Bearer',
            'access_token' => $this->resource['access_token'],
            'access_token_expires_at' => $this->resource['access_token_expires_at'],
            'refresh_token' => $this->resource['refresh_token'],
            'refresh_token_expires_at' => $this->resource['refresh_token_expires_at'],
        ];
    }
}
