<?php
use WallyFootball\Support\TeamData;

ob_start();
$isNotEntered = ($survivorStatus === 'not_entered');
$isAlive = ($survivorStatus === 'alive');
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

            <!-- How It Works Modal Button -->
            <button type="button" 
                    id="btnOpenSurvivorHowItWorks"
                    class="px-3 py-1.5 text-xs font-bold rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 transition flex items-center gap-1.5 shadow-sm">
                <span>💡</span>
                <span>How It Works</span>
            </button>
        </div>
    </div>

    <!-- Survivor Status Banners -->
    <?php if ($isNotEntered): ?>
        <!-- Not Entered Banner: Upfront $10 Payment Gate -->
        <div class="p-6 rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-amber-950/20 border-2 border-amber-500/40 shadow-xl space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-5">
                <div class="flex items-start gap-4">
                    <div class="p-3 rounded-xl bg-amber-500/15 text-amber-400 text-3xl border border-amber-500/30 shrink-0">
                        🎟️
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h2 class="text-lg font-black text-white">Survivor Pool Entry Fee Required ($10.00)</h2>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 uppercase tracking-wider">
                                NOT ENTERED
                            </span>
                        </div>
                        <p class="text-xs text-slate-300 mt-1 leading-relaxed">
                            Survivor requires a <strong>one-time $10.00 entry fee upfront</strong> for the season. Send your $10 stake to Commissioner Wally using any verified channel below. Once verified, your status will become <strong>Alive</strong> and your picks will unlock!
                        </p>
                        <div class="mt-2.5 inline-flex items-center gap-2 px-3 py-1 rounded-lg bg-slate-950/80 border border-slate-700/80 text-xs">
                            <span class="text-slate-400">Payment Memo Note:</span>
                            <span class="font-mono font-bold text-amber-300 select-all">Survivor - <?= htmlspecialchars($user['username'] ?? 'username') ?></span>
                        </div>
                    </div>
                </div>

                <!-- Verified Payment Action Buttons -->
                <div class="flex items-center gap-2 flex-wrap shrink-0">
                    <a href="<?= htmlspecialchars($venmoUrl) ?>" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#008CFF]/20 hover:bg-[#008CFF]/30 border border-[#008CFF]/50 text-[#38bdf8] font-bold text-xs transition shadow-sm hover:scale-105 transform">
                        <span>📱</span> Venmo (@WallyAtkins) &rarr;
                    </a>
                    <a href="<?= htmlspecialchars($payPalUrl) ?>" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#0079C1]/20 hover:bg-[#0079C1]/30 border border-[#0079C1]/50 text-[#38bdf8] font-bold text-xs transition shadow-sm hover:scale-105 transform">
                        <span>💳</span> PayPal (paypal.me/WallyAtkins) &rarr;
                    </a>
                    <a href="<?= htmlspecialchars($cashAppUrl) ?>" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#00D632]/20 hover:bg-[#00D632]/30 border border-[#00D632]/50 text-[#4ade80] font-bold text-xs transition shadow-sm hover:scale-105 transform">
                        <span>⚡</span> Cash App ($WallyAtkins) &rarr;
                    </a>
                </div>
            </div>
        </div>

    <?php elseif ($isEliminated): ?>
        <!-- Eliminated Banner -->
        <div class="p-5 rounded-2xl bg-rose-500/10 border border-rose-500/30 flex items-start gap-4 shadow-lg">
            <span class="text-3xl">☠️</span>
            <div>
                <h3 class="text-base font-black text-rose-300">Eliminated from Survivor Prize Pool</h3>
                <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                    You were knocked out of the Survivor challenge in Week <?= $eliminationWeek ?>. Your entry is inactive in the season-long pot.
                </p>
            </div>
        </div>

    <?php else: ?>
        <!-- Alive & Active Banner -->
        <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-slate-950 border border-emerald-500/30 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="p-2.5 rounded-xl bg-emerald-500/15 text-emerald-400 text-2xl border border-emerald-500/30 shrink-0">
                    🛡️
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <span>Survivor Status: Alive &amp; Active</span>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">ALIVE</span>
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
        <div class="p-5 rounded-2xl border shadow-lg flex items-center justify-between"
             style="border-color: <?= $pickedTeamData['color'] ?>; background: linear-gradient(135deg, <?= $pickedTeamData['color'] ?>25 0%, #090d16 100%);">
            <div class="flex items-center gap-4">
                <img src="<?= htmlspecialchars($pickedTeamData['logo']) ?>" 
                     alt="<?= htmlspecialchars($pickedTeamData['name']) ?>" 
                     class="w-14 h-14 object-contain filter drop-shadow-md">
                <div>
                    <span class="text-[10px] font-mono text-emerald-400 font-bold uppercase tracking-wider block">Your Week <?= htmlspecialchars((string) $week) ?> Survivor Pick</span>
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
                $awayDisabled = $isLocked || $awayUsed || $isEliminated || $isNotEntered;

                // Home Team
                $homeAbbr = $game['home_team'];
                $homeTeam = TeamData::get($homeAbbr);
                $homeColor = $homeTeam['color'];
                $homePicked = ($currentTeam === $homeAbbr);
                $homeDisabled = $isLocked || $homeUsed || $isEliminated || $isNotEntered;
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
                        <label class="survivor-card relative flex flex-col items-center justify-between p-3.5 rounded-xl border-2 transition-all select-none
                            <?= $awayDisabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer hover:scale-[1.02]' ?>"
                            style="<?= $awayPicked ? "border-color: {$awayColor}; background: linear-gradient(135deg, {$awayColor}28 0%, #090d16 100%); box-shadow: 0 0 22px {$awayColor}40;" : "border-color: #1e293b; background: rgba(2, 6, 23, 0.7);" ?>">
                            
                            <input type="radio" 
                                   name="selected_team" 
                                   value="<?= htmlspecialchars($awayAbbr) ?>" 
                                   class="sr-only survivor-radio"
                                   data-color="<?= htmlspecialchars($awayColor) ?>"
                                   <?= $awayPicked ? 'checked' : '' ?>
                                   <?= $awayDisabled ? 'disabled' : '' ?>>

                            <div class="w-full flex items-center justify-between mb-1">
                                <span class="text-[10px] font-mono font-bold uppercase tracking-wider text-slate-400">Away</span>
                                <?php if ($awayUsed): ?>
                                    <span class="text-[10px] font-bold text-rose-400 font-mono">BURNED</span>
                                <?php endif; ?>
                            </div>

                            <div class="my-2 h-16 flex items-center justify-center">
                                <img src="<?= htmlspecialchars($awayTeam['logo']) ?>" 
                                     alt="<?= htmlspecialchars($awayTeam['name']) ?>" 
                                     class="w-14 h-14 object-contain filter drop-shadow-md"
                                     loading="lazy">
                            </div>

                            <div class="text-center w-full mt-1">
                                <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block truncate">
                                    <?= htmlspecialchars($awayTeam['name']) ?>
                                </span>
                                <span class="text-base font-black tracking-tight text-white block leading-tight">
                                    <?= htmlspecialchars($awayTeam['nick']) ?>
                                </span>
                                <span class="text-xs font-mono font-bold text-slate-500">
                                    <?= htmlspecialchars($awayAbbr) ?>
                                </span>
                            </div>

                            <div class="survivor-badge w-full mt-2 pt-2 border-t border-slate-800/80 text-center <?= $awayPicked ? 'block' : 'hidden' ?>">
                                <span class="inline-flex items-center justify-center gap-1 w-full py-1 rounded-md text-[10px] font-black uppercase tracking-wider bg-emerald-500 text-slate-950 shadow-md">
                                    <span>🛡️</span> SURVIVOR PICK
                                </span>
                            </div>
                        </label>

                        <!-- Home Team -->
                        <label class="survivor-card relative flex flex-col items-center justify-between p-3.5 rounded-xl border-2 transition-all select-none
                            <?= $homeDisabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer hover:scale-[1.02]' ?>"
                            style="<?= $homePicked ? "border-color: {$homeColor}; background: linear-gradient(135deg, {$homeColor}28 0%, #090d16 100%); box-shadow: 0 0 22px {$homeColor}40;" : "border-color: #1e293b; background: rgba(2, 6, 23, 0.7);" ?>">
                            
                            <input type="radio" 
                                   name="selected_team" 
                                   value="<?= htmlspecialchars($homeAbbr) ?>" 
                                   class="sr-only survivor-radio"
                                   data-color="<?= htmlspecialchars($homeColor) ?>"
                                   <?= $homePicked ? 'checked' : '' ?>
                                   <?= $homeDisabled ? 'disabled' : '' ?>>

                            <div class="w-full flex items-center justify-between mb-1">
                                <span class="text-[10px] font-mono font-bold uppercase tracking-wider text-slate-400">Home</span>
                                <?php if ($homeUsed): ?>
                                    <span class="text-[10px] font-bold text-rose-400 font-mono">BURNED</span>
                                <?php endif; ?>
                            </div>

                            <div class="my-2 h-16 flex items-center justify-center">
                                <img src="<?= htmlspecialchars($homeTeam['logo']) ?>" 
                                     alt="<?= htmlspecialchars($homeTeam['name']) ?>" 
                                     class="w-14 h-14 object-contain filter drop-shadow-md"
                                     loading="lazy">
                            </div>

                            <div class="text-center w-full mt-1">
                                <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block truncate">
                                    <?= htmlspecialchars($homeTeam['name']) ?>
                                </span>
                                <span class="text-base font-black tracking-tight text-white block leading-tight">
                                    <?= htmlspecialchars($homeTeam['nick']) ?>
                                </span>
                                <span class="text-xs font-mono font-bold text-slate-500">
                                    <?= htmlspecialchars($homeAbbr) ?>
                                </span>
                            </div>

                            <div class="survivor-badge w-full mt-2 pt-2 border-t border-slate-800/80 text-center <?= $homePicked ? 'block' : 'hidden' ?>">
                                <span class="inline-flex items-center justify-center gap-1 w-full py-1 rounded-md text-[10px] font-black uppercase tracking-wider bg-emerald-500 text-slate-950 shadow-md">
                                    <span>🛡️</span> SURVIVOR PICK
                                </span>
                            </div>
                        </label>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Submit Controls -->
        <div class="sticky bottom-16 md:bottom-6 z-30 p-4 rounded-2xl bg-slate-900/95 border border-slate-800 backdrop-blur shadow-2xl flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2 text-xs text-slate-400">
                <span>🛡️</span>
                <?php if ($isNotEntered): ?>
                    <span class="text-amber-400 font-semibold">Payment required: Send $10 to Commissioner Wally with note "Survivor - <?= htmlspecialchars($user['username'] ?? '') ?>" to unlock picks.</span>
                <?php elseif ($isEliminated): ?>
                    <span class="text-rose-400">Eliminated from the season-long prize pool.</span>
                <?php else: ?>
                    <span>Your pick locks permanently at the chosen game's scheduled kickoff.</span>
                <?php endif; ?>
            </div>

            <?php if ($isNotEntered): ?>
                <button type="button" disabled
                        class="w-full sm:w-auto px-8 py-3 rounded-xl bg-slate-800 text-slate-500 font-bold text-xs uppercase tracking-wider cursor-not-allowed border border-slate-700">
                    🔒 $10 Payment Verification Required
                </button>
            <?php elseif ($isAlive): ?>
                <button type="submit" 
                        class="w-full sm:w-auto px-8 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-sm uppercase tracking-wider transition shadow-lg hover:shadow-emerald-500/25 flex items-center justify-center gap-2">
                    <span>🛡️</span>
                    <span>Confirm Week <?= htmlspecialchars((string) $week) ?> Survivor Pick</span>
                </button>
            <?php endif; ?>
        </div>
    </form>

</div>

<!-- Survivor How It Works Modal -->
<div id="survivorHowItWorksModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-700/80 rounded-2xl max-w-lg w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        
        <!-- Header -->
        <div class="p-5 border-b border-slate-800 bg-slate-950/80 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="p-2 rounded-xl bg-emerald-500/20 text-emerald-400 text-xl border border-emerald-500/30">🛡️</span>
                <div>
                    <h3 class="text-lg font-black text-white">How Survivor Works</h3>
                    <span class="text-xs text-slate-400">NFL Eliminator Pool Rules</span>
                </div>
            </div>
            <button type="button" id="btnCloseSurvivorHowItWorksX" class="text-slate-400 hover:text-white text-2xl font-bold leading-none p-2">&times;</button>
        </div>

        <!-- Content (Scrollable) -->
        <div class="p-5 overflow-y-auto space-y-3.5 text-xs">
            
            <!-- Rule 1: One Pick Per Week -->
            <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="p-2 rounded-lg bg-emerald-500/15 text-emerald-400 font-black text-sm shrink-0">1</span>
                <div>
                    <strong class="text-white text-sm block mb-0.5">Pick 1 Winner Each Week</strong>
                    <p class="text-slate-300 leading-relaxed">
                        Each week, select exactly <strong>one NFL team</strong> you believe will win their game outright (straight-up, no point spreads).
                    </p>
                </div>
            </div>

            <!-- Rule 2: The Golden Rule (No Repeats) -->
            <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="p-2 rounded-lg bg-rose-500/15 text-rose-400 font-black text-sm shrink-0">2</span>
                <div>
                    <strong class="text-white text-sm block mb-0.5">The Golden Rule: Pick Each Team Once</strong>
                    <p class="text-slate-300 leading-relaxed">
                        You can only pick each NFL team <strong>ONCE</strong> during the entire season. Once you choose a team, they are burned (<span class="text-rose-400 font-semibold">BURNED</span>) and cannot be selected again in future weeks. Plan your season-long strategy carefully!
                    </p>
                </div>
            </div>

            <!-- Rule 3: Survive & Advance -->
            <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="p-2 rounded-lg bg-amber-500/15 text-amber-400 font-black text-sm shrink-0">3</span>
                <div>
                    <strong class="text-white text-sm block mb-0.5">Survive &amp; Advance</strong>
                    <p class="text-slate-300 leading-relaxed">
                        If your selected team wins, you survive and advance to the next week. If your team <strong>loses or ties</strong>, you are permanently eliminated from the pool.
                    </p>
                </div>
            </div>

            <!-- Rule 4: One-Time Season Entry Fee -->
            <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="p-2 rounded-lg bg-blue-500/15 text-blue-400 font-black text-sm shrink-0">4</span>
                <div>
                    <strong class="text-white text-sm block mb-0.5">One-Time $10 Season Stake</strong>
                    <p class="text-slate-300 leading-relaxed">
                        Entry is a one-time $10 fee for the entire season. Send your stake to Commissioner Wally via Venmo (<span class="text-sky-400 font-bold font-mono">@WallyAtkins</span>), PayPal, or Cash App (<span class="text-emerald-400 font-bold font-mono">$WallyAtkins</span>). The last remaining player wins the entire Survivor prize pot!
                    </p>
                </div>
            </div>

            <!-- Rule 5: Lockout Times -->
            <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="p-2 rounded-lg bg-purple-500/15 text-purple-400 font-black text-sm shrink-0">5</span>
                <div>
                    <strong class="text-white text-sm block mb-0.5">Game Kickoff Lockout</strong>
                    <p class="text-slate-300 leading-relaxed">
                        Your pick locks in when your selected game reaches its scheduled kickoff time. Until kickoff, you can adjust your pick for unplayed games.
                    </p>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <div class="p-4 border-t border-slate-800 bg-slate-950/90 flex justify-end">
            <button type="button" id="btnCloseSurvivorHowItWorks" class="px-5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs uppercase tracking-wider transition shadow">
                Got It, Let's Survive!
            </button>
        </div>

    </div>
</div>

<script>
// Survivor Single-Selection across games
document.addEventListener('DOMContentLoaded', function () {
    const cards = document.querySelectorAll('.survivor-card');

    cards.forEach(card => {
        card.addEventListener('click', function () {
            const radio = this.querySelector('.survivor-radio');
            if (!radio || radio.disabled) return;

            // Reset all cards across the page
            cards.forEach(c => {
                const r = c.querySelector('.survivor-radio');
                if (r && !r.disabled) {
                    c.style.borderColor = '#1e293b';
                    c.style.background = 'rgba(2, 6, 23, 0.7)';
                    c.style.boxShadow = 'none';
                    const badge = c.querySelector('.survivor-badge');
                    if (badge) badge.classList.add('hidden');
                }
            });

            // Highlight selected card
            const color = radio.getAttribute('data-color') || '#10b981';
            this.style.borderColor = color;
            this.style.background = `linear-gradient(135deg, ${color}28 0%, #090d16 100%)`;
            this.style.boxShadow = `0 0 22px ${color}40`;

            const badge = this.querySelector('.survivor-badge');
            if (badge) badge.classList.remove('hidden');

            radio.checked = true;
        });
    });

    // Survivor How It Works Modal
    const survivorModal = document.getElementById('survivorHowItWorksModal');
    const btnOpenSurvivorModal = document.getElementById('btnOpenSurvivorHowItWorks');
    const btnCloseSurvivorModal = document.getElementById('btnCloseSurvivorHowItWorks');
    const btnCloseSurvivorModalX = document.getElementById('btnCloseSurvivorHowItWorksX');

    function openSurvivorModal() {
        if (survivorModal) {
            survivorModal.classList.remove('hidden');
            survivorModal.classList.add('flex');
        }
    }

    function closeSurvivorModal() {
        if (survivorModal) {
            survivorModal.classList.add('hidden');
            survivorModal.classList.remove('flex');
        }
    }

    if (btnOpenSurvivorModal) btnOpenSurvivorModal.addEventListener('click', openSurvivorModal);
    if (btnCloseSurvivorModal) btnCloseSurvivorModal.addEventListener('click', closeSurvivorModal);
    if (btnCloseSurvivorModalX) btnCloseSurvivorModalX.addEventListener('click', closeSurvivorModal);
    if (survivorModal) {
        survivorModal.addEventListener('click', function (e) {
            if (e.target === survivorModal) closeSurvivorModal();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeSurvivorModal();
    });
});
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
