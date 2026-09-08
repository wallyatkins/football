<?php
declare(strict_types=1);

namespace WallyFootball\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WallyFootball\Auth\WallyAuthClient;

class WallyAuthClientTest extends TestCase
{
    private WallyAuthClient $client;

    protected function setUp(): void
    {
        $this->client = new WallyAuthClient(
            'https://auth.wallyatkins.com',
            'football',
            'test_secret',
            'https://football.wallyatkins.com/auth/callback'
        );
    }

    public function testGenerateCodeVerifierProducesUrlSafeString(): void
    {
        $verifier = $this->client->generateCodeVerifier();
        $this->assertNotEmpty($verifier);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $verifier);
        $this->assertGreaterThanOrEqual(43, strlen($verifier));
    }

    public function testGenerateCodeChallengeProducesExpectedHash(): void
    {
        $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $challenge = $this->client->generateCodeChallenge($verifier);
        $expectedChallenge = $this->client->base64UrlEncode(hash('sha256', $verifier, true));

        $this->assertSame($expectedChallenge, $challenge);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $challenge);
    }

    public function testGetAuthorizationUrlContainsExpectedParameters(): void
    {
        $state = 'random_state_123';
        $verifier = $this->client->generateCodeVerifier();
        $authUrl = $this->client->getAuthorizationUrl($state, $verifier);

        $this->assertStringStartsWith('https://auth.wallyatkins.com/oauth/authorize?', $authUrl);
        $parts = parse_url($authUrl);
        parse_str($parts['query'], $queryParams);

        $this->assertSame('code', $queryParams['response_type']);
        $this->assertSame('football', $queryParams['client_id']);
        $this->assertSame('https://football.wallyatkins.com/auth/callback', $queryParams['redirect_uri']);
        $this->assertSame('random_state_123', $queryParams['state']);
        $this->assertSame('S256', $queryParams['code_challenge_method']);
        $this->assertNotEmpty($queryParams['code_challenge']);
    }

    public function testParseIdTokenDecodesValidJwt(): void
    {
        $header = $this->client->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->client->base64UrlEncode(json_encode([
            'sub' => 'user-12345',
            'email' => 'wally@wallyatkins.com',
            'preferred_username' => 'wally',
            'roles' => ['admin', 'player'],
        ]));
        $signature = $this->client->base64UrlEncode('fakesignature');
        $fakeJwt = "{$header}.{$payload}.{$signature}";

        $claims = $this->client->parseIdToken($fakeJwt);
        $this->assertSame('user-12345', $claims['sub']);
        $this->assertSame('wally@wallyatkins.com', $claims['email']);
        $this->assertSame('wally', $claims['preferred_username']);
        $this->assertContains('admin', $claims['roles']);
    }
}
