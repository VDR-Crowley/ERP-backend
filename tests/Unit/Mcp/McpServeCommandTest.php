<?php

namespace Tests\Unit\Mcp;

use App\Console\Commands\McpServeCommand;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class McpServeCommandTest extends TestCase
{
    public function test_command_is_registered(): void
    {
        $this->assertArrayHasKey('mcp:serve', Artisan::all());
    }

    public function test_command_class_only_writes_warnings_to_stderr_helper(): void
    {
        // Garante que o comando não usa $this->info()/$this->line() (que vão pra STDOUT) —
        // só fwrite(STDERR, ...) e o retorno de Server::run(). Regressão-guard textual:
        // se alguém trocar por Artisan output, este teste falha.
        $source = file_get_contents((new \ReflectionClass(McpServeCommand::class))->getFileName());

        $this->assertStringNotContainsString('$this->info(', $source);
        $this->assertStringNotContainsString('$this->line(', $source);
        $this->assertStringContainsString('STDERR', $source);
    }
}
