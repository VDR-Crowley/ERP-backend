<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\AuthTokenResource;
use App\Services\Auth\RegistrationService;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __construct(private readonly RegistrationService $registration) {}

    public function store(RegisterRequest $request): JsonResponse
    {
        $result = $this->registration->register($request->validated());

        return (new AuthTokenResource($result))
            ->response()
            ->setStatusCode(201);
    }
}
