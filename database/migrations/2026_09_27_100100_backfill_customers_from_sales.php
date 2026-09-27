<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill do CRM: cria um `customer` pra cada comprador (`buyer`) distinto que
 * já existe em `sales`. Idempotente (insertOrIgnore respeita o unique de `name`),
 * então rodar de novo não duplica. `phone` fica null — é preenchido no CRM.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $names = DB::table('sales')
            ->select('buyer')
            ->whereNotNull('buyer')
            ->where('buyer', '<>', '')
            ->distinct()
            ->pluck('buyer');

        $rows = $names->map(fn ($name) => [
            'name' => $name,
            'phone' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('customers')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        // Sem down específico: os customers criados por aqui não são
        // distinguíveis dos criados manualmente. A tabela some no rollback da
        // migration de criação.
    }
};
