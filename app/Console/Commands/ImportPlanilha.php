<?php

namespace App\Console\Commands;

use App\Models\DailyProduction;
use App\Models\EggStock;
use App\Models\Expense;
use App\Models\FeedOpenLog;
use App\Models\FeedStock;
use App\Models\Flock;
use App\Models\FlockCleaning;
use App\Models\FlockIncubation;
use App\Models\HatchEvent;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Vendedor;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Importa os dados reais da planilha MiniERP pro banco, marcados
 * `is_mock = false`. Idempotente — natural key por entidade evita
 * duplicata em reimportação (upsert via `updateOrCreate`).
 *
 * Abas fora do escopo (não processadas):
 * - "Vendedores": não existe na planilha — vendedores são derivados da
 *   coluna "Vendedor" da aba Vendas.
 * - "Fluxo de Caixa": só tem cabeçalho, sem linha de dado.
 * - "Usuários"/"Dashboard": fora do escopo dos 17 entidades core (a
 *   primeira tem credencial em texto puro — nunca lida/logada aqui).
 *
 * Decisões de ambiguidade validadas com o usuário em 2026-08-23 (ver
 * relato da sessão) estão comentadas nos métodos específicos onde se
 * aplicam.
 */
class ImportPlanilha extends Command
{
    protected $signature = 'import:planilha {path=/Users/vandodosreis/Library/Application Support/Claude/local-agent-mode-sessions/9d8bb3ae-a09d-4990-9290-499e05c9ed63/16226cd6-c64b-4e31-9356-cc7477291512/agent/local_ditto_16226cd6-c64b-4e31-9356-cc7477291512/uploads/58efa7a0-MiniERP_22_08.xlsx : caminho do .xlsx}';

    protected $description = 'Importa dados reais da planilha MiniERP pras entidades core (is_mock = false), idempotente';

    /** @var array<string, int> */
    private array $summary = [];

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        $spreadsheet = IOFactory::load($path);

        $products = $this->importProdutos($spreadsheet);
        $vendedores = $this->importVendedoresFromVendas($spreadsheet);
        $this->importPlantel($spreadsheet);
        $this->importNovoPlantel($spreadsheet);
        $this->importVendas($spreadsheet, $products, $vendedores);
        $this->importProducao($spreadsheet);
        $this->importEstoqueOvos($spreadsheet);
        $this->importDespesas($spreadsheet);
        $feedStocks = $this->importRacao($spreadsheet);
        $this->importRacaoSacosAbertos($spreadsheet, $feedStocks);
        $this->importHigienizacao($spreadsheet);

        $this->newLine();
        $this->table(['Entidade', 'Registros reais importados/atualizados'], collect($this->summary)
            ->map(fn (int $count, string $entity) => [$entity, $count])
            ->values()
            ->all());

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<mixed>> linhas de dado (sem o cabeçalho)
     */
    private function rows(Spreadsheet $spreadsheet, string $sheetName): array
    {
        $sheet = $spreadsheet->getSheetByName($sheetName);

        if ($sheet === null) {
            $this->warn("Aba '{$sheetName}' não encontrada, pulando.");

            return [];
        }

        $rows = $sheet->toArray(null, true, true, false);
        array_shift($rows); // cabeçalho

        // Remove linhas totalmente vazias.
        return array_values(array_filter($rows, fn (array $row) => collect($row)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty()));
    }

    private function parseDate(mixed $raw): ?string
    {
        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }

        return Carbon::createFromFormat('d/m/Y', trim((string) $raw))->toDateString();
    }

    private function toFloat(mixed $raw): ?float
    {
        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }

        return (float) $raw;
    }

    private function toInt(mixed $raw): ?int
    {
        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }

        return (int) $raw;
    }

    /** 'Codorna'/'Galinha' (livre, PT) -> enum quail/chicken do backend. */
    private function mapSpecies(string $raw): string
    {
        $lower = mb_strtolower(trim($raw));

        return str_contains($lower, 'codorna') ? 'quail' : 'chicken';
    }

    private function bump(string $entity, int $by = 1): void
    {
        $this->summary[$entity] = ($this->summary[$entity] ?? 0) + $by;
    }

    /**
     * `updateOrCreate` com chave natural que envolve coluna(s) `date`. No
     * SQLite, Eloquent grava atributo `date` como `Y-m-d H:i:s` (formato de
     * data da grammar), então comparar com `where('col', 'Y-m-d')` puro
     * nunca bate — sempre cria de novo (duplicata silenciosa, ou violação
     * de unique quando existe). `whereDate()` normaliza a comparação e
     * resolve isso independente do formato de storage.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $exact  colunas não-data da chave natural
     * @param  array<string, string|null>  $dates  colunas de data da chave natural (Y-m-d)
     * @param  array<string, mixed>  $values  demais atributos a gravar/atualizar
     */
    private function upsertByNaturalKey(string $modelClass, array $exact, array $dates, array $values): Model
    {
        $query = $modelClass::query();

        foreach ($exact as $column => $value) {
            $query->where($column, $value);
        }

        foreach ($dates as $column => $value) {
            $query->whereDate($column, $value);
        }

        $existing = $query->first();

        if ($existing !== null) {
            $existing->fill($values)->save();

            return $existing;
        }

        return $modelClass::query()->create([...$exact, ...$dates, ...$values]);
    }

    // -- Produtos ------------------------------------------------------

    /** @return array<string, Product> nome (trim) => Product */
    private function importProdutos(Spreadsheet $spreadsheet): array
    {
        $map = [];

        foreach ($this->rows($spreadsheet, 'Produtos') as $row) {
            [$name, $unit, $unitPrice, $stock, $eggsPerUnit] = $row;
            $name = trim((string) $name);

            $product = Product::query()->updateOrCreate(
                ['name' => $name],
                [
                    'unit' => trim((string) $unit),
                    'unit_price' => $this->toFloat($unitPrice) ?? 0,
                    'stock' => $this->toInt($stock) ?? 0,
                    'eggs_per_unit' => $this->toInt($eggsPerUnit) ?? 0,
                    'is_mock' => false,
                ]
            );

            $map[$name] = $product;
            $this->bump('products');
        }

        return $map;
    }

    // -- Vendedores (derivados da aba Vendas) ---------------------------

    /** @return array<string, Vendedor> nome (trim) => Vendedor */
    private function importVendedoresFromVendas(Spreadsheet $spreadsheet): array
    {
        $names = collect($this->rows($spreadsheet, 'Vendas'))
            ->map(fn (array $row) => trim((string) ($row[7] ?? '')))
            ->filter()
            ->unique();

        $map = [];

        foreach ($names as $name) {
            $vendedor = Vendedor::query()->updateOrCreate(
                ['name' => $name],
                ['active' => true, 'is_mock' => false]
            );

            $map[$name] = $vendedor;
            $this->bump('vendedores');
        }

        return $map;
    }

    // -- Plantel ---------------------------------------------------------

    private function importPlantel(Spreadsheet $spreadsheet): void
    {
        foreach ($this->rows($spreadsheet, 'Plantel') as $row) {
            [$species, $quantity, $bagsPerMonth, $bagPrice, $monthlyTotal] = $row;

            Flock::query()->updateOrCreate(
                ['species' => trim((string) $species)],
                [
                    'quantity' => $this->toInt($quantity) ?? 0,
                    'feed_bags_per_month' => $this->toInt($bagsPerMonth) ?? 0,
                    'bag_price' => $this->toFloat($bagPrice) ?? 0,
                    'monthly_total' => $this->toFloat($monthlyTotal) ?? 0,
                    'is_mock' => false,
                ]
            );

            $this->bump('flock');
        }
    }

    // -- Novo Plantel (incubações + eclosões) -----------------------------

    private function importNovoPlantel(Spreadsheet $spreadsheet): void
    {
        // Correção de digitação validada com o usuário em 2026-08-23: a
        // linha de início 16/05/2026 (codorna, 100 ovos) tinha Data Eclosão
        // = 16/07/2026 na planilha (61 dias depois do início — incubação de
        // codorna é ~18 dias, incompatível). Corrigido pra 16/06/2026.
        $hatchDateCorrections = [
            '2026-05-16|100' => '2026-06-16',
        ];

        foreach ($this->rows($spreadsheet, 'Novo Plantel') as $row) {
            [$startDateRaw, $speciesRaw, $eggCount, $expectedHatchRaw, $actualHatchRaw, $hatchedCount, $status, $eggCost, $feedCost, $notes] = $row;

            $startDate = $this->parseDate($startDateRaw);
            $species = $this->mapSpecies((string) $speciesRaw);
            $eggCountInt = $this->toInt($eggCount) ?? 0;
            $expectedHatchDate = $this->parseDate($expectedHatchRaw);
            $status = mb_strtolower(trim((string) $status));
            $notes = $notes !== null ? trim((string) $notes) : null;

            $incubation = $this->upsertByNaturalKey(
                FlockIncubation::class,
                ['species' => $species, 'egg_count' => $eggCountInt],
                ['start_date' => $startDate],
                [
                    'expected_hatch_date' => $expectedHatchDate,
                    'status' => in_array($status, ['incubando', 'eclodido'], true) ? $status : 'incubando',
                    'egg_cost' => $this->toFloat($eggCost),
                    'feed_cost' => $this->toFloat($feedCost),
                    'notes' => $notes,
                    'is_mock' => false,
                ]
            );
            $this->bump('flock_incubations');

            $actualHatchDate = $this->parseDate($actualHatchRaw);
            $key = "{$startDate}|{$eggCountInt}";
            if ($actualHatchDate !== null && isset($hatchDateCorrections[$key])) {
                $actualHatchDate = $hatchDateCorrections[$key];
            }

            $hatchCount = $this->toInt($hatchedCount);

            // Ambiguidade validada com o usuário em 2026-08-23: linha
            // 15/07/2026 (codorna, 250 ovos) tem status "eclodido" mas Data
            // Eclosão/Qtd. Nascida em branco na planilha; nota "Gato matou
            // 60, sobraram 90". Decisão: criar hatch_event com count=90 e
            // date = expected_hatch_date.
            if ($status === 'eclodido' && $actualHatchDate === null && $hatchCount === null) {
                $actualHatchDate = $expectedHatchDate;
                $hatchCount = 90;
            }

            if ($actualHatchDate !== null && $hatchCount !== null) {
                $this->upsertByNaturalKey(
                    HatchEvent::class,
                    ['flock_incubation_id' => $incubation->id],
                    ['date' => $actualHatchDate],
                    ['count' => $hatchCount, 'notes' => $notes, 'is_mock' => false]
                );
                $this->bump('hatch_events');
            }
        }
    }

    // -- Vendas ------------------------------------------------------------

    /**
     * @param  array<string, Product>  $products
     * @param  array<string, Vendedor>  $vendedores
     */
    private function importVendas(Spreadsheet $spreadsheet, array $products, array $vendedores): void
    {
        foreach ($this->rows($spreadsheet, 'Vendas') as $row) {
            [$dateRaw, $productName, $quantity, $unitPrice, $total, $paymentStatus, $buyer, $vendedorName, $deliveryStatus, $deliveryDateRaw] = $row;

            $productName = trim((string) $productName);
            $vendedorName = trim((string) $vendedorName);
            $quantityFloat = $this->toFloat($quantity) ?? 0.0;

            // Ambiguidade validada com o usuário em 2026-08-23: linha
            // 22/06/2026 (Mercadinho/Washigton) tem quantity=0.5, mas a
            // coluna é integer no schema. Decisão: pular a linha.
            if ($quantityFloat !== floor($quantityFloat)) {
                $this->warn("Venda pulada (quantidade fracionária {$quantityFloat}): {$dateRaw} / {$productName} / {$buyer}");

                continue;
            }

            $quantityInt = (int) $quantityFloat;

            $product = $products[$productName] ?? null;
            if ($product === null) {
                // Ambiguidade validada com o usuário em 2026-08-23: produto
                // "5 ovos Galinha + 50 Codorna" (venda de 02/07/2026) não
                // está cadastrado na aba Produtos. Decisão: cadastrar como
                // combo, eggs_per_unit=0 (não entra no total de ovos).
                $product = Product::query()->updateOrCreate(
                    ['name' => $productName],
                    [
                        'unit' => 'Combo (5 galinha + 50 codorna)',
                        'unit_price' => $this->toFloat($unitPrice) ?? 0,
                        'stock' => 0,
                        'eggs_per_unit' => 0,
                        'is_mock' => false,
                    ]
                );
                $products[$productName] = $product;
                $this->bump('products');
            }

            $vendedor = $vendedores[$vendedorName] ?? Vendedor::query()->updateOrCreate(
                ['name' => $vendedorName],
                ['active' => true, 'is_mock' => false]
            );
            $vendedores[$vendedorName] = $vendedor;

            $unitPriceFloat = $this->toFloat($unitPrice) ?? 0.0;
            $totalFloat = $this->toFloat($total) ?? 0.0;

            // Regra: total=0 com quantity/unit_price > 0 é inconsistência de
            // planilha (validado com o usuário em 2026-08-23 na linha
            // 12/07/2026 Maeleide/Sueli) — recalcula total = quantity × unit_price.
            if ($totalFloat === 0.0 && $quantityInt > 0 && $unitPriceFloat > 0) {
                $totalFloat = $quantityInt * $unitPriceFloat;
            }

            $paymentPending = mb_strtoupper(trim((string) $paymentStatus)) !== 'PAGO';
            $deliveryPending = mb_strtoupper(trim((string) $deliveryStatus)) !== 'ENTREGUE';

            $this->upsertByNaturalKey(
                Sale::class,
                [
                    'product_id' => $product->id,
                    'buyer' => trim((string) $buyer),
                    'seller_id' => $vendedor->id,
                    'quantity' => $quantityInt,
                    'unit_price' => $unitPriceFloat,
                ],
                ['date' => $this->parseDate($dateRaw)],
                [
                    'total' => $totalFloat,
                    'payment_pending' => $paymentPending,
                    'delivery_pending' => $deliveryPending,
                    'delivery_date' => $this->parseDate($deliveryDateRaw),
                    'stock_location_type' => 'plantel',
                    'stock_location_vendedor_id' => null,
                    'is_mock' => false,
                ]
            );
            $this->bump('sales');
        }
    }

    // -- Produção ------------------------------------------------------------

    private function importProducao(Spreadsheet $spreadsheet): void
    {
        foreach ($this->rows($spreadsheet, 'Produção') as $row) {
            [$dateRaw, $quailEggs, $chickenEggs] = $row;

            $this->upsertByNaturalKey(
                DailyProduction::class,
                [],
                ['date' => $this->parseDate($dateRaw)],
                [
                    'quail_eggs' => $this->toInt($quailEggs),
                    'chicken_eggs' => $this->toInt($chickenEggs),
                    'is_mock' => false,
                ]
            );
            $this->bump('daily_productions');
        }
    }

    // -- Estoque de Ovos -------------------------------------------------

    private function importEstoqueOvos(Spreadsheet $spreadsheet): void
    {
        foreach ($this->rows($spreadsheet, 'Estoque de Ovos') as $row) {
            [$dateRaw, $quailEggs, $chickenEggs, $quailPacks, $chickenPacks, $quailValue, $chickenValue] = $row;

            // Ambiguidade validada com o usuário em 2026-08-23 (linhas
            // 05/07 e 06/07/2026 com valores negativos): decisão foi
            // importar como está, sem clamp em zero.
            $this->upsertByNaturalKey(
                EggStock::class,
                [],
                ['date' => $this->parseDate($dateRaw)],
                [
                    'quail_eggs' => $this->toInt($quailEggs),
                    'chicken_eggs' => $this->toInt($chickenEggs),
                    'quail_packs' => $this->toFloat($quailPacks) ?? 0,
                    'chicken_packs' => $this->toFloat($chickenPacks) ?? 0,
                    'quail_stock_value' => $this->toFloat($quailValue) ?? 0,
                    'chicken_stock_value' => $this->toFloat($chickenValue) ?? 0,
                    'is_mock' => false,
                ]
            );
            $this->bump('egg_stocks');
        }
    }

    // -- Despesas ----------------------------------------------------------

    private function importDespesas(Spreadsheet $spreadsheet): void
    {
        // Ambiguidade validada com o usuário em 2026-08-23: existem grupos
        // de linhas 100% idênticas (ex. "Ração Codorna Postura"/Codornas/52
        // em 13/07/2026 aparece 4x) — são compras reais separadas, não erro
        // de digitação. (date, description, category, amount) sozinho não
        // diferencia essas linhas, então conto a ocorrência dentro do grupo
        // (1ª, 2ª, 3ª...) e casa pela N-ésima linha já existente no banco
        // com essa chave — mantém idempotência sem coluna nova.
        $occurrenceSeen = [];

        foreach ($this->rows($spreadsheet, 'Despesas') as $row) {
            [$dateRaw, $description, $category, $quantity, $unitPrice, $amount, $paid] = $row;

            $exact = [
                'description' => trim((string) $description),
                'category' => trim((string) $category),
                'amount' => $this->toFloat($amount) ?? 0,
            ];
            $date = $this->parseDate($dateRaw);
            $groupKey = $date.'|'.implode('|', $exact);
            $occurrenceSeen[$groupKey] = ($occurrenceSeen[$groupKey] ?? 0) + 1;
            $occurrenceIndex = $occurrenceSeen[$groupKey]; // 1-based

            $values = [
                'quantity' => $this->toInt($quantity),
                'unit_price' => $this->toFloat($unitPrice),
                // Ambiguidade validada com o usuário em 2026-08-23 (6 linhas
                // com data 01/09/2026 e 01/10/2026, futuras em relação a
                // hoje 23/08/2026, marcadas "Pago=Sim"): decisão foi
                // importar como está (paid=true, data literal).
                'paid' => mb_strtolower(trim((string) $paid)) === 'sim',
                'is_mock' => false,
            ];

            $existingOfGroup = Expense::query()
                ->where($exact)
                ->whereDate('date', $date)
                ->orderBy('id')
                ->get();

            if ($existingOfGroup->count() >= $occurrenceIndex) {
                $existingOfGroup->get($occurrenceIndex - 1)->fill($values)->save();
            } else {
                Expense::query()->create([...$exact, 'date' => $date, ...$values]);
            }

            $this->bump('expenses');
        }
    }

    // -- Ração (estoque) -----------------------------------------------------

    /** @return array<string, FeedStock> tipo (lowercase trim) => FeedStock */
    private function importRacao(Spreadsheet $spreadsheet): array
    {
        $map = [];

        foreach ($this->rows($spreadsheet, 'Ração') as $row) {
            [$type, $bags, $kg, $bagWeight, $expiration] = $row;
            $type = trim((string) $type);

            $feedStock = FeedStock::query()->updateOrCreate(
                ['type' => $type],
                [
                    'bags_in_stock' => $this->toInt($bags) ?? 0,
                    'kg_in_stock' => $this->toFloat($kg) ?? 0,
                    'last_bag_weight_kg' => $this->toFloat($bagWeight) ?? 0,
                    'expiration_date' => $this->parseDate($expiration),
                    'is_mock' => false,
                ]
            );

            $map[mb_strtolower($type)] = $feedStock;
            $this->bump('feed_stocks');
        }

        return $map;
    }

    // -- Ração - Sacos Abertos -------------------------------------------

    /** @param  array<string, FeedStock>  $feedStocks */
    private function importRacaoSacosAbertos(Spreadsheet $spreadsheet, array $feedStocks): void
    {
        foreach ($this->rows($spreadsheet, 'Ração - Sacos Abertos') as $row) {
            [$dateRaw, $type, $weight] = $row;
            $type = trim((string) $type);

            // Comportamento documentado na migration de feed_open_logs: o
            // texto do tipo não precisa bater com feed_stocks.type — quando
            // não bate (ex. "Codorna Poastura", typo da planilha, vs
            // "Codorna Postura" cadastrado), a FK fica null mas o texto cru
            // é gravado do mesmo jeito. Não normalizamos o typo.
            $feedStock = $feedStocks[mb_strtolower($type)] ?? null;

            $this->upsertByNaturalKey(
                FeedOpenLog::class,
                ['feed_type' => $type],
                ['date' => $this->parseDate($dateRaw)],
                [
                    'feed_stock_id' => $feedStock?->id,
                    'weight_kg' => $this->toFloat($weight) ?? 0,
                    'is_mock' => false,
                ]
            );
            $this->bump('feed_open_logs');
        }
    }

    // -- Higienização --------------------------------------------------------

    private function importHigienizacao(Spreadsheet $spreadsheet): void
    {
        // Ambiguidade validada com o usuário em 2026-08-23: enum
        // cleaning_type só tem total/feeder/tray/nest; "Bebedouro" da
        // planilha foi mapeado pra "feeder" (mais próximo disponível hoje).
        $typeMap = [
            'bandeja' => 'tray',
            'bebedouro' => 'feeder',
            'total' => 'total',
        ];

        foreach ($this->rows($spreadsheet, 'Higienização') as $row) {
            [$dateRaw, $speciesRaw, $typeRaw, $notes] = $row;

            $species = $this->mapSpecies((string) $speciesRaw);
            $typeKey = mb_strtolower(trim((string) $typeRaw));
            $cleaningType = $typeMap[$typeKey] ?? null;

            if ($cleaningType === null) {
                $this->warn("Higienização pulada (tipo desconhecido '{$typeRaw}'): {$dateRaw}");

                continue;
            }

            $this->upsertByNaturalKey(
                FlockCleaning::class,
                ['species' => $species, 'cleaning_type' => $cleaningType],
                ['date' => $this->parseDate($dateRaw)],
                ['notes' => $notes !== null ? trim((string) $notes) : null, 'is_mock' => false]
            );
            $this->bump('flock_cleanings');
        }
    }
}
