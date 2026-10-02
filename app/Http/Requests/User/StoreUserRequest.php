<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `role` opcional (default ADMINISTRADOR). Pra VENDEDOR, `vendedor_id` é
     * obrigatório (liga o login ao vendedor que ele enxerga/baixa estoque).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(6)],
            'role' => ['sometimes', Rule::in([User::ROLE_ADMINISTRADOR, User::ROLE_VENDEDOR])],
            'vendedor_id' => [
                'nullable',
                'required_if:role,'.User::ROLE_VENDEDOR,
                'integer',
                'exists:vendedores,id',
            ],
        ];
    }
}
