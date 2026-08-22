<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthTokenResource;
use App\Services\Auth\AuthService;

class LoginController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function store(LoginRequest $request): AuthTokenResource
    {
        $result = $this->auth->attempt(
            $request->validated('email'),
            $request->validated('password'),
        );

        return new AuthTokenResource($result);
    }
}
