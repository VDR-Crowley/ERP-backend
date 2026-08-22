<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FlockIncubation\StoreFlockIncubationRequest;
use App\Http\Requests\FlockIncubation\UpdateFlockIncubationRequest;
use App\Http\Requests\HatchEvent\StoreHatchEventRequest;
use App\Http\Requests\HatchEvent\UpdateHatchEventRequest;
use App\Models\FlockIncubation;
use App\Models\HatchEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FlockIncubationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(FlockIncubation::with('hatchEvents')->get());
    }

    public function store(StoreFlockIncubationRequest $request): JsonResponse
    {
        $flockIncubation = FlockIncubation::create($request->validated());

        return response()->json($flockIncubation, Response::HTTP_CREATED);
    }

    public function show(FlockIncubation $flockIncubation): JsonResponse
    {
        return response()->json($flockIncubation->load('hatchEvents'));
    }

    public function update(UpdateFlockIncubationRequest $request, FlockIncubation $flockIncubation): JsonResponse
    {
        $flockIncubation->update($request->validated());

        return response()->json($flockIncubation);
    }

    public function destroy(FlockIncubation $flockIncubation): Response
    {
        $flockIncubation->delete();

        return response()->noContent();
    }

    /**
     * Histórico de nascimentos do lote — CRUD ainda sem a regra de fechar o
     * lote automaticamente (`deriveStatusAfterHatchChange`), que fica pra
     * próxima etapa.
     */
    public function hatchEvents(FlockIncubation $flockIncubation): JsonResponse
    {
        return response()->json($flockIncubation->hatchEvents);
    }

    public function storeHatchEvent(StoreHatchEventRequest $request, FlockIncubation $flockIncubation): JsonResponse
    {
        $hatchEvent = $flockIncubation->hatchEvents()->create($request->validated());

        return response()->json($hatchEvent, Response::HTTP_CREATED);
    }

    public function updateHatchEvent(
        UpdateHatchEventRequest $request,
        FlockIncubation $flockIncubation,
        HatchEvent $hatchEvent,
    ): JsonResponse {
        $this->assertBelongsToLote($flockIncubation, $hatchEvent);

        $hatchEvent->update($request->validated());

        return response()->json($hatchEvent);
    }

    public function destroyHatchEvent(FlockIncubation $flockIncubation, HatchEvent $hatchEvent): Response
    {
        $this->assertBelongsToLote($flockIncubation, $hatchEvent);

        $hatchEvent->delete();

        return response()->noContent();
    }

    private function assertBelongsToLote(FlockIncubation $flockIncubation, HatchEvent $hatchEvent): void
    {
        if ($hatchEvent->flock_incubation_id !== $flockIncubation->id) {
            throw new NotFoundHttpException;
        }
    }
}
