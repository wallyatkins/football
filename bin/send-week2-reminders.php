#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use WallyFootball\Database\Connection;

$options = getopt('', ['season:', 'week:', 'dry-run', 'test-to:', 'user:', 'force', 'help']);

if (isset($options['help'])) {
    echo "Usage: php bin/send-week2-reminders.php [options]\n";
    echo "  --season=2026           Target season (default: 2026)\n";
    echo "  --week=2                Target NFL week (default: 2)\n";
    echo "  --dry-run               Preview summary and emails without sending\n";
    echo "  --test-to=email@domain  Send a test reminder only to this address\n";
    echo "  --user=username         Target a single username (e.g. --user=bartatkins)\n";
    echo "  --force                 Dispatch emails to all eligible users\n";
    exit(0);
}

$season = isset($options['season']) ? (int) $options['season'] : (int) (getenv('NFL_CURRENT_SEASON') ?: 2026);
$week = isset($options['week']) ? (int) $options['week'] : 2;
$isDryRun = isset($options['dry-run']);
$testTo = !empty($options['test-to']) ? trim((string) $options['test-to']) : null;
$targetUser = !empty($options['user']) ? trim((string) $options['user']) : null;
$force = isset($options['force']);

if (!$isDryRun && !$testTo && !$force) {
    echo "⚠️ Safety Guard: You must specify --dry-run, --test-to=email, or --force to execute.\n";
    echo "Run with --dry-run first to review the personalization for all users.\n";
    exit(1);
}

$sqlite = Connection::getInstance();

// Load teams lookup
$teamsFile = dirname(__DIR__) . '/src/Support/teams.json';
$teams = file_exists($teamsFile) ? json_decode(file_get_contents($teamsFile), true) : [];

// 1. Fetch eligible WallyAuth users with football access
$wallyAuthUsers = [];
$pgHost = getenv('WALLYAUTH_DB_HOST') ?: 'localhost';
$pgPort = getenv('WALLYAUTH_DB_PORT') ?: '5432';
$pgName = getenv('WALLYAUTH_DB_NAME') ?: 'multili2_wallyauth';
$pgUser = getenv('WALLYAUTH_DB_USER') ?: 'multili2_pg_wally_auth';
$pgPass = getenv('WALLYAUTH_DB_PASS') ?: 'JTDS1sUp@ndRunn1ng!';

try {
    $pg = new PDO("pgsql:host={$pgHost};port={$pgPort};dbname={$pgName}", $pgUser, $pgPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 3,
    ]);

    $stmt = $pg->query("
        SELECT DISTINCT u.id as oidc_sub, u.username, u.email
        FROM oauth_users u
        JOIN oauth_user_roles ur ON u.id = ur.user_id
        WHERE ur.role_id IN ('football-player', 'football-commissioner', 'commissioner')
        ORDER BY u.username ASC
    ");
    $wallyAuthUsers = $stmt->fetchAll();
    echo "Fetched " . count($wallyAuthUsers) . " football-authorized users from WallyAuth PostgreSQL.\n";
} catch (\Throwable $e) {
    echo "Note: Could not connect directly to WallyAuth PostgreSQL ({$e->getMessage()}).\n";
    echo "Falling back to users table in football SQLite...\n";
    $wallyAuthUsers = $sqlite->query("SELECT oidc_sub, username, email FROM users ORDER BY username ASC");
    echo "Loaded " . count($wallyAuthUsers) . " users from local database.\n";
}

// 2. Fetch Week 2 games
$games = $sqlite->query("
    SELECT id, away_team, home_team, kickoff_time, status 
    FROM games 
    WHERE season_year = :season AND week_number = :week 
    ORDER BY kickoff_time ASC, id ASC
", ['season' => $season, 'week' => $week]);

$totalGames = count($games);
$firstGame = $games[0] ?? null;
$firstKickoffStr = "Thursday, September 17, 2026 at 8:15 PM EDT";
if ($firstGame && !empty($firstGame['kickoff_time'])) {
    try {
        $dt = new DateTime($firstGame['kickoff_time']);
        $dt->setTimezone(new DateTimeZone('America/New_York'));
        $firstKickoffStr = $dt->format('l, F j, Y \a\t g:i A T');
    } catch (\Throwable) {}
}

// 3. Process each user and build personalized status
$dispatchList = [];

foreach ($wallyAuthUsers as $wu) {
    $uname = $wu['username'];
    $email = $wu['email'];
    $sub = $wu['oidc_sub'];

    if ($targetUser !== null && strcasecmp($uname, $targetUser) !== 0) {
        continue;
    }

    // Look up user in SQLite
    $localUser = $sqlite->queryOne("SELECT * FROM users WHERE oidc_sub = :sub OR LOWER(email) = LOWER(:email) LIMIT 1", [
        'sub' => $sub,
        'email' => $email
    ]);

    $userId = $localUser ? (int)$localUser['id'] : null;

    // A. Pick'em status
    $pickemEntry = null;
    $pickemPicks = [];
    if ($userId !== null) {
        $pickemEntry = $sqlite->queryOne("
            SELECT * FROM pickem_entries 
            WHERE user_id = :uid AND season_year = :season AND week_number = :week 
            LIMIT 1
        ", ['uid' => $userId, 'season' => $season, 'week' => $week]);

        if ($pickemEntry) {
            $picksRows = $sqlite->query("
                SELECT p.game_id, p.selected_team, g.away_team, g.home_team, g.kickoff_time
                FROM pickem_picks p
                JOIN games g ON p.game_id = g.id
                WHERE p.entry_id = :eid AND g.week_number = :week AND g.season_year = :season
                ORDER BY g.kickoff_time ASC, g.id ASC
            ", ['eid' => $pickemEntry['id'], 'week' => $week, 'season' => $season]);

            foreach ($picksRows as $pr) {
                $pickemPicks[(int)$pr['game_id']] = $pr;
            }
        }
    }

    $pickCount = count($pickemPicks);
    $pickemStatus = 'none';
    if ($pickCount >= $totalGames && $totalGames > 0) {
        $pickemStatus = 'complete';
    } elseif ($pickCount > 0) {
        $pickemStatus = 'partial';
    }

    // B. Survivor status
    $survivorEntry = null;
    $survivorPickWeek2 = null;
    $survivorTeamsUsed = [];
    $isEliminated = false;
    $elimWeek = null;

    if ($userId !== null) {
        $survivorEntry = $sqlite->queryOne("
            SELECT * FROM survivor_entries 
            WHERE user_id = :uid AND season_year = :season 
            LIMIT 1
        ", ['uid' => $userId, 'season' => $season]);

        if ($survivorEntry) {
            $isEliminated = (bool)$survivorEntry['is_eliminated'];
            $elimWeek = $survivorEntry['elimination_week'] ? (int)$survivorEntry['elimination_week'] : null;

            $allSurvPicks = $sqlite->query("
                SELECT week_number, selected_team 
                FROM survivor_picks 
                WHERE user_id = :uid AND season_year = :season 
                ORDER BY week_number ASC
            ", ['uid' => $userId, 'season' => $season]);

            foreach ($allSurvPicks as $sp) {
                if ((int)$sp['week_number'] === $week) {
                    $survivorPickWeek2 = $sp['selected_team'];
                } else {
                    $survivorTeamsUsed[] = $sp['selected_team'];
                }
            }
        }
    }

    $survivorStatus = 'not_registered';
    if ($isEliminated) {
        $survivorStatus = 'eliminated';
    } elseif ($survivorEntry) {
        $survivorStatus = ($survivorPickWeek2 !== null) ? 'complete' : 'pending_pick';
    }

    $dispatchList[] = [
        'username' => $uname,
        'email' => $email,
        'user_id' => $userId,
        'pickem_status' => $pickemStatus,
        'pickem_count' => $pickCount,
        'pickem_total' => $totalGames,
        'pickem_picks' => $pickemPicks,
        'survivor_status' => $survivorStatus,
        'survivor_pick' => $survivorPickWeek2,
        'survivor_elim_week' => $elimWeek,
        'survivor_used' => $survivorTeamsUsed,
    ];
}

// Summary table
echo "\n========================================================================================\n";
echo sprintf("%-18s | %-26s | %-16s | %-20s\n", "User", "Email", "Pick'em (Wk 2)", "Survivor (Wk 2)");
echo "----------------------------------------------------------------------------------------\n";
foreach ($dispatchList as $d) {
    $pDesc = match ($d['pickem_status']) {
        'complete' => "✅ Complete (16/16)",
        'partial' => "⚠️ Partial ({$d['pickem_count']}/16)",
        'none' => "⏳ Not Started (0/16)",
    };

    $sDesc = match ($d['survivor_status']) {
        'complete' => "✅ Picked: {$d['survivor_pick']}",
        'pending_pick' => "🚨 PICK NEEDED!",
        'eliminated' => "❌ Eliminated (Wk {$d['survivor_elim_week']})",
        'not_registered' => "⚪ Not Entered",
    };

    echo sprintf("%-18s | %-26s | %-16s | %-20s\n", $d['username'], $d['email'], $pDesc, $sDesc);
}
echo "========================================================================================\n\n";

// Function to get team name
function getTeamName(string $abbr, array $teams): string {
    return $teams[$abbr]['name'] ?? $abbr;
}

function getTeamColor(string $abbr, array $teams): string {
    return $teams[$abbr]['color'] ?? '#3b82f6';
}

// Builder for personalized email content
function renderPersonalizedEmail(array $user, array $games, array $teams, string $firstKickoffStr): array {
    $uname = htmlspecialchars($user['username']);
    $email = htmlspecialchars($user['email']);
    $week = 2;
    $season = 2026;

    $pickemStatus = $user['pickem_status'];
    $pickCount = $user['pickem_count'];
    $totalGames = $user['pickem_total'];
    $pickemPicks = $user['pickem_picks'];

    $survivorStatus = $user['survivor_status'];
    $survPick = $user['survivor_pick'];
    $elimWeek = $user['survivor_elim_week'];

    // 1. Determine headline & subject
    $bothComplete = ($pickemStatus === 'complete' && ($survivorStatus === 'complete' || $survivorStatus === 'eliminated'));
    
    if ($bothComplete) {
        $subject = "✅ Atkins NFL Pool: Your Week 2 Picks are Locked In! (Kickoff Tomorrow)";
        $heroBadge = "🎉 All Picks Confirmed";
        $heroTitle = "You're All Set for Week 2, {$uname}!";
        $heroSubtitle = ($survivorStatus === 'eliminated')
            ? "Thank you for getting your Pick'em selections in early! Here is your official selections receipt for this week's action:"
            : "Thank you for getting your selections in early! Here is your official selections receipt for this week's action:";
        $heroBg = "linear-gradient(135deg, #064e3b 0%, #0f172a 100%)";
        $heroBorder = "#10b981";
    } elseif ($pickemStatus === 'complete' && $survivorStatus === 'pending_pick') {
        $subject = "⚠️ Atkins NFL Pool: Survivor Pick Needed! (Pick'em is Locked In)";
        $heroBadge = "⚡ Action Required";
        $heroTitle = "Pick'em is Locked, but Don't Forget Survivor!";
        $heroSubtitle = "Great job submitting your Pick'em card early! However, your Week 2 Survivor selection is still pending before kickoff.";
        $heroBg = "linear-gradient(135deg, #78350f 0%, #0f172a 100%)";
        $heroBorder = "#f59e0b";
    } elseif ($pickemStatus === 'complete') {
        $subject = "✅ Atkins NFL Pool: Your Week 2 Pick'em is Locked In! (Kickoff Tomorrow)";
        $heroBadge = "🎉 Pick'em Confirmed";
        $heroTitle = "You're All Set for Week 2 Pick'em, {$uname}!";
        $heroSubtitle = "Thank you for getting your selections in early. Here is your official selections receipt for this week's action (the Survivor pool is also open if you want to join!):";
        $heroBg = "linear-gradient(135deg, #064e3b 0%, #0f172a 100%)";
        $heroBorder = "#10b981";
    } elseif ($survivorStatus === 'complete') {
        $subject = "⚠️ Atkins NFL Pool: Week 2 Pick'em Card Needed! (Survivor is Locked)";
        $heroBadge = "⚡ Action Required";
        $heroTitle = "Survivor Locked! Finish Your Pick'em Card";
        $heroSubtitle = "You've successfully chosen your Survivor team early, but your Week 2 Pick'em card still needs your selections!";
        $heroBg = "linear-gradient(135deg, #78350f 0%, #0f172a 100%)";
        $heroBorder = "#f59e0b";
    } elseif ($survivorStatus === 'pending_pick') {
        $subject = "🚨 Atkins NFL Pool: Week 2 Picks & Survivor Selection Needed!";
        $heroBadge = "⚡ Action Required";
        $heroTitle = "Week 2 Picks are Open, {$uname}!";
        $heroSubtitle = "Thursday Night Football is right around the corner! You survived Week 1 — lock in both your Pick'em selections and your Week 2 Survivor team before kickoff.";
        $heroBg = "linear-gradient(135deg, #78350f 0%, #0f172a 100%)";
        $heroBorder = "#ef4444";
    } elseif ($survivorStatus === 'eliminated') {
        $subject = "🏈 Atkins NFL Pool: Week 2 Pick'em Kickoff Tomorrow! Get Your Picks In";
        $heroBadge = "⏰ Kickoff Tomorrow";
        $heroTitle = "Week 2 Pick'em is Open, {$uname}!";
        $heroSubtitle = "Thursday Night Football is right around the corner! Lock in your 16 Pick'em selections before kickoff.";
        $heroBg = "linear-gradient(135deg, #1e293b 0%, #0f172a 100%)";
        $heroBorder = "#f59e0b";
    } else {
        $subject = "🏈 Atkins NFL Pool: Week 2 Kickoff Tomorrow! Get Your Picks In";
        $heroBadge = "⏰ Kickoff Tomorrow";
        $heroTitle = "Week 2 Picks are Open, {$uname}!";
        $heroSubtitle = "Thursday Night Football is right around the corner! Lock in your 16 Pick'em selections (and join the Survivor pool if you'd like) before kickoff.";
        $heroBg = "linear-gradient(135deg, #1e293b 0%, #0f172a 100%)";
        $heroBorder = "#f59e0b";
    }

    // 2. Build Pick'em Section HTML
    $pickemCardHtml = "";
    if ($pickemStatus === 'complete') {
        $picksRowsHtml = "";
        foreach ($games as $g) {
            $gid = (int)$g['id'];
            $pickedAbbr = $pickemPicks[$gid]['selected_team'] ?? '';
            $pickedName = getTeamName($pickedAbbr, $teams);
            $awayName = getTeamName($g['away_team'], $teams);
            $homeName = getTeamName($g['home_team'], $teams);
            $pickedColor = getTeamColor($pickedAbbr, $teams);

            $picksRowsHtml .= <<<HTML
            <tr style="border-bottom: 1px solid #1e293b;">
                <td style="padding: 9px 10px; font-size: 12px; color: #94a3b8;">
                    {$awayName} <span style="color:#64748b; font-size:11px;">@</span> {$homeName}
                </td>
                <td style="padding: 9px 10px; text-align: right; font-size: 13px; font-weight: bold; color: #ffffff;">
                    <span style="display:inline-block; padding: 2px 8px; border-radius: 6px; background-color: {$pickedColor}33; border: 1px solid {$pickedColor}; color: #ffffff;">
                        {$pickedName} ({$pickedAbbr})
                    </span>
                </td>
            </tr>
HTML;
        }

        $pickemCardHtml = <<<HTML
        <div style="background-color: #131b2e; border: 2px solid #10b981; border-radius: 14px; padding: 20px; margin-bottom: 24px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.15);">
            <div style="font-size: 11px; font-weight: 900; color: #34d399; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">
                ✅ Pick'em Pool Status: 16 of 16 Games Complete
            </div>
            <div style="font-size: 16px; font-weight: 900; color: #ffffff; margin-bottom: 8px;">
                Your Week 2 Selections are Confirmed!
            </div>
            <div style="font-size: 13px; color: #cbd5e1; margin-bottom: 16px; line-height: 1.5;">
                You beat the clock and have every game picked. If you want to review or change any prediction before that game kicks off, you can do so in the app anytime:
            </div>
            <div style="background-color: #0b0f19; border-radius: 10px; border: 1px solid #1e293b; overflow: hidden; margin-bottom: 16px;">
                <table style="width: 100%; border-collapse: collapse;">
                    {$picksRowsHtml}
                </table>
            </div>
            <div style="text-align: center;">
                <a href="https://football.wallyatkins.com/pickem?week=2&mtm_campaign=week_2_reminder&mtm_source=email&mtm_medium=button" 
                   style="display: inline-block; padding: 10px 20px; background-color: #1e293b; color: #34d399; font-weight: 800; font-size: 12px; text-decoration: none; border-radius: 8px; border: 1px solid #10b981; text-transform: uppercase; letter-spacing: 0.5px;">
                    Review / Adjust Pick'em Picks &rarr;
                </a>
            </div>
        </div>
HTML;
    } elseif ($pickemStatus === 'partial') {
        $remainingCount = $totalGames - $pickCount;
        $pickemCardHtml = <<<HTML
        <div style="background-color: #1e1b18; border: 2px solid #f59e0b; border-radius: 14px; padding: 20px; margin-bottom: 24px; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.15);">
            <div style="font-size: 11px; font-weight: 900; color: #fbbf24; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">
                ⚠️ Pick'em Pool Status: {$pickCount} of {$totalGames} Games Selected
            </div>
            <div style="font-size: 16px; font-weight: 900; color: #ffffff; margin-bottom: 8px;">
                {$remainingCount} More Games Remaining to Pick!
            </div>
            <div style="font-size: 13px; color: #cbd5e1; margin-bottom: 16px; line-height: 1.5;">
                You have saved {$pickCount} game predictions, but your Week 2 card is not completely filled out yet. The first game kicks off tomorrow night:
            </div>
            <div style="text-align: center; margin: 16px 0;">
                <a href="https://football.wallyatkins.com/pickem?week=2&mtm_campaign=week_2_reminder&mtm_source=email&mtm_medium=button" 
                   style="display: inline-block; padding: 12px 24px; background-color: #f59e0b; color: #020617; font-weight: 900; font-size: 13px; text-decoration: none; border-radius: 8px; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);">
                    🏈 Complete Your Remaining {$remainingCount} Picks &rarr;
                </a>
            </div>
        </div>
HTML;
    } else {
        $pickemCardHtml = <<<HTML
        <div style="background-color: #131b2e; border: 2px solid #3b82f6; border-radius: 14px; padding: 20px; margin-bottom: 24px;">
            <div style="font-size: 11px; font-weight: 900; color: #60a5fa; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">
                ⏳ Pick'em Pool Status: No Picks Saved Yet (0 / 16)
            </div>
            <div style="font-size: 16px; font-weight: 900; color: #ffffff; margin-bottom: 8px;">
                Week 2 Pick'em is Open — Don't Miss Out!
            </div>
            <div style="font-size: 13px; color: #cbd5e1; margin-bottom: 16px; line-height: 1.5;">
                All 16 NFL matchups are ready for your predictions. It takes less than two minutes to make your picks:
            </div>
            <div style="text-align: center; margin: 16px 0;">
                <a href="https://football.wallyatkins.com/pickem?week=2&mtm_campaign=week_2_reminder&mtm_source=email&mtm_medium=button" 
                   style="display: inline-block; padding: 13px 26px; background-color: #f59e0b; color: #020617; font-weight: 900; font-size: 13px; text-decoration: none; border-radius: 8px; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);">
                    🏈 Make Your Week 2 Picks Now &rarr;
                </a>
            </div>
        </div>
HTML;
    }

    // 3. Build Survivor Section HTML
    $survivorCardHtml = "";
    if ($survivorStatus === 'complete') {
        $survTeamName = getTeamName($survPick, $teams);
        $survColor = getTeamColor($survPick, $teams);
        $usedStr = !empty($user['survivor_used']) ? implode(', ', $user['survivor_used']) : 'None';

        $survivorCardHtml = <<<HTML
        <div style="background-color: #131b2e; border: 2px solid #10b981; border-radius: 14px; padding: 20px; margin-bottom: 24px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.15);">
            <div style="font-size: 11px; font-weight: 900; color: #34d399; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">
                ✅ Survivor Pool Status: Pick Locked In
            </div>
            <div style="font-size: 16px; font-weight: 900; color: #ffffff; margin-bottom: 8px;">
                Your Week 2 Survivor Team: <span style="color: #34d399;">{$survTeamName} ({$survPick})</span>
            </div>
            <div style="font-size: 13px; color: #cbd5e1; margin-bottom: 12px; line-height: 1.5;">
                You're locked in! Remember, you cannot select the {$survTeamName} again for the remainder of the season.
            </div>
            <div style="font-size: 11px; color: #94a3b8; font-family: monospace; background-color: #0b0f19; padding: 8px 12px; border-radius: 6px; display: inline-block;">
                Prior Teams Used: {$usedStr}
            </div>
            <div style="text-align: center; margin-top: 14px;">
                <a href="https://football.wallyatkins.com/survivor?week=2&mtm_campaign=week_2_reminder&mtm_source=email&mtm_medium=button" 
                   style="display: inline-block; padding: 10px 20px; background-color: #1e293b; color: #34d399; font-weight: 800; font-size: 12px; text-decoration: none; border-radius: 8px; border: 1px solid #10b981; text-transform: uppercase; letter-spacing: 0.5px;">
                    View Survivor Board &rarr;
                </a>
            </div>
        </div>
HTML;
    } elseif ($survivorStatus === 'pending_pick') {
        $survivorCardHtml = <<<HTML
        <div style="background-color: #241113; border: 2px solid #ef4444; border-radius: 14px; padding: 20px; margin-bottom: 24px; box-shadow: 0 4px 14px rgba(239, 68, 68, 0.2);">
            <div style="font-size: 11px; font-weight: 900; color: #f87171; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">
                🚨 Survivor Alert: You Are Still In The Hunt!
            </div>
            <div style="font-size: 17px; font-weight: 900; color: #ffffff; margin-bottom: 8px;">
                You Have NOT Chosen Your Week 2 Survivor Pick!
            </div>
            <div style="font-size: 13px; color: #fca5a5; margin-bottom: 16px; line-height: 1.5;">
                You survived Week 1, but if you do not lock in a team before kickoff, you will be eliminated by default. Choose wisely — each team can only be used once all year!
            </div>
            <div style="text-align: center; margin: 16px 0;">
                <a href="https://football.wallyatkins.com/survivor?week=2&mtm_campaign=week_2_reminder&mtm_source=email&mtm_medium=button" 
                   style="display: inline-block; padding: 13px 26px; background-color: #ef4444; color: #ffffff; font-weight: 900; font-size: 13px; text-decoration: none; border-radius: 8px; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 14px rgba(239, 68, 68, 0.4);">
                    🛡️ Lock In Your Week 2 Survivor Team &rarr;
                </a>
            </div>
        </div>
HTML;
    } elseif ($survivorStatus === 'eliminated') {
        $survivorCardHtml = <<<HTML
        <div style="background-color: #0f172a; border: 1px solid #334155; border-radius: 14px; padding: 16px 20px; margin-bottom: 24px;">
            <div style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px;">
                🛡️ Survivor Pool: Eliminated in Week {$elimWeek}
            </div>
            <div style="font-size: 13px; color: #64748b; line-height: 1.5;">
                Your Survivor run came to an end in Week {$elimWeek}, but the weekly cash and season prizes in the Pick'em pool are completely up for grabs!
            </div>
        </div>
HTML;
    } else {
        $survivorCardHtml = <<<HTML
        <div style="background-color: #131b2e; border: 1px solid #334155; border-radius: 14px; padding: 16px 20px; margin-bottom: 24px;">
            <div style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px;">
                🛡️ Survivor Pool: Open For Entry
            </div>
            <div style="font-size: 13px; color: #cbd5e1; line-height: 1.5; margin-bottom: 10px;">
                Want to join the Survivor Pool? Pick one straight-up winner every week without repeating teams.
            </div>
            <a href="https://football.wallyatkins.com/survivor?week=2&mtm_campaign=week_2_reminder&mtm_source=email&mtm_medium=button" style="color: #60a5fa; font-size: 12px; font-weight: bold; text-decoration: underline;">
                Check Survivor Rules &amp; Register &rarr;
            </a>
        </div>
HTML;
    }

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #040813; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #f8fafc;">
    <div style="max-width: 620px; margin: 24px auto; background-color: #0b0f19; border-radius: 16px; overflow: hidden; border: 1px solid #1e293b; box-shadow: 0 12px 35px rgba(0,0,0,0.6);">
        
        <!-- Header Banner -->
        <div style="background: {$heroBg}; padding: 26px 24px; border-bottom: 2px solid {$heroBorder}; text-align: center;">
            <div style="font-size: 11px; font-family: monospace; font-weight: bold; color: #f59e0b; text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 6px;">
                🏈 ATKINS NFL POOL &bull; SEASON {$season}
            </div>
            <h1 style="margin: 0; font-size: 24px; font-weight: 900; color: #ffffff; letter-spacing: -0.5px;">
                {$heroTitle}
            </h1>
            <div style="font-size: 13px; color: #cbd5e1; margin-top: 8px; line-height: 1.5;">
                {$heroSubtitle}
            </div>
        </div>

        <!-- Deadline Notice Bar -->
        <div style="background-color: #1e293b; border-bottom: 1px solid #334155; padding: 12px 20px; text-align: center; font-size: 12px; color: #f8fafc;">
            ⚡ <strong>First Kickoff:</strong> {$firstKickoffStr} (DET @ BUF)
        </div>

        <!-- Body Content -->
        <div style="padding: 24px 20px;">
            
            {$pickemCardHtml}

            {$survivorCardHtml}

            <!-- Quick Links -->
            <div style="text-align: center; margin: 28px 0 12px 0;">
                <a href="https://football.wallyatkins.com/pickem?week=2&mtm_campaign=week_2_reminder&mtm_source=email&mtm_medium=cta" 
                   style="display: inline-block; margin: 5px; padding: 13px 22px; background-color: #f59e0b; color: #020617; font-weight: 900; font-size: 13px; text-decoration: none; border-radius: 10px; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35);">
                    🏈 Pick'em Matchups &rarr;
                </a>
                <a href="https://football.wallyatkins.com/survivor?week=2&mtm_campaign=week_2_reminder&mtm_source=email&mtm_medium=cta" 
                   style="display: inline-block; margin: 5px; padding: 13px 22px; background-color: #1e293b; color: #f8fafc; font-weight: 800; font-size: 13px; text-decoration: none; border-radius: 10px; border: 1px solid #475569; text-transform: uppercase; letter-spacing: 0.5px;">
                    🛡️ Survivor Board &rarr;
                </a>
            </div>

            <div style="text-align: center; margin-bottom: 20px;">
                <a href="https://football.wallyatkins.com/pickem/standings?week=1&mtm_campaign=week_2_reminder&mtm_source=email&mtm_medium=link" style="color: #94a3b8; font-size: 11px; text-decoration: underline; margin: 0 8px;">
                    Week 1 Final Standings
                </a>
                &bull;
                <a href="https://football.wallyatkins.com/rules?mtm_campaign=week_2_reminder&mtm_source=email&mtm_medium=link" style="color: #94a3b8; font-size: 11px; text-decoration: underline; margin: 0 8px;">
                    Rules &amp; Payout Details
                </a>
            </div>

        </div>

        <!-- Footer -->
        <div style="background-color: #040813; padding: 20px 24px; border-top: 1px solid #1e293b; text-align: center; font-size: 11px; color: #64748b; line-height: 1.6;">
            Sent by Commissioner Wally Atkins &bull; <a href="mailto:football@wallyatkins.com" style="color: #94a3b8; text-decoration: underline;">football@wallyatkins.com</a><br>
            Protected by WallyAuth SSO &bull; <a href="https://football.wallyatkins.com" style="color: #94a3b8; text-decoration: underline;">football.wallyatkins.com</a><br>
            <span style="color: #475569; font-size: 10px;">Login with your WallyAuth credentials. If you forgot your password, use the passwordless login on the portal.</span>
        </div>

        <!-- Matomo Tracking Pixel (Site ID 9) -->
        <img src="https://analytics.wallyatkins.com/matomo.php?idsite=9&amp;rec=1&amp;action_name=email%2Fweek_2_reminder_{$uname}&amp;url=https%3A%2F%2Femail.wallyatkins.com%2Freminders%2Fweek_2" width="1" height="1" style="display:none; width:1px; height:1px; border:0;" alt="" />

    </div>
</body>
</html>
HTML;

    // Plain text alternative
    $plain = <<<TEXT
================================================================================
ATKINS NFL POOL — WEEK 2 REMINDER & PICKS RECEIPT
================================================================================
Hey {$uname},

First Kickoff: {$firstKickoffStr} (DET @ BUF)

--- PICK'EM STATUS ---
TEXT;

    if ($pickemStatus === 'complete') {
        $plain .= "\n✅ All 16 games are locked in! Thanks for getting your picks in early.\n\nYour Week 2 Picks:\n";
        foreach ($games as $g) {
            $gid = (int)$g['id'];
            $picked = $pickemPicks[$gid]['selected_team'] ?? '';
            $plain .= "  • {$g['away_team']} @ {$g['home_team']} -> {$picked}\n";
        }
    } elseif ($pickemStatus === 'partial') {
        $plain .= "\n⚠️ INCOMPLETE: {$pickCount} of {$totalGames} games selected.\nPlease finish your remaining picks at: https://football.wallyatkins.com/pickem?week=2\n";
    } else {
        $plain .= "\n⏳ NO PICKS SAVED YET (0 of {$totalGames}).\nSubmit your picks before kickoff at: https://football.wallyatkins.com/pickem?week=2\n";
    }

    $plain .= "\n--- SURVIVOR STATUS ---\n";
    if ($survivorStatus === 'complete') {
        $plain .= "✅ Pick Locked: {$survPick}\n";
    } elseif ($survivorStatus === 'pending_pick') {
        $plain .= "🚨 SURVIVOR PICK NEEDED! You are ALIVE in the hunt, but have not picked a team for Week 2.\nLock your team at: https://football.wallyatkins.com/survivor?week=2\n";
    } elseif ($survivorStatus === 'eliminated') {
        $plain .= "Eliminated in Week {$elimWeek}. Good luck in Pick'em!\n";
    } else {
        $plain .= "Survivor pool is open for entry: https://football.wallyatkins.com/survivor?week=2\n";
    }

    $plain .= <<<TEXT

App Link: https://football.wallyatkins.com
Commissioner Wally Atkins • football@wallyatkins.com
================================================================================
TEXT;

    return [$subject, $html, $plain];
}

// 4. Dispatch Loop
$fromEmail = 'football@wallyatkins.com';
$fromName = 'Commissioner Wally Atkins';
$sentCount = 0;
$failCount = 0;

echo "Starting dispatch processing for " . count($dispatchList) . " users...\n";

foreach ($dispatchList as $u) {
    [$subject, $html, $plain] = renderPersonalizedEmail($u, $games, $teams, $firstKickoffStr);

    $targetEmail = $testTo ?: $u['email'];
    $uname = $u['username'];

    if ($isDryRun) {
        echo "[DRY-RUN] Would send to {$uname} <{$targetEmail}> | Subject: {$subject}\n";
        $sentCount++;
        continue;
    }

    $boundary = "==Pickem_Reminder_" . md5((string) microtime()) . "==";
    $headers = [
        "From: {$fromName} <{$fromEmail}>",
        "Reply-To: {$fromEmail}",
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        "X-Mailer: AtkinsNFLPoolReminder/1.0",
        "List-Unsubscribe: <https://football.wallyatkins.com/preferences?email=" . urlencode($targetEmail) . ">",
        "List-Unsubscribe-Post: List-Unsubscribe=One-Click",
    ];

    $body = "--{$boundary}\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: 7bit\r\n\r\n"
          . $plain . "\r\n\r\n"
          . "--{$boundary}\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: 7bit\r\n\r\n"
          . $html . "\r\n\r\n"
          . "--{$boundary}--";

    $headersString = implode("\r\n", $headers);
    $success = @mail($targetEmail, $subject, $body, $headersString, "-f {$fromEmail}");

    if ($success) {
        echo "✅ Delivered reminder to {$uname} <{$targetEmail}>\n";
        $sentCount++;
    } else {
        echo "❌ Failed to deliver to {$uname} <{$targetEmail}>\n";
        $failCount++;
    }

    // Small delay to prevent SMTP throttling
    usleep(150000); // 0.15s

    // If test-to was specified, stop after one send unless multiple users targeted
    if ($testTo && $targetUser === null) {
        echo "\n[TEST MODE] Delivered single sample test to {$testTo}. Stopping.\n";
        break;
    }
}

echo "\nFinished! Sent: {$sentCount}, Failed: {$failCount}\n";
exit($failCount > 0 ? 1 : 0);
