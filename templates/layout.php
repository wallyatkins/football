<?php
$user = $user ?? $_SESSION['user'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? "Wally's NFL Pool") ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col antialiased selection:bg-amber-500 selection:text-black">

    <!-- Global Top Navigation -->
    <header class="border-b border-slate-800/80 bg-slate-900/90 backdrop-blur sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <a href="/" class="flex items-center gap-2.5 group">
                    <span class="text-2xl group-hover:scale-110 transition-transform">🏈</span>
                    <span class="font-black tracking-tight text-white text-base sm:text-lg">Wally's NFL Pool</span>
                </a>

                <nav class="hidden md:flex items-center gap-1">
                    <a href="/pickem" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/pickem') && !str_contains($_SERVER['REQUEST_URI'] ?? '', 'standings') ? 'bg-slate-800 text-amber-400 font-semibold' : 'text-slate-300 hover:text-white hover:bg-slate-800/50' ?> transition">
                        Pick'em
                    </a>
                    <a href="/pickem/standings" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/pickem/standings') ? 'bg-slate-800 text-amber-400 font-semibold' : 'text-slate-300 hover:text-white hover:bg-slate-800/50' ?> transition">
                        Standings
                    </a>
                    <a href="/survivor" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/survivor') && !str_contains($_SERVER['REQUEST_URI'] ?? '', 'standings') ? 'bg-slate-800 text-emerald-400 font-semibold' : 'text-slate-300 hover:text-white hover:bg-slate-800/50' ?> transition">
                        Survivor
                    </a>
                    <a href="/survivor/standings" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/survivor/standings') ? 'bg-slate-800 text-emerald-400 font-semibold' : 'text-slate-300 hover:text-white hover:bg-slate-800/50' ?> transition">
                        Survivor Leaderboard
                    </a>
                    <a href="/fantasy/vault" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/fantasy') ? 'bg-slate-800 text-amber-400 font-semibold border border-amber-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-800/50' ?> transition flex items-center gap-1.5">
                        <span>🏛️</span> Dynasty Vault
                    </a>
                    <?php
                    $isCommissioner = in_array($user['role'] ?? '', ['admin', 'commissioner'], true);
                    if ($isCommissioner): ?>
                        <a href="/admin/payments" class="px-3 py-1.5 text-sm font-bold rounded-lg <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin') ? 'bg-purple-600 text-white shadow-md' : 'bg-purple-950/60 border border-purple-500/40 text-purple-300 hover:bg-purple-900/80 hover:text-white' ?> transition flex items-center gap-1.5 shadow-sm">
                            <span>👑</span> Commissioner
                        </a>
                    <?php endif; ?>
                </nav>
            </div>

            <div class="flex items-center gap-3">
                <?php if (!empty($user)): ?>
                    <div class="flex items-center gap-2.5">
                        <div class="hidden sm:flex flex-col text-right">
                            <span class="text-xs font-semibold text-white leading-tight"><?= htmlspecialchars($user['username'] ?? 'Player') ?></span>
                            <span class="text-[10px] font-mono <?= $isCommissioner ? 'text-purple-400 font-bold' : 'text-slate-400' ?>">
                                <?= $isCommissioner ? '👑 Commissioner' : 'Player' ?>
                            </span>
                        </div>
                        <a href="/auth/logout" class="px-3 py-1.5 text-xs font-medium text-slate-400 hover:text-rose-400 border border-slate-800 hover:border-rose-900/50 rounded-lg transition">
                            Sign Out
                        </a>
                    </div>
                <?php else: ?>
                    <a href="/auth/login" class="px-3.5 py-1.5 text-xs sm:text-sm font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white transition shadow-sm">
                        Sign In (WallyAuth)
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Mobile Bottom Navigation -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-slate-900/95 border-t border-slate-800 flex justify-around py-2.5 backdrop-blur">
        <a href="/pickem" class="flex flex-col items-center gap-0.5 text-[11px] <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/pickem') && !str_contains($_SERVER['REQUEST_URI'] ?? '', 'standings') ? 'text-amber-400 font-bold' : 'text-slate-400' ?>">
            <span>🎯</span>
            <span>Picks</span>
        </a>
        <a href="/pickem/standings" class="flex flex-col items-center gap-0.5 text-[11px] <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/pickem/standings') ? 'text-amber-400 font-bold' : 'text-slate-400' ?>">
            <span>🏆</span>
            <span>Standings</span>
        </a>
        <a href="/survivor" class="flex flex-col items-center gap-0.5 text-[11px] <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/survivor') && !str_contains($_SERVER['REQUEST_URI'] ?? '', 'standings') ? 'text-emerald-400 font-bold' : 'text-slate-400' ?>">
            <span>🛡️</span>
            <span>Survivor</span>
        </a>
        <a href="/fantasy/vault" class="flex flex-col items-center gap-0.5 text-[11px] <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/fantasy') ? 'text-amber-400 font-bold' : 'text-slate-400' ?>">
            <span>🏛️</span>
            <span>Vault</span>
        </a>
        <?php if (!empty($isCommissioner)): ?>
            <a href="/admin/payments" class="flex flex-col items-center gap-0.5 text-[11px] <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin') ? 'text-purple-300 font-bold' : 'text-slate-400 hover:text-purple-300' ?>">
                <span>👑</span>
                <span>Commissioner</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl mx-auto w-full px-4 sm:px-6 py-6 pb-20 md:pb-8">
        <?php if (!empty($_SESSION['flash'])): ?>
            <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm flex items-center justify-between">
                <span><?= htmlspecialchars($_SESSION['flash']) ?></span>
                <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white">&times;</button>
            </div>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['error'])): ?>
            <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-center justify-between">
                <span><?= htmlspecialchars($_SESSION['error']) ?></span>
                <button onclick="this.parentElement.remove()" class="text-rose-400 hover:text-white">&times;</button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?= $content ?? '' ?>
    </main>

    <footer class="hidden md:block border-t border-slate-800/80 py-6 text-center text-xs text-slate-500">
        <p>&copy; <?= date('Y') ?> Wally's NFL Pool &bull; <a href="https://wallyatkins.com" class="hover:text-slate-400 transition underline">wallyatkins.com</a> &bull; Identity by <a href="https://auth.wallyatkins.com" class="hover:text-slate-400 transition underline">WallyAuth</a></p>
    </footer>

</body>
</html>
