<?php
ob_start();
$user = $user ?? $_SESSION['user'] ?? null;
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">

    <!-- Dynasty Vault Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800 pb-6 mb-8">
        <div>
            <div class="flex items-center gap-2.5 text-xs font-mono font-semibold uppercase tracking-wider text-amber-400 mb-1">
                <span>🏛️ Atkins Dynasty Vault</span>
                <span class="text-slate-600">•</span>
                <span>Pool History &amp; Records</span>
            </div>
            <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-white flex items-center gap-3">
                <span>Pick'em &amp; Survivor Archives</span>
            </h1>
            <p class="text-slate-400 text-sm mt-1 max-w-2xl">
                Historical records, weekly champions, cash pot payouts, and survivor elimination timelines.
            </p>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex items-center gap-2 bg-slate-900/80 p-1.5 rounded-xl border border-slate-800 flex-wrap">
            <a href="/fantasy/vault" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">
                🏆 Hall of Fame
            </a>
            <a href="/fantasy/rivalry" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">
                ⚔️ Rivalry Matrix
            </a>
            <a href="/fantasy/seasons" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">
                📅 Season Explorer
            </a>
            <a href="/fantasy/vault?tab=pools" class="px-3.5 py-1.5 text-xs font-bold rounded-lg bg-amber-500 text-black shadow-sm transition">
                📜 Pool Archives
            </a>
        </div>
    </div>

    <!-- Overview Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="p-5 rounded-2xl bg-slate-900/70 border border-slate-800 shadow-xl">
            <span class="text-xs font-semibold text-slate-400 block mb-1">Completed Pick'em Weeks</span>
            <div class="text-3xl font-black text-amber-400 font-mono"><?= count($completedWeeks) ?></div>
            <span class="text-[11px] text-slate-500 mt-1 block">Official final results on file</span>
        </div>

        <div class="p-5 rounded-2xl bg-slate-900/70 border border-slate-800 shadow-xl">
            <span class="text-xs font-semibold text-slate-400 block mb-1">Survivor Cash Prize Pool</span>
            <div class="text-3xl font-black text-emerald-400 font-mono">$<?= number_format($survivorPot['total_pot'] ?? 0, 2) ?></div>
            <span class="text-[11px] text-slate-400 mt-1 block"><?= $survivorPot['alive_cash_count'] ?? 0 ?> / <?= $survivorPot['cash_entries_count'] ?? 0 ?> cash contenders still standing</span>
        </div>

        <div class="p-5 rounded-2xl bg-slate-900/70 border border-slate-800 shadow-xl">
            <span class="text-xs font-semibold text-slate-400 block mb-1">Total Pool Participants</span>
            <div class="text-3xl font-black text-sky-400 font-mono"><?= count($survivorStandings) ?></div>
            <span class="text-[11px] text-slate-400 mt-1 block">Active across Pick'em &amp; Survivor</span>
        </div>
    </div>

    <!-- Completed Weeks Champion Honor Roll -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 mb-10 shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 border-b border-slate-800/80 pb-4">
            <div>
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <span>👑</span> Pick'em Honor Roll (Weekly Champions)
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Chronological record of weekly winners and payouts</p>
            </div>
        </div>

        <?php if (empty($completedWeeks)): ?>
            <div class="py-12 text-center text-slate-400 space-y-3">
                <span class="text-4xl block">🏈</span>
                <p class="text-base font-semibold text-white">Season in Progress</p>
                <p class="text-xs max-w-md mx-auto text-slate-500">
                    Games are currently underway! Weekly champions and official pot payouts will be permanently memorialized here as soon as each week completes.
                </p>
                <a href="/pickem" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition shadow-md mt-2">
                    View Active Week Picks &rarr;
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($completedWeeks as $cw): ?>
                    <div class="p-4 rounded-xl bg-slate-950/70 border border-slate-800 hover:border-amber-500/40 transition flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                    Week <?= $cw['week'] ?>
                                </span>
                                <?php if (!empty($cw['pot']['total_pot']) && $cw['pot']['total_pot'] > 0): ?>
                                    <span class="text-xs font-mono font-bold text-emerald-400">
                                        $<?= number_format($cw['pot']['payout_per_winner'], 2) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="text-base font-black text-white mb-1">
                                <?= !empty($cw['winners']) ? htmlspecialchars(implode(', ', array_column($cw['winners'], 'username'))) : 'No Winner Recorded' ?>
                            </div>
                            <p class="text-xs text-slate-400">
                                Score: <span class="font-bold text-slate-200"><?= $cw['winners'][0]['correct_picks'] ?? 0 ?></span> correct picks
                            </p>
                        </div>
                        <div class="pt-3 mt-3 border-t border-slate-800/80 flex items-center justify-between text-[11px]">
                            <span class="text-slate-500"><?= $cw['games_count'] ?> games completed</span>
                            <a href="/fantasy/vault?tab=pools&week=<?= $cw['week'] ?>#detail" class="text-amber-400 hover:underline font-semibold">
                                View Leaderboard &rarr;
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Survivor Elimination Tracker -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 border-b border-slate-800/80 pb-4">
            <div>
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <span>🛡️</span> Survivor Hall of Survival
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Track remaining survivors and elimination milestones across the season</p>
            </div>
            <a href="/survivor/standings" class="px-3.5 py-1.5 text-xs font-bold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white transition shadow-sm self-start sm:self-auto">
                Live Survivor Board &rarr;
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Still Alive -->
            <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800">
                <h3 class="text-sm font-bold text-emerald-300 flex items-center gap-2 mb-3">
                    <span>🟢</span> Contenders Still Standing (<?= count(array_filter($survivorStandings, fn($s) => $s['is_alive'])) ?>)
                </h3>
                <div class="space-y-2">
                    <?php 
                    $aliveList = array_filter($survivorStandings, fn($s) => $s['is_alive']);
                    if (empty($aliveList)): ?>
                        <p class="text-xs text-slate-500 italic">No survivors remaining.</p>
                    <?php else: ?>
                        <?php foreach ($aliveList as $s): ?>
                            <div class="flex items-center justify-between p-2 rounded-lg bg-slate-900/70 border border-slate-800 text-xs">
                                <span class="font-semibold text-white"><?= htmlspecialchars($s['username']) ?></span>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-[11px] <?= $s['is_paid'] ? 'text-emerald-400' : 'text-amber-400' ?>">
                                        <?= $s['is_paid'] ? '💰 Cash Eligible' : '🎮 Free Play' ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Knocked Out -->
            <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800">
                <h3 class="text-sm font-bold text-rose-400 flex items-center gap-2 mb-3">
                    <span>☠️</span> Fallen Contenders (<?= count(array_filter($survivorStandings, fn($s) => $s['is_eliminated'])) ?>)
                </h3>
                <div class="space-y-2">
                    <?php 
                    $outList = array_filter($survivorStandings, fn($s) => $s['is_eliminated']);
                    if (empty($outList)): ?>
                        <p class="text-xs text-slate-500 italic">No eliminations recorded yet.</p>
                    <?php else: ?>
                        <?php foreach ($outList as $s): ?>
                            <div class="flex items-center justify-between p-2 rounded-lg bg-slate-900/70 border border-slate-800 text-xs">
                                <span class="font-semibold text-slate-300"><?= htmlspecialchars($s['username']) ?></span>
                                <span class="font-mono text-rose-400 text-[11px]">Eliminated Week <?= $s['elimination_week'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
