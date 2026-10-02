<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'vendedor_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** Admin: acesso total (default). */
    public const ROLE_ADMINISTRADOR = 'ADMINISTRADOR';

    /**
     * Vendedor: acesso restrito — só as vendas dele, só os clientes dele, e só
     * cria venda (não edita). Precisa de `vendedor_id` preenchido. Enforçado no
     * backend (RestrictVendedor + escopo em SaleController/CustomerController),
     * não só na tela.
     */
    public const ROLE_VENDEDOR = 'VENDEDOR';

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }

    public function isVendedor(): bool
    {
        return $this->role === self::ROLE_VENDEDOR && $this->vendedor_id !== null;
    }

    public function isAdministrador(): bool
    {
        return $this->role === self::ROLE_ADMINISTRADOR;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
