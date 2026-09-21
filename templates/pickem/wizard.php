<?php

declare(strict_types=1);

use WallyFootball\Support\TeamData;

/**
 * Weekly Pick Wizard Component
 * Full-screen, landscape-focused pick-by-pick flow.
 *
 * Variables expected:
 *   $games (array)
 *   $userPicks (array)
 *   $season (int)
 *   $week (int)
 *   $entry (array|null)
 *   $tiebreakerGame (array|null)
 *   $isWeekLocked (bool)
 */

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
$isAutoLaunch = (isset($_GET['mode']) && $_GET['mode'] === 'wizard') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/pickem/wizard');
?>

<!-- ================================================================= -->
<!-- FULL-SCREEN WEEKLY PICK WIZARD MODAL                              -->
<!-- ================================================================= -->
<div id="pickWizardModal" 
     class="fixed inset-0 z-50 flex flex-col bg-[#060c18]/95 backdrop-blur-2xl text-slate-100 select-none overflow-hidden <?= $isAutoLaunch ? '' : 'hidden' ?>"
     role="dialog" 
     aria-modal="true" 
     aria-label="Weekly Pick Wizard">

    <!-- Background Turf Field Glow Effect -->
    <div class="pointer-events-none absolute inset-0 opacity-15 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-emerald-600/30 via-slate-900/40 to-transparent"></div>

    <!-- TOP CONTROL BAR -->
    <header class="relative z-10 border-b border-[#243247]/80 bg-[#0B1626]/90 px-4 py-3 sm:px-6">
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

            <!-- Center: Live Progress & Completed Counter -->
            <div class="flex flex-col items-center flex-1 max-w-md mx-2">
                <div class="flex items-center justify-between w-full text-[11px] font-mono font-bold mb-1">
                    <span id="wizardGameStepLabel" class="text-[#EAB308]">Game 1 of <?= count($wizardGames) ?></span>
                    <span id="wizardPicksCountLabel" class="text-slate-400">0 of <?= count($wizardGames) ?> Picked</span>
                </div>
                <!-- Progress Track -->
                <div class="w-full h-2 rounded-full bg-[#162235] border border-[#243247] overflow-hidden p-0.5">
                    <div id="wizardProgressBar" 
                         class="h-full rounded-full bg-gradient-to-r from-emerald-500 via-amber-400 to-[#EAB308] transition-all duration-300 ease-out" 
                         style="width: 0%;"></div>
                </div>
            </div>

            <!-- Right: Audio SFX Toggle & Help Shortcuts -->
            <div class="flex items-center gap-2">
                <button type="button" 
                        id="btnWizardAudioToggle"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-[#243247] bg-[#162235] hover:bg-[#1e2e48] text-xs font-bold text-slate-300 hover:text-white transition"
                        title="Toggle Sound Effects">
                    <span id="wizardAudioIcon">🔇</span>
                    <span id="wizardAudioLabel" class="hidden sm:inline text-[11px]">Sound Off</span>
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

    <!-- MAIN VIEWPORT: FOCUSED LANDSCAPE MATCHUP -->
    <main class="relative z-10 flex-1 flex items-center justify-center p-3 sm:p-6 lg:p-8 overflow-y-auto">
        <div class="w-full max-w-6xl mx-auto flex flex-col items-center justify-center min-h-[460px]">

            <!-- Matchup Card Container with smooth slide transitions -->
            <div id="wizardCardContainer" 
                 class="w-full transition-all duration-300 transform opacity-100 scale-100">
                
                <!-- Landscape 3-Column Split -->
                <div class="grid grid-cols-1 md:grid-cols-[1fr_auto_1fr] items-stretch gap-4 sm:gap-6 lg:gap-8 w-full">

                    <!-- ============================================== -->
                    <!-- AWAY TEAM CARD (Left)                          -->
                    <!-- ============================================== -->
                    <div id="wizardAwayCard" 
                         class="wizard-team-card relative group flex flex-col items-center justify-between p-6 sm:p-8 rounded-2xl border-2 border-[#243247] bg-gradient-to-b from-[#162235] to-[#0d1624] cursor-pointer select-none transition-all duration-200 hover:-translate-y-1 hover:shadow-2xl hover:border-slate-500 overflow-hidden shadow-xl"
                         data-team-type="away">
                        
                        <!-- Top Accent Stripe -->
                        <div id="wizardAwayStripe" class="absolute top-0 left-0 right-0 h-2 bg-slate-600 transition-colors"></div>

                        <!-- Pick Check Badge -->
                        <div id="wizardAwayCheck" 
                             class="absolute top-4 right-4 w-9 h-9 rounded-full bg-[#EAB308] text-[#0B1626] flex items-center justify-center shadow-lg transition-all duration-200 scale-0 opacity-0">
                            <svg class="w-5 h-5 stroke-[3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>

                        <!-- Conference / Location Tag -->
                        <div class="w-full flex items-center justify-between mb-3 text-xs font-mono text-slate-400">
                            <span id="wizardAwayConf" class="px-2 py-0.5 rounded bg-black/40 border border-slate-700/60 font-semibold uppercase tracking-wider text-[10px]">AWAY</span>
                            <span id="wizardAwayDivision" class="text-[11px] font-medium text-slate-400"></span>
                        </div>

                        <!-- Large Official Logo -->
                        <div class="my-4 sm:my-6 h-28 sm:h-36 flex items-center justify-center transform transition-transform duration-300 group-hover:scale-110">
                            <img id="wizardAwayLogo" 
                                 src="" 
                                 alt="Away Team Logo" 
                                 class="max-h-full max-w-[130px] sm:max-w-[170px] object-contain filter drop-shadow-2xl">
                        </div>

                        <!-- Team Identification -->
                        <div class="text-center w-full mt-2">
                            <span id="wizardAwayAbbr" class="block text-2xl sm:text-3xl font-black font-mono tracking-tight text-white mb-0.5"></span>
                            <span id="wizardAwayName" class="block text-sm sm:text-base font-bold text-slate-300"></span>
                            <span id="wizardAwayScore" class="hidden text-xl font-black font-mono text-emerald-400 mt-1"></span>
                        </div>

                        <!-- Action Button -->
                        <div class="w-full mt-6">
                            <button type="button" 
                                    id="btnPickAway"
                                    class="w-full py-3 px-4 rounded-xl font-black text-sm font-mono tracking-wide uppercase transition-all shadow-md flex items-center justify-center gap-2 bg-[#0B1626] border border-[#243247] text-slate-200 group-hover:border-amber-400/80 group-hover:text-white">
                                <span>Select Away</span>
                            </button>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- CENTER COLUMN: VS & MATCHUP DETAILS            -->
                    <!-- ============================================== -->
                    <div class="flex flex-col items-center justify-center py-2 sm:py-6 px-3 text-center max-w-[280px] mx-auto w-full">
                        
                        <!-- Broadcast VS Medallion -->
                        <div class="relative mb-4">
                            <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-gradient-to-b from-[#1a2942] to-[#0B1626] border-2 border-[#243247] shadow-2xl flex items-center justify-center">
                                <span class="text-sm sm:text-base font-black font-mono tracking-wider text-[#EAB308] drop-shadow">VS</span>
                            </div>
                        </div>

                        <!-- Kickoff Date & Time -->
                        <div class="mb-3">
                            <div id="wizardKickoffText" class="text-xs sm:text-sm font-bold text-slate-200 leading-snug">
                                Kickoff Time
                            </div>
                            <div id="wizardKickoffRelative" class="text-[11px] font-mono text-slate-400 mt-0.5">
                                Loading schedule...
                            </div>
                        </div>

                        <!-- Lockout Countdown / Status Pill -->
                        <div id="wizardLockPill" class="mb-4 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-bold border transition">
                            <span id="wizardLockIcon">⏱️</span>
                            <span id="wizardLockLabel">Open for Picks</span>
                        </div>

                        <!-- Tiebreaker Prediction Box (Only for Designated MNF Game) -->
                        <div id="wizardTiebreakerBox" class="hidden w-full p-4 rounded-xl border border-amber-500/40 bg-[#162235]/90 shadow-lg text-left mt-2">
                            <div class="flex items-center gap-1.5 mb-1.5">
                                <span class="text-amber-400 text-xs">🎯</span>
                                <span class="text-[10px] font-black font-mono uppercase tracking-wider text-[#EAB308]">Tiebreaker Prediction</span>
                            </div>
                            <label for="wizardMnfPointsInput" class="text-[11px] text-slate-300 font-medium block mb-2 leading-tight">
                                Predict Total Monday Night Points:
                            </label>
                            <div class="relative flex items-center">
                                <input type="number" 
                                       id="wizardMnfPointsInput" 
                                       name="wizard_mnf_points"
                                       min="0" 
                                       max="150" 
                                       placeholder="e.g. 45"
                                       class="w-full px-3 py-2 text-center text-lg font-black font-mono rounded-lg bg-[#0B1626] border border-[#243247] focus:border-[#EAB308] focus:ring-2 focus:ring-[#EAB308]/20 text-white placeholder-slate-600 outline-none transition">
                                <span class="absolute right-3 text-xs font-mono font-bold text-slate-500 pointer-events-none">PTS</span>
                            </div>
                            <div id="wizardMnfSaveNotice" class="text-[10px] font-mono text-emerald-400 text-center mt-1.5 h-3 opacity-0 transition-opacity">
                                Auto-saved
                            </div>
                        </div>

                    </div>

                    <!-- ============================================== -->
                    <!-- HOME TEAM CARD (Right)                         -->
                    <!-- ============================================== -->
                    <div id="wizardHomeCard" 
                         class="wizard-team-card relative group flex flex-col items-center justify-between p-6 sm:p-8 rounded-2xl border-2 border-[#243247] bg-gradient-to-b from-[#162235] to-[#0d1624] cursor-pointer select-none transition-all duration-200 hover:-translate-y-1 hover:shadow-2xl hover:border-slate-500 overflow-hidden shadow-xl"
                         data-team-type="home">
                        
                        <!-- Top Accent Stripe -->
                        <div id="wizardHomeStripe" class="absolute top-0 left-0 right-0 h-2 bg-slate-600 transition-colors"></div>

                        <!-- Pick Check Badge -->
                        <div id="wizardHomeCheck" 
                             class="absolute top-4 right-4 w-9 h-9 rounded-full bg-[#EAB308] text-[#0B1626] flex items-center justify-center shadow-lg transition-all duration-200 scale-0 opacity-0">
                            <svg class="w-5 h-5 stroke-[3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>

                        <!-- Conference / Location Tag -->
                        <div class="w-full flex items-center justify-between mb-3 text-xs font-mono text-slate-400">
                            <span id="wizardHomeConf" class="px-2 py-0.5 rounded bg-black/40 border border-slate-700/60 font-semibold uppercase tracking-wider text-[10px]">HOME</span>
                            <span id="wizardHomeDivision" class="text-[11px] font-medium text-slate-400"></span>
                        </div>

                        <!-- Large Official Logo -->
                        <div class="my-4 sm:my-6 h-28 sm:h-36 flex items-center justify-center transform transition-transform duration-300 group-hover:scale-110">
                            <img id="wizardHomeLogo" 
                                 src="" 
                                 alt="Home Team Logo" 
                                 class="max-h-full max-w-[130px] sm:max-w-[170px] object-contain filter drop-shadow-2xl">
                        </div>

                        <!-- Team Identification -->
                        <div class="text-center w-full mt-2">
                            <span id="wizardHomeAbbr" class="block text-2xl sm:text-3xl font-black font-mono tracking-tight text-white mb-0.5"></span>
                            <span id="wizardHomeName" class="block text-sm sm:text-base font-bold text-slate-300"></span>
                            <span id="wizardHomeScore" class="hidden text-xl font-black font-mono text-emerald-400 mt-1"></span>
                        </div>

                        <!-- Action Button -->
                        <div class="w-full mt-6">
                            <button type="button" 
                                    id="btnPickHome"
                                    class="w-full py-3 px-4 rounded-xl font-black text-sm font-mono tracking-wide uppercase transition-all shadow-md flex items-center justify-center gap-2 bg-[#0B1626] border border-[#243247] text-slate-200 group-hover:border-amber-400/80 group-hover:text-white">
                                <span>Select Home</span>
                            </button>
                        </div>
                    </div>

                </div>

            </div>

            <!-- ======================================================= -->
            <!-- COMPLETION / CELEBRATION VIEW (Hidden until all picked) -->
            <!-- ======================================================= -->
            <div id="wizardCompletionView" class="hidden text-center max-w-xl mx-auto p-6 sm:p-10 rounded-2xl border border-amber-500/40 bg-gradient-to-b from-[#162235] to-[#0B1626] shadow-2xl animate-fade-in">
                <div class="text-5xl sm:text-6xl mb-4">🏆</div>
                <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight mb-2">
                    Week <?= $week ?> Picks Complete!
                </h2>
                <p class="text-sm sm:text-base text-slate-300 mb-6">
                    Every game has been picked and immediately auto-saved to your profile. You're locked and loaded for kickoff!
                </p>

                <div class="grid grid-cols-2 gap-3 max-w-sm mx-auto mb-8 text-left">
                    <div class="p-3 rounded-lg bg-[#0B1626] border border-[#243247]">
                        <span class="text-[10px] font-mono text-slate-400 uppercase block mb-1">Total Picks</span>
                        <span id="wizardCompleteTotal" class="text-xl font-black font-mono text-emerald-400">16 / 16</span>
                    </div>
                    <div class="p-3 rounded-lg bg-[#0B1626] border border-[#243247]">
                        <span class="text-[10px] font-mono text-slate-400 uppercase block mb-1">MNF Tiebreaker</span>
                        <span id="wizardCompleteTb" class="text-xl font-black font-mono text-amber-400">--</span>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                    <button type="button" 
                            id="btnWizardFinishReview"
                            class="w-full sm:w-auto px-6 py-3.5 rounded-xl font-black text-sm bg-gradient-to-r from-amber-500 to-yellow-400 hover:from-amber-400 hover:to-yellow-300 text-slate-950 shadow-lg shadow-amber-500/20 transition transform hover:-translate-y-0.5">
                        Review Picks in Standard Table &rarr;
                    </button>
                    <button type="button" 
                            id="btnWizardRestart"
                            class="w-full sm:w-auto px-5 py-3.5 rounded-xl font-bold text-sm bg-[#162235] hover:bg-[#1e2e48] border border-[#243247] text-slate-300 hover:text-white transition">
                        Revisit Matchups
                    </button>
                </div>
            </div>

        </div>
    </main>

    <!-- BOTTOM DOCK: NAVIGATION CONTROLS & TIMELINE -->
    <footer class="relative z-10 border-t border-[#243247]/80 bg-[#0B1626]/90 px-4 py-3 sm:px-6">
        <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3">
            
            <!-- Previous Button -->
            <button type="button" 
                    id="btnWizardPrev"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-[#243247] bg-[#162235] hover:bg-[#1e2e48] text-slate-200 font-bold text-xs font-mono transition disabled:opacity-30 disabled:pointer-events-none">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
                <span>Previous Game</span>
            </button>

            <!-- Mini Game Timeline Dots / Navigation Pills -->
            <div id="wizardTimeline" class="flex items-center gap-1 sm:gap-1.5 overflow-x-auto py-1 max-w-full px-2">
                <!-- Dynamically populated pills -->
            </div>

            <!-- Next / Skip Button -->
            <button type="button" 
                    id="btnWizardNext"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-amber-500/40 bg-[#162235] hover:bg-amber-500 hover:text-slate-950 text-amber-400 font-black text-xs font-mono transition shadow-sm">
                <span id="wizardNextBtnText">Next Game</span>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>
    </footer>

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
                    <span class="font-mono px-2 py-0.5 rounded bg-[#0B1626] border border-[#243247] text-slate-300 font-bold">→ or Space or D</span>
                </div>
                <div class="flex items-center justify-between py-1 border-b border-[#243247]/50">
                    <span class="text-slate-300">Previous Game</span>
                    <span class="font-mono px-2 py-0.5 rounded bg-[#0B1626] border border-[#243247] text-slate-300 font-bold">← or W</span>
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
<!-- WIZARD INTERACTIVE ENGINE & SOUND SYNTHESIZER                     -->
<!-- ================================================================= -->
<script>
window.addEventListener('DOMContentLoaded', () => {
    // 1. Data Bootstrap
    const wizardGames = <?= json_encode($wizardGames, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const seasonYear = <?= (int) $season ?>;
    const weekNumber = <?= (int) $week ?>;
    const tbGameId = <?= $tbGameId ? (int) $tbGameId : 'null' ?>;
    const tbIsLocked = <?= $tbIsLocked ? 'true' : 'false' ?>;
    let mnfPredictedPoints = <?= $tbCurrentPoints !== null ? (int) $tbCurrentPoints : 'null' ?>;

    let currentIndex = 0;
    let isTransitioning = false;

    // 2. DOM Elements
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

    const wizardCardContainer = document.getElementById('wizardCardContainer');
    const wizardCompletionView = document.getElementById('wizardCompletionView');
    const btnWizardFinishReview = document.getElementById('btnWizardFinishReview');
    const btnWizardRestart = document.getElementById('btnWizardRestart');

    // Away Team DOM
    const awayCard = document.getElementById('wizardAwayCard');
    const awayStripe = document.getElementById('wizardAwayStripe');
    const awayCheck = document.getElementById('wizardAwayCheck');
    const awayConf = document.getElementById('wizardAwayConf');
    const awayDivision = document.getElementById('wizardAwayDivision');
    const awayLogo = document.getElementById('wizardAwayLogo');
    const awayAbbr = document.getElementById('wizardAwayAbbr');
    const awayName = document.getElementById('wizardAwayName');
    const awayScore = document.getElementById('wizardAwayScore');
    const btnPickAway = document.getElementById('btnPickAway');

    // Home Team DOM
    const homeCard = document.getElementById('wizardHomeCard');
    const homeStripe = document.getElementById('wizardHomeStripe');
    const homeCheck = document.getElementById('wizardHomeCheck');
    const homeConf = document.getElementById('wizardHomeConf');
    const homeDivision = document.getElementById('wizardHomeDivision');
    const homeLogo = document.getElementById('wizardHomeLogo');
    const homeAbbr = document.getElementById('wizardHomeAbbr');
    const homeName = document.getElementById('wizardHomeName');
    const homeScore = document.getElementById('wizardHomeScore');
    const btnPickHome = document.getElementById('btnPickHome');

    // Center Matchup Details
    const kickoffText = document.getElementById('wizardKickoffText');
    const kickoffRelative = document.getElementById('wizardKickoffRelative');
    const lockPill = document.getElementById('wizardLockPill');
    const lockIcon = document.getElementById('wizardLockIcon');
    const lockLabel = document.getElementById('wizardLockLabel');

    // Tiebreaker DOM
    const tiebreakerBox = document.getElementById('wizardTiebreakerBox');
    const mnfPointsInput = document.getElementById('wizardMnfPointsInput');
    const mnfSaveNotice = document.getElementById('wizardMnfSaveNotice');

    // Audio Elements
    const btnWizardAudioToggle = document.getElementById('btnWizardAudioToggle');
    const wizardAudioIcon = document.getElementById('wizardAudioIcon');
    const wizardAudioLabel = document.getElementById('wizardAudioLabel');
    let isAudioEnabled = localStorage.getItem('wizard_sound_enabled') === '1';

    // Shortcuts Modal
    const btnWizardShortcuts = document.getElementById('btnWizardShortcuts');
    const wizardShortcutsModal = document.getElementById('wizardShortcutsModal');
    const btnCloseShortcutsModal = document.getElementById('btnCloseShortcutsModal');

    // 3. Web Audio API Synthesizer (100% self-contained sound effects)
    let audioCtx = null;
    function getAudioContext() {
        if (!audioCtx) {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) {
                audioCtx = new AudioContext();
            }
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        return audioCtx;
    }

    function playPickSound() {
        if (!isAudioEnabled) return;
        try {
            const ctx = getAudioContext();
            if (!ctx) return;
            const now = ctx.currentTime;
            
            // Dual chime oscillator for clean stadium ping
            const osc1 = ctx.createOscillator();
            const osc2 = ctx.createOscillator();
            const gain = ctx.createGain();

            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(587.33, now); // D5
            osc1.frequency.exponentialRampToValueAtTime(880, now + 0.12); // A5

            osc2.type = 'triangle';
            osc2.frequency.setValueAtTime(880, now);
            osc2.frequency.exponentialRampToValueAtTime(1174.66, now + 0.12); // D6

            gain.gain.setValueAtTime(0.001, now);
            gain.gain.linearRampToValueAtTime(0.18, now + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.28);

            osc1.connect(gain);
            osc2.connect(gain);
            gain.connect(ctx.destination);

            osc1.start(now);
            osc2.start(now);
            osc1.stop(now + 0.3);
            osc2.stop(now + 0.3);
        } catch (e) {
            // Audio context failure gracefully ignored
        }
    }

    function playAdvanceSound() {
        if (!isAudioEnabled) return;
        try {
            const ctx = getAudioContext();
            if (!ctx) return;
            const now = ctx.currentTime;

            const osc = ctx.createOscillator();
            const filter = ctx.createBiquadFilter();
            const gain = ctx.createGain();

            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(120, now);
            osc.frequency.exponentialRampToValueAtTime(40, now + 0.15);

            filter.type = 'lowpass';
            filter.frequency.setValueAtTime(800, now);
            filter.frequency.exponentialRampToValueAtTime(100, now + 0.15);

            gain.gain.setValueAtTime(0.06, now);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.16);

            osc.connect(filter);
            filter.connect(gain);
            gain.connect(ctx.destination);

            osc.start(now);
            osc.stop(now + 0.18);
        } catch (e) {}
    }

    function playCelebrationSound() {
        if (!isAudioEnabled) return;
        try {
            const ctx = getAudioContext();
            if (!ctx) return;
            const now = ctx.currentTime;

            // Arpeggio chords
            const notes = [523.25, 659.25, 783.99, 1046.50]; // C5, E5, G5, C6
            notes.forEach((freq, idx) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                const start = now + (idx * 0.08);

                osc.type = 'triangle';
                osc.frequency.setValueAtTime(freq, start);

                gain.gain.setValueAtTime(0.001, start);
                gain.gain.linearRampToValueAtTime(0.15, start + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.4);

                osc.connect(gain);
                gain.connect(ctx.destination);

                osc.start(start);
                osc.stop(start + 0.45);
            });
        } catch (e) {}
    }

    function updateAudioUI() {
        if (isAudioEnabled) {
            wizardAudioIcon.textContent = '🔊';
            wizardAudioLabel.textContent = 'Sound ON';
            btnWizardAudioToggle.classList.add('text-amber-400', 'border-amber-500/50');
            btnWizardAudioToggle.classList.remove('text-slate-300', 'border-[#243247]');
        } else {
            wizardAudioIcon.textContent = '🔇';
            wizardAudioLabel.textContent = 'Sound OFF';
            btnWizardAudioToggle.classList.remove('text-amber-400', 'border-amber-500/50');
            btnWizardAudioToggle.classList.add('text-slate-300', 'border-[#243247]');
        }
    }
    updateAudioUI();

    btnWizardAudioToggle.addEventListener('click', () => {
        isAudioEnabled = !isAudioEnabled;
        localStorage.setItem('wizard_sound_enabled', isAudioEnabled ? '1' : '0');
        updateAudioUI();
        if (isAudioEnabled) {
            playPickSound();
        }
    });

    // 4. Open / Close Wizard Flow
    function openWizard() {
        if (!modal) return;
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        
        // Find first unpicked game if available
        let firstUnpicked = wizardGames.findIndex(g => !g.user_pick && !g.is_locked);
        currentIndex = (firstUnpicked !== -1) ? firstUnpicked : 0;

        renderCurrentGame();
        renderTimeline();
        updateProgressCounters();
    }

    function closeWizard() {
        if (!modal) return;
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');

        // If URL had mode=wizard, clean it up without reload
        if (window.location.search.includes('mode=wizard')) {
            const cleanUrl = window.location.pathname + window.location.search.replace(/[?&]mode=wizard/, '').replace(/^[?&]/, '?');
            window.history.replaceState({}, '', cleanUrl || window.location.pathname);
        }
    }

    if (btnLaunchWizard) btnLaunchWizard.addEventListener('click', openWizard);
    if (btnLaunchWizardHero) btnLaunchWizardHero.addEventListener('click', openWizard);
    if (btnWizardExit) btnWizardExit.addEventListener('click', closeWizard);
    if (btnWizardFinishReview) btnWizardFinishReview.addEventListener('click', closeWizard);

    // 5. Render Timeline Dots / Pills
    function renderTimeline() {
        if (!wizardTimeline) return;
        wizardTimeline.innerHTML = '';

        wizardGames.forEach((g, idx) => {
            const pill = document.createElement('button');
            pill.type = 'button';
            pill.className = 'w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center font-mono text-[11px] font-bold transition-all';
            
            const isPicked = Boolean(g.user_pick);
            const isCurrent = (idx === currentIndex);
            const isLocked = Boolean(g.is_locked);

            if (isCurrent) {
                pill.classList.add('border-2', 'border-[#EAB308]', 'bg-[#EAB308]', 'text-[#0B1626]', 'scale-110', 'shadow-md');
                pill.textContent = (idx + 1);
            } else if (isPicked) {
                pill.classList.add('bg-emerald-600/30', 'border', 'border-emerald-500/60', 'text-emerald-300', 'hover:bg-emerald-600/50');
                pill.textContent = '✓';
            } else if (isLocked) {
                pill.classList.add('bg-slate-800/60', 'border', 'border-slate-700', 'text-slate-500');
                pill.textContent = '🔒';
            } else {
                pill.classList.add('bg-[#162235]', 'border', 'border-[#243247]', 'text-slate-400', 'hover:border-slate-500');
                pill.textContent = (idx + 1);
            }

            pill.addEventListener('click', () => {
                if (idx !== currentIndex && !isTransitioning) {
                    goToGame(idx);
                }
            });

            wizardTimeline.appendChild(pill);
        });
    }

    // 6. Update Progress Counters
    function updateProgressCounters() {
        const total = wizardGames.length;
        const pickedCount = wizardGames.filter(g => Boolean(g.user_pick)).length;
        const pct = total > 0 ? Math.round((pickedCount / total) * 100) : 0;

        if (wizardProgressBar) wizardProgressBar.style.width = pct + '%';
        if (wizardGameStepLabel) wizardGameStepLabel.textContent = `Game ${currentIndex + 1} of ${total}`;
        if (wizardPicksCountLabel) wizardPicksCountLabel.textContent = `${pickedCount} of ${total} Picked (${pct}%)`;

        // Update Bottom Bar Buttons
        if (btnWizardPrev) {
            btnWizardPrev.disabled = (currentIndex === 0);
        }
        if (btnWizardNext) {
            if (currentIndex === total - 1) {
                wizardNextBtnText.textContent = 'View Summary';
            } else {
                const currentPicked = Boolean(wizardGames[currentIndex]?.user_pick);
                wizardNextBtnText.textContent = currentPicked ? 'Next Game' : 'Skip Game';
            }
        }
    }

    // 7. Render Game at Current Index
    function renderCurrentGame() {
        const g = wizardGames[currentIndex];
        if (!g) return;

        wizardCompletionView.classList.add('hidden');
        wizardCardContainer.classList.remove('hidden');

        // AWAY TEAM
        awayStripe.style.backgroundColor = g.away_color;
        awayLogo.src = g.away_logo;
        awayLogo.alt = g.away_name;
        awayAbbr.textContent = g.away_team;
        awayName.textContent = g.away_name;
        awayDivision.textContent = `${g.away_conf} ${g.away_division}`.trim();
        btnPickAway.querySelector('span').textContent = `Select ${g.away_nick || g.away_team}`;

        // HOME TEAM
        homeStripe.style.backgroundColor = g.home_color;
        homeLogo.src = g.home_logo;
        homeLogo.alt = g.home_name;
        homeAbbr.textContent = g.home_team;
        homeName.textContent = g.home_name;
        homeDivision.textContent = `${g.home_conf} ${g.home_division}`.trim();
        btnPickHome.querySelector('span').textContent = `Select ${g.home_nick || g.home_team}`;

        // SCORES IF FINAL
        if (g.status === 'final' && g.away_score !== null && g.home_score !== null) {
            awayScore.textContent = g.away_score;
            awayScore.classList.remove('hidden');
            homeScore.textContent = g.home_score;
            homeScore.classList.remove('hidden');
        } else {
            awayScore.classList.add('hidden');
            homeScore.classList.add('hidden');
        }

        // KICKOFF & STATUS
        kickoffText.textContent = g.kickoff_formatted;
        const nowSec = Math.floor(Date.now() / 1000);
        const diffSec = g.kickoff_timestamp - nowSec;

        if (g.is_locked || g.status === 'final') {
            lockPill.className = 'mb-4 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-bold border border-rose-500/40 bg-rose-950/40 text-rose-300';
            lockIcon.textContent = '🔒';
            lockLabel.textContent = (g.status === 'final') ? 'Final Score' : 'Game Locked';
        } else if (diffSec > 0 && diffSec < 86400) {
            const hrs = Math.floor(diffSec / 3600);
            const mins = Math.floor((diffSec % 3600) / 60);
            lockPill.className = 'mb-4 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-bold border border-amber-500/40 bg-amber-950/40 text-amber-300 animate-pulse';
            lockIcon.textContent = '⏱️';
            lockLabel.textContent = `Locks in ${hrs}h ${mins}m`;
        } else {
            lockPill.className = 'mb-4 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-bold border border-emerald-500/40 bg-emerald-950/40 text-emerald-300';
            lockIcon.textContent = '🟢';
            lockLabel.textContent = 'Open for Picks';
        }
        kickoffRelative.textContent = g.is_mnf ? '⭐ Monday Night Football' : (g.status === 'final' ? 'Game Over' : 'NFL Regular Season');

        // TIEBREAKER SECTION
        if (g.is_mnf || (tbGameId && g.id === tbGameId)) {
            tiebreakerBox.classList.remove('hidden');
            if (mnfPointsInput) {
                mnfPointsInput.value = (mnfPredictedPoints !== null) ? mnfPredictedPoints : '';
                mnfPointsInput.disabled = tbIsLocked;
            }
        } else {
            tiebreakerBox.classList.add('hidden');
        }

        // RESET CARD HIGHLIGHTS
        applyPickStateToCards(g.user_pick, g.is_locked);
        updateProgressCounters();
        renderTimeline();
    }

    function applyPickStateToCards(userPick, isLocked) {
        const g = wizardGames[currentIndex];

        // Away Card State
        awayCard.classList.remove('border-amber-400', 'shadow-[0_0_30px_rgba(245,158,11,0.25)]', 'opacity-40');
        awayCheck.classList.remove('scale-100', 'opacity-100');
        awayCheck.classList.add('scale-0', 'opacity-0');
        awayCard.style.borderColor = '#243247';
        awayCard.style.background = 'linear-gradient(to bottom, #162235, #0d1624)';

        // Home Card State
        homeCard.classList.remove('border-amber-400', 'shadow-[0_0_30px_rgba(245,158,11,0.25)]', 'opacity-40');
        homeCheck.classList.remove('scale-100', 'opacity-100');
        homeCheck.classList.add('scale-0', 'opacity-0');
        homeCard.style.borderColor = '#243247';
        homeCard.style.background = 'linear-gradient(to bottom, #162235, #0d1624)';

        if (isLocked) {
            awayCard.classList.add('cursor-not-allowed');
            homeCard.classList.add('cursor-not-allowed');
            btnPickAway.classList.add('opacity-50', 'pointer-events-none');
            btnPickHome.classList.add('opacity-50', 'pointer-events-none');
        } else {
            awayCard.classList.remove('cursor-not-allowed');
            homeCard.classList.remove('cursor-not-allowed');
            btnPickAway.classList.remove('opacity-50', 'pointer-events-none');
            btnPickHome.classList.remove('opacity-50', 'pointer-events-none');
        }

        if (userPick === g.away_team) {
            awayCard.style.borderColor = g.away_color || '#EAB308';
            awayCard.style.background = `linear-gradient(135deg, ${g.away_color}25 0%, #0d1624 100%)`;
            awayCard.classList.add('shadow-[0_0_35px_rgba(234,179,8,0.2)]');
            awayCheck.classList.remove('scale-0', 'opacity-0');
            awayCheck.classList.add('scale-100', 'opacity-100');
            btnPickAway.querySelector('span').textContent = `✓ Selected (${g.away_team})`;
            btnPickAway.classList.add('bg-amber-500', 'text-slate-950', 'border-amber-400');
            btnPickAway.classList.remove('bg-[#0B1626]', 'text-slate-200');

            // Dim opposing card
            homeCard.classList.add('opacity-40');
            btnPickHome.querySelector('span').textContent = `Select ${g.home_nick || g.home_team}`;
            btnPickHome.classList.remove('bg-amber-500', 'text-slate-950', 'border-amber-400');
            btnPickHome.classList.add('bg-[#0B1626]', 'text-slate-200');
        } else if (userPick === g.home_team) {
            homeCard.style.borderColor = g.home_color || '#EAB308';
            homeCard.style.background = `linear-gradient(135deg, ${g.home_color}25 0%, #0d1624 100%)`;
            homeCard.classList.add('shadow-[0_0_35px_rgba(234,179,8,0.2)]');
            homeCheck.classList.remove('scale-0', 'opacity-0');
            homeCheck.classList.add('scale-100', 'opacity-100');
            btnPickHome.querySelector('span').textContent = `✓ Selected (${g.home_team})`;
            btnPickHome.classList.add('bg-amber-500', 'text-slate-950', 'border-amber-400');
            btnPickHome.classList.remove('bg-[#0B1626]', 'text-slate-200');

            // Dim opposing card
            awayCard.classList.add('opacity-40');
            btnPickAway.querySelector('span').textContent = `Select ${g.away_nick || g.away_team}`;
            btnPickAway.classList.remove('bg-amber-500', 'text-slate-950', 'border-amber-400');
            btnPickAway.classList.add('bg-[#0B1626]', 'text-slate-200');
        } else {
            btnPickAway.classList.remove('bg-amber-500', 'text-slate-950', 'border-amber-400');
            btnPickAway.classList.add('bg-[#0B1626]', 'text-slate-200');
            btnPickHome.classList.remove('bg-amber-500', 'text-slate-950', 'border-amber-400');
            btnPickHome.classList.add('bg-[#0B1626]', 'text-slate-200');
        }
    }

    // 8. Game Navigation Transitions
    function goToGame(newIndex, direction = 'next') {
        if (newIndex < 0 || newIndex >= wizardGames.length || isTransitioning) return;
        isTransitioning = true;
        playAdvanceSound();

        const offset = (direction === 'next') ? '-30px' : '30px';
        wizardCardContainer.style.transform = `translateX(${offset})`;
        wizardCardContainer.style.opacity = '0';

        setTimeout(() => {
            currentIndex = newIndex;
            renderCurrentGame();

            // Slide in from opposite direction
            const enterOffset = (direction === 'next') ? '30px' : '-30px';
            wizardCardContainer.style.transform = `translateX(${enterOffset})`;

            setTimeout(() => {
                wizardCardContainer.style.transform = 'translateX(0)';
                wizardCardContainer.style.opacity = '1';
                isTransitioning = false;
            }, 30);
        }, 150);
    }

    function showCompletion() {
        playCelebrationSound();
        wizardCardContainer.classList.add('hidden');
        wizardCompletionView.classList.remove('hidden');

        const total = wizardGames.length;
        const picked = wizardGames.filter(g => Boolean(g.user_pick)).length;
        document.getElementById('wizardCompleteTotal').textContent = `${picked} / ${total}`;
        document.getElementById('wizardCompleteTb').textContent = (mnfPredictedPoints !== null && mnfPredictedPoints !== '') ? `${mnfPredictedPoints} pts` : 'None';
    }

    btnWizardPrev.addEventListener('click', () => {
        if (currentIndex > 0) {
            goToGame(currentIndex - 1, 'prev');
        }
    });

    btnWizardNext.addEventListener('click', () => {
        if (currentIndex < wizardGames.length - 1) {
            goToGame(currentIndex + 1, 'next');
        } else {
            showCompletion();
        }
    });

    btnWizardRestart.addEventListener('click', () => {
        goToGame(0, 'prev');
    });

    // 9. Handle Team Selection & Autosave
    function selectTeam(teamType) {
        const g = wizardGames[currentIndex];
        if (!g || g.is_locked || isTransitioning) return;

        const selectedTeam = (teamType === 'away') ? g.away_team : g.home_team;
        g.user_pick = selectedTeam;

        // Trigger Sound & Immediate Visual Feedback
        playPickSound();
        applyPickStateToCards(selectedTeam, false);
        renderTimeline();
        updateProgressCounters();

        // Synchronize with standard table view radio buttons in real time
        syncWithStandardGrid(g.id, selectedTeam);

        // Perform background autosave
        fetch('/pickem/autosave', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                season_year: seasonYear,
                week_number: weekNumber,
                game_id: g.id,
                selected_team: selectedTeam
            })
        }).then(r => r.json()).then(data => {
            if (data && data.success) {
                // Keep standard badge synchronized if function exists
                if (typeof showAutoSaveBadge === 'function') {
                    showAutoSaveBadge(`${selectedTeam} Pick Saved`);
                }
            }
        }).catch(err => {
            console.warn('Pick Wizard autosave notice:', err);
        });

        // Advance to next game after a crisp micro-interaction delay (~320ms)
        setTimeout(() => {
            if (currentIndex < wizardGames.length - 1) {
                goToGame(currentIndex + 1, 'next');
            } else {
                // Check if all games are picked
                const unpicked = wizardGames.filter(x => !x.user_pick && !x.is_locked);
                if (unpicked.length === 0) {
                    showCompletion();
                } else {
                    goToGame(currentIndex, 'next');
                }
            }
        }, 320);
    }

    awayCard.addEventListener('click', () => selectTeam('away'));
    btnPickAway.addEventListener('click', (e) => {
        e.stopPropagation();
        selectTeam('away');
    });

    homeCard.addEventListener('click', () => selectTeam('home'));
    btnPickHome.addEventListener('click', (e) => {
        e.stopPropagation();
        selectTeam('home');
    });

    // 10. Synchronize with Standard Grid DOM
    function syncWithStandardGrid(gameId, teamVal) {
        // Standard view input radio
        const targetRadio = document.querySelector(`input[name="picks[${gameId}]"][value="${teamVal}"]`);
        if (targetRadio) {
            targetRadio.checked = true;
            const parentCard = targetRadio.closest('.matchup-card');
            if (parentCard) {
                // Trigger visual update on standard card
                const labels = parentCard.querySelectorAll('.team-card');
                labels.forEach(l => {
                    const r = l.querySelector('.pick-radio');
                    if (r && r.value === teamVal) {
                        l.classList.add('is-picked', 'opacity-100');
                        l.classList.remove('opacity-50');
                        const color = r.getAttribute('data-color') || '#EAB308';
                        l.style.borderColor = color;
                        l.style.background = `linear-gradient(135deg, ${color}28 0%, #0f172a 100%)`;
                        l.style.boxShadow = `0 0 22px ${color}44`;
                        const check = l.querySelector('.pick-check');
                        if (check) {
                            check.classList.remove('scale-0', 'opacity-0');
                            check.classList.add('scale-100', 'opacity-100');
                        }
                    } else if (l) {
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
                    }
                });
            }
        }
    }

    // 11. Tiebreaker Debounced Auto-Save
    let mnfTimer = null;
    if (mnfPointsInput) {
        mnfPointsInput.addEventListener('input', function() {
            const val = this.value.trim();
            mnfPredictedPoints = (val !== '') ? parseInt(val, 10) : null;

            // Mirror into standard table input
            const standardMnfInput = document.getElementById('mnf_total_points');
            if (standardMnfInput) {
                standardMnfInput.value = val;
            }

            clearTimeout(mnfTimer);
            mnfTimer = setTimeout(() => {
                fetch('/pickem/autosave', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        season_year: seasonYear,
                        week_number: weekNumber,
                        mnf_total_points: mnfPredictedPoints
                    })
                }).then(r => r.json()).then(data => {
                    if (data && data.success) {
                        mnfSaveNotice.style.opacity = '1';
                        setTimeout(() => { mnfSaveNotice.style.opacity = '0'; }, 1800);
                        if (typeof showAutoSaveBadge === 'function') {
                            showAutoSaveBadge(`Tiebreaker Saved (${val || '0'} pts)`);
                        }
                    }
                }).catch(e => console.warn('Tiebreaker autosave error:', e));
            }, 500);
        });
    }

    // 12. Keyboard Shortcuts Handler
    document.addEventListener('keydown', (e) => {
        if (!modal || modal.classList.contains('hidden')) return;

        // Ignore shortcuts if actively typing in the tiebreaker input
        if (document.activeElement === mnfPointsInput) {
            if (e.key === 'Escape') {
                mnfPointsInput.blur();
            }
            return;
        }

        switch (e.key) {
            case 'Escape':
                closeWizard();
                break;
            case 'ArrowLeft':
            case 'w':
            case 'W':
                if (currentIndex > 0) goToGame(currentIndex - 1, 'prev');
                break;
            case 'ArrowRight':
            case 'd':
            case 'D':
            case ' ':
                e.preventDefault();
                if (currentIndex < wizardGames.length - 1) {
                    goToGame(currentIndex + 1, 'next');
                } else {
                    showCompletion();
                }
                break;
            case '1':
            case 'a':
            case 'A':
            case 'ArrowUp':
                selectTeam('away');
                break;
            case '2':
            case 'h':
            case 'H':
            case 'ArrowDown':
                selectTeam('home');
                break;
        }
    });

    // Shortcuts Modal Toggles
    if (btnWizardShortcuts && wizardShortcutsModal) {
        btnWizardShortcuts.addEventListener('click', () => {
            wizardShortcutsModal.classList.remove('hidden');
        });
    }
    if (btnCloseShortcutsModal && wizardShortcutsModal) {
        btnCloseShortcutsModal.addEventListener('click', () => {
            wizardShortcutsModal.classList.add('hidden');
        });
    }
    if (wizardShortcutsModal) {
        wizardShortcutsModal.addEventListener('click', (e) => {
            if (e.target === wizardShortcutsModal) {
                wizardShortcutsModal.classList.add('hidden');
            }
        });
    }

    // 13. Auto Launch if URL has mode=wizard
    if (<?= $isAutoLaunch ? 'true' : 'false' ?>) {
        openWizard();
    }
});
</script>
