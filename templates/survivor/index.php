<?php
use WallyFootball\Support\TeamData;

ob_start();
?>

<div class="space-y-6">

    <!-- Header & Single-Week Focus -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800/80 pb-5">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 uppercase tracking-wider">Survivor Pool</span>
                <span class="text-xs text-slate-400">Season <?= htmlspecialchars((string) $season) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white flex items-center gap-3">
                <span>Week <?= htmlspecialchars((string) $week) ?> Selection</span>
                <span class="text-xs font-mono font-semibold px-2.5 py-1 rounded-full bg-slate-900 border border-slate-700 text-slate-300">
                    Pick 1 Winner
                </span>
            </h1>
        </div>

        <!-- Subtle Week Switcher -->
        <div class="flex items-center gap-2">
            <?php if ($week > 1): ?>
                <a href="/survivor?week=<?= $week - 1 ?>&season=<?= $season ?>" 
                   class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-800 transition flex items-center gap-1">
                    &larr; Week <?= $week - 1 ?>
                </a>
            <?php endif; ?>
            <span class="px-3 py-1.5 text-xs font-bold font-mono rounded-lg bg-emerald-500 text-slate-950 shadow-sm">
                Week <?= $week ?>
            </span>
            <?php if ($week < 18): ?>
                <a href="/survivor?week=<?= $week + 1 ?>&season=<?= $season ?>" 
                   class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-800 transition flex items-center gap-1">
                    Week <?= $week + 1 ?> &rarr;
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status Banner -->
    <?php if ($isEliminated): ?>
        <div class="p-5 rounded-2xl bg-rose-500/10 border border-rose-500/30 flex items-start gap-4 shadow-lg">
            <span class="text-3xl">☠️</span>
            <div>
                <h3 class="text-base font-black text-rose-300">Eliminated from Survivor Prize Pool</h3>
                <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                    You were knocked out of the Survivor challenge in Week <?= $eliminationWeek ?>. You may continue making picks for pride and tracking, but your entry is inactive in the season-long pot.
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-slate-950 border border-slate-800 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="p-2.5 rounded-xl bg-emerald-500/10 text-emerald-400 text-2xl border border-emerald-500/20 shrink-0">
                    🛡️
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <span>Survivor Status: Alive &amp; Active</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">ALIVE</span>
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">Pick 1 straight-up winner. Teams can only be used once per season.</p>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs font-mono uppercase tracking-wider text-slate-400">Burned Teams:</span>
                <?php if (empty($usedTeams)): ?>
                    <span class="text-xs text-slate-500 italic">None yet (all 32 teams open)</span>
                <?php else: ?>
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <?php foreach ($usedTeams as $ut): ?>
                            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded bg-slate-800 text-rose-400 border border-rose-900/40 line-through">
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
        <?php 
        $pickedTeamAbbr = $currentPick['selected_team'];
        $pickedTeamData = TeamData::get($pickedTeamAbbr);
        ?>
        <div class="p-4 rounded-2xl border shadow-lg flex items-center justify-between"
             style="border-color: <?= $pickedTeamData['color'] ?>; background: linear-gradient(135deg, <?= $pickedTeamData['color'] ?>25 0%, #090d16 100%);">
            <div class="flex items-center gap-3.5">
                <div class="w-12 h-10 flex items-center justify-center">
                    <?= TeamData::renderHelmet($pickedTeamAbbr, 48, 36) ?>
                </div>
                <img src="<?= htmlspecialchars($pickedTeamData['logo']) ?>" 
                     alt="<?= htmlspecialchars($pickedTeamData['name']) ?>" 
                     class="w-10 h-10 object-contain drop-shadow-md">
                <div>
                    <span class="text-[10px] font-mono text-emerald-400 font-bold uppercase tracking-wider block">Your Locked Week <?= htmlspecialchars((string) $week) ?> Survivor Pick</span>
                    <span class="text-xl font-black text-white"><?= htmlspecialchars($pickedTeamData['name']) ?></span>
                </div>
            </div>
            <span class="text-xs font-mono text-slate-400">
                Submitted <?= htmlspecialchars(date('M j, g:i A', strtotime($currentPick['created_at']))) ?>
            </span>
        </div>
    <?php endif; ?>

    <!-- Matchup Selector -->
    <form action="/survivor/save" method="POST" id="survivorForm" class="space-y-6">
        <input type="hidden" name="season_year" value="<?= htmlspecialchars((string) $season) ?>">
        <input type="hidden" name="week_number" value="<?= htmlspecialchars((string) $week) ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <?php foreach ($games as $game): ?>
                <?php
                $isLocked = (bool) $game['is_locked'];
                $homeUsed = (bool) $game['home_used'];
                $awayUsed = (bool) $game['away_used'];
                $currentTeam = $currentPick['selected_team'] ?? '';
                $kickoff = new DateTimeImmutable($game['kickoff_time']);
                $kickoffEt = $kickoff->setTimezone(new DateTimeZone('America/New_York'))->format('D, M j @ g:i A T');

                // Away Team
                $awayAbbr = $game['away_team'];
                $awayTeam = TeamData::get($awayAbbr);
                $awayColor = $awayTeam['color'];
                $awayPicked = ($currentTeam === $awayAbbr);
                $awayDisabled = $isLocked || $awayUsed || $isEliminated;

                // Home Team
                $homeAbbr = $game['home_team'];
                $homeTeam = TeamData::get($homeAbbr);
                $homeColor = $homeTeam['color'];
                $homePicked = ($currentTeam === $homeAbbr);
                $homeDisabled = $isLocked || $homeUsed || $isEliminated;
                ?>
                <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden shadow-lg">
                    <div class="flex items-center justify-between px-4 py-2.5 bg-slate-950/70 border-b border-slate-800/80 text-xs">
                        <span class="font-mono text-[11px] text-slate-400"><?= htmlspecialchars($kickoffEt) ?></span>
                        <?php if ($isLocked): ?>
                            <span class="px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">🔒 LOCKED</span>
                        <?php else: ?>
                            <span class="text-slate-500 text-[11px] font-medium">Kickoff Open</span>
                        <?php endif; ?>
                    </div>

                    <div class="p-3.5 grid grid-cols-2 gap-3.5" data-game-id="<?= $game['id'] ?>">
                        
                        <!-- Away Team -->
                        <label class="survivor-card relative flex flex-col p-3 rounded-xl border-2 transition-all select-none
                            <?= $awayDisabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer hover:scale-[1.02]' ?>"
                            style="<?= $awayPicked ? "border-color: {$awayColor}; background: linear-gradient(135deg, {$awayColor}28 0%, #090d16 100%); box-shadow: 0 0 20px {$awayColor}33;" : "border-color: #1e293b; background: rgba(2, 6, 23, 0.7);" ?>">
                            
                            <input type="radio" 
                                   name="selected_team" 
                                   value="<?= htmlspecialchars($awayAbbr) ?>" 
                                   class="sr-only survivor-radio"
                                   data-color="<?= htmlspecialchars($awayColor) ?>"
                                   <?= $awayPicked ? 'checked' : '' ?>
                                   <?= $awayDisabled ? 'disabled' : '' ?>>

                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-mono font-bold uppercase tracking-wider text-slate-400">Away</span>
                                <?php if ($awayUsed): ?>
                                    <span class="text-[10px] font-bold text-rose-400 font-mono">BURNED</span>
                                <?php endif; ?>
                            </div>

                            <div class="flex items-center justify-center gap-2 my-1">
                                <div class="w-12 h-10 flex items-center justify-center">
                                    <?= TeamData::renderHelmet($awayAbbr, 48, 36) ?>
                                </div>
                                <img src="<?= htmlspecialchars($awayTeam['logo']) ?>" 
                                     alt="<?= htmlspecialchars($awayTeam['name']) ?>" 
                                     class="w-10 h-10 object-contain drop-shadow-md"
                                     loading="lazy">
                            </div>

                            <div class="text-center mt-1">
                                <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block truncate">
                                    <?= htmlspecialchars($awayTeam['name']) ?>
                                </span>
                                <span class="text-lg font-black tracking-tight text-white block leading-tight">
                                    <?= htmlspecialchars($awayTeam['nick']) ?>
                                </span>
                                <span class="text-xs font-mono font-bold text-slate-500">
                                    <?= htmlspecialchars($awayAbbr) ?>
                                </span>
                            </div>

                            <div class="survivor-badge mt-2 pt-2 border-t border-slate-800/80 text-center <?= $awayPicked ? 'block' : 'hidden' ?>">
                                <span class="inline-flex items-center justify-center gap-1 w-full py-1 rounded-md text-[11px] font-black uppercase tracking-wider bg-emerald-500 text-slate-950 shadow-md">
                                    <span>🛡️</span> SURVIVOR PICK
                                </span>
                            </div>
                        </label>

                        <!-- Home Team -->
                        <label class="survivor-card relative flex flex-col p-3 rounded-xl border-2 transition-all select-none
                            <?= $homeDisabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer hover:scale-[1.02]' ?>"
                            style="<?= $homePicked ? "border-color: {$homeColor}; background: linear-gradient(135deg, {$homeColor}28 0%, #090d16 100%); box-shadow: 0 0 20px {$homeColor}33;" : "border-color: #1e293b; background: rgba(2, 6, 23, 0.7);" ?>">
                            
                            <input type="radio" 
                                   name="selected_team" 
                                   value="<?= htmlspecialchars($homeAbbr) ?>" 
                                   class="sr-only survivor-radio"
                                   data-color="<?= htmlspecialchars($homeColor) ?>"
                                   <?= $homePicked ? 'checked' : '' ?>
                                   <?= $homeDisabled ? 'disabled' : '' ?>>

                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-mono font-bold uppercase tracking-wider text-slate-400">Home</span>
                                <?php if ($homeUsed): ?>
                                    <span class="text-[10px] font-bold text-rose-400 font-mono">BURNED</span>
                                <?php endif; ?>
                            </div>

                            <div class="flex items-center justify-center gap-2 my-1">
                                <div class="w-12 h-10 flex items-center justify-center">
                                    <?= TeamData::renderHelmet($homeAbbr, 48, 36) ?>
                                </div>
                                <img src="<?= htmlspecialchars($homeTeam['logo']) ?>" 
                                     alt="<?= htmlspecialchars($homeTeam['name']) ?>" 
                                     class="w-10 h-10 object-contain drop-shadow-md"
                                     loading="lazy">
                            </div>

                            <div class="text-center mt-1">
                                <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block truncate">
                                    <?= htmlspecialchars($homeTeam['name']) ?>
                                </span>
                                <span class="text-lg font-black tracking-tight text-white block leading-tight">
                                    <?= htmlspecialchars($homeTeam['nick']) ?>
                                </span>
                                <span class="text-xs font-mono font-bold text-slate-500">
                                    <?= htmlspecialchars($homeAbbr) ?>
                                </span>
                            </div>

                            <div class="survivor-badge mt-2 pt-2 border-t border-slate-800/80 text-center <?= $homePicked ? 'block' : 'hidden' ?>">
                                <span class="inline-flex items-center justify-center gap-1 w-full py-1 rounded-md text-[11px] font-black uppercase tracking-wider bg-emerald-500 text-slate-950 shadow-md">
                                    <span>🛡️</span> SURVIVOR PICK
                                </span>
                            </div>
                        </label>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!$isEliminated): ?>
            <div class="sticky bottom-16 md:bottom-6 z-30 p-4 rounded-2xl bg-slate-900/95 border border-slate-800 backdrop-blur shadow-2xl flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2 text-xs text-slate-400">
                    <span>🛡️</span>
                    <span>Your pick locks at the chosen game's scheduled kickoff.</span>
                </div>
                <button type="submit" 
                        class="w-full sm:w-auto px-8 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-sm uppercase tracking-wider transition shadow-lg hover:shadow-emerald-500/25 flex items-center justify-center gap-2">
                    <span>🛡️</span>
                    <span>Confirm Week <?= htmlspecialchars((string) $week) ?> Survivor Pick</span>
                </button>
            </div>
        <?php endif; ?>
    </form>

</div>

<script>
// Single selection behavior across entire form
document.addEventListener('DOMContentLoaded', function () {
    const cards = document.querySelectorAll('.survivor-card');

    cards.forEach(card => {
        card.addEventListener('click', function () {
            const radio = this.querySelector('.survivor-radio');
            if (!radio || radio.disabled) return;

            // Reset all other survivor cards across the whole page
            cards.forEach(c => {
                if (!c.querySelector('.survivor-radio[disabled]')) {
                    c.style.borderColor = '#1e293b';
                    c.style.background = 'rgba(2, 6, 23, 0.7)';
                    c.style.boxShadow = 'none';
                    const badge = c.querySelector('.survivor-badge');
                    if (badge) badge.classList.add('hidden');
                }
            });

            // Highlight chosen team
            const color = radio.getAttribute('data-color') || '#10b981';
            this.style.borderColor = color;
            this.style.background = `linear-gradient(135deg, ${color}28 0%, #090d16 100%)`;
            this.style.boxShadow = `0 0 22px ${color}40`;

            const badge = this.querySelector('.survivor-badge');
            if (badge) badge.classList.remove('hidden');

            radio.checked = true;
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';

