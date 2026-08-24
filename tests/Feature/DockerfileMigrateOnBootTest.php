<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guarda contra regressão do incidente real: `flock_incubations`/`hatch_events`
 * nunca migraram em produção porque `migrate --force` só rodava no Pre-Deploy
 * Command do Railway — config manual do dashboard, fora do repositório, sem
 * garantia nenhuma de código. Ver docs/deploy-railway.md e
 * tests/Feature/FlockIncubationImportTest.php pro caso real que expôs isso
 * (500 puro — `Illuminate\Database\QueryException` de tabela inexistente —
 * pra um payload de import 100% válido).
 *
 * Não builda a imagem Docker (custoso demais pra um teste de unidade), só
 * garante que o `CMD` do Dockerfile continua rodando `migrate --force` antes
 * de subir o servidor — a rede de segurança que faz o App Service nunca
 * servir HTTP com schema desatualizado, mesmo que o Pre-Deploy Command do
 * Railway tenha sido pulado, apagado ou nunca configurado.
 */
class DockerfileMigrateOnBootTest extends TestCase
{
    public function test_cmd_runs_migrate_before_starting_the_server(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));

        $cmdLine = collect(explode("\n", $dockerfile))
            ->first(fn (string $line) => str_starts_with(trim($line), 'CMD '));

        $this->assertNotNull($cmdLine, 'Dockerfile deve ter uma instrução CMD.');
        $this->assertStringContainsString('php artisan migrate --force', $cmdLine);
        $this->assertMatchesRegularExpression(
            '/migrate --force\s*&&.*artisan serve/',
            $cmdLine,
            'migrate --force precisa rodar ANTES do "php artisan serve" no CMD.',
        );
    }
}
