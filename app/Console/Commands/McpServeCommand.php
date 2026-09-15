<?php

namespace App\Console\Commands;

use App\Mcp\McpServerFactory;
use Illuminate\Console\Command;
use Mcp\Server\Transport\StdioTransport;

/**
 * Sobe o servidor MCP (PHP) em modo STDIO, pra um cliente MCP (Claude Code/
 * Desktop) rodar como subprocesso. Ver docs/MCP.md.
 *
 * IMPORTANTE: STDOUT é o canal do protocolo JSON-RPC — nunca escrever nada
 * nele fora do que o próprio SDK escreve. Avisos/logs sempre em STDERR.
 */
class McpServeCommand extends Command
{
    protected $signature = 'mcp:serve';

    protected $description = 'Sobe o servidor MCP de leitura (STDIO) que expõe a API do MiniERP como tools';

    public function __construct(private readonly McpServerFactory $factory)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        fwrite(STDERR, "[erp-mcp-php] Servidor MCP read-only rodando (leitura direta via Eloquent, mesma conexão de banco do app).\n");

        $exitCode = $this->factory->build()->run(new StdioTransport);

        return \is_int($exitCode) ? $exitCode : self::SUCCESS;
    }
}
