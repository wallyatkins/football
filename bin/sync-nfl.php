#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use WallyFootball\Services\ScoringEngine;
use WallyFootball\Services\SportsDataService;

$options = getopt('', ['season:', 'week:', 'all', 'help', 'quiet']);

if (isset($options['help'])) {
    echo "Usage: php bin/sync-nfl.php [--season=2026] [--week=1] [--all] [--quiet]\n";
    echo "  --all    Sync all 18 regular season weeks\n";
    echo "  --quiet  Suppress standard output except for errors\n";
    exit(0);
}

$service = new SportsDataService();
$scoring = new ScoringEngine();
$season = isset($options['season']) ? (int) $options['season'] : 2026;
$quiet = isset($options['quiet']);
$timestamp = date('Y-m-d H:i:s T');

if (isset($options['all'])) {
    if (!$quiet) {
        echo "[{$timestamp}] === Proactively Syncing Full NFL Season ({$season} Weeks 1-18) ===\n";
    }
    $start = microtime(true);
    for ($w = 1; $w <= 18; $w++) {
        try {
            $res = $service->syncWeek($season, $w);
            $elim = $scoring->gradeSurvivorWeek($season, $w);
            if (!$quiet) {
                echo "[*] Week {$w}: {$res['total_events']} games ({$res['inserted']} inserted, {$res['updated']} updated, {$elim} survivor elim)\n";
            }
        } catch (\Throwable $e) {
            echo "[!] Week {$w} FAILED: " . $e->getMessage() . "\n";
        }
    }
    $elapsed = round(microtime(true) - $start, 3);
    if (!$quiet) {
        echo "[{$timestamp}] === Full season sync completed in {$elapsed}s ===\n";
    }
    exit(0);
}

if (isset($options['week'])) {
    $week = (int) $options['week'];
} else {
    $info = $service->getCurrentWeekInfo();
    $week = (int) $info['week_number'];
}

$start = microtime(true);
try {
    $result = $service->syncWeek($season, $week);
    $eliminated = $scoring->gradeSurvivorWeek($season, $week);
    $elapsed = round(microtime(true) - $start, 3);

    // Update cache touch file
    $cacheFile = dirname(__DIR__) . "/data/.last_sync_{$season}_{$week}";
    @touch($cacheFile);

    if (!$quiet) {
        echo "[{$timestamp}] Week {$week} ({$season}): {$result['total_events']} games synced in {$elapsed}s ({$result['inserted']} new, {$result['updated']} updated";
        if ($eliminated > 0) {
            echo ", {$eliminated} survivor eliminations";
        }
        echo ")\n";
    }
} catch (\Throwable $e) {
    echo "[{$timestamp}] ERROR syncing Week {$week} ({$season}): " . $e->getMessage() . "\n";
    exit(1);
}
