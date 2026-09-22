<?php
use WallyFootball\Support\TeamData;
ob_start();
?>

<div class="space-y-6">

    <!-- Standings Navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-[#243247] pb-3">
        <a href="/pickem/standings?season=<?= $season ?>" 
           class="px-4 py-2 text-xs font-bold rounded-lg bg-[#162235] border border-[#243247] text-[#94A3B8] hover:text-white hover:bg-[#1f2e44] transition uppercase tracking-wider">
            Weekly Pick'em Standings
        </a>
        <a href="/survivor/standings?season=<?= $season ?>" 
           class="px-4 py-2 text-xs font-bold rounded-lg bg-[#15803D] text-white font-bold transition shadow-sm uppercase tracking-wider">
            Survivor Pool Standings
        </a>
    </div>

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[#243247] pb-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-mono px-2 py-0.5 rounded bg-[#162235] text-emerald-400 border border-[#243247]">Survivor Pool</span>
                <span class="text-xs text-[#94A3B8] font-mono">Season <?= htmlspecialchars((string) $season) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-[#F8FAFC]">Survivor Leaderboard &amp; Pick History</h1>
        </div>
        <div class="flex items-center gap-2 font-mono text-xs">
            <a href="/fantasy/vault?tab=pools" 
               class="px-3 py-1.5 rounded-lg bg-[#162235] hover:bg-[#1f2e44] text-[#94A3B8] hover:text-white border border-[#243247] transition">
                Dynasty Vault
            </a>
            <a href="/survivor" class="px-3 py-1.5 rounded-lg bg-[#15803D] hover:bg-emerald-600 text-white font-bold transition">
                &larr; Make Weekly Pick
            </a>
        </div>
    </div>

    <!-- Pot & Participation Overview Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-4 rounded-xl bg-[#162235] border border-[#243247]">
            <span class="text-xs font-semibold text-[#94A3B8] block mb-1">Season Cash Prize Pot</span>
            <div class="text-2xl font-bold text-[#EAB308] font-mono tabular-nums">$<?= number_format($pot['total_pot'] ?? 0, 2) ?></div>
            <span class="text-[11px] text-[#94A3B8] font-mono mt-1 block tabular-nums">
                <?= $pot['cash_entries_count'] ?? 0 ?> verified cash entries @ $10.00
            </span>
        </div>

        <div class="p-4 rounded-xl bg-[#162235] border border-[#243247]">
            <span class="text-xs font-semibold text-[#94A3B8] block mb-1">Cash Contenders Alive</span>
            <div class="text-2xl font-bold text-emerald-400 font-mono tabular-nums">
                <?= $pot['alive_cash_count'] ?? 0 ?> <span class="text-xs font-normal text-[#94A3B8]">/ <?= $pot['cash_entries_count'] ?? 0 ?></span>
            </div>
            <span class="text-[11px] text-[#94A3B8] font-mono mt-1 block tabular-nums">
                Competing for the $<?= number_format($pot['total_pot'] ?? 0, 2) ?> payout
            </span>
        </div>

        <div class="p-4 rounded-xl bg-[#162235] border border-[#243247]">
            <span class="text-xs font-semibold text-[#94A3B8] block mb-1">Free Tier Contenders Alive</span>
            <div class="text-2xl font-bold text-[#F8FAFC] font-mono tabular-nums">
                <?= $pot['alive_free_count'] ?? 0 ?> <span class="text-xs font-normal text-[#94A3B8]">/ <?= $pot['free_entries_count'] ?? 0 ?></span>
            </div>
            <span class="text-[11px] text-[#94A3B8] font-mono mt-1 block">Playing for fun &amp; bragging rights</span>
        </div>
    </div>

    <!-- Tier Filter Tabs -->
    <div class="flex items-center gap-2 border-b border-[#243247] pb-2">
        <button type="button" onclick="filterSurvivor('all')" id="tab-all"
                class="survivor-tab px-3 py-1.5 text-xs font-bold rounded-lg bg-[#15803D] text-white transition">
            All Players (<?= count($standings) ?>)
        </button>
        <button type="button" onclick="filterSurvivor('cash')" id="tab-cash"
                class="survivor-tab px-3 py-1.5 text-xs font-bold rounded-lg bg-[#162235] border border-[#243247] text-[#94A3B8] hover:text-white transition">
            Cash Prize Pool (<?= $pot['cash_entries_count'] ?? 0 ?>)
        </button>
        <button type="button" onclick="filterSurvivor('free')" id="tab-free"
                class="survivor-tab px-3 py-1.5 text-xs font-bold rounded-lg bg-[#162235] border border-[#243247] text-[#94A3B8] hover:text-white transition">
            Free / Fun (<?= $pot['free_entries_count'] ?? 0 ?>)
        </button>
    </div>

    <!-- Survivor Grid Table -->
    <div class="overflow-x-auto rounded-xl border border-[#243247] bg-[#162235] shadow-sm">
        <table class="w-full text-left text-sm" id="survivorTable">
            <thead>
                <tr class="border-b border-[#243247] bg-[#0B1626] text-[11px] font-mono uppercase tracking-wider text-[#94A3B8]">
                    <th class="py-3 px-4">Participant</th>
                    <th class="py-3 px-4 text-center">Pool Tier</th>
                    <th class="py-3 px-4 text-center">Status</th>
                    <th class="py-3 px-4 text-center">Alive Weeks</th>
                    <th class="py-3 px-4">Pick History Across Weeks</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#243247] text-xs">
                <?php if (empty($standings)): ?>
                    <tr>
                        <td colspan="5" class="py-8 text-center text-[#94A3B8] italic">No participants found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($standings as $row): ?>
                        <tr class="transition hover:bg-[#1f2e44]/40 survivor-row" data-tier="<?= htmlspecialchars($row['tier'] ?? 'free') ?>">
                            <td class="py-3.5 px-4 font-bold text-[#F8FAFC]">
                                <?= htmlspecialchars($row['username']) ?>
                                <?php if (($user['id'] ?? 0) === $row['user_id']): ?>
                                    <span class="text-[10px] ml-1 px-1.5 py-0.2 rounded bg-[#0B1626] text-[#94A3B8] border border-[#243247] font-mono">You</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <?php if (!empty($row['is_paid'])): ?>
                                    <span class="inline-block text-[10px] font-bold font-mono px-2 py-0.5 rounded bg-emerald-950/80 text-emerald-400 border border-emerald-500/40 uppercase">
                                        Cash ($10)
                                    </span>
                                <?php else: ?>
                                    <span class="inline-block text-[10px] font-bold font-mono px-2 py-0.5 rounded bg-[#0B1626] text-[#94A3B8] border border-[#243247] uppercase">
                                        Free
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <?php if (($row['status'] ?? '') === 'alive'): ?>
                                    <span class="inline-block text-[10px] font-bold font-mono px-2 py-0.5 rounded bg-emerald-950/80 text-emerald-400 border border-emerald-500/40 uppercase">
                                        ALIVE
                                    </span>
                                <?php elseif (($row['status'] ?? '') === 'eliminated'): ?>
                                    <span class="inline-block text-[10px] font-bold font-mono px-2 py-0.5 rounded bg-rose-950/80 text-rose-400 border border-rose-500/40 uppercase">
                                        OUT (Wk <?= $row['elimination_week'] ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="inline-block text-[10px] font-bold font-mono px-2 py-0.5 rounded bg-[#0B1626] text-[#94A3B8] border border-[#243247] uppercase">
                                        Not Entered
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-center font-mono tabular-nums font-bold text-[#F8FAFC]">
                                <?= $row['picks_count'] ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <?php if (empty($row['teams_used'])): ?>
                                    <span class="text-xs text-[#94A3B8] italic">No picks submitted yet</span>
                                <?php else: ?>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <?php foreach ($row['history'] as $h): ?>
                                            <?php 
                                             $teamAbbr = $h['selected_team'] ?? '';
                                             $tData = !empty($teamAbbr) ? TeamData::get($teamAbbr) : null;
                                            ?>
                                            <?php if (!empty($h['is_hidden'])): ?>
                                                <div class="inline-flex items-center gap-1 px-2 py-1 rounded border bg-[#0B1626] border-[#243247] text-[#94A3B8] text-xs font-mono" title="Opponent pick masked until game kickoff">
                                                    <span class="text-[10px] text-[#94A3B8] font-bold">Wk <?= $h['week_number'] ?>:</span>
                                                    <span>LOCKED</span>
                                                </div>
                                            <?php elseif (!empty($h['is_eliminated'])): ?>
                                                <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded border bg-rose-950/40 border-rose-800/60 text-rose-300 text-xs font-mono shadow-sm" title="Eliminated in Week <?= $h['week_number'] ?>">
                                                    <span class="text-[10px] text-rose-400 font-bold">Wk <?= $h['week_number'] ?>:</span>
                                                    <?php if ($tData && !empty($tData['logo'])): ?>
                                                        <img src="<?= htmlspecialchars($tData['logo']) ?>" alt="<?= htmlspecialchars($teamAbbr) ?>" class="w-4 h-4 object-contain grayscale opacity-70">
                                                    <?php endif; ?>
                                                    <span class="line-through font-bold"><?= htmlspecialchars($h['display_team'] ?? $teamAbbr) ?></span>
                                                    <span class="text-[9px] font-mono uppercase text-rose-400 font-bold">Out</span>
                                                </div>
                                            <?php else: ?>
                                                <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded border bg-[#0B1626] border-[#243247] text-[#F8FAFC] text-xs font-mono shadow-sm" title="Survived Week <?= $h['week_number'] ?>">
                                                    <span class="text-[10px] text-[#94A3B8] font-bold">Wk <?= $h['week_number'] ?>:</span>
                                                    <?php if ($tData && !empty($tData['logo'])): ?>
                                                        <img src="<?= htmlspecialchars($tData['logo']) ?>" alt="<?= htmlspecialchars($teamAbbr) ?>" class="w-4 h-4 object-contain">
                                                    <?php endif; ?>
                                                    <span class="font-bold text-[#F8FAFC]"><?= htmlspecialchars($h['display_team'] ?? $teamAbbr) ?></span>
                                                    <span class="text-[9px] font-mono uppercase text-emerald-400 font-bold">Adv</span>
                                                </div>
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
    
    <!-- Mid-Season & Late-Join Rules Explainer -->
    <div class="rounded-xl border border-[#243247] bg-[#162235] p-5 text-xs shadow-sm space-y-3">
        <div class="flex items-center gap-2 mb-1">
            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
            <h3 class="text-sm font-bold text-[#F8FAFC]">⚖️ Mid-Season &amp; Late-Join Survivor Guidelines</h3>
        </div>
        <p class="text-[#94A3B8] leading-relaxed">
            Want to jump into the Survivor pool mid-season? To keep things 100% fair to Week 1 starters who risked elimination and already burned powerhouse teams (like Cincinnati and the Chargers), here are the official league options for late entrants:
        </p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-2">
            <div class="p-3 rounded-lg bg-[#0B1626] border border-[#243247]">
                <span class="font-mono font-bold text-amber-400 uppercase text-[10px] block mb-1">Option 1: The "Used Teams" Handicap</span>
                <p class="text-[#94A3B8] leading-relaxed text-[11px]">
                    Late entrants can join the main pool, but must retroactively forfeit one consensus top team per missed week (e.g. you cannot select Cincinnati or Buffalo), ensuring equal team scarcity.
                </p>
            </div>
            <div class="p-3 rounded-lg bg-[#0B1626] border border-[#243247]">
                <span class="font-mono font-bold text-sky-400 uppercase text-[10px] block mb-1">Option 2: Flight B Second-Chance Pool</span>
                <p class="text-[#94A3B8] leading-relaxed text-[11px]">
                    A secondary Survivor pool will kick off in Week 4 with a fresh mini-pot for all newcomers and players who were knocked out in Weeks 1–3.
                </p>
            </div>
            <div class="p-3 rounded-lg bg-[#0B1626] border border-[#243247]">
                <span class="font-mono font-bold text-emerald-400 uppercase text-[10px] block mb-1">Option 3: Sudden Death Buy-In</span>
                <p class="text-[#94A3B8] leading-relaxed text-[11px]">
                    Late entrants jump in with zero strikes or safety nets. Any loss eliminates you immediately from contention.
                </p>
            </div>
        </div>
        <div class="pt-2 border-t border-[#243247] text-[11px] text-[#94A3B8] flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <span>💵 <em>Subtle Note:</em> An optional <strong>$20 Survivor Season Cash Pool</strong> is also active. 100% of verified buy-ins go to the last survivor standing.</span>
            <span class="text-emerald-400 font-mono font-bold">Venmo: @WallyAtkins &bull; Cash App: $WallyAtkins</span>
        </div>
    </div>

</div>

<script>
function filterSurvivor(tier) {
    document.querySelectorAll('.survivor-tab').forEach(el => {
        el.className = 'survivor-tab px-3 py-1.5 text-xs font-bold rounded-lg bg-[#162235] border border-[#243247] text-[#94A3B8] hover:text-white transition';
    });
    const activeBtn = document.getElementById('tab-' + tier);
    if (activeBtn) {
        activeBtn.className = 'survivor-tab px-3 py-1.5 text-xs font-bold rounded-lg bg-[#15803D] text-white transition';
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
