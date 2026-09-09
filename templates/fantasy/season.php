<?php
ob_start();
$season = $seasonData['season'];
$standings = $seasonData['standings'];
$matchupsByWeek = $seasonData['matchups_by_week'];
$currentYear = (int)($season['year'] ?? 2024);
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800 pb-6 mb-8">
        <div>
            <div class="flex items-center gap-2.5 text-xs font-mono font-semibold uppercase tracking-wider text-amber-400 mb-1">
                <span>📅 Season Archive</span>
                <span class="text-slate-600">•</span>
                <span>Season-by-Season Explorer</span>
            </div>
            <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-white flex items-center gap-3">
                <span>The <?= $currentYear ?> Season</span>
            </h1>
            <p class="text-slate-400 text-sm mt-1">
                Full final standings and complete week-by-week box scores for the <?= $currentYear ?> campaign.
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
            <a href="/fantasy/seasons" class="px-3.5 py-1.5 text-xs font-bold rounded-lg bg-amber-500 text-black shadow-sm transition">
                📅 Season Explorer
            </a>
            <a href="/fantasy/vault?tab=pools" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">
                📜 Pool Archives
            </a>
        </div>
    </div>

    <!-- Season Year Selector Horizontal Scroller -->
    <div class="mb-8">
        <div class="text-xs font-mono uppercase tracking-wider text-slate-400 font-semibold mb-2">
            Select a Season:
        </div>
        <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-thin">
            <?php foreach ($allSeasons as $s): 
                $yr = (int)$s['year'];
                $isSelected = ($yr === $currentYear);
            ?>
                <a href="/fantasy/seasons?year=<?= $yr ?>" class="px-3.5 py-1.5 text-xs font-mono font-bold rounded-xl whitespace-nowrap transition <?= $isSelected ? 'bg-amber-500 text-black shadow-md scale-105' : 'bg-slate-900 border border-slate-800 text-slate-300 hover:text-white hover:border-slate-700' ?>">
                    <?= $yr ?> <?= $s['champion_name'] ? '🏆' : '' ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Season Banner (Champion & Highlights) -->
    <?php if ($season && $season['champion_name']): ?>
        <div class="bg-gradient-to-r from-amber-950/40 via-slate-900 to-slate-900 border border-amber-500/40 rounded-3xl p-6 sm:p-8 mb-8 shadow-2xl relative overflow-hidden">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                <div>
                    <div class="text-xs font-mono uppercase tracking-wider text-amber-400 font-bold mb-1 flex items-center gap-1.5">
                        <span>🏆</span> <?= $currentYear ?> League Champion
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                        <?= htmlspecialchars($season['champion_name']) ?>
                    </h2>
                    <?php if ($season['runner_up_name']): ?>
                        <div class="text-xs text-slate-400 mt-1">
                            Runner-Up: <span class="text-slate-200 font-semibold"><?= htmlspecialchars($season['runner_up_name']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($season['notes']): ?>
                        <div class="text-xs text-slate-400 italic mt-2">
                            <?= htmlspecialchars($season['notes']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-3">
                    <a href="/fantasy/rivalry?teamA=<?= $season['champion_franchise_id'] ?? 1 ?>" class="px-4 py-2 rounded-xl bg-amber-500/20 border border-amber-500/40 text-amber-300 hover:bg-amber-500 hover:text-black transition text-xs font-bold font-mono">
                        View Franchise Rivalries →
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Final Standings Table -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 mb-10 shadow-xl">
        <div class="flex items-center justify-between mb-4 border-b border-slate-800/80 pb-3">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <span>📊</span> <?= $currentYear ?> Final Standings
            </h2>
            <span class="text-xs text-slate-400">12 Teams</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-xs uppercase font-mono tracking-wider text-slate-400 border-b border-slate-800">
                        <th class="py-2.5 px-3">Rank</th>
                        <th class="py-2.5 px-3">Team Name</th>
                        <?php if (!empty($standings[0]['division_name'])): ?>
                            <th class="py-2.5 px-3">Division</th>
                        <?php endif; ?>
                        <th class="py-2.5 px-3 text-right">W-L-T</th>
                        <th class="py-2.5 px-3 text-right">Win %</th>
                        <th class="py-2.5 px-3 text-right">Points For</th>
                        <th class="py-2.5 px-3 text-right">Points Against</th>
                        <th class="py-2.5 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-mono">
                    <?php foreach ($standings as $s): ?>
                        <tr class="hover:bg-slate-800/40 transition">
                            <td class="py-3 px-3 font-bold text-slate-400">
                                <?= $s['rank'] ?>
                            </td>
                            <td class="py-3 px-3 font-sans font-bold text-white">
                                <?= htmlspecialchars($s['team_name']) ?>
                            </td>
                            <?php if (!empty($standings[0]['division_name'])): ?>
                                <td class="py-3 px-3 text-xs text-slate-400 font-sans">
                                    <?= htmlspecialchars($s['division_name'] ?? '—') ?>
                                </td>
                            <?php endif; ?>
                            <td class="py-3 px-3 text-right font-semibold text-slate-200">
                                <?= $s['wins'] ?>-<?= $s['losses'] ?><?= $s['ties'] > 0 ? "-{$s['ties']}" : '' ?>
                            </td>
                            <td class="py-3 px-3 text-right font-bold <?= $s['win_pct'] >= 0.5 ? 'text-emerald-400' : 'text-slate-400' ?>">
                                <?= number_format((float)$s['win_pct'], 3) ?>
                            </td>
                            <td class="py-3 px-3 text-right text-slate-300"><?= number_format((float)$s['points_for'], 1) ?></td>
                            <td class="py-3 px-3 text-right text-slate-400"><?= number_format((float)$s['points_against'], 1) ?></td>
                            <td class="py-3 px-3 text-right">
                                <a href="/fantasy/rivalry?teamA=<?= $s['franchise_id'] ?>" class="text-[11px] font-sans text-amber-400 hover:text-amber-300 hover:underline">
                                    Rivalries →
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Weekly Matchup Results (2007-2024) -->
    <?php if (!empty($matchupsByWeek)): ?>
        <div class="mb-10">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-bold text-white flex items-center gap-2">
                        <span>🏈</span> <?= $currentYear ?> Weekly Box Scores
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">Every head-to-head outcome from Week 1 to the Championship</p>
                </div>
                <span class="text-xs font-mono text-slate-400"><?= count($matchupsByWeek) ?> Weeks Scheduled</span>
            </div>

            <div class="space-y-6">
                <?php foreach ($matchupsByWeek as $weekNum => $weekMatches): ?>
                    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg">
                        <div class="flex items-center justify-between border-b border-slate-800/80 pb-3 mb-4">
                            <h3 class="text-sm font-bold font-mono text-amber-400 flex items-center gap-2">
                                <span>WEEK <?= $weekNum ?></span>
                                <?php if (!empty($weekMatches[0]['is_playoff'])): ?>
                                    <span class="text-[10px] uppercase font-sans font-bold px-2 py-0.5 rounded bg-purple-950 text-purple-300 border border-purple-800">
                                        Playoffs
                                    </span>
                                <?php endif; ?>
                            </h3>
                            <span class="text-[11px] text-slate-400 font-mono"><?= count($weekMatches) ?> Games</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            <?php foreach ($weekMatches as $m): 
                                $awayWon = ((float)$m['away_score'] > (float)$m['home_score']);
                                $homeWon = ((float)$m['home_score'] > (float)$m['away_score']);
                            ?>
                                <div class="p-3 rounded-xl bg-slate-800/40 border border-slate-800/80 hover:border-slate-700 transition flex flex-col justify-between">
                                    <div class="space-y-1.5 font-mono text-xs">
                                        <div class="flex items-center justify-between">
                                            <span class="font-sans font-semibold <?= $awayWon ? 'text-white' : 'text-slate-400' ?> truncate max-w-[160px]">
                                                <?= htmlspecialchars($m['away_team_name']) ?>
                                            </span>
                                            <span class="font-bold <?= $awayWon ? 'text-amber-300' : 'text-slate-400' ?>">
                                                <?= number_format((float)$m['away_score'], 1) ?>
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="font-sans font-semibold <?= $homeWon ? 'text-white' : 'text-slate-400' ?> truncate max-w-[160px]">
                                                <?= htmlspecialchars($m['home_team_name']) ?>
                                            </span>
                                            <span class="font-bold <?= $homeWon ? 'text-amber-300' : 'text-slate-400' ?>">
                                                <?= number_format((float)$m['home_score'], 1) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="pt-2 mt-2 border-t border-slate-800/60 flex items-center justify-between text-[10px] text-slate-500 font-mono">
                                        <span>Diff: <?= number_format((float)$m['point_diff'], 1) ?> pts</span>
                                        <a href="/fantasy/rivalry?teamA=<?= $m['away_franchise_id'] ?>&teamB=<?= $m['home_franchise_id'] ?>" class="text-amber-400/80 hover:text-amber-300 transition">
                                            Series History →
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
