<?php
ob_start();
$sortBy = $_GET['sort'] ?? 'titles';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">

    <!-- Dynasty Vault Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800 pb-6 mb-8">
        <div>
            <div class="flex items-center gap-2.5 text-xs font-mono font-semibold uppercase tracking-wider text-amber-400 mb-1">
                <span>🏛️ Atkins Dynasty Vault</span>
                <span class="text-slate-600">•</span>
                <span>CBS Sports Archive (2003–2025)</span>
            </div>
            <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-white flex items-center gap-3">
                <span>20-Year League Hall of Fame</span>
            </h1>
            <p class="text-slate-400 text-sm mt-1 max-w-2xl">
                23 seasons of glory, bad beats, and family rivalries across 1,818 head-to-head battles in the <span class="text-slate-200 font-semibold">Remember the Titans</span> league.
            </p>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex items-center gap-2 bg-slate-900/80 p-1.5 rounded-xl border border-slate-800 flex-wrap">
            <a href="/fantasy/vault" class="px-3.5 py-1.5 text-xs font-bold rounded-lg bg-amber-500 text-black shadow-sm transition">
                🏆 Hall of Fame
            </a>
            <a href="/fantasy/rivalry" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">
                ⚔️ Rivalry Matrix
            </a>
            <a href="/fantasy/seasons" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">
                📅 Season Explorer
            </a>
            <a href="/fantasy/vault?tab=pools" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">
                📜 Pool Archives
            </a>
        </div>
    </div>

    <!-- Ring Leaders / Trophy Room Grid -->
    <div class="mb-10">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <span>💍</span> The Trophy Room (Championship Rings)
            </h2>
            <span class="text-xs text-slate-400">22 Titles Awarded</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($hallOfFame['ring_leaders'] as $f): ?>
                <div class="bg-slate-900/70 border <?= $f['titles_count'] >= 3 ? 'border-amber-500/50 bg-gradient-to-b from-amber-950/20 to-slate-900/80' : 'border-slate-800' ?> rounded-2xl p-4 flex flex-col justify-between shadow-lg relative overflow-hidden group hover:border-amber-400 transition">
                    <?php if ($f['titles_count'] >= 4): ?>
                        <div class="absolute -right-8 -top-8 w-24 h-24 bg-amber-500/10 rounded-full blur-xl group-hover:bg-amber-500/20 transition"></div>
                    <?php endif; ?>
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <span class="text-sm font-bold text-white tracking-tight group-hover:text-amber-300 transition">
                                <?= htmlspecialchars($f['current_name']) ?>
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-black <?= $f['titles_count'] >= 3 ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40' : 'bg-slate-800 text-slate-300' ?>">
                                <?= str_repeat('💍', $f['titles_count']) ?> <?= $f['titles_count'] ?> <?= $f['titles_count'] === 1 ? 'Title' : 'Titles' ?>
                            </span>
                        </div>
                        <div class="text-xs text-slate-400 mb-3 truncate" title="<?= htmlspecialchars($f['current_managers']) ?>">
                            👤 <?= htmlspecialchars($f['current_managers']) ?>
                        </div>
                    </div>
                    <div class="pt-3 border-t border-slate-800/80 flex items-center justify-between text-[11px] font-mono text-slate-400">
                        <span>Career Record:</span>
                        <span class="font-bold text-slate-200"><?= $f['wins'] ?>-<?= $f['losses'] ?> (<?= number_format($f['win_pct'], 3) ?>)</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- All-Time Franchise Leaderboard -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 mb-10 shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5 border-b border-slate-800/80 pb-4">
            <div>
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <span>📊</span> 20-Year All-Time Franchise Leaderboard
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Aggregate performance from 2003 through 2024</p>
            </div>

            <!-- Sort Pills -->
            <div class="flex items-center gap-1.5 text-xs font-medium">
                <span class="text-slate-400 text-[11px] mr-1">Sort by:</span>
                <a href="/fantasy/vault?sort=titles" class="px-2.5 py-1 rounded-lg <?= $sortBy === 'titles' ? 'bg-amber-500 text-black font-bold' : 'bg-slate-800 text-slate-300 hover:text-white' ?> transition">
                    Titles
                </a>
                <a href="/fantasy/vault?sort=wins" class="px-2.5 py-1 rounded-lg <?= $sortBy === 'wins' ? 'bg-amber-500 text-black font-bold' : 'bg-slate-800 text-slate-300 hover:text-white' ?> transition">
                    Wins
                </a>
                <a href="/fantasy/vault?sort=pct" class="px-2.5 py-1 rounded-lg <?= $sortBy === 'pct' ? 'bg-amber-500 text-black font-bold' : 'bg-slate-800 text-slate-300 hover:text-white' ?> transition">
                    Win %
                </a>
                <a href="/fantasy/vault?sort=points" class="px-2.5 py-1 rounded-lg <?= $sortBy === 'points' ? 'bg-amber-500 text-black font-bold' : 'bg-slate-800 text-slate-300 hover:text-white' ?> transition">
                    Points
                </a>
                <a href="/fantasy/vault?sort=finish" class="px-2.5 py-1 rounded-lg <?= $sortBy === 'finish' ? 'bg-amber-500 text-black font-bold' : 'bg-slate-800 text-slate-300 hover:text-white' ?> transition">
                    Avg Finish
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-xs uppercase font-mono tracking-wider text-slate-400 border-b border-slate-800">
                        <th class="py-2.5 px-3">#</th>
                        <th class="py-2.5 px-3">Franchise</th>
                        <th class="py-2.5 px-3">Manager(s)</th>
                        <th class="py-2.5 px-3 text-center">Rings</th>
                        <th class="py-2.5 px-3 text-right">W-L-T</th>
                        <th class="py-2.5 px-3 text-right">Win %</th>
                        <th class="py-2.5 px-3 text-right">Pts For</th>
                        <th class="py-2.5 px-3 text-right">Pts Against</th>
                        <th class="py-2.5 px-3 text-right">Diff</th>
                        <th class="py-2.5 px-3 text-right">Avg Finish</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-mono">
                    <?php foreach ($leaderboard as $idx => $f): 
                        $diff = $f['points_for'] - $f['points_against'];
                    ?>
                        <tr class="hover:bg-slate-800/40 transition <?= $f['id'] == 1 ? 'bg-amber-500/5' : '' ?>">
                            <td class="py-3 px-3 text-slate-400 font-bold"><?= $idx + 1 ?></td>
                            <td class="py-3 px-3 font-sans font-bold text-white flex items-center gap-2">
                                <a href="/fantasy/rivalry?teamA=<?= $f['id'] ?>" class="hover:text-amber-400 transition flex items-center gap-1.5">
                                    <span><?= htmlspecialchars($f['current_name']) ?></span>
                                    <?php if ($f['id'] == 1): ?>
                                        <span class="text-[10px] bg-amber-500/20 text-amber-300 border border-amber-500/40 px-1.5 py-0.2 rounded">Wally</span>
                                    <?php endif; ?>
                                </a>
                            </td>
                            <td class="py-3 px-3 font-sans text-xs text-slate-300">
                                <?= htmlspecialchars($f['current_managers']) ?>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <?php if ($f['titles_count'] > 0): ?>
                                    <span class="text-amber-400 font-bold text-xs"><?= str_repeat('💍', $f['titles_count']) ?></span>
                                <?php else: ?>
                                    <span class="text-slate-600 text-xs">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-3 text-right font-semibold text-slate-200">
                                <?= $f['wins'] ?>-<?= $f['losses'] ?><?= $f['ties'] > 0 ? "-{$f['ties']}" : '' ?>
                            </td>
                            <td class="py-3 px-3 text-right font-bold <?= $f['win_pct'] >= 0.5 ? 'text-emerald-400' : 'text-slate-400' ?>">
                                <?= number_format($f['win_pct'], 3) ?>
                            </td>
                            <td class="py-3 px-3 text-right text-slate-300"><?= number_format($f['points_for'], 1) ?></td>
                            <td class="py-3 px-3 text-right text-slate-400"><?= number_format($f['points_against'], 1) ?></td>
                            <td class="py-3 px-3 text-right font-bold <?= $diff >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                                <?= ($diff >= 0 ? '+' : '') . number_format($diff, 1) ?>
                            </td>
                            <td class="py-3 px-3 text-right text-slate-300">
                                <?= $f['avg_finish'] ? number_format($f['avg_finish'], 1) : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- The All-Time Record Book & Superlatives -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">

        <!-- Highest Single Game Scores -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-xl">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800/80 pb-3">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <span>🔥</span> All-Time Highest Game Scores
                </h3>
                <span class="text-[11px] font-mono text-amber-400 font-semibold">Single-Game Peak</span>
            </div>
            <div class="space-y-2.5 font-mono text-xs">
                <?php foreach ($recordBook['highest_scores'] as $i => $rec): ?>
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-800/40 border border-slate-800/80 <?= $i === 0 ? 'border-amber-500/50 bg-amber-500/5' : '' ?>">
                        <div class="flex items-center gap-2.5">
                            <span class="font-bold <?= $i === 0 ? 'text-amber-400 text-sm' : 'text-slate-500' ?>">#<?= $i + 1 ?></span>
                            <div>
                                <span class="font-sans font-bold text-white"><?= htmlspecialchars($rec['team']) ?></span>
                                <span class="text-slate-400 text-[11px] block font-sans">vs <?= htmlspecialchars($rec['opponent']) ?> (Wk <?= $rec['week_number'] ?>, <?= $rec['season_year'] ?>)</span>
                            </div>
                        </div>
                        <span class="text-sm font-black <?= $i === 0 ? 'text-amber-300' : 'text-emerald-400' ?>">
                            <?= number_format((float)$rec['score'], 1) ?> pts
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Closest Thrillers (Heartbreakers) -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-xl">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800/80 pb-3">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <span>⚡</span> Closest Heartbreakers (Smallest Margin)
                </h3>
                <span class="text-[11px] font-mono text-purple-400 font-semibold">Decided by Inches</span>
            </div>
            <div class="space-y-2.5 font-mono text-xs">
                <?php foreach ($recordBook['closest_games'] as $i => $rec): ?>
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-800/40 border border-slate-800/80">
                        <div class="flex items-center gap-2.5">
                            <span class="font-bold text-slate-500">#<?= $i + 1 ?></span>
                            <div>
                                <span class="font-sans text-slate-200"><?= htmlspecialchars($rec['away_team_name']) ?> <span class="text-slate-400 font-mono">(<?= $rec['away_score'] ?>)</span></span>
                                <span class="text-slate-500 text-[11px]">vs</span>
                                <span class="font-sans text-slate-200"><?= htmlspecialchars($rec['home_team_name']) ?> <span class="text-slate-400 font-mono">(<?= $rec['home_score'] ?>)</span></span>
                                <span class="text-slate-500 text-[11px] block font-sans">Wk <?= $rec['week_number'] ?>, <?= $rec['season_year'] ?></span>
                            </div>
                        </div>
                        <span class="text-xs font-bold px-2 py-1 rounded bg-purple-950/60 border border-purple-500/30 text-purple-300">
                            Δ <?= number_format((float)$rec['point_diff'], 2) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Biggest Blowouts -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-xl">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800/80 pb-3">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <span>💥</span> Biggest Blowouts in League History
                </h3>
                <span class="text-[11px] font-mono text-rose-400 font-semibold">Historic Annihilations</span>
            </div>
            <div class="space-y-2.5 font-mono text-xs">
                <?php foreach ($recordBook['blowouts'] as $i => $rec): ?>
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-800/40 border border-slate-800/80">
                        <div class="flex items-center gap-2.5">
                            <span class="font-bold text-slate-500">#<?= $i + 1 ?></span>
                            <div>
                                <span class="font-sans font-bold text-white"><?= htmlspecialchars($rec['winner_name']) ?></span>
                                <span class="text-slate-400 text-[11px] block font-sans">over <?= htmlspecialchars($rec['loser_name']) ?> (Wk <?= $rec['week_number'] ?>, <?= $rec['season_year'] ?>)</span>
                            </div>
                        </div>
                        <span class="text-sm font-bold text-rose-400">
                            +<?= number_format((float)$rec['point_diff'], 1) ?> pts
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Lowest Disasters -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-xl">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800/80 pb-3">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <span>💀</span> Lowest Single-Game Disasters
                </h3>
                <span class="text-[11px] font-mono text-slate-400 font-semibold">The Hall of Pain</span>
            </div>
            <div class="space-y-2.5 font-mono text-xs">
                <?php foreach ($recordBook['lowest_scores'] as $i => $rec): ?>
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-800/40 border border-slate-800/80">
                        <div class="flex items-center gap-2.5">
                            <span class="font-bold text-slate-500">#<?= $i + 1 ?></span>
                            <div>
                                <span class="font-sans font-bold text-slate-300"><?= htmlspecialchars($rec['team']) ?></span>
                                <span class="text-slate-500 text-[11px] block font-sans">vs <?= htmlspecialchars($rec['opponent']) ?> (Wk <?= $rec['week_number'] ?>, <?= $rec['season_year'] ?>)</span>
                            </div>
                        </div>
                        <span class="text-sm font-bold text-slate-400">
                            <?= number_format((float)$rec['score'], 1) ?> pts
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Yearly Roll of Honor (2003–2024) -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-xl">
        <div class="flex items-center justify-between mb-4 border-b border-slate-800/80 pb-3">
            <div>
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <span>📜</span> Chronological Roll of Champions (2003–2024)
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Every crowned champion across two decades of league history</p>
            </div>
            <a href="/fantasy/seasons" class="text-xs text-amber-400 hover:text-amber-300 transition font-semibold">
                Explore Game Logs →
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
            <?php foreach ($hallOfFame['champions'] as $c): ?>
                <a href="/fantasy/seasons?year=<?= $c['year'] ?>" class="p-3 rounded-xl bg-slate-800/40 border border-slate-800/80 hover:border-amber-400/60 hover:bg-slate-800/80 transition flex flex-col justify-between group">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-sm font-black font-mono text-amber-400"><?= $c['year'] ?></span>
                        <span class="text-xs">🏆</span>
                    </div>
                    <div class="font-bold text-white text-sm group-hover:text-amber-300 transition truncate">
                        <?= htmlspecialchars($c['champion_name']) ?>
                    </div>
                    <?php if ($c['runner_up_name']): ?>
                        <div class="text-[11px] text-slate-400 truncate mt-0.5">
                            Runner-up: <span class="text-slate-300"><?= htmlspecialchars($c['runner_up_name']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($c['notes']): ?>
                        <div class="text-[10px] text-slate-500 italic truncate mt-1">
                            <?= htmlspecialchars($c['notes']) ?>
                        </div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
