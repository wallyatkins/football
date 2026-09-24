<?php

declare(strict_types=1);

use WallyFootball\Support\TeamData;

/**
 * Weekly Pick Wizard Component
 * Full-screen, responsive, clutter-free 5-state wizard flow.
 *
 * Variables expected:
 *   $games (array)
 *   $userPicks (array)
 *   $season (int)
 *   $week (int)
 *   $entry (array|null)
 *   $tiebreakerGame (array|null)
 *   $isWeekLocked (bool)
 *   $usedSurvivorTeams (array) [optional]
 *   $missedSurvivorWeeks (array) [optional]
 *   $needsSurvivorCatchup (bool) [optional]
 *   $currentSurvivorPick (string|null) [optional]
 *   $isSurvivorEliminated (bool) [optional]
 *   $hasSeenPickemIntro (bool) [optional]
 *   $hasSeenSurvivorIntro (bool) [optional]
 */

$usedSurvivorTeams = $usedSurvivorTeams ?? [];
$missedSurvivorWeeks = $missedSurvivorWeeks ?? [];
$needsSurvivorCatchup = $needsSurvivorCatchup ?? (!empty($missedSurvivorWeeks));
$currentSurvivorPick = $currentSurvivorPick ?? null;
$isSurvivorEliminated = $isSurvivorEliminated ?? false;
$hasSeenPickemIntro = $hasSeenPickemIntro ?? false;
$hasSeenSurvivorIntro = $hasSeenSurvivorIntro ?? false;

$allNflTeams = TeamData::load();
$pylTeamsList = [];
foreach ($allNflTeams as $abbr => $t) {
    $pylTeamsList[] = [
        'abbr' => $abbr,
        'name' => $t['name'],
        'nick' => $t['nick'],
        'logo' => $t['logo'],
        'color' => $t['color'],
        'color2' => $t['color2'] ?? '#ffffff',
    ];
}

$wizardGames = [];
foreach ($games as $g) {
    $hTeam = TeamData::get($g['home_team']);
    $aTeam = TeamData::get($g['away_team']);
    $kt = strtotime($g['kickoff_time']);
    $dt = (new \DateTimeImmutable("@{$kt}"))->setTimezone(new \DateTimeZone('America/New_York'));

    $userPick = $g['user_pick'] ?? ($userPicks[$g['id']] ?? null);

    $wizardGames[] = [
        'id' => (int) $g['id'],
        'home_team' => $g['home_team'],
        'home_name' => $hTeam['name'],
        'home_nick' => $hTeam['nick'],
        'home_color' => $hTeam['color'],
        'home_color2' => $hTeam['color2'] ?? '#ffffff',
        'home_logo' => $hTeam['logo'],
        'home_score' => $g['home_score'] ?? null,

        'away_team' => $g['away_team'],
        'away_name' => $aTeam['name'],
        'away_nick' => $aTeam['nick'],
        'away_color' => $aTeam['color'],
        'away_color2' => $aTeam['color2'] ?? '#ffffff',
        'away_logo' => $aTeam['logo'],
        'away_score' => $g['away_score'] ?? null,

        'kickoff_time' => $g['kickoff_time'],
        'kickoff_timestamp' => $kt,
        'kickoff_formatted' => $dt->format('l, M j \a\t g:i A T'),
        'kickoff_short' => $dt->format('D, M j @ g:i A'),
        'is_mnf' => !empty($g['is_mnf']),
        'status' => $g['status'] ?? 'scheduled',
        'is_locked' => !empty($g['is_locked']),
        'user_pick' => $userPick,
        'winning_team' => $g['winning_team'] ?? null,
        'pick_result' => $g['pick_result'] ?? 'pending',
    ];
}

$tbGameId = !empty($tiebreakerGame) ? (int) $tiebreakerGame['id'] : null;
$tbCurrentPoints = $entry['mnf_total_points_prediction'] ?? null;
$tbIsLocked = !empty($tiebreakerGame) && (strtotime($tiebreakerGame['kickoff_time']) + 3600 <= time());
$tbAwayData = !empty($tiebreakerGame) ? TeamData::get($tiebreakerGame['away_team']) : null;
$tbHomeData = !empty($tiebreakerGame) ? TeamData::get($tiebreakerGame['home_team']) : null;

// Explicit launch: only when ?mode=wizard is in URL or route /pickem/wizard
$isAutoLaunch = (isset($_GET['mode']) && $_GET['mode'] === 'wizard')
    || str_contains($_SERVER['REQUEST_URI'] ?? '', '/pickem/wizard');
?>

<!-- Retro Arcade 8-Bit Font for Authentic Press Your Luck (Self-Hosted) -->
<link rel="stylesheet" href="/assets/css/fonts.css">

<style>
  .font-pyl { font-family: 'Press Start 2P', monospace; }

  /* Full Viewport Split Screen Support */
  .wizard-viewport {
    height: 100dvh;
    min-height: 100dvh;
  }

  /* Team Panel Ambient Radial Gradient Vignette */
  .team-panel-vignette {
    background-image: radial-gradient(circle at center, transparent 30%, rgba(0, 0, 0, 0.45) 100%);
  }

  /* Press Your Luck Chassis */
  .pyl-chassis {
    background: #000000;
    border: 5px solid #ffea00;
    box-shadow: 0 0 35px rgba(255, 234, 0, 0.4), inset 0 0 20px rgba(255, 200, 0, 0.2);
    border-radius: 18px;
    position: relative;
  }

  /* PYL Square - Team Logos ONLY, Centered */
  .pyl-square {
    background: #0d1527;
    border: 2px solid #334155;
    transition: all 0.08s ease;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    user-select: none;
  }
  .pyl-square.is-lit {
    border-color: #ff0055 !important;
    background: rgba(255, 0, 85, 0.35) !important;
    box-shadow: 0 0 25px #ff0055, inset 0 0 12px #ffea00 !important;
    transform: scale(1.05);
    z-index: 30;
  }
  .pyl-square.is-lit::after {
    content: '';
    position: absolute;
    inset: 0;
    border: 2px solid #ffea00;
    pointer-events: none;
  }

  /* 3-flash freeze animation */
  @keyframes flash-three {
    0%, 33%, 66% { opacity: 1; filter: brightness(2); }
    16%, 50%, 83% { opacity: 0.3; filter: brightness(0.5); }
    100% { opacity: 1; filter: brightness(1); }
  }
  .flash-freeze {
    animation: flash-three 0.7s ease-in-out;
  }

  /* Slide Switch Animation */
  .slide-fade {
    transition: opacity 0.2s ease, transform 0.2s ease;
  }
  .slide-switching {
    opacity: 0;
    transform: scale(0.8);
  }

  /* Circular Start / Stop Center Button */
  .pyl-circle-button {
    background: radial-gradient(circle at 35% 35%, #10b981, #059669 60%, #047857 100%);
    box-shadow: 0 8px 0 #064e3b, 0 15px 30px rgba(16, 185, 129, 0.4);
    border: 3px solid #6ee7b7;
    transition: transform 0.1s ease, box-shadow 0.1s ease, background 0.2s ease;
  }
  .pyl-circle-button:active {
    transform: translateY(4px) scale(0.96);
    box-shadow: 0 4px 0 #064e3b, 0 8px 15px rgba(16, 185, 129, 0.3);
  }
  .pyl-circle-button.is-spinning {
    background: radial-gradient(circle at 35% 35%, #ff4d4d, #cc0000 60%, #800000 100%) !important;
    box-shadow: 0 8px 0 #550000, 0 0 35px rgba(255, 0, 0, 0.8) !important;
    border-color: #ff9999 !important;
    animation: pyl-btn-pulse 0.9s infinite alternate;
  }
  @keyframes pyl-btn-pulse {
    0% { transform: scale(1); filter: brightness(1); }
    100% { transform: scale(1.05); filter: brightness(1.2); }
  }

  /* Audio Equalizer Bars */
  @keyframes eq-pulse-1 { 0%, 100% { height: 4px; } 50% { height: 16px; } }
  @keyframes eq-pulse-2 { 0%, 100% { height: 14px; } 50% { height: 6px; } }
  @keyframes eq-pulse-3 { 0%, 100% { height: 8px; } 50% { height: 18px; } }
  .eq-b1 { animation: eq-pulse-1 0.7s infinite ease-in-out; }
  .eq-b2 { animation: eq-pulse-2 0.5s infinite ease-in-out; }
  .eq-b3 { animation: eq-pulse-3 0.8s infinite ease-in-out; }
</style>

<!-- Audio Assets -->
<audio id="audioNflTheme" src="/media/nfl-theme.mp3" preload="auto" loop></audio>
<audio id="audioPylSoundboard" src="/media/press-your-luck-sound-board.mp3" preload="auto"></audio>

<!-- Full-Screen Interactive Wizard Modal Container -->
<div id="pickWizardModal" 
     class="<?= $isAutoLaunch ? '' : 'hidden' ?> fixed inset-0 z-50 bg-[#070d17] text-white flex flex-col wizard-viewport w-full overflow-hidden select-none"
     role="dialog" 
     aria-modal="true" 
     aria-label="Weekly Pick Wizard">

    <!-- TOP CONTROL BAR -->
    <header class="relative z-20 border-b border-[#243247]/80 bg-[#0B1626]/95 px-3 py-2 sm:px-6 sm:py-2.5 shrink-0">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-2 sm:gap-4">
            
            <!-- Left: Exit Button & Pool Title -->
            <div class="flex items-center gap-2 sm:gap-3">
                <button type="button" 
                        id="btnWizardExit" 
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-[#243247] bg-[#162235] hover:bg-[#1e2e48] hover:border-slate-500 text-slate-300 hover:text-white text-xs font-bold transition shadow-sm cursor-pointer"
                        title="Exit to Standard View (Esc)">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span class="hidden sm:inline">Exit to Standard View</span>
                    <span class="sm:hidden">Exit</span>
                </button>

                <div class="hidden md:flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded font-mono text-[10px] font-black bg-[#EAB308] text-[#0B1626] uppercase tracking-wider">
                        Week <?= $week ?>
                    </span>
                    <span class="text-xs font-bold text-slate-300">Wally's NFL Pool</span>
                </div>
            </div>

            <!-- Center: Step Progress Tracker Pills -->
            <div class="flex flex-col items-center flex-1 max-w-md mx-2">
                <div class="grid grid-cols-5 gap-1 sm:gap-1.5 w-full text-center text-[9px] sm:text-[10px] font-mono font-bold">
                    <div id="step-pill-1" onclick="goToState(1)" class="p-1 rounded cursor-pointer bg-amber-500 text-slate-950 font-black shadow truncate">1. Pick'em</div>
                    <div id="step-pill-2" onclick="goToState(2)" class="p-1 rounded cursor-pointer bg-slate-900 border border-slate-800 text-slate-400 truncate">2. Tiebreaker</div>
                    <div id="step-pill-3" onclick="goToState(3)" class="p-1 rounded cursor-pointer bg-slate-900 border border-slate-800 text-slate-400 truncate">3. Catch-Up</div>
                    <div id="step-pill-4" onclick="goToState(4)" class="p-1 rounded cursor-pointer bg-slate-900 border border-slate-800 text-slate-400 truncate">4. Survivor</div>
                    <div id="step-pill-5" onclick="goToState(5)" class="p-1 rounded cursor-pointer bg-slate-900 border border-slate-800 text-slate-400 truncate">5. Review</div>
                </div>
                
                <!-- Overall Progress Bar -->
                <div class="w-full h-1 rounded-full bg-[#162235] border border-[#243247] overflow-hidden mt-1">
                    <div id="wizardProgressBar" 
                         class="h-full rounded-full bg-gradient-to-r from-emerald-500 via-amber-400 to-[#EAB308] transition-all duration-300 ease-out" 
                         style="width: 0%;"></div>
                </div>
            </div>

            <!-- Right: Utility Corner (Help & Persistent Audio Toggle) -->
            <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                <!-- Help / Rules Onboarding Button -->
                <button type="button" 
                        id="btnWizardHelp"
                        onclick="openContextualRules()"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-[#243247] bg-[#162235] hover:bg-[#1e2e48] text-slate-300 hover:text-amber-400 transition cursor-pointer text-xs font-bold font-mono"
                        title="Rules & How to Play">
                    <span>📖</span>
                    <span class="hidden sm:inline">Rules</span>
                </button>

                <!-- Persistent Speaker / Mute Toggle -->
                <button type="button" 
                        id="btnWizardAudioToggle"
                        onclick="toggleAudioPlayback()"
                        class="inline-flex items-center gap-1.5 px-2 py-1.5 rounded-lg border border-[#243247] bg-[#162235] hover:bg-[#1e2e48] text-xs font-bold text-slate-300 hover:text-white transition cursor-pointer"
                        title="Toggle Background Audio">
                    <span id="wizardAudioIcon">🔊</span>
                    <div id="wizardAudioEqualizer" class="flex items-end gap-0.5 h-3.5 px-0.5">
                        <div id="eq1" class="w-0.5 bg-amber-400 rounded-full" style="height: 4px;"></div>
                        <div id="eq2" class="w-0.5 bg-amber-400 rounded-full" style="height: 4px;"></div>
                        <div id="eq3" class="w-0.5 bg-amber-400 rounded-full" style="height: 4px;"></div>
                    </div>
                </button>

                <!-- Shortcuts button on larger screens -->
                <button type="button" 
                        id="btnWizardShortcuts"
                        class="hidden lg:inline-flex items-center gap-1 px-2 py-1.5 rounded-lg border border-[#243247] bg-[#162235] hover:bg-[#1e2e48] text-xs font-bold text-slate-400 hover:text-slate-200 transition"
                        title="Keyboard Shortcuts">
                    <span>⌨️</span>
                </button>
            </div>
        </div>
    </header>

    <!-- MAIN VIEWPORT: 5 CLEAN WORKFLOW STATES -->
    <main class="relative z-10 flex-1 flex flex-col items-center justify-between p-2 sm:p-4 overflow-hidden h-full min-h-0">
        <div class="w-full max-w-4xl mx-auto flex-1 flex flex-col items-center justify-between min-h-0">

            <!-- ================================================================= -->
            <!-- STATE 1: WEEKLY PICK'EM CAROUSEL (De-cluttered Fullscreen Matchup) -->
            <!-- ================================================================= -->
            <section id="state-1-view" class="w-full flex-1 flex flex-col items-center justify-between min-h-0">
                
                <!-- Matchup Card Container: 100% Mobile Height & Desktop Grid (No Page Scroll) -->
                <div id="wizardCardContainer" class="w-full flex-1 flex flex-col items-center justify-center min-h-0 relative py-1">
                    
                    <div id="wizardLockNotice" class="hidden w-full mb-1 p-1 rounded-lg bg-amber-950/60 border border-amber-500/40 text-amber-300 text-[11px] font-mono text-center">
                        🔒 Kickoff Passed &bull; Game Locked
                    </div>

                    <!-- UNIFIED MATCHUP DUEL VIEWPORT (Mobile Vertical Split / Desktop Horizontal Split: Panels Touch Directly, VS Overlay Bridges Seam) -->
                    <div class="relative w-full flex-1 flex flex-col md:grid md:grid-cols-2 rounded-2xl border-2 border-[#243247] bg-[#070d17] shadow-2xl overflow-hidden min-h-0 select-none">

                        <!-- AWAY TEAM PANEL (Top half on mobile, Left half on desktop - touches Home Panel) -->
                        <div id="wizardAwayCard" 
                             class="wizard-team-panel flex-1 h-full w-full flex flex-col items-center justify-center p-3 sm:p-6 lg:p-8 cursor-pointer relative overflow-hidden transition-all duration-200 select-none active:brightness-90 group team-panel-vignette"
                             data-team-type="away">
                            
                            <!-- Large Prominent Bold Team Logo (Maximized in area) -->
                            <div class="w-full h-full flex items-center justify-center pointer-events-none p-2 sm:p-4">
                                <img id="wizardAwayLogo" 
                                     src="" 
                                     alt="Away Team Logo" 
                                     class="max-w-[70%] max-h-[75%] sm:max-w-[75%] sm:max-h-[80%] md:max-w-[80%] md:max-h-[80%] object-contain filter drop-shadow-[0_15px_30px_rgba(0,0,0,0.65)] group-hover:scale-105 group-active:scale-95 transition-transform duration-200">
                            </div>

                            <!-- Pick Selection Confirmation Badge (Gold Checkmark) -->
                            <div id="wizardAwayCheck" 
                                 class="absolute top-3 right-3 sm:top-5 sm:right-5 md:right-auto md:left-5 w-8 h-8 sm:w-11 sm:h-11 rounded-full bg-amber-400 text-slate-950 font-black flex items-center justify-center shadow-xl transition-all duration-200 scale-0 opacity-0 z-20 pointer-events-none">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 stroke-[3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </div>

                            <!-- Subtle Accent Selection Ring -->
                            <div id="wizardAwaySelectionRing" class="absolute inset-0 border-4 border-amber-400 pointer-events-none opacity-0 transition-opacity duration-200"></div>
                        </div>

                        <!-- HOME TEAM PANEL (Bottom half on mobile, Right half on desktop - touches Away Panel) -->
                        <div id="wizardHomeCard" 
                             class="wizard-team-panel flex-1 h-full w-full flex flex-col items-center justify-center p-3 sm:p-6 lg:p-8 cursor-pointer relative overflow-hidden transition-all duration-200 select-none active:brightness-90 group border-t-2 md:border-t-0 md:border-l border-slate-900/60 team-panel-vignette"
                             data-team-type="home">
                            
                            <!-- Large Prominent Bold Team Logo (Maximized in area) -->
                            <div class="w-full h-full flex items-center justify-center pointer-events-none p-2 sm:p-4">
                                <img id="wizardHomeLogo" 
                                     src="" 
                                     alt="Home Team Logo" 
                                     class="max-w-[70%] max-h-[75%] sm:max-w-[75%] sm:max-h-[80%] md:max-w-[80%] md:max-h-[80%] object-contain filter drop-shadow-[0_15px_30px_rgba(0,0,0,0.65)] group-hover:scale-105 group-active:scale-95 transition-transform duration-200">
                            </div>

                            <!-- Pick Selection Confirmation Badge (Gold Checkmark) -->
                            <div id="wizardHomeCheck" 
                                 class="absolute top-3 right-3 sm:top-5 sm:right-5 w-8 h-8 sm:w-11 sm:h-11 rounded-full bg-amber-400 text-slate-950 font-black flex items-center justify-center shadow-xl transition-all duration-200 scale-0 opacity-0 z-20 pointer-events-none">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 stroke-[3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </div>

                            <!-- Subtle Accent Selection Ring -->
                            <div id="wizardHomeSelectionRing" class="absolute inset-0 border-4 border-amber-400 pointer-events-none opacity-0 transition-opacity duration-200"></div>
                        </div>

                        <!-- Date/Time Header on Desktop (Centered at top of seam above VS) -->
                        <div class="hidden md:flex absolute top-4 left-1/2 -translate-x-1/2 z-30 pointer-events-none whitespace-nowrap bg-black/75 border border-slate-700/80 px-3 py-1 rounded-full text-[11px] font-mono font-bold text-slate-300 shadow-md" id="wizardKickoffText">
                        </div>

                        <!-- Date/Time Header on Mobile (Overlaid along horizontal seam) -->
                        <div class="md:hidden absolute top-1/2 left-3 -translate-y-1/2 z-30 pointer-events-none bg-black/80 border border-slate-700/80 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold text-slate-300 shadow" id="wizardKickoffTextMobile">
                        </div>

                        <!-- CONNECTING VS OVERLAY BADGE (Centered right on seam between Away & Home) -->
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-30 pointer-events-none flex items-center justify-center">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 md:w-16 md:h-16 rounded-full bg-[#0B1626] border-2 border-amber-400 text-amber-400 font-mono font-black text-xs sm:text-sm md:text-base flex items-center justify-center shadow-[0_0_25px_rgba(0,0,0,0.85)] ring-4 ring-[#070d17]/80">
                                VS
                            </div>
                        </div>

                    </div>
                </div>

                <!-- NAVIGATION FOOTER BAR (PREV, Bubbles 1..N, NEXT) -->
                <div class="w-full pt-2 pb-1 flex items-center justify-between gap-2 shrink-0">
                    <!-- Previous Button: strictly labeled PREV -->
                    <button type="button" 
                            id="btnWizardPrev"
                            class="px-4 py-2 sm:px-5 sm:py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white font-mono font-bold text-xs uppercase tracking-wider transition disabled:opacity-25 disabled:pointer-events-none shadow cursor-pointer">PREV</button>

                    <!-- Game Jump Indicators: Row of Compact Numbered Bubbles (1 through 16) -->
                    <div id="wizardTimeline" class="flex items-center justify-center gap-1 sm:gap-1.5 overflow-x-auto py-1 max-w-full px-1">
                        <!-- Dynamically populated via renderTimeline() -->
                    </div>

                    <!-- Next Button: strictly labeled NEXT -->
                    <button type="button" 
                            id="btnWizardNext"
                            class="px-5 py-2 sm:px-6 sm:py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-mono font-black text-xs uppercase tracking-wider transition shadow-lg shadow-amber-500/20 active:scale-95 cursor-pointer">NEXT</button>
                </div>

            </section>

            <!-- ================================================================= -->
            <!-- STATE 2: TIEBREAKER INPUT (MONDAY NIGHT FOOTBALL)                 -->
            <!-- ================================================================= -->
            <section id="state-2-view" class="hidden w-full max-w-2xl mx-auto my-auto text-center space-y-4 sm:space-y-6">
                <div class="p-5 sm:p-8 rounded-2xl bg-gradient-to-b from-[#162235] to-[#0d1624] border-2 border-amber-500/40 shadow-2xl">
                    
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 text-xs font-mono font-bold mb-2">
                        <span>🏈 Designated Tiebreaker Game</span>
                    </div>

                    <h2 class="text-xl sm:text-2xl md:text-3xl font-black text-white tracking-tight">
                        Monday Night Football: Enter Total Combined Score
                    </h2>

                    <!-- Matchup Context Details with Team Logos -->
                    <?php if ($tbAwayData && $tbHomeData): ?>
                        <div class="flex items-center justify-center gap-4 my-3 p-3 rounded-xl bg-black/40 border border-slate-800 max-w-md mx-auto">
                            <div class="flex items-center gap-2">
                                <img src="<?= htmlspecialchars($tbAwayData['logo']) ?>" class="w-8 h-8 object-contain">
                                <span class="font-bold text-sm text-slate-200"><?= htmlspecialchars($tbAwayData['name']) ?></span>
                            </div>
                            <span class="text-xs font-mono font-black text-amber-400">@</span>
                            <div class="flex items-center gap-2">
                                <img src="<?= htmlspecialchars($tbHomeData['logo']) ?>" class="w-8 h-8 object-contain">
                                <span class="font-bold text-sm text-slate-200"><?= htmlspecialchars($tbHomeData['name']) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Dynamic Score Generator & Flanking Steppers -->
                    <div id="wizardTiebreakerBox" class="my-6 p-5 sm:p-6 rounded-2xl bg-slate-950 border-2 border-amber-500/50 shadow-[0_0_30px_rgba(234,179,8,0.2)] max-w-md mx-auto">
                        <span class="text-[10px] font-mono uppercase tracking-widest text-slate-400 block mb-2">Combined Score Prediction</span>
                        
                        <div class="flex items-center justify-center gap-4">
                            <!-- Large Touch-Friendly Minus Stepper -->
                            <button type="button" 
                                    id="btnTbMinus"
                                    onclick="adjustTiebreaker(-1)" 
                                    class="w-14 h-14 rounded-2xl bg-slate-800 hover:bg-slate-700 text-white font-mono font-black text-3xl transition active:scale-90 flex items-center justify-center shadow-lg border border-slate-700 cursor-pointer">
                                −
                            </button>
                            
                            <!-- Direct Click-To-Edit Numeric Input -->
                            <div class="relative w-36 sm:w-40">
                                <input type="number" 
                                       id="wizardTiebreakerInput" 
                                       value="<?= $tbCurrentPoints ?? 47 ?>" 
                                       min="10" 
                                       max="120"
                                       class="w-full text-center text-5xl sm:text-6xl font-black font-mono bg-transparent text-amber-400 outline-none border-b-2 border-amber-500/50 focus:border-amber-400 pb-1 tabular-nums">
                                <span class="block text-[10px] font-mono text-slate-400 mt-1 uppercase font-bold">TOTAL POINTS</span>
                            </div>

                            <!-- Large Touch-Friendly Plus Stepper -->
                            <button type="button" 
                                    id="btnTbPlus"
                                    onclick="adjustTiebreaker(1)" 
                                    class="w-14 h-14 rounded-2xl bg-slate-800 hover:bg-slate-700 text-white font-mono font-black text-3xl transition active:scale-90 flex items-center justify-center shadow-lg border border-slate-700 cursor-pointer">
                                +
                            </button>
                        </div>

                        <!-- Randomized Score Spinner & Settle Controls -->
                        <div class="mt-6 flex items-center justify-center gap-2 flex-wrap pt-4 border-t border-slate-800/80">
                            <button type="button" 
                                    id="btnSpinTb"
                                    onclick="handleTiebreakerSpin()" 
                                    class="px-4 py-2 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 text-xs font-mono font-bold transition flex items-center gap-1.5 cursor-pointer">
                                <span>🎰</span>
                                <span id="tbSpinBtnLabel">Spin Random Total (34–54)</span>
                            </button>
                            <button type="button" onclick="setTiebreakerVal(41)" class="px-2.5 py-1 rounded bg-slate-900 border border-slate-800 text-slate-400 hover:text-white text-xs font-mono">41</button>
                            <button type="button" onclick="setTiebreakerVal(47)" class="px-2.5 py-1 rounded bg-slate-900 border border-slate-800 text-slate-400 hover:text-white text-xs font-mono">47</button>
                            <button type="button" onclick="setTiebreakerVal(51)" class="px-2.5 py-1 rounded bg-slate-900 border border-slate-800 text-slate-400 hover:text-white text-xs font-mono">51</button>
                        </div>
                    </div>

                    <!-- Navigation Handoff -->
                    <div class="flex items-center justify-between pt-4 border-t border-slate-800">
                        <button type="button" onclick="goToState(1)" class="px-4 py-2.5 rounded-xl bg-slate-800 text-slate-300 text-xs font-mono font-bold transition cursor-pointer">
                            &larr; PREV
                        </button>
                        <button type="button" onclick="handleTiebreakerNext()" class="px-6 py-3 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-400 hover:from-amber-400 text-slate-950 font-black text-xs font-mono uppercase tracking-wider transition shadow-lg cursor-pointer">
                            NEXT &rarr;
                        </button>
                    </div>

                </div>
            </section>

            <!-- ================================================================= -->
            <!-- STATE 3: PRESS YOUR LUCK ELIMINATION (SURVIVOR CATCH-UP)          -->
            <!-- ================================================================= -->
            <section id="state-3-view" class="hidden w-full max-w-4xl mx-auto my-auto space-y-3 sm:space-y-4">
                
                <!-- Catch-Up Context Banner -->
                <div class="p-3.5 sm:p-4 rounded-2xl border-2 border-fuchsia-500/40 bg-gradient-to-r from-[#170524] via-slate-900 to-slate-950 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-fuchsia-500 text-slate-950 font-pyl text-[9px] uppercase">
                                Late Entrant Catch-Up
                            </span>
                            <span class="text-xs font-mono text-fuchsia-300">Fair-Play Handicap</span>
                        </div>
                        <h2 class="text-base sm:text-lg font-black text-white mt-1" id="pylCatchupTitle">
                            Survivor Pool: Random Team Elimination
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5" id="pylCatchupSubtitle">
                            Missed weeks detected. Randomly eliminate handicap teams to catch up with Week 1 players!
                        </p>
                    </div>
                    <div class="flex sm:flex-col items-center sm:items-end justify-between gap-2 font-mono text-xs">
                        <button type="button" 
                                onclick="openPylIntro()" 
                                class="px-2.5 py-1 rounded-lg bg-fuchsia-950/80 hover:bg-fuchsia-900 border border-fuchsia-500/50 text-fuchsia-300 text-[11px] font-bold transition flex items-center gap-1 cursor-pointer">
                            <span>🎲</span>
                            <span>How Random Elimination Works</span>
                        </button>
                        <span id="pylHandicapCounter" class="text-amber-400 font-black text-xs sm:text-sm">0 of 0 Burned</span>
                    </div>
                </div>

                <!-- Explanatory Guide Box for Late Arrivals -->
                <div class="p-3 rounded-xl bg-slate-900/90 border border-slate-800 text-xs text-slate-300 flex items-start gap-2.5 shadow-sm">
                    <span class="text-base shrink-0 mt-0.5">ℹ️</span>
                    <p class="leading-relaxed">
                        <strong class="text-white">Why are teams eliminated?</strong> Players who joined in Week 1 have burned one team per week that they cannot use again. To ensure fair competition, you must randomly eliminate <span class="text-amber-400 font-bold"><?= count($missedSurvivorWeeks) ?></span> handicap team(s) using the retro board below before picking for Week <?= $week ?>. Press <span class="text-emerald-400 font-bold">Start!</span> to spin, then <span class="text-rose-400 font-bold">Stop!</span> to burn a team!
                    </p>
                </div>

                <!-- The 18-Square Chassis -->
                <div class="pyl-chassis p-3 sm:p-5">
                    <div class="grid grid-cols-6 grid-rows-5 gap-1.5 sm:gap-2.5 aspect-[6/5] w-full">
                        
                        <!-- TOP ROW: Squares 1 to 6 (Team logos only, centered) -->
                        <div id="pyl-sq-1" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-1 sm:p-1.5"></div></div>
                        <div id="pyl-sq-2" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-1 sm:p-1.5"></div></div>
                        <div id="pyl-sq-3" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-1 sm:p-1.5"></div></div>
                        <div id="pyl-sq-4" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-1 sm:p-1.5"></div></div>
                        <div id="pyl-sq-5" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-1 sm:p-1.5"></div></div>
                        <div id="pyl-sq-6" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-1 sm:p-1.5"></div></div>

                        <!-- ROW 2: Sq 18 (Left), Sq 7 (Right) -->
                        <div id="pyl-sq-18" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-1 sm:p-1.5"></div></div>
                        
                        <!-- CENTER STAGE: Circular Start/Stop Button Only, Whammy Overlay -->
                        <div class="col-span-4 row-span-3 rounded-2xl bg-gradient-to-b from-[#080f1d] via-[#050a14] to-black border-2 border-slate-800 p-2 sm:p-4 flex items-center justify-center text-center relative overflow-hidden shadow-2xl">
                            
                            <!-- Transparent Whammy Canvas Overlay -->
                            <canvas id="whammyCanvas" width="480" height="360" class="absolute inset-0 w-full h-full object-contain pointer-events-none z-40 hidden"></canvas>
                            <video id="whammyVideoPlayer" playsinline preload="auto" class="hidden"></video>

                            <!-- Circular Start/Stop Button: Sole element in center stage -->
                            <button type="button" 
                                    id="btnPylBuzzer"
                                    onclick="handlePylBuzzer()"
                                    class="pyl-circle-button w-24 h-24 sm:w-32 sm:h-32 md:w-36 md:h-36 rounded-full font-black text-xl sm:text-2xl md:text-3xl tracking-wider uppercase text-white shadow-2xl active:scale-95 flex items-center justify-center border-4 border-white/20 cursor-pointer select-none z-20">
                                <span id="pylBuzzerLabel">Start!</span>
                            </button>

                        </div>

                        <div id="pyl-sq-7" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-1 sm:p-1.5"></div></div>

                        <!-- ROW 3: Sq 17 (Left), Sq 8 (Right) -->
                        <div id="pyl-sq-17" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-1 sm:p-1.5"></div></div>
                        <div id="pyl-sq-8" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-1 sm:p-1.5"></div></div>

                        <!-- ROW 4: Sq 16 (Left), Sq 9 (Right) -->
                        <div id="pyl-sq-16" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-1 sm:p-1.5"></div></div>
                        <div id="pyl-sq-9" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-1 sm:p-1.5"></div></div>

                        <!-- BOTTOM ROW: Squares 15 to 10 (Right to Left) -->
                        <div id="pyl-sq-15" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-1 sm:p-1.5"></div></div>
                        <div id="pyl-sq-14" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-1 sm:p-1.5"></div></div>
                        <div id="pyl-sq-13" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-1 sm:p-1.5"></div></div>
                        <div id="pyl-sq-12" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-1 sm:p-1.5"></div></div>
                        <div id="pyl-sq-11" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-1 sm:p-1.5"></div></div>
                        <div id="pyl-sq-10" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-1 sm:p-1.5"></div></div>

                    </div>
                </div>

                <!-- Burned Teams Ledger -->
                <div class="p-3.5 sm:p-4 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-between font-mono text-xs">
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold">Burned Handicap Teams:</span>
                        <span id="pylBurnedLedger" class="text-white font-bold">None yet</span>
                    </div>
                    <button type="button" onclick="goToState(4)" id="btnSkipCatchup" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition cursor-pointer">
                        NEXT &rarr;
                    </button>
                </div>

            </section>

            <!-- ================================================================= -->
            <!-- STATE 4: SURVIVOR PICK SELECTION                                  -->
            <!-- ================================================================= -->
            <section id="state-4-view" class="hidden w-full max-w-4xl mx-auto my-auto space-y-4">
                
                <div class="p-5 sm:p-7 rounded-2xl bg-gradient-to-b from-[#162235] to-[#0d1624] border border-[#243247] shadow-xl">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-4">
                        <div>
                            <h2 class="text-base sm:text-xl font-black text-white">Select Your Week <?= $week ?> Survivor Pick</h2>
                            <p class="text-xs font-mono text-slate-400">Previously picked &amp; burned teams are grayed out.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" 
                                    onclick="openSurvivorRules()" 
                                    class="px-2.5 py-1 rounded-lg bg-emerald-950/80 hover:bg-emerald-900 border border-emerald-500/40 text-[11px] font-mono font-bold text-emerald-400 transition cursor-pointer flex items-center gap-1">
                                <span>🛡️</span>
                                <span>Survivor Rules</span>
                            </button>
                            <span class="px-3 py-1 rounded-lg bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 font-mono text-xs font-bold">
                                1 Team Required
                            </span>
                        </div>
                    </div>

                    <!-- Survivor Matchup Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 max-h-[60vh] overflow-y-auto p-1" id="survivorGridList">
                        <!-- Dynamically populated via JS -->
                    </div>

                    <div class="flex items-center justify-between pt-4 mt-4 border-t border-slate-800">
                        <button type="button" onclick="goToState(needsSurvivorCatchup ? 3 : 2)" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-mono font-bold transition cursor-pointer">
                            &larr; PREV
                        </button>
                        <span class="text-xs font-mono text-slate-400" id="survivorSelectionMsg">Select 1 team to lock in</span>
                        <button type="button" onclick="goToState(5)" id="btnFinishSurvivor" class="px-6 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-mono font-black transition disabled:opacity-30 disabled:pointer-events-none cursor-pointer" disabled>
                            Review &amp; Lock Picks &rarr;
                        </button>
                    </div>

                </div>
            </section>

            <!-- ================================================================= -->
            <!-- STATE 5: COMPLETION / REVIEW & CELEBRATION                        -->
            <!-- ================================================================= -->
            <div id="wizardCompletionView" class="hidden text-center max-w-xl mx-auto my-auto p-6 sm:p-10 rounded-2xl border-2 border-emerald-500/40 bg-gradient-to-b from-[#162235] to-[#0B1626] shadow-2xl animate-fade-in">
                <div class="text-5xl sm:text-6xl mb-3">🏆</div>
                <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight mb-2">
                    Week <?= $week ?> Picks Complete!
                </h2>
                <p class="text-xs sm:text-sm text-slate-300 mb-6 max-w-md mx-auto">
                    All game selections and your survivor pick are securely saved to your account. You are ready for kickoff!
                </p>

                <div class="grid grid-cols-3 gap-3 max-w-md mx-auto mb-8 text-left">
                    <div class="p-3 rounded-xl bg-[#0B1626] border border-[#243247]">
                        <span class="text-[10px] font-mono text-slate-400 uppercase block mb-1">Pick'em Slate</span>
                        <span id="wizardCompleteTotal" class="text-base sm:text-lg font-black font-mono text-emerald-400"><?= count($wizardGames) ?> / <?= count($wizardGames) ?></span>
                    </div>
                    <div class="p-3 rounded-xl bg-[#0B1626] border border-[#243247]">
                        <span class="text-[10px] font-mono text-slate-400 uppercase block mb-1">MNF Total</span>
                        <span id="wizardCompleteTb" class="text-base sm:text-lg font-black font-mono text-amber-400"><?= $tbCurrentPoints ?? '--' ?> PTS</span>
                    </div>
                    <div class="p-3 rounded-xl bg-[#0B1626] border border-[#243247]">
                        <span class="text-[10px] font-mono text-slate-400 uppercase block mb-1">Survivor</span>
                        <span id="wizardCompleteSurvivor" class="text-base sm:text-lg font-black font-mono text-emerald-400"><?= htmlspecialchars((string) ($currentSurvivorPick ?? '--')) ?></span>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                    <button type="button" 
                            id="btnWizardFinishReview"
                            onclick="exitWizardToStandardView()"
                            class="w-full sm:w-auto px-6 py-3.5 rounded-xl font-black text-xs font-mono uppercase tracking-wider bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 text-slate-950 shadow-lg shadow-emerald-500/20 transition transform hover:-translate-y-0.5 cursor-pointer">
                        View Live Standings &amp; Picks Table &rarr;
                    </button>
                    <button type="button" 
                            id="btnWizardRestart"
                            onclick="goToState(1)"
                            class="w-full sm:w-auto px-5 py-3.5 rounded-xl font-bold text-xs font-mono bg-[#162235] hover:bg-[#1e2e48] border border-[#243247] text-slate-300 hover:text-white transition cursor-pointer">
                        Modify Picks
                    </button>
                </div>
            </div>

        </div>
    </main>

    <!-- ONBOARDING & RULES MODALS -->
    <!-- 1. Pick'em Rules Modal (Displayed before user starts making picks) -->
    <div id="pickemIntroOverlay" class="hidden fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md">
        <div class="w-full max-w-lg max-h-[92vh] flex flex-col p-5 sm:p-7 rounded-2xl bg-[#0F172A] border-2 border-amber-500/60 shadow-2xl text-left overflow-hidden">
            <!-- Header -->
            <div class="flex items-center gap-3 pb-4 border-b border-slate-800 shrink-0">
                <div class="w-11 h-11 rounded-xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center text-2xl shrink-0">
                    🏈
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-black uppercase bg-amber-400 text-slate-950">
                            Official Rules
                        </span>
                        <span class="text-xs font-mono text-slate-400">Week <?= htmlspecialchars((string)$week) ?></span>
                    </div>
                    <h3 class="text-lg sm:text-xl font-black text-white tracking-tight">Weekly Pick'em Rules</h3>
                </div>
            </div>

            <!-- Rules Content (Scrollable for small screens) -->
            <div class="space-y-3 my-4 overflow-y-auto pr-1 text-xs text-slate-300 leading-relaxed">
                <div class="p-3 rounded-xl bg-slate-900/90 border border-slate-800 flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-amber-500/20 text-amber-400 font-mono font-bold flex items-center justify-center shrink-0 mt-0.5 text-xs">1</span>
                    <div>
                        <strong class="text-white block font-semibold text-xs sm:text-sm">Pick All Outright Winners</strong>
                        <p class="text-slate-400 mt-0.5">Select the team you predict will win outright for every matchup on this week's slate. Straight up—no point spreads.</p>
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-slate-900/90 border border-slate-800 flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-amber-500/20 text-amber-400 font-mono font-bold flex items-center justify-center shrink-0 mt-0.5 text-xs">2</span>
                    <div>
                        <strong class="text-white block font-semibold text-xs sm:text-sm">Individual Kickoff Deadlines</strong>
                        <p class="text-slate-400 mt-0.5">Each game locks strictly when its scheduled kickoff arrives. You can change your picks on later games right up until their kickoff.</p>
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-slate-900/90 border border-slate-800 flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-amber-500/20 text-amber-400 font-mono font-bold flex items-center justify-center shrink-0 mt-0.5 text-xs">3</span>
                    <div>
                        <strong class="text-white block font-semibold text-xs sm:text-sm">Monday Night Tiebreaker</strong>
                        <p class="text-slate-400 mt-0.5">Predict the total combined points scored in the designated Monday Night Football game to break ties for weekly payouts.</p>
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-slate-900/90 border border-slate-800 flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-amber-500/20 text-amber-400 font-mono font-bold flex items-center justify-center shrink-0 mt-0.5 text-xs">4</span>
                    <div>
                        <strong class="text-white block font-semibold text-xs sm:text-sm">Scoring &amp; Season Standings</strong>
                        <p class="text-slate-400 mt-0.5">Earn 1 point per correct pick. Weekly champions win the weekly pot, and points tally across 18 weeks toward the Championship trophy.</p>
                    </div>
                </div>
            </div>

            <!-- Footer Action Button -->
            <div class="pt-2 border-t border-slate-800 shrink-0">
                <button type="button" 
                        onclick="dismissPickemIntro()" 
                        class="w-full py-3 rounded-xl bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-950 font-black text-xs sm:text-sm font-mono uppercase tracking-wider transition shadow-lg shadow-amber-500/25 active:scale-[0.98] cursor-pointer flex items-center justify-center gap-2">
                    <span>Start Making Picks</span>
                    <span>&rarr;</span>
                </button>
            </div>
        </div>
    </div>

    <!-- 2. Survivor Pool Rules Modal (Presented before user makes their survivor pick) -->
    <div id="survivorIntroOverlay" class="hidden fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md">
        <div class="w-full max-w-lg max-h-[92vh] flex flex-col p-5 sm:p-7 rounded-2xl bg-[#071714] border-2 border-emerald-500/60 shadow-2xl text-left overflow-hidden">
            <!-- Header -->
            <div class="flex items-center gap-3 pb-4 border-b border-emerald-900/50 shrink-0">
                <div class="w-11 h-11 rounded-xl bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-2xl shrink-0">
                    🛡️
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-black uppercase bg-emerald-400 text-slate-950">
                            Official Rules
                        </span>
                        <span class="text-xs font-mono text-emerald-400/80">Week <?= htmlspecialchars((string)$week) ?></span>
                    </div>
                    <h3 class="text-lg sm:text-xl font-black text-white tracking-tight">Survivor Pool Rules</h3>
                </div>
            </div>

            <!-- Rules Content -->
            <div class="space-y-3 my-4 overflow-y-auto pr-1 text-xs text-slate-300 leading-relaxed">
                <div class="p-3 rounded-xl bg-slate-950/80 border border-emerald-900/40 flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 font-mono font-bold flex items-center justify-center shrink-0 mt-0.5 text-xs">1</span>
                    <div>
                        <strong class="text-white block font-semibold text-xs sm:text-sm">Pick 1 Outright Winner Each Week</strong>
                        <p class="text-slate-400 mt-0.5">Select exactly one NFL team that you predict will win their matchup outright in Week <?= htmlspecialchars((string)$week) ?>.</p>
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-slate-950/80 border border-emerald-900/40 flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 font-mono font-bold flex items-center justify-center shrink-0 mt-0.5 text-xs">2</span>
                    <div>
                        <strong class="text-white block font-semibold text-xs sm:text-sm">Strict "One and Done" Rule</strong>
                        <p class="text-slate-400 mt-0.5">Once you select a team, that team is <strong>burned permanently</strong>. You can never select them again for the entire remainder of the season!</p>
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-slate-950/80 border border-emerald-900/40 flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 font-mono font-bold flex items-center justify-center shrink-0 mt-0.5 text-xs">3</span>
                    <div>
                        <strong class="text-white block font-semibold text-xs sm:text-sm">Win or Go Home (Single Elimination)</strong>
                        <p class="text-slate-400 mt-0.5">If your picked team wins, you advance to the next week. If your picked team loses or ties, you are eliminated from the pool.</p>
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-slate-950/80 border border-emerald-900/40 flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 font-mono font-bold flex items-center justify-center shrink-0 mt-0.5 text-xs">4</span>
                    <div>
                        <strong class="text-white block font-semibold text-xs sm:text-sm">Lock Deadline &amp; Last Standing</strong>
                        <p class="text-slate-400 mt-0.5">Your pick locks at the kickoff time of your chosen team's game. Survive all 18 weeks—the last remaining player standing wins the jackpot!</p>
                    </div>
                </div>
            </div>

            <!-- Footer Action Button -->
            <div class="pt-2 border-t border-emerald-900/50 shrink-0">
                <button type="button" 
                        onclick="dismissSurvivorIntro()" 
                        class="w-full py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 hover:to-teal-300 text-slate-950 font-black text-xs sm:text-sm font-mono uppercase tracking-wider transition shadow-lg shadow-emerald-500/25 active:scale-[0.98] cursor-pointer flex items-center justify-center gap-2">
                    <span>Make My Survivor Pick</span>
                    <span>&rarr;</span>
                </button>
            </div>
        </div>
    </div>

    <!-- 3. Late Entrant Survivor Catch-Up Modal (Random elimination method briefing) -->
    <div id="pylCatchupIntroOverlay" class="hidden fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/90 backdrop-blur-md">
        <div class="w-full max-w-lg max-h-[92vh] flex flex-col p-5 sm:p-7 rounded-2xl bg-[#110519] border-2 border-fuchsia-500/60 shadow-2xl text-left overflow-hidden">
            <!-- Header -->
            <div class="flex items-center gap-3 pb-4 border-b border-fuchsia-900/50 shrink-0">
                <div class="w-11 h-11 rounded-xl bg-fuchsia-500/20 border border-fuchsia-500/40 flex items-center justify-center text-2xl shrink-0">
                    🎰
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-[9px] font-pyl uppercase bg-fuchsia-500 text-slate-950">
                            Press Your Luck
                        </span>
                        <span class="text-xs font-mono text-fuchsia-300">Survivor Catch-Up</span>
                    </div>
                    <h3 class="text-lg sm:text-xl font-black text-white tracking-tight">Random Team Elimination</h3>
                </div>
            </div>

            <!-- Content -->
            <div class="space-y-3 my-4 overflow-y-auto pr-1 text-xs text-slate-300 leading-relaxed">
                <div class="p-3 rounded-xl bg-slate-950/80 border border-fuchsia-900/40 flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-fuchsia-500/20 text-fuchsia-400 font-mono font-bold flex items-center justify-center shrink-0 mt-0.5 text-xs">⚖️</span>
                    <div>
                        <strong class="text-white block font-semibold text-xs sm:text-sm">Why Catch-Up Is Required</strong>
                        <p class="text-slate-400 mt-0.5">You are joining the Survivor Pool in Week <?= htmlspecialchars((string)$week) ?>. Players who entered in Week 1 have already burned <?= count($missedSurvivorWeeks) ?> team(s) that they can never pick again this season. To ensure fair competition, you must also eliminate <?= count($missedSurvivorWeeks) ?> handicap team(s).</p>
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-slate-950/80 border border-fuchsia-900/40 flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-fuchsia-500/20 text-fuchsia-400 font-mono font-bold flex items-center justify-center shrink-0 mt-0.5 text-xs">🎲</span>
                    <div>
                        <strong class="text-white block font-semibold text-xs sm:text-sm">The 100% Random Elimination Method</strong>
                        <p class="text-slate-400 mt-0.5">Instead of manually sacrificing teams or having commissioners pick for you, the league uses our retro <strong>Press Your Luck</strong> arcade board to randomly decide which teams get eliminated!</p>
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-slate-950/80 border border-fuchsia-900/40 flex items-start gap-3">
                    <span class="w-6 h-6 rounded-full bg-fuchsia-500/20 text-fuchsia-400 font-mono font-bold flex items-center justify-center shrink-0 mt-0.5 text-xs">🕹️</span>
                    <div>
                        <strong class="text-white block font-semibold text-xs sm:text-sm">How to Play &amp; Eliminate</strong>
                        <p class="text-slate-400 mt-0.5">Press the green <strong>"Start!"</strong> button to start the randomized board spinner. When you're ready, hit <strong>"Stop!"</strong>. Whichever team is illuminated is <strong>burned</strong> with the classic Whammy animation. Repeat until all <?= count($missedSurvivorWeeks) ?> missed week(s) are cleared!</p>
                    </div>
                </div>
            </div>

            <!-- Footer Action Button -->
            <div class="pt-2 border-t border-fuchsia-900/50 shrink-0">
                <button type="button" 
                        onclick="dismissPylIntro()" 
                        class="w-full py-3 rounded-xl bg-gradient-to-r from-fuchsia-500 to-pink-500 hover:from-fuchsia-400 hover:to-pink-400 text-white font-black text-xs sm:text-sm font-mono uppercase tracking-wider transition shadow-lg shadow-fuchsia-500/25 active:scale-[0.98] cursor-pointer flex items-center justify-center gap-2">
                    <span>Got It &bull; Let's Spin!</span>
                    <span>&rarr;</span>
                </button>
            </div>
        </div>
    </div>

    <!-- 4. General Help & Rules Modal (Invoked manually via 'Rules' button) -->
    <div id="wizardHelpModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-sm">
        <div class="w-full max-w-lg max-h-[92vh] flex flex-col p-5 sm:p-6 rounded-2xl bg-[#162235] border border-[#243247] text-left shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between pb-3 border-b border-[#243247] shrink-0">
                <h3 class="font-bold text-base text-white flex items-center gap-2">
                    <span>📖 Pool Rules &amp; Guidelines</span>
                </h3>
                <button type="button" onclick="closeHelpModal()" class="text-slate-400 hover:text-white text-xl leading-none">&times;</button>
            </div>
            <div class="space-y-3.5 my-4 overflow-y-auto pr-1 text-xs text-slate-300 leading-relaxed">
                <div class="p-3 rounded-xl bg-slate-900 border border-slate-800">
                    <h4 class="font-bold text-amber-400 mb-1 flex items-center gap-1.5">
                        <span>🏈</span> <span>Weekly Pick'em Rules</span>
                    </h4>
                    <p class="text-slate-400">Pick every game winner on the slate outright (no spread). Games lock individually at scheduled kickoff times. 1 point is awarded per correct pick toward weekly pots and the season title.</p>
                </div>
                <div class="p-3 rounded-xl bg-slate-900 border border-slate-800">
                    <h4 class="font-bold text-amber-400 mb-1 flex items-center gap-1.5">
                        <span>🎯</span> <span>Monday Night Tiebreaker</span>
                    </h4>
                    <p class="text-slate-400">Enter your prediction for total combined points scored in Monday Night Football. The player closest to the actual score wins the tiebreaker.</p>
                </div>
                <div class="p-3 rounded-xl bg-slate-900 border border-slate-800">
                    <h4 class="font-bold text-emerald-400 mb-1 flex items-center gap-1.5">
                        <span>🛡️</span> <span>Survivor Pool Rules</span>
                    </h4>
                    <p class="text-slate-400">Pick exactly one team to win outright each week. Once you pick a team, they are burned for the rest of the season. A loss or tie eliminates you. Surviving all 18 weeks wins the prize.</p>
                </div>
                <div class="p-3 rounded-xl bg-slate-900 border border-slate-800">
                    <h4 class="font-bold text-fuchsia-400 mb-1 flex items-center gap-1.5">
                        <span>🎰</span> <span>Late Entrant Random Elimination</span>
                    </h4>
                    <p class="text-slate-400">Late arrivals must burn one handicap team per missed week to ensure fair play against Week 1 players. Teams are chosen randomly via the retro Press Your Luck board.</p>
                </div>
            </div>
            <div class="pt-2 border-t border-[#243247] shrink-0">
                <button type="button" onclick="closeHelpModal()" class="w-full py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-mono text-xs font-bold transition cursor-pointer">
                    Close Rules
                </button>
            </div>
        </div>
    </div>

    <!-- SURVIVOR CONFIRMATION MODAL -->
    <div id="survivorConfirmModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md">
        <div class="w-full max-w-sm p-6 rounded-2xl bg-slate-900 border-2 border-emerald-500 text-center shadow-2xl">
            <div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-center p-2">
                <img id="confirmModalLogo" src="" class="w-full h-full object-contain">
            </div>
            <h3 class="text-xl font-black text-white" id="confirmModalTeamName">Confirm Pick</h3>
            <p class="text-xs font-mono text-slate-400 mt-2 mb-6">
                Lock in <span class="text-emerald-400 font-bold" id="confirmModalTeamNick"></span> as your Week <?= $week ?> Survivor pick? You cannot pick this team again for the rest of the season!
            </p>
            <div class="flex items-center gap-3">
                <button type="button" onclick="closeSurvivorConfirmModal()" class="w-1/2 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-mono font-bold transition">
                    Cancel
                </button>
                <button type="button" onclick="commitSurvivorPick()" class="w-1/2 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-mono font-black uppercase transition shadow">
                    Yes, Lock In!
                </button>
            </div>
        </div>
    </div>

    <!-- KEYBOARD SHORTCUTS MODAL -->
    <div id="wizardShortcutsModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div class="w-full max-w-md p-6 rounded-2xl border border-[#243247] bg-[#162235] text-slate-100 shadow-2xl">
            <div class="flex items-center justify-between pb-3 border-b border-[#243247] mb-4">
                <h3 class="font-bold text-base text-white flex items-center gap-2">
                    <span>⌨️ Keyboard Shortcuts</span>
                </h3>
                <button type="button" id="btnCloseShortcutsModal" class="text-slate-400 hover:text-white text-lg">&times;</button>
            </div>
            <div class="space-y-3 text-xs">
                <div class="flex items-center justify-between py-1 border-b border-[#243247]/50">
                    <span class="text-slate-300">Select Away Team</span>
                    <span class="font-mono px-2 py-0.5 rounded bg-[#0B1626] border border-[#243247] text-amber-400 font-bold">1 or A or ↑</span>
                </div>
                <div class="flex items-center justify-between py-1 border-b border-[#243247]/50">
                    <span class="text-slate-300">Select Home Team</span>
                    <span class="font-mono px-2 py-0.5 rounded bg-[#0B1626] border border-[#243247] text-amber-400 font-bold">2 or H or ↓</span>
                </div>
                <div class="flex items-center justify-between py-1 border-b border-[#243247]/50">
                    <span class="text-slate-300">Next Game / Skip</span>
                    <span class="font-mono px-2 py-0.5 rounded bg-[#0B1626] border border-[#243247] text-slate-300 font-bold">&rarr; or Space or D</span>
                </div>
                <div class="flex items-center justify-between py-1 border-b border-[#243247]/50">
                    <span class="text-slate-300">Previous Game</span>
                    <span class="font-mono px-2 py-0.5 rounded bg-[#0B1626] border border-[#243247] text-slate-300 font-bold">&larr; or W</span>
                </div>
                <div class="flex items-center justify-between py-1">
                    <span class="text-slate-300">Exit Wizard</span>
                    <span class="font-mono px-2 py-0.5 rounded bg-[#0B1626] border border-[#243247] text-slate-400 font-bold">Escape</span>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ================================================================= -->
<!-- WIZARD INTERACTIVE ENGINE & SFX SYNTHESIZER                       -->
<!-- ================================================================= -->
<script>
// Expose core audio & sync functions globally for testing and interoperability
window.playPickSound = function() {};
window.playAdvanceSound = function() {};
window.playCelebrationSound = function() {};
window.syncWithStandardGrid = function() {};

window.addEventListener('DOMContentLoaded', () => {
    // -----------------------------------------------------------------
    // 1. Data Bootstrap
    // -----------------------------------------------------------------
    const wizardGames = <?= json_encode($wizardGames, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const allPylTeams = <?= json_encode($pylTeamsList, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const seasonYear = <?= (int) $season ?>;
    const weekNumber = <?= (int) $week ?>;
    const tbGameId = <?= $tbGameId ? (int) $tbGameId : 'null' ?>;
    const tbIsLocked = <?= $tbIsLocked ? 'true' : 'false' ?>;
    let mnfPredictedPoints = <?= $tbCurrentPoints !== null ? (int) $tbCurrentPoints : 'null' ?>;

    // Survivor State
    let usedSurvivorTeams = <?= json_encode($usedSurvivorTeams) ?>;
    let missedSurvivorWeeks = <?= json_encode($missedSurvivorWeeks) ?>;
    let needsSurvivorCatchup = <?= $needsSurvivorCatchup ? 'true' : 'false' ?>;
    let activeSurvivorPick = <?= json_encode($currentSurvivorPick) ?>;
    let pendingSurvivorSelection = null;
    const isSurvivorEliminated = <?= $isSurvivorEliminated ? 'true' : 'false' ?>;

    // Rules presentation & acknowledgment states (Ensures rules are displayed before picking in each session)
    let hasAcknowledgedPickemRules = (sessionStorage.getItem('wally_pickem_rules_ack') === 'true');
    let hasAcknowledgedSurvivorRules = (sessionStorage.getItem('wally_survivor_rules_ack') === 'true');
    let hasAcknowledgedPylRules = (sessionStorage.getItem('wally_pyl_rules_ack') === 'true');

    let currentState = 1;
    let currentIndex = 0;
    let isTransitioning = false;

    // -----------------------------------------------------------------
    // 2. Audio Engine (NFL Theme & PYL Soundboard)
    // -----------------------------------------------------------------
    const audioNfl = document.getElementById('audioNflTheme');
    const audioPyl = document.getElementById('audioPylSoundboard');
    let isNflAudioPlaying = false;
    let isUserMuted = (localStorage.getItem('wally_nfl_muted') === 'true');

    if (audioNfl) audioNfl.volume = 0.30;
    if (audioPyl) audioPyl.volume = 0.40;

    // Apply persistent mute preference on launch
    function updateAudioIconState() {
        const icon = document.getElementById('wizardAudioIcon');
        if (icon) {
            icon.textContent = isUserMuted ? '🔇' : '🔊';
        }
        animateEqualizer(!isUserMuted && isNflAudioPlaying);
    }
    updateAudioIconState();

    function startNflThemeLoop() {
        if (!audioNfl || isUserMuted || isNflAudioPlaying) return;
        audioNfl.play().then(() => {
            isNflAudioPlaying = true;
            updateAudioIconState();
        }).catch(e => {
            // Autoplay waiting for user gesture
        });
    }

    window.toggleAudioPlayback = function() {
        if (!audioNfl) return;
        isUserMuted = !isUserMuted;
        localStorage.setItem('wally_nfl_muted', isUserMuted ? 'true' : 'false');
        
        if (isUserMuted) {
            audioNfl.pause();
            isNflAudioPlaying = false;
        } else {
            audioNfl.play().then(() => {
                isNflAudioPlaying = true;
            }).catch(e => console.warn(e));
        }
        updateAudioIconState();
    };

    function animateEqualizer(active) {
        ['eq1', 'eq2', 'eq3'].forEach((id, idx) => {
            const el = document.getElementById(id);
            if (!el) return;
            if (active) el.className = `w-0.5 bg-amber-400 rounded-full eq-b${idx + 1}`;
            else {
                el.className = 'w-0.5 bg-amber-400 rounded-full';
                el.style.height = '4px';
            }
        });
    }

    const fadeAudioTimers = {};
    function fadeAudio(audio, targetVolume, durationMs = 500, callback) {
        if (!audio) return;
        if (fadeAudioTimers[audio.id]) {
            cancelAnimationFrame(fadeAudioTimers[audio.id]);
        }
        const startVolume = audio.volume;
        const startTime = performance.now();
        function tick(now) {
            const elapsed = now - startTime;
            const progress = Math.min(1, elapsed / durationMs);
            audio.volume = Math.max(0, Math.min(1, startVolume + (targetVolume - startVolume) * progress));
            if (progress < 1) {
                fadeAudioTimers[audio.id] = requestAnimationFrame(tick);
            } else {
                delete fadeAudioTimers[audio.id];
                if (callback) callback();
            }
        }
        fadeAudioTimers[audio.id] = requestAnimationFrame(tick);
    }

    // Web Audio Synthesizer fallback for crisp UI clicks
    let audioCtx = null;
    function getAudioContext() {
        if (!audioCtx) {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) audioCtx = new AudioContext();
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        return audioCtx;
    }

    window.playPickSound = function() {
        try {
            const ctx = getAudioContext();
            if (!ctx) return;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(440, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.1);
            gain.gain.setValueAtTime(0.12, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.1);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.1);
        } catch(e) {}
    };

    window.playAdvanceSound = function() {
        try {
            const ctx = getAudioContext();
            if (!ctx) return;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(523.25, ctx.currentTime);
            osc.frequency.setValueAtTime(659.25, ctx.currentTime + 0.05);
            gain.gain.setValueAtTime(0.1, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.12);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.12);
        } catch(e) {}
    };

    window.playCelebrationSound = function() {
        try {
            const ctx = getAudioContext();
            if (!ctx) return;
            const notes = [523.25, 659.25, 783.99, 1046.50];
            notes.forEach((freq, idx) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(freq, ctx.currentTime + idx * 0.08);
                gain.gain.setValueAtTime(0.12, ctx.currentTime + idx * 0.08);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + idx * 0.08 + 0.3);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(ctx.currentTime + idx * 0.08);
                osc.stop(ctx.currentTime + idx * 0.08 + 0.3);
            });
        } catch(e) {}
    };

    // -----------------------------------------------------------------
    // 3. Navigation & 5-State Machine Engine
    // -----------------------------------------------------------------
    window.goToState = function(step) {
        currentState = step;
        for (let s = 1; s <= 5; s++) {
            const view = (s === 5) ? document.getElementById('wizardCompletionView') : document.getElementById(`state-${s}-view`);
            const pill = document.getElementById(`step-pill-${s}`);
            if (!view || !pill) continue;
            if (s === step) {
                view.classList.remove('hidden');
                pill.className = 'p-1 rounded cursor-pointer bg-amber-500 text-slate-950 font-black shadow truncate';
            } else {
                view.classList.add('hidden');
                pill.className = 'p-1 rounded cursor-pointer bg-slate-900 border border-slate-800 text-slate-400 truncate';
            }
        }

        if (step === 1) {
            renderCurrentMatchup();
            // Display Pick'em rules overlay before user begins making picks
            if (!hasAcknowledgedPickemRules) {
                document.getElementById('pickemIntroOverlay').classList.remove('hidden');
            } else {
                startNflThemeLoop();
            }
        } else if (step === 3) {
            setupPylCatchupView();
            // Display late entrant Press Your Luck random elimination briefing before spinning
            if (!hasAcknowledgedPylRules && missedSurvivorWeeks.length > 0) {
                document.getElementById('pylCatchupIntroOverlay').classList.remove('hidden');
            }
        } else if (step === 4) {
            renderSurvivorGrid();
            // Display Survivor pool rules before user makes their survivor pick
            if (!hasAcknowledgedSurvivorRules) {
                document.getElementById('survivorIntroOverlay').classList.remove('hidden');
            }
        } else if (step === 5) {
            playCelebrationSound();
            renderCompletionSummary();
        }
    };

    // Onboarding & Rules Modal Handlers
    window.dismissPickemIntro = function() {
        document.getElementById('pickemIntroOverlay').classList.add('hidden');
        hasAcknowledgedPickemRules = true;
        sessionStorage.setItem('wally_pickem_rules_ack', 'true');
        startNflThemeLoop();
    };

    window.openPickemRules = function() {
        document.getElementById('pickemIntroOverlay').classList.remove('hidden');
    };

    window.dismissSurvivorIntro = function() {
        document.getElementById('survivorIntroOverlay').classList.add('hidden');
        hasAcknowledgedSurvivorRules = true;
        sessionStorage.setItem('wally_survivor_rules_ack', 'true');
    };

    window.openSurvivorRules = function() {
        document.getElementById('survivorIntroOverlay').classList.remove('hidden');
    };

    window.dismissPylIntro = function() {
        document.getElementById('pylCatchupIntroOverlay').classList.add('hidden');
        hasAcknowledgedPylRules = true;
        sessionStorage.setItem('wally_pyl_rules_ack', 'true');
    };

    window.openPylIntro = function() {
        document.getElementById('pylCatchupIntroOverlay').classList.remove('hidden');
    };

    window.openHelpModal = function() {
        document.getElementById('wizardHelpModal').classList.remove('hidden');
    };

    window.closeHelpModal = function() {
        document.getElementById('wizardHelpModal').classList.add('hidden');
    };

    window.openContextualRules = function() {
        if (currentState === 1 || currentState === 2) {
            openPickemRules();
        } else if (currentState === 3) {
            openPylIntro();
        } else if (currentState === 4) {
            openSurvivorRules();
        } else {
            openHelpModal();
        }
    };

    // -----------------------------------------------------------------
    // 4. State 1: Matchup Carousel Rendering & Autosave
    // -----------------------------------------------------------------
    const modal = document.getElementById('pickWizardModal');
    const btnWizardExit = document.getElementById('btnWizardExit');
    const btnLaunchWizard = document.getElementById('btnLaunchWizard');
    const btnLaunchWizardHero = document.getElementById('btnLaunchWizardHero');
    const btnWizardPrev = document.getElementById('btnWizardPrev');
    const btnWizardNext = document.getElementById('btnWizardNext');
    const wizardTimeline = document.getElementById('wizardTimeline');
    const wizardProgressBar = document.getElementById('wizardProgressBar');

    const wizardAwayCard = document.getElementById('wizardAwayCard');
    const wizardHomeCard = document.getElementById('wizardHomeCard');
    const wizardAwayLogo = document.getElementById('wizardAwayLogo');
    const wizardHomeLogo = document.getElementById('wizardHomeLogo');
    const wizardAwayCheck = document.getElementById('wizardAwayCheck');
    const wizardHomeCheck = document.getElementById('wizardHomeCheck');
    const wizardAwaySelectionRing = document.getElementById('wizardAwaySelectionRing');
    const wizardHomeSelectionRing = document.getElementById('wizardHomeSelectionRing');
    const wizardKickoffText = document.getElementById('wizardKickoffText');
    const wizardKickoffTextMobile = document.getElementById('wizardKickoffTextMobile');
    const wizardLockNotice = document.getElementById('wizardLockNotice');

    function renderCurrentMatchup() {
        if (!wizardGames.length) return;
        const game = wizardGames[currentIndex];

        // Away Panel: domintated by Away team primary color & large bold logo
        wizardAwayCard.style.backgroundColor = game.away_color;
        wizardAwayLogo.src = game.away_logo;
        wizardAwayLogo.alt = game.away_name;

        // Home Panel: dominated by Home team primary color & large bold logo
        wizardHomeCard.style.backgroundColor = game.home_color;
        wizardHomeLogo.src = game.home_logo;
        wizardHomeLogo.alt = game.home_name;

        // Kickoff Date & Time header
        if (wizardKickoffText) wizardKickoffText.textContent = game.kickoff_formatted;
        if (wizardKickoffTextMobile) wizardKickoffTextMobile.textContent = game.kickoff_short;

        // Lock Notice
        if (game.is_locked) {
            wizardLockNotice.classList.remove('hidden');
        } else {
            wizardLockNotice.classList.add('hidden');
        }

        // Selection styling
        updateCardSelectionState(game.user_pick);

        // Navigation state
        btnWizardPrev.disabled = (currentIndex === 0);

        // Progress indicators
        updatePickCounters();
        renderTimeline();
    }

    function updateCardSelectionState(userPick) {
        const game = wizardGames[currentIndex];

        // Reset checkmarks & selection rings
        wizardAwayCheck.classList.add('scale-0', 'opacity-0');
        wizardHomeCheck.classList.add('scale-0', 'opacity-0');
        if (wizardAwaySelectionRing) wizardAwaySelectionRing.classList.add('opacity-0');
        if (wizardHomeSelectionRing) wizardHomeSelectionRing.classList.add('opacity-0');

        wizardAwayCard.classList.remove('opacity-40');
        wizardHomeCard.classList.remove('opacity-40');

        if (userPick === game.away_team) {
            wizardAwayCheck.classList.remove('scale-0', 'opacity-0');
            if (wizardAwaySelectionRing) wizardAwaySelectionRing.classList.remove('opacity-0');
            wizardHomeCard.classList.add('opacity-40');
        } else if (userPick === game.home_team) {
            wizardHomeCheck.classList.remove('scale-0', 'opacity-0');
            if (wizardHomeSelectionRing) wizardHomeSelectionRing.classList.remove('opacity-0');
            wizardAwayCard.classList.add('opacity-40');
        }
    }

    function updatePickCounters() {
        const pickedCount = wizardGames.filter(g => g.user_pick !== null).length;
        const pct = (pickedCount / wizardGames.length) * 100;
        wizardProgressBar.style.width = `${pct}%`;
    }

    function renderTimeline() {
        wizardTimeline.innerHTML = '';
        wizardGames.forEach((g, idx) => {
            const bubble = document.createElement('button');
            bubble.type = 'button';
            bubble.title = `Game ${idx + 1}`;
            const isCurrent = (idx === currentIndex);
            const isPicked = (g.user_pick !== null);

            bubble.className = `w-7 h-7 sm:w-8 sm:h-8 rounded-full font-mono text-[10px] sm:text-xs font-bold transition-all flex items-center justify-center cursor-pointer ${
                isCurrent 
                    ? 'ring-2 ring-amber-400 bg-amber-400 text-slate-950 font-black scale-110 shadow-lg z-10' 
                    : (isPicked 
                        ? 'bg-emerald-500/90 hover:bg-emerald-400 text-slate-950 font-black border border-emerald-400' 
                        : 'bg-slate-800 hover:bg-slate-700 text-slate-400 border border-slate-700')
            }`;
            bubble.innerHTML = isPicked && !isCurrent ? `${idx + 1}✓` : `${idx + 1}`;
            bubble.onclick = () => {
                currentIndex = idx;
                renderCurrentMatchup();
            };
            wizardTimeline.appendChild(bubble);
        });
    }

    function makePick(team) {
        startNflThemeLoop();
        const game = wizardGames[currentIndex];
        if (game.is_locked) return;

        playPickSound();
        game.user_pick = team;
        updateCardSelectionState(team);
        updatePickCounters();
        renderTimeline();

        // Autosave quietly to server in background (implicit save)
        fetch('/pickem/autosave', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                season_year: seasonYear,
                week_number: weekNumber,
                game_id: game.id,
                selected_team: team,
            })
        }).catch(e => console.warn('Autosave error:', e));

        window.syncWithStandardGrid(game.id, team);

        // Smooth immediate transition to next game
        setTimeout(() => {
            if (currentIndex < wizardGames.length - 1) {
                currentIndex++;
                playAdvanceSound();
                renderCurrentMatchup();
            } else {
                // Last game picked -> advance to Tiebreaker
                goToState(2);
            }
        }, 220);
    }

    // Tapping top half / left panel selects Away
    wizardAwayCard.onclick = () => {
        const game = wizardGames[currentIndex];
        makePick(game.away_team);
    };

    // Tapping bottom half / right panel selects Home
    wizardHomeCard.onclick = () => {
        const game = wizardGames[currentIndex];
        makePick(game.home_team);
    };

    btnWizardPrev.onclick = () => {
        if (currentIndex > 0) {
            currentIndex--;
            playAdvanceSound();
            renderCurrentMatchup();
        }
    };

    btnWizardNext.onclick = () => {
        if (currentIndex < wizardGames.length - 1) {
            currentIndex++;
            playAdvanceSound();
            renderCurrentMatchup();
        } else {
            goToState(2);
        }
    };

    // -----------------------------------------------------------------
    // 5. State 2: Tiebreaker Input Logic & Dynamic Score Generator
    // -----------------------------------------------------------------
    const tbInput = document.getElementById('wizardTiebreakerInput');
    let isTbSpinning = false;
    let tbSpinInterval = null;

    window.adjustTiebreaker = function(delta) {
        let val = parseInt(tbInput.value, 10) || 47;
        val = Math.max(10, Math.min(120, val + delta));
        tbInput.value = val;
        saveTiebreaker(val);
    };

    window.setTiebreakerVal = function(v) {
        tbInput.value = v;
        saveTiebreaker(v);
    };

    window.handleTiebreakerSpin = function() {
        const btn = document.getElementById('btnSpinTb');
        const label = document.getElementById('tbSpinBtnLabel');

        if (!isTbSpinning) {
            // Start spinning
            isTbSpinning = true;
            if (label) label.textContent = 'STOP / Settle Score';
            if (btn) btn.className = 'px-4 py-2 rounded-xl bg-red-500/30 hover:bg-red-500/40 text-red-300 border border-red-500/50 text-xs font-mono font-bold transition flex items-center gap-1.5 cursor-pointer animate-pulse';

            tbSpinInterval = setInterval(() => {
                tbInput.value = Math.floor(Math.random() * (54 - 34 + 1)) + 34;
            }, 60);
        } else {
            // Stop spinning and settle
            clearInterval(tbSpinInterval);
            isTbSpinning = false;
            const finalScore = Math.floor(Math.random() * (54 - 34 + 1)) + 34;
            setTiebreakerVal(finalScore);
            if (label) label.textContent = 'Spin Random Total (34–54)';
            if (btn) btn.className = 'px-4 py-2 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 text-xs font-mono font-bold transition flex items-center gap-1.5 cursor-pointer';
        }
    };

    function saveTiebreaker(points) {
        mnfPredictedPoints = points;
        fetch('/pickem/autosave', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                season_year: seasonYear,
                week_number: weekNumber,
                mnf_total_points: points,
            })
        }).catch(e => console.warn('Tiebreaker save error:', e));

        const gridTb = document.getElementById('mnf_total_points');
        if (gridTb) gridTb.value = points;
    }

    tbInput.addEventListener('change', () => {
        saveTiebreaker(parseInt(tbInput.value, 10) || 47);
    });

    window.handleTiebreakerNext = function() {
        saveTiebreaker(parseInt(tbInput.value, 10) || 47);
        if (needsSurvivorCatchup && missedSurvivorWeeks.length > 0) {
            goToState(3); // Press Your Luck Board
        } else {
            goToState(4); // Survivor Pick
        }
    };

    // -----------------------------------------------------------------
    // 6. State 3: Press Your Luck 18-Square Board (Larson Patterns)
    // -----------------------------------------------------------------
    const LARSON_PATTERNS = [
        [1, 15, 3, 8, 14, 6, 11, 17, 4, 12, 18, 5, 9, 13, 2, 10, 16, 7], // Pattern 1
        [4, 18, 2, 13, 7, 16, 10, 1, 9, 14, 6, 11, 17, 3, 12, 5, 8, 15], // Pattern 2
        [12, 5, 17, 3, 11, 6, 14, 9, 1, 10, 16, 7, 13, 2, 18, 4, 8, 15], // Pattern 3
        [8, 15, 4, 18, 2, 13, 7, 16, 10, 1, 9, 14, 6, 11, 17, 3, 12, 5], // Pattern 4
        [3, 17, 11, 6, 14, 9, 1, 10, 16, 7, 13, 2, 18, 4, 12, 5, 8, 15]  // Pattern 5
    ];

    // Build square slides: Team logos only (centered, no text)
    const squareSlides = {};
    for (let i = 1; i <= 18; i++) {
        squareSlides[i] = [
            { type: 'team', team: allPylTeams[(i * 2) % allPylTeams.length] },
            { type: 'team', team: allPylTeams[(i * 3 + 1) % allPylTeams.length] },
            { type: 'team', team: allPylTeams[(i * 5 + 2) % allPylTeams.length] }
        ];
    }

    let activeSlideIdx = 0;
    let isPylSpinning = false;
    let pylSpinInterval = null;
    let currentLarsonPattern = LARSON_PATTERNS[0];
    let larsonStep = 0;

    function renderPylSquare(sqNum, slide) {
        const sq = document.getElementById('pyl-sq-' + sqNum);
        if (!sq) return;
        const content = sq.querySelector('.slide-content');
        if (!content) return;

        // Big bold centered NFL team logo filling the game square
        content.innerHTML = `
            <img src="${slide.team.logo}" alt="${slide.team.name}" class="w-full h-full max-h-[88%] max-w-[88%] object-contain drop-shadow-md select-none pointer-events-none">
        `;
    }

    function cyclePylSpaces() {
        activeSlideIdx = (activeSlideIdx + 1) % 3;
        for (let i = 1; i <= 18; i++) {
            const sq = document.getElementById('pyl-sq-' + i);
            if (!sq) continue;
            const content = sq.querySelector('.slide-content');
            if (content) {
                content.classList.add('slide-switching');
                setTimeout(() => {
                    renderPylSquare(i, squareSlides[i][activeSlideIdx]);
                    content.classList.remove('slide-switching');
                }, 100);
            }
        }
    }

    // Initial render of PYL squares
    for (let i = 1; i <= 18; i++) {
        renderPylSquare(i, squareSlides[i][0]);
    }
    setInterval(() => {
        if (!isPylSpinning) cyclePylSpaces();
    }, 3500);

    function setupPylCatchupView() {
        const titleEl = document.getElementById('pylCatchupTitle');
        const countEl = document.getElementById('pylHandicapCounter');
        const ledgerEl = document.getElementById('pylBurnedLedger');
        const buzzerBtn = document.getElementById('btnPylBuzzer');
        const buzzerLabel = document.getElementById('pylBuzzerLabel');
        
        const missedCount = missedSurvivorWeeks.length;
        if (titleEl) titleEl.textContent = `Catch-Up: ${missedCount} Missed Week${missedCount > 1 ? 's' : ''} to Burn`;
        if (countEl) countEl.textContent = `0 of ${missedCount} Burned`;
        if (ledgerEl) ledgerEl.textContent = usedSurvivorTeams.length ? usedSurvivorTeams.join(', ') : 'None yet';

        if (buzzerBtn) {
            buzzerBtn.disabled = false;
            buzzerBtn.classList.remove('is-spinning');
        }
        if (buzzerLabel) buzzerLabel.textContent = 'Start!';
    }

    window.handlePylBuzzer = function() {
        const buzzerBtn = document.getElementById('btnPylBuzzer');
        const buzzerLabel = document.getElementById('pylBuzzerLabel');

        if (!isPylSpinning) {
            // User pressed START!
            isPylSpinning = true;
            if (buzzerLabel) buzzerLabel.textContent = 'Stop!';
            if (buzzerBtn) buzzerBtn.classList.add('is-spinning');

            // 1. Fade NFL theme music into background
            if (audioNfl && isNflAudioPlaying && !isUserMuted) {
                fadeAudio(audioNfl, 0.04, 600);
            }

            // 2. Play Press Your Luck soundboard music
            if (audioPyl && !isUserMuted) {
                audioPyl.currentTime = 0;
                audioPyl.volume = 0.45;
                audioPyl.play().catch(e => console.warn(e));
            }

            const pIdx = Math.floor(Math.random() * LARSON_PATTERNS.length);
            currentLarsonPattern = LARSON_PATTERNS[pIdx];
            larsonStep = 0;

            pylSpinInterval = setInterval(() => {
                larsonStep = (larsonStep + 1) % currentLarsonPattern.length;
                const sqNum = currentLarsonPattern[larsonStep];
                document.querySelectorAll('.pyl-square').forEach(sq => sq.classList.remove('is-lit'));
                const litSq = document.getElementById('pyl-sq-' + sqNum);
                if (litSq) litSq.classList.add('is-lit');
            }, 110);

        } else {
            // User pressed STOP!
            clearInterval(pylSpinInterval);
            isPylSpinning = false;
            if (buzzerLabel) buzzerLabel.textContent = 'Stop!';
            if (buzzerBtn) {
                buzzerBtn.classList.remove('is-spinning');
                buzzerBtn.disabled = true;
            }

            // 1. Stop Press Your Luck audio immediately
            if (audioPyl) {
                audioPyl.pause();
                audioPyl.currentTime = 0;
            }

            // 2. Fade NFL theme music back in
            if (audioNfl && isNflAudioPlaying && !isUserMuted) {
                fadeAudio(audioNfl, 0.30, 800);
            }

            const landedSqNum = currentLarsonPattern[larsonStep];
            const landedSq = document.getElementById('pyl-sq-' + landedSqNum);

            // 3-flash freeze animation
            if (landedSq) {
                landedSq.classList.add('flash-freeze');
                setTimeout(() => landedSq.classList.remove('flash-freeze'), 800);
            }

            const landedSlide = squareSlides[landedSqNum][activeSlideIdx];
            processPylLanding(landedSlide);
        }
    };

    function processPylLanding(slide) {
        // Every square is an authentic team square
        const team = slide.team;

        // Play Chroma-Keyed Transparent Whammy Canvas Overlay
        playWhammyChromaKeyOverlay(() => {
            commitBurnedTeam(team.abbr);
        });
    }

    function playWhammyChromaKeyOverlay(onComplete) {
        const canvas = document.getElementById('whammyCanvas');
        const video = document.getElementById('whammyVideoPlayer');
        if (!canvas || !video) {
            if (onComplete) onComplete();
            return;
        }

        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        canvas.classList.remove('hidden');

        const whammyVideos = [
            '/media/transparent/1-running-mallet.webm',
            '/media/transparent/49-football.webm'
        ];
        video.src = whammyVideos[Math.floor(Math.random() * whammyVideos.length)];

        video.onloadeddata = () => {
            video.play().catch(e => console.warn(e));
            renderWhammyFrames();
        };

        function renderWhammyFrames() {
            if (video.paused || video.ended) {
                canvas.classList.add('hidden');
                if (onComplete) onComplete();
                return;
            }
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            requestAnimationFrame(renderWhammyFrames);
        }

        video.onended = () => {
            canvas.classList.add('hidden');
            if (onComplete) onComplete();
        };

        setTimeout(() => {
            if (!video.ended) {
                canvas.classList.add('hidden');
                if (onComplete) onComplete();
            }
        }, 5000);
    }

    function commitBurnedTeam(teamAbbr) {
        if (!usedSurvivorTeams.includes(teamAbbr)) {
            usedSurvivorTeams.push(teamAbbr);
        }

        const targetWeek = missedSurvivorWeeks.shift() || 1;

        // Persist handicap burn to server
        fetch('/survivor/burn-handicap', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                season_year: seasonYear,
                week_number: targetWeek,
                eliminated_team: teamAbbr,
            })
        }).then(r => r.json()).then(data => {
            if (data.remaining_missed_weeks) {
                missedSurvivorWeeks = data.remaining_missed_weeks;
            }
            const remaining = missedSurvivorWeeks.length;
            const ledgerEl = document.getElementById('pylBurnedLedger');
            const countEl = document.getElementById('pylHandicapCounter');
            if (ledgerEl) ledgerEl.textContent = usedSurvivorTeams.join(', ');
            if (countEl) countEl.textContent = `${usedSurvivorTeams.length} Burned`;

            const buzzerBtn = document.getElementById('btnPylBuzzer');
            const buzzerLabel = document.getElementById('pylBuzzerLabel');

            if (remaining > 0) {
                if (buzzerBtn) buzzerBtn.disabled = false;
                if (buzzerLabel) buzzerLabel.textContent = 'Start!';
            } else {
                needsSurvivorCatchup = false;
                if (buzzerLabel) buzzerLabel.textContent = 'Done!';
                setTimeout(() => goToState(4), 1600);
            }
        }).catch(e => {
            console.warn(e);
            setTimeout(() => goToState(4), 1200);
        });
    }

    // -----------------------------------------------------------------
    // 7. State 4: Survivor Matchup Grid (Burned Teams Disabled)
    // -----------------------------------------------------------------
    function renderSurvivorGrid() {
        const container = document.getElementById('survivorGridList');
        if (!container) return;
        container.innerHTML = '';

        wizardGames.forEach(g => {
            [
                { abbr: g.away_team, name: g.away_name, nick: g.away_nick, logo: g.away_logo },
                { abbr: g.home_team, name: g.home_name, nick: g.home_nick, logo: g.home_logo }
            ].forEach(team => {
                const isBurned = usedSurvivorTeams.includes(team.abbr) || usedSurvivorTeams.includes(team.name);
                const isPicked = (activeSurvivorPick === team.abbr || activeSurvivorPick === team.name);

                const card = document.createElement('div');
                card.className = `p-3 rounded-xl border transition-all relative overflow-hidden select-none flex items-center justify-between ${
                    isBurned 
                        ? 'opacity-25 grayscale bg-slate-950 border-slate-800 pointer-events-none' 
                        : (isPicked 
                            ? 'border-emerald-400 bg-emerald-950/40 shadow-[0_0_20px_rgba(16,185,129,0.3)] cursor-pointer' 
                            : 'border-slate-800 bg-slate-900/90 hover:border-emerald-500/50 cursor-pointer')
                }`;

                card.innerHTML = `
                    <div class="flex items-center gap-3">
                        <img src="${team.logo}" class="w-9 h-9 object-contain">
                        <div>
                            <span class="text-sm font-bold text-white block">${team.nick}</span>
                            <span class="text-[10px] font-mono text-slate-400">${isBurned ? 'BURNED / UNAVAILABLE' : (isPicked ? 'SELECTED PICK' : 'ELIGIBLE')}</span>
                        </div>
                    </div>
                    ${isBurned ? '<span class="text-xs">🔒</span>' : (isPicked ? '<span class="text-emerald-400 text-lg">✓</span>' : '<span class="text-slate-600 text-xs">&rarr;</span>')}
                `;

                if (!isBurned && !g.is_locked) {
                    card.onclick = () => openSurvivorConfirmModal(team);
                }
                container.appendChild(card);
            });
        });

        if (activeSurvivorPick) {
            document.getElementById('btnFinishSurvivor').disabled = false;
            document.getElementById('survivorSelectionMsg').textContent = `✓ ${activeSurvivorPick} Locked In`;
            document.getElementById('survivorSelectionMsg').className = 'text-xs font-mono text-emerald-400 font-bold';
        }
    }

    function openSurvivorConfirmModal(team) {
        pendingSurvivorSelection = team;
        document.getElementById('confirmModalLogo').src = team.logo;
        document.getElementById('confirmModalTeamName').textContent = team.name;
        document.getElementById('confirmModalTeamNick').textContent = team.nick;
        document.getElementById('survivorConfirmModal').classList.remove('hidden');
    }

    window.closeSurvivorConfirmModal = function() {
        document.getElementById('survivorConfirmModal').classList.add('hidden');
        pendingSurvivorSelection = null;
    };

    window.commitSurvivorPick = function() {
        if (!pendingSurvivorSelection) return;
        activeSurvivorPick = pendingSurvivorSelection.abbr;
        closeSurvivorConfirmModal();
        renderSurvivorGrid();

        // Autosave survivor pick to server
        fetch('/survivor/autosave', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                season_year: seasonYear,
                week_number: weekNumber,
                selected_team: activeSurvivorPick,
            })
        }).catch(e => console.warn(e));

        document.getElementById('btnFinishSurvivor').disabled = false;
        document.getElementById('survivorSelectionMsg').textContent = `✓ ${activeSurvivorPick} Locked In!`;
        document.getElementById('survivorSelectionMsg').className = 'text-xs font-mono text-emerald-400 font-bold';

        // Auto advance to state 5 (Review)
        setTimeout(() => goToState(5), 300);
    };

    // -----------------------------------------------------------------
    // 8. State 5: Completion & Review
    // -----------------------------------------------------------------
    function renderCompletionSummary() {
        const pickedCount = wizardGames.filter(g => g.user_pick !== null).length;
        document.getElementById('wizardCompleteTotal').textContent = `${pickedCount} / ${wizardGames.length}`;
        document.getElementById('wizardCompleteTb').textContent = mnfPredictedPoints ? `${mnfPredictedPoints} PTS` : '--';
        document.getElementById('wizardCompleteSurvivor').textContent = activeSurvivorPick || '--';
    }

    window.exitWizardToStandardView = function() {
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        if (audioNfl) audioNfl.pause();
        if (audioPyl) audioPyl.pause();
    };

    btnWizardExit.onclick = exitWizardToStandardView;
    
    function launchWizardModal() {
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        goToState(1);
    }

    if (btnLaunchWizard) btnLaunchWizard.onclick = launchWizardModal;
    if (btnLaunchWizardHero) btnLaunchWizardHero.onclick = launchWizardModal;
    const btnOpenHowItWorks = document.getElementById('btnOpenHowItWorks');
    if (btnOpenHowItWorks) btnOpenHowItWorks.onclick = openPickemRules;

    // Standard Grid Sync Hook
    window.syncWithStandardGrid = function(gameId, team) {
        const gridRadio = document.querySelector(`input[name="picks[${gameId}]"][value="${team}"]`);
        if (gridRadio) {
            gridRadio.checked = true;
            gridRadio.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    // Keyboard Shortcuts
    const shortcutsModal = document.getElementById('wizardShortcutsModal');
    const btnShortcuts = document.getElementById('btnWizardShortcuts');
    const btnCloseShortcuts = document.getElementById('btnCloseShortcutsModal');

    if (btnShortcuts) btnShortcuts.onclick = () => shortcutsModal.classList.remove('hidden');
    if (btnCloseShortcuts) btnCloseShortcuts.onclick = () => shortcutsModal.classList.add('hidden');

    window.addEventListener('keydown', (e) => {
        if (modal.classList.contains('hidden')) return;

        if (e.key === 'Escape') {
            if (!shortcutsModal.classList.contains('hidden')) {
                shortcutsModal.classList.add('hidden');
            } else if (!document.getElementById('wizardHelpModal').classList.contains('hidden')) {
                closeHelpModal();
            } else {
                exitWizardToStandardView();
            }
            return;
        }

        if (currentState === 1) {
            const game = wizardGames[currentIndex];
            if (e.key === '1' || e.key === 'a' || e.key === 'A' || e.key === 'ArrowUp') {
                e.preventDefault();
                makePick(game.away_team);
            } else if (e.key === '2' || e.key === 'h' || e.key === 'H' || e.key === 'ArrowDown') {
                e.preventDefault();
                makePick(game.home_team);
            } else if (e.key === 'ArrowRight' || e.key === ' ' || e.key === 'd' || e.key === 'D') {
                e.preventDefault();
                btnWizardNext.click();
            } else if (e.key === 'ArrowLeft' || e.key === 'w' || e.key === 'W') {
                e.preventDefault();
                btnWizardPrev.click();
            }
        }
    });

    // Auto-launch if explicitly requested via ?mode=wizard
    <?php if ($isAutoLaunch): ?>
        launchWizardModal();
    <?php endif; ?>
});
</script>
