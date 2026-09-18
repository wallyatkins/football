<?php
use WallyFootball\Support\TeamData;

ob_start();
$entryStatus = $entry['payment_status'] ?? 'none';
$isPaid = in_array($entryStatus, ['paid', 'exempt'], true);
$isUserLocked = !empty($isWeekLocked);
$hasConfirmedPicks = !empty($entry['is_locked']);
$firstKickoffFormatted = $firstKickoffFormatted ?? 'Kickoff of Week ' . $week;
$cutoffFormatted = $cutoffFormatted ?? ($firstKickoffFormatted ?? 'Cutoff of Week ' . $week);
$isCommissioner = in_array($user['role'] ?? '', ['admin', 'commissioner'], true);

$venmoUrl = 'https://account.venmo.com/u/WallyAtkins';
$payPalUrl = 'https://paypal.me/WallyAtkins';
$cashAppUrl = 'https://cash.app/$WallyAtkins';

// Designated Tiebreaker Game Data
$tbAwayData = !empty($tiebreakerGame) ? TeamData::get($tiebreakerGame['away_team']) : null;
$tbHomeData = !empty($tiebreakerGame) ? TeamData::get($tiebreakerGame['home_team']) : null;
$tbMatchupLabel = ($tbAwayData && $tbHomeData) ? "{$tbAwayData['name']} @ {$tbHomeData['name']}" : "Official Tiebreaker Game";
?>

<div class="space-y-6">

    <!-- Header & Single-Week Focus -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800/80 pb-5">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20 uppercase tracking-wider">NFL Regular Season</span>
                <span class="text-xs text-slate-400">Season <?= htmlspecialchars((string) $season) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white flex items-center gap-3">
                <span>Week <?= htmlspecialchars((string) $week) ?> Matchups</span>
                <span class="text-xs font-mono font-semibold px-2.5 py-1 rounded-full bg-slate-900 border border-slate-700 text-slate-300">
                    <?= count($games) ?> Games
                </span>
            </h1>
        </div>

        <!-- Single-Week Focus Action Bar -->
        <div class="flex items-center gap-2">
            <span class="px-3.5 py-1.5 text-xs font-black font-mono rounded-lg bg-amber-500 text-slate-950 shadow-sm flex items-center gap-1.5">
                <span>🎯</span> Active Week <?= $week ?>
            </span>
            <a href="/fantasy/vault?tab=pools" 
               class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-800 transition flex items-center gap-1.5"
               title="View historical results in the Dynasty Vault">
                <span>🏛️</span>
                <span class="hidden sm:inline">Archives</span>
            </a>

            <!-- How It Works Modal Button -->
            <button type="button" 
                    id="btnOpenHowItWorks"
                    class="px-3 py-1.5 text-xs font-bold rounded-lg bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border border-amber-500/30 transition flex items-center gap-1.5 shadow-sm">
                <span>💡</span>
                <span>How It Works</span>
            </button>

            <?php if (!empty($isCommissioner)): ?>
                <a href="/admin/payments?week=<?= $week ?>&season=<?= $season ?>" 
                   class="px-3 py-1.5 text-xs font-bold rounded-lg bg-purple-900/60 border border-purple-500/40 text-purple-300 hover:bg-purple-800 hover:text-white transition flex items-center gap-1.5 shadow-sm">
                    <span>👑</span>
                    <span class="hidden sm:inline">Commissioner Portal</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Center Column Gridiron Layout (One Game Per Row) -->
    <div class="max-w-3xl mx-auto space-y-6">

    <!-- Week Champion Congratulatory Banners (Displayed when games are final) -->
    <?php 
    $activeCelebrateWeek = !empty($isWeekComplete) ? $week : (!empty($lastCompletedWeek) ? $lastCompletedWeek : null);
    $activeWinnersOverall = !empty($isWeekComplete) ? ($weeklyWinnersOverall ?? []) : ($lastWeekWinnersOverall ?? []);
    $activeWinnersPaid    = !empty($isWeekComplete) ? ($weeklyWinnersPaid ?? []) : ($lastWeekWinnersPaid ?? []);
    $activeWinnersFree    = !empty($isWeekComplete) ? ($weeklyWinnersFree ?? []) : ($lastWeekWinnersFree ?? []);
    $activePotInfo        = !empty($isWeekComplete) ? ($potInfo ?? null) : ($lastWeekPotInfo ?? null);
    ?>

    <?php if ($activeCelebrateWeek !== null && (!empty($activeWinnersOverall) || !empty($activeWinnersPaid) || !empty($activeWinnersFree))): ?>
        <div class="p-6 rounded-2xl bg-gradient-to-r from-amber-500/15 via-yellow-500/10 to-emerald-500/15 border-2 border-amber-400/50 shadow-2xl relative overflow-hidden space-y-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-800/80 pb-3">
                <div class="flex items-center gap-3">
                    <span class="p-2.5 rounded-xl bg-amber-500/25 text-amber-300 text-2xl border border-amber-400/50 shadow-sm">🏆</span>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-mono font-black px-2.5 py-0.5 rounded-full bg-amber-400 text-slate-950 uppercase tracking-wider">
                                Week <?= $activeCelebrateWeek ?> Winners Circle
                            </span>
                            <span class="text-xs font-mono text-emerald-400 font-bold"><?= !empty($isWeekComplete) ? 'Week Complete' : 'Last Week Wrapup' ?></span>
                        </div>
                        <h2 class="text-lg sm:text-xl font-black text-white mt-0.5">Week <?= $activeCelebrateWeek ?> Pool Champions</h2>
                    </div>
                </div>
                <a href="/pickem/standings?week=<?= $activeCelebrateWeek ?>&season=<?= $season ?>" class="px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition shadow-sm shrink-0">
                    Week <?= $activeCelebrateWeek ?> Standings &rarr;
                </a>
            </div>

            <!-- Three Winner Categories Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 text-xs">
                
                <!-- 1. Overall Champion -->
                <div class="p-4 rounded-xl bg-slate-900/90 border border-amber-400/50 shadow-md flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-1.5 mb-2">
                            <span class="text-base">👑</span>
                            <span class="font-mono font-black text-[10px] uppercase tracking-wider text-amber-400">Overall Champion</span>
                        </div>
                        <?php if (!empty($activeWinnersOverall)): ?>
                            <div class="font-black text-white text-base truncate">
                                <?= implode(' &amp; ', array_map(fn($w) => htmlspecialchars($w['username']), $activeWinnersOverall)) ?>
                            </div>
                            <div class="text-slate-300 font-semibold mt-1">
                                <span class="text-amber-300 font-bold"><?= $activeWinnersOverall[0]['correct_picks'] ?></span> correct picks
                                <?= count($activeWinnersOverall) > 1 ? '<span class="text-amber-400 text-[10px] ml-1">(Tied)</span>' : '' ?>
                            </div>
                        <?php else: ?>
                            <div class="text-slate-500 italic">Pending final games</div>
                        <?php endif; ?>
                    </div>
                    <span class="text-[10px] text-slate-500 font-mono mt-2 pt-1.5 border-t border-slate-800">All entrants combined</span>
                </div>

                <!-- 2. Money Pool Winner -->
                <div class="p-4 rounded-xl bg-slate-900/90 border border-emerald-400/50 shadow-md flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-1.5 mb-2">
                            <span class="text-base">💰</span>
                            <span class="font-mono font-black text-[10px] uppercase tracking-wider text-emerald-400">Money Pool Winner</span>
                        </div>
                        <?php if (!empty($activeWinnersPaid)): ?>
                            <div class="font-black text-white text-base truncate">
                                <?= implode(' &amp; ', array_map(fn($w) => htmlspecialchars($w['username']), $activeWinnersPaid)) ?>
                            </div>
                            <div class="text-slate-300 font-semibold mt-1">
                                <span class="text-emerald-300 font-bold"><?= $activeWinnersPaid[0]['correct_picks'] ?></span> correct
                                <?php if (!empty($activePotInfo['total_pot']) && $activePotInfo['total_pot'] > 0): ?>
                                    &bull; Won <strong class="font-mono text-emerald-300">$<?= number_format($activePotInfo['payout_per_winner'] / max(1, count($activeWinnersPaid)), 2) ?></strong>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-slate-500 italic">No cash entries verified</div>
                        <?php endif; ?>
                    </div>
                    <span class="text-[10px] text-slate-500 font-mono mt-2 pt-1.5 border-t border-slate-800">$10 stake cash pool</span>
                </div>

                <!-- 3. For Fun / Free Pool Winner -->
                <div class="p-4 rounded-xl bg-slate-900/90 border border-violet-400/50 shadow-md flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-1.5 mb-2">
                            <span class="text-base">🎮</span>
                            <span class="font-mono font-black text-[10px] uppercase tracking-wider text-violet-400">Free / Fun Winner</span>
                        </div>
                        <?php if (!empty($activeWinnersFree)): ?>
                            <div class="font-black text-white text-base truncate">
                                <?= implode(' &amp; ', array_map(fn($w) => htmlspecialchars($w['username']), $activeWinnersFree)) ?>
                            </div>
                            <div class="text-slate-300 font-semibold mt-1">
                                <span class="text-violet-300 font-bold"><?= $activeWinnersFree[0]['correct_picks'] ?></span> correct
                                <?= count($activeWinnersFree) > 1 ? '<span class="text-violet-400 text-[10px] ml-1">(Tied)</span>' : '' ?>
                            </div>
                        <?php else: ?>
                            <div class="text-slate-500 italic">No free entries</div>
                        <?php endif; ?>
                    </div>
                    <span class="text-[10px] text-slate-500 font-mono mt-2 pt-1.5 border-t border-slate-800">Free tier &bull; Bragging rights</span>
                </div>

            </div>
        </div>
    <?php endif; ?>


    <!-- User Pick Lock & Payment Callout -->
    <?php if ($isUserLocked): ?>
        <div class="locked-banner p-6 rounded-2xl bg-gradient-to-br from-emerald-950/40 via-slate-900 to-slate-950 border border-emerald-500/40 shadow-xl space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-5">
                <div class="flex items-start gap-4">
                    <div class="p-3 rounded-xl bg-emerald-500/20 text-emerald-300 text-3xl border border-emerald-500/30 shrink-0">
                        🔒
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h2 class="text-lg font-black text-white">Your Week <?= $week ?> Picks Are Locked In!</h2>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 uppercase tracking-wider">
                                LOCKED &bull; GAME STARTED
                            </span>
                            <?php if ($isPaid): ?>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-purple-500/20 text-purple-300 border border-purple-500/40 uppercase tracking-wider">
                                    ✓ STAKE VERIFIED
                                </span>
                            <?php else: ?>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 uppercase tracking-wider">
                                    AWAITING $10 PAYMENT
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-slate-300 mt-1 leading-relaxed">
                            Picks closed at the selection cutoff (<strong><?= htmlspecialchars($cutoffFormatted) ?></strong> — 1 hour into the first game).
                            <?php if ($isPaid): ?>
                                Your picks and $10.00 entry fee are verified. You are fully active in this week's prize pool!
                            <?php else: ?>
                                Your picks are officially recorded! Please send your <strong>$10.00</strong> entry stake to Commissioner Wally below so your picks count toward this week's cash pot.
                            <?php endif; ?>
                        </p>
                        <div class="mt-2.5 inline-flex items-center gap-2 px-3 py-1 rounded-lg bg-slate-950/90 border border-slate-700/80 text-xs">
                            <span class="text-slate-400">Payment Memo Note:</span>
                            <span class="font-mono font-bold text-amber-300 select-all">Pickem - <?= htmlspecialchars($user['username'] ?? 'username') ?> - Week <?= $week ?></span>
                        </div>
                    </div>
                </div>

                <!-- Verified Payment Action Buttons -->
                <?php if (!$isPaid): ?>
                    <div class="flex items-center gap-2 flex-wrap shrink-0">
                        <a href="<?= htmlspecialchars($venmoUrl) ?>" target="_blank" rel="noopener noreferrer" 
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#008CFF]/20 hover:bg-[#008CFF]/30 border border-[#008CFF]/50 text-[#38bdf8] font-bold text-xs transition shadow-sm hover:scale-105 transform">
                            <span>📱</span> Venmo (@WallyAtkins) &rarr;
                        </a>
                        <a href="<?= htmlspecialchars($payPalUrl) ?>" target="_blank" rel="noopener noreferrer" 
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#0079C1]/20 hover:bg-[#0079C1]/30 border border-[#0079C1]/50 text-[#38bdf8] font-bold text-xs transition shadow-sm hover:scale-105 transform">
                            <span>💳</span> PayPal (paypal.me/WallyAtkins) &rarr;
                        </a>
                        <a href="<?= htmlspecialchars($cashAppUrl) ?>" target="_blank" rel="noopener noreferrer" 
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#00D632]/20 hover:bg-[#00D632]/30 border border-[#00D632]/50 text-[#4ade80] font-bold text-xs transition shadow-sm hover:scale-105 transform">
                            <span>⚡</span> Cash App ($WallyAtkins) &rarr;
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($hasConfirmedPicks): ?>
        <!-- Confirmed & Editable Banner (Pre-Kickoff) -->
        <div class="p-6 rounded-2xl bg-gradient-to-br from-slate-900 via-emerald-950/20 to-slate-950 border border-emerald-500/40 shadow-xl space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-5">
                <div class="flex items-start gap-4">
                    <div class="p-3 rounded-xl bg-emerald-500/20 text-emerald-300 text-3xl border border-emerald-500/30 shrink-0">
                        ✓
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h2 class="text-lg font-black text-white">Your Week <?= $week ?> Picks Are Confirmed!</h2>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 uppercase tracking-wider">
                                CONFIRMED &bull; EDITABLE UNTIL KICKOFF
                            </span>
                            <?php if ($isPaid): ?>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-purple-500/20 text-purple-300 border border-purple-500/40 uppercase tracking-wider">
                                    ✓ STAKE VERIFIED
                                </span>
                            <?php else: ?>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 uppercase tracking-wider">
                                    AWAITING $10 PAYMENT
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-slate-300 mt-1 leading-relaxed">
                            Your ballot has been confirmed and saved! You can still change any pick or your tiebreaker score anytime before the cutoff deadline (<strong><?= htmlspecialchars($cutoffFormatted) ?></strong>). All changes are saved automatically behind the scenes.
                        </p>
                        <div class="mt-2.5 inline-flex items-center gap-2 px-3 py-1 rounded-lg bg-slate-950/90 border border-slate-700/80 text-xs">
                            <span class="text-slate-400">Payment Memo Note:</span>
                            <span class="font-mono font-bold text-amber-300 select-all">Pickem - <?= htmlspecialchars($user['username'] ?? 'username') ?> - Week <?= $week ?></span>
                        </div>
                    </div>
                </div>

                <!-- Verified Payment Action Buttons -->
                <?php if (!$isPaid): ?>
                    <div class="flex items-center gap-2 flex-wrap shrink-0">
                        <a href="<?= htmlspecialchars($venmoUrl) ?>" target="_blank" rel="noopener noreferrer" 
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#008CFF]/20 hover:bg-[#008CFF]/30 border border-[#008CFF]/50 text-[#38bdf8] font-bold text-xs transition shadow-sm hover:scale-105 transform">
                            <span>📱</span> Venmo (@WallyAtkins) &rarr;
                        </a>
                        <a href="<?= htmlspecialchars($payPalUrl) ?>" target="_blank" rel="noopener noreferrer" 
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#0079C1]/20 hover:bg-[#0079C1]/30 border border-[#0079C1]/50 text-[#38bdf8] font-bold text-xs transition shadow-sm hover:scale-105 transform">
                            <span>💳</span> PayPal (paypal.me/WallyAtkins) &rarr;
                        </a>
                        <a href="<?= htmlspecialchars($cashAppUrl) ?>" target="_blank" rel="noopener noreferrer" 
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#00D632]/20 hover:bg-[#00D632]/30 border border-[#00D632]/50 text-[#4ade80] font-bold text-xs transition shadow-sm hover:scale-105 transform">
                            <span>⚡</span> Cash App ($WallyAtkins) &rarr;
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Pre-Lock Reminder Banner (Auto-saving Draft) -->
        <div class="prelock-banner p-5 rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900/90 to-slate-950 border border-amber-500/30 shadow-xl space-y-3">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-start gap-3.5">
                    <div class="p-2.5 rounded-xl bg-amber-500/10 text-amber-400 text-2xl border border-amber-500/20 shrink-0">
                        🏈
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-white">Weekly Pick'em Stake: $10.00</h3>
                            <span class="text-[10px] font-mono uppercase tracking-wider font-bold px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 border border-slate-700">
                                Auto-Saving Draft
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5 leading-relaxed">
                            Pick a winner for each matchup and enter the tiebreaker score for <strong><?= htmlspecialchars($tbMatchupLabel) ?></strong>. <strong>Your selections and tiebreaker score auto-save silently behind the scenes.</strong> You can change your picks anytime until the selection cutoff (<strong><?= htmlspecialchars($cutoffFormatted) ?></strong> — 1 hour into the opening game).
                        </p>
                        <div class="mt-2 text-[11px] text-slate-400">
                            Remember to add note <span class="font-mono text-amber-300 font-bold">Pickem - <?= htmlspecialchars($user['username'] ?? 'username') ?> - Week <?= $week ?></span> when sending your $10 stake.
                        </div>
                    </div>
                </div>

                <!-- Payment Channels -->
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="<?= htmlspecialchars($venmoUrl) ?>" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#008CFF]/15 hover:bg-[#008CFF]/25 border border-[#008CFF]/40 text-[#38bdf8] font-bold text-xs transition">
                        <span>📱</span> Venmo
                    </a>
                    <a href="<?= htmlspecialchars($payPalUrl) ?>" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#0079C1]/15 hover:bg-[#0079C1]/25 border border-[#0079C1]/40 text-[#38bdf8] font-bold text-xs transition">
                        <span>💳</span> PayPal
                    </a>
                    <a href="<?= htmlspecialchars($cashAppUrl) ?>" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#00D632]/15 hover:bg-[#00D632]/25 border border-[#00D632]/40 text-[#4ade80] font-bold text-xs transition">
                        <span>⚡</span> Cash App
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Real-Time Validation Notification Box (Dynamic) -->
    <div id="validationAlert" class="hidden p-4 rounded-xl bg-rose-500/15 border-2 border-rose-500/60 shadow-xl transition-all duration-300">
        <div class="flex items-start gap-3 text-rose-300 text-xs font-semibold">
            <span class="text-xl">⚠️</span>
            <div id="validationAlertMessage" class="flex-1 space-y-1">
                <!-- Injected via JavaScript -->
            </div>
        </div>
    </div>

    <!-- User's Live Performance Scorecard (When any game is final or user has submitted) -->
    <?php if (!empty($userGradedCount) && $userGradedCount > 0): ?>
        <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-slate-950 border border-slate-700/80 shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="p-3 rounded-2xl bg-amber-500/15 text-amber-400 text-2xl border border-amber-500/30 shrink-0">
                    📊
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-bold text-white">Your Week <?= $week ?> Pick'em Performance</h3>
                        <span class="text-[10px] font-mono uppercase font-bold px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 border border-slate-700">
                            Live Grading
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5">
                        <?= $userGradedCount ?> of <?= count($games) ?> games graded &bull; Current Accuracy: <strong class="text-amber-300 font-mono"><?= $userGradedCount > 0 ? round(($userCorrectCount / $userGradedCount) * 100) : 0 ?>%</strong>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2.5 font-mono flex-wrap">
                <div class="px-3.5 py-2 rounded-xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 flex items-center gap-2 shadow-sm">
                    <span class="text-lg font-black">✓</span>
                    <div>
                        <span class="text-base font-black"><?= $userCorrectCount ?></span>
                        <span class="text-[10px] uppercase block text-emerald-400/80">Correct</span>
                    </div>
                </div>
                <div class="px-3.5 py-2 rounded-xl bg-rose-500/20 border border-rose-500/40 text-rose-300 flex items-center gap-2 shadow-sm">
                    <span class="text-lg font-black">✗</span>
                    <div>
                        <span class="text-base font-black"><?= $userIncorrectCount ?></span>
                        <span class="text-[10px] uppercase block text-rose-400/80">Missed</span>
                    </div>
                </div>
                <div class="px-3.5 py-2 rounded-xl bg-slate-800/80 border border-slate-700 text-slate-300 flex items-center gap-2 shadow-sm">
                    <span class="text-lg">⏳</span>
                    <div>
                        <span class="text-base font-black"><?= $userPendingCount ?></span>
                        <span class="text-[10px] uppercase block text-slate-400">Pending</span>
                    </div>
                </div>
                <a href="/pickem/standings?week=<?= $week ?>&season=<?= $season ?>" class="px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs uppercase tracking-wider transition shadow flex items-center gap-1.5">
                    <span>🏆</span>
                    <span>Standings</span>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Pick'em Form -->
    <form action="/pickem/save" method="POST" id="pickemForm" class="space-y-6">
        <input type="hidden" name="season_year" value="<?= htmlspecialchars((string) $season) ?>">
        <input type="hidden" name="week_number" value="<?= htmlspecialchars((string) $week) ?>">

        <div class="space-y-6">
            <?php foreach ($games as $game): ?>
                <?php
                $isKickoffLocked = (bool) $game['is_locked'];
                $isMnf = (bool) $game['is_mnf'];
                $userPick = $game['user_pick'];
                $kickoff = new DateTimeImmutable($game['kickoff_time']);
                $kickoffEt = $kickoff->setTimezone(new DateTimeZone('America/New_York'))->format('D, M j @ g:i A T');
                $isFinal = ($game['status'] === 'final');
                $inProgress = ($game['status'] === 'in_progress');

                // Card interaction lock state
                $cardDisabled = $isKickoffLocked || $isUserLocked;

                // Away Team Data
                $awayAbbr = $game['away_team'];
                $awayTeam = TeamData::get($awayAbbr);
                $awayColor = $awayTeam['color'];
                $awayPicked = ($userPick === $awayAbbr);

                // Home Team Data
                $homeAbbr = $game['home_team'];
                $homeTeam = TeamData::get($homeAbbr);
                $homeColor = $homeTeam['color'];
                $homePicked = ($userPick === $homeAbbr);

                $hasPick = $awayPicked || $homePicked;

                $isCorrectAway = ($isFinal && $awayPicked && ($game['pick_result'] ?? '') === 'correct');
                $isIncorrectAway = ($isFinal && $awayPicked && ($game['pick_result'] ?? '') === 'incorrect');
                $isCorrectHome = ($isFinal && $homePicked && ($game['pick_result'] ?? '') === 'correct');
                $isIncorrectHome = ($isFinal && $homePicked && ($game['pick_result'] ?? '') === 'incorrect');

                $awayCardStyle = "";
                if ($isCorrectAway) {
                    $awayCardStyle = "border-color: #10b981; background-color: #064e3b; box-shadow: 0 0 20px rgba(16,185,129,0.3);";
                } elseif ($isIncorrectAway) {
                    $awayCardStyle = "border-color: #f43f5e; background-color: #4c0519; box-shadow: 0 0 16px rgba(244,63,94,0.25);";
                } elseif ($awayPicked) {
                    $awayCardStyle = "border-color: {$awayColor}; background-color: #1e293b; box-shadow: 0 0 20px {$awayColor}44;";
                } else {
                    $awayCardStyle = "border-color: #334155; background-color: #0f172a;";
                }

                $homeCardStyle = "";
                if ($isCorrectHome) {
                    $homeCardStyle = "border-color: #10b981; background-color: #064e3b; box-shadow: 0 0 20px rgba(16,185,129,0.3);";
                } elseif ($isIncorrectHome) {
                    $homeCardStyle = "border-color: #f43f5e; background-color: #4c0519; box-shadow: 0 0 16px rgba(244,63,94,0.25);";
                } elseif ($homePicked) {
                    $homeCardStyle = "border-color: {$homeColor}; background-color: #1e293b; box-shadow: 0 0 20px {$homeColor}44;";
                } else {
                    $homeCardStyle = "border-color: #334155; background-color: #0f172a;";
                }
                ?>
                <div class="matchup-card rounded-2xl border transition-all duration-200 overflow-hidden shadow-lg <?= $isMnf ? 'border-amber-500/60 bg-slate-900 ring-1 ring-amber-500/30' : 'border-slate-800 bg-slate-900' ?>"
                     data-game-id="<?= $game['id'] ?>"
                     data-unlocked="<?= $cardDisabled ? 'false' : 'true' ?>"
                     data-away-abbr="<?= htmlspecialchars($awayAbbr) ?>"
                     data-away-name="<?= htmlspecialchars($awayTeam['name']) ?>"
                     data-home-abbr="<?= htmlspecialchars($homeAbbr) ?>"
                     data-home-name="<?= htmlspecialchars($homeTeam['name']) ?>">
                    
                    <!-- Matchup Broadcast Header -->
                    <div class="matchup-header-bar flex items-center justify-between px-4 py-2.5 bg-slate-950 border-b border-slate-800 text-xs">
                        <div class="flex items-center gap-2 text-slate-400">
                            <span class="font-mono text-[11px]"><?= htmlspecialchars($kickoffEt) ?></span>
                            <?php if ($isMnf): ?>
                                <span class="px-2 py-0.5 rounded text-[10px] font-black bg-amber-500 text-slate-950 uppercase tracking-wider shadow-sm flex items-center gap-1">
                                    ⭐ Official Tiebreaker Game
                                </span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($isFinal): ?>
                                <?php if (($game['pick_result'] ?? '') === 'correct'): ?>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-mono font-black bg-emerald-500/25 text-emerald-300 border border-emerald-500/50 shadow-sm flex items-center gap-1">
                                        <span>✓</span>
                                        <span>CORRECT (+1)</span>
                                    </span>
                                <?php elseif (($game['pick_result'] ?? '') === 'incorrect'): ?>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-mono font-black bg-rose-500/25 text-rose-300 border border-rose-500/50 shadow-sm flex items-center gap-1">
                                        <span>✗</span>
                                        <span>MISSED (0)</span>
                                    </span>
                                <?php else: ?>
                                    <span class="px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-800 text-slate-300 border border-slate-700">FINAL</span>
                                <?php endif; ?>
                            <?php elseif ($inProgress): ?>
                                <span class="px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 animate-pulse">LIVE</span>
                            <?php elseif ($isKickoffLocked): ?>
                                <span class="px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">🔒 KICKOFF</span>
                            <?php elseif ($isUserLocked): ?>
                                <span class="px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">LOCKED</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Teams Selection Grid -->
                    <div class="p-3.5 relative">
                        <div class="grid grid-cols-2 gap-3.5">
                        
                            <!-- Away Team Card -->
                            <label class="team-card relative flex flex-col items-center justify-center p-4 pt-5 rounded-xl border-2 transition-all select-none group overflow-hidden <?= $awayPicked ? 'is-picked' : '' ?>
                                <?= $cardDisabled ? 'pointer-events-none' : 'cursor-pointer hover:scale-[1.01]' ?>"
                                style="<?= $awayCardStyle ?>">
                                
                                <!-- Team Color Top Accent Stripe -->
                                <div class="absolute top-0 left-0 right-0 h-1.5" style="background-color: <?= htmlspecialchars($awayColor) ?>;"></div>

                                <input type="radio" 
                                       name="picks[<?= $game['id'] ?>]" 
                                       value="<?= htmlspecialchars($awayAbbr) ?>" 
                                       class="sr-only pick-radio"
                                       data-abbr="<?= htmlspecialchars($awayAbbr) ?>"
                                       data-name="<?= htmlspecialchars($awayTeam['name']) ?>"
                                       data-logo="<?= htmlspecialchars($awayTeam['logo']) ?>"
                                       data-color="<?= htmlspecialchars($awayColor) ?>"
                                       <?= $awayPicked ? 'checked' : '' ?>
                                       <?= $cardDisabled ? 'disabled' : '' ?>>

                                <!-- Highlight Selection Indicator (Absolute: No layout shift) -->
                                <div class="pick-check absolute top-2.5 right-2.5 w-6 h-6 rounded-full bg-amber-500 text-slate-950 flex items-center justify-center font-black text-xs shadow-md transition-all duration-150 <?= $awayPicked ? 'scale-100 opacity-100' : 'scale-0 opacity-0 pointer-events-none' ?>" title="Selected Pick">
                                    ✓
                                </div>

                                <!-- Official ESPN Logo -->
                                <div class="my-2 h-16 flex items-center justify-center">
                                    <img src="<?= htmlspecialchars($awayTeam['logo']) ?>" 
                                         alt="<?= htmlspecialchars($awayTeam['name']) ?>" 
                                         class="w-14 h-14 object-contain filter drop-shadow-md transition-transform duration-200 group-hover:scale-110"
                                         loading="lazy">
                                </div>

                                <!-- Single-line Team Name -->
                                <div class="text-center w-full mt-2">
                                    <span class="text-sm sm:text-base font-bold text-white block truncate">
                                        <?= htmlspecialchars($awayTeam['name']) ?>
                                    </span>
                                </div>
                            </label>

                            <!-- Home Team Card -->
                            <label class="team-card relative flex flex-col items-center justify-center p-4 pt-5 rounded-xl border-2 transition-all select-none group overflow-hidden <?= $homePicked ? 'is-picked' : '' ?>
                                <?= $cardDisabled ? 'pointer-events-none' : 'cursor-pointer hover:scale-[1.01]' ?>"
                                style="<?= $homeCardStyle ?>">
                                
                                <!-- Team Color Top Accent Stripe -->
                                <div class="absolute top-0 left-0 right-0 h-1.5" style="background-color: <?= htmlspecialchars($homeColor) ?>;"></div>

                                <input type="radio" 
                                       name="picks[<?= $game['id'] ?>]" 
                                       value="<?= htmlspecialchars($homeAbbr) ?>" 
                                       class="sr-only pick-radio"
                                       data-abbr="<?= htmlspecialchars($homeAbbr) ?>"
                                       data-name="<?= htmlspecialchars($homeTeam['name']) ?>"
                                       data-logo="<?= htmlspecialchars($homeTeam['logo']) ?>"
                                       data-color="<?= htmlspecialchars($homeColor) ?>"
                                       <?= $homePicked ? 'checked' : '' ?>
                                       <?= $cardDisabled ? 'disabled' : '' ?>>

                                <!-- Highlight Selection Indicator (Absolute: No layout shift) -->
                                <div class="pick-check absolute top-2.5 right-2.5 w-6 h-6 rounded-full bg-amber-500 text-slate-950 flex items-center justify-center font-black text-xs shadow-md transition-all duration-150 <?= $homePicked ? 'scale-100 opacity-100' : 'scale-0 opacity-0 pointer-events-none' ?>" title="Selected Pick">
                                    ✓
                                </div>

                                <!-- Official ESPN Logo -->
                                <div class="my-2 h-16 flex items-center justify-center">
                                    <img src="<?= htmlspecialchars($homeTeam['logo']) ?>" 
                                         alt="<?= htmlspecialchars($homeTeam['name']) ?>" 
                                         class="w-14 h-14 object-contain filter drop-shadow-md transition-transform duration-200 group-hover:scale-110"
                                         loading="lazy">
                                </div>

                                <!-- Single-line Team Name -->
                                <div class="text-center w-full mt-2">
                                    <span class="text-sm sm:text-base font-bold text-white block truncate">
                                        <?= htmlspecialchars($homeTeam['name']) ?>
                                    </span>
                                </div>
                            </label>

                        </div>

                        <!-- Cool Broadcast-Style VS Circle Overlay (centered between two cards) -->
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-10 pointer-events-none">
                            <div class="relative w-12 h-12 rounded-full shadow-[0_4px_20px_rgba(0,0,0,0.8)] overflow-hidden ring-4 ring-slate-900 border border-slate-700/60 flex items-center justify-center">
                                <!-- Away color left half -->
                                <div class="absolute left-0 top-0 w-1/2 h-full" style="background-color: <?= htmlspecialchars($awayColor) ?>;"></div>
                                <!-- Home color right half -->
                                <div class="absolute right-0 top-0 w-1/2 h-full" style="background-color: <?= htmlspecialchars($homeColor) ?>;"></div>
                                <!-- Subtle depth overlay -->
                                <div class="absolute inset-0 bg-gradient-to-b from-black/25 via-transparent to-black/40"></div>
                                <!-- Inner VS circular badge -->
                                <div class="relative w-7 h-7 rounded-full bg-slate-950/90 border border-slate-700 shadow-inner flex items-center justify-center">
                                    <span class="text-[10px] font-black font-mono text-amber-400 tracking-wider">VS</span>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>
            <?php endforeach; ?>
        </div>

        <!-- Tiebreaker Input Section -->
        <div id="mnfTiebreakerContainer" class="p-6 rounded-2xl border transition-all duration-300 border-amber-500/40 bg-gradient-to-br from-slate-900 via-slate-900 to-amber-950/20 shadow-xl">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-5">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs font-mono uppercase tracking-wider text-amber-400 font-bold">Official Tiebreaker Question</span>
                        <span class="text-xs px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 font-bold">Game of the Week</span>
                    </div>
                    <?php if (isset($tbAwayData) && isset($tbHomeData)): ?>
                        <div class="flex items-center gap-3 my-1.5 flex-wrap">
                            <div class="flex items-center gap-2">
                                <img src="<?= htmlspecialchars($tbAwayData['logo']) ?>" alt="<?= htmlspecialchars($tbAwayData['name']) ?>" class="w-7 h-7 object-contain">
                                <span class="font-bold text-white text-base sm:text-lg"><?= htmlspecialchars($tbAwayData['name']) ?></span>
                            </div>
                            <span class="text-slate-400 font-semibold text-sm">@</span>
                            <div class="flex items-center gap-2">
                                <img src="<?= htmlspecialchars($tbHomeData['logo']) ?>" alt="<?= htmlspecialchars($tbHomeData['name']) ?>" class="w-7 h-7 object-contain">
                                <span class="font-bold text-white text-base sm:text-lg"><?= htmlspecialchars($tbHomeData['name']) ?></span>
                            </div>
                        </div>
                        <p class="text-xs text-slate-400 mt-1 max-w-xl leading-relaxed">
                            Predict the combined total final score for <strong><?= htmlspecialchars($tbAwayData['name']) ?> @ <?= htmlspecialchars($tbHomeData['name']) ?></strong> (our randomly selected tiebreaker game of the week). If two or more players tie in weekly picks, lowest absolute point delta wins the prize pot!
                        </p>
                    <?php else: ?>
                        <h4 class="text-base sm:text-lg font-bold text-white">Weekly Tiebreaker Combined Total Score</h4>
                        <p class="text-xs text-slate-400 mt-1 max-w-xl leading-relaxed">
                            Predict the combined total final score for our randomly selected game of the week. Lowest absolute point delta breaks ties!
                        </p>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <div class="relative">
                        <input type="number" 
                               id="mnfTotalPointsInput"
                               name="mnf_total_points" 
                               min="1" 
                               max="150" 
                               placeholder="e.g. 48"
                               value="<?= htmlspecialchars((string) ($entry['mnf_total_points_prediction'] ?? '')) ?>"
                               <?= $isUserLocked ? 'disabled' : '' ?>
                               class="w-32 px-4 py-3 rounded-xl bg-slate-950 border border-slate-700 text-white font-mono text-center text-lg font-black focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/40 shadow-inner transition">
                    </div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider">Total Points</span>
                </div>
            </div>
        </div>

        <!-- Submission & Status Card (In-flow, Non-Floating) -->
        <div class="p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-xl flex flex-col sm:flex-row items-center justify-between gap-5">
            <div class="flex items-center gap-3 text-xs text-slate-300">
                <span class="text-xl"><?= $isUserLocked ? '🔒' : ($hasConfirmedPicks ? '✓' : '💾') ?></span>
                <?php if ($isUserLocked): ?>
                    <div>
                        <span class="text-emerald-400 font-semibold text-sm">Picks for Week <?= $week ?> are locked in.</span>
                        <span class="text-slate-400 block text-xs">Selection cutoff passed at <?= htmlspecialchars($cutoffFormatted) ?> (1 hr into first game).</span>
                    </div>
                <?php elseif ($hasConfirmedPicks): ?>
                    <div>
                        <span class="font-bold text-emerald-400 block text-sm mb-0.5">Week <?= $week ?> Ballot Confirmed &amp; Auto-Saved!</span>
                        <span class="text-slate-400">All picks auto-save behind the scenes. You can adjust your picks or tiebreaker score anytime before <?= htmlspecialchars($cutoffFormatted) ?>.</span>
                    </div>
                <?php else: ?>
                    <div>
                        <span class="font-bold text-white block text-sm mb-0.5">Picks Auto-Save As You Go</span>
                        <span class="text-slate-400">Selections save behind the scenes. Click Review &amp; Submit whenever you're ready to confirm your ballot before <?= htmlspecialchars($cutoffFormatted) ?>.</span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($isUserLocked): ?>
                <div class="flex items-center gap-3 shrink-0">
                    <a href="/pickem/standings?week=<?= $week ?>&season=<?= $season ?>" 
                       class="w-full sm:w-auto px-6 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white font-bold text-xs uppercase tracking-wider transition shadow flex items-center justify-center gap-2">
                        <span>📊</span>
                        <span>View Live Standings</span>
                    </a>
                </div>
            <?php else: ?>
                <button type="button" 
                        id="btnReviewPicks"
                        class="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-sm uppercase tracking-wider transition shadow-lg hover:shadow-emerald-500/30 flex items-center justify-center gap-2 shrink-0">
                    <span><?= $hasConfirmedPicks ? '✏️' : '🔍' ?></span>
                    <span><?= $hasConfirmedPicks ? "Review &amp; Re-confirm Week {$week} Picks" : "Review &amp; Submit Week {$week} Picks" ?></span>
                </button>
            <?php endif; ?>
        </div>

    </form>
    </div>

</div>

<!-- Review & Confirmation Modal -->
<div id="reviewModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-700/80 rounded-2xl max-w-2xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        
        <!-- Modal Header -->
        <div class="p-5 border-b border-slate-800 bg-slate-950/80 flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20 uppercase">
                        Review Selections
                    </span>
                    <span class="text-xs text-slate-400">Week <?= $week ?></span>
                </div>
                <h3 class="text-xl font-black text-white">Review Your Picks</h3>
            </div>
            <button type="button" id="btnModalCloseX" class="text-slate-400 hover:text-white text-2xl font-bold leading-none p-2">&times;</button>
        </div>

        <!-- Info Callout -->
        <div class="p-4 bg-emerald-500/10 border-b border-emerald-500/20 text-xs text-emerald-300 flex items-start gap-2.5">
            <span class="text-lg">✓</span>
            <div>
                <strong class="font-bold">Ballot Confirmation:</strong>
                <span>Confirming your picks records your official submission. You can continue to modify any pick or tiebreaker score anytime until the selection cutoff (<strong><?= htmlspecialchars($cutoffFormatted) ?></strong> — 1 hour into the opening game).</span>
            </div>
        </div>

        <!-- Picks Summary List (Scrollable) -->
        <div class="p-5 overflow-y-auto space-y-3 divide-y divide-slate-800/60" id="reviewPicksList">
            <!-- Populated via JS -->
        </div>

        <!-- Tiebreaker Summary -->
        <div class="p-4 bg-slate-950/60 border-t border-slate-800 flex items-center justify-between text-xs">
            <div>
                <span class="text-slate-400 font-semibold">Weekly Tiebreaker (Game of the Week):</span>
                <span class="text-slate-300 block text-[11px]"><?= htmlspecialchars($tbMatchupLabel) ?></span>
            </div>
            <span id="reviewMnfPoints" class="font-mono text-base font-black text-amber-400">--</span>
        </div>

        <!-- Modal Footer Actions -->
        <div class="p-4 border-t border-slate-800 bg-slate-950/90 flex flex-col-reverse sm:flex-row items-center justify-between gap-3">
            <button type="button" id="btnCancelModal" 
                    class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs uppercase tracking-wider transition">
                ✏️ Keep Editing
            </button>
            <button type="button" id="btnConfirmLockIn" 
                    class="w-full sm:w-auto px-7 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs uppercase tracking-wider shadow-lg hover:shadow-emerald-500/30 transition flex items-center justify-center gap-2">
                <span>✓</span>
                <span>Confirm &amp; Submit My Picks</span>
            </button>
        </div>

    </div>
</div>

<!-- Pick'em How It Works Modal -->
<div id="howItWorksModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-700/80 rounded-2xl max-w-lg w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        
        <!-- Header -->
        <div class="p-5 border-b border-slate-800 bg-slate-950/80 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="p-2 rounded-xl bg-amber-500/20 text-amber-400 text-xl border border-amber-500/30">🎯</span>
                <div>
                    <h3 class="text-lg font-black text-white">How Pick'em Works</h3>
                    <span class="text-xs text-slate-400">Weekly Straight-Up Pool Rules</span>
                </div>
            </div>
            <button type="button" id="btnCloseHowItWorksX" class="text-slate-400 hover:text-white text-2xl font-bold leading-none p-2">&times;</button>
        </div>

        <!-- Content (Scrollable) -->
        <div class="p-5 overflow-y-auto space-y-3.5 text-xs">
            
            <!-- Rule 1: Weekly Straight-Up Picks -->
            <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="p-2 rounded-lg bg-blue-500/15 text-blue-400 font-black text-sm shrink-0">1</span>
                <div>
                    <strong class="text-white text-sm block mb-0.5">Pick Straight-Up Winners</strong>
                    <p class="text-slate-300 leading-relaxed">
                        For every game in the week's slate, pick which team will win straight-up (no point spreads). Each correct selection earns <strong>1 point</strong>.
                    </p>
                </div>
            </div>

            <!-- Rule 2: $10 Entry Fee -->
            <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="p-2 rounded-lg bg-emerald-500/15 text-emerald-400 font-black text-sm shrink-0">2</span>
                <div>
                    <strong class="text-white text-sm block mb-0.5">Weekly $10.00 Entry Stake</strong>
                    <p class="text-slate-300 leading-relaxed">
                        Entry is $10 per week. Send your stake directly to Commissioner Wally via Venmo (<span class="text-sky-400 font-bold font-mono">@WallyAtkins</span>), PayPal, or Cash App (<span class="text-emerald-400 font-bold font-mono">$WallyAtkins</span>). 100% of collected stakes fund that week's cash pot!
                    </p>
                </div>
            </div>

            <!-- Rule 3: Tiebreaker Question -->
            <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="p-2 rounded-lg bg-amber-500/15 text-amber-400 font-black text-sm shrink-0">3</span>
                <div>
                    <strong class="text-white text-sm block mb-0.5">Game of the Week Tiebreaker</strong>
                    <p class="text-slate-300 leading-relaxed">
                        Each week features a designated <strong>Game of the Week Tiebreaker</strong>. Predict the combined total final score (e.g. 48). If players tie with the same number of wins, the player with the lowest absolute point difference wins the prize pot!
                    </p>
                </div>
            </div>

            <!-- Rule 4: Auto-Save & Review -->
            <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="p-2 rounded-lg bg-purple-500/15 text-purple-400 font-black text-sm shrink-0">4</span>
                <div>
                    <strong class="text-white text-sm block mb-0.5">Auto-Saved As You Go &bull; Confirmed Ballot</strong>
                    <p class="text-slate-300 leading-relaxed">
                        Picks and tiebreaker scores are saved automatically behind the scenes! You can change your picks anytime until kickoff of the week's first game. Click <strong>Review &amp; Submit</strong> to confirm your choices whenever you're ready.
                    </p>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <div class="p-4 border-t border-slate-800 bg-slate-950/90 flex justify-end">
            <button type="button" id="btnCloseHowItWorks" class="px-5 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs uppercase tracking-wider transition shadow">
                Got It, Let's Play!
            </button>
        </div>

    </div>
</div>

<!-- Silent Behind-the-Scenes Auto-Save Toast Notification -->
<div id="autoSaveToast" class="fixed bottom-6 right-6 z-50 px-4 py-2.5 rounded-xl bg-slate-900/95 text-emerald-400 border border-emerald-500/40 text-xs font-mono font-bold shadow-2xl flex items-center gap-2 opacity-0 pointer-events-none transition-all duration-300 transform translate-y-2">
    <span class="text-sm">✓</span>
    <span id="autoSaveToastText">Saved behind the scenes</span>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('pickemForm');
    const reviewBtn = document.getElementById('btnReviewPicks');
    const reviewModal = document.getElementById('reviewModal');
    const btnCancelModal = document.getElementById('btnCancelModal');
    const btnModalCloseX = document.getElementById('btnModalCloseX');
    const btnConfirmLockIn = document.getElementById('btnConfirmLockIn');
    const validationAlert = document.getElementById('validationAlert');
    const validationAlertMessage = document.getElementById('validationAlertMessage');
    const mnfInput = document.getElementById('mnfTotalPointsInput');
    const mnfContainer = document.getElementById('mnfTiebreakerContainer');
    const reviewPicksList = document.getElementById('reviewPicksList');
    const reviewMnfPoints = document.getElementById('reviewMnfPoints');

    // Auto-Save Toast Notification
    const autoSaveToast = document.getElementById('autoSaveToast');
    const autoSaveToastText = document.getElementById('autoSaveToastText');
    let toastTimeout = null;

    function showAutoSaveBadge(text) {
        if (!autoSaveToast || !autoSaveToastText) return;
        autoSaveToastText.textContent = text || 'Saved behind the scenes';
        autoSaveToast.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-2');
        autoSaveToast.classList.add('opacity-100', 'translate-y-0');
        clearTimeout(toastTimeout);
        toastTimeout = setTimeout(() => {
            autoSaveToast.classList.remove('opacity-100', 'translate-y-0');
            autoSaveToast.classList.add('opacity-0', 'pointer-events-none', 'translate-y-2');
        }, 1800);
    }

    // How It Works Modal elements
    const howItWorksModal = document.getElementById('howItWorksModal');
    const btnOpenHowItWorks = document.getElementById('btnOpenHowItWorks');
    const btnCloseHowItWorks = document.getElementById('btnCloseHowItWorks');
    const btnCloseHowItWorksX = document.getElementById('btnCloseHowItWorksX');

    function openHowItWorks() {
        if (howItWorksModal) {
            howItWorksModal.classList.remove('hidden');
            howItWorksModal.classList.add('flex');
        }
    }

    function closeHowItWorks() {
        if (howItWorksModal) {
            howItWorksModal.classList.add('hidden');
            howItWorksModal.classList.remove('flex');
        }
    }

    if (btnOpenHowItWorks) btnOpenHowItWorks.addEventListener('click', openHowItWorks);
    if (btnCloseHowItWorks) btnCloseHowItWorks.addEventListener('click', closeHowItWorks);
    if (btnCloseHowItWorksX) btnCloseHowItWorksX.addEventListener('click', closeHowItWorks);
    if (howItWorksModal) {
        howItWorksModal.addEventListener('click', function (e) {
            if (e.target === howItWorksModal) closeHowItWorks();
        });
    }

    // 1. Dynamic selection & radio button handling + silent autosave
    const matchups = document.querySelectorAll('.matchup-card');
    matchups.forEach(card => {
        const labels = card.querySelectorAll('.team-card');
        labels.forEach(label => {
            label.addEventListener('click', function () {
                const radio = this.querySelector('.pick-radio');
                if (!radio || radio.disabled) return;

                // Reset siblings
                labels.forEach(l => {
                    l.classList.remove('is-picked');
                    l.style.borderColor = 'var(--card-surface-border)';
                    l.style.backgroundColor = 'var(--card-surface)';
                    l.style.boxShadow = 'none';
                    l.classList.add('opacity-50');
                    l.classList.remove('opacity-100');
                    const check = l.querySelector('.pick-check');
                    if (check) {
                        check.classList.remove('scale-100', 'opacity-100');
                        check.classList.add('scale-0', 'opacity-0');
                    }
                });

                // Highlight selected (team color gradient background, crisp border & glow, zero layout shift)
                const color = radio.getAttribute('data-color') || '#f59e0b';
                this.classList.add('is-picked');
                this.style.borderColor = color;
                this.style.background = `linear-gradient(135deg, ${color}28 0%, #0f172a 100%)`;
                this.style.boxShadow = `0 0 22px ${color}44`;
                this.classList.remove('opacity-50');
                this.classList.add('opacity-100');

                const check = this.querySelector('.pick-check');
                if (check) {
                    check.classList.remove('scale-0', 'opacity-0');
                    check.classList.add('scale-100', 'opacity-100');
                }

                radio.checked = true;

                // Remove red error highlight if present
                card.classList.remove('ring-4', 'ring-rose-500', 'animate-pulse');
                if (validationAlert) validationAlert.classList.add('hidden');

                // Silent auto-save behind the scenes
                const gameId = card.getAttribute('data-game-id');
                const teamVal = radio.value;
                if (gameId && teamVal) {
                    fetch('/pickem/autosave', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            season_year: <?= (int) $season ?>,
                            week_number: <?= (int) $week ?>,
                            game_id: parseInt(gameId, 10),
                            selected_team: teamVal
                        })
                    }).then(r => r.json()).then(data => {
                        if (data && data.success) {
                            showAutoSaveBadge(teamVal + ' Pick Saved ✓');
                        }
                    }).catch(e => console.warn('Autosave notice:', e));
                }
            });
        });
    });

    // 2. Debounced auto-save for Tiebreaker input
    let mnfSaveTimer = null;
    function autoSaveTiebreaker() {
        if (!mnfInput) return;
        const val = mnfInput.value.trim();
        fetch('/pickem/autosave', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                season_year: <?= (int) $season ?>,
                week_number: <?= (int) $week ?>,
                mnf_total_points: val !== '' ? parseInt(val, 10) : null
            })
        }).then(r => r.json()).then(data => {
            if (data && data.success) {
                showAutoSaveBadge('Tiebreaker Saved (' + (val !== '' ? val : '0') + ' pts) ✓');
            }
        }).catch(e => console.warn('Tiebreaker autosave notice:', e));
    }

    if (mnfInput) {
        mnfInput.addEventListener('input', function () {
            if (parseInt(this.value, 10) > 0) {
                mnfContainer.classList.remove('ring-4', 'ring-rose-500', 'animate-pulse');
                if (validationAlert) validationAlert.classList.add('hidden');
            }
            clearTimeout(mnfSaveTimer);
            mnfSaveTimer = setTimeout(autoSaveTiebreaker, 600);
        });
        mnfInput.addEventListener('change', autoSaveTiebreaker);
    }

    // 3. Client-side Validation & Review modal trigger
    if (reviewBtn) {
        reviewBtn.addEventListener('click', function (e) {
            e.preventDefault();

            // Clear previous alerts
            validationAlert.classList.add('hidden');
            validationAlertMessage.innerHTML = '';
            document.querySelectorAll('.ring-rose-500').forEach(el => el.classList.remove('ring-4', 'ring-rose-500', 'animate-pulse'));

            const unlockedMatchups = document.querySelectorAll('.matchup-card[data-unlocked="true"]');
            let unpickedCards = [];

            unlockedMatchups.forEach(card => {
                const checked = card.querySelector('.pick-radio:checked');
                if (!checked) {
                    unpickedCards.push(card);
                }
            });

            const mnfVal = mnfInput ? parseInt(mnfInput.value.trim(), 10) : 0;
            const mnfMissing = !mnfInput || isNaN(mnfVal) || mnfVal <= 0;

            if (unpickedCards.length > 0 || mnfMissing) {
                // Build human-friendly message
                let errorHtml = '';
                if (unpickedCards.length > 0) {
                    errorHtml += `<div><strong>Unpicked Games:</strong> You have <strong>${unpickedCards.length}</strong> unpicked matchup(s) remaining. Please pick a winner for every game.</div>`;
                    unpickedCards.forEach(c => c.classList.add('ring-4', 'ring-rose-500', 'animate-pulse'));
                }
                if (mnfMissing) {
                    const tbLabel = <?= json_encode($tbMatchupLabel) ?>;
                    errorHtml += `<div><strong>Missing Tiebreaker:</strong> Please enter your predicted combined total points for <strong>${tbLabel}</strong>.</div>`;
                    if (mnfContainer) mnfContainer.classList.add('ring-4', 'ring-rose-500', 'animate-pulse');
                }

                validationAlertMessage.innerHTML = errorHtml;
                validationAlert.classList.remove('hidden');

                // Smooth scroll to the first missing element
                if (unpickedCards.length > 0) {
                    unpickedCards[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else if (mnfMissing && mnfContainer) {
                    mnfContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    mnfInput.focus();
                }

                return;
            }

            // If valid, populate Review Modal
            reviewPicksList.innerHTML = '';
            let pickIndex = 1;

            matchups.forEach(card => {
                const checkedRadio = card.querySelector('.pick-radio:checked');
                if (!checkedRadio) return;

                const teamAbbr = checkedRadio.getAttribute('data-abbr');
                const teamName = checkedRadio.getAttribute('data-name');
                const teamLogo = checkedRadio.getAttribute('data-logo');
                const awayName = card.getAttribute('data-away-name');
                const homeName = card.getAttribute('data-home-name');
                const awayAbbr = card.getAttribute('data-away-abbr');
                const homeAbbr = card.getAttribute('data-home-abbr');

                const vsOpponent = (teamAbbr === awayAbbr) ? `vs ${homeName} (${homeAbbr})` : `@ ${awayName} (${awayAbbr})`;

                const row = document.createElement('div');
                row.className = 'pt-2.5 flex items-center justify-between gap-4 text-xs';
                row.innerHTML = `
                    <div class="flex items-center gap-3">
                        <span class="font-mono text-slate-500 text-[11px] w-4">${pickIndex++}.</span>
                        <img src="${teamLogo}" alt="${teamName}" class="w-8 h-8 object-contain">
                        <div>
                            <span class="font-bold text-white text-sm">${teamName}</span>
                            <span class="text-slate-400 block text-[11px]">${vsOpponent}</span>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 rounded font-mono font-bold text-xs bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                        ${teamAbbr}
                    </span>
                `;
                reviewPicksList.appendChild(row);
            });

            if (reviewMnfPoints) {
                reviewMnfPoints.textContent = mnfVal + ' Points';
            }

            // Show Modal
            reviewModal.classList.remove('hidden');
            reviewModal.classList.add('flex');
        });
    }

    // Modal Close
    function closeModal() {
        reviewModal.classList.add('hidden');
        reviewModal.classList.remove('flex');
    }

    if (btnCancelModal) btnCancelModal.addEventListener('click', closeModal);
    if (btnModalCloseX) btnModalCloseX.addEventListener('click', closeModal);

    // Confirm & Submit
    if (btnConfirmLockIn) {
        btnConfirmLockIn.addEventListener('click', function () {
            btnConfirmLockIn.disabled = true;
            btnConfirmLockIn.innerHTML = '<span>⏳</span> Submitting Ballot...';
            form.submit();
        });
    }

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal();
            closeHowItWorks();
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
