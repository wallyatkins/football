<?php

declare(strict_types=1);

namespace WallyFootball\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WallyFootball\Database\Connection;
use WallyFootball\Services\DeviceAuthService;

class DeviceAuthServiceTest extends TestCase
{
    private Connection $db;
    private DeviceAuthService $service;
    private int $testUserId;

    protected function setUp(): void
    {
        $this->db = Connection::getInstance();
        $this->service = new DeviceAuthService($this->db);

        // Ensure user_device_tokens table exists
        $migrationFile = dirname(__DIR__, 2) . '/db/migrations/005_device_tokens.sql';
        if (file_exists($migrationFile)) {
            $sql = file_get_contents($migrationFile);
            if ($sql) {
                $this->db->execute($sql);
            }
        }

        // Create or fetch a test user
        $user = $this->db->queryOne('SELECT id FROM users WHERE username = :u', ['u' => 'test_device_user']);
        if ($user) {
            $this->testUserId = (int) $user['id'];
        } else {
            $this->testUserId = (int) $this->db->insert(
                'INSERT INTO users (oidc_sub, username, email, role, avatar_url)
                 VALUES (:sub, :name, :email, :role, :av)',
                [
                    'sub' => 'sub_test_device_123',
                    'name' => 'test_device_user',
                    'email' => 'test_device@wallyatkins.com',
                    'role' => 'player',
                    'av' => 'https://wallyatkins.com/avatar.png',
                ]
            );
        }

        // Clean any existing device tokens for test user
        $this->service->revokeAllForUser($this->testUserId);
    }

    protected function tearDown(): void
    {
        $this->service->revokeAllForUser($this->testUserId);
    }

    public function testCreateTokenReturnsValidCookieFormatAndStoresInDatabase(): void
    {
        $cookieValue = $this->service->createToken($this->testUserId, 'Mozilla/5.0 TestBrowser');

        $this->assertNotEmpty($cookieValue);
        $this->assertStringContainsString(':', $cookieValue);

        [$userId, $token] = explode(':', $cookieValue, 2);
        $this->assertSame((string) $this->testUserId, $userId);
        $this->assertSame(64, strlen($token));

        $tokenHash = hash('sha256', $token);
        $row = $this->db->queryOne(
            'SELECT * FROM user_device_tokens WHERE user_id = :uid AND token_hash = :thash',
            ['uid' => $this->testUserId, 'thash' => $tokenHash]
        );

        $this->assertNotNull($row);
        $this->assertSame($this->testUserId, (int) $row['user_id']);
        $this->assertSame('Mozilla/5.0 TestBrowser', $row['user_agent']);
    }

    public function testValidateTokenReturnsUserRecordOnValidToken(): void
    {
        $cookieValue = $this->service->createToken($this->testUserId, 'ValidTestAgent');

        $user = $this->service->validateToken($cookieValue);

        $this->assertNotNull($user);
        $this->assertSame($this->testUserId, $user['id']);
        $this->assertSame('test_device_user', $user['username']);
        $this->assertSame('test_device@wallyatkins.com', $user['email']);
        $this->assertSame('player', $user['role']);
    }

    public function testValidateTokenReturnsNullOnInvalidOrMalformedCookie(): void
    {
        $this->assertNull($this->service->validateToken(null));
        $this->assertNull($this->service->validateToken(''));
        $this->assertNull($this->service->validateToken('invalidformat'));
        $this->assertNull($this->service->validateToken('abc:def'));
        $this->assertNull($this->service->validateToken($this->testUserId . ':wrongtoken123'));
    }

    public function testValidateTokenReturnsNullOnExpiredToken(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiredTime = date('Y-m-d H:i:s', time() - 3600); // 1 hour ago

        $this->db->execute(
            'INSERT INTO user_device_tokens (user_id, token_hash, user_agent, expires_at)
             VALUES (:uid, :thash, :ua, :exp)',
            [
                'uid' => $this->testUserId,
                'thash' => $tokenHash,
                'ua' => 'ExpiredAgent',
                'exp' => $expiredTime,
            ]
        );

        $cookieValue = $this->testUserId . ':' . $rawToken;
        $this->assertNull($this->service->validateToken($cookieValue));
    }

    public function testRevokeTokenDeletesFromDatabase(): void
    {
        $cookieValue = $this->service->createToken($this->testUserId);
        $this->assertNotNull($this->service->validateToken($cookieValue));

        $this->service->revokeToken($cookieValue);

        $this->assertNull($this->service->validateToken($cookieValue));
    }

    public function testRevokeAllForUserClearsMultipleTokens(): void
    {
        $c1 = $this->service->createToken($this->testUserId, 'Device1');
        $c2 = $this->service->createToken($this->testUserId, 'Device2');

        $this->assertNotNull($this->service->validateToken($c1));
        $this->assertNotNull($this->service->validateToken($c2));

        $this->service->revokeAllForUser($this->testUserId);

        $this->assertNull($this->service->validateToken($c1));
        $this->assertNull($this->service->validateToken($c2));
    }
}
