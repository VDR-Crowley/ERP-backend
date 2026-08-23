<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function __construct(private readonly UserManagementService $users) {}

    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /** Tela de administração de usuários (não paginado, mesmo padrão das outras entidades core). */
    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection($this->users->list());
    }

    /** Cria usuário novo com role ADMINISTRADOR — fluxo de admin, não o cadastro público (`/register`). */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /** Senha só troca se enviada; `is_active` inclusa (bloqueado desativar a própria conta). */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $user = $this->users->update($user, $request->validated(), $request->user());

        return new UserResource($user);
    }

    /** "Excluir" desativa (`is_active=false`) em vez de apagar de verdade. */
    public function destroy(Request $request, User $user): Response
    {
        $this->users->deactivate($user, $request->user());

        return response()->noContent();
    }
}
