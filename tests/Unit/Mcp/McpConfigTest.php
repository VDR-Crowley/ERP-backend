<?php

namespace Tests\Unit\Mcp;

use App\Mcp\McpConfig;
use Tests\TestCase;

class McpConfigTest extends TestCase
{
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
        $this->assertSame('test-mcp-http-token-456', config('mcp.http_token'));
    }
}
