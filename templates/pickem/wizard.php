<?php

declare(strict_types=1);

use WallyFootball\Support\TeamData;

/**
 * Weekly Pick Wizard Component
 * Full-screen, landscape-focused 5-state wizard flow.
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
 */

$usedSurvivorTeams = $usedSurvivorTeams ?? [];
$missedSurvivorWeeks = $missedSurvivorWeeks ?? [];
$needsSurvivorCatchup = $needsSurvivorCatchup ?? (!empty($missedSurvivorWeeks));
$currentSurvivorPick = $currentSurvivorPick ?? null;
$isSurvivorEliminated = $isSurvivorEliminated ?? false;

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
        'home_conf' => $hTeam['conf'] ?? '',
        'home_division' => $hTeam['division'] ?? '',
        'home_color' => $hTeam['color'],
        'home_color2' => $hTeam['color2'] ?? '#ffffff',
        'home_logo' => $hTeam['logo'],
        'home_score' => $g['home_score'] ?? null,

        'away_team' => $g['away_team'],
        'away_name' => $aTeam['name'],
        'away_nick' => $aTeam['nick'],
        'away_conf' => $aTeam['conf'] ?? '',
        'away_division' => $aTeam['division'] ?? '',
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

// Auto-launch if explicitly requested via ?mode=wizard OR if user has incomplete picks and week is unlocked
$hasIncompletePicks = count($userPicks) < count($games);
$isAutoLaunch = (isset($_GET['mode']) && $_GET['mode'] === 'wizard')
    || str_contains($_SERVER['REQUEST_URI'] ?? '', '/pickem/wizard')
    || ($hasIncompletePicks && empty($isWeekLocked));
?>

<!-- Retro Arcade 8-Bit Font for Authentic Press Your Luck -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">

<style>
  .font-pyl { font-family: 'Press Start 2P', monospace; }

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

  /* Big Red Arcade Buzzer */
  .arcade-buzzer-btn {
    background: radial-gradient(circle at 35% 35%, #ff4d4d, #cc0000 60%, #800000 100%);
    box-shadow: 0 8px 0 #590000, 0 16px 20px rgba(255, 0, 0, 0.6), inset 0 2px 4px rgba(255, 255, 255, 0.6);
    transition: all 0.08s ease;
  }
  .arcade-buzzer-btn:active {
    transform: translateY(5px);
    box-shadow: 0 3px 0 #590000, 0 6px 12px rgba(255, 0, 0, 0.5), inset 0 1px 2px rgba(255, 255, 255, 0.5);
  }

  /* Audio Equalizer animation */
  @keyframes eq-pulse {
    0%, 100% { height: 4px; }
    50% { height: 16px; }
  }
  .eq-b1 { animation: eq-pulse 0.5s infinite ease-in-out; }
  .eq-b2 { animation: eq-pulse 0.7s infinite ease-in-out 0.15s; }
  .eq-b3 { animation: eq-pulse 0.4s infinite ease-in-out 0.3s; }
</style>

<!-- Audio Elements (pointing to web-served /media/) -->
<audio id="audioNflTheme" src="/media/nfl-theme.mp3" loop preload="auto"></audio>
<audio id="audioPylSoundboard" src="/media/press-your-luck-sound-board.mp3" loop preload="auto"></audio>

<!-- ================================================================= -->
<!-- FULL-SCREEN WEEKLY PICK WIZARD MODAL                              -->
<!-- ================================================================= -->
<div id="pickWizardModal" 
     class="fixed inset-0 z-50 flex flex-col bg-[#060c18]/98 backdrop-blur-2xl text-slate-100 select-none overflow-hidden <?= $isAutoLaunch ? '' : 'hidden' ?>"
     role="dialog" 
     aria-modal="true" 
     aria-label="Weekly Pick Wizard">

    <!-- Background Turf Glow Effect -->
    <div class="pointer-events-none absolute inset-0 opacity-15 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-emerald-600/30 via-slate-900/40 to-transparent"></div>

    <!-- TOP CONTROL BAR -->
    <header class="relative z-10 border-b border-[#243247]/80 bg-[#0B1626]/95 px-4 py-3 sm:px-6">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-3">
            
            <!-- Left: Exit Button & Week Badge -->
            <div class="flex items-center gap-3">
                <button type="button" 
                        id="btnWizardExit" 
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-[#243247] bg-[#162235] hover:bg-[#1e2e48] hover:border-slate-500 text-slate-300 hover:text-white text-xs font-bold transition shadow-sm"
                        title="Exit to Standard View (Esc)">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Exit to Standard View</span>
                    <span class="hidden sm:inline-block text-[10px] font-mono px-1.5 py-0.5 rounded bg-black/40 text-slate-400 border border-slate-700">ESC</span>
                </button>

                <div class="hidden sm:flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded font-mono text-[10px] font-black bg-[#EAB308] text-[#0B1626] uppercase tracking-wider">
                        Week <?= $week ?>
                    </span>
                    <span class="text-xs font-bold text-slate-300">Pick Wizard</span>
                </div>
            </div>

            <!-- Center: Step Progress Pills & Live Progress -->
            <div class="flex flex-col items-center flex-1 max-w-xl mx-2">
                <!-- 5-State Step Tracker Pills -->
                <div class="grid grid-cols-5 gap-1 sm:gap-2 w-full text-center text-[9px] sm:text-[10px] font-mono font-bold mb-1.5">
                    <div id="step-pill-1" onclick="goToState(1)" class="p-1 rounded cursor-pointer bg-amber-500 text-slate-950 font-black shadow truncate">1. Pick'em</div>
                    <div id="step-pill-2" onclick="goToState(2)" class="p-1 rounded cursor-pointer bg-slate-900 border border-slate-800 text-slate-400 truncate">2. Tiebreaker</div>
                    <div id="step-pill-3" onclick="goToState(3)" class="p-1 rounded cursor-pointer bg-slate-900 border border-slate-800 text-slate-400 truncate">3. Catch-Up</div>
                    <div id="step-pill-4" onclick="goToState(4)" class="p-1 rounded cursor-pointer bg-slate-900 border border-slate-800 text-slate-400 truncate">4. Survivor</div>
                    <div id="step-pill-5" onclick="goToState(5)" class="p-1 rounded cursor-pointer bg-slate-900 border border-slate-800 text-slate-400 truncate">5. Review</div>
                </div>

                <!-- Progress Track -->
                <div class="w-full h-1.5 rounded-full bg-[#162235] border border-[#243247] overflow-hidden p-0.5">
                    <div id="wizardProgressBar" 
                         class="h-full rounded-full bg-gradient-to-r from-emerald-500 via-amber-400 to-[#EAB308] transition-all duration-300 ease-out" 
                         style="width: 0%;"></div>
                </div>
                <div class="flex items-center justify-between w-full text-[10px] font-mono font-bold mt-1 text-slate-400">
                    <span id="wizardGameStepLabel" class="text-amber-400">Game 1 of <?= count($wizardGames) ?></span>
                    <span id="wizardPicksCountLabel">0 of <?= count($wizardGames) ?> Picked</span>
                </div>
            </div>

            <!-- Right: Audio SFX Toggle & Shortcuts -->
            <div class="flex items-center gap-2">
                <button type="button" 
                        id="btnWizardAudioToggle"
                        onclick="toggleAudioPlayback()"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-[#243247] bg-[#162235] hover:bg-[#1e2e48] text-xs font-bold text-slate-300 hover:text-white transition"
                        title="Toggle Background NFL Theme">
                    <span id="wizardAudioIcon">🎵</span>
                    <span id="wizardAudioLabel" class="hidden sm:inline text-[11px]">NFL Theme</span>
                    <div class="flex items-center gap-0.5 h-3 ml-0.5">
                        <div id="eq1" class="w-1 bg-amber-400 rounded-full" style="height: 4px;"></div>
                        <div id="eq2" class="w-1 bg-amber-400 rounded-full" style="height: 6px;"></div>
                        <div id="eq3" class="w-1 bg-amber-400 rounded-full" style="height: 4px;"></div>
                    </div>
                </button>

                <button type="button" 
                        id="btnWizardShortcuts"
                        class="hidden md:inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-[#243247] bg-[#162235] hover:bg-[#1e2e48] text-xs font-bold text-slate-400 hover:text-slate-200 transition"
                        title="Keyboard Shortcuts">
                    <span>⌨️</span>
                    <span class="text-[11px]">Shortcuts</span>
                </button>
            </div>
        </div>
    </header>

    <!-- MAIN VIEWPORT: 5 CLEAN WORKFLOW STATES -->
    <main class="relative z-10 flex-1 flex items-center justify-center p-2 sm:p-5 lg:p-8 overflow-y-auto">
        <div class="w-full max-w-4xl mx-auto flex flex-col items-center justify-center">

            <!-- ================================================================= -->
            <!-- STATE 1: PICK'EM MATCHUP CAROUSEL (Ultra-Compact on Mobile)        -->
            <!-- ================================================================= -->
            <section id="state-1-view" class="w-full flex flex-col items-center">
                
                <!-- 1-Sentence Onboarding Dismissible Alert (Hidden on small mobile to maximize screen) -->
                <div id="pickemIntroBanner" class="hidden sm:flex w-full mb-3 p-2.5 px-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-300 items-center justify-between">
                    <span>🏈 <strong>Pick'em Mode:</strong> Pick every outright winner. Instant autosave in real time! Games lock individually at kickoff.</span>
                    <button onclick="this.parentElement.remove()" class="text-amber-400 hover:text-white text-base leading-none ml-2">&times;</button>
                </div>

                <!-- Matchup Card Container with smooth slide transitions -->
                <div id="wizardCardContainer" class="w-full transition-all duration-300 transform opacity-100 scale-100 flex flex-col items-center">
                    
                    <!-- Compact Kickoff & Status Subheader Bar -->
                    <div class="w-full flex items-center justify-between text-[11px] font-mono px-2 py-1 mb-2 text-slate-400 border-b border-[#243247]/60">
                        <span id="wizardKickoffText" class="font-bold text-slate-300"></span>
                        <span id="wizardStatusBadge" class="px-2 py-0.5 rounded text-[9px] sm:text-[10px] uppercase font-bold tracking-wider bg-black/40 border border-slate-700 text-amber-400"></span>
                    </div>

                    <div id="wizardLockNotice" class="hidden w-full mb-2 p-1.5 rounded-lg bg-amber-950/40 border border-amber-500/40 text-amber-300 text-xs font-mono text-center">
                        🔒 Game Locked
                    </div>

                    <!-- JOINED DUEL MODULE: Away & Home physically touch, VS badge joins them at the seam -->
                    <div class="relative w-full rounded-2xl border-2 border-[#243247] bg-[#0d1624] shadow-2xl flex flex-col md:grid md:grid-cols-[1fr_auto_1fr] overflow-hidden">

                        <!-- AWAY TEAM CARD (Top on mobile, Left on desktop) -->
                        <div id="wizardAwayCard" 
                             class="wizard-team-card relative group flex flex-row md:flex-col items-center justify-between p-3.5 sm:p-5 md:p-8 cursor-pointer select-none transition-all duration-150 overflow-hidden"
                             data-team-type="away">
                            
                            <!-- Top Accent Stripe -->
                            <div id="wizardAwayStripe" class="absolute top-0 left-0 right-0 h-1.5 md:h-2 bg-slate-600 transition-colors"></div>

                            <!-- Pick Check Badge -->
                            <div id="wizardAwayCheck" 
                                 class="absolute top-2.5 right-2.5 md:top-4 md:right-4 w-6 h-6 sm:w-8 sm:h-8 md:w-9 md:h-9 rounded-full bg-[#EAB308] text-[#0B1626] flex items-center justify-center shadow-lg transition-all duration-200 scale-0 opacity-0 z-10">
                                <svg class="w-3.5 h-3.5 md:w-5 md:h-5 stroke-[3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </div>

                            <!-- Mobile: Row (Logo + Info) / Desktop: Column Stack -->
                            <div class="flex items-center gap-3 md:flex-col md:gap-0 flex-1 min-w-0">
                                <div class="w-14 h-14 sm:w-16 sm:h-16 md:w-28 md:h-28 md:my-3 flex items-center justify-center shrink-0 transform transition-transform duration-300 group-hover:scale-105">
                                    <img id="wizardAwayLogo" 
                                         src="" 
                                         alt="Away Team Logo" 
                                         class="max-h-full max-w-full object-contain filter drop-shadow-xl">
                                </div>

                                <div class="text-left md:text-center flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 md:justify-center mb-0.5">
                                        <span id="wizardAwayConf" class="px-1.5 py-0.2 rounded bg-black/40 border border-slate-700/60 font-semibold uppercase tracking-wider text-[9px] text-slate-400">AWAY</span>
                                        <span id="wizardAwayDivision" class="text-[10px] font-medium text-slate-500 truncate"></span>
                                    </div>
                                    <div class="flex items-baseline gap-1.5 md:flex-col md:gap-0">
                                        <span id="wizardAwayAbbr" class="text-base sm:text-xl md:text-3xl font-black font-mono tracking-tight text-white"></span>
                                        <span id="wizardAwayName" class="text-xs sm:text-sm md:text-base font-bold text-slate-300 truncate"></span>
                                    </div>
                                    <span id="wizardAwayScore" class="hidden text-sm sm:text-base md:text-xl font-black font-mono text-emerald-400 mt-0.5"></span>
                                </div>
                            </div>

                            <!-- Action Button -->
                            <div class="shrink-0 ml-2 md:ml-0 md:w-full md:mt-5">
                                <button type="button" 
                                        id="btnPickAway"
                                        class="py-2 px-3 sm:px-4 md:py-3 rounded-xl font-black text-xs sm:text-sm font-mono tracking-wide uppercase transition-all shadow-md flex items-center justify-center gap-1.5 bg-[#0B1626] border border-[#243247] text-slate-200 group-hover:border-amber-400/80 group-hover:text-white">
                                    <span>Select Away</span>
                                </button>
                            </div>
                        </div>

                        <!-- VS DIVIDER SEAM & BADGE (Physically joining Away and Home at the boundary!) -->
                        <div class="relative flex items-center justify-center -my-3.5 md:my-0 md:h-full z-20 pointer-events-none">
                            <div class="w-8 h-8 sm:w-10 sm:h-10 md:w-14 md:h-14 rounded-full bg-[#162235] border-2 border-amber-400 text-amber-400 font-black font-mono text-[11px] sm:text-xs md:text-base flex items-center justify-center shadow-2xl">
                                VS
                            </div>
                        </div>

                        <!-- HOME TEAM CARD (Bottom on mobile, Right on desktop) -->
                        <div id="wizardHomeCard" 
                             class="wizard-team-card relative group flex flex-row md:flex-col items-center justify-between p-3.5 sm:p-5 md:p-8 cursor-pointer select-none transition-all duration-150 overflow-hidden border-t border-[#243247] md:border-t-0 md:border-l"
                             data-team-type="home">
                            
                            <!-- Top Accent Stripe -->
                            <div id="wizardHomeStripe" class="absolute top-0 left-0 right-0 h-1.5 md:h-2 bg-slate-600 transition-colors"></div>

                            <!-- Pick Check Badge -->
                            <div id="wizardHomeCheck" 
                                 class="absolute top-2.5 right-2.5 md:top-4 md:right-4 w-6 h-6 sm:w-8 sm:h-8 md:w-9 md:h-9 rounded-full bg-[#EAB308] text-[#0B1626] flex items-center justify-center shadow-lg transition-all duration-200 scale-0 opacity-0 z-10">
                                <svg class="w-3.5 h-3.5 md:w-5 md:h-5 stroke-[3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </div>

                            <!-- Mobile: Row (Logo + Info) / Desktop: Column Stack -->
                            <div class="flex items-center gap-3 md:flex-col md:gap-0 flex-1 min-w-0">
                                <div class="w-14 h-14 sm:w-16 sm:h-16 md:w-28 md:h-28 md:my-3 flex items-center justify-center shrink-0 transform transition-transform duration-300 group-hover:scale-105">
                                    <img id="wizardHomeLogo" 
                                         src="" 
                                         alt="Home Team Logo" 
                                         class="max-h-full max-w-full object-contain filter drop-shadow-xl">
                                </div>

                                <div class="text-left md:text-center flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 md:justify-center mb-0.5">
                                        <span id="wizardHomeConf" class="px-1.5 py-0.2 rounded bg-black/40 border border-slate-700/60 font-semibold uppercase tracking-wider text-[9px] text-slate-400">HOME</span>
                                        <span id="wizardHomeDivision" class="text-[10px] font-medium text-slate-500 truncate"></span>
                                    </div>
                                    <div class="flex items-baseline gap-1.5 md:flex-col md:gap-0">
                                        <span id="wizardHomeAbbr" class="text-base sm:text-xl md:text-3xl font-black font-mono tracking-tight text-white"></span>
                                        <span id="wizardHomeName" class="text-xs sm:text-sm md:text-base font-bold text-slate-300 truncate"></span>
                                    </div>
                                    <span id="wizardHomeScore" class="hidden text-sm sm:text-base md:text-xl font-black font-mono text-emerald-400 mt-0.5"></span>
                                </div>
                            </div>

                            <!-- Action Button -->
                            <div class="shrink-0 ml-2 md:ml-0 md:w-full md:mt-5">
                                <button type="button" 
                                        id="btnPickHome"
                                        class="py-2 px-3 sm:px-4 md:py-3 rounded-xl font-black text-xs sm:text-sm font-mono tracking-wide uppercase transition-all shadow-md flex items-center justify-center gap-1.5 bg-[#0B1626] border border-[#243247] text-slate-200 group-hover:border-amber-400/80 group-hover:text-white">
                                    <span>Select Home</span>
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- Carousel Controls & Autosave Notification -->
                    <div class="flex items-center justify-between pt-3 mt-3 sm:pt-4 sm:mt-4 border-t border-[#243247]/80 w-full">
                        <button type="button" 
                                id="btnWizardPrev"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 sm:px-4 sm:py-2 rounded-xl bg-slate-800 text-slate-400 hover:text-white text-xs font-mono font-bold transition disabled:opacity-30 disabled:pointer-events-none">
                            &larr; <span class="hidden sm:inline">Previous Game</span><span class="sm:hidden">Prev</span>
                        </button>

                        <span class="text-[11px] sm:text-xs font-mono text-emerald-400 font-bold truncate px-2 text-center" id="carouselAutoSaveMsg">
                            ⚡ Auto-Saves in Real Time
                        </span>

                        <button type="button" 
                                id="btnWizardNext"
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 sm:px-5 sm:py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-mono font-black transition shadow">
                            <span id="wizardNextBtnText">Next</span> &rarr;
                        </button>
                    </div>

                    <!-- Mini Game Timeline Dots / Navigation Pills -->
                    <div id="wizardTimeline" class="flex items-center justify-center gap-1 sm:gap-1.5 overflow-x-auto py-2 max-w-full px-1 mt-1">
                        <!-- Dynamically populated pills -->
                    </div>

                </div>
            </section>

            <!-- ================================================================= -->
            <!-- STATE 2: TIEBREAKER INPUT (MONDAY NIGHT FOOTBALL)                 -->
            <!-- ================================================================= -->
            <section id="state-2-view" class="hidden w-full max-w-2xl mx-auto text-center space-y-6">
                <div class="p-6 sm:p-10 rounded-2xl bg-gradient-to-b from-[#162235] to-[#0d1624] border-2 border-amber-500/40 shadow-2xl">
                    
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 text-xs font-mono font-bold mb-4">
                        <span>🎯 Designated Tiebreaker Matchup</span>
                    </div>

                    <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                        Monday Night Football Total Points
                    </h2>
                    <p class="text-xs font-mono text-slate-400 mt-1" id="wizardTbMatchupLabel">
                        Predict Total Combined Final Score
                    </p>

                    <!-- Slot Machine / Stepper Widget -->
                    <div id="wizardTiebreakerBox" class="my-8 p-6 rounded-2xl bg-slate-950 border-2 border-amber-500/50 shadow-[0_0_30px_rgba(234,179,8,0.2)]">
                        <span class="text-[10px] font-mono uppercase tracking-widest text-slate-400 block mb-2">Combined Score Prediction</span>
                        
                        <div class="flex items-center justify-center gap-4">
                            <button type="button" onclick="adjustTiebreaker(-1)" class="w-12 h-12 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-mono font-bold text-2xl transition active:scale-90">-</button>
                            
                            <div class="relative w-36">
                                <input type="number" 
                                       id="wizardTiebreakerInput" 
                                       value="<?= $tbCurrentPoints ?? 47 ?>" 
                                       min="10" 
                                       max="120"
                                       class="w-full text-center text-4xl sm:text-5xl font-black font-mono bg-transparent text-amber-400 outline-none border-b-2 border-amber-500/50 focus:border-amber-400 pb-1">
                                <span class="block text-[10px] font-mono text-slate-400 mt-1 uppercase">TOTAL POINTS</span>
                            </div>

                            <button type="button" onclick="adjustTiebreaker(1)" class="w-12 h-12 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-mono font-bold text-2xl transition active:scale-90">+</button>
                        </div>

                        <!-- Slot Machine Randomizer & Quick Presets -->
                        <div class="mt-6 flex items-center justify-center gap-2 flex-wrap">
                            <button type="button" onclick="spinTiebreakerRandom()" class="px-3 py-1.5 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 text-xs font-mono font-bold transition">
                                🎰 Random Spin (34–54)
                            </button>
                            <button type="button" onclick="setTiebreakerVal(41)" class="px-2.5 py-1 rounded bg-slate-900 border border-slate-800 text-slate-400 hover:text-white text-xs font-mono">41</button>
                            <button type="button" onclick="setTiebreakerVal(45)" class="px-2.5 py-1 rounded bg-slate-900 border border-slate-800 text-slate-400 hover:text-white text-xs font-mono">45</button>
                            <button type="button" onclick="setTiebreakerVal(48)" class="px-2.5 py-1 rounded bg-slate-900 border border-slate-800 text-slate-400 hover:text-white text-xs font-mono">48</button>
                            <button type="button" onclick="setTiebreakerVal(51)" class="px-2.5 py-1 rounded bg-slate-900 border border-slate-800 text-slate-400 hover:text-white text-xs font-mono">51</button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-slate-800">
                        <button type="button" onclick="goToState(1)" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-mono font-bold transition">
                            &larr; Back to Pick'em
                        </button>
                        <button type="button" onclick="handleTiebreakerNext()" class="px-6 py-3 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-400 hover:from-amber-400 text-slate-950 font-black text-xs font-mono uppercase tracking-wider transition shadow-lg">
                            Lock Tiebreaker &amp; Continue &rarr;
                        </button>
                    </div>

                </div>
            </section>

            <!-- ================================================================= -->
            <!-- STATE 3: PRESS YOUR LUCK ELIMINATION (SURVIVOR CATCH-UP)          -->
            <!-- 18 Perimeter Squares: Team Logos ONLY, Centered, Larson Patterns  -->
            <!-- ================================================================= -->
            <section id="state-3-view" class="hidden w-full max-w-4xl mx-auto space-y-4">
                
                <!-- Catch-Up Context Banner -->
                <div class="p-4 rounded-2xl border-2 border-emerald-500/40 bg-gradient-to-r from-emerald-950/40 via-slate-900 to-slate-950 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <span class="px-2 py-0.5 rounded bg-emerald-500 text-slate-950 font-pyl text-[9px] uppercase">
                            Late Arrival Detected
                        </span>
                        <h2 class="text-base sm:text-lg font-black text-white mt-1" id="pylCatchupTitle">
                            Catch-Up: Burn Handicap Teams
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5" id="pylCatchupSubtitle">
                            Missed weeks detected! Spin the authentic 18-Square Board to eliminate your handicap teams.
                        </p>
                    </div>
                    <div class="text-right font-mono text-xs">
                        <span class="text-slate-400 block text-[10px] uppercase">Burned Progress:</span>
                        <span id="pylHandicapCounter" class="text-amber-400 font-black text-sm">0 of 0 Teams</span>
                    </div>
                </div>

                <!-- The 18-Square Chassis -->
                <div class="pyl-chassis p-3 sm:p-5">
                    <div class="grid grid-cols-6 grid-rows-5 gap-2 sm:gap-3 aspect-[6/5] w-full">
                        
                        <!-- TOP ROW: Squares 1 to 6 (Team logos only, centered) -->
                        <div id="pyl-sq-1" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-2"></div></div>
                        <div id="pyl-sq-2" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-2"></div></div>
                        <div id="pyl-sq-3" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-2"></div></div>
                        <div id="pyl-sq-4" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-2"></div></div>
                        <div id="pyl-sq-5" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-2"></div></div>
                        <div id="pyl-sq-6" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-2"></div></div>

                        <!-- ROW 2: Sq 18 (Left), Sq 7 (Right) -->
                        <div id="pyl-sq-18" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-2"></div></div>
                        
                        <!-- CENTER STAGE: Buzzer, Whammy Overlay, Status -->
                        <div class="col-span-4 row-span-3 rounded-2xl bg-gradient-to-b from-[#080f1d] via-[#050a14] to-black border-2 border-slate-800 p-4 sm:p-6 flex flex-col items-center justify-between text-center relative overflow-hidden shadow-2xl">
                            
                            <!-- Transparent Whammy Canvas Overlay -->
                            <canvas id="whammyCanvas" width="480" height="360" class="absolute inset-0 w-full h-full object-contain pointer-events-none z-40 hidden"></canvas>
                            <video id="whammyVideoPlayer" playsinline preload="auto" class="hidden"></video>

                            <!-- Pattern HUD -->
                            <div class="w-full flex items-center justify-between border-b border-slate-800/80 pb-2 z-10">
                                <span class="font-pyl text-[8px] sm:text-[9px] text-amber-400">SEQUENCE: <span id="pylPatternTxt">LARSON #1</span></span>
                                <span class="font-mono text-[10px] text-slate-400">Spaces Cycling &bull; 1984 Larson Patterns</span>
                            </div>

                            <!-- Central Message Box -->
                            <div class="my-auto z-10 flex flex-col items-center justify-center p-2">
                                <div id="pylMainMsg" class="font-pyl text-sm sm:text-lg text-yellow-300 drop-shadow-[0_2px_10px_rgba(255,234,0,0.5)]">
                                    HIT BUZZER TO SPIN!
                                </div>
                                <p id="pylSubMsg" class="text-xs font-mono text-slate-400 mt-2 max-w-md">
                                    "No Whammies, Big Bucks... STOP!"
                                </p>
                            </div>

                            <!-- Big Red Arcade Buzzer Button -->
                            <div class="z-20 mt-2 mb-1">
                                <button type="button" 
                                        id="btnPylBuzzer"
                                        onclick="handlePylBuzzer()"
                                        class="arcade-buzzer-btn px-8 sm:px-12 py-3.5 sm:py-4 rounded-full font-pyl text-xs sm:text-sm tracking-wider uppercase text-white shadow-2xl active:scale-95 cursor-pointer">
                                    <span id="pylBuzzerLabel">SPIN BOARD!</span>
                                </button>
                            </div>

                        </div>

                        <div id="pyl-sq-7" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-2"></div></div>

                        <!-- ROW 3: Sq 17 (Left), Sq 8 (Right) -->
                        <div id="pyl-sq-17" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-2"></div></div>
                        <div id="pyl-sq-8" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-2"></div></div>

                        <!-- ROW 4: Sq 16 (Left), Sq 9 (Right) -->
                        <div id="pyl-sq-16" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-2"></div></div>
                        <div id="pyl-sq-9" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-2"></div></div>

                        <!-- BOTTOM ROW: Squares 15 to 10 (Right to Left) -->
                        <div id="pyl-sq-15" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-2"></div></div>
                        <div id="pyl-sq-14" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-2"></div></div>
                        <div id="pyl-sq-13" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-2"></div></div>
                        <div id="pyl-sq-12" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-2"></div></div>
                        <div id="pyl-sq-11" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-2"></div></div>
                        <div id="pyl-sq-10" class="pyl-square rounded-xl"><div class="slide-content slide-fade w-full h-full flex items-center justify-center p-2"></div></div>

                    </div>
                </div>

                <!-- Burned Teams Ledger -->
                <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-between font-mono text-xs">
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold">Burned Handicap Teams:</span>
                        <span id="pylBurnedLedger" class="text-white font-bold">None yet</span>
                    </div>
                    <button type="button" onclick="goToState(4)" id="btnSkipCatchup" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition">
                        Advance to Survivor Pick &rarr;
                    </button>
                </div>

            </section>

            <!-- ================================================================= -->
            <!-- STATE 4: SURVIVOR PICK SELECTION                                  -->
            <!-- ================================================================= -->
            <section id="state-4-view" class="hidden w-full max-w-4xl mx-auto space-y-6">
                
                <!-- 1-Sentence Onboarding Dismissible Alert -->
                <div id="survivorIntroBanner" class="p-3.5 px-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-xs text-emerald-300 flex items-center justify-between">
                    <span>🛡️ <strong>Survivor Mode:</strong> Pick 1 team to win outright this week. You can never pick them again this season!</span>
                    <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white text-base leading-none">&times;</button>
                </div>

                <div class="p-6 sm:p-8 rounded-2xl bg-gradient-to-b from-[#162235] to-[#0d1624] border border-[#243247] shadow-xl">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-6">
                        <div>
                            <h2 class="text-lg sm:text-xl font-black text-white">Select Your Week <?= $week ?> Survivor Pick</h2>
                            <p class="text-xs font-mono text-slate-400">Previously used &amp; Whammy-burned teams are locked with padlocks.</p>
                        </div>
                        <span class="px-3 py-1 rounded-lg bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 font-mono text-xs font-bold">
                            1 Team Required
                        </span>
                    </div>

                    <!-- Survivor Matchup Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5" id="survivorGridList">
                        <!-- Dynamically populated via JS -->
                    </div>

                    <div class="flex items-center justify-between pt-6 mt-6 border-t border-slate-800">
                        <button type="button" onclick="goToState(2)" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-mono font-bold transition">
                            &larr; Back
                        </button>
                        <span class="text-xs font-mono text-slate-400" id="survivorSelectionMsg">Select 1 team to lock in</span>
                        <button type="button" onclick="goToState(5)" id="btnFinishSurvivor" class="px-6 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-mono font-black transition disabled:opacity-40 disabled:pointer-events-none" disabled>
                            Review &amp; Lock Picks &rarr;
                        </button>
                    </div>

                </div>
            </section>

            <!-- ================================================================= -->
            <!-- STATE 5: COMPLETION / REVIEW & CELEBRATION                        -->
            <!-- ================================================================= -->
            <div id="wizardCompletionView" class="hidden text-center max-w-xl mx-auto p-6 sm:p-10 rounded-2xl border-2 border-emerald-500/40 bg-gradient-to-b from-[#162235] to-[#0B1626] shadow-2xl animate-fade-in">
                <div class="text-5xl sm:text-6xl mb-4">🏆</div>
                <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight mb-2">
                    Week <?= $week ?> Picks Complete!
                </h2>
                <p class="text-sm sm:text-base text-slate-300 mb-6">
                    Every game has been picked and immediately auto-saved to your profile. You're locked and loaded for kickoff!
                </p>

                <div class="grid grid-cols-3 gap-3 max-w-md mx-auto mb-8 text-left">
                    <div class="p-3 rounded-lg bg-[#0B1626] border border-[#243247]">
                        <span class="text-[10px] font-mono text-slate-400 uppercase block mb-1">Pick'em Slate</span>
                        <span id="wizardCompleteTotal" class="text-base sm:text-lg font-black font-mono text-emerald-400"><?= count($wizardGames) ?> / <?= count($wizardGames) ?></span>
                    </div>
                    <div class="p-3 rounded-lg bg-[#0B1626] border border-[#243247]">
                        <span class="text-[10px] font-mono text-slate-400 uppercase block mb-1">MNF Points</span>
                        <span id="wizardCompleteTb" class="text-base sm:text-lg font-black font-mono text-amber-400"><?= $tbCurrentPoints ?? '--' ?> PTS</span>
                    </div>
                    <div class="p-3 rounded-lg bg-[#0B1626] border border-[#243247]">
                        <span class="text-[10px] font-mono text-slate-400 uppercase block mb-1">Survivor</span>
                        <span id="wizardCompleteSurvivor" class="text-base sm:text-lg font-black font-mono text-emerald-400"><?= htmlspecialchars((string) ($currentSurvivorPick ?? '--')) ?></span>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                    <button type="button" 
                            id="btnWizardFinishReview"
                            onclick="exitWizardToStandardView()"
                            class="w-full sm:w-auto px-6 py-3.5 rounded-xl font-black text-xs font-mono uppercase tracking-wider bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 text-slate-950 shadow-lg shadow-emerald-500/20 transition transform hover:-translate-y-0.5">
                        View Live Standings &amp; Picks Table &rarr;
                    </button>
                    <button type="button" 
                            id="btnWizardRestart"
                            onclick="goToState(1)"
                            class="w-full sm:w-auto px-5 py-3.5 rounded-xl font-bold text-xs font-mono bg-[#162235] hover:bg-[#1e2e48] border border-[#243247] text-slate-300 hover:text-white transition">
                        Modify Picks
                    </button>
                </div>
            </div>

        </div>
    </main>

    <!-- "YOU PICK" MODAL (Triggered when user lands on "YOU PICK" PYL space) -->
    <div id="youPickModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/85 backdrop-blur-md">
        <div class="w-full max-w-lg p-6 rounded-2xl bg-slate-900 border-2 border-emerald-500 shadow-2xl">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-4">
                <div>
                    <span class="px-2 py-0.5 rounded bg-emerald-500 text-slate-950 font-pyl text-[9px] uppercase">LUCKY HIT!</span>
                    <h3 class="text-lg font-black text-white mt-1">YOU PICK: Choose a Team to Burn</h3>
                </div>
            </div>
            <p class="text-xs text-slate-300 mb-4">
                You landed on <strong>YOU PICK</strong>! Choose one of the teams currently showing on the board to eliminate as your handicap:
            </p>
            <div id="youPickGrid" class="grid grid-cols-3 sm:grid-cols-4 gap-3 max-h-60 overflow-y-auto p-1">
                <!-- Injected via JavaScript -->
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

    let currentState = 1;
    let currentIndex = 0;
    let isTransitioning = false;

    // -----------------------------------------------------------------
    // 2. Audio Engine (NFL Theme & PYL Soundboard)
    // -----------------------------------------------------------------
    const audioNfl = document.getElementById('audioNflTheme');
    const audioPyl = document.getElementById('audioPylSoundboard');
    let isNflAudioPlaying = false;

    // Set initial volume at ~30% as requested
    if (audioNfl) audioNfl.volume = 0.30;
    if (audioPyl) audioPyl.volume = 0.40;

    function startNflThemeLoop() {
        if (!audioNfl || isNflAudioPlaying) return;
        audioNfl.play().then(() => {
            isNflAudioPlaying = true;
            document.getElementById('wizardAudioIcon').textContent = '⏸';
            document.getElementById('wizardAudioLabel').textContent = 'Theme: ON';
            animateEqualizer(true);
        }).catch(e => {
            // Autoplay blocked by browser policy until interaction
        });
    }

    window.toggleAudioPlayback = function() {
        if (!audioNfl) return;
        if (audioNfl.paused) {
            audioNfl.play().then(() => {
                isNflAudioPlaying = true;
                document.getElementById('wizardAudioIcon').textContent = '⏸';
                document.getElementById('wizardAudioLabel').textContent = 'Theme: ON';
                animateEqualizer(true);
            }).catch(e => console.warn(e));
        } else {
            audioNfl.pause();
            isNflAudioPlaying = false;
            document.getElementById('wizardAudioIcon').textContent = '🎵';
            document.getElementById('wizardAudioLabel').textContent = 'Theme: OFF';
            animateEqualizer(false);
        }
    };

    function animateEqualizer(active) {
        ['eq1', 'eq2', 'eq3'].forEach((id, idx) => {
            const el = document.getElementById(id);
            if (!el) return;
            if (active) el.className = `w-1 bg-amber-400 rounded-full eq-b${idx + 1}`;
            else {
                el.className = 'w-1 bg-amber-400 rounded-full';
                el.style.height = '4px';
            }
        });
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
            startNflThemeLoop();
            renderCurrentMatchup();
        } else if (step === 3) {
            setupPylCatchupView();
        } else if (step === 4) {
            renderSurvivorGrid();
        } else if (step === 5) {
            playCelebrationSound();
            renderCompletionSummary();
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
    const wizardNextBtnText = document.getElementById('wizardNextBtnText');
    const wizardTimeline = document.getElementById('wizardTimeline');
    const wizardProgressBar = document.getElementById('wizardProgressBar');
    const wizardGameStepLabel = document.getElementById('wizardGameStepLabel');
    const wizardPicksCountLabel = document.getElementById('wizardPicksCountLabel');

    const wizardAwayCard = document.getElementById('wizardAwayCard');
    const wizardHomeCard = document.getElementById('wizardHomeCard');
    const wizardAwayLogo = document.getElementById('wizardAwayLogo');
    const wizardHomeLogo = document.getElementById('wizardHomeLogo');
    const wizardAwayAbbr = document.getElementById('wizardAwayAbbr');
    const wizardHomeAbbr = document.getElementById('wizardHomeAbbr');
    const wizardAwayName = document.getElementById('wizardAwayName');
    const wizardHomeName = document.getElementById('wizardHomeName');
    const wizardAwayConf = document.getElementById('wizardAwayConf');
    const wizardHomeConf = document.getElementById('wizardHomeConf');
    const wizardAwayDivision = document.getElementById('wizardAwayDivision');
    const wizardHomeDivision = document.getElementById('wizardHomeDivision');
    const wizardAwayCheck = document.getElementById('wizardAwayCheck');
    const wizardHomeCheck = document.getElementById('wizardHomeCheck');
    const wizardAwayStripe = document.getElementById('wizardAwayStripe');
    const wizardHomeStripe = document.getElementById('wizardHomeStripe');
    const btnPickAway = document.getElementById('btnPickAway');
    const btnPickHome = document.getElementById('btnPickHome');
    const wizardKickoffText = document.getElementById('wizardKickoffText');
    const wizardStatusBadge = document.getElementById('wizardStatusBadge');
    const wizardLockNotice = document.getElementById('wizardLockNotice');
    const carouselAutoSaveMsg = document.getElementById('carouselAutoSaveMsg');

    function renderCurrentMatchup() {
        if (!wizardGames.length) return;
        const game = wizardGames[currentIndex];

        // Away Card
        wizardAwayLogo.src = game.away_logo;
        wizardAwayAbbr.textContent = game.away_team;
        wizardAwayName.textContent = game.away_name;
        wizardAwayConf.textContent = game.away_conf || 'AWAY';
        wizardAwayDivision.textContent = game.away_division;
        wizardAwayStripe.style.backgroundColor = game.away_color;

        // Home Card
        wizardHomeLogo.src = game.home_logo;
        wizardHomeAbbr.textContent = game.home_team;
        wizardHomeName.textContent = game.home_name;
        wizardHomeConf.textContent = game.home_conf || 'HOME';
        wizardHomeDivision.textContent = game.home_division;
        wizardHomeStripe.style.backgroundColor = game.home_color;

        // Matchup Info
        wizardKickoffText.textContent = game.kickoff_formatted;
        wizardStatusBadge.textContent = game.is_mnf ? 'MONDAY NIGHT FOOTBALL' : (game.status === 'final' ? 'FINAL' : 'SCHEDULED');
        
        if (game.is_locked) {
            wizardLockNotice.classList.remove('hidden');
            btnPickAway.disabled = true;
            btnPickHome.disabled = true;
        } else {
            wizardLockNotice.classList.add('hidden');
            btnPickAway.disabled = false;
            btnPickHome.disabled = false;
        }

        // Selection styling
        updateCardSelectionState(game.user_pick);

        // Navigation state
        btnWizardPrev.disabled = (currentIndex === 0);
        wizardNextBtnText.textContent = (currentIndex === wizardGames.length - 1) ? 'Tiebreaker' : 'Next Game';

        // Progress indicators
        wizardGameStepLabel.textContent = `Game ${currentIndex + 1} of ${wizardGames.length}`;
        updatePickCounters();
        renderTimeline();
    }

    function updateCardSelectionState(userPick) {
        const game = wizardGames[currentIndex];
        
        // Reset styles
        [wizardAwayCard, wizardHomeCard].forEach(card => {
            card.classList.remove('border-amber-400', 'bg-amber-950/40', 'scale-[1.01]', 'opacity-40');
        });
        wizardAwayCheck.classList.add('scale-0', 'opacity-0');
        wizardHomeCheck.classList.add('scale-0', 'opacity-0');

        const baseBtn = 'py-2 px-3 sm:px-4 md:py-3 rounded-xl font-black text-xs sm:text-sm font-mono tracking-wide uppercase transition-all shadow-md flex items-center justify-center gap-1.5';
        const unselectedClass = `${baseBtn} bg-[#0B1626] border border-[#243247] text-slate-200 group-hover:border-amber-400/80 group-hover:text-white`;
        const selectedClass = `${baseBtn} bg-amber-500 text-slate-950 border border-amber-400 shadow-md shadow-amber-500/20`;

        btnPickAway.className = unselectedClass;
        btnPickHome.className = unselectedClass;
        btnPickAway.textContent = `Select ${game.away_nick}`;
        btnPickHome.textContent = `Select ${game.home_nick}`;

        if (userPick === game.away_team) {
            wizardAwayCard.classList.add('border-amber-400', 'bg-amber-950/40', 'scale-[1.01]');
            wizardHomeCard.classList.add('opacity-40');
            wizardAwayCheck.classList.remove('scale-0', 'opacity-0');
            btnPickAway.className = selectedClass;
            btnPickAway.textContent = `✓ ${game.away_nick}`;
        } else if (userPick === game.home_team) {
            wizardHomeCard.classList.add('border-amber-400', 'bg-amber-950/40', 'scale-[1.01]');
            wizardAwayCard.classList.add('opacity-40');
            wizardHomeCheck.classList.remove('scale-0', 'opacity-0');
            btnPickHome.className = selectedClass;
            btnPickHome.textContent = `✓ ${game.home_nick}`;
        }
    }

    function updatePickCounters() {
        const pickedCount = wizardGames.filter(g => g.user_pick !== null).length;
        wizardPicksCountLabel.textContent = `${pickedCount} of ${wizardGames.length} Picked`;
        const pct = (pickedCount / wizardGames.length) * 100;
        wizardProgressBar.style.width = `${pct}%`;
    }

    function renderTimeline() {
        wizardTimeline.innerHTML = '';
        wizardGames.forEach((g, idx) => {
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.title = `Game ${idx + 1}: ${g.away_team} @ ${g.home_team}`;
            const isCurrent = (idx === currentIndex);
            const isPicked = (g.user_pick !== null);

            dot.className = `w-4 h-4 rounded-full transition-all flex items-center justify-center text-[8px] font-mono font-bold ${
                isCurrent ? 'ring-2 ring-amber-400 bg-amber-400 text-slate-950 scale-125 z-10' :
                (isPicked ? 'bg-emerald-500 text-slate-950 hover:bg-emerald-400' : 'bg-slate-700 hover:bg-slate-500 text-slate-300')
            }`;
            dot.textContent = idx + 1;
            dot.onclick = () => {
                currentIndex = idx;
                renderCurrentMatchup();
            };
            wizardTimeline.appendChild(dot);
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

        // Autosave to server
        carouselAutoSaveMsg.textContent = `✓ ${team} Saved`;
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

        // Smooth Auto-Advance
        setTimeout(() => {
            if (currentIndex < wizardGames.length - 1) {
                currentIndex++;
                playAdvanceSound();
                renderCurrentMatchup();
            } else {
                // All games browsed -> proceed to Tiebreaker
                goToState(2);
            }
        }, 320);
    }

    wizardAwayCard.onclick = () => {
        const game = wizardGames[currentIndex];
        makePick(game.away_team);
    };
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
    // 5. State 2: Tiebreaker Input Logic
    // -----------------------------------------------------------------
    const tbInput = document.getElementById('wizardTiebreakerInput');
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

    window.spinTiebreakerRandom = function() {
        const target = Math.floor(Math.random() * (54 - 34 + 1)) + 34;
        let count = 0;
        const spinTimer = setInterval(() => {
            tbInput.value = Math.floor(Math.random() * (54 - 34 + 1)) + 34;
            count++;
            if (count >= 12) {
                clearInterval(spinTimer);
                setTiebreakerVal(target);
            }
        }, 45);
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

    // Build square slides: Team logos only (centered, no text). Sq 4 = Spin Again, Sq 8 = You Pick
    const squareSlides = {};
    for (let i = 1; i <= 18; i++) {
        squareSlides[i] = [
            { type: 'team', team: allPylTeams[(i * 2) % allPylTeams.length] },
            { type: (i === 4 ? 'spin_again' : (i === 8 ? 'you_pick' : 'team')), team: allPylTeams[(i * 3 + 1) % allPylTeams.length] },
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

        if (slide.type === 'spin_again') {
            content.innerHTML = `
                <div class="text-center font-pyl text-[8px] sm:text-[9px] text-amber-400 leading-tight">
                    SPIN<br>AGAIN
                </div>
            `;
        } else if (slide.type === 'you_pick') {
            content.innerHTML = `
                <div class="text-center font-pyl text-[8px] sm:text-[9px] text-emerald-400 leading-tight">
                    YOU<br>PICK!
                </div>
            `;
        } else {
            // Team logo ONLY, centered, NO text labels!
            content.innerHTML = `
                <img src="${slide.team.logo}" alt="${slide.team.name}" class="w-8 h-8 sm:w-11 sm:h-11 object-contain drop-shadow">
            `;
        }
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
                }, 150);
            }
        }
    }
    setInterval(cyclePylSpaces, 2500);

    // Initial render of 18 squares
    for (let i = 1; i <= 18; i++) {
        renderPylSquare(i, squareSlides[i][0]);
    }

    function setupPylCatchupView() {
        const titleEl = document.getElementById('pylCatchupTitle');
        const countEl = document.getElementById('pylHandicapCounter');
        const ledgerEl = document.getElementById('pylBurnedLedger');
        
        const missedCount = missedSurvivorWeeks.length;
        if (titleEl) titleEl.textContent = `Week ${weekNumber} Catch-Up: ${missedCount} Team${missedCount > 1 ? 's' : ''} to Burn`;
        if (countEl) countEl.textContent = `0 of ${missedCount} Burned`;
        if (ledgerEl) ledgerEl.textContent = usedSurvivorTeams.length ? usedSurvivorTeams.join(', ') : 'None yet';
    }

    window.handlePylBuzzer = function() {
        const buzzerLabel = document.getElementById('pylBuzzerLabel');
        const mainMsg = document.getElementById('pylMainMsg');
        const subMsg = document.getElementById('pylSubMsg');

        if (!isPylSpinning) {
            isPylSpinning = true;
            buzzerLabel.textContent = 'STOP!';
            mainMsg.textContent = 'SPINNING...';
            subMsg.textContent = 'Hit the red buzzer to freeze the board!';

            if (audioPyl) {
                audioPyl.currentTime = 0;
                audioPyl.play().catch(e => console.warn(e));
            }

            const pIdx = Math.floor(Math.random() * LARSON_PATTERNS.length);
            currentLarsonPattern = LARSON_PATTERNS[pIdx];
            document.getElementById('pylPatternTxt').textContent = `LARSON #${pIdx + 1}`;
            larsonStep = 0;

            pylSpinInterval = setInterval(() => {
                larsonStep = (larsonStep + 1) % currentLarsonPattern.length;
                const sqNum = currentLarsonPattern[larsonStep];
                document.querySelectorAll('.pyl-square').forEach(sq => sq.classList.remove('is-lit'));
                const litSq = document.getElementById('pyl-sq-' + sqNum);
                if (litSq) litSq.classList.add('is-lit');
            }, 110);

        } else {
            clearInterval(pylSpinInterval);
            isPylSpinning = false;
            buzzerLabel.textContent = 'SPIN AGAIN';

            if (audioPyl) audioPyl.pause();

            const hitSqNum = currentLarsonPattern[larsonStep];
            const hitSlide = squareSlides[hitSqNum][activeSlideIdx];
            const hitSqEl = document.getElementById('pyl-sq-' + hitSqNum);

            if (hitSqEl) {
                hitSqEl.classList.add('flash-freeze');
                setTimeout(() => hitSqEl.classList.remove('flash-freeze'), 800);
            }

            if (hitSlide.type === 'spin_again') {
                mainMsg.textContent = '🔄 SPIN AGAIN!';
                subMsg.textContent = 'Lucky break! No team burned. Take another spin!';
            } else if (hitSlide.type === 'you_pick') {
                mainMsg.textContent = '🎯 YOU PICK!';
                subMsg.textContent = 'Select which team on the board to eliminate!';
                openYouPickModal();
            } else {
                // Team hit: The Whammy animation ALWAYS plays when a team is burned!
                const burnedTeam = hitSlide.team;
                mainMsg.textContent = `BURNED: ${burnedTeam.name}!`;
                subMsg.textContent = `Whammy stole the ${burnedTeam.name}! Team is eliminated.`;
                
                triggerWhammyVideoOverlay();
                burnSurvivorHandicap(burnedTeam.abbr || burnedTeam.name);
            }
        }
    };

    function triggerWhammyVideoOverlay() {
        const canvas = document.getElementById('whammyCanvas');
        const video = document.getElementById('whammyVideoPlayer');
        if (!canvas || !video) return;
        const ctx = canvas.getContext('2d');
        canvas.classList.remove('hidden');

        video.src = '/media/transparent/1-running-mallet.webm';
        video.currentTime = 0;
        video.play().then(() => {
            function renderFrame() {
                if (!video.paused && !video.ended) {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    requestAnimationFrame(renderFrame);
                } else {
                    canvas.classList.add('hidden');
                }
            }
            renderFrame();
        }).catch(e => {
            console.warn('Whammy video overlay error:', e);
            canvas.classList.add('hidden');
        });
    }

    function burnSurvivorHandicap(teamAbbr) {
        fetch('/survivor/burn-handicap', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                season_year: seasonYear,
                current_week: weekNumber,
                burned_team: teamAbbr,
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (!usedSurvivorTeams.includes(teamAbbr)) {
                    usedSurvivorTeams.push(teamAbbr);
                }
                missedSurvivorWeeks = data.remaining_missed_weeks || [];
                document.getElementById('pylBurnedLedger').textContent = usedSurvivorTeams.join(', ');
                
                const remaining = missedSurvivorWeeks.length;
                if (remaining === 0) {
                    needsSurvivorCatchup = false;
                    document.getElementById('pylBuzzerLabel').textContent = 'DONE!';
                    setTimeout(() => {
                        goToState(4);
                    }, 1200);
                }
            }
        })
        .catch(e => console.warn('Error burning handicap:', e));
    }

    function openYouPickModal() {
        const modal = document.getElementById('youPickModal');
        const grid = document.getElementById('youPickGrid');
        grid.innerHTML = '';

        const seen = new Set();
        for (let i = 1; i <= 18; i++) {
            const item = squareSlides[i][activeSlideIdx];
            if (item.type === 'team' && !seen.has(item.team.name)) {
                seen.add(item.team.name);
                const btn = document.createElement('button');
                btn.className = 'p-3 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-center flex flex-col items-center justify-center transition active:scale-95';
                btn.innerHTML = `<img src="${item.team.logo}" class="w-8 h-8 object-contain mb-1"><span class="text-xs font-mono font-bold text-white">${item.team.name}</span>`;
                btn.onclick = () => {
                    modal.classList.add('hidden');
                    triggerWhammyVideoOverlay();
                    burnSurvivorHandicap(item.team.abbr || item.team.name);
                };
                grid.appendChild(btn);
            }
        }
        modal.classList.remove('hidden');
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
                card.className = `p-3.5 rounded-xl border transition-all relative overflow-hidden select-none flex items-center justify-between ${
                    isBurned ? 'opacity-35 grayscale bg-slate-950 border-slate-800 cursor-not-allowed' :
                    (isPicked ? 'border-emerald-400 bg-emerald-950/40 shadow-[0_0_20px_rgba(16,185,129,0.3)] cursor-pointer' : 'border-slate-800 bg-slate-900/90 hover:border-emerald-500/50 cursor-pointer')
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
    if (btnLaunchWizard) {
        btnLaunchWizard.onclick = () => {
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            goToState(1);
        };
    }
    if (btnLaunchWizardHero) {
        btnLaunchWizardHero.onclick = () => {
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            goToState(1);
        };
    }

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

    document.addEventListener('keydown', (e) => {
        if (modal.classList.contains('hidden')) return;
        if (['INPUT', 'TEXTAREA'].includes(e.target.tagName)) return;

        if (e.key === 'Escape') {
            if (!shortcutsModal.classList.contains('hidden')) {
                shortcutsModal.classList.add('hidden');
            } else {
                exitWizardToStandardView();
            }
        } else if (currentState === 1) {
            const game = wizardGames[currentIndex];
            if (['1', 'a', 'A', 'ArrowUp'].includes(e.key)) {
                makePick(game.away_team);
            } else if (['2', 'h', 'H', 'ArrowDown'].includes(e.key)) {
                makePick(game.home_team);
            } else if (['ArrowRight', ' ', 'd', 'D'].includes(e.key)) {
                btnWizardNext.click();
            } else if (['ArrowLeft', 'w', 'W'].includes(e.key)) {
                btnWizardPrev.click();
            }
        }
    });

    // Boot into initial state
    renderCurrentMatchup();
});
</script>
