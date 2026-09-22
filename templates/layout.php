<?php
$user = $user ?? $_SESSION['user'] ?? null;
$username = $user['username'] ?? 'Player';
$userEmail = $user['email'] ?? '';
$userAvatar = $user['avatar_url'] ?? $user['picture'] ?? null;
$initials = strtoupper(substr($username, 0, 2));
$isCommissioner = in_array($user['role'] ?? '', ['admin', 'commissioner'], true);
$isFantasyMember = $isCommissioner || in_array($user['role'] ?? '', ['fantasy_member', 'dynasty_member'], true) || !empty($user['is_fantasy_member']);
$reqUri = $_SERVER['REQUEST_URI'] ?? '';
$isSurvivor = str_starts_with($reqUri, '/survivor');
$isPickem = !$isSurvivor && !str_starts_with($reqUri, '/fantasy') && !str_starts_with($reqUri, '/admin');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? "Wally's NFL Pool") ?></title>
    <!-- Matomo Analytics (Site ID 3: Atkins NFL Pool) -->
    <script>
      var _paq = window._paq = window._paq || [];
      _paq.push(['setDocumentTitle', document.domain + '/' + (document.title || 'Football')]);
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
            --stadium-navy: #0B1626;
            --surface-slate: #162235;
            --surface-border: #243247;
            --crisp-white: #F8FAFC;
            --yard-silver: #94A3B8;
            --gridiron-green: #15803D;
            --gold-accent: #EAB308;

            --turf-bg: #07150e;
            --turf-img: url('/assets/field-turf.svg');
            --card-surface: #162235;
            --card-surface-border: #243247;
            --picked-end: #162235;
            --matchup-card-bg: #162235;
            --matchup-header-bg: #0B1626;
            --matchup-border: #243247;
            --tiebreaker-bg: #162235;
            --input-bg: #0B1626;
            --input-border: #243247;
            --input-text: #F8FAFC;
            --bottom-bar-bg: #0B1626;
            --bottom-bar-border: #243247;
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

        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; transition: background-color 0.2s, color 0.2s; }
        .font-mono { font-family: 'JetBrains Mono', monospace; font-variant-numeric: tabular-nums; }
        .tabular-nums { font-variant-numeric: tabular-nums; }

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

        /* Static Centered Official NFL Midfield Watermark */
        .nfl-static-watermark-container {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
            user-select: none;
        }

        .nfl-static-watermark {
            width: min(540px, 75vw);
            max-height: 65vh;
            object-fit: contain;
            opacity: 0.20;
            filter: drop-shadow(0 0 50px rgba(0, 0, 0, 0.7));
            user-select: none;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        html[data-theme="light"] .nfl-static-watermark {
            opacity: 0.18;
            filter: drop-shadow(0 0 35px rgba(0, 0, 0, 0.25));
        }

        @media (max-width: 640px) {
            .nfl-static-watermark {
                width: min(340px, 80vw);
                max-height: 50vh;
                opacity: 0.16;
            }
            html[data-theme="light"] .nfl-static-watermark {
                opacity: 0.14;
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
<body class="football-field text-slate-100 min-h-screen flex flex-col antialiased selection:bg-amber-500 selection:text-black relative">

    <!-- Static Centered Official NFL Midfield Logo Background Item -->
    <div class="nfl-static-watermark-container" aria-hidden="true">
        <img src="/assets/nfl-logo-vector.svg" 
             alt="NFL Shield Watermark" 
             class="nfl-static-watermark"
             loading="eager">
    </div>

    <!-- Global Top Navigation -->
    <header class="border-b border-[#243247] bg-[#0B1626]/95 backdrop-blur sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
            
            <!-- Branding & Title -->
            <div class="flex items-center gap-4 shrink-0">
                <a href="/" class="flex items-center gap-2.5 group">
                    <div class="w-8 h-8 rounded-lg bg-[#162235] border border-[#243247] flex items-center justify-center font-black font-mono text-xs text-[#EAB308] group-hover:border-[#EAB308]/50 transition">
                        NFL
                    </div>
                    <div class="flex flex-col">
                        <span class="font-black tracking-tight text-[#F8FAFC] text-sm sm:text-base uppercase leading-none">Atkins NFL Pool</span>
                        <span class="text-[10px] font-mono text-[#94A3B8] uppercase tracking-wider font-semibold">2026 Season</span>
                    </div>
                </a>
            </div>

            <!-- Prominent Contest Toggle Tabs (Desktop / Tablet) -->
            <div class="hidden sm:flex items-center p-1 rounded-xl bg-[#0B1626] border border-[#243247] shadow-inner shrink-0">
                <a href="/pickem" 
                   class="px-4 py-1.5 text-xs font-black uppercase tracking-wider rounded-lg transition <?= $isPickem ? 'bg-[#EAB308] text-[#0B1626] shadow-sm font-black' : 'text-[#94A3B8] hover:text-[#F8FAFC]' ?>">
                    Weekly Pick 'Em
                </a>
                <a href="/survivor" 
                   class="px-4 py-1.5 text-xs font-black uppercase tracking-wider rounded-lg transition <?= $isSurvivor ? 'bg-[#15803D] text-[#F8FAFC] shadow-sm font-black' : 'text-[#94A3B8] hover:text-[#F8FAFC]' ?>">
                    Survivor Pool
                </a>
            </div>

            <!-- Active Contest Sub-Navigation & Profile -->
            <div class="flex items-center gap-3">
                <nav class="hidden md:flex items-center gap-1.5 text-xs font-bold font-mono">
                    <?php if ($isPickem): ?>
                        <a href="/pickem" class="px-2.5 py-1 rounded <?= !str_contains($reqUri, 'standings') ? 'text-[#EAB308] bg-[#162235] border border-[#243247]' : 'text-[#94A3B8] hover:text-[#F8FAFC]' ?>">
                            MATCHUPS
                        </a>
                        <a href="/pickem/standings" class="px-2.5 py-1 rounded <?= str_contains($reqUri, 'standings') ? 'text-[#EAB308] bg-[#162235] border border-[#243247]' : 'text-[#94A3B8] hover:text-[#F8FAFC]' ?>">
                            STANDINGS
                        </a>
                    <?php elseif ($isSurvivor): ?>
                        <a href="/survivor" class="px-2.5 py-1 rounded <?= !str_contains($reqUri, 'standings') ? 'text-[#15803D] bg-[#162235] border border-[#243247]' : 'text-[#94A3B8] hover:text-[#F8FAFC]' ?>">
                            SELECTION
                        </a>
                        <a href="/survivor/standings" class="px-2.5 py-1 rounded <?= str_contains($reqUri, 'standings') ? 'text-[#15803D] bg-[#162235] border border-[#243247]' : 'text-[#94A3B8] hover:text-[#F8FAFC]' ?>">
                            LEADERBOARD
                        </a>
                    <?php endif; ?>

                    <?php if ($isCommissioner): ?>
                        <a href="/admin/payments" class="px-2.5 py-1 rounded text-[11px] font-bold <?= str_starts_with($reqUri, '/admin') ? 'text-purple-200 bg-purple-900/60 border border-purple-500/50' : 'text-purple-400 hover:text-purple-200' ?>">
                            COMMISSIONER
                        </a>
                    <?php endif; ?>
                </nav>

                <?php if (!empty($user)): ?>
                    <div class="relative" id="userMenuContainer">
                        <button type="button" 
                                id="userMenuBtn"
                                class="flex items-center gap-2 p-1 pl-1.5 pr-2.5 rounded-full hover:bg-[#162235] border border-[#243247] hover:border-slate-600 transition focus:outline-none"
                                aria-expanded="false" 
                                aria-haspopup="true">
                            <?php if (!empty($userAvatar)): ?>
                                <img src="<?= htmlspecialchars($userAvatar) ?>" 
                                     alt="<?= htmlspecialchars($username) ?>" 
                                     class="w-7 h-7 rounded-full object-cover border border-[#243247]">
                            <?php else: ?>
                                <div class="w-7 h-7 rounded-full bg-[#162235] border border-[#243247] flex items-center justify-center text-xs font-black text-[#EAB308] font-mono">
                                    <?= htmlspecialchars($initials) ?>
                                </div>
                            <?php endif; ?>
                            <span class="hidden sm:inline text-xs font-semibold text-[#F8FAFC] max-w-[110px] truncate"><?= htmlspecialchars($username) ?></span>
                            <svg class="w-3.5 h-3.5 text-[#94A3B8] transition-transform duration-200" id="userMenuChevron" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <!-- Profile Dropdown Menu -->
                        <div id="userMenuDropdown" 
                             class="theme-menu-panel hidden absolute right-0 mt-2 w-64 rounded-xl bg-[#162235] border border-[#243247] shadow-2xl z-50 py-2 divide-y divide-[#243247]">
                            <!-- User identity banner -->
                            <div class="px-4 py-3 flex items-center gap-3">
                                <?php if (!empty($userAvatar)): ?>
                                    <img src="<?= htmlspecialchars($userAvatar) ?>" 
                                         alt="<?= htmlspecialchars($username) ?>" 
                                         class="w-9 h-9 rounded-full object-cover border border-[#243247] shrink-0">
                                <?php else: ?>
                                    <div class="w-9 h-9 rounded-full bg-[#0B1626] border border-[#243247] flex items-center justify-center text-xs font-black text-[#EAB308] font-mono shrink-0">
                                        <?= htmlspecialchars($initials) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <p class="text-sm font-bold text-[#F8FAFC] truncate"><?= htmlspecialchars($username) ?></p>
                                        <?php if ($isCommissioner): ?>
                                            <span class="text-[9px] font-mono font-bold px-1 py-0.5 rounded bg-purple-900/60 text-purple-300 border border-purple-500/30">COMMISH</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs text-[#94A3B8] truncate"><?= htmlspecialchars($userEmail) ?></p>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="py-1.5">
                                <a href="https://auth.wallyatkins.com/account?return_url=https://football.wallyatkins.com" 
                                   class="flex items-center gap-2.5 px-4 py-2 text-xs font-semibold text-slate-200 hover:text-white hover:bg-[#0B1626] transition">
                                    <svg class="w-4 h-4 text-[#94A3B8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    <div class="flex flex-col">
                                        <span>Account Settings</span>
                                        <span class="text-[10px] text-[#94A3B8] font-normal">Avatar, username &amp; profile</span>
                                    </div>
                                </a>

                                <?php if ($isFantasyMember): ?>
                                    <a href="/fantasy/vault" 
                                       class="flex items-center gap-2.5 px-4 py-2 text-xs font-semibold text-amber-300 hover:text-white hover:bg-[#0B1626] transition">
                                        <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                        <div class="flex flex-col">
                                            <span>Dynasty Vault</span>
                                            <span class="text-[10px] text-[#94A3B8] font-normal">20-Year Fantasy History</span>
                                        </div>
                                    </a>
                                <?php endif; ?>

                                <button type="button" 
                                        onclick="toggleWallyTheme()" 
                                        class="w-full flex items-center justify-between px-4 py-2 text-xs font-semibold text-slate-200 hover:text-white hover:bg-[#0B1626] transition text-left">
                                    <div class="flex items-center gap-2.5">
                                        <svg class="w-4 h-4 text-[#94A3B8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                                        <div class="flex flex-col">
                                            <span>Toggle Theme</span>
                                            <span class="text-[10px] text-[#94A3B8] font-normal" id="themeDropdownSub">Dark / Light Mode</span>
                                        </div>
                                    </div>
                                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-[#0B1626] text-[#94A3B8] border border-[#243247]" id="themePill">Dark</span>
                                </button>
                            </div>

                            <!-- Sign Out -->
                            <div class="py-1.5">
                                <a href="/auth/logout" 
                                   class="flex items-center gap-2.5 px-4 py-2 text-xs font-semibold text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 transition">
                                    <svg class="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                    <span>Sign Out</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="flex items-center gap-2">
                        <button type="button" 
                                onclick="toggleWallyTheme()" 
                                class="p-2 rounded-lg text-[#94A3B8] hover:text-white hover:bg-[#162235] border border-[#243247] transition" 
                                title="Toggle Dark/Light Mode">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                        </button>
                        <a href="/auth/login" class="px-3.5 py-1.5 text-xs sm:text-sm font-semibold rounded-lg bg-[#15803D] hover:bg-emerald-600 text-white transition shadow-sm">
                            Sign In
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mobile Contest Toggle Bar -->
        <div class="sm:hidden px-4 py-2 border-t border-[#243247] bg-[#0B1626]">
            <div class="grid grid-cols-2 p-1 rounded-xl bg-[#162235] border border-[#243247] text-center">
                <a href="/pickem" class="py-1.5 text-xs font-black uppercase tracking-wider rounded-lg transition <?= $isPickem ? 'bg-[#EAB308] text-[#0B1626] shadow-sm font-black' : 'text-[#94A3B8]' ?>">
                    Weekly Pick 'Em
                </a>
                <a href="/survivor" class="py-1.5 text-xs font-black uppercase tracking-wider rounded-lg transition <?= $isSurvivor ? 'bg-[#15803D] text-[#F8FAFC] shadow-sm font-black' : 'text-[#94A3B8]' ?>">
                    Survivor Pool
                </a>
            </div>
        </div>
    </header>

    <!-- Mobile Bottom Navigation (Clean Vector Icons, No Decorative Emojis) -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-[#0B1626]/95 border-t border-[#243247] flex justify-around py-2.5 backdrop-blur">
        <a href="/pickem" class="flex flex-col items-center gap-1 text-[10px] font-mono uppercase tracking-wider <?= str_starts_with($reqUri, '/pickem') && !str_contains($reqUri, 'standings') ? 'text-[#EAB308] font-bold' : 'text-[#94A3B8]' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
            <span>Picks</span>
        </a>
        <a href="/pickem/standings" class="flex flex-col items-center gap-1 text-[10px] font-mono uppercase tracking-wider <?= str_contains($reqUri, '/pickem/standings') ? 'text-[#EAB308] font-bold' : 'text-[#94A3B8]' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            <span>Standings</span>
        </a>
        <a href="/survivor" class="flex flex-col items-center gap-1 text-[10px] font-mono uppercase tracking-wider <?= str_starts_with($reqUri, '/survivor') && !str_contains($reqUri, 'standings') ? 'text-[#15803D] font-bold' : 'text-[#94A3B8]' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            <span>Survivor</span>
        </a>
        <a href="/survivor/standings" class="flex flex-col items-center gap-1 text-[10px] font-mono uppercase tracking-wider <?= str_contains($reqUri, '/survivor/standings') ? 'text-[#15803D] font-bold' : 'text-[#94A3B8]' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            <span>Survivors</span>
        </a>
        <?php if (!empty($isCommissioner)): ?>
            <a href="/admin/payments" class="flex flex-col items-center gap-1 text-[10px] font-mono uppercase tracking-wider <?= str_starts_with($reqUri, '/admin') ? 'text-purple-300 font-bold' : 'text-[#94A3B8]' ?>">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                <span>Admin</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl mx-auto w-full px-4 sm:px-6 py-6 pb-20 md:pb-8 relative z-10">
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

    <footer class="hidden md:block border-t border-slate-800/80 py-6 text-center text-xs text-slate-500 relative z-10">
        <p>&copy; <?= date('Y') ?> Wally's NFL Pool &bull; <a href="https://wallyatkins.com" class="hover:text-slate-400 transition underline">wallyatkins.com</a> &bull; <a href="https://wallyatkins.com/privacy" class="hover:text-slate-400 transition underline">Privacy Policy</a> &bull; <a href="https://wallyatkins.com/terms" class="hover:text-slate-400 transition underline">Terms of Use</a> &bull; <a href="https://wallyatkins.com/#contact" class="hover:text-slate-400 transition underline">Get in Touch</a> &bull; Identity by <a href="https://auth.wallyatkins.com" class="hover:text-slate-400 transition underline">WallyAuth</a></p>
    </footer>

    <?php if (!empty($user)): ?>
        <?php require __DIR__ . '/partials/chat_widget.php'; ?>
    <?php endif; ?>

    <script>
        // Theme toggle helper
        function updateThemeUI(theme) {
            var isDark = theme === 'dark';
            var sub = isDark ? 'Currently Dark Mode' : 'Currently Light Mode';
            var pill = isDark ? 'Dark' : 'Light';

            var subEl = document.getElementById('themeDropdownSub');
            if (subEl) subEl.textContent = sub;
            var pillEl = document.getElementById('themePill');
            if (pillEl) pillEl.textContent = pill;
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
