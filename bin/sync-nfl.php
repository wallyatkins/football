#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use WallyFootball\Services\SportsDataService;

$options = getopt('', ['season:', 'week:', 'help']);

if (isset($options['help'])) {
    echo "Usage: php bin/sync-nfl.php [--season=2026] [--week=1]\n";
    exit(0);
}

$service = new SportsDataService();

if (isset($options['season']) && isset($options['week'])) {
    $season = (int) $options['season'];
    $week = (int) $options['week'];
} else {
    $info = $service->getCurrentWeekInfo();
    $season = (int) ($options['season'] ?? $info['season_year']);
    $week = (int) ($options['week'] ?? $info['week_number']);
}

echo "=== Syncing NFL Schedule & Scores (Season {$season}, Week {$week}) ===\n";
$start = microtime(true);
$result = $service->syncWeek($season, $week);
$elapsed = round(microtime(true) - $start, 3);

echo "Completed in {$elapsed}s:\n";
echo "- Total Events: " . $result['total_events'] . "\n";
echo "- Inserted: " . $result['inserted'] . "\n";
echo "- Updated: " . $result['updated'] . "\n";
