<?php

declare(strict_types=1);

namespace WallyFootball\Services;

use WallyFootball\Database\Connection;

class DeviceAuthService
{
    public const COOKIE_NAME = 'wally_football_device';
    public const TOKEN_LIFETIME = 31536000; // 365 days (1 year)

    private Connection $db;

    public function __construct(?Connection $db = null)
    {
        $this->db = $db ?? Connection::getInstance();
    }

    /**
     * Create a new persistent device token for a user.
     * Returns the cookie value (userId:plaintextToken).
     */
    public function createToken(int $userId, ?string $userAgent = null): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_LIFETIME);
        $uaSubstr = $userAgent ? substr($userAgent, 0, 250) : null;

        $this->db->execute(
            'INSERT INTO user_device_tokens (user_id, token_hash, user_agent, expires_at, last_seen_at)
             VALUES (:uid, :thash, :ua, :exp, CURRENT_TIMESTAMP)',
            [
                'uid' => $userId,
                'thash' => $tokenHash,
                'ua' => $uaSubstr,
                'exp' => $expiresAt,
            ]
        );

        return $userId . ':' . $rawToken;
    }

    /**
     * Set the long-lived HTTP-only cookie on the client response.
     */
    public function setDeviceCookie(string $cookieValue): void
    {
        $domain = $this->determineCookieDomain();
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $options = [
            'expires' => time() + self::TOKEN_LIFETIME,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        if ($domain !== '') {
            $options['domain'] = $domain;
        }

        setcookie(self::COOKIE_NAME, $cookieValue, $options);
        $_COOKIE[self::COOKIE_NAME] = $cookieValue;
    }

    /**
     * Clear the device cookie from client and $_COOKIE.
     */
    public function clearDeviceCookie(): void
    {
        $domain = $this->determineCookieDomain();
        $options = [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if ($domain !== '') {
            $options['domain'] = $domain;
        }

        setcookie(self::COOKIE_NAME, '', $options);
        unset($_COOKIE[self::COOKIE_NAME]);
    }

    /**
     * Validate a device cookie string and return the authenticated user record if valid.
     * Updates last_seen_at on success.
     *
     * @return array<string, mixed>|null
     */
    public function validateToken(?string $cookie): ?array
    {
        if (empty($cookie) || !is_string($cookie)) {
            return null;
        }

        $parts = explode(':', $cookie, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0]) || empty($parts[1])) {
            return null;
        }

        $userId = (int) $parts[0];
        $rawToken = $parts[1];
        $tokenHash = hash('sha256', $rawToken);

        $now = date('Y-m-d H:i:s');
        $row = $this->db->queryOne(
            'SELECT id, user_id FROM user_device_tokens 
             WHERE user_id = :uid AND token_hash = :thash AND expires_at > :now',
            [
                'uid' => $userId,
                'thash' => $tokenHash,
                'now' => $now,
            ]
        );

        if (!$row) {
            return null;
        }

        // Update last_seen_at
        $this->db->execute(
            'UPDATE user_device_tokens SET last_seen_at = CURRENT_TIMESTAMP WHERE id = :id',
            ['id' => $row['id']]
        );

        // Fetch full user record from users table
        $user = $this->db->queryOne(
            'SELECT id, oidc_sub, username, email, role, avatar_url FROM users WHERE id = :uid',
            ['uid' => $userId]
        );

        if (!$user) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'sub' => $user['oidc_sub'] ?? '',
            'username' => $user['username'] ?? '',
            'email' => $user['email'] ?? '',
            'role' => $user['role'] ?? 'player',
            'picture' => $user['avatar_url'] ?? null,
            'avatar_url' => $user['avatar_url'] ?? null,
        ];
    }

    /**
     * Revoke a single device token (used on logout).
     */
    public function revokeToken(?string $cookie): void
    {
        if (!empty($cookie) && is_string($cookie)) {
            $parts = explode(':', $cookie, 2);
            if (count($parts) === 2 && ctype_digit($parts[0]) && !empty($parts[1])) {
                $userId = (int) $parts[0];
                $tokenHash = hash('sha256', $parts[1]);
                $this->db->execute(
                    'DELETE FROM user_device_tokens WHERE user_id = :uid AND token_hash = :thash',
                    ['uid' => $userId, 'thash' => $tokenHash]
                );
            }
        }

        $this->clearDeviceCookie();
    }

    /**
     * Revoke all device tokens for a given user.
     */
    public function revokeAllForUser(int $userId): void
    {
        $this->db->execute(
            'DELETE FROM user_device_tokens WHERE user_id = :uid',
            ['uid' => $userId]
        );
    }

    /**
     * Determine appropriate cookie domain (.wallyatkins.com on production, empty on local).
     */
    private function determineCookieDomain(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (str_contains($host, 'wallyatkins.com')) {
            return '.wallyatkins.com';
        }
        return '';
    }
}
