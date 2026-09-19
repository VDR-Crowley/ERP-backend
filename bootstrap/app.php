<?php

use App\Http\Middleware\AuthenticateMcpHttp;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Backend API-only: nao existe rota web "login" para redirecionar
        // convidados nao autenticados. Sempre devolve 401 JSON.
        $middleware->redirectGuestsTo(fn () => null);

        // Aliases do Sanctum p/ checar abilities dos tokens (access vs
        // refresh) — nao vem registrado por padrao no pacote.
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'mcp.http-token' => AuthenticateMcpHttp::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Violação de unicidade no banco vira 409 (Conflict) em vez de 500 —
        // o import (import.ts) trata 409 como "já existe, ignora a linha".
        $exceptions->render(function (\Illuminate\Database\UniqueConstraintViolationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Já existe um registro com esses dados.'], 409);
            }

            return null;
        });
    })->create();
