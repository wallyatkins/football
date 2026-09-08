<?php
ob_start();
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

        <!-- Week Nav Buttons -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 max-w-full">
            <?php for ($w = 1; $w <= 18; $w++): ?>
                <a href="/pickem/standings?week=<?= $w ?>&season=<?= $season ?>" 
                   class="px-3 py-1 text-xs font-bold rounded-lg transition <?= $w === $week ? 'bg-amber-500 text-slate-950 shadow-md' : 'bg-slate-900 border border-slate-800 text-slate-400 hover:text-white hover:bg-slate-800' ?>">
                    W<?= $w ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>

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

    <!-- Standings Table -->
    <div class="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900/60 shadow-xl">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-slate-800 bg-slate-900/80 text-[11px] font-mono uppercase tracking-wider text-slate-400">
                    <th class="py-3 px-4 text-center w-12">Rank</th>
                    <th class="py-3 px-4">Participant</th>
                    <th class="py-3 px-4 text-center">Correct Picks</th>
                    <th class="py-3 px-4 text-center">MNF Pred / Delta</th>
                    <th class="py-3 px-4 text-right">Payment Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                <?php if (empty($standings)): ?>
                    <tr>
                        <td colspan="5" class="py-8 text-center text-slate-500 italic">No entries recorded for Week <?= htmlspecialchars((string) $week) ?> yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($standings as $row): ?>
                        <?php
                        $isWinner = in_array($row['username'], array_column($pot['winners'] ?? [], 'username'), true);
                        ?>
                        <tr class="transition <?= $isWinner ? 'bg-amber-500/10 hover:bg-amber-500/15' : 'hover:bg-slate-800/30' ?>">
                            <td class="py-3.5 px-4 text-center font-mono font-bold text-slate-300">
                                <?= $isWinner ? '👑' : '#' . $row['rank'] ?>
                            </td>
                            <td class="py-3.5 px-4 font-semibold text-white">
                                <?= htmlspecialchars($row['username']) ?>
                                <?php if (($user['id'] ?? 0) === $row['user_id']): ?>
                                    <span class="text-[10px] ml-1.5 px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 border border-slate-700">You</span>
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
                            <td class="py-3.5 px-4 text-right">
                                <?php if ($row['is_paid']): ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                        ✓ Verified
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                        ⏱️ Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
