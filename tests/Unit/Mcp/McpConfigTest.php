<?php

namespace Tests\Unit\Mcp;

use App\Mcp\McpConfig;
use Tests\TestCase;

class McpConfigTest extends TestCase
{
    public function test_resolve_base_url_uses_env_when_set(): void
    {
        $this->assertSame(
            'https://laravel-production-4c67.up.railway.app/api',
            McpConfig::resolveBaseUrl('https://laravel-production-4c67.up.railway.app/api/', 'http://localhost'),
        );
    }

    public function test_resolve_base_url_falls_back_to_app_url_when_env_unset(): void
    {
        $this->assertSame('http://localhost/api', McpConfig::resolveBaseUrl(null, 'http://localhost'));
    }

    public function test_resolve_base_url_falls_back_to_app_url_when_env_blank(): void
    {
        $this->assertSame('http://localhost/api', McpConfig::resolveBaseUrl('   ', 'http://localhost'));
    }

    public function test_resolve_base_url_strips_trailing_slash(): void
    {
        $this->assertSame('http://localhost/api', McpConfig::resolveBaseUrl(null, 'http://localhost/'));
    }

    public function test_resolve_token_trims_whitespace(): void
    {
        $this->assertSame('abc123', McpConfig::resolveToken(' abc123 '));
    }

    public function test_resolve_token_returns_null_when_unset(): void
    {
        $this->assertNull(McpConfig::resolveToken(null));
    }

    public function test_resolve_token_treats_blank_as_null(): void
    {
        $this->assertNull(McpConfig::resolveToken('   '));
    }

    public function test_config_file_is_wired_to_env(): void
    {
        $this->assertSame('https://mcp-test.example/api', config('mcp.base_url'));
        $this->assertSame('test-token-123', config('mcp.token'));
    }
}
