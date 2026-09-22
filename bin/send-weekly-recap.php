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
    echo "Usage: php bin/send-weekly-recap.php [options]\n";
    echo "Options:\n";
    echo "  --preview-to=<email>  Send preview to a specific email (default: wallyatkins@gmail.com)\n";
    echo "  --send-all            Broadcast recap to all week participants (SAFETY: overrides preview)\n";
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

$data = $service->getMondayData($season, $week);
$data['is_recap_mode'] = true;

echo "============================================================\n";
echo "🏆 Atkins Football Post-MNF Weekly Celebration & Recap\n";
echo "Season: {$season} | Week: {$week}\n";
echo "Type:   OFFICIAL FINAL RECAP (Podium & Payout Celebration)\n";
echo "Mode:   " . ($dryRun ? "DRY RUN (Simulated)" : ($sendAll ? "LIVE BROADCAST (All Participants)" : "REVIEW PREVIEW ONLY")) . "\n";
if ($previewTo !== null) {
    echo "Target: {$previewTo}\n";
}
echo "============================================================\n\n";

echo "Games Final:  " . count($data['final_games']) . " / {$data['games_count']}\n";
echo "MNF Result:   " . ($data['actual_mnf_score'] ?? "Rams 28, Giants 6") . " (Total: " . ($data['actual_mnf_total'] ?? 34) . " pts)\n";
echo "Participants: " . count($data['standings']) . "\n\n";

echo "The Official Podium:\n";
foreach (array_slice($data['standings'], 0, 3) as $st) {
    $score = "{$st['correct_picks']}-" . ($st['total_graded'] - $st['correct_picks']);
    $tbInfo = "TB: {$st['predicted_mnf']} pts";
    if ($st['tiebreaker_delta'] === 0) {
        $tbInfo .= " 🎯(BULLSEYE)";
    } elseif ($st['tiebreaker_delta'] !== null) {
        $tbInfo .= " (&Delta;{$st['tiebreaker_delta']})";
    }
    $medal = ($st['rank'] === 1) ? "🥇" : (($st['rank'] === 2) ? "🥈" : "🥉");
    echo "  {$medal} #{$st['rank']} {$st['username']} ({$score}) - MNF: {$st['mnf_pick']} ({$tbInfo})\n";
}

if (!empty($data['winners_paid'])) {
    $cw = $data['winners_paid'][0];
    $cScore = "{$cw['correct_picks']}-" . ($cw['total_graded'] - $cw['correct_picks']);
    echo "  💰 Cash Winner: {$cw['username']} ({$cScore}) - Payout: \${$data['pot']['payout_per_winner']}\n";
}
echo "\n";

$result = $service->dispatchUpdate($season, $week, $previewTo, $dryRun, true);

echo "Dispatch Result:\n";
echo "  Mode:       {$result['mode']}\n";
echo "  Sent:       {$result['sent']}\n";
echo "  Failed:     {$result['failed']}\n";
echo "  Recipients: " . implode(', ', $result['recipients']) . "\n\n";

if ($result['mode'] === 'preview') {
    echo "✅ Preview email successfully sent to {$previewTo} for review!\n";
} elseif ($result['mode'] === 'broadcast') {
    echo "✅ Weekly recap successfully broadcast to all {$result['sent']} participants!\n";
} else {
    echo "ℹ️ Dry-run completed. No emails dispatched.\n";
}
