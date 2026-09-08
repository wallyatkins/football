<?php
use WallyFootball\Support\TeamData;

ob_start();

$paidCount = 0;
$pendingCount = 0;
foreach ($entries as $e) {
    if (in_array($e['payment_status'], ['paid', 'exempt'], true)) {
        $paidCount++;
    } else {
        $pendingCount++;
    }
}
$pickemPot = $paidCount * 10.00;

$survivorPaidCount = 0;
$survivorAliveCount = 0;
$survivorElimCount = 0;
$survivorNotEnteredCount = 0;

foreach ($survivorRoster as $s) {
    $isSvrPaid = in_array($s['survivor_payment_status'], ['paid', 'exempt'], true);
    if ($isSvrPaid) {
        $survivorPaidCount++;
        if (!empty($s['is_eliminated'])) {
            $survivorElimCount++;
        } else {
            $survivorAliveCount++;
        }
    } else {
        $survivorNotEnteredCount++;
    }
}
$survivorPot = $survivorPaidCount * 10.00;
?>

<div class="space-y-8">

    <!-- Header & Global Commissioner Controls -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5 border-b border-slate-800 pb-5">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <span class="text-xs font-mono font-bold px-2 py-0.5 rounded bg-purple-500/15 text-purple-300 border border-purple-500/30 uppercase tracking-wider">Commissioner Command Center</span>
                <span class="text-xs text-slate-400">Season <?= htmlspecialchars((string) $season) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white flex items-center gap-3">
                <span>Wally's Commissioner Portal</span>
                <span class="text-xs font-mono font-bold px-2.5 py-1 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/40">
                    Week <?= $week ?>
                </span>
            </h1>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <!-- Sync Scores -->
            <form action="/admin/sync" method="POST" class="inline">
                <input type="hidden" name="season_year" value="<?= htmlspecialchars((string) $season) ?>">
                <input type="hidden" name="week_number" value="<?= htmlspecialchars((string) $week) ?>">
                <button type="submit" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-900 border border-slate-700 text-slate-200 hover:bg-slate-800 transition flex items-center gap-2 shadow-sm">
                    <span>🔄</span>
                    <span>Sync ESPN Scores</span>
                </button>
            </form>

            <!-- Auto-Grade Survivor Week -->
            <form action="/admin/survivor/grade" method="POST" class="inline" onsubmit="return confirm('Grade Survivor picks for Week <?= $week ?> based on final scores? Losing picks will be marked eliminated.');">
                <input type="hidden" name="season_year" value="<?= htmlspecialchars((string) $season) ?>">
                <input type="hidden" name="week_number" value="<?= htmlspecialchars((string) $week) ?>">
                <button type="submit" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-900 border border-emerald-500/40 text-emerald-300 hover:bg-emerald-950/40 transition flex items-center gap-2 shadow-sm">
                    <span>⚡</span>
                    <span>Grade Survivor Wk <?= $week ?></span>
                </button>
            </form>

            <!-- Week Switcher Dropdown -->
            <div class="flex items-center gap-1 overflow-x-auto p-1 bg-slate-900/90 rounded-xl border border-slate-800">
                <?php for ($w = 1; $w <= 18; $w++): ?>
                    <a href="/admin/payments?week=<?= $w ?>&season=<?= $season ?>" 
                       class="px-2 py-1 text-xs font-mono font-bold rounded-lg transition <?= $w === $week ? 'bg-purple-600 text-white shadow-md' : 'text-slate-400 hover:text-white' ?>">
                        W<?= $w ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- Designated Tiebreaker Game Control -->
    <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-amber-950/30 border border-amber-500/40 shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-5">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/30 uppercase tracking-wider">
                    🎲 Random Tiebreaker Game
                </span>
                <span class="text-xs text-slate-400">Week <?= $week ?> Designated Contest</span>
            </div>
            <?php if (!empty($tiebreakerGame)): ?>
                <?php
                $tbAway = TeamData::get($tiebreakerGame['away_team']);
                $tbHome = TeamData::get($tiebreakerGame['home_team']);
                $tbKickoff = (new DateTimeImmutable($tiebreakerGame['kickoff_time']))
                    ->setTimezone(new DateTimeZone('America/New_York'))->format('D, M j @ g:i A T');
                ?>
                <div class="flex items-center gap-3 my-2 flex-wrap">
                    <div class="flex items-center gap-2">
                        <img src="<?= htmlspecialchars($tbAway['logo']) ?>" alt="<?= htmlspecialchars($tbAway['name']) ?>" class="w-8 h-8 object-contain">
                        <span class="font-black text-white text-base sm:text-lg"><?= htmlspecialchars($tbAway['name']) ?></span>
                    </div>
                    <span class="text-slate-500 font-black text-sm">@</span>
                    <div class="flex items-center gap-2">
                        <img src="<?= htmlspecialchars($tbHome['logo']) ?>" alt="<?= htmlspecialchars($tbHome['name']) ?>" class="w-8 h-8 object-contain">
                        <span class="font-black text-white text-base sm:text-lg"><?= htmlspecialchars($tbHome['name']) ?></span>
                    </div>
                </div>
                <div class="text-xs text-slate-400">
                    <span class="font-mono text-amber-300 font-bold"><?= htmlspecialchars($tbKickoff) ?></span>
                    <span class="mx-1 text-slate-600">&bull;</span>
                    Status: <span class="uppercase font-mono font-bold text-slate-300"><?= htmlspecialchars($tiebreakerGame['status']) ?></span>
                    <?php if ($tiebreakerGame['status'] === 'final'): ?>
                        <span class="ml-1 font-mono text-emerald-400 font-bold">(Final: <?= $tiebreakerGame['away_score'] ?> - <?= $tiebreakerGame['home_score'] ?>, Total: <?= (int)$tiebreakerGame['home_score'] + (int)$tiebreakerGame['away_score'] ?> pts)</span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="text-amber-400 font-bold text-sm">No tiebreaker game designated for this week yet.</div>
            <?php endif; ?>
        </div>

        <div class="flex items-center gap-3 shrink-0">
            <form action="/admin/tiebreaker/randomize" method="POST" onsubmit="return confirm('Randomly re-roll the tiebreaker game for Week <?= $week ?>? All players will predict total points for the newly selected matchup.');">
                <input type="hidden" name="season_year" value="<?= htmlspecialchars((string) $season) ?>">
                <input type="hidden" name="week_number" value="<?= htmlspecialchars((string) $week) ?>">
                <button type="submit" class="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs uppercase tracking-wider transition shadow-lg flex items-center gap-2">
                    <span>🎲</span>
                    <span>Re-roll Tiebreaker Game</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Summary Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Pick'em Pot -->
        <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-amber-950/20 border border-amber-500/30 shadow-lg">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-bold text-slate-400">Week <?= $week ?> Pick'em Pot</span>
                <span class="text-xs font-mono px-2 py-0.5 rounded bg-amber-500/20 text-amber-300">$10/entry</span>
            </div>
            <div class="text-3xl font-black text-amber-400 font-mono">$<?= number_format($pickemPot, 2) ?></div>
            <span class="text-[11px] text-slate-400 mt-1 block"><?= $paidCount ?> verified / <?= count($entries) ?> total submissions</span>
        </div>

        <!-- Survivor Pot -->
        <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-emerald-950/20 border border-emerald-500/30 shadow-lg">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-bold text-slate-400">Season Survivor Pot</span>
                <span class="text-xs font-mono px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300">$10 upfront</span>
            </div>
            <div class="text-3xl font-black text-emerald-400 font-mono">$<?= number_format($survivorPot, 2) ?></div>
            <span class="text-[11px] text-slate-400 mt-1 block"><?= $survivorPaidCount ?> active participants</span>
        </div>

        <!-- Pick'em Verification Status -->
        <div class="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-lg">
            <span class="text-xs font-bold text-slate-400 block mb-1">Pick'em Unpaid Alerts</span>
            <div class="text-3xl font-black <?= $pendingCount > 0 ? 'text-rose-400' : 'text-slate-400' ?> font-mono">
                <?= $pendingCount ?>
            </div>
            <span class="text-[11px] text-slate-400 mt-1 block">Awaiting CashApp / Venmo / PayPal check</span>
        </div>

        <!-- Survivor Status -->
        <div class="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-lg">
            <span class="text-xs font-bold text-slate-400 block mb-1">Survivor Pool Census</span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-black text-emerald-400 font-mono"><?= $survivorAliveCount ?></span>
                <span class="text-xs text-slate-400">Alive</span>
                <span class="text-slate-600">&bull;</span>
                <span class="text-xl font-bold text-rose-400 font-mono"><?= $survivorElimCount ?></span>
                <span class="text-xs text-slate-400">Out</span>
                <span class="text-slate-600">&bull;</span>
                <span class="text-sm font-bold text-slate-500 font-mono"><?= $survivorNotEnteredCount ?></span>
                <span class="text-xs text-slate-500">Unpaid</span>
            </div>
            <span class="text-[11px] text-slate-500 mt-1 block">Total pool roster</span>
        </div>
    </div>

    <!-- Section 1: Weekly Pick'em Submissions -->
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <span class="text-lg">🎯</span>
                <h2 class="text-lg font-bold text-white">Week <?= htmlspecialchars((string) $week) ?> Pick'em Entries (<?= count($entries) ?>)</h2>
            </div>

            <!-- Quick Filter Tabs -->
            <div class="flex items-center gap-2 text-xs">
                <button type="button" onclick="filterPickem('all')" id="btnFilterAll" class="px-3 py-1 rounded-lg bg-purple-600 text-white font-bold transition">All</button>
                <button type="button" onclick="filterPickem('pending')" id="btnFilterPending" class="px-3 py-1 rounded-lg bg-slate-800 text-amber-300 hover:bg-slate-700 font-bold border border-slate-700 transition">Unpaid (<?= $pendingCount ?>)</button>
                <button type="button" onclick="filterPickem('paid')" id="btnFilterPaid" class="px-3 py-1 rounded-lg bg-slate-800 text-emerald-300 hover:bg-slate-700 font-bold border border-slate-700 transition">Paid (<?= $paidCount ?>)</button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-800 bg-slate-900/60 shadow-xl">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-800 bg-slate-950/70 text-[11px] font-mono uppercase tracking-wider text-slate-400">
                        <th class="py-3.5 px-4">User</th>
                        <th class="py-3.5 px-4 text-center">Picks Made</th>
                        <th class="py-3.5 px-4 text-center">Tiebreaker (Pts)</th>
                        <th class="py-3.5 px-4 text-center">Pick Status</th>
                        <th class="py-3.5 px-4 text-center">Payment Stake</th>
                        <th class="py-3.5 px-4">Verification Audit</th>
                        <th class="py-3.5 px-4 text-right">Commissioner Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60" id="pickemTableBody">
                    <?php if (empty($entries)): ?>
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500 italic">No entries submitted for Week <?= $week ?> yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($entries as $e): ?>
                            <?php 
                            $isEntryPaid = in_array($e['payment_status'], ['paid', 'exempt'], true);
                            $isEntryLocked = !empty($e['is_locked']);
                            ?>
                            <tr class="pickem-row transition hover:bg-slate-800/30" data-status="<?= $e['payment_status'] ?>">
                                <td class="py-3.5 px-4 font-semibold text-white">
                                    <div><?= htmlspecialchars($e['username']) ?></div>
                                    <div class="text-[11px] text-slate-400 font-normal"><?= htmlspecialchars($e['email']) ?></div>
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono text-xs">
                                    <span class="px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700 font-bold">
                                        <?= $e['pick_count'] ?> / 16
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono text-xs font-bold text-amber-300">
                                    <?= $e['mnf_total_points_prediction'] !== null ? $e['mnf_total_points_prediction'] . ' pts' : '—' ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($isEntryLocked): ?>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                            🔒 LOCKED
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2.5 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700">
                                            📝 DRAFT
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($e['payment_status'] === 'paid'): ?>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                            ✓ PAID ($10)
                                        </span>
                                    <?php elseif ($e['payment_status'] === 'exempt'): ?>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2.5 py-0.5 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30">
                                            EXEMPT
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 animate-pulse">
                                            UNPAID ($10)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-400">
                                    <?php if ($e['payment_verified_at']): ?>
                                        <span class="text-emerald-400/90 font-medium">Verified <?= date('M j, g:i A', strtotime($e['payment_verified_at'])) ?> by <?= htmlspecialchars($e['verified_by_username'] ?? 'Wally') ?></span>
                                    <?php else: ?>
                                        <span class="text-slate-500">Submitted <?= date('M j, g:i A', strtotime($e['created_at'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="inline-flex items-center gap-1.5 flex-wrap justify-end">
                                        <!-- Payment Toggle -->
                                        <form action="/admin/payments/toggle" method="POST" class="inline">
                                            <input type="hidden" name="type" value="pickem">
                                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                            <input type="hidden" name="season_year" value="<?= $season ?>">
                                            <input type="hidden" name="week_number" value="<?= $week ?>">

                                            <?php if ($e['payment_status'] !== 'paid'): ?>
                                                <button type="submit" name="status" value="paid" class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white transition shadow-sm">
                                                    Mark Paid
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="status" value="pending" class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition">
                                                    Set Unpaid
                                                </button>
                                            <?php endif; ?>
                                        </form>

                                        <!-- Lock/Unlock Toggle -->
                                        <form action="/admin/lock/toggle" method="POST" class="inline">
                                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                            <input type="hidden" name="season_year" value="<?= $season ?>">
                                            <input type="hidden" name="week_number" value="<?= $week ?>">

                                            <?php if ($isEntryLocked): ?>
                                                <button type="submit" name="locked" value="0" class="px-2 py-1 text-[11px] font-bold rounded-lg bg-slate-800 hover:bg-amber-500/20 text-amber-300 border border-amber-500/40 transition" title="Unlock picks so player can edit">
                                                    🔓 Unlock
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="locked" value="1" class="px-2 py-1 text-[11px] font-bold rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 border border-slate-700 transition" title="Force lock picks">
                                                    🔒 Lock
                                                </button>
                                            <?php endif; ?>
                                        </form>

                                        <!-- Reset / Clear Picks -->
                                        <form action="/admin/picks/reset" method="POST" class="inline" onsubmit="return confirm('Delete and reset picks for <?= htmlspecialchars($e['username']) ?> in Week <?= $week ?>? This allows them to submit a completely fresh slate of picks.');">
                                            <input type="hidden" name="entry_id" value="<?= $e['id'] ?>">
                                            <input type="hidden" name="season_year" value="<?= $season ?>">
                                            <input type="hidden" name="week_number" value="<?= $week ?>">
                                            <button type="submit" class="px-2 py-1 text-[11px] font-bold rounded-lg bg-slate-800 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 border border-slate-700 hover:border-rose-500/40 transition" title="Delete entry and picks for this week">
                                                🗑️ Reset
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 2: Survivor Pool Management ($10 Upfront Entry) -->
    <div class="space-y-4 pt-4 border-t border-slate-800" id="survivor">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <span class="text-lg">🛡️</span>
                <h2 class="text-lg font-bold text-white">Survivor Pool Roster &amp; Upfront Entry ($10.00 Stake)</h2>
            </div>
            <div class="text-xs text-slate-400">
                Players must be verified by commissioner as <strong>Paid ($10)</strong> to make picks.
            </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-800 bg-slate-900/60 shadow-xl">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-800 bg-slate-950/70 text-[11px] font-mono uppercase tracking-wider text-slate-400">
                        <th class="py-3.5 px-4">Participant</th>
                        <th class="py-3.5 px-4 text-center">Survivor Status</th>
                        <th class="py-3.5 px-4 text-center">Week <?= $week ?> Pick</th>
                        <th class="py-3.5 px-4 text-center">Weeks Survived</th>
                        <th class="py-3.5 px-4 text-center">Entry Stake ($10)</th>
                        <th class="py-3.5 px-4">Audit Details</th>
                        <th class="py-3.5 px-4 text-right">Commissioner Controls</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($survivorRoster)): ?>
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500 italic">No registered users in the pool yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($survivorRoster as $s): ?>
                            <?php
                            $isSvrPaid = in_array($s['survivor_payment_status'], ['paid', 'exempt'], true);
                            $isElim = !empty($s['is_eliminated']);
                            $weekPick = $s['current_week_pick'];
                            $teamData = $weekPick ? TeamData::get($weekPick) : null;
                            ?>
                            <tr class="transition hover:bg-slate-800/30">
                                <td class="py-3.5 px-4 font-semibold text-white">
                                    <div><?= htmlspecialchars($s['username']) ?></div>
                                    <div class="text-[11px] text-slate-400 font-normal"><?= htmlspecialchars($s['email']) ?></div>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <?php if (!$isSvrPaid): ?>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2.5 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700">
                                            NOT ENTERED
                                        </span>
                                    <?php elseif ($isElim): ?>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2.5 py-0.5 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                            ☠️ OUT (Wk <?= $s['elimination_week'] ?? $week ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                            🛡️ ALIVE
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($weekPick && $teamData): ?>
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-800 border border-slate-700">
                                            <img src="<?= htmlspecialchars($teamData['logo']) ?>" alt="<?= htmlspecialchars($teamData['name']) ?>" class="w-5 h-5 object-contain">
                                            <span class="font-mono font-bold text-xs text-white"><?= htmlspecialchars($weekPick) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-500 italic">No pick yet</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono font-bold text-xs text-slate-300">
                                    <?= (int) $s['total_weeks_picked'] ?> wks
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($isSvrPaid): ?>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                            ✓ PAID ($10)
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                            UNPAID
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-400">
                                    <?php if ($s['payment_verified_at']): ?>
                                        <span class="text-emerald-400 font-medium">Verified <?= date('M j, g:i A', strtotime($s['payment_verified_at'])) ?> by <?= htmlspecialchars($s['verified_by_username'] ?? 'Wally') ?></span>
                                    <?php else: ?>
                                        <span class="text-slate-500">Awaiting payment note: Survivor - <?= htmlspecialchars($s['username']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="inline-flex items-center gap-1.5 flex-wrap justify-end">
                                        <!-- Payment Toggle -->
                                        <form action="/admin/survivor/toggle" method="POST" class="inline">
                                            <input type="hidden" name="user_id" value="<?= $s['user_id'] ?>">
                                            <input type="hidden" name="season_year" value="<?= $season ?>">
                                            <input type="hidden" name="week_number" value="<?= $week ?>">

                                            <?php if (!$isSvrPaid): ?>
                                                <button type="submit" name="status" value="paid" class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white transition shadow-sm">
                                                    ✓ Mark Paid ($10)
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="status" value="unpaid" class="px-2 py-1 text-[11px] font-bold rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white border border-slate-700 transition">
                                                    Mark Unpaid
                                                </button>
                                            <?php endif; ?>
                                        </form>

                                        <!-- Elimination Manual Override -->
                                        <?php if ($isSvrPaid): ?>
                                            <form action="/admin/survivor/eliminate" method="POST" class="inline">
                                                <input type="hidden" name="user_id" value="<?= $s['user_id'] ?>">
                                                <input type="hidden" name="season_year" value="<?= $season ?>">
                                                <input type="hidden" name="week_number" value="<?= $week ?>">

                                                <?php if (!$isElim): ?>
                                                    <button type="submit" name="eliminate" value="1" class="px-2 py-1 text-[11px] font-bold rounded-lg bg-slate-800 hover:bg-rose-500/20 text-rose-300 border border-rose-900/50 transition" title="Manual knock out">
                                                        Eliminate
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" name="eliminate" value="0" class="px-2 py-1 text-[11px] font-bold rounded-lg bg-slate-800 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-900/50 transition" title="Revive back to alive">
                                                        Revive
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function filterPickem(type) {
    const rows = document.querySelectorAll('.pickem-row');
    const btnAll = document.getElementById('btnFilterAll');
    const btnPending = document.getElementById('btnFilterPending');
    const btnPaid = document.getElementById('btnFilterPaid');

    // Reset button styles
    [btnAll, btnPending, btnPaid].forEach(b => {
        b.className = 'px-3 py-1 rounded-lg bg-slate-800 text-slate-300 hover:bg-slate-700 font-bold border border-slate-700 transition';
    });

    if (type === 'all') {
        btnAll.className = 'px-3 py-1 rounded-lg bg-purple-600 text-white font-bold transition shadow';
        rows.forEach(r => r.style.display = '');
    } else if (type === 'pending') {
        btnPending.className = 'px-3 py-1 rounded-lg bg-amber-500 text-slate-950 font-bold transition shadow';
        rows.forEach(r => {
            const st = r.getAttribute('data-status');
            r.style.display = (st === 'pending') ? '' : 'none';
        });
    } else if (type === 'paid') {
        btnPaid.className = 'px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold transition shadow';
        rows.forEach(r => {
            const st = r.getAttribute('data-status');
            r.style.display = (st === 'paid' || st === 'exempt') ? '' : 'none';
        });
    }
}
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
