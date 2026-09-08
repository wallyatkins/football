<?php
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
$potEstimate = $paidCount * 10.00;
?>

<div class="space-y-6">

    <!-- Header & Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800 pb-5">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-mono px-2 py-0.5 rounded bg-purple-500/10 text-purple-300 border border-purple-500/30">Commissioner Portal</span>
                <span class="text-xs text-slate-400">Season <?= htmlspecialchars((string) $season) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">Payment & Roster Management</h1>
        </div>

        <div class="flex items-center gap-3">
            <form action="/admin/sync" method="POST" class="inline">
                <input type="hidden" name="season_year" value="<?= htmlspecialchars((string) $season) ?>">
                <input type="hidden" name="week_number" value="<?= htmlspecialchars((string) $week) ?>">
                <button type="submit" class="px-4 py-2 text-xs font-bold rounded-lg bg-slate-900 border border-slate-700 text-slate-200 hover:bg-slate-800 transition flex items-center gap-2">
                    <span>🔄</span>
                    <span>Sync Scores (ESPN API)</span>
                </button>
            </form>

            <!-- Week Nav Dropdown -->
            <div class="flex items-center gap-1.5 overflow-x-auto">
                <?php for ($w = 1; $w <= 18; $w++): ?>
                    <a href="/admin/payments?week=<?= $w ?>&season=<?= $season ?>" 
                       class="px-2.5 py-1 text-xs font-bold rounded transition <?= $w === $week ? 'bg-purple-600 text-white shadow-md' : 'bg-slate-900 border border-slate-800 text-slate-400 hover:text-white' ?>">
                        W<?= $w ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- Summary Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-5 rounded-xl bg-slate-900/60 border border-slate-800">
            <span class="text-xs font-semibold text-slate-400 block mb-1">Estimated Prize Pot</span>
            <div class="text-3xl font-black text-amber-400 font-mono">$<?= number_format($potEstimate, 2) ?></div>
            <span class="text-[11px] text-slate-500 mt-1 block">Based on $10.00 / verified entry</span>
        </div>

        <div class="p-5 rounded-xl bg-slate-900/60 border border-slate-800">
            <span class="text-xs font-semibold text-slate-400 block mb-1">Verified Entries</span>
            <div class="text-3xl font-black text-emerald-400 font-mono"><?= $paidCount ?></div>
            <span class="text-[11px] text-slate-500 mt-1 block">Paid or fee-exempt</span>
        </div>

        <div class="p-5 rounded-xl bg-slate-900/60 border border-slate-800">
            <span class="text-xs font-semibold text-slate-400 block mb-1">Pending Verification</span>
            <div class="text-3xl font-black text-rose-400 font-mono"><?= $pendingCount ?></div>
            <span class="text-[11px] text-slate-500 mt-1 block">Awaiting Cash App / Venmo check</span>
        </div>
    </div>

    <!-- Pick'em Entries Table -->
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <span>🎯</span>
                <span>Week <?= htmlspecialchars((string) $week) ?> Pick'em Submissions (<?= count($entries) ?>)</span>
            </h3>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900/60 shadow-xl">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-800 bg-slate-900/80 text-[11px] font-mono uppercase tracking-wider text-slate-400">
                        <th class="py-3 px-4">User</th>
                        <th class="py-3 px-4 text-center">Picks</th>
                        <th class="py-3 px-4 text-center">MNF Total</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4">Audit Details</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($entries)): ?>
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-500 italic">No entries submitted for this week yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($entries as $e): ?>
                            <tr class="transition hover:bg-slate-800/30">
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
                                    <?php if ($e['payment_status'] === 'paid'): ?>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                            ✓ PAID
                                        </span>
                                    <?php elseif ($e['payment_status'] === 'exempt'): ?>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2 py-0.5 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30">
                                            EXEMPT
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                            PENDING
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-400">
                                    <?php if ($e['payment_verified_at']): ?>
                                        <span>Verified <?= date('M j, g:i A', strtotime($e['payment_verified_at'])) ?> by <?= htmlspecialchars($e['verified_by_username'] ?? 'Admin') ?></span>
                                    <?php else: ?>
                                        <span class="text-slate-600">Submitted <?= date('M j, g:i A', strtotime($e['created_at'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <form action="/admin/payments/toggle" method="POST" class="inline-flex items-center gap-1.5">
                                        <input type="hidden" name="type" value="pickem">
                                        <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                        <input type="hidden" name="season_year" value="<?= $season ?>">
                                        <input type="hidden" name="week_number" value="<?= $week ?>">

                                        <?php if ($e['payment_status'] !== 'paid'): ?>
                                            <button type="submit" name="status" value="paid" class="px-2.5 py-1 text-[11px] font-bold rounded bg-emerald-600 hover:bg-emerald-500 text-white transition">
                                                Mark Paid
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" name="status" value="pending" class="px-2.5 py-1 text-[11px] font-bold rounded bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white border border-slate-700 transition">
                                                Set Pending
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($e['payment_status'] !== 'exempt'): ?>
                                            <button type="submit" name="status" value="exempt" class="px-2 py-1 text-[11px] font-bold rounded bg-slate-900 hover:bg-slate-800 text-purple-300 border border-purple-900/50 transition">
                                                Exempt
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
