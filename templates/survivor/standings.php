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
        <div>
            <a href="/survivor" class="px-4 py-2 text-xs font-bold rounded-lg bg-slate-900 border border-slate-800 text-slate-300 hover:text-white transition">
                &larr; Make Weekly Pick
            </a>
        </div>
    </div>

    <!-- Survivor Grid Table -->
    <div class="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900/60 shadow-xl">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-slate-800 bg-slate-900/80 text-[11px] font-mono uppercase tracking-wider text-slate-400">
                    <th class="py-3 px-4">Participant</th>
                    <th class="py-3 px-4 text-center">Status</th>
                    <th class="py-3 px-4 text-center">Alive Weeks</th>
                    <th class="py-3 px-4">Teams Selected This Season</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                <?php if (empty($standings)): ?>
                    <tr>
                        <td colspan="4" class="py-8 text-center text-slate-500 italic">No participants found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($standings as $row): ?>
                        <tr class="transition hover:bg-slate-800/30">
                            <td class="py-3.5 px-4 font-semibold text-white">
                                <?= htmlspecialchars($row['username']) ?>
                                <?php if (($user['id'] ?? 0) === $row['user_id']): ?>
                                    <span class="text-[10px] ml-1.5 px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 border border-slate-700">You</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <?php if ($row['is_alive']): ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold font-mono px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                        🛡️ Alive
                                    </span>
                                <?php elseif ($row['is_eliminated']): ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold font-mono px-2.5 py-0.5 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                        ☠️ Out (Wk <?= $row['elimination_week'] ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs text-slate-600 font-mono">No Picks</span>
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
                                            <span class="text-xs font-mono px-2 py-0.5 rounded border <?= $h['is_eliminated'] ? 'bg-rose-950/40 border-rose-800/60 text-rose-300 line-through' : 'bg-slate-800 border-slate-700 text-emerald-400' ?>">
                                                <span class="text-[10px] text-slate-400 mr-1">W<?= $h['week_number'] ?>:</span><?= htmlspecialchars($h['selected_team']) ?>
                                            </span>
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

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
