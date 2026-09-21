#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use WallyFootball\Database\Connection;
use WallyFootball\Services\MondayUpdateService;

$options = getopt('', [
    'season:',
    'week:',
    'preview-to:',
    'send-all',
    'dry-run',
    'help',
]);

if (isset($options['help'])) {
    echo "Usage: php bin/send-monday-update.php [options]\n";
    echo "Options:\n";
    echo "  --preview-to=<email>  Send preview to a specific email (default: wallyatkins@gmail.com)\n";
    echo "  --send-all            Broadcast update to all week participants (SAFETY: overrides preview)\n";
    echo "  --week=<N>            Week number (default: 2)\n";
    echo "  --season=<YYYY>       Season year (default: 2026)\n";
    echo "  --dry-run             Generate report and log recipient list without sending\n";
    echo "  --help                Show this help text\n";
    exit(0);
}

$season = isset($options['season']) ? (int) $options['season'] : 2026;
$week = isset($options['week']) ? (int) $options['week'] : 2;
$dryRun = isset($options['dry-run']);
$sendAll = isset($options['send-all']);

// Safety default: If --send-all is NOT explicitly provided, route as a preview to wallyatkins@gmail.com
if ($sendAll) {
    $previewTo = null;
} else {
    $previewTo = isset($options['preview-to']) && trim((string) $options['preview-to']) !== ''
        ? trim((string) $options['preview-to'])
        : 'wallyatkins@gmail.com';
}

$db = Connection::getInstance();
$service = new MondayUpdateService($db);

echo "============================================================\n";
echo "🏈 Atkins Football Monday Huddle Dispatch\n";
echo "Season: {$season} | Week: {$week}\n";
echo "Mode:   " . ($dryRun ? "DRY RUN (Simulated)" : ($sendAll ? "LIVE BROADCAST (All Participants)" : "REVIEW PREVIEW ONLY")) . "\n";
if ($previewTo !== null) {
    echo "Target: {$previewTo}\n";
}
echo "============================================================\n\n";

$data = $service->getMondayData($season, $week);
$mnfGame = $data['mnf_game'];
$mnfMatchup = $mnfGame ? "{$mnfGame['away_team']} @ {$mnfGame['home_team']}" : "NYG @ LAR";

echo "Final Games:  " . count($data['final_games']) . "\n";
echo "MNF Game:     {$mnfMatchup}\n";
echo "Participants: " . count($data['standings']) . "\n\n";

echo "Current Standings Preview (Top 3):\n";
foreach (array_slice($data['standings'], 0, 3) as $st) {
    $score = "{$st['correct_picks']}-" . ($st['total_graded'] - $st['correct_picks']);
    echo "  #{$st['rank']} {$st['username']} ({$score}) - MNF: {$st['mnf_pick']} (TB: {$st['predicted_mnf']} pts)\n";
}
echo "\n";

$result = $service->dispatchUpdate($season, $week, $previewTo, $dryRun);

echo "Dispatch Result:\n";
echo "  Mode:       {$result['mode']}\n";
echo "  Sent:       {$result['sent']}\n";
echo "  Failed:     {$result['failed']}\n";
echo "  Recipients: " . implode(', ', $result['recipients']) . "\n\n";

if ($result['mode'] === 'preview') {
    echo "✅ Preview email successfully sent to {$previewTo} for review!\n";
} elseif ($result['mode'] === 'broadcast') {
    echo "✅ Monday Update successfully broadcast to all {$result['sent']} participants!\n";
} else {
    echo "ℹ️ Dry-run completed. No emails dispatched.\n";
}
