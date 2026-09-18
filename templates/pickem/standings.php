<?php
use WallyFootball\Support\TeamData;

ob_start();

$tbAwayData = !empty($tiebreakerGame) ? TeamData::get($tiebreakerGame['away_team']) : null;
$tbHomeData = !empty($tiebreakerGame) ? TeamData::get($tiebreakerGame['home_team']) : null;
$tbLabel = ($tbAwayData && $tbHomeData) ? "{$tbAwayData['name']} @ {$tbHomeData['name']}" : null;
$cutoffFormatted = $cutoffFormatted ?? ($firstKickoffFormatted ?? 'Cutoff');
$cutoffPassed = !empty($cutoffPassed);
?>

<div class="space-y-6">

    <!-- Standings Navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-[#243247] pb-3">
        <a href="/pickem/standings?week=<?= $week ?>&season=<?= $season ?>" 
           class="px-4 py-2 text-xs font-bold rounded-lg bg-[#EAB308] text-[#0B1626] uppercase tracking-wider transition shadow-sm">
            Weekly Pick'em Standings
        </a>
        <a href="/survivor/standings?season=<?= $season ?>" 
           class="px-4 py-2 text-xs font-bold rounded-lg bg-[#162235] border border-[#243247] text-[#94A3B8] hover:text-white hover:bg-[#1f2e44] transition uppercase tracking-wider">
            Survivor Pool Standings
        </a>
    </div>

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[#243247] pb-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-mono px-2 py-0.5 rounded bg-[#162235] text-[#EAB308] border border-[#243247]">Official Leaderboard</span>
                <span class="text-xs text-[#94A3B8] font-mono">Season <?= htmlspecialchars((string) $season) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-[#F8FAFC]">Week <?= htmlspecialchars((string) $week) ?> Standings</h1>
        </div>

        <div class="flex items-center gap-2 flex-wrap font-mono text-xs">
            <span class="px-3 py-1.5 font-bold rounded-lg bg-[#EAB308] text-[#0B1626]">
                Week <?= $week ?> Leaderboard
            </span>
            <a href="/fantasy/vault?tab=pools" 
               class="px-3 py-1.5 rounded-lg bg-[#162235] hover:bg-[#1f2e44] text-[#94A3B8] hover:text-white border border-[#243247] transition">
                Dynasty Vault
            </a>
            <a href="/pickem?week=<?= $week ?>&season=<?= $season ?>" 
               class="px-3 py-1.5 rounded-lg bg-[#15803D] hover:bg-emerald-600 text-white font-bold transition">
                &larr; Make Picks
            </a>
        </div>
    </div>

    <!-- Week Navigation Pills -->
    <?php if (!empty($availableWeeks) && count($availableWeeks) > 1): ?>
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-[11px] font-mono text-[#94A3B8] uppercase tracking-wider">Week:</span>
            <?php foreach ($availableWeeks as $wk): ?>
                <a href="/pickem/standings?week=<?= $wk ?>&season=<?= $season ?>"
                   class="px-3 py-1 text-xs font-bold rounded-lg font-mono tabular-nums transition <?= (int)$wk === (int)$week ? 'bg-[#EAB308] text-[#0B1626]' : 'bg-[#162235] border border-[#243247] text-[#94A3B8] hover:text-white' ?>">
                    Wk <?= $wk ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Completed Week Champions (Collapsible) -->
    <?php if (!empty($isWeekComplete)): ?>
        <details class="rounded-xl border border-[#243247] bg-[#162235] p-4 text-xs shadow-sm group">
            <summary class="cursor-pointer flex items-center justify-between font-bold text-[#F8FAFC] select-none">
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold bg-[#EAB308] text-[#0B1626] uppercase">
                        Official Results
                    </span>
                    <span class="text-sm">Week <?= $week ?> Champions &amp; Payouts</span>
                </div>
                <span class="text-[#94A3B8] font-mono text-xs group-open:rotate-180 transition-transform">&darr;</span>
            </summary>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-4 mt-3 border-t border-[#243247]">
                <!-- Overall Champion -->
                <div class="p-3.5 rounded-lg bg-[#0B1626] border border-[#243247]">
                    <span class="font-mono font-bold text-[10px] uppercase text-[#EAB308] block mb-1">Overall Champion</span>
                    <?php if (!empty($winnersOverall)): ?>
                        <div class="font-bold text-[#F8FAFC] truncate text-sm">
                            <?= implode(' &amp; ', array_map(fn($w) => htmlspecialchars($w['username']), $winnersOverall)) ?>
                        </div>
                        <span class="text-[11px] text-[#94A3B8] font-mono block mt-0.5 tabular-nums">
                            <?= $winnersOverall[0]['correct_picks'] ?> correct picks <?= count($winnersOverall) > 1 ? '(Tied)' : '' ?>
                        </span>
                    <?php else: ?>
                        <span class="text-[#94A3B8] italic">Pending</span>
                    <?php endif; ?>
                </div>

                <!-- Cash Winner -->
                <div class="p-3.5 rounded-lg bg-[#0B1626] border border-[#243247]">
                    <span class="font-mono font-bold text-[10px] uppercase text-emerald-400 block mb-1">Cash Pool Winner</span>
                    <?php if (!empty($winnersPaid)): ?>
                        <div class="font-bold text-[#F8FAFC] truncate text-sm">
                            <?= implode(' &amp; ', array_map(fn($w) => htmlspecialchars($w['username']), $winnersPaid)) ?>
                        </div>
                        <span class="text-[11px] text-emerald-400 font-mono block mt-0.5 tabular-nums">
                            <?= $winnersPaid[0]['correct_picks'] ?> correct &bull; $<?= number_format($pot['payout_per_winner'] / max(1, count($winnersPaid)), 2) ?>
                        </span>
                    <?php else: ?>
                        <span class="text-[#94A3B8] italic">No cash verified</span>
                    <?php endif; ?>
                </div>

                <!-- Free / Fun Winner -->
                <div class="p-3.5 rounded-lg bg-[#0B1626] border border-[#243247]">
                    <span class="font-mono font-bold text-[10px] uppercase text-purple-300 block mb-1">Free / Fun Winner</span>
                    <?php if (!empty($winnersFree)): ?>
                        <div class="font-bold text-[#F8FAFC] truncate text-sm">
                            <?= implode(' &amp; ', array_map(fn($w) => htmlspecialchars($w['username']), $winnersFree)) ?>
                        </div>
                        <span class="text-[11px] text-[#94A3B8] font-mono block mt-0.5 tabular-nums">
                            <?= $winnersFree[0]['correct_picks'] ?> correct
                        </span>
                    <?php else: ?>
                        <span class="text-[#94A3B8] italic">Pending</span>
                    <?php endif; ?>
                </div>
            </div>
        </details>
    <?php endif; ?>

    <!-- Pot Overview Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-4 rounded-xl bg-[#162235] border border-[#243247]">
            <span class="text-xs font-semibold text-[#94A3B8] block mb-1">Weekly Prize Pot</span>
            <div class="text-2xl font-bold text-[#EAB308] font-mono tabular-nums">$<?= number_format($pot['total_pot'], 2) ?></div>
            <span class="text-[11px] text-[#94A3B8] font-mono mt-1 block tabular-nums">
                <?= $pot['verified_entries_count'] ?> verified entries @ $<?= number_format($pot['entry_stake'], 2) ?>
            </span>
        </div>

        <div class="p-4 rounded-xl bg-[#162235] border border-[#243247]">
            <span class="text-xs font-semibold text-[#94A3B8] block mb-1">Current Leader / Winner</span>
            <?php if (!empty($pot['winners'])): ?>
                <div class="text-base font-bold text-[#F8FAFC] truncate">
                    <?= htmlspecialchars(implode(', ', array_column($pot['winners'], 'username'))) ?>
                </div>
                <span class="text-[11px] text-emerald-400 font-mono mt-1 block tabular-nums">
                    Payout: $<?= number_format($pot['payout_per_winner'], 2) ?><?= $pot['is_split'] ? ' (Split)' : '' ?>
                </span>
            <?php else: ?>
                <div class="text-sm text-[#94A3B8] italic mt-1">Pending completed games</div>
            <?php endif; ?>
        </div>

        <div class="p-4 rounded-xl bg-[#162235] border border-[#243247]">
            <span class="text-xs font-semibold text-[#94A3B8] block mb-1">Pool Participation</span>
            <div class="text-2xl font-bold text-[#F8FAFC] font-mono tabular-nums"><?= $pot['total_entries_count'] ?> Entrants</div>
            <span class="text-[11px] text-[#94A3B8] font-mono mt-1 block tabular-nums">
                <?= $pot['verified_entries_count'] ?> Paid &bull; <?= $pot['total_entries_count'] - $pot['verified_entries_count'] ?> Pending
            </span>
        </div>
    </div>

    <?php if ($tbLabel): ?>
        <!-- Designated Tiebreaker Contest Info -->
        <div class="px-4 py-3 rounded-xl bg-[#162235] border border-[#243247] flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="font-mono font-bold text-[#EAB308] px-2 py-0.5 rounded bg-[#0B1626] border border-[#243247] text-[10px] uppercase">
                    Tiebreaker Game
                </span>
                <span class="font-bold text-[#F8FAFC]"><?= htmlspecialchars($tbLabel) ?></span>
                <?php if (!empty($tiebreakerGame) && $tiebreakerGame['status'] === 'final'): ?>
                    <span class="font-mono text-emerald-400 font-bold tabular-nums">
                        (Final: <?= $tiebreakerGame['away_score'] ?> - <?= $tiebreakerGame['home_score'] ?>, Total: <?= (int)$tiebreakerGame['home_score'] + (int)$tiebreakerGame['away_score'] ?> pts)
                    </span>
                <?php else: ?>
                    <span class="text-[#94A3B8] italic">(Final score pending)</span>
                <?php endif; ?>
            </div>
            <span class="text-[11px] font-mono text-[#94A3B8]">Lowest absolute &Delta; wins ties</span>
        </div>
    <?php endif; ?>

    <!-- Opponent Picks Visibility Notice -->
    <div class="px-4 py-3 rounded-xl bg-[#162235] border border-[#243247] text-xs text-[#94A3B8] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <div class="w-6 h-6 rounded-full bg-[#0B1626] border border-[#243247] flex items-center justify-center shrink-0">
                <svg class="w-3.5 h-3.5 text-[#EAB308]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <strong class="text-[#F8FAFC] font-semibold block">Per-Game Kickoff Transparency</strong>
                <span>Opponent selections are revealed in real time as each game kicks off. Unstarted games remain confidential.</span>
            </div>
        </div>

        <?php if ($canViewOpponentPicks): ?>
            <button type="button" onclick="openPicksMatrixModal()" class="px-3 py-1.5 rounded-lg bg-[#0B1626] hover:bg-[#1f2e44] text-[#F8FAFC] border border-[#243247] font-bold transition shrink-0 font-mono text-[11px] uppercase tracking-wider">
                Full League Matrix
            </button>
        <?php endif; ?>
    </div>

    <!-- Tier Filter Tabs & Action Bar -->
    <div class="flex items-center justify-between gap-2 border-b border-[#243247] pb-2 flex-wrap">
        <div class="flex items-center gap-2">
            <button type="button" onclick="filterPickem('all')" id="pickem-tab-all"
                    class="pickem-tab px-3 py-1.5 text-xs font-bold rounded-lg bg-[#EAB308] text-[#0B1626] transition">
                All Entrants (<?= count($standings) ?>)
            </button>
            <button type="button" onclick="filterPickem('cash')" id="pickem-tab-cash"
                    class="pickem-tab px-3 py-1.5 text-xs font-bold rounded-lg bg-[#162235] border border-[#243247] text-[#94A3B8] hover:text-white transition">
                Cash Pool (<?= $pot['verified_entries_count'] ?>)
            </button>
            <button type="button" onclick="filterPickem('free')" id="pickem-tab-free"
                    class="pickem-tab px-3 py-1.5 text-xs font-bold rounded-lg bg-[#162235] border border-[#243247] text-[#94A3B8] hover:text-white transition">
                Free / Fun (<?= $pot['total_entries_count'] - $pot['verified_entries_count'] ?>)
            </button>
        </div>

        <?php if ($canViewOpponentPicks): ?>
            <button type="button" onclick="openPicksMatrixModal()" class="px-3 py-1.5 rounded-lg bg-[#162235] border border-[#243247] hover:bg-[#1f2e44] text-[#94A3B8] hover:text-white font-bold text-xs transition font-mono">
                Matrix View
            </button>
        <?php endif; ?>
    </div>

    <!-- Standings Table -->
    <div class="overflow-x-auto rounded-xl border border-[#243247] bg-[#162235] shadow-sm">
        <table class="w-full text-left text-sm" id="pickemTable">
            <thead>
                <tr class="border-b border-[#243247] bg-[#0B1626] text-[11px] font-mono uppercase tracking-wider text-[#94A3B8]">
                    <th class="py-3 px-4 text-center w-14">Rank</th>
                    <th class="py-3 px-4">Participant</th>
                    <th class="py-3 px-4 text-center">Entry</th>
                    <th class="py-3 px-4 text-center">Correct</th>
                    <th class="py-3 px-4 text-center">Tiebreaker Pred / Delta</th>
                    <th class="py-3 px-4 text-center">Selections</th>
                    <th class="py-3 px-4 text-right">Prize Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#243247] text-xs">
                <?php if (empty($standings)): ?>
                    <tr>
                        <td colspan="7" class="py-8 text-center text-[#94A3B8] italic">No entries recorded for Week <?= htmlspecialchars((string) $week) ?> yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($standings as $row): ?>
                        <?php
                        $isWinner = in_array($row['username'], array_column($pot['winners'] ?? [], 'username'), true);
                        $canSeeThisRow = $canViewOpponentPicks || (($user['id'] ?? 0) === $row['user_id']);
                        ?>
                        <tr class="transition pickem-row <?= $isWinner ? 'bg-[#EAB308]/10' : 'hover:bg-[#1f2e44]/40' ?>"
                            data-tier="<?= $row['is_paid'] ? 'cash' : 'free' ?>">
                            <td class="py-3.5 px-4 text-center font-mono font-bold text-[#F8FAFC]">
                                <?php if ($isWinner): ?>
                                    <span class="px-2 py-0.5 rounded bg-[#EAB308] text-[#0B1626] text-[10px] font-black">#1</span>
                                <?php else: ?>
                                    #<?= $row['rank'] ?>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 font-bold text-[#F8FAFC]">
                                <?= htmlspecialchars($row['username']) ?>
                                <?php if (($user['id'] ?? 0) === $row['user_id']): ?>
                                    <span class="text-[10px] ml-1 px-1.5 py-0.2 rounded bg-[#0B1626] text-[#94A3B8] border border-[#243247] font-mono">You</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <?php if ($row['is_paid']): ?>
                                    <span class="inline-block text-[10px] font-bold font-mono px-2 py-0.5 rounded bg-emerald-950/80 text-emerald-400 border border-emerald-500/40 uppercase">
                                        Cash ($10)
                                    </span>
                                <?php else: ?>
                                    <span class="inline-block text-[10px] font-bold font-mono px-2 py-0.5 rounded bg-[#0B1626] text-[#94A3B8] border border-[#243247] uppercase">
                                        Free
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-center font-mono tabular-nums font-bold text-emerald-400">
                                <?= $row['correct_picks'] ?> <span class="text-[11px] text-[#94A3B8] font-normal">/ <?= $row['total_graded'] ?></span>
                            </td>
                            <td class="py-3.5 px-4 text-center font-mono tabular-nums text-xs">
                                <?php if ($row['predicted_mnf'] !== null): ?>
                                    <span class="text-[#F8FAFC] font-bold"><?= $row['predicted_mnf'] ?> pts</span>
                                    <?php if ($row['tiebreaker_delta'] !== null): ?>
                                        <span class="text-[#EAB308] ml-1">(&Delta; <?= $row['tiebreaker_delta'] ?>)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-slate-600">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <?php if ($canSeeThisRow): ?>
                                    <button type="button" onclick="toggleUserPicks(<?= $row['entry_id'] ?>)" 
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-[#0B1626] hover:bg-[#1f2e44] text-[#F8FAFC] border border-[#243247] text-[11px] font-semibold transition font-mono">
                                        <span id="btnText-<?= $row['entry_id'] ?>">View Picks</span>
                                        <span class="text-[10px] text-[#94A3B8]">(<?= $row['total_picks'] ?>)</span>
                                    </button>
                                <?php else: ?>
                                    <span class="text-[#94A3B8] font-mono text-xs">LOCKED</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <?php if ($isWinner): ?>
                                    <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded bg-[#EAB308] text-[#0B1626] uppercase">
                                        Cash Winner
                                    </span>
                                <?php elseif ($row['is_paid']): ?>
                                    <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded bg-emerald-950/80 text-emerald-400 border border-emerald-500/40 uppercase">
                                        Verified
                                    </span>
                                <?php else: ?>
                                    <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded bg-[#0B1626] text-[#94A3B8] border border-[#243247] uppercase">
                                        Free Entry
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Accordion Row with Detailed Picks -->
                        <?php if ($canSeeThisRow): ?>
                            <tr id="picksRow-<?= $row['entry_id'] ?>" class="hidden bg-[#0B1626] border-b border-[#243247]">
                                <td colspan="7" class="p-4">
                                    <div class="rounded-lg border border-[#243247] bg-[#162235] p-3.5 space-y-3">
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-[#243247] pb-2 text-xs">
                                            <div class="flex items-center gap-2 flex-wrap font-mono">
                                                <span class="font-bold text-[#F8FAFC]"><?= htmlspecialchars($row['username']) ?>'s Selections:</span>
                                                <span class="px-2 py-0.5 rounded bg-emerald-950/80 text-emerald-400 border border-emerald-500/40 text-[10px] font-bold tabular-nums">
                                                    <?= $row['correct_picks'] ?> Correct
                                                </span>
                                                <span class="px-2 py-0.5 rounded bg-rose-950/80 text-rose-400 border border-rose-500/40 text-[10px] font-bold tabular-nums">
                                                    <?= $row['total_graded'] - $row['correct_picks'] ?> Missed
                                                </span>
                                                <span class="px-2 py-0.5 rounded bg-[#0B1626] text-[#94A3B8] border border-[#243247] text-[10px] font-bold tabular-nums">
                                                    <?= $row['pending_picks'] ?> Pending
                                                </span>
                                            </div>
                                            <?php if ($row['predicted_mnf'] !== null): ?>
                                                <div class="text-[11px] font-mono text-[#EAB308] bg-[#0B1626] border border-[#243247] px-2 py-0.5 rounded tabular-nums">
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
                                                $isRevealed = !empty($pd['is_revealed']);
                                                $selected = $pd['selected_team'];
                                                $selData = $selected ? TeamData::get($selected) : null;
                                                ?>
                                                <div class="p-2 rounded-lg border text-center flex flex-col justify-between items-center bg-[#0B1626] <?= $isCorrect ? 'border-emerald-500/50' : ($isIncorrect ? 'border-rose-500/50' : ($isLive ? 'border-amber-500/50' : 'border-[#243247]')) ?>">
                                                    <span class="text-[9px] font-mono text-[#94A3B8] block truncate w-full">
                                                        <?= $pd['away_team'] ?> @ <?= $pd['home_team'] ?>
                                                    </span>

                                                    <?php if (!$isRevealed): ?>
                                                        <div class="my-2 py-1 px-2 rounded bg-[#162235] border border-[#243247] text-[10px] font-mono text-[#94A3B8]">
                                                            LOCKED
                                                        </div>
                                                        <span class="text-[9px] font-mono text-[#94A3B8]">Before Kickoff</span>
                                                    <?php elseif ($selData): ?>
                                                        <img src="<?= htmlspecialchars($selData['logo']) ?>" alt="<?= $selected ?>" class="w-6 h-6 object-contain my-1">
                                                        <span class="text-xs font-bold text-[#F8FAFC] font-mono"><?= $selected ?></span>
                                                        <div class="mt-1">
                                                            <?php if ($isCorrect): ?>
                                                                <span class="text-[9px] font-mono font-bold text-emerald-400">WIN (+1)</span>
                                                            <?php elseif ($isIncorrect): ?>
                                                                <span class="text-[9px] font-mono font-bold text-rose-400">LOSS (0)</span>
                                                            <?php elseif ($isLive): ?>
                                                                <span class="text-[9px] font-mono font-bold text-[#EAB308]">LIVE</span>
                                                            <?php else: ?>
                                                                <span class="text-[9px] font-mono text-[#94A3B8]">PENDING</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-xs text-slate-600 font-mono my-2">—</span>
                                                        <span class="text-[9px] font-mono text-[#94A3B8]">UNPICKED</span>
                                                    <?php endif; ?>
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
        <div class="bg-[#162235] border border-[#243247] rounded-xl max-w-6xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <div class="p-4 border-b border-[#243247] bg-[#0B1626] flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-[#F8FAFC]">Week <?= $week ?> Picks Matrix</h3>
                    <span class="text-xs text-[#94A3B8]">League-wide selections overview</span>
                </div>
                <button type="button" onclick="closePicksMatrixModal()" class="text-[#94A3B8] hover:text-white text-2xl font-bold leading-none p-2">&times;</button>
            </div>
            <div class="p-4 overflow-auto flex-1">
                <table class="w-full text-xs text-left border-collapse">
                    <thead>
                        <tr class="border-b border-[#243247] bg-[#0B1626] font-mono text-[10px] uppercase text-[#94A3B8]">
                            <th class="py-2.5 px-3 sticky left-0 bg-[#0B1626] z-10">Participant</th>
                            <th class="py-2.5 px-2 text-center">Score</th>
                            <?php foreach ($games as $g): ?>
                                <th class="py-2.5 px-2 text-center min-w-[75px]">
                                    <span class="block truncate font-bold"><?= $g['away_team'] ?> @ <?= $g['home_team'] ?></span>
                                    <span class="text-[9px] text-[#94A3B8] tabular-nums"><?= $g['status'] === 'final' ? "({$g['away_score']}-{$g['home_score']})" : ($g['status'] === 'in_progress' ? 'Live' : 'Sched') ?></span>
                                </th>
                            <?php endforeach; ?>
                            <th class="py-2.5 px-2 text-center">Tiebreaker</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#243247] font-mono text-xs">
                        <?php foreach ($standings as $row): ?>
                            <tr class="hover:bg-[#1f2e44]/40">
                                <td class="py-2 px-3 font-bold text-[#F8FAFC] sticky left-0 bg-[#162235] z-10 truncate">
                                    <?= htmlspecialchars($row['username']) ?>
                                    <?php if (($user['id'] ?? 0) === $row['user_id']): ?>
                                        <span class="text-[9px] px-1 py-0.2 rounded bg-[#0B1626] text-[#94A3B8] border border-[#243247]">You</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 px-2 text-center text-emerald-400 font-bold tabular-nums">
                                    <?= $row['correct_picks'] ?>/<?= $row['total_graded'] ?>
                                </td>
                                <?php foreach ($games as $g): ?>
                                    <?php
                                    $pd = $row['picks_detail'][$g['id']] ?? null;
                                    $isRev = !empty($pd['is_revealed']);
                                    $sel = $pd['selected_team'] ?? null;
                                    $res = $pd['result'] ?? 'pending';
                                    $cellClass = "bg-[#0B1626] text-[#94A3B8] border-[#243247]";
                                    if ($res === 'correct') {
                                        $cellClass = "bg-emerald-950 text-emerald-400 border-emerald-500/40 font-bold";
                                    } elseif ($res === 'incorrect') {
                                        $cellClass = "bg-rose-950 text-rose-400 border-rose-500/40";
                                    }
                                    ?>
                                    <td class="py-1.5 px-2 text-center">
                                        <?php if (!$isRev): ?>
                                            <span class="text-[10px] text-[#94A3B8]">LOCKED</span>
                                        <?php elseif ($sel): ?>
                                            <span class="inline-block px-1.5 py-0.5 rounded border text-[10px] <?= $cellClass ?>">
                                                <?= $sel ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-600">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="py-2 px-2 text-center text-[#EAB308] font-bold tabular-nums">
                                    <?= $row['predicted_mnf'] !== null ? $row['predicted_mnf'] : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-3.5 border-t border-[#243247] bg-[#0B1626] flex justify-end">
                <button type="button" onclick="closePicksMatrixModal()" class="px-4 py-2 rounded-lg bg-[#162235] hover:bg-[#1f2e44] text-[#F8FAFC] font-bold text-xs uppercase tracking-wider transition border border-[#243247]">
                    Close
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function filterPickem(tier) {
    document.querySelectorAll('.pickem-tab').forEach(el => {
        el.className = 'pickem-tab px-3 py-1.5 text-xs font-bold rounded-lg bg-[#162235] border border-[#243247] text-[#94A3B8] hover:text-white transition';
    });
    const activeBtn = document.getElementById('pickem-tab-' + tier);
    if (activeBtn) {
        activeBtn.className = 'pickem-tab px-3 py-1.5 text-xs font-bold rounded-lg bg-[#EAB308] text-[#0B1626] transition';
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

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closePicksMatrixModal();
    }
});
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
