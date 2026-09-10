#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use WallyFootball\Database\Connection;
use WallyFootball\Services\DailyPickemDigestService;
use WallyFootball\Services\SportsDataService;

$options = getopt('', ['season:', 'week:', 'force', 'dry-run', 'test-to:', 'help']);

if (isset($options['help'])) {
    echo "Usage: php bin/send-daily-pickem-update.php [--season=2026] [--week=1] [--force] [--dry-run] [--test-to=email@example.com]\n";
    echo "  --force    Send update regardless of whether new games finished since last run\n";
    echo "  --dry-run  Generate emails and output log without dispatching\n";
    echo "  --test-to  Send a sample update only to the specified recipient\n";
    exit(0);
}

$db = Connection::getInstance();
$sports = new SportsDataService($db);
$digest = new DailyPickemDigestService($db, null, $sports);

$season = isset($options['season']) ? (int) $options['season'] : (int) (getenv('NFL_CURRENT_SEASON') ?: date('Y'));

if (isset($options['week'])) {
    $week = (int) $options['week'];
} else {
    // Dynamic active week
    $activeWeek = $db->queryValue(
        'SELECT MIN(week_number) FROM games WHERE season_year = :s AND status != "final"',
        ['s' => $season]
    );
    $week = ($activeWeek && (int)$activeWeek > 0) ? (int) $activeWeek : 1;
}

$force = isset($options['force']);
$dryRun = isset($options['dry-run']);
$testTo = isset($options['test-to']) ? (string) $options['test-to'] : null;

$timestamp = date('Y-m-d H:i:s T');
echo "[{$timestamp}] Starting Pick'em Daily Morning Digest for Season {$season} Week {$week}...\n";

$result = $digest->sendDigest($season, $week, $force, $testTo, $dryRun);

echo "Status: {$result['status']}\n";
if (isset($result['reason'])) {
    echo "Reason: {$result['reason']}\n";
}
echo "Sent: {$result['sent']}, Failed: {$result['failed']}\n";
foreach ($result['log'] as $line) {
    echo " - {$line}\n";
}
