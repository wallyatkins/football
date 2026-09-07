<?php
declare(strict_types=1);

/**
 * football.wallyatkins.com - Front Controller
 */

session_start();

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($uri === '/healthz') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'healthy', 'app' => 'football.wallyatkins.com', 'timestamp' => time()]);
    exit;
}

// Fallback landing / placeholder
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NFL Pool — football.wallyatkins.com</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col font-sans selection:bg-amber-500 selection:text-black">
    <header class="border-b border-slate-800 bg-slate-900/80 backdrop-blur px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="text-2xl">🏈</span>
            <span class="font-bold tracking-tight text-lg text-white">Atkins NFL Pool</span>
            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 font-mono border border-amber-500/30">Pick'em & Survivor</span>
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
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight mb-3">NFL Pick'em & Survivor Pool</h1>
            <p class="text-slate-400 text-sm sm:text-base mb-6 leading-relaxed">
                Welcome to the private Atkins family & friends NFL straight Pick'em and season-long Survivor pool. Authentication is authenticated via WallyAuth SSO.
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
        &copy; 2026 wallyatkins.com &bull; Powered by WallyAuth SSO &bull; Built with BMAD
    </footer>
</body>
</html>
