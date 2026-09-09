#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use WallyFootball\Services\SportsDataService;

$options = getopt('', ['season:', 'week:', 'all', 'help']);

if (isset($options['help'])) {
    echo "Usage: php bin/sync-nfl.php [--season=2026] [--week=1] [--all]\n";
    echo "  --all    Sync all 18 regular season weeks\n";
    exit(0);
}

$service = new SportsDataService();
$season = isset($options['season']) ? (int) $options['season'] : 2026;

if (isset($options['all'])) {
    echo "=== Proactively Syncing Full NFL Season ({$season} Weeks 1-18) ===\n";
    $start = microtime(true);
    for ($w = 1; $w <= 18; $w++) {
        echo "[*] Syncing Week {$w}... ";
        try {
            $res = $service->syncWeek($season, $w);
            echo "DONE ({$res['total_events']} games, {$res['inserted']} inserted, {$res['updated']} updated)\n";
        } catch (\Throwable $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
        }
    }
    $elapsed = round(microtime(true) - $start, 3);
    echo "=== Full season sync completed in {$elapsed}s ===\n";
    exit(0);
}

if (isset($options['week'])) {
    $week = (int) $options['week'];
} else {
    $info = $service->getCurrentWeekInfo();
    $week = (int) $info['week_number'];
}

echo "=== Syncing NFL Schedule & Scores (Season {$season}, Week {$week}) ===\n";
$start = microtime(true);
$result = $service->syncWeek($season, $week);
$elapsed = round(microtime(true) - $start, 3);

echo "Completed in {$elapsed}s:\n";
echo "- Total Events: " . $result['total_events'] . "\n";
echo "- Inserted: " . $result['inserted'] . "\n";
echo "- Updated: " . $result['updated'] . "\n";
