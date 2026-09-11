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
        // STDOUT é o canal do protocolo JSON-RPC: o comando só pode escrever em
        // STDERR (fwrite) e deixar o SDK falar em STDOUT. Regressão-guard textual —
        // cobre a superfície inteira de output do Artisan/PHP, não só info()/line().
        // php_strip_whitespace() tira comentários — senão o próprio comentário
        // "STDOUT é o canal do protocolo" faria o teste falhar.
        $source = php_strip_whitespace((new \ReflectionClass(McpServeCommand::class))->getFileName());

        $this->assertDoesNotMatchRegularExpression(
            '/\$this->(info|line|comment|warn|error|question|alert|newLine|table|write|writeln|withProgressBar|components|output)\s*[(\->]/',
            $source,
            'O comando não pode usar os helpers de output do Artisan — eles vão pra STDOUT.',
        );

        $this->assertDoesNotMatchRegularExpression(
            '/(^|[^\w$>])(echo|print|printf|vprintf|var_dump|print_r|dump|dd)\s*[("\'$]/m',
            $source,
            'O comando não pode escrever direto em STDOUT.',
        );

        $this->assertDoesNotMatchRegularExpression(
            '/(STDOUT|php:\/\/stdout|php:\/\/output)/i',
            $source,
            'STDOUT é do protocolo — nada do comando pode escrever nele.',
        );

        $this->assertStringContainsString('fwrite(STDERR', $source);
    }
}
