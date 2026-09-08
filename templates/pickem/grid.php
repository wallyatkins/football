<?php
use WallyFootball\Support\TeamData;

ob_start();
$entryStatus = $entry['payment_status'] ?? 'none';
$isPaid = in_array($entryStatus, ['paid', 'exempt'], true);
$isUserLocked = !empty($entry['is_locked']);
$isCommissioner = in_array($user['role'] ?? '', ['admin', 'commissioner'], true);

$venmoUrl = 'https://account.venmo.com/u/WallyAtkins';
$payPalUrl = 'https://paypal.me/WallyAtkins';
$cashAppUrl = 'https://cash.app/$WallyAtkins';

// Designated Tiebreaker Game Data
$tbAwayData = !empty($tiebreakerGame) ? TeamData::get($tiebreakerGame['away_team']) : null;
$tbHomeData = !empty($tiebreakerGame) ? TeamData::get($tiebreakerGame['home_team']) : null;
$tbMatchupLabel = ($tbAwayData && $tbHomeData) ? "{$tbAwayData['name']} @ {$tbHomeData['name']}" : "Official Tiebreaker Game";
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

        <!-- Subtle Week Switcher -->
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

            <?php if (!empty($isCommissioner)): ?>
                <a href="/admin/payments?week=<?= $week ?>&season=<?= $season ?>" 
                   class="px-3 py-1.5 text-xs font-bold rounded-lg bg-purple-900/60 border border-purple-500/40 text-purple-300 hover:bg-purple-800 hover:text-white transition flex items-center gap-1.5 shadow-sm">
                    <span>👑</span>
                    <span class="hidden sm:inline">Commissioner Portal</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- User Pick Lock & Payment Callout -->
    <?php if ($isUserLocked): ?>
        <div class="p-6 rounded-2xl bg-gradient-to-br from-emerald-950/40 via-slate-900 to-slate-950 border border-emerald-500/40 shadow-xl space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-5">
                <div class="flex items-start gap-4">
                    <div class="p-3 rounded-xl bg-emerald-500/20 text-emerald-300 text-3xl border border-emerald-500/30 shrink-0">
                        🔒
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h2 class="text-lg font-black text-white">Your Week <?= $week ?> Picks Are Locked In!</h2>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 uppercase tracking-wider">
                                LOCKED &amp; SUBMITTED
                            </span>
                            <?php if ($isPaid): ?>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-purple-500/20 text-purple-300 border border-purple-500/40 uppercase tracking-wider">
                                    ✓ STAKE VERIFIED
                                </span>
                            <?php else: ?>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 uppercase tracking-wider">
                                    AWAITING $10 PAYMENT
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-slate-300 mt-1 leading-relaxed">
                            <?php if ($isPaid): ?>
                                Your picks and $10.00 entry fee are verified. You are fully active in this week's prize pool!
                            <?php else: ?>
                                Your picks are officially recorded! Please send your <strong>$10.00</strong> entry stake to Commissioner Wally below so your picks count toward this week's cash pot.
                            <?php endif; ?>
                        </p>
                        <div class="mt-2.5 inline-flex items-center gap-2 px-3 py-1 rounded-lg bg-slate-950/90 border border-slate-700/80 text-xs">
                            <span class="text-slate-400">Payment Memo Note:</span>
                            <span class="font-mono font-bold text-amber-300 select-all">Pickem - <?= htmlspecialchars($user['username'] ?? 'username') ?> - Week <?= $week ?></span>
                        </div>
                    </div>
                </div>

                <!-- Verified Payment Action Buttons -->
                <?php if (!$isPaid): ?>
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
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Pre-Lock Reminder Banner -->
        <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900/90 to-slate-950 border border-amber-500/30 shadow-xl space-y-3">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-start gap-3.5">
                    <div class="p-2.5 rounded-xl bg-amber-500/10 text-amber-400 text-2xl border border-amber-500/20 shrink-0">
                        🏈
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-white">Weekly Pick'em Stake: $10.00</h3>
                            <span class="text-[10px] font-mono uppercase tracking-wider font-bold px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 border border-slate-700">
                                Unlocked Draft
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5 leading-relaxed">
                            Pick a winner for each matchup and enter the tiebreaker score for <strong><?= htmlspecialchars($tbMatchupLabel) ?></strong>. <strong>Once saved and submitted, your picks are locked in for the week.</strong>
                        </p>
                        <div class="mt-2 text-[11px] text-slate-400">
                            Remember to add note <span class="font-mono text-amber-300 font-bold">Pickem - <?= htmlspecialchars($user['username'] ?? 'username') ?> - Week <?= $week ?></span> when sending your $10 stake.
                        </div>
                    </div>
                </div>

                <!-- Payment Channels -->
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="<?= htmlspecialchars($venmoUrl) ?>" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#008CFF]/15 hover:bg-[#008CFF]/25 border border-[#008CFF]/40 text-[#38bdf8] font-bold text-xs transition">
                        <span>📱</span> Venmo
                    </a>
                    <a href="<?= htmlspecialchars($payPalUrl) ?>" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#0079C1]/15 hover:bg-[#0079C1]/25 border border-[#0079C1]/40 text-[#38bdf8] font-bold text-xs transition">
                        <span>💳</span> PayPal
                    </a>
                    <a href="<?= htmlspecialchars($cashAppUrl) ?>" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#00D632]/15 hover:bg-[#00D632]/25 border border-[#00D632]/40 text-[#4ade80] font-bold text-xs transition">
                        <span>⚡</span> Cash App
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Real-Time Validation Notification Box (Dynamic) -->
    <div id="validationAlert" class="hidden p-4 rounded-xl bg-rose-500/15 border-2 border-rose-500/60 shadow-xl transition-all duration-300">
        <div class="flex items-start gap-3 text-rose-300 text-xs font-semibold">
            <span class="text-xl">⚠️</span>
            <div id="validationAlertMessage" class="flex-1 space-y-1">
                <!-- Injected via JavaScript -->
            </div>
        </div>
    </div>

    <!-- Pick'em Form -->
    <form action="/pickem/save" method="POST" id="pickemForm" class="space-y-6">
        <input type="hidden" name="season_year" value="<?= htmlspecialchars((string) $season) ?>">
        <input type="hidden" name="week_number" value="<?= htmlspecialchars((string) $week) ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <?php foreach ($games as $game): ?>
                <?php
                $isKickoffLocked = (bool) $game['is_locked'];
                $isMnf = (bool) $game['is_mnf'];
                $userPick = $game['user_pick'];
                $kickoff = new DateTimeImmutable($game['kickoff_time']);
                $kickoffEt = $kickoff->setTimezone(new DateTimeZone('America/New_York'))->format('D, M j @ g:i A T');
                $isFinal = ($game['status'] === 'final');
                $inProgress = ($game['status'] === 'in_progress');

                // Card interaction lock state
                $cardDisabled = $isKickoffLocked || $isUserLocked;

                // Away Team Data
                $awayAbbr = $game['away_team'];
                $awayTeam = TeamData::get($awayAbbr);
                $awayColor = $awayTeam['color'];
                $awayPicked = ($userPick === $awayAbbr);

                // Home Team Data
                $homeAbbr = $game['home_team'];
                $homeTeam = TeamData::get($homeAbbr);
                $homeColor = $homeTeam['color'];
                $homePicked = ($userPick === $homeAbbr);

                $hasPick = $awayPicked || $homePicked;
                ?>
                <div class="matchup-card rounded-2xl border transition-all duration-200 overflow-hidden shadow-lg <?= $isMnf ? 'border-amber-500/60 bg-slate-900/90 ring-1 ring-amber-500/30' : 'border-slate-800/80 bg-slate-900/70' ?>"
                     data-game-id="<?= $game['id'] ?>"
                     data-unlocked="<?= $cardDisabled ? 'false' : 'true' ?>"
                     data-away-abbr="<?= htmlspecialchars($awayAbbr) ?>"
                     data-away-name="<?= htmlspecialchars($awayTeam['name']) ?>"
                     data-home-abbr="<?= htmlspecialchars($homeAbbr) ?>"
                     data-home-name="<?= htmlspecialchars($homeTeam['name']) ?>">
                    
                    <!-- Matchup Broadcast Header -->
                    <div class="flex items-center justify-between px-4 py-2.5 bg-slate-950/70 border-b border-slate-800/80 text-xs">
                        <div class="flex items-center gap-2 text-slate-400">
                            <span class="font-mono text-[11px]"><?= htmlspecialchars($kickoffEt) ?></span>
                            <?php if ($isMnf): ?>
                                <span class="px-2 py-0.5 rounded text-[10px] font-black bg-amber-500 text-slate-950 uppercase tracking-wider shadow-sm flex items-center gap-1">
                                    ⭐ Official Tiebreaker Game
                                </span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($isFinal): ?>
                                <span class="px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-800 text-slate-300 border border-slate-700">FINAL</span>
                            <?php elseif ($inProgress): ?>
                                <span class="px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 animate-pulse">LIVE</span>
                            <?php elseif ($isKickoffLocked): ?>
                                <span class="px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">🔒 KICKOFF</span>
                            <?php elseif ($isUserLocked): ?>
                                <span class="px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">LOCKED</span>
                            <?php else: ?>
                                <span class="text-slate-500 text-[11px] font-medium">Open</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Teams Selection Grid -->
                    <div class="p-3.5 grid grid-cols-2 gap-3.5">
                        
                        <!-- Away Team Card -->
                        <label class="team-card relative flex flex-col items-center justify-between p-3.5 rounded-xl border-2 transition-all select-none group
                            <?= $cardDisabled ? 'pointer-events-none' : 'cursor-pointer hover:scale-[1.02]' ?>
                            <?= $hasPick && !$awayPicked ? 'opacity-40' : 'opacity-100' ?>"
                            style="<?= $awayPicked ? "border-color: {$awayColor}; background: linear-gradient(135deg, {$awayColor}28 0%, #090d16 100%); box-shadow: 0 0 22px {$awayColor}40;" : "border-color: #1e293b; background: rgba(2, 6, 23, 0.7);" ?>">
                            
                            <input type="radio" 
                                   name="picks[<?= $game['id'] ?>]" 
                                   value="<?= htmlspecialchars($awayAbbr) ?>" 
                                   class="sr-only pick-radio"
                                   data-abbr="<?= htmlspecialchars($awayAbbr) ?>"
                                   data-name="<?= htmlspecialchars($awayTeam['name']) ?>"
                                   data-logo="<?= htmlspecialchars($awayTeam['logo']) ?>"
                                   data-color="<?= htmlspecialchars($awayColor) ?>"
                                   <?= $awayPicked ? 'checked' : '' ?>
                                   <?= $cardDisabled ? 'disabled' : '' ?>>

                            <!-- Top: Away Tag & Score -->
                            <div class="w-full flex items-center justify-between mb-1">
                                <span class="text-[10px] font-mono font-bold uppercase tracking-wider text-slate-400">Away</span>
                                <?php if ($game['away_score'] !== null): ?>
                                    <span class="text-base font-mono font-black <?= $isFinal && $game['away_score'] > $game['home_score'] ? 'text-emerald-400' : 'text-slate-300' ?>">
                                        <?= (int) $game['away_score'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Official ESPN Logo -->
                            <div class="my-2 h-16 flex items-center justify-center">
                                <img src="<?= htmlspecialchars($awayTeam['logo']) ?>" 
                                     alt="<?= htmlspecialchars($awayTeam['name']) ?>" 
                                     class="w-14 h-14 object-contain filter drop-shadow-md transition-transform duration-200 group-hover:scale-110"
                                     loading="lazy">
                            </div>

                            <!-- Team Name & City -->
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

                            <!-- Picked Indicator Badge -->
                            <div class="pick-badge w-full mt-2 pt-2 border-t border-slate-800/80 text-center <?= $awayPicked ? 'block' : 'hidden' ?>">
                                <span class="inline-flex items-center justify-center gap-1 w-full py-1 rounded-md text-[10px] font-black uppercase tracking-wider <?= $isUserLocked ? 'bg-emerald-600 text-white shadow' : 'bg-emerald-500 text-slate-950 shadow-md' ?>">
                                    <span><?= $isUserLocked ? '🔒' : '✓' ?></span>
                                    <span><?= $isUserLocked ? 'LOCKED IN PICK' : 'YOUR PICK' ?></span>
                                </span>
                            </div>
                        </label>

                        <!-- Home Team Card -->
                        <label class="team-card relative flex flex-col items-center justify-between p-3.5 rounded-xl border-2 transition-all select-none group
                            <?= $cardDisabled ? 'pointer-events-none' : 'cursor-pointer hover:scale-[1.02]' ?>
                            <?= $hasPick && !$homePicked ? 'opacity-40' : 'opacity-100' ?>"
                            style="<?= $homePicked ? "border-color: {$homeColor}; background: linear-gradient(135deg, {$homeColor}28 0%, #090d16 100%); box-shadow: 0 0 22px {$homeColor}40;" : "border-color: #1e293b; background: rgba(2, 6, 23, 0.7);" ?>">
                            
                            <input type="radio" 
                                   name="picks[<?= $game['id'] ?>]" 
                                   value="<?= htmlspecialchars($homeAbbr) ?>" 
                                   class="sr-only pick-radio"
                                   data-abbr="<?= htmlspecialchars($homeAbbr) ?>"
                                   data-name="<?= htmlspecialchars($homeTeam['name']) ?>"
                                   data-logo="<?= htmlspecialchars($homeTeam['logo']) ?>"
                                   data-color="<?= htmlspecialchars($homeColor) ?>"
                                   <?= $homePicked ? 'checked' : '' ?>
                                   <?= $cardDisabled ? 'disabled' : '' ?>>

                            <!-- Top: Home Tag & Score -->
                            <div class="w-full flex items-center justify-between mb-1">
                                <span class="text-[10px] font-mono font-bold uppercase tracking-wider text-slate-400">Home</span>
                                <?php if ($game['home_score'] !== null): ?>
                                    <span class="text-base font-mono font-black <?= $isFinal && $game['home_score'] > $game['away_score'] ? 'text-emerald-400' : 'text-slate-300' ?>">
                                        <?= (int) $game['home_score'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Official ESPN Logo -->
                            <div class="my-2 h-16 flex items-center justify-center">
                                <img src="<?= htmlspecialchars($homeTeam['logo']) ?>" 
                                     alt="<?= htmlspecialchars($homeTeam['name']) ?>" 
                                     class="w-14 h-14 object-contain filter drop-shadow-md transition-transform duration-200 group-hover:scale-110"
                                     loading="lazy">
                            </div>

                            <!-- Team Name & City -->
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

                            <!-- Picked Indicator Badge -->
                            <div class="pick-badge w-full mt-2 pt-2 border-t border-slate-800/80 text-center <?= $homePicked ? 'block' : 'hidden' ?>">
                                <span class="inline-flex items-center justify-center gap-1 w-full py-1 rounded-md text-[10px] font-black uppercase tracking-wider <?= $isUserLocked ? 'bg-emerald-600 text-white shadow' : 'bg-emerald-500 text-slate-950 shadow-md' ?>">
                                    <span><?= $isUserLocked ? '🔒' : '✓' ?></span>
                                    <span><?= $isUserLocked ? 'LOCKED IN PICK' : 'YOUR PICK' ?></span>
                                </span>
                            </div>
                        </label>

                    </div>

                </div>
            <?php endforeach; ?>
        </div>

        <!-- Tiebreaker Input Section -->
        <div id="mnfTiebreakerContainer" class="p-6 rounded-2xl border transition-all duration-300 border-amber-500/40 bg-gradient-to-br from-slate-900 via-slate-900 to-amber-950/20 shadow-xl">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-5">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs font-mono uppercase tracking-wider text-amber-400 font-bold">Official Tiebreaker Question</span>
                        <span class="text-xs px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 font-bold">Game of the Week</span>
                    </div>
                    <?php if (isset($tbAwayData) && isset($tbHomeData)): ?>
                        <div class="flex items-center gap-3 my-1.5 flex-wrap">
                            <div class="flex items-center gap-2">
                                <img src="<?= htmlspecialchars($tbAwayData['logo']) ?>" alt="<?= htmlspecialchars($tbAwayData['name']) ?>" class="w-7 h-7 object-contain">
                                <span class="font-bold text-white text-base sm:text-lg"><?= htmlspecialchars($tbAwayData['name']) ?></span>
                            </div>
                            <span class="text-slate-400 font-semibold text-sm">@</span>
                            <div class="flex items-center gap-2">
                                <img src="<?= htmlspecialchars($tbHomeData['logo']) ?>" alt="<?= htmlspecialchars($tbHomeData['name']) ?>" class="w-7 h-7 object-contain">
                                <span class="font-bold text-white text-base sm:text-lg"><?= htmlspecialchars($tbHomeData['name']) ?></span>
                            </div>
                        </div>
                        <p class="text-xs text-slate-400 mt-1 max-w-xl leading-relaxed">
                            Predict the combined total final score for <strong><?= htmlspecialchars($tbAwayData['name']) ?> @ <?= htmlspecialchars($tbHomeData['name']) ?></strong> (our randomly selected tiebreaker game of the week). If two or more players tie in weekly picks, lowest absolute point delta wins the prize pot!
                        </p>
                    <?php else: ?>
                        <h4 class="text-base sm:text-lg font-bold text-white">Weekly Tiebreaker Combined Total Score</h4>
                        <p class="text-xs text-slate-400 mt-1 max-w-xl leading-relaxed">
                            Predict the combined total final score for our randomly selected game of the week. Lowest absolute point delta breaks ties!
                        </p>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <div class="relative">
                        <input type="number" 
                               id="mnfTotalPointsInput"
                               name="mnf_total_points" 
                               min="1" 
                               max="150" 
                               placeholder="e.g. 48"
                               value="<?= htmlspecialchars((string) ($entry['mnf_total_points_prediction'] ?? '')) ?>"
                               <?= $isUserLocked ? 'disabled' : '' ?>
                               class="w-32 px-4 py-3 rounded-xl bg-slate-950 border border-slate-700 text-white font-mono text-center text-lg font-black focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/40 shadow-inner transition">
                    </div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider">Total Points</span>
                </div>
            </div>
        </div>

        <!-- Bottom Action Bar -->
        <div class="sticky bottom-16 md:bottom-6 z-30 p-4 rounded-2xl bg-slate-900/95 border border-slate-800 backdrop-blur shadow-2xl flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2 text-xs text-slate-400">
                <span>🔒</span>
                <?php if ($isUserLocked): ?>
                    <span class="text-emerald-400 font-semibold">Your Week <?= $week ?> picks are locked in. Contact Commissioner Wally if you need changes before kickoff.</span>
                <?php else: ?>
                    <span>Selections lock permanently once submitted. Review your picks before locking them in.</span>
                <?php endif; ?>
            </div>

            <?php if ($isUserLocked): ?>
                <div class="flex items-center gap-3">
                    <a href="/pickem/standings?week=<?= $week ?>&season=<?= $season ?>" 
                       class="w-full sm:w-auto px-6 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white font-bold text-xs uppercase tracking-wider transition shadow flex items-center justify-center gap-2">
                        <span>📊</span>
                        <span>View Live Standings</span>
                    </a>
                </div>
            <?php else: ?>
                <button type="button" 
                        id="btnReviewPicks"
                        class="w-full sm:w-auto px-8 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-sm uppercase tracking-wider transition shadow-lg hover:shadow-emerald-500/25 flex items-center justify-center gap-2">
                    <span>🔍</span>
                    <span>Review &amp; Submit Week <?= htmlspecialchars((string) $week) ?> Picks</span>
                </button>
            <?php endif; ?>
        </div>

    </form>

</div>

<!-- Review & Confirmation Modal -->
<div id="reviewModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-700/80 rounded-2xl max-w-2xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        
        <!-- Modal Header -->
        <div class="p-5 border-b border-slate-800 bg-slate-950/80 flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20 uppercase">
                        Review Selections
                    </span>
                    <span class="text-xs text-slate-400">Week <?= $week ?></span>
                </div>
                <h3 class="text-xl font-black text-white">Review Your Picks Before Lock-In</h3>
            </div>
            <button type="button" id="btnModalCloseX" class="text-slate-400 hover:text-white text-2xl font-bold leading-none p-2">&times;</button>
        </div>

        <!-- Warning Callout -->
        <div class="p-4 bg-amber-500/10 border-b border-amber-500/20 text-xs text-amber-300 flex items-start gap-2.5">
            <span class="text-lg">⚠️</span>
            <div>
                <strong class="font-bold">Final Submission Notice:</strong>
                <span>Once submitted, your picks for Week <?= $week ?> will be <strong>locked in</strong> and cannot be modified. Please inspect your picks below before confirming.</span>
            </div>
        </div>

        <!-- Picks Summary List (Scrollable) -->
        <div class="p-5 overflow-y-auto space-y-3 divide-y divide-slate-800/60" id="reviewPicksList">
            <!-- Populated via JS -->
        </div>

        <!-- Tiebreaker Summary -->
        <div class="p-4 bg-slate-950/60 border-t border-slate-800 flex items-center justify-between text-xs">
            <div>
                <span class="text-slate-400 font-semibold">Weekly Tiebreaker (Game of the Week):</span>
                <span class="text-slate-300 block text-[11px]"><?= htmlspecialchars($tbMatchupLabel) ?></span>
            </div>
            <span id="reviewMnfPoints" class="font-mono text-base font-black text-amber-400">--</span>
        </div>

        <!-- Modal Footer Actions -->
        <div class="p-4 border-t border-slate-800 bg-slate-950/90 flex flex-col-reverse sm:flex-row items-center justify-between gap-3">
            <button type="button" id="btnCancelModal" 
                    class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs uppercase tracking-wider transition">
                ✏️ Make Changes
            </button>
            <button type="button" id="btnConfirmLockIn" 
                    class="w-full sm:w-auto px-7 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs uppercase tracking-wider shadow-lg hover:shadow-emerald-500/30 transition flex items-center justify-center gap-2">
                <span>🔒</span>
                <span>Confirm &amp; Lock In My Picks</span>
            </button>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('pickemForm');
    const reviewBtn = document.getElementById('btnReviewPicks');
    const reviewModal = document.getElementById('reviewModal');
    const btnCancelModal = document.getElementById('btnCancelModal');
    const btnModalCloseX = document.getElementById('btnModalCloseX');
    const btnConfirmLockIn = document.getElementById('btnConfirmLockIn');
    const validationAlert = document.getElementById('validationAlert');
    const validationAlertMessage = document.getElementById('validationAlertMessage');
    const mnfInput = document.getElementById('mnfTotalPointsInput');
    const mnfContainer = document.getElementById('mnfTiebreakerContainer');
    const reviewPicksList = document.getElementById('reviewPicksList');
    const reviewMnfPoints = document.getElementById('reviewMnfPoints');

    // 1. Dynamic selection & radio button handling
    const matchups = document.querySelectorAll('.matchup-card');
    matchups.forEach(card => {
        const labels = card.querySelectorAll('.team-card');
        labels.forEach(label => {
            label.addEventListener('click', function () {
                const radio = this.querySelector('.pick-radio');
                if (!radio || radio.disabled) return;

                // Reset siblings
                labels.forEach(l => {
                    l.style.borderColor = '#1e293b';
                    l.style.background = 'rgba(2, 6, 23, 0.7)';
                    l.style.boxShadow = 'none';
                    l.classList.add('opacity-40');
                    l.classList.remove('opacity-100');
                    const badge = l.querySelector('.pick-badge');
                    if (badge) badge.classList.add('hidden');
                });

                // Highlight selected
                const color = radio.getAttribute('data-color') || '#10b981';
                this.style.borderColor = color;
                this.style.background = `linear-gradient(135deg, ${color}28 0%, #090d16 100%)`;
                this.style.boxShadow = `0 0 22px ${color}40`;
                this.classList.remove('opacity-40');
                this.classList.add('opacity-100');

                const badge = this.querySelector('.pick-badge');
                if (badge) badge.classList.remove('hidden');

                radio.checked = true;

                // Remove red error highlight if present
                card.classList.remove('ring-4', 'ring-rose-500', 'animate-pulse');
                if (validationAlert) validationAlert.classList.add('hidden');
            });
        });
    });

    if (mnfInput) {
        mnfInput.addEventListener('input', function () {
            if (parseInt(this.value, 10) > 0) {
                mnfContainer.classList.remove('ring-4', 'ring-rose-500', 'animate-pulse');
                if (validationAlert) validationAlert.classList.add('hidden');
            }
        });
    }

    // 2. Client-side Validation & Review modal trigger
    if (reviewBtn) {
        reviewBtn.addEventListener('click', function (e) {
            e.preventDefault();

            // Clear previous alerts
            validationAlert.classList.add('hidden');
            validationAlertMessage.innerHTML = '';
            document.querySelectorAll('.ring-rose-500').forEach(el => el.classList.remove('ring-4', 'ring-rose-500', 'animate-pulse'));

            const unlockedMatchups = document.querySelectorAll('.matchup-card[data-unlocked="true"]');
            let unpickedCards = [];

            unlockedMatchups.forEach(card => {
                const checked = card.querySelector('.pick-radio:checked');
                if (!checked) {
                    unpickedCards.push(card);
                }
            });

            const mnfVal = mnfInput ? parseInt(mnfInput.value.trim(), 10) : 0;
            const mnfMissing = !mnfInput || isNaN(mnfVal) || mnfVal <= 0;

            if (unpickedCards.length > 0 || mnfMissing) {
                // Build human-friendly message
                let errorHtml = '';
                if (unpickedCards.length > 0) {
                    errorHtml += `<div><strong>Unpicked Games:</strong> You have <strong>${unpickedCards.length}</strong> unpicked matchup(s) remaining. Please pick a winner for every game.</div>`;
                    unpickedCards.forEach(c => c.classList.add('ring-4', 'ring-rose-500', 'animate-pulse'));
                }
                if (mnfMissing) {
                    const tbLabel = <?= json_encode($tbMatchupLabel) ?>;
                    errorHtml += `<div><strong>Missing Tiebreaker:</strong> Please enter your predicted combined total points for <strong>${tbLabel}</strong>.</div>`;
                    if (mnfContainer) mnfContainer.classList.add('ring-4', 'ring-rose-500', 'animate-pulse');
                }

                validationAlertMessage.innerHTML = errorHtml;
                validationAlert.classList.remove('hidden');

                // Smooth scroll to the first missing element
                if (unpickedCards.length > 0) {
                    unpickedCards[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else if (mnfMissing && mnfContainer) {
                    mnfContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    mnfInput.focus();
                }

                return;
            }

            // If valid, populate Review Modal
            reviewPicksList.innerHTML = '';
            let pickIndex = 1;

            matchups.forEach(card => {
                const checkedRadio = card.querySelector('.pick-radio:checked');
                if (!checkedRadio) return;

                const teamAbbr = checkedRadio.getAttribute('data-abbr');
                const teamName = checkedRadio.getAttribute('data-name');
                const teamLogo = checkedRadio.getAttribute('data-logo');
                const awayName = card.getAttribute('data-away-name');
                const homeName = card.getAttribute('data-home-name');
                const awayAbbr = card.getAttribute('data-away-abbr');
                const homeAbbr = card.getAttribute('data-home-abbr');

                const vsOpponent = (teamAbbr === awayAbbr) ? `vs ${homeName} (${homeAbbr})` : `@ ${awayName} (${awayAbbr})`;

                const row = document.createElement('div');
                row.className = 'pt-2.5 flex items-center justify-between gap-4 text-xs';
                row.innerHTML = `
                    <div class="flex items-center gap-3">
                        <span class="font-mono text-slate-500 text-[11px] w-4">${pickIndex++}.</span>
                        <img src="${teamLogo}" alt="${teamName}" class="w-8 h-8 object-contain">
                        <div>
                            <span class="font-bold text-white text-sm">${teamName}</span>
                            <span class="text-slate-400 block text-[11px]">${vsOpponent}</span>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 rounded font-mono font-bold text-xs bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                        ${teamAbbr}
                    </span>
                `;
                reviewPicksList.appendChild(row);
            });

            if (reviewMnfPoints) {
                reviewMnfPoints.textContent = mnfVal + ' Points';
            }

            // Show Modal
            reviewModal.classList.remove('hidden');
            reviewModal.classList.add('flex');
        });
    }

    // Modal Close
    function closeModal() {
        reviewModal.classList.add('hidden');
        reviewModal.classList.remove('flex');
    }

    if (btnCancelModal) btnCancelModal.addEventListener('click', closeModal);
    if (btnModalCloseX) btnModalCloseX.addEventListener('click', closeModal);

    // Confirm & Lock In Submit
    if (btnConfirmLockIn) {
        btnConfirmLockIn.addEventListener('click', function () {
            btnConfirmLockIn.disabled = true;
            btnConfirmLockIn.innerHTML = '<span>⏳</span> Submitting &amp; Locking...';
            form.submit();
        });
    }
});
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
