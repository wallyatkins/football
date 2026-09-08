<?php

declare(strict_types=1);

namespace WallyFootball\Support;

class TeamData
{
    private static ?array $teams = null;

    private static array $aliases = [
        'LA'  => 'LAR',
        'STL' => 'LAR',
        'WSH' => 'WAS',
        'JAC' => 'JAX',
        'OAK' => 'LV',
        'SD'  => 'LAC',
    ];

    public static function normalize(string $abbr): string
    {
        $abbr = strtoupper(trim($abbr));
        return self::$aliases[$abbr] ?? $abbr;
    }

    public static function load(): array
    {
        if (self::$teams === null) {
            $path = __DIR__ . '/teams.json';
            if (file_exists($path)) {
                self::$teams = json_decode(file_get_contents($path), true) ?: [];
            } else {
                self::$teams = [];
            }
        }
        return self::$teams;
    }

    public static function get(string $abbr): array
    {
        $abbr = strtoupper(trim($abbr));
        $teams = self::load();

        if (isset($teams[$abbr])) {
            return $teams[$abbr];
        }

        $mapped = self::$aliases[$abbr] ?? null;
        if ($mapped && isset($teams[$mapped])) {
            return $teams[$mapped];
        }

        // Fallback for unknown teams
        return [
            'abbr' => $abbr,
            'name' => $abbr,
            'nick' => $abbr,
            'color' => '#334155',
            'color2' => '#94A3B8',
            'logo' => "https://a.espncdn.com/i/teamlogos/nfl/500/" . strtolower($abbr) . ".png",
        ];
    }

    public static function getColor(string $abbr): string
    {
        return self::get($abbr)['color'] ?? '#334155';
    }

    public static function getSecondaryColor(string $abbr): string
    {
        return self::get($abbr)['color2'] ?? '#94A3B8';
    }

    public static function getLogo(string $abbr): string
    {
        $team = self::get($abbr);
        return $team['logo'] ?? "https://a.espncdn.com/i/teamlogos/nfl/500/" . strtolower($abbr) . ".png";
    }

    public static function getName(string $abbr): string
    {
        return self::get($abbr)['name'] ?? $abbr;
    }

    public static function getNick(string $abbr): string
    {
        return self::get($abbr)['nick'] ?? $abbr;
    }

    /**
     * Generate an authentic vector SVG helmet with team colors and embedded decal
     */
    public static function renderHelmet(string $abbr, int $width = 64, int $height = 48): string
    {
        $team = self::get($abbr);
        $primary = htmlspecialchars($team['color']);
        $secondary = htmlspecialchars($team['color2']);
        $logo = htmlspecialchars(self::getLogo($abbr));

        return <<<SVG
<svg width="{$width}" height="{$height}" viewBox="0 0 120 90" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block shrink-0 drop-shadow-md">
    <defs>
        <radialGradient id="shell-shine-{$abbr}" cx="35%" cy="30%" r="70%">
            <stop offset="0%" stop-color="#ffffff" stop-opacity="0.3"/>
            <stop offset="60%" stop-color="{$primary}" stop-opacity="0.9"/>
            <stop offset="100%" stop-color="#000000" stop-opacity="0.7"/>
        </radialGradient>
        <linearGradient id="stripe-grad-{$abbr}" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="{$secondary}"/>
            <stop offset="100%" stop-color="#ffffff" stop-opacity="0.8"/>
        </linearGradient>
    </defs>
    <!-- Helmet Main Shell -->
    <path d="M22 68 C12 60, 10 40, 18 25 C26 10, 52 6, 75 10 C92 14, 104 26, 102 44 C100 56, 88 64, 76 66 L74 74 C60 76, 38 76, 22 68 Z" fill="{$primary}" stroke="rgba(0,0,0,0.5)" stroke-width="2"/>
    <path d="M22 68 C12 60, 10 40, 18 25 C26 10, 52 6, 75 10 C92 14, 104 26, 102 44 C100 56, 88 64, 76 66 L74 74 C60 76, 38 76, 22 68 Z" fill="url(#shell-shine-{$abbr})"/>
    
    <!-- Helmet Center Stripe -->
    <path d="M22 23 C35 10, 60 7, 78 12 C77 15, 58 13, 24 26 Z" fill="url(#stripe-grad-{$abbr})" opacity="0.9"/>
    
    <!-- Earhole & Inner Shadow -->
    <circle cx="58" cy="52" r="5.5" fill="#0f172a" stroke="rgba(255,255,255,0.2)" stroke-width="1"/>
    
    <!-- Team Logo Decal on Shell -->
    <image href="{$logo}" x="40" y="24" width="30" height="26" preserveAspectRatio="xMidYMid meet" opacity="0.95"/>
    
    <!-- Facemask (Metallic / Secondary Color) -->
    <!-- Top Bar -->
    <path d="M80 38 L114 42 C116 43, 116 47, 114 48 L80 48" stroke="{$secondary}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
    <!-- Middle Bar -->
    <path d="M78 48 L114 53 C115 54, 115 57, 113 58 L76 60" stroke="{$secondary}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
    <!-- Bottom Jaw Guard -->
    <path d="M75 58 L110 65 C108 72, 98 75, 82 72 L72 65" stroke="{$secondary}" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
    <!-- Vertical Cross Bars -->
    <path d="M102 41 L98 70" stroke="{$secondary}" stroke-width="3" stroke-linecap="round"/>
    <path d="M112 43 L106 67" stroke="{$secondary}" stroke-width="2.5" stroke-linecap="round"/>
    <!-- Chinstrap -->
    <path d="M58 54 C66 65, 78 72, 92 71" stroke="#e2e8f0" stroke-width="2" stroke-dasharray="2 1" fill="none" opacity="0.75"/>
</svg>
SVG;
    }
}
