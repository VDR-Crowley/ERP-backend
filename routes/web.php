<?php

use Illuminate\Support\Facades\Route;

// Backend API-only: sem views. Rotas de negócio ficam em routes/api.php.
// Health check padrão do Laravel disponível em GET /up.
Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'status' => 'ok',
    ]);
});
