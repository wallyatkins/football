<?php
use WallyFootball\Support\TeamData;

ob_start();
$entryStatus = $entry['payment_status'] ?? 'none';
$isPaid = in_array($entryStatus, ['paid', 'exempt'], true);

$venmoUrl = 'https://account.venmo.com/u/WallyAtkins';
$payPalUrl = 'https://paypal.me/WallyAtkins';
$cashAppUrl = 'https://cash.app/$WallyAtkins';
?>

<div class="space-y-6">

    <!-- Header & Single-Week Focus -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800/80 pb-5">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20 uppercase tracking-wider">NFL Regular Season</span>
                <span class="text-xs text-slate-400">Season <?= htmlspecialchars((string) $season) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white flex items-center gap-3">
                <span>Week <?= htmlspecialchars((string) $week) ?> Matchups</span>
                <span class="text-xs font-mono font-semibold px-2.5 py-1 rounded-full bg-slate-900 border border-slate-700 text-slate-300">
                    <?= count($games) ?> Games
                </span>
            </h1>
        </div>

        <!-- Subtle Week Switcher (Compact, No 1-18 clutter) -->
        <div class="flex items-center gap-2">
            <?php if ($week > 1): ?>
                <a href="/pickem?week=<?= $week - 1 ?>&season=<?= $season ?>" 
                   class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-800 transition flex items-center gap-1">
                    &larr; Week <?= $week - 1 ?>
                </a>
            <?php endif; ?>
            <span class="px-3 py-1.5 text-xs font-bold font-mono rounded-lg bg-amber-500 text-slate-950 shadow-sm">
                Week <?= $week ?>
            </span>
            <?php if ($week < 18): ?>
                <a href="/pickem?week=<?= $week + 1 ?>&season=<?= $season ?>" 
                   class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-800 transition flex items-center gap-1">
                    Week <?= $week + 1 ?> &rarr;
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Official Payment Channels Banner -->
    <?php if (!$isPaid): ?>
        <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900/90 to-slate-950 border border-amber-500/30 shadow-xl space-y-3">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-start gap-3.5">
                    <div class="p-2.5 rounded-xl bg-amber-500/10 text-amber-400 text-2xl border border-amber-500/20 shrink-0">
                        💵
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-white">Weekly Stake: $10.00</h3>
                            <span class="text-[10px] font-mono uppercase tracking-wider font-bold px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                Pending Commissioner Verification
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5 leading-relaxed">
                            Send your $10.00 entry stake directly to Commissioner Wally using any of the verified channels below:
                        </p>
                    </div>
                </div>

                <!-- Verified Payment Action Buttons -->
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="<?= htmlspecialchars($venmoUrl) ?>" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#008CFF]/15 hover:bg-[#008CFF]/25 border border-[#008CFF]/40 text-[#008CFF] font-bold text-xs transition shadow-sm hover:scale-105 transform">
                        <span>📱</span> Venmo (@WallyAtkins) &rarr;
                    </a>
                    <a href="<?= htmlspecialchars($payPalUrl) ?>" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#0079C1]/15 hover:bg-[#0079C1]/25 border border-[#0079C1]/40 text-[#38bdf8] font-bold text-xs transition shadow-sm hover:scale-105 transform">
                        <span>💳</span> PayPal (paypal.me/WallyAtkins) &rarr;
                    </a>
                    <a href="<?= htmlspecialchars($cashAppUrl) ?>" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#00D632]/15 hover:bg-[#00D632]/25 border border-[#00D632]/40 text-[#00D632] font-bold text-xs transition shadow-sm hover:scale-105 transform">
                        <span>⚡</span> Cash App ($WallyAtkins) &rarr;
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3 text-xs text-emerald-300 font-medium">
                <span class="text-lg font-bold">✓</span>
                <span>Payment Verified! Your entry is active in this week's official prize pool. Good luck!</span>
            </div>
            <span class="text-[10px] font-mono uppercase tracking-wider font-bold px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                Verified In Pool
            </span>
        </div>
    <?php endif; ?>

    <!-- Pick'em Form -->
    <form action="/pickem/save" method="POST" id="pickemForm" class="space-y-6">
        <input type="hidden" name="season_year" value="<?= htmlspecialchars((string) $season) ?>">
        <input type="hidden" name="week_number" value="<?= htmlspecialchars((string) $week) ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <?php foreach ($games as $game): ?>
                <?php
                $isLocked = (bool) $game['is_locked'];
                $isMnf = (bool) $game['is_mnf'];
                $userPick = $game['user_pick'];
                $kickoff = new DateTimeImmutable($game['kickoff_time']);
                $kickoffEt = $kickoff->setTimezone(new DateTimeZone('America/New_York'))->format('D, M j @ g:i A T');
                $isFinal = ($game['status'] === 'final');
                $inProgress = ($game['status'] === 'in_progress');

                // Away Team Data
                $awayAbbr = $game['away_team'];
                $awayTeam = TeamData::get($awayAbbr);
                $awayColor = $awayTeam['color'];
                $awayColor2 = $awayTeam['color2'];
                $awayPicked = ($userPick === $awayAbbr);

                // Home Team Data
                $homeAbbr = $game['home_team'];
                $homeTeam = TeamData::get($homeAbbr);
                $homeColor = $homeTeam['color'];
                $homeColor2 = $homeTeam['color2'];
                $homePicked = ($userPick === $homeAbbr);

                $hasPick = $awayPicked || $homePicked;
                ?>
                <div class="matchup-card rounded-2xl border transition-all duration-200 overflow-hidden shadow-lg <?= $isMnf ? 'border-amber-500/60 bg-slate-900/90 ring-1 ring-amber-500/30' : 'border-slate-800/80 bg-slate-900/70' ?>">
                    
                    <!-- Matchup Broadcast Header -->
                    <div class="flex items-center justify-between px-4 py-2.5 bg-slate-950/70 border-b border-slate-800/80 text-xs">
                        <div class="flex items-center gap-2 text-slate-400">
                            <span class="font-mono text-[11px]"><?= htmlspecialchars($kickoffEt) ?></span>
                            <?php if ($isMnf): ?>
                                <span class="px-2 py-0.5 rounded text-[10px] font-black bg-amber-500 text-slate-950 uppercase tracking-wider shadow-sm">
                                    ⭐ MNF Tiebreaker
                                </span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($isFinal): ?>
                                <span class="px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-800 text-slate-300 border border-slate-700">FINAL</span>
                            <?php elseif ($inProgress): ?>
                                <span class="px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 animate-pulse">LIVE</span>
                            <?php elseif ($isLocked): ?>
                                <span class="px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">🔒 LOCKED</span>
                            <?php else: ?>
                                <span class="text-slate-500 text-[11px] font-medium">Kickoff Open</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Teams Pick Grid -->
                    <div class="p-3.5 grid grid-cols-2 gap-3.5" data-game-id="<?= $game['id'] ?>">
                        
                        <!-- Away Team Selection Card -->
                        <label class="team-card relative flex flex-col p-3 rounded-xl border-2 cursor-pointer transition-all select-none group
                            <?= $isLocked ? 'pointer-events-none' : 'hover:scale-[1.02]' ?>
                            <?= $hasPick && !$awayPicked ? 'opacity-50' : 'opacity-100' ?>"
                            style="<?= $awayPicked ? "border-color: {$awayColor}; background: linear-gradient(135deg, {$awayColor}28 0%, #090d16 100%); box-shadow: 0 0 20px {$awayColor}33;" : "border-color: #1e293b; background: rgba(2, 6, 23, 0.7);" ?>">
                            
                            <input type="radio" 
                                   name="picks[<?= $game['id'] ?>]" 
                                   value="<?= htmlspecialchars($awayAbbr) ?>" 
                                   class="sr-only pick-radio"
                                   data-abbr="<?= htmlspecialchars($awayAbbr) ?>"
                                   data-color="<?= htmlspecialchars($awayColor) ?>"
                                   <?= $awayPicked ? 'checked' : '' ?>
                                   <?= $isLocked ? 'disabled' : '' ?>>

                            <!-- Top Bar: Tag & Live Score -->
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-mono font-bold uppercase tracking-wider text-slate-400">Away</span>
                                <?php if ($game['away_score'] !== null): ?>
                                    <span class="text-lg font-mono font-black <?= $isFinal && $game['away_score'] > $game['home_score'] ? 'text-emerald-400' : 'text-slate-300' ?>">
                                        <?= (int) $game['away_score'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Helmet & Logo Artwork -->
                            <div class="flex items-center justify-center gap-2 my-1">
                                <div class="w-12 h-10 flex items-center justify-center">
                                    <?= TeamData::renderHelmet($awayAbbr, 48, 36) ?>
                                </div>
                                <img src="<?= htmlspecialchars($awayTeam['logo']) ?>" 
                                     alt="<?= htmlspecialchars($awayTeam['name']) ?>" 
                                     class="w-10 h-10 object-contain drop-shadow-md"
                                     loading="lazy">
                            </div>

                            <!-- Team Text & Nickname -->
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

                            <!-- Visual Picked Badge Indicator -->
                            <div class="pick-badge mt-2 pt-2 border-t border-slate-800/80 text-center <?= $awayPicked ? 'block' : 'hidden' ?>">
                                <span class="inline-flex items-center justify-center gap-1 w-full py-1 rounded-md text-[11px] font-black uppercase tracking-wider bg-emerald-500 text-slate-950 shadow-md">
                                    <span>✓</span> YOUR PICK
                                </span>
                            </div>
                        </label>

                        <!-- Home Team Selection Card -->
                        <label class="team-card relative flex flex-col p-3 rounded-xl border-2 cursor-pointer transition-all select-none group
                            <?= $isLocked ? 'pointer-events-none' : 'hover:scale-[1.02]' ?>
                            <?= $hasPick && !$homePicked ? 'opacity-50' : 'opacity-100' ?>"
                            style="<?= $homePicked ? "border-color: {$homeColor}; background: linear-gradient(135deg, {$homeColor}28 0%, #090d16 100%); box-shadow: 0 0 20px {$homeColor}33;" : "border-color: #1e293b; background: rgba(2, 6, 23, 0.7);" ?>">
                            
                            <input type="radio" 
                                   name="picks[<?= $game['id'] ?>]" 
                                   value="<?= htmlspecialchars($homeAbbr) ?>" 
                                   class="sr-only pick-radio"
                                   data-abbr="<?= htmlspecialchars($homeAbbr) ?>"
                                   data-color="<?= htmlspecialchars($homeColor) ?>"
                                   <?= $homePicked ? 'checked' : '' ?>
                                   <?= $isLocked ? 'disabled' : '' ?>>

                            <!-- Top Bar: Tag & Live Score -->
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-mono font-bold uppercase tracking-wider text-slate-400">Home</span>
                                <?php if ($game['home_score'] !== null): ?>
                                    <span class="text-lg font-mono font-black <?= $isFinal && $game['home_score'] > $game['away_score'] ? 'text-emerald-400' : 'text-slate-300' ?>">
                                        <?= (int) $game['home_score'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Helmet & Logo Artwork -->
                            <div class="flex items-center justify-center gap-2 my-1">
                                <div class="w-12 h-10 flex items-center justify-center">
                                    <?= TeamData::renderHelmet($homeAbbr, 48, 36) ?>
                                </div>
                                <img src="<?= htmlspecialchars($homeTeam['logo']) ?>" 
                                     alt="<?= htmlspecialchars($homeTeam['name']) ?>" 
                                     class="w-10 h-10 object-contain drop-shadow-md"
                                     loading="lazy">
                            </div>

                            <!-- Team Text & Nickname -->
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

                            <!-- Visual Picked Badge Indicator -->
                            <div class="pick-badge mt-2 pt-2 border-t border-slate-800/80 text-center <?= $homePicked ? 'block' : 'hidden' ?>">
                                <span class="inline-flex items-center justify-center gap-1 w-full py-1 rounded-md text-[11px] font-black uppercase tracking-wider bg-emerald-500 text-slate-950 shadow-md">
                                    <span>✓</span> YOUR PICK
                                </span>
                            </div>
                        </label>

                    </div>

                </div>
            <?php endforeach; ?>
        </div>

        <!-- Tiebreaker Input Section -->
        <div class="p-6 rounded-2xl border border-amber-500/40 bg-gradient-to-br from-slate-900 via-slate-900 to-amber-950/20 shadow-xl">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-5">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs font-mono uppercase tracking-wider text-amber-400 font-bold">Official Tiebreaker Question</span>
                        <span class="text-xs px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 font-bold">MNF Total Points</span>
                    </div>
                    <h4 class="text-base sm:text-lg font-bold text-white">Monday Night Football Combined Total Score</h4>
                    <p class="text-xs text-slate-400 mt-1 max-w-xl leading-relaxed">
                        Predict the combined total final score for the designated Monday Night Football game. The player with the lowest absolute point delta wins tiebreaks in the weekly standings.
                    </p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <div class="relative">
                        <input type="number" 
                               name="mnf_total_points" 
                               min="0" 
                               max="150" 
                               placeholder="e.g. 48"
                               value="<?= htmlspecialchars((string) ($entry['mnf_total_points_prediction'] ?? '')) ?>"
                               class="w-32 px-4 py-3 rounded-xl bg-slate-950 border border-slate-700 text-white font-mono text-center text-lg font-black focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/40 shadow-inner">
                    </div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider">Total Points</span>
                </div>
            </div>
        </div>

        <!-- Form Submit Bar -->
        <div class="sticky bottom-16 md:bottom-6 z-30 p-4 rounded-2xl bg-slate-900/95 border border-slate-800 backdrop-blur shadow-2xl flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2 text-xs text-slate-400">
                <span>🔒</span>
                <span>Individual games lock automatically at their scheduled kickoff time.</span>
            </div>
            <button type="submit" 
                    class="w-full sm:w-auto px-8 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-sm uppercase tracking-wider transition shadow-lg hover:shadow-emerald-500/25 flex items-center justify-center gap-2">
                <span>💾</span>
                <span>Save Week <?= htmlspecialchars((string) $week) ?> Picks</span>
            </button>
        </div>

    </form>

</div>

<script>
// Interactive zero-delay team selection feedback
document.addEventListener('DOMContentLoaded', function () {
    const grid = document.querySelectorAll('[data-game-id]');

    grid.forEach(container => {
        const labels = container.querySelectorAll('.team-card');

        labels.forEach(label => {
            label.addEventListener('click', function () {
                const radio = this.querySelector('.pick-radio');
                if (!radio || radio.disabled) return;

                // Reset sibling cards in this matchup
                labels.forEach(l => {
                    l.style.borderColor = '#1e293b';
                    l.style.background = 'rgba(2, 6, 23, 0.7)';
                    l.style.boxShadow = 'none';
                    l.classList.add('opacity-50');
                    l.classList.remove('opacity-100');
                    const badge = l.querySelector('.pick-badge');
                    if (badge) badge.classList.add('hidden');
                });

                // Highlight chosen card
                const color = radio.getAttribute('data-color') || '#10b981';
                this.style.borderColor = color;
                this.style.background = `linear-gradient(135deg, ${color}28 0%, #090d16 100%)`;
                this.style.boxShadow = `0 0 22px ${color}40`;
                this.classList.remove('opacity-50');
                this.classList.add('opacity-100');

                const badge = this.querySelector('.pick-badge');
                if (badge) badge.classList.remove('hidden');

                radio.checked = true;
            });
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';

