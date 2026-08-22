<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestPasswordResetRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyPasswordResetCodeRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordResetService $passwordReset) {}

    /**
     * Sempre responde OK genérico — não revela se o e-mail existe.
     */
    public function requestCode(RequestPasswordResetRequest $request): JsonResponse
    {
        $this->passwordReset->requestCode($request->validated('email'));

        return response()->json([
            'message' => 'Se o e-mail existir, enviamos um código de redefinição.',
        ]);
    }

    public function verifyCode(VerifyPasswordResetCodeRequest $request): JsonResponse
    {
        $this->passwordReset->verifyCode(
            $request->validated('email'),
            $request->validated('code'),
        );

        return response()->json(['message' => 'Código válido.']);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordReset->reset(
            $request->validated('email'),
            $request->validated('code'),
            $request->validated('password'),
        );

        return response()->json(['message' => 'Senha redefinida com sucesso.']);
    }
}
