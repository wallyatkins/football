<?php
use WallyFootball\Support\TeamData;

ob_start();
$entryStatus = $entry['payment_status'] ?? 'none';
$isPaid = in_array($entryStatus, ['paid', 'exempt'], true);
$isUserLocked = !empty($isWeekLocked);
$hasConfirmedPicks = !empty($entry['is_locked']);
$firstKickoffFormatted = $firstKickoffFormatted ?? 'Kickoff of Week ' . $week;
$cutoffFormatted = $cutoffFormatted ?? ($firstKickoffFormatted ?? 'Cutoff of Week ' . $week);
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
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[#243247] pb-5">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-[#162235] text-[#EAB308] border border-[#243247] uppercase tracking-wider">NFL Regular Season</span>
                <span class="text-xs font-mono text-[#94A3B8]">Season <?= htmlspecialchars((string) $season) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-[#F8FAFC] flex items-center gap-3">
                <span>Week <?= htmlspecialchars((string) $week) ?> Matchups</span>
                <span class="text-xs font-mono font-semibold px-2.5 py-0.5 rounded-full bg-[#162235] border border-[#243247] text-[#94A3B8] tabular-nums">
                    <?= count($games) ?> Games
                </span>
            </h1>
        </div>

        <!-- Action Bar -->
        <div class="flex items-center gap-2 flex-wrap">
            <span class="px-3 py-1.5 text-xs font-black font-mono rounded-lg bg-[#EAB308] text-[#0B1626] shadow-sm uppercase tracking-wider">
                Week <?= $week ?>
            </span>
            <button type="button" 
                    id="btnOpenHowItWorks"
                    class="px-3 py-1.5 text-xs font-bold rounded-lg bg-[#162235] hover:bg-[#0B1626] text-[#94A3B8] hover:text-[#F8FAFC] border border-[#243247] transition">
                Rules &amp; Scoring
            </button>
            <?php if (!empty($isCommissioner)): ?>
                <a href="/admin/payments?week=<?= $week ?>&season=<?= $season ?>" 
                   class="px-3 py-1.5 text-xs font-bold rounded-lg bg-purple-950/60 border border-purple-500/40 text-purple-300 hover:bg-purple-900/80 hover:text-white transition shadow-sm">
                    Commissioner
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Center Column Layout (One Game Per Row) -->
    <div class="max-w-3xl mx-auto space-y-6">

    <!-- Week Champion Summary (Collapsible, Auto-collapsed Once Games Kick Off) -->
    <?php 
    $activeCelebrateWeek = !empty($isWeekComplete) ? $week : (!empty($lastCompletedWeek) ? $lastCompletedWeek : null);
    $activeWinnersOverall = !empty($isWeekComplete) ? ($weeklyWinnersOverall ?? []) : ($lastWeekWinnersOverall ?? []);
    $activeWinnersPaid    = !empty($isWeekComplete) ? ($weeklyWinnersPaid ?? []) : ($lastWeekWinnersPaid ?? []);
    $activeWinnersFree    = !empty($isWeekComplete) ? ($weeklyWinnersFree ?? []) : ($lastWeekWinnersFree ?? []);
    $activePotInfo        = !empty($isWeekComplete) ? ($potInfo ?? null) : ($lastWeekPotInfo ?? null);
    $shouldCollapseWinners = ($openGamesCount < count($games)) || !empty($userGradedCount);
    ?>

    <?php if ($activeCelebrateWeek !== null && (!empty($activeWinnersOverall) || !empty($activeWinnersPaid) || !empty($activeWinnersFree))): ?>
        <details class="group rounded-xl border border-[#243247] bg-[#162235] overflow-hidden" <?= $shouldCollapseWinners ? '' : 'open' ?>>
            <summary class="p-3.5 px-4 flex items-center justify-between cursor-pointer list-none select-none hover:bg-[#0B1626]/40 transition">
                <div class="flex items-center gap-2.5">
                    <span class="px-2 py-0.5 rounded font-mono text-[10px] font-black bg-[#EAB308] text-[#0B1626] uppercase tracking-wider">WINNERS CIRCLE</span>
                    <span class="text-xs font-bold text-[#F8FAFC]">Week <?= $activeCelebrateWeek ?> Champions</span>
                </div>
                <div class="flex items-center gap-2 text-[11px] font-mono text-[#94A3B8]">
                    <a href="/pickem/standings?week=<?= $activeCelebrateWeek ?>&season=<?= $season ?>" class="text-[#EAB308] hover:underline mr-2" onclick="event.stopPropagation()">View Board &rarr;</a>
                    <span class="group-open:hidden">Show &darr;</span>
                    <span class="hidden group-open:inline">Hide &uarr;</span>
                </div>
            </summary>

            <div class="p-4 pt-1 border-t border-[#243247] grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                <!-- 1. Overall -->
                <div class="p-3 rounded-lg bg-[#0B1626] border border-[#243247]">
                    <span class="font-mono font-bold text-[10px] uppercase text-[#EAB308] block mb-1">Overall Winner</span>
                    <?php if (!empty($activeWinnersOverall)): ?>
                        <div class="font-bold text-[#F8FAFC] truncate">
                            <?= implode(', ', array_map(fn($w) => htmlspecialchars($w['username']), $activeWinnersOverall)) ?>
                        </div>
                        <span class="text-[11px] text-[#94A3B8] font-mono block mt-0.5 tabular-nums">
                            <?= $activeWinnersOverall[0]['correct_picks'] ?> correct picks
                        </span>
                    <?php else: ?>
                        <span class="text-[#94A3B8] italic">Pending</span>
                    <?php endif; ?>
                </div>

                <!-- 2. Cash -->
                <div class="p-3 rounded-lg bg-[#0B1626] border border-[#243247]">
                    <span class="font-mono font-bold text-[10px] uppercase text-emerald-400 block mb-1">Cash Pool Winner</span>
                    <?php if (!empty($activeWinnersPaid)): ?>
                        <div class="font-bold text-[#F8FAFC] truncate">
                            <?= implode(', ', array_map(fn($w) => htmlspecialchars($w['username']), $activeWinnersPaid)) ?>
                        </div>
                        <span class="text-[11px] text-emerald-400 font-mono block mt-0.5 tabular-nums">
                            <?= $activeWinnersPaid[0]['correct_picks'] ?> correct &bull; $<?= number_format($activePotInfo['payout_per_winner'] / max(1, count($activeWinnersPaid)), 2) ?>
                        </span>
                    <?php else: ?>
                        <span class="text-[#94A3B8] italic">No cash verified</span>
                    <?php endif; ?>
                </div>

                <!-- 3. Free -->
                <div class="p-3 rounded-lg bg-[#0B1626] border border-[#243247]">
                    <span class="font-mono font-bold text-[10px] uppercase text-purple-300 block mb-1">Free / Fun Winner</span>
                    <?php if (!empty($activeWinnersFree)): ?>
                        <div class="font-bold text-[#F8FAFC] truncate">
                            <?= implode(', ', array_map(fn($w) => htmlspecialchars($w['username']), $activeWinnersFree)) ?>
                        </div>
                        <span class="text-[11px] text-[#94A3B8] font-mono block mt-0.5 tabular-nums">
                            <?= $activeWinnersFree[0]['correct_picks'] ?> correct
                        </span>
                    <?php else: ?>
                        <span class="text-[#94A3B8] italic">Pending</span>
                    <?php endif; ?>
                </div>
            </div>
        </details>
    <?php endif; ?>

    <!-- User Ballot Status & Payment Info (Editorial Strip) -->
    <div class="rounded-xl border border-[#243247] bg-[#162235] p-4 text-xs shadow-sm space-y-2">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="font-bold text-sm text-[#F8FAFC]">Week <?= $week ?> Ballot Status:</span>
                <?php if ($isWeekLocked): ?>
                    <span class="px-2.5 py-1 rounded font-mono text-[11px] font-bold bg-[#0B1626] text-[#94A3B8] border border-[#243247] flex items-center gap-1.5 uppercase">
                        <svg class="w-3.5 h-3.5 text-[#94A3B8]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>PICKS LOCKED</span>
                    </span>
                <?php else: ?>
                    <span class="px-2.5 py-1 rounded font-mono text-[11px] font-bold bg-emerald-950/80 text-emerald-400 border border-emerald-500/40 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Auto-saved &bull; Editable until <?= htmlspecialchars($cutoffFormatted) ?></span>
                    </span>
                <?php endif; ?>

                <?php if ($isPaid): ?>
                    <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold bg-emerald-950/80 text-emerald-300 border border-emerald-500/40 uppercase">
                        CASH VERIFIED
                    </span>
                <?php else: ?>
                    <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold bg-[#0B1626] text-[#94A3B8] border border-[#243247] uppercase">
                        AWAITING $10 STAKE
                    </span>
                <?php endif; ?>
            </div>

            <span class="font-mono text-[11px] text-[#94A3B8]">
                <?= $openGamesCount ?> of <?= count($games) ?> games open
            </span>
        </div>

        <p class="text-[#94A3B8] leading-relaxed">
            <?php if ($isWeekLocked): ?>
                All selections for Week <?= $week ?> are locked. Live scores and standings update in real-time.
            <?php else: ?>
                Your selections auto-save immediately as you pick. You can modify any open game until <?= htmlspecialchars($cutoffFormatted) ?>.
            <?php endif; ?>
        </p>

        <?php if (!$isPaid): ?>
            <details class="pt-1.5 border-t border-[#243247] text-[11px]">
                <summary class="cursor-pointer text-[#EAB308] hover:underline select-none font-semibold">
                    Payment Options ($10.00 Cash Stake) &amp; Memo Note &rarr;
                </summary>
                <div class="mt-2.5 p-3 rounded-lg bg-[#0B1626] border border-[#243247] space-y-2">
                    <p class="text-[#94A3B8]">
                        Send your $10 stake to Commissioner Wally. Include memo: <code class="font-mono font-bold text-amber-300 select-all">Pickem - <?= htmlspecialchars($user['username'] ?? 'username') ?> - Week <?= $week ?></code>
                    </p>
                    <div class="flex items-center gap-2 flex-wrap pt-1 font-mono font-bold">
                        <a href="<?= htmlspecialchars($venmoUrl) ?>" target="_blank" rel="noopener noreferrer" class="px-2.5 py-1 rounded bg-[#162235] border border-[#243247] text-sky-400 hover:text-white transition">
                            Venmo (@WallyAtkins)
                        </a>
                        <a href="<?= htmlspecialchars($payPalUrl) ?>" target="_blank" rel="noopener noreferrer" class="px-2.5 py-1 rounded bg-[#162235] border border-[#243247] text-sky-400 hover:text-white transition">
                            PayPal (paypal.me/WallyAtkins)
                        </a>
                        <a href="<?= htmlspecialchars($cashAppUrl) ?>" target="_blank" rel="noopener noreferrer" class="px-2.5 py-1 rounded bg-[#162235] border border-[#243247] text-emerald-400 hover:text-white transition">
                            Cash App ($WallyAtkins)
                        </a>
                    </div>
                </div>
            </details>
        <?php endif; ?>
    </div>

    <!-- User Live Performance Scorecard (When any game is final) -->
    <?php if (!empty($userGradedCount) && $userGradedCount > 0): ?>
        <div class="p-4 rounded-xl bg-[#162235] border border-[#243247] flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <div>
                <span class="font-bold text-[#F8FAFC] block mb-0.5">Week <?= $week ?> Performance</span>
                <span class="text-[#94A3B8] font-mono tabular-nums">
                    <?= $userGradedCount ?> of <?= count($games) ?> games graded &bull; Accuracy: <?= $userGradedCount > 0 ? round(($userCorrectCount / $userGradedCount) * 100) : 0 ?>%
                </span>
            </div>
            <div class="flex items-center gap-2 font-mono tabular-nums font-bold">
                <span class="px-2.5 py-1 rounded bg-emerald-950/80 text-emerald-400 border border-emerald-500/40">
                    <?= $userCorrectCount ?> Correct
                </span>
                <span class="px-2.5 py-1 rounded bg-rose-950/80 text-rose-400 border border-rose-500/40">
                    <?= $userIncorrectCount ?> Missed
                </span>
                <span class="px-2.5 py-1 rounded bg-[#0B1626] text-[#94A3B8] border border-[#243247]">
                    <?= $userPendingCount ?> Pending
                </span>
                <a href="/pickem/standings?week=<?= $week ?>&season=<?= $season ?>" class="px-2.5 py-1 rounded bg-[#EAB308] text-[#0B1626] uppercase text-[11px] font-black hover:bg-amber-400 transition">
                    Standings
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Real-Time Validation Notification Box (Dynamic) -->
    <div id="validationAlert" class="hidden p-4 rounded-xl bg-rose-950/80 border border-rose-500/60 shadow-lg transition-all duration-200">
        <div class="flex items-start gap-3 text-rose-300 text-xs font-semibold">
            <svg class="w-4 h-4 text-rose-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div id="validationAlertMessage" class="flex-1 space-y-1">
                <!-- Injected via JavaScript -->
            </div>
        </div>
    </div>

    <!-- Pick'em Form -->
    <form action="/pickem/save" method="POST" id="pickemForm" class="space-y-6">
        <input type="hidden" name="season_year" value="<?= htmlspecialchars((string) $season) ?>">
        <input type="hidden" name="week_number" value="<?= htmlspecialchars((string) $week) ?>">

        <div class="space-y-6">
            <?php foreach ($games as $game): ?>
                <?php
                $isKickoffLocked = (bool) $game['is_locked'];
                $isMnf = (bool) $game['is_mnf'];
                $userPick = $game['user_pick'];
                $kickoff = new DateTimeImmutable($game['kickoff_time']);
                $kickoffEt = $kickoff->setTimezone(new DateTimeZone('America/New_York'))->format('D, M j @ g:i A T');
                $isFinal = ($game['status'] === 'final');
                $inProgress = ($game['status'] === 'in_progress');

                // Card interaction lock state: per-game kickoff lockout
                $cardDisabled = $isKickoffLocked;

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

                $isCorrectAway = ($isFinal && $awayPicked && ($game['pick_result'] ?? '') === 'correct');
                $isIncorrectAway = ($isFinal && $awayPicked && ($game['pick_result'] ?? '') === 'incorrect');
                $isCorrectHome = ($isFinal && $homePicked && ($game['pick_result'] ?? '') === 'correct');
                $isIncorrectHome = ($isFinal && $homePicked && ($game['pick_result'] ?? '') === 'incorrect');

                $awayCardStyle = "";
                if ($isCorrectAway) {
                    $awayCardStyle = "border-color: #15803D; background-color: #064e3b; box-shadow: 0 0 16px rgba(21,128,61,0.3);";
                } elseif ($isIncorrectAway) {
                    $awayCardStyle = "border-color: #e11d48; background-color: #4c0519; box-shadow: 0 0 16px rgba(225,29,72,0.25);";
                } elseif ($awayPicked) {
                    $awayCardStyle = "border-color: {$awayColor}; background-color: #1a2a3f; box-shadow: 0 0 16px {$awayColor}33;";
                } else {
                    $awayCardStyle = "border-color: #243247; background-color: #162235;";
                }

                $homeCardStyle = "";
                if ($isCorrectHome) {
                    $homeCardStyle = "border-color: #15803D; background-color: #064e3b; box-shadow: 0 0 16px rgba(21,128,61,0.3);";
                } elseif ($isIncorrectHome) {
                    $homeCardStyle = "border-color: #e11d48; background-color: #4c0519; box-shadow: 0 0 16px rgba(225,29,72,0.25);";
                } elseif ($homePicked) {
                    $homeCardStyle = "border-color: {$homeColor}; background-color: #1a2a3f; box-shadow: 0 0 16px {$homeColor}33;";
                } else {
                    $homeCardStyle = "border-color: #243247; background-color: #162235;";
                }
                ?>
                <div class="matchup-card rounded-xl border transition-all duration-200 overflow-hidden shadow-sm <?= $isMnf ? 'border-amber-500/50 bg-[#162235]' : 'border-[#243247] bg-[#162235]' ?>"
                     data-game-id="<?= $game['id'] ?>"
                     data-unlocked="<?= $cardDisabled ? 'false' : 'true' ?>"
                     data-away-abbr="<?= htmlspecialchars($awayAbbr) ?>"
                     data-away-name="<?= htmlspecialchars($awayTeam['name']) ?>"
                     data-home-abbr="<?= htmlspecialchars($homeAbbr) ?>"
                     data-home-name="<?= htmlspecialchars($homeTeam['name']) ?>">
                    
                    <!-- Matchup Broadcast Header -->
                    <div class="matchup-header-bar flex items-center justify-between px-4 py-2 bg-[#0B1626] border-b border-[#243247] text-xs">
                        <div class="flex items-center gap-2 text-[#94A3B8]">
                            <span class="font-mono text-[11px] tabular-nums"><?= htmlspecialchars($kickoffEt) ?></span>
                            <?php if ($isMnf): ?>
                                <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold bg-[#EAB308] text-[#0B1626] uppercase tracking-wider">
                                    Tiebreaker Game
                                </span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($isFinal): ?>
                                <?php if (($game['pick_result'] ?? '') === 'correct'): ?>
                                    <span class="px-2.5 py-0.5 rounded font-mono text-[10px] font-bold bg-emerald-950 text-emerald-400 border border-emerald-500/50">
                                        CORRECT (+1)
                                    </span>
                                <?php elseif (($game['pick_result'] ?? '') === 'incorrect'): ?>
                                    <span class="px-2.5 py-0.5 rounded font-mono text-[10px] font-bold bg-rose-950 text-rose-400 border border-rose-500/50">
                                        MISSED (0)
                                    </span>
                                <?php else: ?>
                                    <span class="px-2.5 py-0.5 rounded font-mono text-[10px] font-bold bg-[#162235] text-[#94A3B8] border border-[#243247]">FINAL</span>
                                <?php endif; ?>
                            <?php elseif ($inProgress): ?>
                                <span class="px-2.5 py-0.5 rounded font-mono text-[10px] font-bold bg-emerald-950 text-emerald-400 border border-emerald-500/40">LIVE</span>
                            <?php elseif ($isKickoffLocked): ?>
                                <span class="px-2.5 py-0.5 rounded font-mono text-[10px] font-bold bg-[#0B1626] text-[#94A3B8] border border-[#243247]">LOCKED</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Teams Selection Grid -->
                    <div class="p-3.5 relative">
                        <div class="grid grid-cols-2 gap-3.5">
                        
                            <!-- Away Team Card -->
                            <label class="team-card relative flex flex-col items-center justify-center p-4 pt-5 rounded-xl border transition-all select-none group overflow-hidden <?= $awayPicked ? 'is-picked' : '' ?>
                                <?= $cardDisabled ? 'pointer-events-none' : 'cursor-pointer hover:border-slate-500' ?>"
                                style="<?= $awayCardStyle ?>">
                                
                                <!-- Team Color Top Accent Stripe -->
                                <div class="absolute top-0 left-0 right-0 h-1" style="background-color: <?= htmlspecialchars($awayColor) ?>;"></div>

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

                                <!-- Selection Indicator -->
                                <div class="pick-check absolute top-2 right-2 w-5 h-5 rounded-full bg-[#EAB308] text-[#0B1626] flex items-center justify-center shadow transition-all duration-150 <?= $awayPicked ? 'scale-100 opacity-100' : 'scale-0 opacity-0 pointer-events-none' ?>" title="Selected Pick">
                                    <svg class="w-3 h-3 stroke-[3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>

                                <!-- Official ESPN Logo -->
                                <div class="my-2 h-14 flex items-center justify-center">
                                    <img src="<?= htmlspecialchars($awayTeam['logo']) ?>" 
                                         alt="<?= htmlspecialchars($awayTeam['name']) ?>" 
                                         class="w-12 h-12 object-contain filter drop-shadow transition-transform duration-200 group-hover:scale-105"
                                         loading="lazy">
                                </div>

                                <!-- Team Name -->
                                <div class="text-center w-full mt-1.5">
                                    <span class="text-xs sm:text-sm font-bold text-[#F8FAFC] block truncate">
                                        <?= htmlspecialchars($awayTeam['name']) ?>
                                    </span>
                                </div>
                            </label>

                            <!-- Home Team Card -->
                            <label class="team-card relative flex flex-col items-center justify-center p-4 pt-5 rounded-xl border transition-all select-none group overflow-hidden <?= $homePicked ? 'is-picked' : '' ?>
                                <?= $cardDisabled ? 'pointer-events-none' : 'cursor-pointer hover:border-slate-500' ?>"
                                style="<?= $homeCardStyle ?>">
                                
                                <!-- Team Color Top Accent Stripe -->
                                <div class="absolute top-0 left-0 right-0 h-1" style="background-color: <?= htmlspecialchars($homeColor) ?>;"></div>

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

                                <!-- Selection Indicator -->
                                <div class="pick-check absolute top-2 right-2 w-5 h-5 rounded-full bg-[#EAB308] text-[#0B1626] flex items-center justify-center shadow transition-all duration-150 <?= $homePicked ? 'scale-100 opacity-100' : 'scale-0 opacity-0 pointer-events-none' ?>" title="Selected Pick">
                                    <svg class="w-3 h-3 stroke-[3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>

                                <!-- Official ESPN Logo -->
                                <div class="my-2 h-14 flex items-center justify-center">
                                    <img src="<?= htmlspecialchars($homeTeam['logo']) ?>" 
                                         alt="<?= htmlspecialchars($homeTeam['name']) ?>" 
                                         class="w-12 h-12 object-contain filter drop-shadow transition-transform duration-200 group-hover:scale-105"
                                         loading="lazy">
                                </div>

                                <!-- Team Name -->
                                <div class="text-center w-full mt-1.5">
                                    <span class="text-xs sm:text-sm font-bold text-[#F8FAFC] block truncate">
                                        <?= htmlspecialchars($homeTeam['name']) ?>
                                    </span>
                                </div>
                            </label>

                        </div>

                        <!-- Broadcast-Style VS Badge (centered between two cards) -->
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-10 pointer-events-none">
                            <div class="w-8 h-8 rounded-full bg-[#0B1626] border border-[#243247] shadow-lg flex items-center justify-center">
                                <span class="text-[10px] font-mono font-bold text-[#94A3B8]">VS</span>
                            </div>
                        </div>

                    </div>

                </div>
            <?php endforeach; ?>
        </div>

        <?php
        $tbIsLocked = !empty($tiebreakerGame) && (strtotime($tiebreakerGame['kickoff_time']) <= time());
        ?>
        <!-- Tiebreaker Input Section -->
        <div id="mnfTiebreakerContainer" class="p-5 rounded-xl border border-[#243247] bg-[#162235] shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs font-mono uppercase tracking-wider text-[#EAB308] font-bold">Official Tiebreaker</span>
                        <span class="text-[10px] px-2 py-0.5 rounded bg-[#0B1626] text-[#94A3B8] border border-[#243247] font-mono font-bold">Game of the Week</span>
                    </div>
                    <?php if (isset($tbAwayData) && isset($tbHomeData)): ?>
                        <div class="flex items-center gap-3 my-1.5 flex-wrap">
                            <div class="flex items-center gap-2">
                                <img src="<?= htmlspecialchars($tbAwayData['logo']) ?>" alt="<?= htmlspecialchars($tbAwayData['name']) ?>" class="w-6 h-6 object-contain">
                                <span class="font-bold text-[#F8FAFC] text-sm sm:text-base"><?= htmlspecialchars($tbAwayData['name']) ?></span>
                            </div>
                            <span class="text-[#94A3B8] font-mono text-xs">@</span>
                            <div class="flex items-center gap-2">
                                <img src="<?= htmlspecialchars($tbHomeData['logo']) ?>" alt="<?= htmlspecialchars($tbHomeData['name']) ?>" class="w-6 h-6 object-contain">
                                <span class="font-bold text-[#F8FAFC] text-sm sm:text-base"><?= htmlspecialchars($tbHomeData['name']) ?></span>
                            </div>
                        </div>
                        <p class="text-xs text-[#94A3B8] mt-1 max-w-xl leading-relaxed">
                            Predict the combined total final score for <strong><?= htmlspecialchars($tbAwayData['name']) ?> @ <?= htmlspecialchars($tbHomeData['name']) ?></strong>. Lowest absolute point delta breaks any weekly ties!
                        </p>
                    <?php else: ?>
                        <h4 class="text-sm sm:text-base font-bold text-[#F8FAFC]">Weekly Tiebreaker Combined Total Score</h4>
                        <p class="text-xs text-[#94A3B8] mt-1 max-w-xl leading-relaxed">
                            Predict the combined total final score for our designated game of the week. Lowest absolute point delta breaks ties!
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
                               <?= $tbIsLocked ? 'disabled' : '' ?>
                               class="w-28 px-3 py-2.5 rounded-lg bg-[#0B1626] border border-[#243247] text-[#F8FAFC] font-mono tabular-nums text-center text-base font-bold focus:outline-none focus:border-[#EAB308] focus:ring-1 focus:ring-[#EAB308] transition <?= $tbIsLocked ? 'opacity-60 cursor-not-allowed' : '' ?>">
                    </div>
                    <span class="text-xs text-[#94A3B8] font-mono font-bold uppercase tracking-wider">Total Points</span>
                </div>
            </div>
        </div>

        <!-- Status & Standings Action Card -->
        <div class="p-4 sm:p-5 rounded-xl bg-[#162235] border border-[#243247] shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3 text-xs text-[#94A3B8]">
                <div class="w-8 h-8 rounded-lg bg-[#0B1626] border border-[#243247] flex items-center justify-center shrink-0">
                    <?php if ($isWeekLocked): ?>
                        <svg class="w-4 h-4 text-[#94A3B8]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    <?php else: ?>
                        <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                    <?php endif; ?>
                </div>

                <?php if ($isWeekLocked): ?>
                    <div>
                        <span class="text-[#F8FAFC] font-semibold text-sm block">Week <?= $week ?> is locked.</span>
                        <span class="text-[#94A3B8] block text-xs">The cutoff deadline has passed. Selections are final.</span>
                    </div>
                <?php else: ?>
                    <div>
                        <span class="font-bold text-[#F8FAFC] block text-sm mb-0.5">Selections Auto-Save in Real Time</span>
                        <span class="text-[#94A3B8]">No submit button required. Picks remain editable until <?= htmlspecialchars($cutoffFormatted) ?>.</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-3 shrink-0 w-full sm:w-auto">
                <a href="/pickem/standings?week=<?= $week ?>&season=<?= $season ?>" 
                   class="w-full sm:w-auto px-5 py-2.5 rounded-lg <?= $isWeekLocked ? 'bg-[#EAB308] hover:bg-amber-400 text-[#0B1626]' : 'bg-[#0B1626] hover:bg-[#1f2e44] text-[#F8FAFC] border border-[#243247]' ?> font-bold text-xs uppercase tracking-wider transition text-center shadow-sm">
                    <?= $isWeekLocked ? 'View Live Standings &rarr;' : 'View Standings &rarr;' ?>
                </a>
            </div>
        </div>

    </form>
    </div>

</div>

<!-- Pick'em How It Works Modal -->
<div id="howItWorksModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-[#162235] border border-[#243247] rounded-xl max-w-lg w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        
        <!-- Header -->
        <div class="p-4 border-b border-[#243247] bg-[#0B1626] flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-[#F8FAFC]">Pick'em Rules &amp; Guidelines</h3>
                <span class="text-xs text-[#94A3B8]">Straight-up weekly pool</span>
            </div>
            <button type="button" id="btnCloseHowItWorksX" class="text-[#94A3B8] hover:text-white text-2xl font-bold leading-none p-2">&times;</button>
        </div>

        <!-- Content (Scrollable) -->
        <div class="p-4 overflow-y-auto space-y-3 text-xs">
            
            <!-- Rule 1: Weekly Straight-Up Picks -->
            <div class="flex items-start gap-3 p-3 rounded-lg bg-[#0B1626] border border-[#243247]">
                <span class="w-6 h-6 rounded bg-[#162235] text-[#EAB308] border border-[#243247] font-mono font-bold text-xs flex items-center justify-center shrink-0">1</span>
                <div>
                    <strong class="text-[#F8FAFC] text-xs block mb-0.5">Pick Straight-Up Winners</strong>
                    <p class="text-[#94A3B8] leading-relaxed">
                        For every game in the week's slate, pick which team will win straight-up (no point spreads). Each correct selection earns <strong>1 point</strong>.
                    </p>
                </div>
            </div>

            <!-- Rule 2: $10 Entry Fee -->
            <div class="flex items-start gap-3 p-3 rounded-lg bg-[#0B1626] border border-[#243247]">
                <span class="w-6 h-6 rounded bg-[#162235] text-[#EAB308] border border-[#243247] font-mono font-bold text-xs flex items-center justify-center shrink-0">2</span>
                <div>
                    <strong class="text-[#F8FAFC] text-xs block mb-0.5">Weekly $10.00 Entry Stake</strong>
                    <p class="text-[#94A3B8] leading-relaxed">
                        Entry is $10 per week. Send your stake directly to Commissioner Wally via Venmo (<span class="text-[#F8FAFC] font-mono font-bold">@WallyAtkins</span>), PayPal, or Cash App (<span class="text-[#F8FAFC] font-mono font-bold">$WallyAtkins</span>). 100% of collected stakes fund that week's cash pot!
                    </p>
                </div>
            </div>

            <!-- Rule 3: Tiebreaker Question -->
            <div class="flex items-start gap-3 p-3 rounded-lg bg-[#0B1626] border border-[#243247]">
                <span class="w-6 h-6 rounded bg-[#162235] text-[#EAB308] border border-[#243247] font-mono font-bold text-xs flex items-center justify-center shrink-0">3</span>
                <div>
                    <strong class="text-[#F8FAFC] text-xs block mb-0.5">Game of the Week Tiebreaker</strong>
                    <p class="text-[#94A3B8] leading-relaxed">
                        Each week features a designated tiebreaker game. Predict the combined total final score (e.g. 48). If players tie with the same number of wins, the player with the lowest absolute point difference wins the prize pot!
                    </p>
                </div>
            </div>

            <!-- Rule 4: Auto-Save & Review -->
            <div class="flex items-start gap-3 p-3 rounded-lg bg-[#0B1626] border border-[#243247]">
                <span class="w-6 h-6 rounded bg-[#162235] text-[#EAB308] border border-[#243247] font-mono font-bold text-xs flex items-center justify-center shrink-0">4</span>
                <div>
                    <strong class="text-[#F8FAFC] text-xs block mb-0.5">Per-Game Kickoff Locks &bull; Auto-Saving</strong>
                    <p class="text-[#94A3B8] leading-relaxed">
                        Picks and tiebreaker scores are saved automatically. Each game locks strictly at kickoff; open games can be changed at any time prior to their individual start.
                    </p>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <div class="p-3.5 border-t border-[#243247] bg-[#0B1626] flex justify-end">
            <button type="button" id="btnCloseHowItWorks" class="px-4 py-2 rounded-lg bg-[#EAB308] hover:bg-amber-400 text-[#0B1626] font-bold text-xs uppercase tracking-wider transition">
                Close
            </button>
        </div>

    </div>
</div>

<!-- Auto-Save Toast Notification -->
<div id="autoSaveToast" class="fixed bottom-6 right-6 z-50 px-4 py-2 rounded-lg bg-[#0B1626] text-emerald-400 border border-emerald-500/40 text-xs font-mono font-bold shadow-xl flex items-center gap-2 opacity-0 pointer-events-none transition-all duration-200 transform translate-y-2">
    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
    </svg>
    <span id="autoSaveToastText">Saved</span>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const mnfInput = document.getElementById('mnfTotalPointsInput');
    const mnfContainer = document.getElementById('mnfTiebreakerContainer');

    // Auto-Save Toast Notification
    const autoSaveToast = document.getElementById('autoSaveToast');
    const autoSaveToastText = document.getElementById('autoSaveToastText');
    let toastTimeout = null;

    function showAutoSaveBadge(text) {
        if (!autoSaveToast || !autoSaveToastText) return;
        autoSaveToastText.textContent = text || 'Saved behind the scenes';
        autoSaveToast.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-2');
        autoSaveToast.classList.add('opacity-100', 'translate-y-0');
        clearTimeout(toastTimeout);
        toastTimeout = setTimeout(() => {
            autoSaveToast.classList.remove('opacity-100', 'translate-y-0');
            autoSaveToast.classList.add('opacity-0', 'pointer-events-none', 'translate-y-2');
        }, 1800);
    }

    // How It Works Modal elements
    const howItWorksModal = document.getElementById('howItWorksModal');
    const btnOpenHowItWorks = document.getElementById('btnOpenHowItWorks');
    const btnCloseHowItWorks = document.getElementById('btnCloseHowItWorks');
    const btnCloseHowItWorksX = document.getElementById('btnCloseHowItWorksX');

    function openHowItWorks() {
        if (howItWorksModal) {
            howItWorksModal.classList.remove('hidden');
            howItWorksModal.classList.add('flex');
        }
    }

    function closeHowItWorks() {
        if (howItWorksModal) {
            howItWorksModal.classList.add('hidden');
            howItWorksModal.classList.remove('flex');
        }
    }

    if (btnOpenHowItWorks) btnOpenHowItWorks.addEventListener('click', openHowItWorks);
    if (btnCloseHowItWorks) btnCloseHowItWorks.addEventListener('click', closeHowItWorks);
    if (btnCloseHowItWorksX) btnCloseHowItWorksX.addEventListener('click', closeHowItWorks);
    if (howItWorksModal) {
        howItWorksModal.addEventListener('click', function (e) {
            if (e.target === howItWorksModal) closeHowItWorks();
        });
    }

    // 1. Dynamic selection & radio button handling + silent autosave
    const matchups = document.querySelectorAll('.matchup-card');
    matchups.forEach(card => {
        const labels = card.querySelectorAll('.team-card');
        labels.forEach(label => {
            label.addEventListener('click', function () {
                const radio = this.querySelector('.pick-radio');
                if (!radio || radio.disabled) return;

                // Reset siblings
                labels.forEach(l => {
                    l.classList.remove('is-picked');
                    l.style.borderColor = 'var(--card-surface-border)';
                    l.style.backgroundColor = 'var(--card-surface)';
                    l.style.boxShadow = 'none';
                    l.classList.add('opacity-50');
                    l.classList.remove('opacity-100');
                    const check = l.querySelector('.pick-check');
                    if (check) {
                        check.classList.remove('scale-100', 'opacity-100');
                        check.classList.add('scale-0', 'opacity-0');
                    }
                });

                // Highlight selected (team color gradient background, crisp border & glow, zero layout shift)
                const color = radio.getAttribute('data-color') || '#f59e0b';
                this.classList.add('is-picked');
                this.style.borderColor = color;
                this.style.background = `linear-gradient(135deg, ${color}28 0%, #0f172a 100%)`;
                this.style.boxShadow = `0 0 22px ${color}44`;
                this.classList.remove('opacity-50');
                this.classList.add('opacity-100');

                const check = this.querySelector('.pick-check');
                if (check) {
                    check.classList.remove('scale-0', 'opacity-0');
                    check.classList.add('scale-100', 'opacity-100');
                }

                radio.checked = true;

                // Remove red error highlight if present
                card.classList.remove('ring-4', 'ring-rose-500', 'animate-pulse');
                if (validationAlert) validationAlert.classList.add('hidden');

                // Silent auto-save behind the scenes
                const gameId = card.getAttribute('data-game-id');
                const teamVal = radio.value;
                if (gameId && teamVal) {
                    fetch('/pickem/autosave', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            season_year: <?= (int) $season ?>,
                            week_number: <?= (int) $week ?>,
                            game_id: parseInt(gameId, 10),
                            selected_team: teamVal
                        })
                    }).then(r => r.json()).then(data => {
                        if (data && data.success) {
                            showAutoSaveBadge(teamVal + ' Pick Saved');
                        }
                    }).catch(e => console.warn('Autosave notice:', e));
                }
            });
        });
    });

    // 2. Debounced auto-save for Tiebreaker input
    let mnfSaveTimer = null;
    function autoSaveTiebreaker() {
        if (!mnfInput) return;
        const val = mnfInput.value.trim();
        fetch('/pickem/autosave', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                season_year: <?= (int) $season ?>,
                week_number: <?= (int) $week ?>,
                mnf_total_points: val !== '' ? parseInt(val, 10) : null
            })
        }).then(r => r.json()).then(data => {
            if (data && data.success) {
                showAutoSaveBadge('Tiebreaker Saved (' + (val !== '' ? val : '0') + ' pts)');
            }
        }).catch(e => console.warn('Tiebreaker autosave notice:', e));
    }

    if (mnfInput) {
        mnfInput.addEventListener('input', function () {
            if (parseInt(this.value, 10) > 0) {
                mnfContainer.classList.remove('ring-4', 'ring-rose-500', 'animate-pulse');
                if (validationAlert) validationAlert.classList.add('hidden');
            }
            clearTimeout(mnfSaveTimer);
            mnfSaveTimer = setTimeout(autoSaveTiebreaker, 600);
        });
        mnfInput.addEventListener('change', autoSaveTiebreaker);
    }

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeHowItWorks();
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
