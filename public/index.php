<?php
declare(strict_types=1);

/**
 * football.wallyatkins.com - Front Controller
 * Atkins NFL Pick'em & Survivor League
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
use WallyFootball\Controllers\PickemController;
use WallyFootball\Controllers\SurvivorController;

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Default season and week
$season = isset($_GET['season']) ? (int) $_GET['season'] : (int) (getenv('NFL_CURRENT_SEASON') ?: date('Y'));
$week = isset($_GET['week']) ? (int) $_GET['week'] : (int) (getenv('NFL_CURRENT_WEEK') ?: 1);

// Route dispatch
try {
    switch ($uri) {
        case '/healthz':
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'healthy',
                'app' => 'football.wallyatkins.com',
                'timestamp' => time(),
                'version' => '1.0.0',
            ]);
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
            if ($method === 'POST') {
                (new PickemController())->save();
            } else {
                (new PickemController())->index($season, $week);
            }
            exit;

        case '/pickem/save':
            (new PickemController())->save();
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

        case '/survivor/standings':
            (new SurvivorController())->standings($season);
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
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Wally's NFL Pool — football.wallyatkins.com</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col font-sans selection:bg-amber-500 selection:text-black">
        <header class="border-b border-slate-800 bg-slate-900/80 backdrop-blur px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🏈</span>
                <span class="font-black tracking-tight text-lg text-white">Wally's NFL Pool</span>
                <span class="text-xs px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 font-mono border border-amber-500/30">Pick'em &amp; Survivor</span>
            </div>
            <div class="flex items-center gap-4">
                <a href="/auth/login" class="px-4 py-2 text-sm font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white transition shadow-sm">Sign In via WallyAuth</a>
            </div>
        </header>

        <main class="flex-1 max-w-4xl mx-auto w-full p-6 flex flex-col justify-center items-center text-center">
            <div class="p-8 rounded-2xl bg-slate-900/60 border border-slate-800 shadow-xl max-w-xl">
                <div class="inline-flex p-3 rounded-xl bg-amber-500/10 text-amber-400 text-3xl mb-4 border border-amber-500/20">
                    🏈
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight mb-3">Wally's NFL Pool</h1>
                <p class="text-slate-400 text-sm sm:text-base mb-6 leading-relaxed">
                    Welcome to the private NFL straight Pick'em and season-long Survivor pool. Authentication is verified via WallyAuth SSO.
                </p>
                <div class="grid grid-cols-2 gap-3 text-left mb-6">
                    <div class="p-3 rounded-lg bg-slate-800/50 border border-slate-700/50">
                        <span class="text-xs text-amber-400 font-semibold block uppercase tracking-wider">Weekly Pick'em</span>
                        <span class="text-xs text-slate-300">Straight-up winners + Monday Night Football tiebreaker points.</span>
                    </div>
                    <div class="p-3 rounded-lg bg-slate-800/50 border border-slate-700/50">
                        <span class="text-xs text-emerald-400 font-semibold block uppercase tracking-wider">Season Survivor</span>
                        <span class="text-xs text-slate-300">Pick 1 winner per week. Each NFL team can only be chosen once.</span>
                    </div>
                </div>
                <a href="/auth/login" class="w-full inline-flex justify-center items-center gap-2 px-5 py-3 rounded-xl font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 transition shadow-lg">
                    Enter League with WallyAuth &rarr;
                </a>
            </div>
        </main>

        <footer class="border-t border-slate-800/80 py-4 text-center text-xs text-slate-500">
            &copy; <?= date('Y') ?> Wally's NFL Pool &bull; <a href="https://wallyatkins.com" class="underline hover:text-slate-400">wallyatkins.com</a> &bull; Powered by WallyAuth SSO
        </footer>
    </body>
    </html>
    <?php
}
