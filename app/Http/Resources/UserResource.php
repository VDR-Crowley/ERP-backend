<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'vendedor_id' => $this->vendedor_id,
            // Nome do vendedor ligado (quando VENDEDOR) — o front usa pra travar
            // o local de estoque/vendedor da venda e rotular a conta.
            'vendedor_name' => $this->vendedor?->name,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
