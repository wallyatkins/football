<?php
ob_start();
$entryStatus = $entry['payment_status'] ?? 'none';
$isPaid = in_array($entryStatus, ['paid', 'exempt'], true);
?>

<div class="space-y-6">

    <!-- Header & Week Selector -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800 pb-5">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-mono px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20">NFL Regular Season</span>
                <span class="text-xs text-slate-400">Season <?= htmlspecialchars((string) $season) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">Week <?= htmlspecialchars((string) $week) ?> Pick'em</h1>
        </div>

        <!-- Week Nav Buttons -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 max-w-full">
            <?php for ($w = 1; $w <= 18; $w++): ?>
                <a href="/pickem?week=<?= $w ?>&season=<?= $season ?>" 
                   class="px-3 py-1 text-xs font-bold rounded-lg transition <?= $w === $week ? 'bg-amber-500 text-slate-950 shadow-md' : 'bg-slate-900 border border-slate-800 text-slate-400 hover:text-white hover:bg-slate-800' ?>">
                    W<?= $w ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Payment Notice Banner -->
    <?php if (!$isPaid): ?>
        <div class="p-4 rounded-xl bg-gradient-to-r from-amber-500/10 to-amber-600/5 border border-amber-500/30 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="text-2xl mt-0.5">💵</span>
                <div>
                    <h3 class="text-sm font-bold text-amber-300">Weekly Entry Stake: $10.00</h3>
                    <p class="text-xs text-slate-400 mt-0.5 leading-relaxed">
                        To qualify for the weekly pot and standings, send your entry to Commissioner Wally via Venmo 
                        <span class="text-white font-mono bg-slate-900 px-1.5 py-0.5 rounded border border-slate-700 font-bold">@<?= htmlspecialchars($venmoHandle) ?></span> 
                        or Cash App 
                        <span class="text-white font-mono bg-slate-900 px-1.5 py-0.5 rounded border border-slate-700 font-bold">$<?= htmlspecialchars($cashAppHandle) ?></span>.
                    </p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1 text-[11px] font-bold font-mono px-2.5 py-1 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 shrink-0">
                <span>⏱️</span> Pending Verification
            </span>
        </div>
    <?php else: ?>
        <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-between">
            <div class="flex items-center gap-2.5 text-xs text-emerald-300 font-medium">
                <span class="text-base">✓</span>
                <span>Payment Verified! Your entry is active in this week's official prize pot.</span>
            </div>
            <span class="text-[10px] font-mono uppercase tracking-wider font-bold px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">Verified</span>
        </div>
    <?php endif; ?>

    <!-- Pick'em Form -->
    <form action="/pickem/save" method="POST" class="space-y-6">
        <input type="hidden" name="season_year" value="<?= htmlspecialchars((string) $season) ?>">
        <input type="hidden" name="week_number" value="<?= htmlspecialchars((string) $week) ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($games as $game): ?>
                <?php
                $isLocked = (bool) $game['is_locked'];
                $isMnf = (bool) $game['is_mnf'];
                $userPick = $game['user_pick'];
                $kickoff = new DateTimeImmutable($game['kickoff_time']);
                $kickoffEt = $kickoff->setTimezone(new DateTimeZone('America/New_York'))->format('D, M j @ g:i A T');
                $isFinal = ($game['status'] === 'final');
                $inProgress = ($game['status'] === 'in_progress');
                ?>
                <div class="p-4 rounded-xl border transition <?= $isMnf ? 'border-amber-500/50 bg-slate-900/90 ring-1 ring-amber-500/20' : 'border-slate-800 bg-slate-900/60' ?>">
                    
                    <!-- Matchup Header -->
                    <div class="flex items-center justify-between text-xs mb-3 pb-2 border-b border-slate-800/80">
                        <div class="flex items-center gap-2 text-slate-400">
                            <span><?= htmlspecialchars($kickoffEt) ?></span>
                            <?php if ($isMnf): ?>
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 uppercase tracking-wider">MNF Tiebreaker</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($isFinal): ?>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-300 border border-slate-700">FINAL</span>
                            <?php elseif ($inProgress): ?>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 animate-pulse">LIVE</span>
                            <?php elseif ($isLocked): ?>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">🔒 LOCKED</span>
                            <?php else: ?>
                                <span class="text-slate-500 text-[11px]">Open</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Teams Pick Buttons -->
                    <div class="grid grid-cols-2 gap-3">
                        <!-- Away Team -->
                        <label class="relative flex flex-col p-3 rounded-lg border cursor-pointer transition select-none <?= $userPick === $game['away_team'] ? 'border-emerald-500 bg-emerald-950/30 ring-1 ring-emerald-500/50' : 'border-slate-800 bg-slate-950/60 hover:border-slate-700' ?> <?= $isLocked ? 'pointer-events-none opacity-80' : '' ?>">
                            <input type="radio" 
                                   name="picks[<?= $game['id'] ?>]" 
                                   value="<?= htmlspecialchars($game['away_team']) ?>" 
                                   class="sr-only"
                                   <?= $userPick === $game['away_team'] ? 'checked' : '' ?>
                                   <?= $isLocked ? 'disabled' : '' ?>>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs text-slate-400">Away</span>
                                <?php if ($game['away_score'] !== null): ?>
                                    <span class="text-sm font-mono font-bold <?= $isFinal && $game['away_score'] > $game['home_score'] ? 'text-emerald-400' : 'text-slate-300' ?>">
                                        <?= (int) $game['away_score'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="text-lg font-black tracking-tight text-white"><?= htmlspecialchars($game['away_team']) ?></span>
                            <?php if ($userPick === $game['away_team']): ?>
                                <span class="text-[10px] font-bold text-emerald-400 mt-1 flex items-center gap-1">✓ Your Pick</span>
                            <?php endif; ?>
                        </label>

                        <!-- Home Team -->
                        <label class="relative flex flex-col p-3 rounded-lg border cursor-pointer transition select-none <?= $userPick === $game['home_team'] ? 'border-emerald-500 bg-emerald-950/30 ring-1 ring-emerald-500/50' : 'border-slate-800 bg-slate-950/60 hover:border-slate-700' ?> <?= $isLocked ? 'pointer-events-none opacity-80' : '' ?>">
                            <input type="radio" 
                                   name="picks[<?= $game['id'] ?>]" 
                                   value="<?= htmlspecialchars($game['home_team']) ?>" 
                                   class="sr-only"
                                   <?= $userPick === $game['home_team'] ? 'checked' : '' ?>
                                   <?= $isLocked ? 'disabled' : '' ?>>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs text-slate-400">Home</span>
                                <?php if ($game['home_score'] !== null): ?>
                                    <span class="text-sm font-mono font-bold <?= $isFinal && $game['home_score'] > $game['away_score'] ? 'text-emerald-400' : 'text-slate-300' ?>">
                                        <?= (int) $game['home_score'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="text-lg font-black tracking-tight text-white"><?= htmlspecialchars($game['home_team']) ?></span>
                            <?php if ($userPick === $game['home_team']): ?>
                                <span class="text-[10px] font-bold text-emerald-400 mt-1 flex items-center gap-1">✓ Your Pick</span>
                            <?php endif; ?>
                        </label>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

        <!-- Tiebreaker Input Section -->
        <div class="p-5 rounded-xl border border-amber-500/40 bg-slate-900/90 shadow-lg">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <span class="text-xs font-mono uppercase tracking-wider text-amber-400 font-bold block mb-0.5">Tiebreaker Question</span>
                    <h4 class="text-base font-bold text-white">Monday Night Football Combined Total Score</h4>
                    <p class="text-xs text-slate-400 mt-0.5">Predict the combined final points for the designated MNF game (used to break ties in weekly standings).</p>
                </div>
                <div class="flex items-center gap-2">
                    <input type="number" 
                           name="mnf_total_points" 
                           min="0" 
                           max="150" 
                           placeholder="e.g. 48"
                           value="<?= htmlspecialchars((string) ($entry['mnf_total_points_prediction'] ?? '')) ?>"
                           class="w-28 px-3 py-2 rounded-lg bg-slate-950 border border-slate-700 text-white font-mono text-center font-bold focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                    <span class="text-xs text-slate-400 font-semibold">Total Points</span>
                </div>
            </div>
        </div>

        <!-- Form Submit Bar -->
        <div class="sticky bottom-16 md:bottom-6 z-30 p-4 rounded-xl bg-slate-900/95 border border-slate-800 backdrop-blur shadow-2xl flex items-center justify-between">
            <div class="text-xs text-slate-400">
                Locks automatically at each game's scheduled kickoff.
            </div>
            <button type="submit" class="px-6 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm transition shadow-lg flex items-center gap-2">
                <span>💾</span>
                <span>Save Week <?= htmlspecialchars((string) $week) ?> Picks</span>
            </button>
        </div>

    </form>

</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
