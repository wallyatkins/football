<?php
$user = $user ?? $_SESSION['user'] ?? null;
$username = $user['username'] ?? 'Player';
$userEmail = $user['email'] ?? '';
$userAvatar = $user['avatar_url'] ?? $user['picture'] ?? null;
$initials = strtoupper(substr($username, 0, 2));
$isCommissioner = in_array($user['role'] ?? '', ['admin', 'commissioner'], true);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? "Wally's NFL Pool") ?></title>
    <script>
        (function() {
            var theme = localStorage.getItem('wally_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --turf-bg: #091f11;
            --turf-img: url('/assets/field-turf.svg');
            --card-surface: rgba(15, 23, 42, 0.85);
            --card-surface-border: #1e293b;
            --picked-end: #090d16;
            --matchup-card-bg: rgba(15, 23, 42, 0.85);
            --matchup-header-bg: rgba(2, 6, 23, 0.75);
            --matchup-border: rgba(51, 65, 85, 0.8);
            --tiebreaker-bg: rgba(15, 23, 42, 0.95);
            --input-bg: #020617;
            --input-border: #334155;
            --input-text: #ffffff;
            --bottom-bar-bg: rgba(15, 23, 42, 0.95);
            --bottom-bar-border: #1e293b;
        }

        html[data-theme="light"] {
            --turf-bg: #1c562d;
            --turf-img: url('/assets/field-turf-light.svg');
            --card-surface: #ffffff;
            --card-surface-border: #cbd5e1;
            --picked-end: #ffffff;
            --matchup-card-bg: #ffffff;
            --matchup-header-bg: #f8fafc;
            --matchup-border: #cbd5e1;
            --tiebreaker-bg: #ffffff;
            --input-bg: #f8fafc;
            --input-border: #cbd5e1;
            --input-text: #0f172a;
            --bottom-bar-bg: rgba(255, 255, 255, 0.97);
            --bottom-bar-border: #e2e8f0;
        }

        body { font-family: 'Inter', sans-serif; transition: background-color 0.2s, color 0.2s; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }

        /* Stadium Turf Field Background */
        body.football-field {
            background-color: var(--turf-bg);
            background-image: var(--turf-img);
            background-repeat: repeat-y;
            background-position: top center;
            background-size: 1400px 2500px;
            background-attachment: scroll;
        }

        @media (max-width: 640px) {
            body.football-field {
                background-size: 900px 2500px;
            }
        }

        /* Card and Matchup Surface Defaults */
        .matchup-card, .game-row-card {
            background-color: var(--matchup-card-bg);
            border-color: var(--matchup-border);
        }
        .matchup-header-bar {
            background-color: var(--matchup-header-bg);
            border-color: var(--matchup-border);
        }
        .team-card, .survivor-card {
            border-color: var(--card-surface-border);
            background-color: var(--card-surface);
        }

        /* Light Mode Overrides */
        html[data-theme="light"] body.football-field {
            background-color: var(--turf-bg) !important;
            background-image: var(--turf-img) !important;
            color: #0f172a !important;
        }
        html[data-theme="light"] body {
            background-color: #f8fafc !important;
            color: #0f172a !important;
        }
        html[data-theme="light"] header,
        html[data-theme="light"] nav.fixed {
            background-color: rgba(255, 255, 255, 0.95) !important;
            border-color: #e2e8f0 !important;
        }
        html[data-theme="light"] .matchup-card,
        html[data-theme="light"] .game-row-card {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.05) !important;
        }
        html[data-theme="light"] .matchup-header-bar {
            background-color: #f8fafc !important;
            border-color: #e2e8f0 !important;
            color: #475569 !important;
        }
        html[data-theme="light"] .team-card:not(.is-picked),
        html[data-theme="light"] .survivor-card:not(.is-picked) {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04) !important;
        }
        html[data-theme="light"] #mnfTiebreakerContainer {
            background-color: #ffffff !important;
            background-image: linear-gradient(135deg, rgba(254, 243, 199, 0.5), #ffffff) !important;
            border-color: rgba(245, 158, 11, 0.5) !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08) !important;
        }
        html[data-theme="light"] #mnfTotalPointsInput {
            background-color: #f8fafc !important;
            border-color: #cbd5e1 !important;
            color: #0f172a !important;
        }
        html[data-theme="light"] .prelock-banner,
        html[data-theme="light"] .locked-banner,
        html[data-theme="light"] .survivor-banner-free,
        html[data-theme="light"] .survivor-banner-cash,
        html[data-theme="light"] .survivor-pick-locked-banner {
            background: #ffffff !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08) !important;
        }
        html[data-theme="light"] .sticky.bottom-16,
        html[data-theme="light"] .sticky.md\:bottom-6 {
            background-color: rgba(255, 255, 255, 0.96) !important;
            border-color: #e2e8f0 !important;
            box-shadow: 0 -4px 25px rgba(0, 0, 0, 0.08) !important;
        }
        html[data-theme="light"] .fixed.inset-0 > div {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
            color: #0f172a !important;
        }
        html[data-theme="light"] .fixed.inset-0 .border-b,
        html[data-theme="light"] .fixed.inset-0 .border-t {
            background-color: #f8fafc !important;
            border-color: #e2e8f0 !important;
        }
        html[data-theme="light"] table {
            background-color: #ffffff !important;
            color: #0f172a !important;
        }
        html[data-theme="light"] thead tr {
            background-color: #f1f5f9 !important;
            color: #475569 !important;
        }
        html[data-theme="light"] tbody tr {
            border-color: #e2e8f0 !important;
        }
        html[data-theme="light"] tbody tr:hover {
            background-color: #f8fafc !important;
        }
        html[data-theme="light"] .bg-slate-950,
        html[data-theme="light"] .bg-slate-950\/90,
        html[data-theme="light"] .bg-slate-950\/80,
        html[data-theme="light"] .bg-slate-950\/70,
        html[data-theme="light"] .bg-slate-950\/60 {
            background-color: #f8fafc !important;
        }
        html[data-theme="light"] .bg-slate-900,
        html[data-theme="light"] .bg-slate-900\/95,
        html[data-theme="light"] .bg-slate-900\/90,
        html[data-theme="light"] .bg-slate-900\/80,
        html[data-theme="light"] .bg-slate-900\/70,
        html[data-theme="light"] .bg-slate-900\/60,
        html[data-theme="light"] .bg-slate-900\/50 {
            background-color: #ffffff !important;
        }
        html[data-theme="light"] .bg-slate-800,
        html[data-theme="light"] .bg-slate-800\/80,
        html[data-theme="light"] .bg-slate-800\/50,
        html[data-theme="light"] .bg-slate-800\/40,
        html[data-theme="light"] .bg-slate-800\/30 {
            background-color: #f1f5f9 !important;
        }
        html[data-theme="light"] .border-slate-800,
        html[data-theme="light"] .border-slate-800\/80,
        html[data-theme="light"] .border-slate-800\/60,
        html[data-theme="light"] .border-slate-700,
        html[data-theme="light"] .border-slate-700\/80 {
            border-color: #e2e8f0 !important;
        }
        html[data-theme="light"] .text-white {
            color: #0f172a !important;
        }
        html[data-theme="light"] button.text-white,
        html[data-theme="light"] a.text-white,
        html[data-theme="light"] button[class*="bg-emerald-"],
        html[data-theme="light"] button[class*="bg-rose-"],
        html[data-theme="light"] button[class*="bg-blue-"],
        html[data-theme="light"] a[class*="bg-emerald-"],
        html[data-theme="light"] a[class*="bg-rose-"],
        html[data-theme="light"] a[class*="bg-blue-"],
        html[data-theme="light"] .pick-badge span,
        html[data-theme="light"] .survivor-badge span.text-white {
            color: #ffffff !important;
        }
        html[data-theme="light"] .text-slate-100,
        html[data-theme="light"] .text-slate-200 {
            color: #1e293b !important;
        }
        html[data-theme="light"] .text-slate-300 {
            color: #334155 !important;
        }
        html[data-theme="light"] .text-slate-400 {
            color: #64748b !important;
        }
        html[data-theme="light"] footer {
            border-color: #e2e8f0 !important;
            color: #64748b !important;
        }
        html[data-theme="light"] .theme-menu-panel {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
        }
    </style>
</head>
<body class="football-field text-slate-100 min-h-screen flex flex-col antialiased selection:bg-amber-500 selection:text-black">

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
                    <?php if ($isCommissioner): ?>
                        <a href="/admin/payments" class="px-3 py-1.5 text-sm font-bold rounded-lg <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin') ? 'bg-purple-600 text-white shadow-md' : 'bg-purple-950/60 border border-purple-500/40 text-purple-300 hover:bg-purple-900/80 hover:text-white' ?> transition flex items-center gap-1.5 shadow-sm">
                            <span>👑</span> Commissioner
                        </a>
                    <?php endif; ?>
                </nav>
            </div>

            <div class="flex items-center gap-3">
                <?php if (!empty($user)): ?>
                    <div class="relative" id="userMenuContainer">
                        <button type="button" 
                                id="userMenuBtn"
                                class="flex items-center gap-2 p-1 pl-1.5 pr-2.5 rounded-full hover:bg-slate-800 border border-slate-800 hover:border-slate-700 transition focus:outline-none focus:ring-2 focus:ring-amber-500/50"
                                aria-expanded="false" 
                                aria-haspopup="true">
                            <?php if (!empty($userAvatar)): ?>
                                <img src="<?= htmlspecialchars($userAvatar) ?>" 
                                     alt="<?= htmlspecialchars($username) ?>" 
                                     class="w-8 h-8 rounded-full object-cover border border-slate-700">
                            <?php else: ?>
                                <div class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-xs font-black text-amber-400 font-mono">
                                    <?= htmlspecialchars($initials) ?>
                                </div>
                            <?php endif; ?>
                            <span class="hidden sm:inline text-xs font-semibold text-white max-w-[120px] truncate"><?= htmlspecialchars($username) ?></span>
                            <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" id="userMenuChevron" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <!-- Profile Dropdown Menu -->
                        <div id="userMenuDropdown" 
                             class="theme-menu-panel hidden absolute right-0 mt-2 w-64 rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl z-50 py-2 divide-y divide-slate-800/80">
                            <!-- User identity banner -->
                            <div class="px-4 py-3 flex items-center gap-3">
                                <?php if (!empty($userAvatar)): ?>
                                    <img src="<?= htmlspecialchars($userAvatar) ?>" 
                                         alt="<?= htmlspecialchars($username) ?>" 
                                         class="w-10 h-10 rounded-full object-cover border border-slate-700 shrink-0">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-sm font-black text-amber-400 font-mono shrink-0">
                                        <?= htmlspecialchars($initials) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <p class="text-sm font-bold text-white truncate"><?= htmlspecialchars($username) ?></p>
                                        <?php if ($isCommissioner): ?>
                                            <span class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded bg-purple-900/60 text-purple-300 border border-purple-500/30">👑</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs text-slate-400 truncate"><?= htmlspecialchars($userEmail) ?></p>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="py-1.5">
                                <a href="https://auth.wallyatkins.com/account?return_url=https://football.wallyatkins.com" 
                                   class="flex items-center gap-2.5 px-4 py-2 text-xs font-semibold text-slate-200 hover:text-white hover:bg-slate-800/60 transition group">
                                    <span class="text-base group-hover:scale-110 transition-transform">⚙️</span>
                                    <div class="flex flex-col">
                                        <span>Account Settings</span>
                                        <span class="text-[10px] text-slate-400 font-normal">Avatar, username, password &amp; phone</span>
                                    </div>
                                </a>
                                <button type="button" 
                                        onclick="toggleWallyTheme()" 
                                        class="w-full flex items-center justify-between px-4 py-2 text-xs font-semibold text-slate-200 hover:text-white hover:bg-slate-800/60 transition group text-left">
                                    <div class="flex items-center gap-2.5">
                                        <span class="text-base group-hover:rotate-12 transition-transform" id="themeDropdownIcon">🌙</span>
                                        <div class="flex flex-col">
                                            <span id="themeDropdownLabel">Toggle Theme</span>
                                            <span class="text-[10px] text-slate-400 font-normal" id="themeDropdownSub">Currently Dark Mode</span>
                                        </div>
                                    </div>
                                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-slate-800 text-slate-400 border border-slate-700" id="themePill">Dark</span>
                                </button>
                            </div>

                            <!-- Sign Out -->
                            <div class="py-1.5">
                                <a href="/auth/logout" 
                                   class="flex items-center gap-2.5 px-4 py-2 text-xs font-semibold text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 transition">
                                    <span class="text-base">🚪</span>
                                    <span>Sign Out</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="flex items-center gap-2">
                        <button type="button" 
                                onclick="toggleWallyTheme()" 
                                class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 border border-slate-800 transition" 
                                title="Toggle Dark/Light Mode">
                            <span id="themeAnonIcon">🌙</span>
                        </button>
                        <a href="/auth/login" class="px-3.5 py-1.5 text-xs sm:text-sm font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white transition shadow-sm">
                            Sign In (WallyAuth)
                        </a>
                    </div>
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

    <script>
        // Theme toggle helper
        function updateThemeUI(theme) {
            var isDark = theme === 'dark';
            var icon = isDark ? '🌙' : '☀️';
            var sub = isDark ? 'Currently Dark Mode' : 'Currently Light Mode';
            var pill = isDark ? 'Dark' : 'Light';

            var iconEl = document.getElementById('themeDropdownIcon');
            if (iconEl) iconEl.textContent = icon;
            var subEl = document.getElementById('themeDropdownSub');
            if (subEl) subEl.textContent = sub;
            var pillEl = document.getElementById('themePill');
            if (pillEl) pillEl.textContent = pill;
            var anonEl = document.getElementById('themeAnonIcon');
            if (anonEl) anonEl.textContent = icon;
        }

        function toggleWallyTheme() {
            var current = document.documentElement.getAttribute('data-theme') || 'dark';
            var next = current === 'dark' ? 'light' : 'dark';
            localStorage.setItem('wally_theme', next);
            document.documentElement.setAttribute('data-theme', next);
            if (next === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            updateThemeUI(next);
        }

        // Initialize UI on load
        document.addEventListener('DOMContentLoaded', function() {
            var current = localStorage.getItem('wally_theme') || 'dark';
            updateThemeUI(current);

            var menuBtn = document.getElementById('userMenuBtn');
            var dropdown = document.getElementById('userMenuDropdown');
            var chevron = document.getElementById('userMenuChevron');

            if (menuBtn && dropdown) {
                menuBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var isExpanded = menuBtn.getAttribute('aria-expanded') === 'true';
                    menuBtn.setAttribute('aria-expanded', !isExpanded);
                    dropdown.classList.toggle('hidden');
                    if (chevron) {
                        chevron.style.transform = isExpanded ? 'rotate(0deg)' : 'rotate(180deg)';
                    }
                });

                document.addEventListener('click', function(e) {
                    if (!dropdown.contains(e.target) && !menuBtn.contains(e.target)) {
                        dropdown.classList.add('hidden');
                        menuBtn.setAttribute('aria-expanded', 'false');
                        if (chevron) chevron.style.transform = 'rotate(0deg)';
                    }
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        dropdown.classList.add('hidden');
                        menuBtn.setAttribute('aria-expanded', 'false');
                        if (chevron) chevron.style.transform = 'rotate(0deg)';
                    }
                });
            }
        });
    </script>
</body>
</html>
