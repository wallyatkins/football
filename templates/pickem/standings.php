<?php
use WallyFootball\Support\TeamData;

ob_start();

$tbAwayData = !empty($tiebreakerGame) ? TeamData::get($tiebreakerGame['away_team']) : null;
$tbHomeData = !empty($tiebreakerGame) ? TeamData::get($tiebreakerGame['home_team']) : null;
$tbLabel = ($tbAwayData && $tbHomeData) ? "{$tbAwayData['name']} @ {$tbHomeData['name']}" : null;
?>

<div class="space-y-6">

    <!-- Header & Week Selector -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800 pb-5">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-mono px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20">Official Leaderboard</span>
                <span class="text-xs text-slate-400">Season <?= htmlspecialchars((string) $season) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">Week <?= htmlspecialchars((string) $week) ?> Standings</h1>
        </div>

        <!-- Single-Week Focus Action Bar -->
        <div class="flex items-center gap-2">
            <span class="px-3.5 py-1.5 text-xs font-black font-mono rounded-lg bg-amber-500 text-slate-950 shadow-sm flex items-center gap-1.5">
                <span>🏆</span> Week <?= $week ?> Leaderboard
            </span>
            <a href="/fantasy/vault?tab=pools" 
               class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-800 transition flex items-center gap-1.5"
               title="View historical results in the Dynasty Vault">
                <span>🏛️</span>
                <span class="hidden sm:inline">Historical Vault</span>
            </a>
            <a href="/pickem" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-slate-900 border border-slate-800 text-slate-300 hover:text-white transition">
                &larr; Make Picks
            </a>
        </div>
    </div>

    <!-- Week Champion Congratulatory Banner (Displayed when all games in week are final) -->
    <?php if (!empty($isWeekComplete) && !empty($pot['winners'])): ?>
        <div class="p-6 rounded-2xl bg-gradient-to-r from-amber-500/20 via-yellow-500/15 to-emerald-500/20 border-2 border-amber-400/60 shadow-2xl relative overflow-hidden">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="p-3.5 rounded-2xl bg-amber-500/25 text-amber-300 text-3xl border border-amber-400/50 shrink-0 shadow-lg">
                        🏆
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[11px] font-mono font-black px-2.5 py-0.5 rounded-full bg-amber-400 text-slate-950 uppercase tracking-wider">
                                Week <?= $week ?> Official Champion<?= count($pot['winners']) > 1 ? 's' : '' ?>
                            </span>
                            <span class="text-xs font-mono text-emerald-400 font-bold">Week Complete</span>
                        </div>
                        <h2 class="text-xl sm:text-2xl font-black text-white mt-1">
                            Congratulations <?= implode(' & ', array_map(fn($w) => htmlspecialchars($w['username']), $pot['winners'])) ?>! 🎉
                        </h2>
                        <p class="text-xs text-slate-300 mt-1">
                            Victory with <strong class="text-amber-300"><?= $pot['winners'][0]['correct_picks'] ?></strong> correct picks!
                            <?php if (!empty($pot['total_pot']) && $pot['total_pot'] > 0): ?>
                                Cash Prize: <strong class="font-mono text-emerald-300">$<?= number_format($pot['payout_per_winner'], 2) ?></strong><?= $pot['is_split'] ? ' (Split Pot)' : '' ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Pot Overview Card -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-5 rounded-xl bg-slate-900/60 border border-slate-800">
            <span class="text-xs font-semibold text-slate-400 block mb-1">Weekly Prize Pot</span>
            <div class="text-3xl font-black text-amber-400 font-mono">$<?= number_format($pot['total_pot'], 2) ?></div>
            <span class="text-[11px] text-slate-500 mt-1 block"><?= $pot['verified_entries_count'] ?> verified entries @ $<?= number_format($pot['entry_stake'], 2) ?></span>
        </div>

        <div class="p-5 rounded-xl bg-slate-900/60 border border-slate-800">
            <span class="text-xs font-semibold text-slate-400 block mb-1">Current Leader / Winner</span>
            <?php if (!empty($pot['winners'])): ?>
                <div class="text-lg font-bold text-white flex items-center gap-2">
                    <span>👑</span>
                    <span><?= htmlspecialchars(implode(', ', array_column($pot['winners'], 'username'))) ?></span>
                </div>
                <span class="text-[11px] text-emerald-400 font-mono mt-1 block">Payout: $<?= number_format($pot['payout_per_winner'], 2) ?><?= $pot['is_split'] ? ' (Split)' : '' ?></span>
            <?php else: ?>
                <div class="text-sm font-semibold text-slate-500 italic mt-1">Pending completed games</div>
            <?php endif; ?>
        </div>

        <div class="p-5 rounded-xl bg-slate-900/60 border border-slate-800">
            <span class="text-xs font-semibold text-slate-400 block mb-1">Total Pool Participation</span>
            <div class="text-2xl font-black text-white font-mono"><?= $pot['total_entries_count'] ?> Entrants</div>
            <span class="text-[11px] text-slate-400 mt-1 block"><?= $pot['verified_entries_count'] ?> Paid &bull; <?= $pot['total_entries_count'] - $pot['verified_entries_count'] ?> Pending</span>
        </div>
    </div>

    <?php if ($tbLabel): ?>
        <!-- Designated Tiebreaker Contest Info -->
        <div class="px-4 py-3 rounded-xl bg-slate-900/80 border border-amber-500/30 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="font-mono font-bold text-amber-400 px-2 py-0.5 rounded bg-amber-500/10 border border-amber-500/20 text-[10px] uppercase">
                    🎲 Official Tiebreaker
                </span>
                <span class="font-bold text-white"><?= htmlspecialchars($tbLabel) ?></span>
                <?php if ($tiebreakerGame['status'] === 'final'): ?>
                    <span class="font-mono text-emerald-400 font-bold">(Final: <?= $tiebreakerGame['away_score'] ?> - <?= $tiebreakerGame['home_score'] ?>, Total: <?= (int)$tiebreakerGame['home_score'] + (int)$tiebreakerGame['away_score'] ?> pts)</span>
                <?php else: ?>
                    <span class="text-slate-400 italic">(Final score pending)</span>
                <?php endif; ?>
            </div>
            <span class="text-[11px] font-mono text-slate-400">Lowest absolute &Delta; wins ties</span>
        </div>
    <?php endif; ?>

    <!-- Opponent Picks Visibility Callout -->
    <?php if ($canViewOpponentPicks): ?>
        <div class="px-5 py-3.5 rounded-2xl bg-emerald-950/40 border border-emerald-500/40 text-xs text-emerald-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-md">
            <div class="flex items-center gap-2.5">
                <span class="text-xl">🔓</span>
                <div>
                    <strong class="text-emerald-300 text-sm block font-bold">Opponent Picks Unlocked!</strong>
                    <span>The opening game has kicked off and your picks are locked in. You can now inspect all participant selections below or open the full league matrix.</span>
                </div>
            </div>
            <button type="button" onclick="openPicksMatrixModal()" class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold transition shadow shrink-0 flex items-center gap-1.5">
                <span>📋</span>
                <span>Full League Matrix</span>
            </button>
        </div>
    <?php elseif ($firstGameStarted && !$viewerHasSubmitted): ?>
        <div class="px-5 py-3.5 rounded-2xl bg-amber-950/40 border border-amber-500/40 text-xs text-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-md">
            <div class="flex items-center gap-2.5">
                <span class="text-xl">🔒</span>
                <div>
                    <strong class="text-amber-300 text-sm block font-bold">Opponent Picks Locked</strong>
                    <span>The first game of Week <?= $week ?> has kicked off, but you have not locked in your picks yet. Lock in your picks now to unlock what everyone else picked!</span>
                </div>
            </div>
            <a href="/pickem?week=<?= $week ?>&season=<?= $season ?>" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold transition shadow shrink-0">
                Lock In Your Picks &rarr;
            </a>
        </div>
    <?php else: ?>
        <div class="px-5 py-3 rounded-2xl bg-slate-900 border border-slate-800 text-xs text-slate-400 flex items-center gap-2.5">
            <span class="text-lg">🔒</span>
            <span><strong>Opponent Picks Confidential:</strong> All participant selections remain confidential until the opening kickoff (<?= htmlspecialchars($firstKickoffFormatted) ?>).</span>
        </div>
    <?php endif; ?>

    <!-- Tier Filter Tabs -->
    <div class="flex items-center justify-between gap-2 border-b border-slate-800 pb-2 flex-wrap">
        <div class="flex items-center gap-2">
            <button type="button" onclick="filterPickem('all')" id="pickem-tab-all"
                    class="pickem-tab px-3.5 py-1.5 text-xs font-bold rounded-lg bg-amber-500 text-slate-950 transition shadow-sm">
                All Entrants (<?= count($standings) ?>)
            </button>
            <button type="button" onclick="filterPickem('cash')" id="pickem-tab-cash"
                    class="pickem-tab px-3.5 py-1.5 text-xs font-bold rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-white hover:bg-slate-800 transition">
                🟢 Cash Prize Pool ($) (<?= $pot['verified_entries_count'] ?>)
            </button>
            <button type="button" onclick="filterPickem('free')" id="pickem-tab-free"
                    class="pickem-tab px-3.5 py-1.5 text-xs font-bold rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-white hover:bg-slate-800 transition">
                🎮 Free / For Fun (<?= $pot['total_entries_count'] - $pot['verified_entries_count'] ?>)
            </button>
        </div>

        <?php if ($canViewOpponentPicks): ?>
            <button type="button" onclick="openPicksMatrixModal()" class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-800 hover:bg-slate-800 text-slate-300 font-bold text-xs transition flex items-center gap-1.5">
                <span>📋</span>
                <span>Picks Matrix</span>
            </button>
        <?php endif; ?>
    </div>

    <!-- Standings Table -->
    <div class="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900/60 shadow-xl">
        <table class="w-full text-left text-sm" id="pickemTable">
            <thead>
                <tr class="border-b border-slate-800 bg-slate-900/80 text-[11px] font-mono uppercase tracking-wider text-slate-400">
                    <th class="py-3 px-4 text-center w-12">Rank</th>
                    <th class="py-3 px-4">Participant</th>
                    <th class="py-3 px-4 text-center">Play Mode</th>
                    <th class="py-3 px-4 text-center">Correct Picks</th>
                    <th class="py-3 px-4 text-center">Tiebreaker Pred / Delta</th>
                    <th class="py-3 px-4 text-center">Selections</th>
                    <th class="py-3 px-4 text-right">Cash Prize Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                <?php if (empty($standings)): ?>
                    <tr>
                        <td colspan="7" class="py-8 text-center text-slate-500 italic">No entries recorded for Week <?= htmlspecialchars((string) $week) ?> yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($standings as $row): ?>
                        <?php
                        $isWinner = in_array($row['username'], array_column($pot['winners'] ?? [], 'username'), true);
                        $canSeeThisRow = $canViewOpponentPicks || (($user['id'] ?? 0) === $row['user_id']);
                        ?>
                        <tr class="transition pickem-row <?= $isWinner ? 'bg-amber-500/10 hover:bg-amber-500/15' : 'hover:bg-slate-800/30' ?>"
                            data-tier="<?= $row['is_paid'] ? 'cash' : 'free' ?>">
                            <td class="py-3.5 px-4 text-center font-mono font-bold text-slate-300">
                                <?= $isWinner ? '👑' : '#' . $row['rank'] ?>
                            </td>
                            <td class="py-3.5 px-4 font-semibold text-white">
                                <?= htmlspecialchars($row['username']) ?>
                                <?php if (($user['id'] ?? 0) === $row['user_id']): ?>
                                    <span class="text-[10px] ml-1.5 px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 border border-slate-700">You</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <?php if ($row['is_paid']): ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                        🟢 Cash ($10)
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                        🎮 Free / Fun
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-center font-mono font-bold text-emerald-400">
                                <?= $row['correct_picks'] ?> <span class="text-xs text-slate-500 font-normal">/ <?= $row['total_graded'] ?></span>
                            </td>
                            <td class="py-3.5 px-4 text-center font-mono text-xs">
                                <?php if ($row['predicted_mnf'] !== null): ?>
                                    <span class="text-slate-300 font-bold"><?= $row['predicted_mnf'] ?> pts</span>
                                    <?php if ($row['tiebreaker_delta'] !== null): ?>
                                        <span class="text-amber-400 ml-1">(&Delta; <?= $row['tiebreaker_delta'] ?>)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-slate-600">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <?php if ($canSeeThisRow): ?>
                                    <button type="button" onclick="toggleUserPicks(<?= $row['entry_id'] ?>)" 
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-semibold transition hover:border-slate-600 shadow-sm">
                                        <span>👁️</span>
                                        <span id="btnText-<?= $row['entry_id'] ?>">View Picks</span>
                                        <span class="text-[10px] text-slate-400 font-mono">(<?= $row['total_picks'] ?>)</span>
                                    </button>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-slate-500 text-xs font-mono" title="Picks unlock once you lock in your picks">
                                        <span>🔒</span> Hidden
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <?php if ($isWinner): ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2 py-0.5 rounded-full bg-amber-500/30 text-amber-300 border border-amber-500/50 animate-pulse">
                                        👑 Cash Winner
                                    </span>
                                <?php elseif ($row['is_paid']): ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                        ✓ Cash Verified
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700">
                                        🎮 Bragging Rights
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Accordion Row with Detailed Picks -->
                        <?php if ($canSeeThisRow): ?>
                            <tr id="picksRow-<?= $row['entry_id'] ?>" class="hidden bg-slate-950/90 border-b border-slate-800/80 transition-all duration-200">
                                <td colspan="7" class="p-4 sm:p-5">
                                    <div class="rounded-xl border border-slate-800/90 bg-slate-900/60 p-4 space-y-3">
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-800 pb-2.5">
                                            <div class="flex items-center gap-2.5 flex-wrap">
                                                <span class="font-bold text-white text-xs"><?= htmlspecialchars($row['username']) ?>'s Week <?= $week ?> Selections:</span>
                                                <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-mono text-[10px] font-bold">✓ <?= $row['correct_picks'] ?> Correct</span>
                                                <span class="px-2 py-0.5 rounded bg-rose-500/20 text-rose-300 font-mono text-[10px] font-bold">✗ <?= $row['total_graded'] - $row['correct_picks'] ?> Missed</span>
                                                <span class="px-2 py-0.5 rounded bg-slate-800 text-slate-400 font-mono text-[10px] font-bold">⏳ <?= $row['pending_picks'] ?> Pending</span>
                                            </div>
                                            <?php if ($row['predicted_mnf'] !== null): ?>
                                                <div class="text-[11px] font-mono text-amber-300 bg-amber-500/10 border border-amber-500/20 px-2 py-0.5 rounded">
                                                    Tiebreaker Pred: <strong><?= $row['predicted_mnf'] ?> pts</strong>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-8 gap-2">
                                            <?php foreach ($row['picks_detail'] ?? [] as $gid => $pd): ?>
                                                <?php
                                                $isCorrect = ($pd['result'] === 'correct');
                                                $isIncorrect = ($pd['result'] === 'incorrect');
                                                $isFinal = ($pd['status'] === 'final');
                                                $isLive = ($pd['status'] === 'in_progress');
                                                $selected = $pd['selected_team'];
                                                $selData = $selected ? TeamData::get($selected) : null;
                                                ?>
                                                <div class="p-2 rounded-xl border text-center flex flex-col justify-between items-center transition <?= $isCorrect ? 'border-emerald-500/60 bg-emerald-950/30' : ($isIncorrect ? 'border-rose-500/60 bg-rose-950/30' : ($isLive ? 'border-amber-500/50 bg-amber-950/20 animate-pulse' : 'border-slate-800 bg-slate-950/60')) ?>">
                                                    <span class="text-[9px] font-mono text-slate-400 block truncate w-full">
                                                        <?= $pd['away_team'] ?> @ <?= $pd['home_team'] ?>
                                                    </span>
                                                    <?php if ($selData): ?>
                                                        <img src="<?= htmlspecialchars($selData['logo']) ?>" alt="<?= $selected ?>" class="w-6 h-6 object-contain my-1">
                                                        <span class="text-xs font-black text-white font-mono"><?= $selected ?></span>
                                                    <?php else: ?>
                                                        <span class="text-xs text-slate-600 font-mono my-2">—</span>
                                                    <?php endif; ?>
                                                    <div class="mt-1">
                                                        <?php if ($isCorrect): ?>
                                                            <span class="text-[9px] font-mono font-black text-emerald-400">✓ Win (+1)</span>
                                                        <?php elseif ($isIncorrect): ?>
                                                            <span class="text-[9px] font-mono font-black text-rose-400">✗ Loss (0)</span>
                                                        <?php elseif ($isLive): ?>
                                                            <span class="text-[9px] font-mono font-bold text-amber-300">⚡ Live</span>
                                                        <?php else: ?>
                                                            <span class="text-[9px] font-mono font-medium text-slate-500">⏳ Pending</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- Full League Picks Matrix Modal -->
<?php if ($canViewOpponentPicks): ?>
    <div id="picksMatrixModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-700/80 rounded-2xl max-w-6xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div class="p-5 border-b border-slate-800 bg-slate-950/80 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="p-2 rounded-xl bg-amber-500/20 text-amber-400 text-xl border border-amber-500/30">📋</span>
                    <div>
                        <h3 class="text-lg font-black text-white">Week <?= $week ?> Complete League Picks Matrix</h3>
                        <span class="text-xs text-slate-400">Side-by-side comparison of all participant selections</span>
                    </div>
                </div>
                <button type="button" onclick="closePicksMatrixModal()" class="text-slate-400 hover:text-white text-2xl font-bold leading-none p-2">&times;</button>
            </div>
            <div class="p-5 overflow-auto flex-1">
                <table class="w-full text-xs text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950 font-mono text-[10px] uppercase text-slate-400">
                            <th class="py-2.5 px-3 sticky left-0 bg-slate-950 z-10">Participant</th>
                            <th class="py-2.5 px-2 text-center">Score</th>
                            <?php foreach ($games as $g): ?>
                                <th class="py-2.5 px-2 text-center min-w-[75px]">
                                    <span class="block truncate font-bold"><?= $g['away_team'] ?> @ <?= $g['home_team'] ?></span>
                                    <span class="text-[9px] text-slate-500"><?= $g['status'] === 'final' ? "({$g['away_score']}-{$g['home_score']})" : ($g['status'] === 'in_progress' ? 'Live' : 'Sched') ?></span>
                                </th>
                            <?php endforeach; ?>
                            <th class="py-2.5 px-2 text-center">Tiebreaker</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 font-mono">
                        <?php foreach ($standings as $row): ?>
                            <tr class="hover:bg-slate-800/30">
                                <td class="py-2 px-3 font-bold text-white sticky left-0 bg-slate-900/95 z-10 truncate">
                                    <?= htmlspecialchars($row['username']) ?>
                                    <?php if (($user['id'] ?? 0) === $row['user_id']): ?>
                                        <span class="text-[9px] px-1 py-0.2 rounded bg-slate-800 text-slate-400">You</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 px-2 text-center text-emerald-400 font-bold">
                                    <?= $row['correct_picks'] ?>/<?= $row['total_graded'] ?>
                                </td>
                                <?php foreach ($games as $g): ?>
                                    <?php
                                    $pd = $row['picks_detail'][$g['id']] ?? null;
                                    $sel = $pd['selected_team'] ?? null;
                                    $res = $pd['result'] ?? 'pending';
                                    $cellClass = "bg-slate-800/40 text-slate-400 border-slate-700";
                                    if ($res === 'correct') {
                                        $cellClass = "bg-emerald-500/20 text-emerald-300 border-emerald-500/40 font-bold";
                                    } elseif ($res === 'incorrect') {
                                        $cellClass = "bg-rose-500/20 text-rose-300 border-rose-500/40";
                                    }
                                    ?>
                                    <td class="py-1.5 px-2 text-center">
                                        <?php if ($sel): ?>
                                            <span class="inline-block px-1.5 py-0.5 rounded border text-[10px] <?= $cellClass ?>">
                                                <?= $sel ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-600">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="py-2 px-2 text-center text-amber-400 font-bold">
                                    <?= $row['predicted_mnf'] !== null ? $row['predicted_mnf'] : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-800 bg-slate-950/90 flex justify-end">
                <button type="button" onclick="closePicksMatrixModal()" class="px-5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs uppercase tracking-wider transition shadow">
                    Close Matrix
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function filterPickem(tier) {
    document.querySelectorAll('.pickem-tab').forEach(el => {
        el.className = 'pickem-tab px-3.5 py-1.5 text-xs font-bold rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-white hover:bg-slate-800 transition';
    });
    const activeBtn = document.getElementById('pickem-tab-' + tier);
    if (activeBtn) {
        activeBtn.className = 'pickem-tab px-3.5 py-1.5 text-xs font-bold rounded-lg bg-amber-500 text-slate-950 transition shadow-sm';
    }

    const rows = document.querySelectorAll('.pickem-row');
    rows.forEach(row => {
        if (tier === 'all' || row.dataset.tier === tier) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function toggleUserPicks(entryId) {
    const row = document.getElementById('picksRow-' + entryId);
    const btnText = document.getElementById('btnText-' + entryId);
    if (!row) return;
    if (row.classList.contains('hidden')) {
        row.classList.remove('hidden');
        if (btnText) btnText.textContent = 'Hide Picks';
    } else {
        row.classList.add('hidden');
        if (btnText) btnText.textContent = 'View Picks';
    }
}

function openPicksMatrixModal() {
    const modal = document.getElementById('picksMatrixModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closePicksMatrixModal() {
    const modal = document.getElementById('picksMatrixModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
