<?php

declare(strict_types=1);

namespace WallyFootball\Auth;

use RuntimeException;

class WallyAuthClient
{
    private string $issuer;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct(
        ?string $issuer = null,
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?string $redirectUri = null
    ) {
        $this->issuer = rtrim($issuer ?? (getenv('OIDC_ISSUER') ?: 'https://auth.wallyatkins.com'), '/');
        $this->clientId = $clientId ?? (getenv('OIDC_CLIENT_ID') ?: 'football');
        $this->clientSecret = $clientSecret ?? (getenv('OIDC_CLIENT_SECRET') ?: 'JQYaOCj_CtgZg_uuAx7dsBnB7BOMnE5waS5xHn330vY');
        $this->redirectUri = $redirectUri ?? (getenv('OIDC_REDIRECT_URI') ?: 'https://football.wallyatkins.com/auth/callback');
    }

    public function generateCodeVerifier(): string
    {
        return $this->base64UrlEncode(random_bytes(32));
    }

    public function generateCodeChallenge(string $codeVerifier): string
    {
        return $this->base64UrlEncode(hash('sha256', $codeVerifier, true));
    }

    public function getAuthorizationUrl(string $state, string $codeVerifier, string $scope = 'openid profile email roles'): string
    {
        $challenge = $this->generateCodeChallenge($codeVerifier);

        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => $scope,
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ];

        return $this->issuer . '/oauth/authorize?' . http_build_query($params);
    }

    public function exchangeCodeForTokens(string $code, string $codeVerifier): array
    {
        $tokenUrl = $this->issuer . '/oauth/token';

        $postData = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Failed to communicate with WallyAuth token endpoint: ' . $error);
        }

        $json = json_decode($response, true);
        if ($httpCode !== 200 || !is_array($json) || empty($json['access_token'])) {
            $msg = $json['error_description'] ?? ($json['error'] ?? 'HTTP ' . $httpCode);
            throw new RuntimeException('WallyAuth token exchange failed: ' . $msg);
        }

        return $json;
    }

    public function parseIdToken(string $idToken): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed JWT ID token structure.');
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        if (!is_array($payload)) {
            throw new RuntimeException('Failed to decode ID token payload.');
        }

        return $payload;
    }

    public function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
