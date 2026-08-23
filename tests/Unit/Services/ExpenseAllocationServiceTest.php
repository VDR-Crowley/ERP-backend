<?php

namespace Tests\Unit\Services;

use App\Models\Expense;
use App\Models\ExpenseSpeciesOverride;
use App\Models\Flock;
use App\Services\ExpenseAllocationService;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Porte de business-line-report.util.spec.ts (front) pros casos de
 * detectSpeciesMentions/classifyFlockSpecies/computeFlockRatio/allocateExpenseAmount.
 */
class ExpenseAllocationServiceTest extends TestCase
{
    private ExpenseAllocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExpenseAllocationService;
    }

    private function flockMock(): Collection
    {
        return collect([
            new Flock(['species' => 'Codornas', 'quantity' => 130]),
            new Flock(['species' => 'Galinhas Embrapa 051', 'quantity' => 32]),
        ]);
    }

    private function expense(array $overrides = []): Expense
    {
        return new Expense(array_merge([
            'date' => '2026-07-01',
            'description' => '',
            'category' => '',
            'amount' => 0,
            'paid' => true,
        ], $overrides));
    }

    public function test_detects_single_species_mention_plural_and_case_insensitive(): void
    {
        $this->assertSame(['quail'], $this->service->detectSpeciesMentions('Ração CODORNAS'));
        $this->assertSame(['chicken'], $this->service->detectSpeciesMentions('Galinhas Embrapa 051'));
    }

    public function test_detects_both_species_ordered_by_position_in_text(): void
    {
        $this->assertSame(['chicken', 'quail'], $this->service->detectSpeciesMentions('5 ovos Galinha + 50 Codorna'));
    }

    public function test_returns_empty_when_no_mention(): void
    {
        $this->assertSame([], $this->service->detectSpeciesMentions('Conta de energia'));
    }

    public function test_classifies_single_species_flock(): void
    {
        $this->assertSame('quail', $this->service->classifyFlockSpecies('Codornas'));
        $this->assertSame('chicken', $this->service->classifyFlockSpecies('Galinhas Embrapa 051'));
    }

    public function test_classify_returns_null_when_species_not_identified(): void
    {
        $this->assertNull($this->service->classifyFlockSpecies('Aves'));
    }

    public function test_ratio_is_proportional_to_real_flock_size(): void
    {
        $ratio = $this->service->computeFlockRatio($this->flockMock());

        $this->assertEqualsWithDelta(130 / 162, $ratio['quail'], 1e-5);
        $this->assertEqualsWithDelta(32 / 162, $ratio['chicken'], 1e-5);
    }

    public function test_ratio_falls_back_to_50_50_without_classifiable_flock(): void
    {
        $this->assertSame(['quail' => 0.5, 'chicken' => 0.5], $this->service->computeFlockRatio(collect()));
    }

    public function test_allocates_100_percent_to_species_mentioned_in_description(): void
    {
        $ratio = $this->service->computeFlockRatio($this->flockMock());
        $result = $this->service->allocateExpenseAmount(
            $this->expense(['description' => 'Ração codornas', 'category' => 'Ração', 'amount' => 106]),
            $ratio,
            null,
        );

        $this->assertSame(['quail' => 106.0, 'chicken' => 0.0], $result);
    }

    public function test_allocates_100_percent_to_species_mentioned_in_category_when_description_does_not(): void
    {
        $ratio = $this->service->computeFlockRatio($this->flockMock());
        $result = $this->service->allocateExpenseAmount(
            $this->expense(['description' => 'Compra de insumo', 'category' => 'Galinha', 'amount' => 50]),
            $ratio,
            null,
        );

        $this->assertSame(['quail' => 0.0, 'chicken' => 50.0], $result);
    }

    public function test_allocates_shared_expense_without_mention_proportionally_to_flock(): void
    {
        $ratio = $this->service->computeFlockRatio($this->flockMock());
        $result = $this->service->allocateExpenseAmount(
            $this->expense(['description' => 'Feno', 'category' => 'Insumo geral', 'amount' => 100]),
            $ratio,
            null,
        );

        $this->assertEqualsWithDelta(100 * (130 / 162), $result['quail'], 1e-5);
        $this->assertEqualsWithDelta(100 * (32 / 162), $result['chicken'], 1e-5);
        $this->assertEqualsWithDelta(100, $result['quail'] + $result['chicken'], 1e-5);
    }

    public function test_allocates_by_flock_when_text_mentions_both_species_at_once(): void
    {
        $ratio = $this->service->computeFlockRatio($this->flockMock());
        $result = $this->service->allocateExpenseAmount(
            $this->expense(['description' => 'Ração codornas e galinhas', 'category' => 'Ração', 'amount' => 200]),
            $ratio,
            null,
        );

        $this->assertEqualsWithDelta(200 * (130 / 162), $result['quail'], 1e-5);
        $this->assertEqualsWithDelta(200 * (32 / 162), $result['chicken'], 1e-5);
    }

    /**
     * Caso real reportado: despesa comprada como "Ração galinhas Embrapa" (descrição
     * menciona galinha) mas o usuário recategorizou manualmente pra "Codornas" porque
     * o saco foi de fato usado pras codornas. A categoria (campo editado deliberadamente
     * pelo usuário pra corrigir a classificação) tem que vencer a descrição.
     */
    public function test_category_takes_priority_over_description_when_they_diverge(): void
    {
        $ratio = $this->service->computeFlockRatio($this->flockMock());
        $result = $this->service->allocateExpenseAmount(
            $this->expense(['description' => 'Ração galinhas Embrapa', 'category' => 'Codornas', 'amount' => 106]),
            $ratio,
            null,
        );

        $this->assertSame(['quail' => 106.0, 'chicken' => 0.0], $result);
    }

    public function test_override_species_takes_priority_over_text_detection(): void
    {
        $ratio = $this->service->computeFlockRatio($this->flockMock());
        $override = new ExpenseSpeciesOverride(['species' => 'quail', 'reason' => 'Saco trocado']);

        $result = $this->service->allocateExpenseAmount(
            $this->expense(['description' => 'Saco de ração de galinha aberto pras codornas', 'category' => 'Ração Galinha', 'amount' => 106]),
            $ratio,
            $override,
        );

        $this->assertSame(['quail' => 106.0, 'chicken' => 0.0], $result);
    }

    public function test_without_override_row_keeps_automatic_text_detection(): void
    {
        $ratio = $this->service->computeFlockRatio($this->flockMock());
        $result = $this->service->allocateExpenseAmount(
            $this->expense(['description' => 'Compra de insumo', 'category' => 'Galinha', 'amount' => 50]),
            $ratio,
            null,
        );

        $this->assertSame(['quail' => 0.0, 'chicken' => 50.0], $result);
    }

    public function test_null_species_override_forces_flock_ratio_even_with_identifiable_text(): void
    {
        $ratio = $this->service->computeFlockRatio($this->flockMock());
        $override = new ExpenseSpeciesOverride(['species' => null, 'reason' => 'Forçar rateio']);

        $result = $this->service->allocateExpenseAmount(
            $this->expense(['description' => 'Ração codornas', 'category' => 'Ração', 'amount' => 100]),
            $ratio,
            $override,
        );

        $this->assertEqualsWithDelta(100 * (130 / 162), $result['quail'], 1e-5);
        $this->assertEqualsWithDelta(100 * (32 / 162), $result['chicken'], 1e-5);
    }
}
