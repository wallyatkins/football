<?php
ob_start();
$teamA = $rivalryData['teamA'];
$teamB = $rivalryData['teamB'];
$winsA = $rivalryData['winsA'];
$winsB = $rivalryData['winsB'];
$ties = $rivalryData['ties'];
$total = $rivalryData['total_games'];
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800 pb-6 mb-8">
        <div>
            <div class="flex items-center gap-2.5 text-xs font-mono font-semibold uppercase tracking-wider text-amber-400 mb-1">
                <span>⚔️ Head-to-Head Matrix</span>
                <span class="text-slate-600">•</span>
                <span>20-Year Rivalry Breakdown</span>
            </div>
            <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-white flex items-center gap-3">
                <span>The Rivalry Matrix</span>
            </h1>
            <p class="text-slate-400 text-sm mt-1">
                Compare any two franchises across their complete head-to-head history in Remember the Titans.
            </p>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex items-center gap-2 bg-slate-900/80 p-1.5 rounded-xl border border-slate-800">
            <a href="/fantasy/vault" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">
                🏆 Hall of Fame
            </a>
            <a href="/fantasy/rivalry" class="px-3.5 py-1.5 text-xs font-bold rounded-lg bg-amber-500 text-black shadow-sm transition">
                ⚔️ Rivalry Matrix
            </a>
            <a href="/fantasy/seasons" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">
                📅 Season Explorer
            </a>
        </div>
    </div>

    <!-- Matchup Selector Form -->
    <form method="GET" action="/fantasy/rivalry" class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 mb-8 shadow-xl">
        <div class="text-xs font-mono uppercase tracking-wider text-slate-400 font-semibold mb-3">
            Select Two Teams to Compare:
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-center">
            <div class="sm:col-span-2">
                <label class="block text-xs text-slate-400 mb-1">Franchise A</label>
                <select name="teamA" onchange="this.form.submit()" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white font-semibold focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    <?php foreach ($franchises as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= $f['id'] == ($teamA['id'] ?? 1) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['current_name']) ?> (<?= htmlspecialchars($f['current_managers']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="text-center font-mono font-black text-slate-500 text-lg sm:pt-4">
                VS
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs text-slate-400 mb-1">Franchise B</label>
                <select name="teamB" onchange="this.form.submit()" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white font-semibold focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    <?php foreach ($franchises as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= $f['id'] == ($teamB['id'] ?? 3) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['current_name']) ?> (<?= htmlspecialchars($f['current_managers']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Quick Rivalry Shortcuts -->
        <div class="flex flex-wrap items-center gap-2 mt-4 pt-3 border-t border-slate-800/80 text-xs">
            <span class="text-slate-400 text-[11px]">Popular Rivalries:</span>
            <a href="/fantasy/rivalry?teamA=1&teamB=3" class="px-2.5 py-1 rounded-lg bg-slate-800/60 hover:bg-slate-800 border border-slate-700/60 text-amber-300 font-medium transition">
                Archetypo (Wally) vs Wonder Twins (Tamara)
            </a>
            <a href="/fantasy/rivalry?teamA=1&teamB=4" class="px-2.5 py-1 rounded-lg bg-slate-800/60 hover:bg-slate-800 border border-slate-700/60 text-slate-300 font-medium transition">
                Archetypo vs Injuries R Us (Heath)
            </a>
            <a href="/fantasy/rivalry?teamA=3&teamB=12" class="px-2.5 py-1 rounded-lg bg-slate-800/60 hover:bg-slate-800 border border-slate-700/60 text-slate-300 font-medium transition">
                Wonder Twins vs Mazies Gang
            </a>
            <a href="/fantasy/rivalry?teamA=7&teamB=10" class="px-2.5 py-1 rounded-lg bg-slate-800/60 hover:bg-slate-800 border border-slate-700/60 text-slate-300 font-medium transition">
                Schadenfreude vs BUCBALL
            </a>
        </div>
    </form>

    <?php if ($teamA && $teamB): ?>
        <!-- Tale of the Tape Scoreboard Banner -->
        <div class="bg-gradient-to-br from-slate-900 via-slate-900/90 to-slate-950 border border-slate-800 rounded-3xl p-6 sm:p-8 mb-8 shadow-2xl relative overflow-hidden">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
                
                <!-- Team A Side -->
                <div class="text-center md:text-left">
                    <div class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30 mb-2">
                        Franchise #<?= $teamA['id'] ?>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                        <?= htmlspecialchars($teamA['current_name']) ?>
                    </h2>
                    <div class="text-xs text-slate-400 mt-1">
                        👤 <?= htmlspecialchars($teamA['current_managers']) ?>
                    </div>
                    <div class="mt-3 flex items-center justify-center md:justify-start gap-3 text-xs font-mono text-slate-300">
                        <span>Career: <?= $teamA['wins'] ?>-<?= $teamA['losses'] ?></span>
                        <span class="text-slate-600">•</span>
                        <span><?= $teamA['titles_count'] ?> Titles 💍</span>
                    </div>
                </div>

                <!-- Center Head-to-Head Record -->
                <div class="flex flex-col items-center justify-center py-4 border-y md:border-y-0 md:border-x border-slate-800/80 px-4">
                    <div class="text-xs uppercase font-mono tracking-widest text-slate-400 font-bold mb-1">All-Time Series</div>
                    <div class="text-4xl sm:text-5xl font-black font-mono tracking-tight flex items-center gap-3">
                        <span class="<?= $winsA > $winsB ? 'text-amber-400' : ($winsA < $winsB ? 'text-slate-400' : 'text-slate-200') ?>"><?= $winsA ?></span>
                        <span class="text-slate-600 text-2xl">–</span>
                        <span class="<?= $winsB > $winsA ? 'text-amber-400' : ($winsB < $winsA ? 'text-slate-400' : 'text-slate-200') ?>"><?= $winsB ?></span>
                        <?php if ($ties > 0): ?>
                            <span class="text-xs text-slate-400 font-mono">(<?= $ties ?> T)</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs font-semibold text-slate-300 mt-2 text-center">
                        <?php if ($winsA > $winsB): ?>
                            <span class="text-amber-400"><?= htmlspecialchars($teamA['current_name']) ?></span> leads by <?= $winsA - $winsB ?> games
                        <?php elseif ($winsB > $winsA): ?>
                            <span class="text-amber-400"><?= htmlspecialchars($teamB['current_name']) ?></span> leads by <?= $winsB - $winsA ?> games
                        <?php else: ?>
                            <span class="text-emerald-400">Series Tied Even</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-[11px] font-mono text-slate-500 mt-1">
                        <?= $total ?> Total Matchups
                    </div>
                </div>

                <!-- Team B Side -->
                <div class="text-center md:text-right">
                    <div class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-blue-500/20 text-blue-300 border border-blue-500/30 mb-2">
                        Franchise #<?= $teamB['id'] ?>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                        <?= htmlspecialchars($teamB['current_name']) ?>
                    </h2>
                    <div class="text-xs text-slate-400 mt-1">
                        👤 <?= htmlspecialchars($teamB['current_managers']) ?>
                    </div>
                    <div class="mt-3 flex items-center justify-center md:justify-end gap-3 text-xs font-mono text-slate-300">
                        <span><?= $teamB['titles_count'] ?> Titles 💍</span>
                        <span class="text-slate-600">•</span>
                        <span>Career: <?= $teamB['wins'] ?>-<?= $teamB['losses'] ?></span>
                    </div>
                </div>

            </div>

            <!-- Aggregate Stats Bar -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-6 border-t border-slate-800/80 text-center font-mono">
                <div class="p-3 bg-slate-900/50 rounded-xl border border-slate-800">
                    <div class="text-[11px] text-slate-400 uppercase">Total Points</div>
                    <div class="text-sm font-bold text-white mt-1"><?= $rivalryData['pointsA'] ?> vs <?= $rivalryData['pointsB'] ?></div>
                </div>
                <div class="p-3 bg-slate-900/50 rounded-xl border border-slate-800">
                    <div class="text-[11px] text-slate-400 uppercase">Avg Score / Game</div>
                    <div class="text-sm font-bold text-white mt-1"><?= $rivalryData['avgScoreA'] ?> – <?= $rivalryData['avgScoreB'] ?></div>
                </div>
                <div class="p-3 bg-slate-900/50 rounded-xl border border-slate-800">
                    <div class="text-[11px] text-slate-400 uppercase">Point Differential</div>
                    <?php $ptDiff = round($rivalryData['pointsA'] - $rivalryData['pointsB'], 1); ?>
                    <div class="text-sm font-bold <?= $ptDiff >= 0 ? 'text-amber-400' : 'text-blue-400' ?> mt-1">
                        <?= ($ptDiff >= 0 ? '+' : '') . $ptDiff ?> pts
                    </div>
                </div>
                <div class="p-3 bg-slate-900/50 rounded-xl border border-slate-800">
                    <div class="text-[11px] text-slate-400 uppercase">Series Win %</div>
                    <div class="text-sm font-bold text-white mt-1">
                        <?= $total > 0 ? number_format($winsA / $total, 3) : '0.000' ?> – <?= $total > 0 ? number_format($winsB / $total, 3) : '0.000' ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- Chronological Matchup History -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-xl">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800/80 pb-3">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <span>📜</span> Complete Head-to-Head Game Log (<?= count($rivalryData['matchups']) ?> Games)
                </h3>
                <span class="text-xs text-slate-400">Chronological (Newest First)</span>
            </div>

            <?php if (empty($rivalryData['matchups'])): ?>
                <div class="text-center py-8 text-slate-500 text-sm">
                    No matchup history found between these two teams.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="text-xs uppercase font-mono tracking-wider text-slate-400 border-b border-slate-800">
                                <th class="py-2.5 px-3">Season</th>
                                <th class="py-2.5 px-3">Week</th>
                                <th class="py-2.5 px-3 text-right">Away Team</th>
                                <th class="py-2.5 px-3 text-center">Score</th>
                                <th class="py-2.5 px-3">Home Team</th>
                                <th class="py-2.5 px-3 text-center">Margin</th>
                                <th class="py-2.5 px-3 text-right">Winner</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 font-mono">
                            <?php foreach ($rivalryData['matchups'] as $m): 
                                $isAWinner = ((int)$m['winner_franchise_id'] === (int)$teamA['id']);
                                $isBWinner = ((int)$m['winner_franchise_id'] === (int)$teamB['id']);
                                $winnerName = $isAWinner ? $teamA['current_name'] : ($isBWinner ? $teamB['current_name'] : 'TIE');
                            ?>
                                <tr class="hover:bg-slate-800/40 transition">
                                    <td class="py-3 px-3 font-bold text-amber-400">
                                        <a href="/fantasy/seasons?year=<?= $m['season_year'] ?>" class="hover:underline">
                                            <?= $m['season_year'] ?>
                                        </a>
                                    </td>
                                    <td class="py-3 px-3 text-slate-400">
                                        Wk <?= $m['week_number'] ?>
                                        <?php if ($m['is_playoff']): ?>
                                            <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-purple-950 text-purple-300 border border-purple-800">Playoff</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-right font-sans font-semibold <?= (int)$m['away_franchise_id'] === (int)$teamA['id'] ? 'text-amber-300' : 'text-blue-300' ?>">
                                        <?= htmlspecialchars($m['away_team_name']) ?>
                                    </td>
                                    <td class="py-3 px-3 text-center font-bold font-mono text-white whitespace-nowrap">
                                        <?= $m['away_score'] ?> – <?= $m['home_score'] ?>
                                    </td>
                                    <td class="py-3 px-3 font-sans font-semibold <?= (int)$m['home_franchise_id'] === (int)$teamA['id'] ? 'text-amber-300' : 'text-blue-300' ?>">
                                        <?= htmlspecialchars($m['home_team_name']) ?>
                                    </td>
                                    <td class="py-3 px-3 text-center text-xs text-slate-400">
                                        <?= number_format((float)$m['point_diff'], 1) ?>
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold font-sans <?= $isAWinner ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40' : ($isBWinner ? 'bg-blue-500/20 text-blue-300 border border-blue-500/40' : 'bg-slate-800 text-slate-400') ?>">
                                            <?= htmlspecialchars($winnerName) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
