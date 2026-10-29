<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::get('/_test/proxy', fn (Request $request) => response()->json([
            'ip' => $request->ip(),
            'secure' => $request->isSecure(),
            'host' => $request->getHost(),
        ]));
    }

    public function test_configured_proxy_supplies_client_ip_and_https_without_forwarded_host(): void
    {
        config(['trustedproxy.proxies' => ['172.30.250.2']]);
        $this->withServerVariables(['REMOTE_ADDR' => '172.30.250.2'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'attacker.example'])
            ->get('http://api.learnsignia.com/_test/proxy')
            ->assertOk()->assertExactJson(['ip' => '203.0.113.9', 'secure' => true, 'host' => 'api.learnsignia.com']);
    }

    public function test_untrusted_peer_cannot_spoof_ip_or_https(): void
    {
        config(['trustedproxy.proxies' => ['172.30.250.2']]);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25'])
            ->withHeaders(['X-Forwarded-For' => '198.51.100.5', 'X-Forwarded-Proto' => 'https'])
            ->get('http://api.learnsignia.com/_test/proxy')
            ->assertOk()->assertJson(['ip' => '203.0.113.25', 'secure' => false]);
    }

    public function test_no_proxy_is_trusted_when_setting_is_empty(): void
    {
        config(['trustedproxy.proxies' => []]);
        $this->withServerVariables(['REMOTE_ADDR' => '172.30.250.2'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])
            ->get('http://api.learnsignia.com/_test/proxy')
            ->assertOk()->assertJson(['ip' => '172.30.250.2', 'secure' => false]);
    }
}
