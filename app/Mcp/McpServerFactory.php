<?php

namespace App\Mcp;

use Mcp\Capability\Registry;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server;

/**
 * Monta o `Mcp\Server` a partir de `ToolDefinitions` — um `addTool()` por
 * entrada, registrado num `Registry` próprio (via `setRegistry()`) pra ficar
 * inspecionável nos testes depois de `build()`.
 */
final class McpServerFactory
{
    private ?Registry $registry = null;

    public function __construct(private readonly ErpApiClient $apiClient) {}

    public function build(): Server
    {
        $registry = new Registry;
        $this->registry = $registry;

        $builder = Server::builder()
            ->setServerInfo('erp-mcp-php', '1.0.0')
            ->setRegistry($registry);

        foreach (ToolDefinitions::all() as $def) {
            $builder->addTool(
                handler: fn (array $args) => $this->callTool($def, $args),
                name: $def['name'],
                description: $def['description'],
                inputSchema: $this->buildInputSchema($def),
                annotations: new ToolAnnotations(
                    readOnlyHint: true,
                    destructiveHint: false,
                    idempotentHint: true,
                    openWorldHint: false,
                ),
            );
        }

        return $builder->build();
    }

    /** Só disponível depois de `build()` — usado pra inspecionar o que foi registrado. */
    public function registry(): Registry
    {
        return $this->registry ?? throw new \LogicException('Chame build() antes de registry().');
    }

    /**
     * @param  array{name: string, description: string, path: string, pathParams: array, queryParams: array}  $def
     * @param  array<string, mixed>  $args
     */
    private function callTool(array $def, array $args): array
    {
        $pathParams = [];
        foreach ($def['pathParams'] as $param) {
            $pathParams[$param['name']] = $args[$param['name']] ?? null;
        }

        $query = [];
        foreach ($def['queryParams'] as $param) {
            $query[$param['name']] = $args[$param['name']] ?? null;
        }

        try {
            return $this->apiClient->get($def['path'], $pathParams, $query);
        } catch (\Throwable $e) {
            throw new ToolCallException($e->getMessage(), previous: $e);
        }
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
