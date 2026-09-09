<?php
ob_start();
?>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800 pb-5">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-mono px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Survivor Pool</span>
                <span class="text-xs text-slate-400">Season <?= htmlspecialchars((string) $season) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">Survivor Leaderboard</h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="/fantasy/vault?tab=pools" 
               class="px-3.5 py-2 text-xs font-semibold rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-800 transition flex items-center gap-1.5"
               title="View historical results in the Dynasty Vault">
                <span>🏛️</span>
                <span>Historical Vault</span>
            </a>
            <a href="/survivor" class="px-4 py-2 text-xs font-bold rounded-lg bg-slate-900 border border-slate-800 text-slate-300 hover:text-white transition">
                &larr; Make Weekly Pick
            </a>
        </div>
    </div>

    <!-- Pot & Participation Overview Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-5 rounded-xl bg-slate-900/60 border border-slate-800">
            <span class="text-xs font-semibold text-slate-400 block mb-1">Season Cash Prize Pot</span>
            <div class="text-3xl font-black text-emerald-400 font-mono">$<?= number_format($pot['total_pot'] ?? 0, 2) ?></div>
            <span class="text-[11px] text-slate-500 mt-1 block"><?= $pot['cash_entries_count'] ?? 0 ?> verified cash entries @ $10.00</span>
        </div>

        <div class="p-5 rounded-xl bg-slate-900/60 border border-slate-800">
            <span class="text-xs font-semibold text-slate-400 block mb-1">Cash Contenders Alive</span>
            <div class="text-3xl font-black text-amber-400 font-mono"><?= $pot['alive_cash_count'] ?? 0 ?> <span class="text-xs font-normal text-slate-400">/ <?= $pot['cash_entries_count'] ?? 0 ?></span></div>
            <span class="text-[11px] text-emerald-400 font-mono mt-1 block">Competing for the $<?= number_format($pot['total_pot'] ?? 0, 2) ?> payout</span>
        </div>

        <div class="p-5 rounded-xl bg-slate-900/60 border border-slate-800">
            <span class="text-xs font-semibold text-slate-400 block mb-1">Free Tier Contenders Alive</span>
            <div class="text-3xl font-black text-sky-400 font-mono"><?= $pot['alive_free_count'] ?? 0 ?> <span class="text-xs font-normal text-slate-400">/ <?= $pot['free_entries_count'] ?? 0 ?></span></div>
            <span class="text-[11px] text-slate-400 mt-1 block">Playing for fun &amp; bragging rights</span>
        </div>
    </div>

    <!-- Tier Filter Tabs -->
    <div class="flex items-center gap-2 border-b border-slate-800 pb-2">
        <button type="button" onclick="filterSurvivor('all')" id="tab-all"
                class="survivor-tab px-3.5 py-1.5 text-xs font-bold rounded-lg bg-emerald-500 text-slate-950 transition shadow-sm">
            All Players (<?= count($standings) ?>)
        </button>
        <button type="button" onclick="filterSurvivor('cash')" id="tab-cash"
                class="survivor-tab px-3.5 py-1.5 text-xs font-bold rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-white hover:bg-slate-800 transition">
            🟢 Cash Prize Pool ($) (<?= $pot['cash_entries_count'] ?? 0 ?>)
        </button>
        <button type="button" onclick="filterSurvivor('free')" id="tab-free"
                class="survivor-tab px-3.5 py-1.5 text-xs font-bold rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-white hover:bg-slate-800 transition">
            🎮 Free / For Fun (<?= $pot['free_entries_count'] ?? 0 ?>)
        </button>
    </div>

    <!-- Survivor Grid Table -->
    <div class="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900/60 shadow-xl">
        <table class="w-full text-left text-sm" id="survivorTable">
            <thead>
                <tr class="border-b border-slate-800 bg-slate-900/80 text-[11px] font-mono uppercase tracking-wider text-slate-400">
                    <th class="py-3 px-4">Participant</th>
                    <th class="py-3 px-4 text-center">Pool Tier</th>
                    <th class="py-3 px-4 text-center">Status</th>
                    <th class="py-3 px-4 text-center">Alive Weeks</th>
                    <th class="py-3 px-4">Teams Selected This Season</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                <?php if (empty($standings)): ?>
                    <tr>
                        <td colspan="5" class="py-8 text-center text-slate-500 italic">No participants found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($standings as $row): ?>
                        <tr class="transition hover:bg-slate-800/30 survivor-row" data-tier="<?= htmlspecialchars($row['tier'] ?? 'free') ?>">
                            <td class="py-3.5 px-4 font-semibold text-white">
                                <?= htmlspecialchars($row['username']) ?>
                                <?php if (($user['id'] ?? 0) === $row['user_id']): ?>
                                    <span class="text-[10px] ml-1.5 px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 border border-slate-700">You</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <?php if (!empty($row['is_paid'])): ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                        🟢 Cash ($10)
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                        🎮 Free / Fun
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <?php if (($row['status'] ?? '') === 'alive'): ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold font-mono px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                        🛡️ Alive
                                    </span>
                                <?php elseif (($row['status'] ?? '') === 'eliminated'): ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold font-mono px-2.5 py-0.5 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                        ☠️ Out (Wk <?= $row['elimination_week'] ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold font-mono px-2.5 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700">
                                        Not Entered
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-center font-mono font-bold text-slate-300">
                                <?= $row['picks_count'] ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <?php if (empty($row['teams_used'])): ?>
                                    <span class="text-xs text-slate-600">—</span>
                                <?php else: ?>
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <?php foreach ($row['history'] as $h): ?>
                                            <?php if (!empty($h['is_hidden'])): ?>
                                                <span class="text-xs font-mono px-2 py-0.5 rounded border bg-slate-900 border-slate-800 text-slate-500 flex items-center gap-1" title="Hidden until game kickoff">
                                                    <span class="text-[10px] text-slate-600">W<?= $h['week_number'] ?>:</span><span>🔒 Hidden</span>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-xs font-mono px-2 py-0.5 rounded border <?= $h['is_eliminated'] ? 'bg-rose-950/40 border-rose-800/60 text-rose-300 line-through' : 'bg-slate-800 border-slate-700 text-emerald-400' ?>">
                                                    <span class="text-[10px] text-slate-400 mr-1">W<?= $h['week_number'] ?>:</span><?= htmlspecialchars($h['display_team'] ?? $h['selected_team']) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
function filterSurvivor(tier) {
    document.querySelectorAll('.survivor-tab').forEach(el => {
        el.className = 'survivor-tab px-3.5 py-1.5 text-xs font-bold rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-white hover:bg-slate-800 transition';
    });
    const activeBtn = document.getElementById('tab-' + tier);
    if (activeBtn) {
        activeBtn.className = 'survivor-tab px-3.5 py-1.5 text-xs font-bold rounded-lg bg-emerald-500 text-slate-950 transition shadow-sm';
    }

    const rows = document.querySelectorAll('.survivor-row');
    rows.forEach(row => {
        if (tier === 'all' || row.dataset.tier === tier) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
