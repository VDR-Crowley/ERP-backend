<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Trava contra duplicata em reimportação de planilha (date+description+category+amount). */
class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(), ['access']);
    }

    public function test_store_rejects_exact_duplicate(): void
    {
        Expense::factory()->create([
            'date' => '2026-08-20',
            'description' => 'Ração Codorna',
            'category' => 'Alimentação',
            'amount' => 150.00,
        ]);

        // Duplicata exata (date+description+category+amount) vira 409 — o import
        // (import.ts) trata 409 como "já existe, ignora a linha" em vez de falhar.
        // Ver bootstrap/app.php (UniqueConstraintViolation -> 409).
        $this->postJson('/api/expenses', [
            'date' => '2026-08-20',
            'description' => 'Ração Codorna',
            'category' => 'Alimentação',
            'amount' => 150.00,
            'paid' => true,
        ])->assertStatus(409);

        $this->assertSame(1, Expense::count());
    }

    public function test_store_allows_expense_with_different_amount_on_same_day(): void
    {
        Expense::factory()->create([
            'date' => '2026-08-20',
            'description' => 'Ração Codorna',
            'category' => 'Alimentação',
            'amount' => 150.00,
        ]);

        $this->postJson('/api/expenses', [
            'date' => '2026-08-20',
            'description' => 'Ração Codorna',
            'category' => 'Alimentação',
            'amount' => 200.00,
            'paid' => true,
        ])->assertCreated();

        $this->assertSame(2, Expense::count());
    }

    public function test_store_allows_same_expense_in_a_different_category(): void
    {
        Expense::factory()->create([
            'date' => '2026-08-20',
            'description' => 'Compra diversa',
            'category' => 'Alimentação',
            'amount' => 150.00,
        ]);

        $this->postJson('/api/expenses', [
            'date' => '2026-08-20',
            'description' => 'Compra diversa',
            'category' => 'Manutenção',
            'amount' => 150.00,
            'paid' => true,
        ])->assertCreated();

        $this->assertSame(2, Expense::count());
    }
}
