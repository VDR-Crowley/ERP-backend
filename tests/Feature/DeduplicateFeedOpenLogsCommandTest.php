<?php

namespace Tests\Feature;

use App\Models\FeedOpenLog;
use App\Models\FeedStock;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Cenário sintético do bug real de produção (2026-08-24): sem trava no
 * endpoint `open-bag`, reenvio (double-click/retry no front) duplicou o
 * "Histórico de sacos abertos", cada cópia debitando `bags_in_stock`/
 * `kg_in_stock` de novo.
 *
 * A trava `feed_open_logs_duplicate_unique` (mesma migration da prevenção em
 * `OpenFeedStockBagRequest`) impede inserir duplicata via Eloquent/DB
 * normalmente, então o setup dropa o índice (se já existir) pra reproduzir o
 * estado de produção anterior à trava — exatamente o que
 * `feed-open-logs:deduplicate` precisa reparar. Condicional porque a
 * migration do índice é deployada em etapa separada: o comando precisa
 * funcionar tanto antes quanto depois dela.
 */
class DeduplicateFeedOpenLogsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $indexExists = collect(Schema::getIndexes('feed_open_logs'))
            ->contains(fn (array $index) => $index['name'] === 'feed_open_logs_duplicate_unique');

        if ($indexExists) {
            Schema::table('feed_open_logs', function (Blueprint $table) {
                $table->dropUnique('feed_open_logs_duplicate_unique');
            });
        }
    }

    public function test_dry_run_reports_duplicates_without_changing_anything(): void
    {
        ['feedStockA' => $feedStockA, 'feedStockC' => $feedStockC] = $this->seedDuplicatedOpenLogs();

        $this->artisan('feed-open-logs:deduplicate')->assertExitCode(0);

        $this->assertSame(7, FeedOpenLog::count());
        $this->assertSame(2, $feedStockA->fresh()->bags_in_stock);
        $this->assertSame(4, $feedStockC->fresh()->bags_in_stock);
    }

    public function test_force_removes_duplicates_and_restores_stock(): void
    {
        ['feedStockA' => $feedStockA, 'feedStockB' => $feedStockB, 'feedStockC' => $feedStockC] = $this->seedDuplicatedOpenLogs();

        $this->artisan('feed-open-logs:deduplicate', ['--force' => true])->assertExitCode(0);

        // 1 log sobrevive por grupo duplicado (A e B) + o log de C, que nunca foi duplicado.
        $this->assertSame(3, FeedOpenLog::count());

        // Estoque como se cada saco tivesse sido aberto uma única vez.
        $this->assertSame(4, $feedStockA->fresh()->bags_in_stock);
        $this->assertEqualsWithDelta(160.0, (float) $feedStockA->fresh()->kg_in_stock, 1e-8);
        $this->assertSame(11, $feedStockB->fresh()->bags_in_stock);
        $this->assertEqualsWithDelta(340.0, (float) $feedStockB->fresh()->kg_in_stock, 1e-8);
        $this->assertSame(4, $feedStockC->fresh()->bags_in_stock);
    }

    public function test_force_is_idempotent(): void
    {
        $this->seedDuplicatedOpenLogs();

        $this->artisan('feed-open-logs:deduplicate', ['--force' => true])->assertExitCode(0);
        $countAfterFirstRun = FeedOpenLog::count();

        $this->artisan('feed-open-logs:deduplicate', ['--force' => true])->assertExitCode(0);

        $this->assertSame($countAfterFirstRun, FeedOpenLog::count());
    }

    /**
     * @return array{feedStockA: FeedStock, feedStockB: FeedStock, feedStockC: FeedStock}
     */
    private function seedDuplicatedOpenLogs(): array
    {
        // Grupo duplicado A: 3 cópias do mesmo saco aberto, cada uma debitando 1 saco / 40kg na criação.
        $feedStockA = FeedStock::factory()->create(['type' => 'Codorna Postura', 'bags_in_stock' => 2, 'kg_in_stock' => 80]);
        FeedOpenLog::factory()->count(3)->create([
            'feed_stock_id' => $feedStockA->id,
            'feed_type' => 'Codorna Postura',
            'date' => '2026-08-20',
            'weight_kg' => 40,
        ]);

        // Grupo duplicado B: 3 cópias, cada uma debitando 1 saco / 30kg.
        $feedStockB = FeedStock::factory()->create(['type' => 'Frango Corte', 'bags_in_stock' => 9, 'kg_in_stock' => 280]);
        FeedOpenLog::factory()->count(3)->create([
            'feed_stock_id' => $feedStockB->id,
            'feed_type' => 'Frango Corte',
            'date' => '2026-08-21',
            'weight_kg' => 30,
        ]);

        // Saco aberto legítimo, não duplicado — não pode ser tocado pelo comando.
        $feedStockC = FeedStock::factory()->create(['type' => 'Codorna Corte', 'bags_in_stock' => 4, 'kg_in_stock' => 160]);
        FeedOpenLog::factory()->create([
            'feed_stock_id' => $feedStockC->id,
            'feed_type' => 'Codorna Corte',
            'date' => '2026-08-22',
            'weight_kg' => 40,
        ]);

        return compact('feedStockA', 'feedStockB', 'feedStockC');
    }
}
