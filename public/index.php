<?php
declare(strict_types=1);

/**
 * football.wallyatkins.com - Front Controller
 * Atkins NFL Pick'em & Survivor League
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Session configuration with 90-day persistence matching WallyAuth trusted device duration
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    ini_set('session.gc_maxlifetime', '7776000'); // 90 days
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => 7776000, // 90 days
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Prevent client and browser proxy caching of dynamic NFL pool state
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');

// Dynamic Commissioner upgrade for active session
if (!empty($_SESSION['user'])) {
    $uEmail = strtolower($_SESSION['user']['email'] ?? '');
    $uName = strtolower($_SESSION['user']['username'] ?? '');
    $adminEmails = ['wallyatkins@gmail.com', 'wally@wallyatkins.com', 'accounts@wallyatkins.com'];
    if (in_array($uEmail, $adminEmails, true) || in_array($uName, ['wallyatkins', 'wally'], true) || in_array($_SESSION['user']['role'] ?? '', ['admin', 'commissioner'], true)) {
        $_SESSION['user']['role'] = 'commissioner';
    }
}

// Load optional .env file if present
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v, " \t\n\r\0\x0B\"'");
                if ($k !== '' && getenv($k) === false) {
                    putenv("{$k}={$v}");
                    $_ENV[$k] = $v;
                }
            }
        }
    }
}

use WallyFootball\Controllers\AdminController;
use WallyFootball\Controllers\AuthController;
use WallyFootball\Controllers\FantasyController;
use WallyFootball\Controllers\PickemController;
use WallyFootball\Controllers\SurvivorController;

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Static asset handler
if (str_starts_with($uri, '/assets/')) {
    $assetFile = __DIR__ . $uri;
    if (file_exists($assetFile)) {
        $ext = pathinfo($assetFile, PATHINFO_EXTENSION);
        $mimes = [
            'svg' => 'image/svg+xml',
            'css' => 'text/css',
            'js'  => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'ico' => 'image/x-icon',
        ];
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=86400');
        readfile($assetFile);
        exit;
    }
}

// Default season and dynamic active week
$season = isset($_GET['season']) ? (int) $_GET['season'] : (int) (getenv('NFL_CURRENT_SEASON') ?: date('Y'));
if (isset($_GET['week'])) {
    $week = (int) $_GET['week'];
} elseif (getenv('NFL_CURRENT_WEEK')) {
    $week = (int) getenv('NFL_CURRENT_WEEK');
} else {
    try {
        $db = WallyFootball\Database\Connection::getInstance();
        $activeWeek = $db->queryValue(
            'SELECT MIN(week_number) FROM games WHERE season_year = :s AND status != "final"',
            ['s' => $season]
        );
        $week = ($activeWeek && (int)$activeWeek > 0) ? (int) $activeWeek : 1;
    } catch (\Throwable) {
        $week = 1;
    }
}

// Route dispatch
try {
    switch ($uri) {
        case '/healthz':
            header('Content-Type: application/json');
            $dbReport = [];
            try {
                $db = WallyFootball\Database\Connection::getInstance();
                $gamesCount = (int) $db->queryValue('SELECT count(*) FROM games WHERE season_year = :s AND week_number = :w', ['s' => $season, 'w' => $week]);
                $dbReport = [
                    'status' => 'connected',
                    'week1_games' => $gamesCount,
                ];
                if (!empty($_GET['debug'])) {
                    $dbReport['games'] = $db->query('SELECT id, home_team, away_team, kickoff_time, is_mnf FROM games WHERE season_year = :s AND week_number = :w ORDER BY id ASC', ['s' => $season, 'w' => $week]);
                }
            } catch (\Throwable $e) {
                $dbReport = ['status' => 'error', 'message' => $e->getMessage()];
            }
            echo json_encode([
                'status' => 'healthy',
                'app' => 'football.wallyatkins.com',
                'timestamp' => time(),
                'version' => '1.0.3',
                'database' => $dbReport,
            ], JSON_PRETTY_PRINT);
            exit;

        case '/':
            if (!empty($_SESSION['user'])) {
                header('Location: /pickem');
                exit;
            }
            renderLandingPage();
            exit;

            // --- Authentication ---
        case '/auth/login':
            (new AuthController())->login();
            exit;

        case '/auth/callback':
            (new AuthController())->callback();
            exit;

        case '/auth/logout':
            (new AuthController())->logout();
            exit;

            // --- Pick'em Pool ---
        case '/pickem':
        case '/pickem/wizard':
            if ($method === 'POST') {
                (new PickemController())->save();
            } else {
                (new PickemController())->index($season, $week);
            }
            exit;

        case '/pickem/save':
            (new PickemController())->save();
            exit;

        case '/pickem/autosave':
            (new PickemController())->autoSave();
            exit;

        case '/pickem/standings':
            (new PickemController())->standings($season, $week);
            exit;

            // --- Survivor Pool ---
        case '/survivor':
            if ($method === 'POST') {
                (new SurvivorController())->save();
            } else {
                (new SurvivorController())->index($season, $week);
            }
            exit;

        case '/survivor/save':
            (new SurvivorController())->save();
            exit;

        case '/survivor/autosave':
            (new SurvivorController())->autoSave();
            exit;

        case '/survivor/standings':
            (new SurvivorController())->standings($season);
            exit;

            // --- Fantasy Dynasty Vault ---
        case '/fantasy':
        case '/fantasy/vault':
            (new FantasyController())->vault();
            exit;

        case '/fantasy/pools':
        case '/fantasy/archives':
            (new FantasyController())->poolArchives();
            exit;

        case '/fantasy/rivalry':
            (new FantasyController())->rivalry();
            exit;

        case '/fantasy/seasons':
            (new FantasyController())->seasons();
            exit;

            // --- Commissioner Admin ---
        case '/admin':
        case '/admin/payments':
            (new AdminController())->payments($season, $week);
            exit;

        case '/admin/payments/toggle':
            (new AdminController())->togglePayment();
            exit;

        case '/admin/lock/toggle':
            (new AdminController())->toggleLock();
            exit;

        case '/admin/survivor/toggle':
            (new AdminController())->toggleSurvivor();
            exit;

        case '/admin/survivor/eliminate':
            (new AdminController())->toggleSurvivorElimination();
            exit;

        case '/admin/survivor/grade':
            (new AdminController())->gradeSurvivor();
            exit;

        case '/admin/sync':
            (new AdminController())->syncSchedule();
            exit;

        case '/admin/tiebreaker/randomize':
            (new AdminController())->randomizeTiebreaker();
            exit;

        case '/admin/picks/reset':
            (new AdminController())->resetPicks();
            exit;

        case '/api/cron/daily-digest':
            $secret = $_GET['secret'] ?? '';
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            $expectedSecret = getenv('CRON_SECRET') ?: 'atkins-football-cron-2026';
            $isCommissioner = (!empty($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'commissioner');
            $isBearerValid = ($authHeader === "Bearer {$expectedSecret}");
            $isSecretValid = ($secret === $expectedSecret);

            if (!$isCommissioner && !$isBearerValid && !$isSecretValid) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['status' => 'forbidden', 'message' => 'Invalid or missing cron secret authorization']);
                exit;
            }

            header('Content-Type: application/json');
            try {
                $db = WallyFootball\Database\Connection::getInstance();
                $sports = new WallyFootball\Services\SportsDataService($db);
                $digest = new WallyFootball\Services\DailyPickemDigestService($db, null, $sports);
                $force = isset($_GET['force']) && ($_GET['force'] === '1' || $_GET['force'] === 'true');
                $dryRun = isset($_GET['dry_run']) && ($_GET['dry_run'] === '1' || $_GET['dry_run'] === 'true');
                $testTo = !empty($_GET['test_to']) ? (string) $_GET['test_to'] : null;
                $result = $digest->sendDigest($season, $week, $force, $testTo, $dryRun);
                echo json_encode($result, JSON_PRETTY_PRINT);
            } catch (\Throwable $e) {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;

        default:
            http_response_code(404);
            $user = $_SESSION['user'] ?? null;
            $title = "404 - Not Found — Wally's NFL Pool";
            ob_start();
            ?>
            <div class="max-w-md mx-auto my-12 text-center p-8 bg-slate-900/60 border border-slate-800 rounded-2xl">
                <span class="text-4xl block mb-3">🏈</span>
                <h1 class="text-2xl font-black text-white mb-2">404 - Page Not Found</h1>
                <p class="text-sm text-slate-400 mb-6">The page you requested does not exist or has moved.</p>
                <a href="/" class="inline-flex px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold rounded-lg transition text-sm">
                    Return to League &rarr;
                </a>
            </div>
            <?php
            $content = ob_get_clean();
            require dirname(__DIR__) . '/templates/layout.php';
            exit;
    }
} catch (\Throwable $e) {
    http_response_code(500);
    $user = $_SESSION['user'] ?? null;
    $title = "500 - Application Error — Wally's NFL Pool";
    ob_start();
    ?>
    <div class="max-w-lg mx-auto my-12 p-8 bg-rose-950/20 border border-rose-800/40 rounded-2xl text-left">
        <div class="flex items-center gap-3 mb-4">
            <span class="text-3xl">⚠️</span>
            <h1 class="text-xl font-bold text-rose-300">Application Error</h1>
        </div>
        <p class="text-xs font-mono text-rose-200/80 bg-slate-950 p-4 rounded-lg overflow-x-auto border border-rose-900/30 mb-6">
            <?= htmlspecialchars($e->getMessage()) ?>
        </p>
        <a href="/" class="inline-flex px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white font-semibold rounded-lg transition text-xs">
            Return Home
        </a>
    </div>
    <?php
    $content = ob_get_clean();
    require dirname(__DIR__) . '/templates/layout.php';
    exit;
}

function renderLandingPage(): void
{
    header('Content-Type: text/html; charset=utf-8');
    $season = (int) (getenv('NFL_CURRENT_SEASON') ?: date('Y'));
    $landingWinner = null;
    try {
        $db = WallyFootball\Database\Connection::getInstance();
        $scoring = new WallyFootball\Services\ScoringEngine($db);
        $lastCompletedWeek = (int) ($db->queryValue(
            'SELECT MAX(week_number) FROM games WHERE season_year = :s AND status = "final"',
            ['s' => $season]
        ) ?: 0);
        if ($lastCompletedWeek > 0) {
            $totalG = (int) $db->queryValue('SELECT count(*) FROM games WHERE season_year = :s AND week_number = :w', ['s' => $season, 'w' => $lastCompletedWeek]);
            $finalG = (int) $db->queryValue('SELECT count(*) FROM games WHERE season_year = :s AND week_number = :w AND status = "final"', ['s' => $season, 'w' => $lastCompletedWeek]);
            if ($totalG > 0 && $finalG === $totalG) {
                $potInfo = $scoring->calculateWeeklyPot($season, $lastCompletedWeek);
                if (!empty($potInfo['winners'])) {
                    $landingWinner = [
                        'week' => $lastCompletedWeek,
                        'names' => implode(' & ', array_map(fn ($w) => htmlspecialchars($w['username']), $potInfo['winners'])),
                        'score' => $potInfo['winners'][0]['correct_picks'] ?? 0,
                        'payout' => $potInfo['payout_per_winner'] ?? 0,
                    ];
                }
            }
        }
    } catch (\Throwable) {
        $landingWinner = null;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Wally's NFL Pool — football.wallyatkins.com</title>
        <!-- Matomo Analytics (Site ID 3: Atkins NFL Pool) -->
        <script>
          var _paq = window._paq = window._paq || [];
          _paq.push(['setDocumentTitle', document.domain + '/' + (document.title || 'Landing')]);
          _paq.push(['setCookieDomain', '*.wallyatkins.com']);
          _paq.push(['trackPageView']);
          _paq.push(['enableLinkTracking']);
          (function() {
            var u = 'https://analytics.wallyatkins.com/';
            _paq.push(['setTrackerUrl', u + 'matomo.php']);
            _paq.push(['setSiteId', '3']);
            var d = document, g = d.createElement('script'), s = d.getElementsByTagName('script')[0];
            g.async = true; g.src = u + 'matomo.js'; s.parentNode.insertBefore(g, s);
          })();
        </script>
        <!-- End Matomo Code -->
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col font-sans selection:bg-amber-500 selection:text-black">
        <header class="border-b border-slate-800 bg-slate-900/80 backdrop-blur px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🏈</span>
                <span class="font-black tracking-tight text-lg text-white">Wally's NFL Pool</span>
                <span class="text-xs px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 font-mono border border-amber-500/30">Pick'em &amp; Survivor</span>
            </div>
            <div class="flex items-center gap-4">
                <a href="/auth/login" class="px-4 py-2 text-sm font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white transition shadow-sm">Sign In via WallyAuth</a>
            </div>
        </header>

        <main class="flex-1 max-w-4xl mx-auto w-full p-6 flex flex-col justify-center items-center text-center">
            <div class="p-8 sm:p-10 rounded-2xl bg-slate-900/60 border border-slate-800 shadow-2xl max-w-xl w-full">
                <?php if ($landingWinner): ?>
                    <div class="mb-6 p-4 rounded-xl bg-gradient-to-r from-amber-500/20 via-amber-500/10 to-amber-500/20 border border-amber-500/30 text-center shadow-lg">
                        <div class="text-xs font-black uppercase tracking-wider text-amber-400 mb-1 flex items-center justify-center gap-1.5">
                            <span>👑</span>
                            <span>Week <?= $landingWinner['week'] ?> Champion</span>
                            <span>👑</span>
                        </div>
                        <div class="text-xl font-black text-white">Congratulations, <?= $landingWinner['names'] ?>!</div>
                        <div class="text-xs text-slate-300 mt-1">
                            Finished #1 with <strong class="text-emerald-400 font-bold"><?= $landingWinner['score'] ?> correct picks</strong><?= ($landingWinner['payout'] > 0) ? " &bull; Won <span class='text-emerald-400 font-bold'>$" . number_format($landingWinner['payout'], 2) . "</span>" : "" ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="inline-flex p-3 rounded-xl bg-amber-500/10 text-amber-400 text-3xl mb-4 border border-amber-500/20">
                    🏈
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight mb-3">Wally's NFL Pool</h1>
                <p class="text-slate-400 text-sm sm:text-base mb-6 leading-relaxed">
                    Welcome to the private NFL Pick'em and Survivor tournament pool. Compete against friends and colleagues with live scoreboard syncing, weekly leaderboard standings, and automated daily morning email briefings.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-left mb-6">
                    <div class="p-4 rounded-xl bg-slate-800/50 border border-slate-700/50">
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="text-base">🎯</span>
                            <span class="text-xs text-amber-400 font-bold uppercase tracking-wider">Weekly Pick'em</span>
                        </div>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Pick straight-up winners for every game on the slate. Submit predicted total points for the Game of the Week tiebreaker.
                        </p>
                    </div>
                    <div class="p-4 rounded-xl bg-slate-800/50 border border-slate-700/50">
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="text-base">🛡️</span>
                            <span class="text-xs text-emerald-400 font-bold uppercase tracking-wider">Season Survivor</span>
                        </div>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Select one winning team each week. Each NFL franchise can only be utilized once per season. Survive to the final whistle!
                        </p>
                    </div>
                </div>

                <a href="/auth/login" class="w-full inline-flex justify-center items-center gap-2 px-5 py-3 rounded-xl font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 transition shadow-lg text-base">
                    Enter League with WallyAuth SSO &rarr;
                </a>

                <!-- Account Request Callout -->
                <div class="mt-6 p-4 rounded-xl bg-slate-950/60 border border-slate-800 text-left flex items-start gap-3.5">
                    <span class="text-2xl shrink-0 mt-0.5">✉️</span>
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-amber-400 mb-1">Need an Account or Want to Join?</h4>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Participation is invite-only for friends, family, and colleagues. If you don't have an account yet and would like to join this season's pool, reach out directly using the{' '}
                            <a href="https://wallyatkins.com/contact" class="text-amber-400 font-semibold underline hover:text-amber-300">
                                Get in Touch form
                            </a>{' '}
                            on Wally's website.
                        </p>
                    </div>
                </div>
            </div>
        </main>

        <footer class="border-t border-slate-800/80 py-6 text-center text-xs text-slate-500">
            <div class="max-w-xl mx-auto px-4 flex flex-wrap justify-center items-center gap-x-4 gap-y-2 mb-2">
                <span>&copy; <?= date('Y') ?> Wally's NFL Pool</span>
                <span>&bull;</span>
                <a href="https://wallyatkins.com" class="hover:text-slate-300 transition">wallyatkins.com</a>
                <span>&bull;</span>
                <a href="https://wallyatkins.com/contact" class="hover:text-slate-300 transition">Request Account</a>
                <span>&bull;</span>
                <a href="https://wallyatkins.com/privacy" class="hover:text-slate-300 transition">Privacy</a>
                <span>&bull;</span>
                <a href="https://wallyatkins.com/terms" class="hover:text-slate-300 transition">Terms</a>
            </div>
            <p class="text-[11px] text-slate-600">
                Protected by WallyAuth SSO &bull; Self-hosted telemetry via Matomo Analytics
            </p>
        </footer>
    </body>
    </html>
    <?php
}
