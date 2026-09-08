<?php
declare(strict_types=1);

namespace WallyFootball\Controllers;

use RuntimeException;
use WallyFootball\Auth\WallyAuthClient;
use WallyFootball\Database\Connection;

class AuthController
{
    private WallyAuthClient $client;
    private Connection $db;

    public function __construct(?WallyAuthClient $client = null, ?Connection $db = null)
    {
        $this->client = $client ?? new WallyAuthClient();
        $this->db = $db ?? Connection::getInstance();
    }

    public function login(): void
    {
        $state = bin2hex(random_bytes(16));
        $verifier = $this->client->generateCodeVerifier();

        $_SESSION['oauth_state'] = $state;
        $_SESSION['oauth_verifier'] = $verifier;

        $authUrl = $this->client->getAuthorizationUrl($state, $verifier);
        header('Location: ' . $authUrl);
        exit;
    }

    public function callback(): void
    {
        $incomingState = $_GET['state'] ?? '';
        $savedState = $_SESSION['oauth_state'] ?? '';
        $verifier = $_SESSION['oauth_verifier'] ?? '';
        $code = $_GET['code'] ?? '';

        if (!$incomingState || $incomingState !== $savedState || !$code || !$verifier) {
            http_response_code(400);
            echo "Invalid or expired state parameter during authentication.";
            exit;
        }

        unset($_SESSION['oauth_state'], $_SESSION['oauth_verifier']);

        try {
            $tokens = $this->client->exchangeCodeForTokens($code, $verifier);
            $idToken = $tokens['id_token'] ?? '';
            $claims = $this->client->parseIdToken($idToken);

            $sub = $claims['sub'] ?? '';
            $email = $claims['email'] ?? '';
            $username = $claims['preferred_username'] ?? ($claims['name'] ?? explode('@', $email)[0]);
            $roles = $claims['roles'] ?? [];
            if (is_string($roles)) {
                $roles = explode(' ', $roles);
            }

            // Determine if user is admin
            $adminEmails = array_map('trim', explode(',', getenv('ADMIN_EMAILS') ?: 'wally@wallyatkins.com'));
            $isAdmin = in_array('admin', $roles, true) || in_array(strtolower($email), array_map('strtolower', $adminEmails), true);
            $role = $isAdmin ? 'admin' : 'player';

            // Upsert user in local database
            $user = $this->db->queryOne('SELECT id, role FROM users WHERE oidc_sub = :sub', ['sub' => $sub]);
            if ($user) {
                $this->db->execute(
                    'UPDATE users SET username = :name, email = :email, role = :role WHERE id = :id',
                    ['name' => $username, 'email' => $email, 'role' => $role, 'id' => $user['id']]
                );
                $userId = (int) $user['id'];
            } else {
                $userId = (int) $this->db->insert(
                    'INSERT INTO users (oidc_sub, username, email, role) VALUES (:sub, :name, :email, :role)',
                    ['sub' => $sub, 'name' => $username, 'email' => $email, 'role' => $role]
                );
            }

            $_SESSION['user'] = [
                'id' => $userId,
                'sub' => $sub,
                'username' => $username,
                'email' => $email,
                'role' => $role,
            ];

            header('Location: /pickem');
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo "Authentication error: " . htmlspecialchars($e->getMessage());
            exit;
        }
    }

    public function logout(): void
    {
        unset($_SESSION['user']);
        session_destroy();
        header('Location: /');
        exit;
    }
}
