<?php

namespace App\Mcp;

use App\Models\CashFlow;
use App\Models\DailyProduction;
use App\Models\Expense;
use App\Models\FeedOpenLog;
use App\Models\FeedStock;
use App\Models\Flock;
use App\Models\FlockCleaning;
use App\Models\FlockIncubation;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Vendedor;
use App\Models\VendorStock;
use App\Services\BusinessLineReportService;

/**
 * Lê cada tool direto via Eloquent, no mesmo processo/conexão de banco do
 * app — não é mais um client HTTP pra própria API (ver `ErpApiClient`,
 * removido). Um `match` por nome de tool, um braço por entrada de
 * `ToolDefinitions`, espelhando exatamente a query do controller GET
 * correspondente (mesmas relations eager-loaded, mesmo shape de resposta).
 *
 * `get_current_user` não tem equivalente aqui: dependia do usuário Sanctum
 * que autenticava a chamada HTTP antiga, e essa leitura não autentica como
 * usuário nenhum — é a aplicação lendo o próprio banco. Ver ADR em
 * docs/MCP.md.
 */
final class ErpDataReader
{
    public function __construct(private readonly BusinessLineReportService $businessLineReport) {}

    /**
     * @param  array<string, int|string|null>  $pathParams
     * @param  array<string, string|null>  $query
     * @return array<mixed>
     */
    public function read(string $tool, array $pathParams, array $query): array
    {
        return match ($tool) {
            'get_current_user' => throw new \RuntimeException(
                'Tool "get_current_user" não é suportada nesta transporte: as tools leem direto via Eloquent, '.
                'sem autenticar como um usuário específico. Use "list_users" pra ver os usuários cadastrados.',
            ),
            'list_users' => User::all()->map(self::userArray(...))->all(),
            'list_products' => Product::all()->toArray(),
            'get_product' => Product::findOrFail($pathParams['product'])->toArray(),
            'list_vendedores' => Vendedor::all()->toArray(),
            'get_vendedor' => Vendedor::findOrFail($pathParams['vendedor'])->toArray(),
            'list_flock' => Flock::all()->toArray(),
            'get_flock' => Flock::findOrFail($pathParams['flock'])->toArray(),
            'list_flock_incubations' => FlockIncubation::with('hatchEvents')->get()->toArray(),
            'get_flock_incubation' => FlockIncubation::findOrFail($pathParams['flock_incubation'])
                ->load('hatchEvents')->toArray(),
            'list_hatch_events' => FlockIncubation::findOrFail($pathParams['flock_incubation'])
                ->hatchEvents->toArray(),
            'list_vendor_stock' => VendorStock::all()->toArray(),
            'get_vendor_stock' => VendorStock::findOrFail($pathParams['vendor_stock'])->toArray(),
            'list_sales' => Sale::with('exclusion')->get()->toArray(),
            'get_sale' => Sale::findOrFail($pathParams['sale'])->load('exclusion')->toArray(),
            'list_stock_transfers' => StockTransfer::all()->toArray(),
            'get_stock_transfer' => StockTransfer::findOrFail($pathParams['stock_transfer'])->toArray(),
            'list_daily_productions' => DailyProduction::all()->toArray(),
            'get_daily_production' => DailyProduction::findOrFail($pathParams['daily_production'])->toArray(),
            'list_expenses' => Expense::with('speciesOverride')->get()->toArray(),
            'get_expense' => Expense::findOrFail($pathParams['expense'])->load('speciesOverride')->toArray(),
            'list_cash_flows' => CashFlow::all()->toArray(),
            'get_cash_flow' => CashFlow::findOrFail($pathParams['cash_flow'])->toArray(),
            'list_feed_stocks' => FeedStock::all()->toArray(),
            'get_feed_stock' => FeedStock::findOrFail($pathParams['feed_stock'])->toArray(),
            'list_feed_open_logs' => FeedOpenLog::all()->toArray(),
            'list_flock_cleanings' => FlockCleaning::all()->toArray(),
            'get_flock_cleaning' => FlockCleaning::findOrFail($pathParams['flock_cleaning'])->toArray(),
            'get_business_line_report' => $this->businessLineReport($query['start'] ?? null, $query['end'] ?? null),
            default => throw new \LogicException("Tool \"{$tool}\" não tem leitor mapeado em ErpDataReader (bug de código, não input do usuário)."),
        };
    }

    /** Mesmo shape de `UserResource` — nunca o model cru (vazaria o hash da senha). */
    private static function userArray(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at,
        ];
    }

    /** Mesma query/validação de `BusinessLineReportController::show()`, sem o FormRequest. */
    private function businessLineReport(?string $start, ?string $end): array
    {
        if ($start !== null && $end !== null && $end < $start) {
            throw new \RuntimeException('O parâmetro "end" deve ser >= "start".');
        }

        $sales = Sale::with('product')
            ->whereDoesntHave('exclusion')
            ->when($start, fn ($query) => $query->whereDate('date', '>=', $start))
            ->when($end, fn ($query) => $query->whereDate('date', '<=', $end))
            ->get();

        $expenses = Expense::with('speciesOverride')
            ->when($start, fn ($query) => $query->whereDate('date', '>=', $start))
            ->when($end, fn ($query) => $query->whereDate('date', '<=', $end))
            ->get();

        return $this->businessLineReport->build($sales, $expenses, Flock::all(), Product::all());
    }
}
