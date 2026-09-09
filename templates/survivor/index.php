<?php
use WallyFootball\Support\TeamData;

ob_start();
$isAlive = !$isEliminated;
$isCashEligible = (bool) $isPaid;
$isPickLocked = !empty($currentPick);
$isSurvivorClosed = !empty($isSurvivorClosed);
$firstKickoffFormatted = $firstKickoffFormatted ?? 'Kickoff of Week ' . $week;
?>

<div class="space-y-6">

    <!-- Header & Single-Week Focus -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800/80 pb-5">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 uppercase tracking-wider">Survivor Pool</span>
                <span class="text-xs text-slate-400">Season <?= htmlspecialchars((string) $season) ?></span>
                <?php if ($isCashEligible): ?>
                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 uppercase tracking-wider">
                        🟢 Cash Prize Eligible
                    </span>
                <?php else: ?>
                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/40 uppercase tracking-wider">
                        🎮 Free / For Fun
                    </span>
                <?php endif; ?>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white flex items-center gap-3">
                <span>Week <?= htmlspecialchars((string) $week) ?> Selection</span>
                <span class="text-xs font-mono font-semibold px-2.5 py-1 rounded-full bg-slate-900 border border-slate-700 text-slate-300">
                    Pick 1 Winner &bull; One &amp; Done
                </span>
            </h1>
        </div>

        <!-- Single-Week Focus Action Bar -->
        <div class="flex items-center gap-2">
            <span class="px-3.5 py-1.5 text-xs font-black font-mono rounded-lg bg-emerald-500 text-slate-950 shadow-sm flex items-center gap-1.5">
                <span>🛡️</span> Active Week <?= $week ?>
            </span>
            <a href="/survivor/standings" 
               class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-800 transition flex items-center gap-1.5"
               title="View Survivor Leaderboard">
                <span>📊</span>
                <span class="hidden sm:inline">Leaderboard</span>
            </a>
            <a href="/fantasy/vault?tab=pools" 
               class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-800 transition flex items-center gap-1.5"
               title="View historical results in the Dynasty Vault">
                <span>🏛️</span>
                <span class="hidden sm:inline">Archives</span>
            </a>

            <!-- How It Works Modal Button -->
            <button type="button" 
                    id="btnOpenSurvivorHowItWorks"
                    class="px-3 py-1.5 text-xs font-bold rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 transition flex items-center gap-1.5 shadow-sm">
                <span>💡</span>
                <span>How It Works</span>
            </button>
        </div>
    </div>

    <!-- Center Column Gridiron Layout (One Game Per Row) -->
    <div class="max-w-3xl mx-auto space-y-6">

        <!-- Survivor Status Banners -->
        <?php if ($isEliminated): ?>
            <!-- Eliminated Banner -->
            <div class="p-5 rounded-2xl bg-rose-500/10 border border-rose-500/30 flex items-start gap-4 shadow-lg">
                <span class="text-3xl">☠️</span>
                <div>
                    <h3 class="text-base font-black text-rose-300">Eliminated from Survivor Pool</h3>
                    <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                        You were knocked out of the Survivor challenge in Week <?= htmlspecialchars((string) ($eliminationWeek ?? 'earlier')) ?>. Your season run has ended.
                    </p>
                </div>
            </div>

        <?php elseif ($isSurvivorClosed): ?>
            <!-- Missed Deadline Banner -->
            <div class="p-5 rounded-2xl bg-rose-500/10 border border-rose-500/30 flex items-start gap-4 shadow-lg">
                <span class="text-3xl">🔒</span>
                <div>
                    <h3 class="text-base font-black text-rose-300">Survivor Selections Closed for Week <?= htmlspecialchars((string) $week) ?></h3>
                    <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                        The first game of Week <?= htmlspecialchars((string) $week) ?> kicked off at <strong><?= htmlspecialchars($firstKickoffFormatted) ?></strong>. Per league rules, survivor picks close at the kickoff of the week's first game.
                    </p>
                </div>
            </div>

        <?php elseif ($isPickLocked): ?>
            <!-- Current Week Selected Pick (Locked In & Final) -->
            <?php 
            $pickedTeamAbbr = $currentPick['selected_team'];
            $pickedTeamData = TeamData::get($pickedTeamAbbr);
            ?>
            <div class="p-6 rounded-2xl border shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4"
                 style="border-color: <?= $pickedTeamData['color'] ?>; background: linear-gradient(135deg, <?= $pickedTeamData['color'] ?>28 0%, #090d16 100%);">
                <div class="flex items-center gap-4">
                    <img src="<?= htmlspecialchars($pickedTeamData['logo']) ?>" 
                         alt="<?= htmlspecialchars($pickedTeamData['name']) ?>" 
                         class="w-16 h-16 object-contain filter drop-shadow-md">
                    <div>
                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                            <span class="text-[10px] font-mono text-emerald-400 font-black uppercase tracking-wider px-2 py-0.5 rounded bg-emerald-500/20 border border-emerald-500/40">
                                🔒 Locked In &bull; One and Done
                            </span>
                            <?php if ($isCashEligible): ?>
                                <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                                    💰 CASH CONTENDER
                                </span>
                            <?php else: ?>
                                <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/40">
                                    🎮 FREE POOL
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="text-2xl font-black text-white"><?= htmlspecialchars($pickedTeamData['name']) ?></span>
                        <span class="text-xs text-slate-300 block mt-0.5">
                            Recorded <?= htmlspecialchars(date('M j, g:i A', strtotime($currentPick['created_at']))) ?> &bull; Selection is final and cannot be modified.
                        </span>
                    </div>
                </div>
                <div class="sm:text-right shrink-0">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900/80 text-emerald-300 border border-emerald-500/30 text-xs font-mono font-bold shadow-sm">
                        <span>🛡️</span> Pick Finalized
                    </span>
                </div>
            </div>

        <?php elseif ($isCashEligible): ?>
            <!-- Alive & Cash Verified Banner (Pre-selection) -->
            <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-slate-950 border border-emerald-500/40 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="p-2.5 rounded-xl bg-emerald-500/15 text-emerald-400 text-2xl border border-emerald-500/30 shrink-0">
                        🛡️
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-sm font-bold text-white">Status: Alive &amp; Cash Prize Eligible</h3>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">ALIVE</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-purple-500/20 text-purple-300 border border-purple-500/40">💰 CASH CONTENDER</span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">Your $10 entry stake is verified by Commissioner Wally. Pick 1 winner below to stay alive!</p>
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

        <?php else: ?>
            <!-- Alive & Free Tier Banner (Pre-selection) -->
            <div class="p-6 rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-amber-950/20 border border-amber-500/40 shadow-xl space-y-4">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-5">
                    <div class="flex items-start gap-4">
                        <div class="p-3 rounded-xl bg-amber-500/15 text-amber-400 text-3xl border border-amber-500/30 shrink-0">
                            🎮
                        </div>
                        <div>
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <h2 class="text-lg font-black text-white">Playing For Fun (Free Tier) — Status: Alive!</h2>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                                    ALIVE
                                </span>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40 uppercase tracking-wider">
                                    FREE / FUN POOL
                                </span>
                            </div>
                            <p class="text-xs text-slate-300 mt-1 leading-relaxed">
                                You are active and can submit your survivor pick below to compete for bragging rights! 
                                <strong>Want to play for the Cash Prize pot?</strong> Send your $10 season stake to Commissioner Wally below with your username. Once verified, your status upgrades to the Cash Prize Pool!
                            </p>
                            <div class="mt-2.5 inline-flex items-center gap-2 px-3 py-1 rounded-lg bg-slate-950/80 border border-slate-700/80 text-xs">
                                <span class="text-slate-400">Payment Memo Note:</span>
                                <span class="font-mono font-bold text-amber-300 select-all">Survivor - <?= htmlspecialchars($user['username'] ?? 'username') ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Action Buttons -->
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

                <!-- Burned Teams display for free tier -->
                <div class="pt-3 border-t border-slate-800 flex items-center gap-2 flex-wrap text-xs">
                    <span class="font-mono uppercase tracking-wider text-slate-400">Your Burned Teams:</span>
                    <?php if (empty($usedTeams)): ?>
                        <span class="text-slate-500 italic">None yet (all 32 teams open)</span>
                    <?php else: ?>
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <?php foreach ($usedTeams as $ut): ?>
                                <span class="font-mono font-bold px-2 py-0.5 rounded bg-slate-800 text-rose-400 border border-rose-900/40 line-through">
                                    <?= htmlspecialchars($ut) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Selection Deadline & One-and-Done Notice (When open for selection) -->
        <?php if (!$isPickLocked && !$isSurvivorClosed && !$isEliminated): ?>
            <div class="p-4 rounded-xl bg-slate-900/85 border border-amber-500/40 flex items-start sm:items-center justify-between gap-3 text-xs shadow-md">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">⏱️</span>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-white font-black">Selection Deadline: <?= htmlspecialchars($firstKickoffFormatted) ?></span>
                            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 font-bold border border-amber-500/30">First Game Kickoff</span>
                        </div>
                        <span class="text-slate-400 text-[11px] block mt-0.5">
                            You have until the week's first game kicks off to submit. <strong>Note: Selections are strictly One and Done</strong> — once confirmed, your pick cannot be modified.
                        </span>
                    </div>
                </div>
                <span class="px-2.5 py-1 rounded-full text-[10px] font-mono font-black bg-amber-500/20 text-amber-300 border border-amber-500/40 shrink-0 uppercase tracking-wider hidden sm:inline-block">
                    ⚠️ One &amp; Done
                </span>
            </div>
        <?php endif; ?>

        <!-- Matchup Selector Form -->
        <form action="/survivor/save" method="POST" id="survivorForm" class="space-y-6">
            <input type="hidden" name="season_year" value="<?= htmlspecialchars((string) $season) ?>">
            <input type="hidden" name="week_number" value="<?= htmlspecialchars((string) $week) ?>">

            <!-- Single Game Per Row Stack -->
            <div class="space-y-6">
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
                    $awayDisabled = $isLocked || $awayUsed || $isEliminated || $isPickLocked || $isSurvivorClosed;

                    // Home Team
                    $homeAbbr = $game['home_team'];
                    $homeTeam = TeamData::get($homeAbbr);
                    $homeColor = $homeTeam['color'];
                    $homePicked = ($currentTeam === $homeAbbr);
                    $homeDisabled = $isLocked || $homeUsed || $isEliminated || $isPickLocked || $isSurvivorClosed;

                    $matchupTitle = "{$awayTeam['name']} @ {$homeTeam['name']}";
                    ?>
                    <div class="rounded-2xl border border-slate-800/80 bg-slate-900/80 overflow-hidden shadow-xl">
                        <!-- Game Row Header -->
                        <div class="flex items-center justify-between px-5 py-3 bg-slate-950/80 border-b border-slate-800/80 text-xs">
                            <div class="flex items-center gap-2.5">
                                <span class="font-mono text-[11px] text-slate-400 font-semibold"><?= htmlspecialchars($kickoffEt) ?></span>
                                <span class="text-slate-600">&bull;</span>
                                <span class="text-slate-400 text-xs font-semibold"><?= htmlspecialchars($matchupTitle) ?></span>
                            </div>
                            <div>
                                <?php if ($isLocked): ?>
                                    <span class="px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">🔒 LOCKED</span>
                                <?php elseif ($isPickLocked): ?>
                                    <span class="px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-800 text-slate-400 border border-slate-700">WEEK FINALIZED</span>
                                <?php else: ?>
                                    <span class="px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">OPEN FOR PICK</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Matchup Row Teams (Side by Side in this Row) -->
                        <div class="p-4 grid grid-cols-2 gap-4" data-game-id="<?= $game['id'] ?>">
                            
                            <!-- Away Team -->
                            <label class="survivor-card relative flex flex-col items-center justify-between p-4 rounded-xl border-2 transition-all select-none
                                <?= $awayDisabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer hover:scale-[1.02]' ?>"
                                style="<?= $awayPicked ? "border-color: {$awayColor}; background: linear-gradient(135deg, {$awayColor}28 0%, #090d16 100%); box-shadow: 0 0 22px {$awayColor}40;" : "border-color: #1e293b; background: rgba(2, 6, 23, 0.7);" ?>"
                                data-abbr="<?= htmlspecialchars($awayAbbr) ?>"
                                data-name="<?= htmlspecialchars($awayTeam['name']) ?>"
                                data-nick="<?= htmlspecialchars($awayTeam['nick']) ?>"
                                data-logo="<?= htmlspecialchars($awayTeam['logo']) ?>"
                                data-color="<?= htmlspecialchars($awayColor) ?>"
                                data-matchup="<?= htmlspecialchars($matchupTitle) ?>"
                                data-kickoff="<?= htmlspecialchars($kickoffEt) ?>">
                                
                                <input type="radio" 
                                       name="selected_team" 
                                       value="<?= htmlspecialchars($awayAbbr) ?>" 
                                       class="sr-only survivor-radio"
                                       data-color="<?= htmlspecialchars($awayColor) ?>"
                                       data-name="<?= htmlspecialchars($awayTeam['name']) ?>"
                                       data-nick="<?= htmlspecialchars($awayTeam['nick']) ?>"
                                       data-logo="<?= htmlspecialchars($awayTeam['logo']) ?>"
                                       data-matchup="<?= htmlspecialchars($matchupTitle) ?>"
                                       data-kickoff="<?= htmlspecialchars($kickoffEt) ?>"
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
                                    <span class="inline-flex items-center justify-center gap-1 w-full py-1 rounded-md text-[10px] font-black uppercase tracking-wider <?= $isPickLocked ? 'bg-emerald-600 text-white shadow' : 'bg-emerald-500 text-slate-950 shadow-md' ?>">
                                        <span><?= $isPickLocked ? '🔒' : '🛡️' ?></span>
                                        <span><?= $isPickLocked ? 'LOCKED IN (ONE &amp; DONE)' : 'SURVIVOR PICK' ?></span>
                                    </span>
                                </div>
                            </label>

                            <!-- Home Team -->
                            <label class="survivor-card relative flex flex-col items-center justify-between p-4 rounded-xl border-2 transition-all select-none
                                <?= $homeDisabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer hover:scale-[1.02]' ?>"
                                style="<?= $homePicked ? "border-color: {$homeColor}; background: linear-gradient(135deg, {$homeColor}28 0%, #090d16 100%); box-shadow: 0 0 22px {$homeColor}40;" : "border-color: #1e293b; background: rgba(2, 6, 23, 0.7);" ?>"
                                data-abbr="<?= htmlspecialchars($homeAbbr) ?>"
                                data-name="<?= htmlspecialchars($homeTeam['name']) ?>"
                                data-nick="<?= htmlspecialchars($homeTeam['nick']) ?>"
                                data-logo="<?= htmlspecialchars($homeTeam['logo']) ?>"
                                data-color="<?= htmlspecialchars($homeColor) ?>"
                                data-matchup="<?= htmlspecialchars($matchupTitle) ?>"
                                data-kickoff="<?= htmlspecialchars($kickoffEt) ?>">
                                
                                <input type="radio" 
                                       name="selected_team" 
                                       value="<?= htmlspecialchars($homeAbbr) ?>" 
                                       class="sr-only survivor-radio"
                                       data-color="<?= htmlspecialchars($homeColor) ?>"
                                       data-name="<?= htmlspecialchars($homeTeam['name']) ?>"
                                       data-nick="<?= htmlspecialchars($homeTeam['nick']) ?>"
                                       data-logo="<?= htmlspecialchars($homeTeam['logo']) ?>"
                                       data-matchup="<?= htmlspecialchars($matchupTitle) ?>"
                                       data-kickoff="<?= htmlspecialchars($kickoffEt) ?>"
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
                                    <span class="inline-flex items-center justify-center gap-1 w-full py-1 rounded-md text-[10px] font-black uppercase tracking-wider <?= $isPickLocked ? 'bg-emerald-600 text-white shadow' : 'bg-emerald-500 text-slate-950 shadow-md' ?>">
                                        <span><?= $isPickLocked ? '🔒' : '🛡️' ?></span>
                                        <span><?= $isPickLocked ? 'LOCKED IN (ONE &amp; DONE)' : 'SURVIVOR PICK' ?></span>
                                    </span>
                                </div>
                            </label>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Sticky Bottom Action Bar -->
            <div class="sticky bottom-16 md:bottom-6 z-30 p-4 rounded-2xl bg-slate-900/95 border border-slate-800 backdrop-blur shadow-2xl flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2 text-xs text-slate-400">
                    <span>🛡️</span>
                    <?php if ($isEliminated): ?>
                        <span class="text-rose-400 font-semibold">Eliminated from the season-long pool in Week <?= htmlspecialchars((string) ($eliminationWeek ?? 'earlier')) ?>.</span>
                    <?php elseif ($isPickLocked): ?>
                        <span class="text-emerald-400 font-semibold">Your Week <?= htmlspecialchars((string) $week) ?> pick is locked in. Per pool rules, survivor selections are one and done and cannot be altered.</span>
                    <?php elseif ($isSurvivorClosed): ?>
                        <span class="text-rose-400 font-semibold">Survivor selections closed at kickoff of the first game (<?= htmlspecialchars($firstKickoffFormatted) ?>).</span>
                    <?php elseif ($isCashEligible): ?>
                        <span class="text-emerald-400 font-semibold">🟢 Cash Prize Contender: Selections are One and Done. Deadline: First game kickoff.</span>
                    <?php else: ?>
                        <span class="text-amber-400 font-semibold">🎮 Free / Fun Pool: Selections are One and Done. Deadline: First game kickoff.</span>
                    <?php endif; ?>
                </div>

                <?php if ($isEliminated): ?>
                    <button type="button" disabled
                            class="w-full sm:w-auto px-8 py-3 rounded-xl bg-slate-800 text-slate-500 font-bold text-xs uppercase tracking-wider cursor-not-allowed border border-slate-700">
                        ☠️ Pool Run Ended
                    </button>
                <?php elseif ($isPickLocked): ?>
                    <!-- Pick is Already Locked In: One and Done (No changing!) -->
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <div class="w-full sm:w-auto px-7 py-3 rounded-xl bg-slate-800/90 border border-emerald-500/50 text-emerald-400 font-black text-xs uppercase tracking-wider flex items-center justify-center gap-2 shadow-lg">
                            <span>🔒</span>
                            <span>Pick Locked In: <?= htmlspecialchars($pickedTeamData['name'] ?? $currentPick['selected_team']) ?></span>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-950/80 border border-emerald-400/50 text-emerald-300">
                                ONE &amp; DONE
                            </span>
                        </div>
                    </div>
                <?php elseif ($isSurvivorClosed): ?>
                    <button type="button" disabled
                            class="w-full sm:w-auto px-8 py-3 rounded-xl bg-slate-800 text-slate-500 font-bold text-xs uppercase tracking-wider cursor-not-allowed border border-slate-700">
                        🔒 Picks Closed (First Game Started)
                    </button>
                <?php else: ?>
                    <!-- Eligible to Pick: Opens Confirmation Modal before locking in -->
                    <button type="button" 
                            id="btnOpenSurvivorConfirm"
                            class="w-full sm:w-auto px-8 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-sm uppercase tracking-wider transition shadow-lg hover:shadow-emerald-500/25 flex items-center justify-center gap-2">
                        <span>🛡️</span>
                        <span>Lock In Week <?= htmlspecialchars((string) $week) ?> Survivor Pick</span>
                        <?php if ($isCashEligible): ?>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-950/80 border border-emerald-400/50 text-emerald-300">
                                $10 CASH
                            </span>
                        <?php else: ?>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-amber-950/80 border border-amber-400/50 text-amber-300">
                                FREE / FUN
                            </span>
                        <?php endif; ?>
                    </button>
                <?php endif; ?>
            </div>
        </form>

    </div>

</div>

<!-- Survivor Confirmation Modal (One and Done Warning & Deadline) -->
<div id="survivorConfirmModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-700/80 rounded-2xl max-w-lg w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        
        <!-- Modal Header -->
        <div class="p-5 border-b border-slate-800 bg-slate-950/80 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="p-2 rounded-xl bg-emerald-500/20 text-emerald-400 text-xl border border-emerald-500/30">🛡️</span>
                <div>
                    <h3 class="text-lg font-black text-white">Confirm Survivor Selection</h3>
                    <span class="text-xs text-slate-400">Week <?= htmlspecialchars((string) $week) ?> Survivor Pool</span>
                </div>
            </div>
            <button type="button" id="btnSurvivorModalCloseX" class="text-slate-400 hover:text-white text-2xl font-bold leading-none p-2">&times;</button>
        </div>

        <!-- Modal Body -->
        <div class="p-6 space-y-5 overflow-y-auto">
            
            <!-- Selected Team Showcase Card -->
            <div id="confirmTeamCard" class="p-4 rounded-xl border flex items-center gap-4 shadow-lg" style="border-color: #10b981; background: rgba(16, 185, 129, 0.1);">
                <img id="confirmTeamLogo" src="" alt="Team Logo" class="w-16 h-16 object-contain filter drop-shadow-md">
                <div>
                    <span class="text-[10px] font-mono text-emerald-400 font-bold uppercase tracking-wider block">Your Selected Winner</span>
                    <h4 id="confirmTeamName" class="text-xl font-black text-white">Team Name</h4>
                    <span id="confirmTeamMatchup" class="text-xs text-slate-300 block mt-0.5">Matchup Details</span>
                    <span id="confirmTeamKickoff" class="text-[11px] font-mono text-slate-400 block mt-0.5">Kickoff Time</span>
                </div>
            </div>

            <!-- One and Done Rule Warning -->
            <div class="p-4 rounded-xl bg-rose-500/15 border-2 border-rose-500/50 text-xs text-rose-200 space-y-2">
                <div class="flex items-center gap-2 text-rose-300 font-black uppercase tracking-wider text-xs">
                    <span class="text-base">⚠️</span>
                    <span>One and Done Rule &bull; Irreversible Decision</span>
                </div>
                <p class="leading-relaxed">
                    Once you confirm this pick, it is <strong>permanently locked in</strong> for Week <?= htmlspecialchars((string) $week) ?> and <strong>CANNOT BE CHANGED</strong> under any circumstances.
                </p>
                <p class="leading-relaxed text-slate-300">
                    Additionally, you will <strong>burn</strong> this team and cannot select them again for the remainder of the season.
                </p>
            </div>

            <!-- Deadline Notice -->
            <div class="p-3.5 rounded-xl bg-slate-950/70 border border-slate-800 text-xs text-slate-300 flex items-start gap-3">
                <span class="text-base">⏱️</span>
                <div>
                    <strong class="text-white block mb-0.5">No Rush Before Kickoff:</strong>
                    <span>You have until the kickoff of the week's first game (<strong><?= htmlspecialchars($firstKickoffFormatted) ?></strong>) to make your choice. If you want more time to analyze injuries or weather, click <em>Keep Deciding</em> below.</span>
                </div>
            </div>

        </div>

        <!-- Modal Footer Actions -->
        <div class="p-4 border-t border-slate-800 bg-slate-950/90 flex flex-col-reverse sm:flex-row items-center justify-between gap-3">
            <button type="button" id="btnCancelSurvivorModal" 
                    class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs uppercase tracking-wider transition">
                ✏️ Keep Deciding
            </button>
            <button type="button" id="btnConfirmSurvivorFinal" 
                    class="w-full sm:w-auto px-7 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs uppercase tracking-wider shadow-lg hover:shadow-emerald-500/30 transition flex items-center justify-center gap-2">
                <span>🔒</span>
                <span id="btnConfirmSurvivorFinalText">Yes, Lock In My Pick (Final)</span>
            </button>
        </div>

    </div>
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

            <!-- Rule 2: One and Done (Irreversible) -->
            <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="p-2 rounded-lg bg-rose-500/15 text-rose-400 font-black text-sm shrink-0">2</span>
                <div>
                    <strong class="text-white text-sm block mb-0.5">One and Done — Selections Lock Permanently</strong>
                    <p class="text-slate-300 leading-relaxed">
                        Once you submit and lock in your survivor pick, <strong>it cannot be changed</strong>. You have until the kickoff of that week's first game to make your choice.
                    </p>
                </div>
            </div>

            <!-- Rule 3: The Golden Rule (No Repeats) -->
            <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="p-2 rounded-lg bg-rose-500/15 text-rose-400 font-black text-sm shrink-0">3</span>
                <div>
                    <strong class="text-white text-sm block mb-0.5">The Golden Rule: Pick Each Team Once</strong>
                    <p class="text-slate-300 leading-relaxed">
                        You can only pick each NFL team <strong>ONCE</strong> during the entire season. Once you choose a team, they are burned (<span class="text-rose-400 font-semibold">BURNED</span>) and cannot be selected again in future weeks. Plan your season-long strategy carefully!
                    </p>
                </div>
            </div>

            <!-- Rule 4: Survive & Advance -->
            <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="p-2 rounded-lg bg-amber-500/15 text-amber-400 font-black text-sm shrink-0">4</span>
                <div>
                    <strong class="text-white text-sm block mb-0.5">Survive &amp; Advance</strong>
                    <p class="text-slate-300 leading-relaxed">
                        If your selected team wins, you survive and advance to the next week. If your team <strong>loses or ties</strong>, you are permanently eliminated from the pool.
                    </p>
                </div>
            </div>

            <!-- Rule 5: Two Ways to Play (Free or Cash Prize) -->
            <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="p-2 rounded-lg bg-blue-500/15 text-blue-400 font-black text-sm shrink-0">5</span>
                <div>
                    <strong class="text-white text-sm block mb-0.5">Two Ways to Play: Free or Cash Prize Pool</strong>
                    <p class="text-slate-300 leading-relaxed">
                        <strong>🎮 Playing For Fun (Free):</strong> Everyone can make weekly picks and compete on the leaderboard for bragging rights at zero cost!<br>
                        <strong>💰 Cash Prize Pool ($10.00 Stake):</strong> Send your $10 stake to Commissioner Wally via Venmo (<span class="text-sky-400 font-bold font-mono">@WallyAtkins</span>), PayPal, or Cash App (<span class="text-emerald-400 font-bold font-mono">$WallyAtkins</span>). The last remaining cash-verified player wins the entire cash pot!
                    </p>
                </div>
            </div>

            <!-- Rule 6: Lockout Times -->
            <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="p-2 rounded-lg bg-purple-500/15 text-purple-400 font-black text-sm shrink-0">6</span>
                <div>
                    <strong class="text-white text-sm block mb-0.5">Deadline: First Game Kickoff</strong>
                    <p class="text-slate-300 leading-relaxed">
                        Survivor picks for each week close when the first game of that week kicks off. Be sure to finalize your selection before the week's opening game!
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
// Survivor Single-Selection across games and Confirmation Modal
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

    // Handle card clicks
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

    // Open Confirmation Modal
    if (btnOpenConfirm) {
        btnOpenConfirm.addEventListener('click', function () {
            const checkedRadio = form.querySelector('.survivor-radio:checked');
            if (!checkedRadio) {
                alert('Please select a team before locking in your survivor pick.');
                return;
            }

            const teamAbbr = checkedRadio.value;
            const teamName = checkedRadio.getAttribute('data-name') || teamAbbr;
            const teamNick = checkedRadio.getAttribute('data-nick') || teamAbbr;
            const teamLogo = checkedRadio.getAttribute('data-logo') || '';
            const teamColor = checkedRadio.getAttribute('data-color') || '#10b981';
            const teamMatchup = checkedRadio.getAttribute('data-matchup') || '';
            const teamKickoff = checkedRadio.getAttribute('data-kickoff') || '';

            // Populate Modal
            if (confirmTeamLogo) confirmTeamLogo.src = teamLogo;
            if (confirmTeamName) confirmTeamName.textContent = teamName;
            if (confirmTeamMatchup) confirmTeamMatchup.textContent = teamMatchup;
            if (confirmTeamKickoff) confirmTeamKickoff.textContent = 'Kickoff: ' + teamKickoff;
            if (confirmTeamCard) {
                confirmTeamCard.style.borderColor = teamColor;
                confirmTeamCard.style.background = `linear-gradient(135deg, ${teamColor}25 0%, #090d16 100%)`;
            }
            if (btnFinalConfirmText) {
                btnFinalConfirmText.textContent = `Yes, Lock In ${teamNick} (Final)`;
            }

            // Show modal
            if (confirmModal) {
                confirmModal.classList.remove('hidden');
                confirmModal.classList.add('flex');
            }
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

    // Final Confirm & Form Submit
    if (btnFinalConfirm) {
        btnFinalConfirm.addEventListener('click', function () {
            this.disabled = true;
            this.innerHTML = '<span>🔒</span> Locking In Pick...';
            form.submit();
        });
    }

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
