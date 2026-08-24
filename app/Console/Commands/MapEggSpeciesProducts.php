<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Ativa o estoque calculado (`Product::stock()`) nos 2 produtos de ovo
 * unitário, setando `egg_species`. Escopo fechado de propósito — só esses 2
 * nomes exatos (aprovado pelo usuário 2026-08-24): produtos que dividiriam a
 * mesma produção com outro, misturam espécies, ou não têm `eggs_per_unit`
 * (ex.: ovos férteis) ficam de fora até existir regra de rateio.
 *
 * Roda em modo dry-run por padrão (só mostra o estoque calculado que cada
 * produto passaria a ter); só grava com `--force`. Idempotente.
 */
class MapEggSpeciesProducts extends Command
{
    protected $signature = 'products:map-egg-species {--force : Executa de verdade (seta egg_species); sem essa flag só mostra o que seria feito}';

    protected $description = 'Mapeia os produtos de ovo unitário pra egg_species, ativando o estoque calculado do Plantel';

    /** @var array<string, string> nome exato do produto => egg_species */
    private const MAPPING = [
        '1 Bandeja de ovos de galinha' => 'chicken',
        '50 ovos de codorna' => 'quail',
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $rows = [];
        $toApply = [];

        foreach (self::MAPPING as $name => $species) {
            $product = Product::query()->where('name', $name)->first();

            if (! $product) {
                $this->warn("Produto não encontrado: \"{$name}\" — pulando.");

                continue;
            }

            if ($product->egg_species === $species) {
                $rows[] = [$product->id, $product->name, $species, 'já mapeado', $product->stock];

                continue;
            }

            $toApply[] = $product;

            // Estoque calculado após o mapeamento, sem gravar ainda.
            $product->egg_species = $species;
            $rows[] = [$product->id, $product->name, $species, $force ? 'aplicado' : 'seria aplicado', $product->stock];
        }

        $this->table(['ID', 'Produto', 'egg_species', 'Status', 'Estoque calculado (Plantel)'], $rows);

        if (! $force) {
            $this->info('Dry-run. Rode com --force pra gravar egg_species nos produtos acima.');

            return self::SUCCESS;
        }

        foreach ($toApply as $product) {
            $product->save();
        }

        $this->info(count($toApply).' produto(s) mapeado(s).');

        return self::SUCCESS;
    }
}
