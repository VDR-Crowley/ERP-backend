<?php

namespace App\Mcp;

use Mcp\Exception\ToolCallException;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\ToolHandlerInterface;

/**
 * Executa uma entrada de `ToolDefinitions` como um GET na API do MiniERP.
 *
 * Uma instância por tool, pareada com um `Mcp\Schema\Tool` via
 * `Mcp\Server\Builder::add()` — o caminho "explícito" do SDK, pra elementos
 * cujo nome/schema só são conhecidos em runtime (aqui: um array de definições).
 *
 * Por que não `Builder::addTool()` com uma closure: o `addTool()` manda a
 * closure pro `ReflectedElementLoader`, e o `ReferenceHandler` mapeia o bag de
 * argumentos JSON-RPC nos *parâmetros* da closure por nome, via reflection.
 * Uma closure `fn (array $args)` então explode com
 * "Missing required argument `args`" (-32603) em toda chamada. Só closures com
 * escopo ligado a `ReferenceHandler::class` recebem o bag cru — e é exatamente
 * isso que o `ExplicitElementLoader` (o caminho do `add()`) faz internamente.
 */
final class ToolHandler implements ToolHandlerInterface
{
    /**
     * @param  array{name: string, description: string, path: string, pathParams: list<array{name: string, description: string}>, queryParams: list<array{name: string, description: string}>}  $definition
     */
    public function __construct(
        private readonly array $definition,
        private readonly ErpApiClient $apiClient,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<mixed>
     */
    public function execute(array $arguments, ClientGateway $gateway): mixed
    {
        $pathParams = [];
        foreach ($this->definition['pathParams'] as $param) {
            $pathParams[$param['name']] = $arguments[$param['name']] ?? null;
        }

        $query = [];
        foreach ($this->definition['queryParams'] as $param) {
            $query[$param['name']] = $arguments[$param['name']] ?? null;
        }

        try {
            return $this->apiClient->get($this->definition['path'], $pathParams, $query);
        } catch (\LogicException|\Error $e) {
            // Bug no código (ex.: a guarda GET-only do ErpApiClient), não input do
            // usuário: deixa subir pro SDK virar -32603 + log de erro, em vez de
            // virar uma mensagem de tool "normal" indistinguível de erro da API.
            throw $e;
        } catch (\Throwable $e) {
            // Erro real da API/rede/token: o cliente MCP recebe isError: true com
            // a mensagem de verdade.
            throw new ToolCallException($e->getMessage(), previous: $e);
        }
    }
}
