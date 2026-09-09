#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use WallyFootball\Database\Connection;
use WallyFootball\Services\SportsDataService;
use WallyFootball\Support\TeamData;

$options = getopt('', ['season:', 'week:', 'sync', 'help']);

if (isset($options['help'])) {
    echo "Usage: php bin/verify-schedule.php [--season=2026] [--week=1] [--sync]\n";
    echo "  --sync    Runs SportsDataService::syncWeek prior to verification.\n";
    exit(0);
}

$season = isset($options['season']) ? (int) $options['season'] : 2026;
$week = isset($options['week']) ? (int) $options['week'] : 1;
$doSync = isset($options['sync']);

$db = Connection::getInstance();
$service = new SportsDataService($db);

echo "==============================================================\n";
echo "  NFL Schedule & Data Integrity Verification Tool\n";
echo "  Season: {$season} | Week: {$week}\n";
echo "==============================================================\n";

if ($doSync) {
    echo "\n[*] Running live sync first...\n";
    $syncResult = $service->syncWeek($season, $week);
    echo "    Sync completed: {$syncResult['total_events']} events, {$syncResult['inserted']} inserted, {$syncResult['updated']} updated.\n";
}

$games = $db->query(
    'SELECT * FROM games WHERE season_year = :s AND week_number = :w ORDER BY kickoff_time ASC, id ASC',
    ['s' => $season, 'w' => $week]
);

$errors = [];
$warnings = [];

echo "\n--- [1] Matchup Integrity Check ---\n";
$gameCount = count($games);
echo "Total Games Found: {$gameCount}\n";

if ($gameCount !== 16) {
    $errors[] = "Expected 16 games for Week {$week}, but found {$gameCount}.";
}

$teamAppearances = [];
$mnfGames = [];

foreach ($games as $idx => $g) {
    $home = TeamData::normalize($g['home_team']);
    $away = TeamData::normalize($g['away_team']);
    $gameNum = $idx + 1;

    $teamAppearances[$home] = ($teamAppearances[$home] ?? 0) + 1;
    $teamAppearances[$away] = ($teamAppearances[$away] ?? 0) + 1;

    if (!empty($g['is_mnf'])) {
        $mnfGames[] = $g;
    }

    $isOpener = ($idx === 0);
    $note = '';
    if ($isOpener) {
        $note .= ' [KICKOFF OPENER]';
    }
    if (!empty($g['is_mnf'])) {
        $note .= ' [MNF TIEBREAKER]';
    }

    printf(
        "  #%02d | %s @ %s | Kickoff: %s (UTC) | Status: %-11s%s\n",
        $gameNum,
        $away,
        $home,
        $g['kickoff_time'],
        $g['status'],
        $note
    );
}

// 2. Team Uniqueness
echo "\n--- [2] Franchise Representation Check ---\n";
$uniqueTeams = count($teamAppearances);
echo "Unique Teams Playing: {$uniqueTeams} / 32\n";

$duplicates = [];
foreach ($teamAppearances as $team => $count) {
    if ($count > 1) {
        $duplicates[] = "{$team} ({$count}x)";
    }
}

if (!empty($duplicates)) {
    $errors[] = "Duplicate team appearances detected: " . implode(', ', $duplicates);
}

if ($uniqueTeams !== 32 && $gameCount === 16) {
    $errors[] = "Expected 32 distinct franchises playing, but found {$uniqueTeams}.";
}

// 3. Kickoff Opener Verification
echo "\n--- [3] Season Opener Verification ---\n";
if (!empty($games)) {
    $firstGame = $games[0];
    $home = TeamData::normalize($firstGame['home_team']);
    $away = TeamData::normalize($firstGame['away_team']);

    echo "Opener Matchup: {$away} at {$home} (Kickoff: {$firstGame['kickoff_time']})\n";
    if (($home === 'SEA' && $away === 'NE') || ($home === 'NE' && $away === 'SEA')) {
        echo "  [PASS] Season opener correctly verified as Patriots vs Seahawks!\n";
    } else {
        $errors[] = "Week 1 opener is INCORRECT! Found {$away} @ {$home}, expected NE @ SEA.";
    }
} else {
    $errors[] = "No games found in database for Season {$season} Week {$week}.";
}

// 4. Tiebreaker (MNF) Verification
echo "\n--- [4] Tiebreaker (MNF) Verification ---\n";
if (count($mnfGames) === 1) {
    $mnf = $mnfGames[0];
    $home = TeamData::normalize($mnf['home_team']);
    $away = TeamData::normalize($mnf['away_team']);
    echo "  [PASS] Exactly one MNF tiebreaker designated: {$away} @ {$home} ({$mnf['kickoff_time']})\n";

    if ($week === 1) {
        if (($home === 'KC' && $away === 'DEN') || ($home === 'DEN' && $away === 'KC')) {
            echo "  [PASS] Week 1 MNF correctly designated as Broncos at Chiefs.\n";
        } else {
            $warnings[] = "Week 1 MNF game is {$away} @ {$home}; expected DEN @ KC.";
        }
    }
} elseif (count($mnfGames) === 0) {
    $errors[] = "No game is designated as the Monday Night Football tiebreaker (is_mnf = 1).";
} else {
    $errors[] = "Multiple games (" . count($mnfGames) . ") designated as MNF tiebreaker.";
}

// 5. Pick'em Entry & Orphan Pick Check
echo "\n--- [5] Pool Integrity & Orphan Pick Check ---\n";
$orphanPicks = $db->query(
    'SELECT p.id, p.entry_id, p.game_id 
     FROM pickem_picks p
     JOIN pickem_entries e ON p.entry_id = e.id
     WHERE e.season_year = :s AND e.week_number = :w
       AND p.game_id NOT IN (SELECT id FROM games WHERE season_year = :s AND week_number = :w)',
    ['s' => $season, 'w' => $week]
);

$orphanCount = count($orphanPicks);
if ($orphanCount > 0) {
    $errors[] = "Found {$orphanCount} orphaned pick(s) referencing non-existent or purged game IDs!";
} else {
    echo "  [PASS] 0 orphaned picks detected across all player entries.\n";
}

// 6. Summary
echo "\n==============================================================\n";
if (empty($errors)) {
    echo "  VERIFICATION RESULT: ALL CHECKS PASSED (100% INTEGRITY)\n";
    if (!empty($warnings)) {
        echo "  Warnings: " . count($warnings) . "\n";
        foreach ($warnings as $w) {
            echo "  - {$w}\n";
        }
    }
    echo "==============================================================\n";
    exit(0);
} else {
    echo "  VERIFICATION RESULT: FAILED (" . count($errors) . " ERRORS DETECTED)\n";
    foreach ($errors as $e) {
        echo "  [!] {$e}\n";
    }
    echo "==============================================================\n";
    exit(1);
}
