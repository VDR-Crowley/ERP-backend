<?php

namespace App\Mcp;

use Mcp\Capability\Registry;
use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server;
use Mcp\Server\Builder;
use Mcp\Server\Stateless\StatelessProtocol;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Monta o `Mcp\Server` (STDIO) ou o `Mcp\Server\Stateless\StatelessProtocol`
 * (HTTP) a partir de `ToolDefinitions` — um par `Mcp\Schema\Tool` +
 * `App\Mcp\ToolHandler` por entrada, registrado via `Builder::add()` num
 * `Registry` próprio (via `setRegistry()`) pra ficar inspecionável nos testes.
 *
 * Os dois transportes (`php artisan mcp:serve` via `build()`, `POST /api/mcp`
 * via `buildStatelessProtocol()`) reusam a MESMA configuração de tools —
 * `registerTools()` é a única fonte de verdade de como as 29 tools viram
 * elementos do SDK. `build()` continua com o mesmo comportamento de antes.
 *
 * `Builder::add()` e não `addTool()`: ver o docblock de `ToolHandler` — o
 * `addTool()` faz o `ReferenceHandler` mapear os argumentos JSON-RPC nos
 * parâmetros do handler por nome (reflection), então uma closure
 * `fn (array $args)` morre com -32603 em toda chamada.
 */
final class McpServerFactory
{
    private ?Registry $registry = null;

    public function __construct(private readonly ErpApiClient $apiClient) {}

    public function build(): Server
    {
        $builder = $this->newBuilder();

        return $builder->build();
    }

    /** Contraparte HTTP de `build()` — mesma config de tools, protocolo stateless (SEP-2575). */
    public function buildStatelessProtocol(): StatelessProtocol
    {
        $builder = $this->newBuilder();

        return $builder->buildStateless();
    }

    private function newBuilder(): Builder
    {
        $registry = new Registry;
        $this->registry = $registry;

        $builder = Server::builder()
            ->setServerInfo('erp-mcp-php', '1.0.0')
            ->setLogger($this->makeLogger())
            ->setRegistry($registry);

        $this->registerTools($builder);

        return $builder;
    }

    private function registerTools(Builder $builder): void
    {
        foreach (ToolDefinitions::all() as $def) {
            $builder->add(
                new Tool(
                    name: $def['name'],
                    title: null,
                    inputSchema: $this->buildInputSchema($def),
                    description: $def['description'],
                    annotations: new ToolAnnotations(
                        readOnlyHint: true,
                        destructiveHint: false,
                        idempotentHint: true,
                        openWorldHint: false,
                    ),
                ),
                new ToolHandler($def, $this->apiClient),
            );
        }
    }

    /**
     * Logs do SDK vão pra STDERR — STDOUT é o canal do protocolo JSON-RPC
     * (mesma restrição documentada em `McpServeCommand`). Warning pra cima só,
     * pra não afogar o stderr do cliente MCP com o debug de cada tools/call.
     */
    private function makeLogger(): LoggerInterface
    {
        return new Logger('erp-mcp-php', [new StreamHandler('php://stderr', Level::Warning)]);
    }

    /** Só disponível depois de `build()`/`buildStatelessProtocol()` — usado pra inspecionar o que foi registrado. */
    public function registry(): Registry
    {
        return $this->registry ?? throw new \LogicException('Chame build() ou buildStatelessProtocol() antes de registry().');
    }

    /**
     * @param  array{pathParams: array, queryParams: array}  $def
     * @return array<string, mixed>
     */
    private function buildInputSchema(array $def): array
    {
        $properties = [];
        $required = [];

        foreach ($def['pathParams'] as $param) {
            $properties[$param['name']] = [
                'type' => 'integer',
                'minimum' => 1,
                'description' => $param['description'],
            ];
            $required[] = $param['name'];
        }

        foreach ($def['queryParams'] as $param) {
            $properties[$param['name']] = [
                'type' => 'string',
                'pattern' => '^\d{4}-\d{2}-\d{2}$',
                'description' => $param['description'],
            ];
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }
}
