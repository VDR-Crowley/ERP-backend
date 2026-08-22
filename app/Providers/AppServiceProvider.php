<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // API pura — sem envelope "data" nos Resources (só usamos JSON:API
        // wrapping se algum dia precisarmos, mas hoje simplifica o front).
        JsonResource::withoutWrapping();

        // Login: limita por IP+e-mail, pra nao travar todo mundo se um IP
        // ficar tentando senhas erradas pra varios e-mails diferentes.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(6)->by($request->ip().'|'.$request->input('email'));
        });

        // Reset de senha: codigo de 6 digitos = so 1 milhao de combinacoes,
        // throttle e obrigatorio aqui (nao so recomendado).
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip().'|'.$request->input('email'));
        });
    }
}
