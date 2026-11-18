<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | Railway Public API (GraphQL) — usado só pela tela admin de consumo
    | (GET /api/admin/railway-usage). Sem token o endpoint responde 503 com a
    | instrução de configuração; o build/boot não depende disso.
    |
    | - RAILWAY_API_TOKEN: token de conta/workspace (railway.com/account/tokens),
    |   enviado como `Authorization: Bearer`. Token de projeto NÃO serve (não lê
    |   usage).
    | - RAILWAY_PROJECT_ID: o próprio Railway injeta essa variável em todo
    |   service; localmente precisa setar à mão.
    | - Preços em USD por unidade da API (a API devolve consumo por minuto).
    |   Defaults = tabela pública da Railway; ajustar se a Railway mudar preço.
    */
    'railway' => [
        'token' => env('RAILWAY_API_TOKEN'),
        'project_id' => env('RAILWAY_PROJECT_ID'),
        'endpoint' => env('RAILWAY_API_ENDPOINT', 'https://backboard.railway.com/graphql/v2'),
        'timeout' => (int) env('RAILWAY_API_TIMEOUT', 10),
        'cache_seconds' => (int) env('RAILWAY_USAGE_CACHE_SECONDS', 300),
        // Crédito mensal do plano (Free = US$ 1; Hobby inclui US$ 5).
        'plan_credit_usd' => (float) env('RAILWAY_PLAN_CREDIT_USD', 1),
        'prices_usd' => [
            'CPU_USAGE' => (float) env('RAILWAY_PRICE_VCPU_MINUTE', 0.000463),       // US$ 20 / vCPU / mês
            'MEMORY_USAGE_GB' => (float) env('RAILWAY_PRICE_GB_RAM_MINUTE', 0.000231), // US$ 10 / GB / mês
            'NETWORK_TX_GB' => (float) env('RAILWAY_PRICE_GB_EGRESS', 0.05),           // US$ 0,05 / GB
            'DISK_USAGE_GB' => (float) env('RAILWAY_PRICE_GB_DISK_MINUTE', 0.00000347), // US$ 0,15 / GB / mês
            'BACKUP_USAGE_GB' => (float) env('RAILWAY_PRICE_GB_BACKUP_MINUTE', 0.00000347),
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
