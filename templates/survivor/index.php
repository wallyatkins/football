<?php
use WallyFootball\Support\TeamData;

ob_start();
$isAlive = !$isEliminated;
$isCashEligible = (bool) $isPaid;
$isPickLocked = !empty($isPickLocked);
$isSurvivorClosed = !empty($isSurvivorClosed);
$hasCurrentPick = !empty($currentPick);
$firstKickoffFormatted = $firstKickoffFormatted ?? 'Kickoff of Week ' . $week;
$cutoffFormatted = $cutoffFormatted ?? ($firstKickoffFormatted ?? 'Cutoff of Week ' . $week);

$usedPicksByTeam = [];
if (!empty($usedPicks)) {
    foreach ($usedPicks as $up) {
        $usedPicksByTeam[$up['selected_team']] = (int) $up['week_number'];
    }
}
?>

<div class="space-y-6">

    <!-- Header & Contest Subnav -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[#243247] pb-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-mono px-2 py-0.5 rounded bg-[#162235] text-emerald-400 border border-[#243247]">Survivor Pool</span>
                <span class="text-xs text-[#94A3B8] font-mono">Season <?= htmlspecialchars((string) $season) ?></span>
                <?php if ($isCashEligible): ?>
                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-emerald-950/80 text-emerald-400 border border-emerald-500/40 uppercase">
                        Cash Verified
                    </span>
                <?php else: ?>
                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-[#0B1626] text-[#94A3B8] border border-[#243247] uppercase">
                        Free Entry
                    </span>
                <?php endif; ?>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-[#F8FAFC]">
                Week <?= htmlspecialchars((string) $week) ?> Survivor Pick
            </h1>
        </div>

        <div class="flex items-center gap-2 font-mono text-xs">
            <span class="px-3 py-1.5 font-bold rounded-lg bg-[#15803D] text-white">
                Active Week <?= $week ?>
            </span>
            <a href="/survivor/standings" 
               class="px-3 py-1.5 rounded-lg bg-[#162235] hover:bg-[#1f2e44] text-[#94A3B8] hover:text-white border border-[#243247] transition">
                Leaderboard
            </a>
            <a href="/fantasy/vault?tab=pools" 
               class="px-3 py-1.5 rounded-lg bg-[#162235] hover:bg-[#1f2e44] text-[#94A3B8] hover:text-white border border-[#243247] transition">
                Archives
            </a>
            <button type="button" 
                    id="btnOpenSurvivorHowItWorks"
                    class="px-3 py-1.5 rounded-lg bg-[#0B1626] hover:bg-[#1f2e44] text-[#EAB308] border border-[#243247] font-bold transition">
                Rules
            </button>
        </div>
    </div>

    <!-- Center Column Layout -->
    <div class="max-w-3xl mx-auto space-y-6">

        <!-- Status Banners -->
        <?php if ($isEliminated): ?>
            <!-- Eliminated Banner -->
            <div class="p-4 rounded-xl bg-rose-950/80 border border-rose-500/60 flex items-start gap-3 shadow-sm text-xs">
                <svg class="w-5 h-5 text-rose-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                    <h3 class="text-sm font-bold text-rose-300">Eliminated from Survivor Challenge</h3>
                    <p class="text-[#94A3B8] mt-0.5 leading-relaxed">
                        You were knocked out of the Survivor pool in Week <?= htmlspecialchars((string) ($eliminationWeek ?? 'earlier')) ?>. Your season run has concluded.
                    </p>
                </div>
            </div>

        <?php elseif ($isSurvivorClosed && !$hasCurrentPick): ?>
            <!-- Missed Deadline Banner -->
            <div class="p-4 rounded-xl bg-rose-950/80 border border-rose-500/60 flex items-start gap-3 shadow-sm text-xs">
                <svg class="w-5 h-5 text-rose-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <div>
                    <h3 class="text-sm font-bold text-rose-300">Survivor Selections Closed</h3>
                    <p class="text-[#94A3B8] mt-0.5 leading-relaxed">
                        All scheduled games for Week <?= htmlspecialchars((string) $week) ?> have kicked off or all remaining teams have been burned.
                    </p>
                </div>
            </div>

        <?php elseif ($isPickLocked): ?>
            <!-- Locked In Pick Banner -->
            <?php 
            $pickedTeamAbbr = $currentPick['selected_team'];
            $pickedTeamData = TeamData::get($pickedTeamAbbr);
            ?>
            <div class="p-4 rounded-xl border border-[#243247] bg-[#162235] shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <img src="<?= htmlspecialchars($pickedTeamData['logo']) ?>" 
                         alt="<?= htmlspecialchars($pickedTeamData['name']) ?>" 
                         class="w-12 h-12 object-contain">
                    <div>
                        <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                            <span class="text-[10px] font-mono text-[#94A3B8] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-[#0B1626] border border-[#243247]">
                                Locked In &bull; One and Done
                            </span>
                            <?php if ($isCashEligible): ?>
                                <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-emerald-950/80 text-emerald-400 border border-emerald-500/40">
                                    CASH CONTENDER
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="text-lg font-bold text-[#F8FAFC]"><?= htmlspecialchars($pickedTeamData['name']) ?></span>
                        <span class="text-xs text-[#94A3B8] block mt-0.5">
                            Game has kicked off. Selection is final and cannot be modified.
                        </span>
                    </div>
                </div>
                <div class="sm:text-right shrink-0">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#0B1626] text-emerald-400 border border-emerald-500/40 text-xs font-mono font-bold">
                        Pick Finalized
                    </span>
                </div>
            </div>

        <?php elseif ($hasCurrentPick): ?>
            <!-- Saved Pick (Editable) Banner -->
            <?php 
            $pickedTeamAbbr = $currentPick['selected_team'];
            $pickedTeamData = TeamData::get($pickedTeamAbbr);
            ?>
            <div class="p-4 rounded-xl border border-emerald-500/40 bg-[#162235] shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <img src="<?= htmlspecialchars($pickedTeamData['logo']) ?>" 
                         alt="<?= htmlspecialchars($pickedTeamData['name']) ?>" 
                         class="w-12 h-12 object-contain">
                    <div>
                        <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                            <span class="text-[10px] font-mono text-emerald-400 font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-emerald-950/80 border border-emerald-500/40">
                                Pick Auto-Saved &bull; Editable Until Kickoff
                            </span>
                            <?php if ($isCashEligible): ?>
                                <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-emerald-950/80 text-emerald-400 border border-emerald-500/40">
                                    CASH CONTENDER
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="text-lg font-bold text-[#F8FAFC]"><?= htmlspecialchars($pickedTeamData['name']) ?></span>
                        <span class="text-xs text-[#94A3B8] block mt-0.5">
                            Saved behind the scenes. You can switch to any eligible team prior to that game's kickoff.
                        </span>
                    </div>
                </div>
                <div class="sm:text-right shrink-0">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#0B1626] text-emerald-400 border border-[#243247] text-xs font-mono font-bold">
                        Saved &bull; Editable
                    </span>
                </div>
            </div>

        <?php elseif (!$isCashEligible): ?>
            <!-- Free Tier Payment Info Banner -->
            <div class="p-4 rounded-xl bg-[#162235] border border-[#243247] shadow-sm space-y-2 text-xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="font-bold text-[#F8FAFC]">Playing in Free / Fun Pool</span>
                            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-emerald-950/80 text-emerald-400 border border-emerald-500/40 uppercase">
                                ALIVE
                            </span>
                        </div>
                        <p class="text-[#94A3B8] leading-relaxed">
                            Pick a winner below to stay alive! To compete for the cash prize pot, send your $10 stake to Commissioner Wally.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 font-mono text-[11px] font-bold shrink-0">
                        <a href="<?= htmlspecialchars($venmoUrl) ?>" target="_blank" rel="noopener noreferrer" 
                           class="px-2.5 py-1 rounded bg-[#0B1626] border border-[#243247] text-sky-400 hover:text-white transition">
                            Venmo
                        </a>
                        <a href="<?= htmlspecialchars($payPalUrl) ?>" target="_blank" rel="noopener noreferrer" 
                           class="px-2.5 py-1 rounded bg-[#0B1626] border border-[#243247] text-sky-400 hover:text-white transition">
                            PayPal
                        </a>
                        <a href="<?= htmlspecialchars($cashAppUrl) ?>" target="_blank" rel="noopener noreferrer" 
                           class="px-2.5 py-1 rounded bg-[#0B1626] border border-[#243247] text-emerald-400 hover:text-white transition">
                            Cash App
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Burned Teams Showcase Bar -->
        <div class="p-4 rounded-xl bg-[#162235] border border-[#243247] shadow-sm space-y-2.5 text-xs">
            <div class="flex items-center justify-between gap-3 border-b border-[#243247] pb-2">
                <div class="flex items-center gap-2 font-mono">
                    <span class="font-bold text-[#F8FAFC]">Burned Teams:</span>
                    <span class="text-[10px] px-2 py-0.5 rounded <?= empty($usedTeams) ? 'bg-[#0B1626] text-[#94A3B8] border border-[#243247]' : 'bg-rose-950/80 text-rose-400 border border-rose-500/40' ?> font-bold">
                        <?= count($usedTeams) ?> of 32 Used
                    </span>
                </div>
                <div class="font-mono text-[#94A3B8]">
                    <span class="text-emerald-400 font-bold"><?= 32 - count($usedTeams) ?></span> teams remaining
                </div>
            </div>

            <div>
                <?php if (empty($usedTeams)): ?>
                    <span class="text-[#94A3B8] italic">No teams burned yet — all 32 NFL teams are available!</span>
                <?php else: ?>
                    <div class="flex items-center gap-2 flex-wrap pt-0.5">
                        <?php foreach ($usedTeams as $ut): ?>
                            <?php 
                            $utData = TeamData::get($ut);
                            $utWeek = $usedPicksByTeam[$ut] ?? null;
                            ?>
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-[#0B1626] border border-[#243247] text-xs font-mono" title="Burned in Week <?= $utWeek ?? 'earlier' ?>: <?= htmlspecialchars($utData['name']) ?>">
                                <?php if ($utWeek !== null): ?>
                                    <span class="text-[10px] text-[#94A3B8] font-bold">Wk <?= $utWeek ?>:</span>
                                <?php endif; ?>
                                <img src="<?= htmlspecialchars($utData['logo']) ?>" 
                                     alt="<?= htmlspecialchars($utData['name']) ?>" 
                                     class="w-4 h-4 object-contain opacity-70">
                                <span class="line-through text-[#94A3B8] font-bold"><?= htmlspecialchars($utData['nick']) ?></span>
                                <span class="text-[9px] uppercase text-rose-400 font-bold">Burned</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Matchup Selector Form -->
        <form action="/survivor/save" method="POST" id="survivorForm" class="space-y-6">
            <input type="hidden" name="season_year" value="<?= htmlspecialchars((string) $season) ?>">
            <input type="hidden" name="week_number" value="<?= htmlspecialchars((string) $week) ?>">

            <!-- Matchup Cards -->
            <div class="space-y-4">
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
                    $awayDisabled = $isLocked || $awayUsed || $isEliminated || $isPickLocked;

                    // Home Team
                    $homeAbbr = $game['home_team'];
                    $homeTeam = TeamData::get($homeAbbr);
                    $homeColor = $homeTeam['color'];
                    $homePicked = ($currentTeam === $homeAbbr);
                    $homeDisabled = $isLocked || $homeUsed || $isEliminated || $isPickLocked;

                    $matchupTitle = "{$awayTeam['name']} @ {$homeTeam['name']}";
                    ?>
                    <div class="matchup-card rounded-xl border transition-all duration-200 overflow-hidden shadow-sm border-[#243247] bg-[#162235]"
                         data-game-id="<?= $game['id'] ?>"
                         data-unlocked="<?= (!$awayDisabled || !$homeDisabled) ? 'true' : 'false' ?>"
                         data-away-abbr="<?= htmlspecialchars($awayAbbr) ?>"
                         data-away-name="<?= htmlspecialchars($awayTeam['name']) ?>"
                         data-home-abbr="<?= htmlspecialchars($homeAbbr) ?>"
                         data-home-name="<?= htmlspecialchars($homeTeam['name']) ?>">
                        
                        <!-- Matchup Broadcast Header -->
                        <div class="matchup-header-bar flex items-center justify-between px-4 py-2 bg-[#0B1626] border-b border-[#243247] text-xs">
                            <div class="flex items-center gap-2 text-[#94A3B8]">
                                <span class="font-mono text-[11px] tabular-nums"><?= htmlspecialchars($kickoffEt) ?></span>
                                <span class="text-[#94A3B8] hidden sm:inline">&bull;</span>
                                <span class="text-[#94A3B8] text-xs font-semibold hidden sm:inline"><?= htmlspecialchars($matchupTitle) ?></span>
                            </div>
                            <div>
                                <?php if ($isLocked): ?>
                                    <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold bg-[#0B1626] text-[#94A3B8] border border-[#243247]">LOCKED</span>
                                <?php elseif ($isPickLocked): ?>
                                    <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold bg-[#0B1626] text-[#94A3B8] border border-[#243247]">LOCKED</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold bg-emerald-950 text-emerald-400 border border-emerald-500/40">OPEN</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Teams Selection Grid -->
                        <div class="p-3.5 relative">
                            <div class="grid grid-cols-2 gap-3.5">
                                
                                <!-- Away Team Card -->
                                <?php
                                $awayCardStyle = "";
                                if ($awayUsed) {
                                    $awayCardStyle = "border-color: #4c0519; background-color: #1a1419;";
                                } elseif ($awayPicked) {
                                    $awayCardStyle = "border-color: {$awayColor}; background-color: #1a2a3f; box-shadow: 0 0 16px {$awayColor}33;";
                                } else {
                                    $awayCardStyle = "border-color: #243247; background-color: #162235;";
                                }
                                ?>
                                <label class="team-card survivor-card relative flex flex-col items-center justify-center p-4 pt-5 rounded-xl border transition-all select-none group overflow-hidden <?= $awayPicked ? 'is-picked' : '' ?>
                                    <?= $awayDisabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:border-slate-500' ?>"
                                    style="<?= $awayCardStyle ?>"
                                    data-abbr="<?= htmlspecialchars($awayAbbr) ?>"
                                    data-name="<?= htmlspecialchars($awayTeam['name']) ?>"
                                    data-nick="<?= htmlspecialchars($awayTeam['nick']) ?>"
                                    data-logo="<?= htmlspecialchars($awayTeam['logo']) ?>"
                                    data-color="<?= htmlspecialchars($awayColor) ?>"
                                    data-matchup="<?= htmlspecialchars($matchupTitle) ?>"
                                    data-kickoff="<?= htmlspecialchars($kickoffEt) ?>">
                                    
                                    <!-- Team Color Top Accent Stripe -->
                                    <div class="absolute top-0 left-0 right-0 h-1 <?= $awayUsed ? 'opacity-30' : '' ?>" style="background-color: <?= htmlspecialchars($awayColor) ?>;"></div>

                                    <input type="radio" 
                                           name="selected_team" 
                                           value="<?= htmlspecialchars($awayAbbr) ?>" 
                                           class="sr-only survivor-radio"
                                           data-abbr="<?= htmlspecialchars($awayAbbr) ?>"
                                           data-color="<?= htmlspecialchars($awayColor) ?>"
                                           data-name="<?= htmlspecialchars($awayTeam['name']) ?>"
                                           data-nick="<?= htmlspecialchars($awayTeam['nick']) ?>"
                                           data-logo="<?= htmlspecialchars($awayTeam['logo']) ?>"
                                           data-matchup="<?= htmlspecialchars($matchupTitle) ?>"
                                           data-kickoff="<?= htmlspecialchars($kickoffEt) ?>"
                                           <?= $awayPicked ? 'checked' : '' ?>
                                           <?= $awayDisabled ? 'disabled' : '' ?>>

                                    <!-- Burned Badge -->
                                    <?php if ($awayUsed): ?>
                                        <div class="absolute top-2 right-2 px-1.5 py-0.5 rounded bg-rose-950 border border-rose-500/50 text-rose-300 font-mono font-bold text-[9px] uppercase tracking-wider">
                                            BURNED
                                        </div>
                                    <?php else: ?>
                                        <div class="pick-check absolute top-2 right-2 w-5 h-5 rounded-full bg-[#15803D] text-white flex items-center justify-center shadow transition-all duration-150 <?= $awayPicked ? 'scale-100 opacity-100' : 'scale-0 opacity-0 pointer-events-none' ?>" title="Selected Pick">
                                            <svg class="w-3 h-3 stroke-[3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Team Logo -->
                                    <div class="my-2 h-14 flex items-center justify-center">
                                        <img src="<?= htmlspecialchars($awayTeam['logo']) ?>" 
                                             alt="<?= htmlspecialchars($awayTeam['name']) ?>" 
                                             class="w-12 h-12 object-contain filter drop-shadow transition-transform duration-200 <?= $awayUsed ? 'grayscale opacity-50' : 'group-hover:scale-105' ?>"
                                             loading="lazy">
                                    </div>

                                    <!-- Team Name -->
                                    <div class="text-center w-full mt-1.5">
                                        <span class="text-xs sm:text-sm font-bold <?= $awayUsed ? 'text-[#94A3B8] line-through' : 'text-[#F8FAFC]' ?> block truncate">
                                            <?= htmlspecialchars($awayTeam['name']) ?>
                                        </span>
                                        <?php if ($awayUsed): ?>
                                            <span class="text-[10px] font-mono font-bold text-rose-400 block mt-0.5">
                                                Already Used
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </label>

                                <!-- Home Team Card -->
                                <?php
                                $homeCardStyle = "";
                                if ($homeUsed) {
                                    $homeCardStyle = "border-color: #4c0519; background-color: #1a1419;";
                                } elseif ($homePicked) {
                                    $homeCardStyle = "border-color: {$homeColor}; background-color: #1a2a3f; box-shadow: 0 0 16px {$homeColor}33;";
                                } else {
                                    $homeCardStyle = "border-color: #243247; background-color: #162235;";
                                }
                                ?>
                                <label class="team-card survivor-card relative flex flex-col items-center justify-center p-4 pt-5 rounded-xl border transition-all select-none group overflow-hidden <?= $homePicked ? 'is-picked' : '' ?>
                                    <?= $homeDisabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:border-slate-500' ?>"
                                    style="<?= $homeCardStyle ?>"
                                    data-abbr="<?= htmlspecialchars($homeAbbr) ?>"
                                    data-name="<?= htmlspecialchars($homeTeam['name']) ?>"
                                    data-nick="<?= htmlspecialchars($homeTeam['nick']) ?>"
                                    data-logo="<?= htmlspecialchars($homeTeam['logo']) ?>"
                                    data-color="<?= htmlspecialchars($homeColor) ?>"
                                    data-matchup="<?= htmlspecialchars($matchupTitle) ?>"
                                    data-kickoff="<?= htmlspecialchars($kickoffEt) ?>">
                                    
                                    <!-- Team Color Top Accent Stripe -->
                                    <div class="absolute top-0 left-0 right-0 h-1 <?= $homeUsed ? 'opacity-30' : '' ?>" style="background-color: <?= htmlspecialchars($homeColor) ?>;"></div>

                                    <input type="radio" 
                                           name="selected_team" 
                                           value="<?= htmlspecialchars($homeAbbr) ?>" 
                                           class="sr-only survivor-radio"
                                           data-abbr="<?= htmlspecialchars($homeAbbr) ?>"
                                           data-color="<?= htmlspecialchars($homeColor) ?>"
                                           data-name="<?= htmlspecialchars($homeTeam['name']) ?>"
                                           data-nick="<?= htmlspecialchars($homeTeam['nick']) ?>"
                                           data-logo="<?= htmlspecialchars($homeTeam['logo']) ?>"
                                           data-matchup="<?= htmlspecialchars($matchupTitle) ?>"
                                           data-kickoff="<?= htmlspecialchars($kickoffEt) ?>"
                                           <?= $homePicked ? 'checked' : '' ?>
                                           <?= $homeDisabled ? 'disabled' : '' ?>>

                                    <!-- Burned Badge -->
                                    <?php if ($homeUsed): ?>
                                        <div class="absolute top-2 right-2 px-1.5 py-0.5 rounded bg-rose-950 border border-rose-500/50 text-rose-300 font-mono font-bold text-[9px] uppercase tracking-wider">
                                            BURNED
                                        </div>
                                    <?php else: ?>
                                        <div class="pick-check absolute top-2 right-2 w-5 h-5 rounded-full bg-[#15803D] text-white flex items-center justify-center shadow transition-all duration-150 <?= $homePicked ? 'scale-100 opacity-100' : 'scale-0 opacity-0 pointer-events-none' ?>" title="Selected Pick">
                                            <svg class="w-3 h-3 stroke-[3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Team Logo -->
                                    <div class="my-2 h-14 flex items-center justify-center">
                                        <img src="<?= htmlspecialchars($homeTeam['logo']) ?>" 
                                             alt="<?= htmlspecialchars($homeTeam['name']) ?>" 
                                             class="w-12 h-12 object-contain filter drop-shadow transition-transform duration-200 <?= $homeUsed ? 'grayscale opacity-50' : 'group-hover:scale-105' ?>"
                                             loading="lazy">
                                    </div>

                                    <!-- Team Name -->
                                    <div class="text-center w-full mt-1.5">
                                        <span class="text-xs sm:text-sm font-bold <?= $homeUsed ? 'text-[#94A3B8] line-through' : 'text-[#F8FAFC]' ?> block truncate">
                                            <?= htmlspecialchars($homeTeam['name']) ?>
                                        </span>
                                        <?php if ($homeUsed): ?>
                                            <span class="text-[10px] font-mono font-bold text-rose-400 block mt-0.5">
                                                Already Used
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </label>

                            </div>

                            <!-- VS Badge (centered between cards) -->
                            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-10 pointer-events-none">
                                <div class="w-8 h-8 rounded-full bg-[#0B1626] border border-[#243247] shadow flex items-center justify-center">
                                    <span class="text-[10px] font-mono font-bold text-[#94A3B8]">VS</span>
                                </div>
                            </div>

                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

            <!-- In-Flow Action Card -->
            <div class="mt-6 p-4 rounded-xl bg-[#162235] border border-[#243247] flex flex-col sm:flex-row items-center justify-between gap-4 shadow-sm text-xs">
                <div class="flex items-start gap-3 text-[#94A3B8]">
                    <div class="w-7 h-7 rounded-lg bg-[#0B1626] border border-[#243247] flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-[#EAB308]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <div>
                        <?php if ($isEliminated): ?>
                            <span class="text-rose-400 font-semibold">Eliminated from the season-long pool in Week <?= htmlspecialchars((string) ($eliminationWeek ?? 'earlier')) ?>.</span>
                        <?php elseif ($isPickLocked): ?>
                            <span class="text-[#F8FAFC] font-semibold">Your Week <?= htmlspecialchars((string) $week) ?> pick is locked in.</span>
                        <?php elseif ($hasCurrentPick): ?>
                            <span class="text-emerald-400 font-semibold">Pick Auto-Saved! You can change your selection to another eligible team prior to kickoff.</span>
                        <?php else: ?>
                            <span class="text-[#F8FAFC] font-semibold">Selections auto-save as you pick. Each team locks at individual game kickoff.</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($isEliminated): ?>
                    <button type="button" disabled
                            class="w-full sm:w-auto px-6 py-2.5 rounded-lg bg-[#0B1626] text-[#94A3B8] font-bold text-xs uppercase tracking-wider cursor-not-allowed border border-[#243247]">
                        Pool Run Ended
                    </button>
                <?php elseif ($isPickLocked): ?>
                    <div class="w-full sm:w-auto px-5 py-2.5 rounded-lg bg-[#0B1626] border border-emerald-500/40 text-emerald-400 font-bold text-xs uppercase tracking-wider flex items-center justify-center gap-2">
                        <span>Locked: <?= htmlspecialchars($pickedTeamData['name'] ?? $currentPick['selected_team']) ?></span>
                    </div>
                <?php else: ?>
                    <?php 
                    $currentPickNick = !empty($currentPick['selected_team']) ? (TeamData::get($currentPick['selected_team'])['nick'] ?? '') : '';
                    ?>
                    <button type="button" 
                            id="btnOpenSurvivorConfirm"
                            class="w-full sm:w-auto px-6 py-2.5 rounded-lg bg-[#15803D] hover:bg-emerald-600 text-white font-bold text-xs uppercase tracking-wider transition shadow shrink-0">
                        <span class="btn-confirm-label"><?= !empty($currentPickNick) ? "Review &amp; Confirm {$currentPickNick} Pick" : ($hasCurrentPick ? 'Review &amp; Confirm Survivor Pick' : "Confirm Week {$week} Survivor Pick") ?></span>
                    </button>
                <?php endif; ?>
            </div>
        </form>

    </div>

</div>

<!-- Survivor Confirmation Modal -->
<div id="survivorConfirmModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-[#162235] border border-[#243247] rounded-xl max-w-lg w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        
        <!-- Modal Header -->
        <div class="p-4 border-b border-[#243247] bg-[#0B1626] flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-[#F8FAFC]">Confirm Survivor Selection</h3>
                <span class="text-xs text-[#94A3B8]">Week <?= htmlspecialchars((string) $week) ?> Pool</span>
            </div>
            <button type="button" id="btnSurvivorModalCloseX" class="text-[#94A3B8] hover:text-white text-2xl font-bold leading-none p-2">&times;</button>
        </div>

        <!-- Modal Body -->
        <div class="p-5 space-y-4 overflow-y-auto text-xs">
            
            <!-- Selected Team Showcase Card -->
            <div id="confirmTeamCard" class="p-3.5 rounded-lg border border-[#243247] bg-[#0B1626] flex items-center gap-3.5">
                <img id="confirmTeamLogo" src="" alt="Team Logo" class="w-12 h-12 object-contain">
                <div>
                    <span class="text-[10px] font-mono text-emerald-400 font-bold uppercase tracking-wider block">Selected Winner</span>
                    <h4 id="confirmTeamName" class="text-base font-bold text-[#F8FAFC]">Team Name</h4>
                    <span id="confirmTeamMatchup" class="text-xs text-[#94A3B8] block mt-0.5">Matchup Details</span>
                    <span id="confirmTeamKickoff" class="text-[11px] font-mono text-[#94A3B8] block mt-0.5">Kickoff Time</span>
                </div>
            </div>

            <!-- One and Done Rule Notice -->
            <div class="p-3.5 rounded-lg bg-[#0B1626] border border-[#243247] space-y-1.5 text-xs text-[#94A3B8]">
                <strong class="text-[#F8FAFC] block">One and Done Rule:</strong>
                <p class="leading-relaxed">
                    Once this game kicks off, your selection locks permanently and this team is <strong>burned</strong> (cannot be picked again for the rest of the season).
                </p>
                <p class="leading-relaxed">
                    Prior to this game's kickoff, you can return and change your pick to any other open team.
                </p>
            </div>

        </div>

        <!-- Modal Footer Actions -->
        <div class="p-3.5 border-t border-[#243247] bg-[#0B1626] flex flex-col-reverse sm:flex-row items-center justify-between gap-3">
            <button type="button" id="btnCancelSurvivorModal" 
                    class="w-full sm:w-auto px-4 py-2 rounded-lg bg-[#162235] hover:bg-[#1f2e44] text-[#94A3B8] hover:text-white font-bold text-xs uppercase tracking-wider transition border border-[#243247]">
                Keep Deciding
            </button>
            <button type="button" id="btnConfirmSurvivorFinal" 
                    class="w-full sm:w-auto px-6 py-2 rounded-lg bg-[#15803D] hover:bg-emerald-600 text-white font-bold text-xs uppercase tracking-wider shadow transition">
                <span id="btnConfirmSurvivorFinalText">Confirm Pick</span>
            </button>
        </div>

    </div>
</div>

<!-- Survivor How It Works Modal -->
<div id="survivorHowItWorksModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-[#162235] border border-[#243247] rounded-xl max-w-lg w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        
        <!-- Header -->
        <div class="p-4 border-b border-[#243247] bg-[#0B1626] flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-[#F8FAFC]">Survivor Pool Rules</h3>
                <span class="text-xs text-[#94A3B8]">NFL Eliminator pool guidelines</span>
            </div>
            <button type="button" id="btnCloseSurvivorHowItWorksX" class="text-[#94A3B8] hover:text-white text-2xl font-bold leading-none p-2">&times;</button>
        </div>

        <!-- Content (Scrollable) -->
        <div class="p-4 overflow-y-auto space-y-3 text-xs">
            
            <!-- Rule 1: One Pick Per Week -->
            <div class="flex items-start gap-3 p-3 rounded-lg bg-[#0B1626] border border-[#243247]">
                <span class="w-6 h-6 rounded bg-[#162235] text-[#EAB308] border border-[#243247] font-mono font-bold text-xs flex items-center justify-center shrink-0">1</span>
                <div>
                    <strong class="text-[#F8FAFC] text-xs block mb-0.5">Pick 1 Winner Each Week</strong>
                    <p class="text-[#94A3B8] leading-relaxed">
                        Select exactly one NFL team to win straight-up (no point spreads).
                    </p>
                </div>
            </div>

            <!-- Rule 2: One and Done -->
            <div class="flex items-start gap-3 p-3 rounded-lg bg-[#0B1626] border border-[#243247]">
                <span class="w-6 h-6 rounded bg-[#162235] text-[#EAB308] border border-[#243247] font-mono font-bold text-xs flex items-center justify-center shrink-0">2</span>
                <div>
                    <strong class="text-[#F8FAFC] text-xs block mb-0.5">One and Done &bull; No Repeats</strong>
                    <p class="text-[#94A3B8] leading-relaxed">
                        Each NFL team can only be chosen once per season. Once used, that team is burned for the remainder of the year.
                    </p>
                </div>
            </div>

            <!-- Rule 3: Survive & Advance -->
            <div class="flex items-start gap-3 p-3 rounded-lg bg-[#0B1626] border border-[#243247]">
                <span class="w-6 h-6 rounded bg-[#162235] text-[#EAB308] border border-[#243247] font-mono font-bold text-xs flex items-center justify-center shrink-0">3</span>
                <div>
                    <strong class="text-[#F8FAFC] text-xs block mb-0.5">Survive &amp; Advance</strong>
                    <p class="text-[#94A3B8] leading-relaxed">
                        If your team wins, you advance to next week. If your team loses or ties, you are eliminated.
                    </p>
                </div>
            </div>

            <!-- Rule 4: Kickoff Locking -->
            <div class="flex items-start gap-3 p-3 rounded-lg bg-[#0B1626] border border-[#243247]">
                <span class="w-6 h-6 rounded bg-[#162235] text-[#EAB308] border border-[#243247] font-mono font-bold text-xs flex items-center justify-center shrink-0">4</span>
                <div>
                    <strong class="text-[#F8FAFC] text-xs block mb-0.5">Per-Game Kickoff Locking</strong>
                    <p class="text-[#94A3B8] leading-relaxed">
                        Your pick locks when that specific team's game kicks off. You can freely switch among unstarted games up until each game begins.
                    </p>
                </div>
            </div>

            <!-- Rule 5: Late-Join Fairness & Cash Option -->
            <div class="flex items-start gap-3 p-3 rounded-lg bg-[#0B1626] border border-[#243247]">
                <span class="w-6 h-6 rounded bg-[#162235] text-emerald-400 border border-[#243247] font-mono font-bold text-xs flex items-center justify-center shrink-0">5</span>
                <div>
                    <strong class="text-[#F8FAFC] text-xs block mb-0.5">Late-Join Fairness &amp; Cash Option</strong>
                    <p class="text-[#94A3B8] leading-relaxed">
                        Joining mid-season? To preserve fairness for Week 1 starters, late entrants either forfeit 1 consensus top team per missed week, or enter our upcoming Flight B second-chance bracket. An optional $20 season cash pool is also active!
                    </p>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <div class="p-3.5 border-t border-[#243247] bg-[#0B1626] flex justify-end">
            <button type="button" id="btnCloseSurvivorHowItWorks" class="px-4 py-2 rounded-lg bg-[#EAB308] hover:bg-amber-400 text-[#0B1626] font-bold text-xs uppercase tracking-wider transition">
                Close
            </button>
        </div>

    </div>
</div>

<!-- Auto-Save Toast Notification -->
<div id="survivorAutoSaveToast" class="fixed bottom-6 right-6 z-50 px-4 py-2 rounded-lg bg-[#0B1626] text-emerald-400 border border-emerald-500/40 text-xs font-mono font-bold shadow-xl flex items-center gap-2 opacity-0 pointer-events-none transition-all duration-200 transform translate-y-2">
    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
    </svg>
    <span id="survivorAutoSaveToastText">Saved</span>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('survivorForm');
    const cards = document.querySelectorAll('.survivor-card');
    const btnOpenConfirm = document.getElementById('btnOpenSurvivorConfirm');
    const confirmModal = document.getElementById('survivorConfirmModal');
    const btnCancelModal = document.getElementById('btnCancelSurvivorModal');
    const btnCloseModalX = document.getElementById('btnSurvivorModalCloseX');
    const btnFinalConfirm = document.getElementById('btnConfirmSurvivorFinal');
    const btnFinalConfirmText = document.getElementById('btnConfirmSurvivorFinalText');

    const confirmTeamCard = document.getElementById('confirmTeamCard');
    const confirmTeamLogo = document.getElementById('confirmTeamLogo');
    const confirmTeamName = document.getElementById('confirmTeamName');
    const confirmTeamMatchup = document.getElementById('confirmTeamMatchup');
    const confirmTeamKickoff = document.getElementById('confirmTeamKickoff');

    const autoSaveToast = document.getElementById('survivorAutoSaveToast');
    const autoSaveToastText = document.getElementById('survivorAutoSaveToastText');
    let toastTimeout = null;

    function showAutoSaveToast(text) {
        if (!autoSaveToast || !autoSaveToastText) return;
        autoSaveToastText.textContent = text || 'Saved';
        autoSaveToast.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-2');
        autoSaveToast.classList.add('opacity-100', 'translate-y-0');
        clearTimeout(toastTimeout);
        toastTimeout = setTimeout(() => {
            autoSaveToast.classList.remove('opacity-100', 'translate-y-0');
            autoSaveToast.classList.add('opacity-0', 'pointer-events-none', 'translate-y-2');
        }, 1800);
    }

    function openConfirmModal(checkedRadio) {
        if (!checkedRadio) return;
        const teamAbbr = checkedRadio.value;
        const teamName = checkedRadio.getAttribute('data-name') || teamAbbr;
        const teamNick = checkedRadio.getAttribute('data-nick') || teamAbbr;
        const teamLogo = checkedRadio.getAttribute('data-logo') || '';
        const teamColor = checkedRadio.getAttribute('data-color') || '#15803D';
        const teamMatchup = checkedRadio.getAttribute('data-matchup') || '';
        const teamKickoff = checkedRadio.getAttribute('data-kickoff') || '';

        if (confirmTeamLogo) confirmTeamLogo.src = teamLogo;
        if (confirmTeamName) confirmTeamName.textContent = teamName;
        if (confirmTeamMatchup) confirmTeamMatchup.textContent = teamMatchup;
        if (confirmTeamKickoff) confirmTeamKickoff.textContent = 'Kickoff: ' + teamKickoff;
        if (confirmTeamCard) {
            confirmTeamCard.style.borderColor = teamColor;
        }
        if (btnFinalConfirmText) {
            btnFinalConfirmText.textContent = `Confirm ${teamNick} Pick`;
        }

        if (confirmModal) {
            confirmModal.classList.remove('hidden');
            confirmModal.classList.add('flex');
        }
    }

    cards.forEach(card => {
        card.addEventListener('click', function () {
            const radio = this.querySelector('.survivor-radio');
            if (!radio || radio.disabled) return;

            cards.forEach(c => {
                const r = c.querySelector('.survivor-radio');
                if (r && !r.disabled) {
                    c.classList.remove('is-picked');
                    c.style.borderColor = '#243247';
                    c.style.backgroundColor = '#162235';
                    c.style.boxShadow = 'none';
                    const check = c.querySelector('.pick-check');
                    if (check) {
                        check.classList.remove('scale-100', 'opacity-100');
                        check.classList.add('scale-0', 'opacity-0');
                    }
                }
            });

            const color = radio.getAttribute('data-color') || '#15803D';
            const nick = radio.getAttribute('data-nick') || radio.value;
            this.classList.add('is-picked');
            this.style.borderColor = color;
            this.style.backgroundColor = '#1a2a3f';
            this.style.boxShadow = `0 0 16px ${color}33`;

            const check = this.querySelector('.pick-check');
            if (check) {
                check.classList.remove('scale-0', 'opacity-0');
                check.classList.add('scale-100', 'opacity-100');
            }

            radio.checked = true;

            if (btnOpenConfirm) {
                const labelSpan = btnOpenConfirm.querySelector('.btn-confirm-label');
                if (labelSpan) {
                    labelSpan.textContent = `Review & Confirm ${nick} Pick`;
                }
            }

            const teamVal = radio.value;
            if (teamVal) {
                fetch('/survivor/autosave', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        season_year: <?= (int)$season ?>,
                        week_number: <?= (int)$week ?>,
                        selected_team: teamVal
                    })
                }).then(r => r.json()).then(data => {
                    if (data && data.success) {
                        showAutoSaveToast(nick + ' Pick Saved');
                    }
                }).catch(e => console.warn('Survivor autosave notice:', e));
            }
        });
    });

    if (btnOpenConfirm) {
        btnOpenConfirm.addEventListener('click', function () {
            const checkedRadio = form.querySelector('.survivor-radio:checked');
            if (!checkedRadio) {
                alert('Please select a team before confirming your survivor pick.');
                return;
            }
            openConfirmModal(checkedRadio);
        });
    }

    function closeConfirmModal() {
        if (confirmModal) {
            confirmModal.classList.add('hidden');
            confirmModal.classList.remove('flex');
        }
    }

    if (btnCancelModal) btnCancelModal.addEventListener('click', closeConfirmModal);
    if (btnCloseModalX) btnCloseModalX.addEventListener('click', closeConfirmModal);
    if (confirmModal) {
        confirmModal.addEventListener('click', function (e) {
            if (e.target === confirmModal) closeConfirmModal();
        });
    }

    if (btnFinalConfirm) {
        btnFinalConfirm.addEventListener('click', function () {
            this.disabled = true;
            this.textContent = 'Confirming Pick...';
            form.submit();
        });
    }

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
        if (e.key === 'Escape') {
            closeConfirmModal();
            closeSurvivorModal();
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
