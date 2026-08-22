<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\StoreExpenseSpeciesOverrideRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ExpenseController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Expense::with('speciesOverride')->get());
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = Expense::create($request->validated());

        return response()->json($expense, Response::HTTP_CREATED);
    }

    public function show(Expense $expense): JsonResponse
    {
        return response()->json($expense->load('speciesOverride'));
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $expense->update($request->validated());

        return response()->json($expense);
    }

    public function destroy(Expense $expense): Response
    {
        $expense->delete();

        return response()->noContent();
    }

    /** Sobrescreve manualmente a espécie que consumiu a despesa (ver `allocateExpenseAmount` no front). */
    public function storeSpeciesOverride(StoreExpenseSpeciesOverrideRequest $request, Expense $expense): JsonResponse
    {
        $override = $expense->speciesOverride()->updateOrCreate([], $request->validated());

        return response()->json($override, Response::HTTP_CREATED);
    }

    /** Remove o override (volta pra detecção automática por texto). */
    public function destroySpeciesOverride(Expense $expense): Response
    {
        $expense->speciesOverride()->delete();

        return response()->noContent();
    }
}
