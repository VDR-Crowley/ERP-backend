<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessLineReport\ShowBusinessLineReportRequest;
use App\Models\Expense;
use App\Models\Flock;
use App\Models\Product;
use App\Models\Sale;
use App\Services\BusinessLineReportService;
use Illuminate\Http\JsonResponse;

/**
 * Relatório "Análise por Linha de Negócio" (codorna/quail x galinha/chicken),
 * montado no backend a partir de `sales` (sem as marcadas em `sale_exclusions`),
 * `expenses` (respeitando `expense_species_overrides`), `flock` e `products`.
 * Período opcional via `start`/`end` (query string).
 */
class BusinessLineReportController extends Controller
{
    public function __construct(private readonly BusinessLineReportService $report) {}

    public function show(ShowBusinessLineReportRequest $request): JsonResponse
    {
        $start = $request->validated('start');
        $end = $request->validated('end');

        $sales = Sale::with('product')
            ->whereDoesntHave('exclusion')
            ->when($start, fn ($query) => $query->whereDate('date', '>=', $start))
            ->when($end, fn ($query) => $query->whereDate('date', '<=', $end))
            ->get();

        $expenses = Expense::with('speciesOverride')
            ->when($start, fn ($query) => $query->whereDate('date', '>=', $start))
            ->when($end, fn ($query) => $query->whereDate('date', '<=', $end))
            ->get();

        $flock = Flock::all();
        $products = Product::all();

        return response()->json($this->report->build($sales, $expenses, $flock, $products));
    }
}
