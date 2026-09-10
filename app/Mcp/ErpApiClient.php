<?php

namespace App\Mcp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Client HTTP pra API do MiniERP. GET-only por construção: `get()` é o
 * único método público, e o `request()` interno recusa qualquer verbo
 * diferente de GET — defesa em profundidade, mesmo que hoje nada no código
 * chame com outro verbo (mesmo desenho do client Node em ../ERP-MCP).
 */
class ErpApiClient
{
    /**
     * @param  array<string, int|string>  $pathParams
     * @param  array<string, int|string|bool|null>  $query
     */
    public function get(string $path, array $pathParams = [], array $query = []): array
    {
        return $this->request('GET', $path, $pathParams, $query);
    }

    /**
     * @param  array<string, int|string>  $pathParams
     * @param  array<string, int|string|bool|null>  $query
     */
    private function request(string $method, string $path, array $pathParams, array $query): array
    {
        if ($method !== 'GET') {
            throw new \LogicException(
                "ErpApiClient é somente leitura: recusou emitir uma requisição {$method} ".
                '(isso é um bug no código, não input do usuário).',
            );
        }

        $token = config('mcp.token');
        if (empty($token)) {
            throw new MissingTokenException;
        }

        $url = $this->buildUrl($path, $pathParams, $query);

        try {
            $response = Http::withToken($token)->acceptJson()->get($url);
        } catch (ConnectionException $e) {
            throw new ApiUnreachableException($url, $e);
        }

        if ($response->failed()) {
            $body = $response->json() ?? $response->body();

            throw new ErpApiException(
                $this->formatErrorMessage($response->status(), $body),
                $response->status(),
                $url,
                $body,
            );
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, int|string>  $pathParams
     * @param  array<string, int|string|bool|null>  $query
     */
    private function buildUrl(string $path, array $pathParams, array $query): string
    {
        $resolvedPath = preg_replace_callback(
            '/\{([^}]+)\}/',
            function (array $matches) use ($pathParams, $path) {
                $name = $matches[1];
                $value = $pathParams[$name] ?? null;

                if ($value === null || $value === '') {
                    throw new \InvalidArgumentException("Parâmetro de path obrigatório \"{$name}\" faltando pra {$path}");
                }

                return rawurlencode((string) $value);
            },
            $path,
        );

        $baseUrl = rtrim((string) config('mcp.base_url'), '/');
        $url = $baseUrl.$resolvedPath;

        $query = array_filter($query, static fn ($value) => $value !== null);

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    private function formatErrorMessage(int $status, mixed $body): string
    {
        $bodyMessage = \is_array($body) && \is_string($body['message'] ?? null) ? $body['message'] : null;
        $base = "API do MiniERP retornou {$status}";

        if ($status === 401) {
            return "{$base}. Token ausente, inválido, expirado ou revogado (access tokens expiram em 2h) — ".
                'pegue um novo (veja docs/MCP.md "Como obter um token").'.
                ($bodyMessage ? " Mensagem da API: {$bodyMessage}" : '');
        }

        if ($status === 403) {
            return "{$base}. Token autenticado mas sem a ability \"access\" pra essa rota ".
                '(ex.: um refresh token foi usado aqui).'.
                ($bodyMessage ? " Mensagem da API: {$bodyMessage}" : '');
        }

        if ($status === 404) {
            return "{$base}. O recurso pedido não existe.".
                ($bodyMessage ? " Mensagem da API: {$bodyMessage}" : '');
        }

        return "{$base}.".($bodyMessage ? " Mensagem da API: {$bodyMessage}" : '').
            ' Corpo completo da resposta: '.json_encode($body);
    }
}
