<?php
ob_start();
?>

<div class="space-y-6">

    <!-- Header & Week Selector -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800 pb-5">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-mono px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Survivor Pool</span>
                <span class="text-xs text-slate-400">Season <?= htmlspecialchars((string) $season) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">Week <?= htmlspecialchars((string) $week) ?> Survivor Selection</h1>
        </div>

        <!-- Week Nav Buttons -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 max-w-full">
            <?php for ($w = 1; $w <= 18; $w++): ?>
                <a href="/survivor?week=<?= $w ?>&season=<?= $season ?>" 
                   class="px-3 py-1 text-xs font-bold rounded-lg transition <?= $w === $week ? 'bg-emerald-500 text-slate-950 shadow-md font-bold' : 'bg-slate-900 border border-slate-800 text-slate-400 hover:text-white hover:bg-slate-800' ?>">
                    W<?= $w ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Status Banner -->
    <?php if ($isEliminated): ?>
        <div class="p-5 rounded-xl bg-rose-500/10 border border-rose-500/30 flex items-start gap-4">
            <span class="text-3xl">☠️</span>
            <div>
                <h3 class="text-base font-bold text-rose-300">You are Eliminated</h3>
                <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                    You were knocked out of the Survivor challenge in Week <?= $eliminationWeek ?>. You can continue submitting picks for fun, but your entry is inactive in the prize pool.
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🛡️</span>
                <div>
                    <h3 class="text-sm font-bold text-emerald-400">Survivor Status: Active & Alive</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Pick 1 straight-up winner. You cannot pick any team you have already used this season.</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-slate-400">Burned Teams:</span>
                <?php if (empty($usedTeams)): ?>
                    <span class="text-xs text-slate-500 italic">None yet</span>
                <?php else: ?>
                    <div class="flex items-center gap-1 flex-wrap">
                        <?php foreach ($usedTeams as $ut): ?>
                            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded bg-slate-800 text-rose-300 border border-slate-700 line-through">
                                <?= htmlspecialchars($ut) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Current Week Selected Pick (if already picked) -->
    <?php if ($currentPick): ?>
        <div class="p-4 rounded-xl bg-emerald-950/20 border border-emerald-500/40 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="text-xl">🏈</span>
                <div>
                    <span class="text-[11px] font-mono text-emerald-400 font-bold uppercase tracking-wider block">Your Week <?= htmlspecialchars((string) $week) ?> Selection</span>
                    <span class="text-lg font-black text-white"><?= htmlspecialchars($currentPick['selected_team']) ?></span>
                </div>
            </div>
            <span class="text-xs font-mono text-slate-400">
                Submitted <?= htmlspecialchars(date('M j, g:i A', strtotime($currentPick['created_at']))) ?>
            </span>
        </div>
    <?php endif; ?>

    <!-- Matchup Selector -->
    <form action="/survivor/save" method="POST" class="space-y-6">
        <input type="hidden" name="season_year" value="<?= htmlspecialchars((string) $season) ?>">
        <input type="hidden" name="week_number" value="<?= htmlspecialchars((string) $week) ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($games as $game): ?>
                <?php
                $isLocked = (bool) $game['is_locked'];
                $homeUsed = (bool) $game['home_used'];
                $awayUsed = (bool) $game['away_used'];
                $currentTeam = $currentPick['selected_team'] ?? '';
                $kickoff = new DateTimeImmutable($game['kickoff_time']);
                $kickoffEt = $kickoff->setTimezone(new DateTimeZone('America/New_York'))->format('D, M j @ g:i A T');
                ?>
                <div class="p-4 rounded-xl border border-slate-800 bg-slate-900/60 space-y-3">
                    <div class="flex items-center justify-between text-xs text-slate-400 border-b border-slate-800/80 pb-2">
                        <span><?= htmlspecialchars($kickoffEt) ?></span>
                        <?php if ($isLocked): ?>
                            <span class="text-[10px] font-bold text-rose-400">🔒 LOCKED</span>
                        <?php else: ?>
                            <span class="text-[10px] font-bold text-slate-500">OPEN</span>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <!-- Away Team -->
                        <?php
                        $awayDisabled = $isLocked || $awayUsed || $isEliminated;
                        ?>
                        <label class="relative flex flex-col p-3 rounded-lg border transition select-none <?= $currentTeam === $game['away_team'] ? 'border-emerald-500 bg-emerald-950/30 ring-1 ring-emerald-500/50' : 'border-slate-800 bg-slate-950/60' ?> <?= $awayDisabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer hover:border-slate-700' ?>">
                            <input type="radio" 
                                   name="selected_team" 
                                   value="<?= htmlspecialchars($game['away_team']) ?>" 
                                   class="sr-only"
                                   <?= $currentTeam === $game['away_team'] ? 'checked' : '' ?>
                                   <?= $awayDisabled ? 'disabled' : '' ?>>
                            <span class="text-xs text-slate-400">Away</span>
                            <span class="text-lg font-black tracking-tight text-white mt-0.5"><?= htmlspecialchars($game['away_team']) ?></span>
                            <?php if ($awayUsed): ?>
                                <span class="text-[10px] font-bold text-rose-400 mt-1">Already Used</span>
                            <?php elseif ($currentTeam === $game['away_team']): ?>
                                <span class="text-[10px] font-bold text-emerald-400 mt-1">✓ Chosen</span>
                            <?php endif; ?>
                        </label>

                        <!-- Home Team -->
                        <?php
                        $homeDisabled = $isLocked || $homeUsed || $isEliminated;
                        ?>
                        <label class="relative flex flex-col p-3 rounded-lg border transition select-none <?= $currentTeam === $game['home_team'] ? 'border-emerald-500 bg-emerald-950/30 ring-1 ring-emerald-500/50' : 'border-slate-800 bg-slate-950/60' ?> <?= $homeDisabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer hover:border-slate-700' ?>">
                            <input type="radio" 
                                   name="selected_team" 
                                   value="<?= htmlspecialchars($game['home_team']) ?>" 
                                   class="sr-only"
                                   <?= $currentTeam === $game['home_team'] ? 'checked' : '' ?>
                                   <?= $homeDisabled ? 'disabled' : '' ?>>
                            <span class="text-xs text-slate-400">Home</span>
                            <span class="text-lg font-black tracking-tight text-white mt-0.5"><?= htmlspecialchars($game['home_team']) ?></span>
                            <?php if ($homeUsed): ?>
                                <span class="text-[10px] font-bold text-rose-400 mt-1">Already Used</span>
                            <?php elseif ($currentTeam === $game['home_team']): ?>
                                <span class="text-[10px] font-bold text-emerald-400 mt-1">✓ Chosen</span>
                            <?php endif; ?>
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!$isEliminated): ?>
            <div class="sticky bottom-16 md:bottom-6 z-30 p-4 rounded-xl bg-slate-900/95 border border-slate-800 backdrop-blur shadow-2xl flex items-center justify-between">
                <div class="text-xs text-slate-400">
                    Your pick locks at the chosen game's scheduled kickoff.
                </div>
                <button type="submit" class="px-6 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm transition shadow-lg flex items-center gap-2">
                    <span>🛡️</span>
                    <span>Confirm Week <?= htmlspecialchars((string) $week) ?> Survivor Pick</span>
                </button>
            </div>
        <?php endif; ?>
    </form>

</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
