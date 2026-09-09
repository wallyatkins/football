#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use WallyFootball\Database\Connection;
use WallyFootball\Services\NewsletterService;

$options = getopt('', ['send', 'dry-run', 'week:', 'season:', 'to:', 'help']);

if (isset($options['help'])) {
    echo "Usage: php bin/send-weekly-newsletter.php [options]\n";
    echo "Options:\n";
    echo "  --dry-run        Preview newsletter and recipients without sending (default)\n";
    echo "  --send           Dispatch weekly newsletter via football@wallyatkins.com\n";
    echo "  --week=<N>       Override week number (defaults to auto-detected active slate)\n";
    echo "  --season=<YYYY>  Override season year (defaults to auto-detected active slate)\n";
    echo "  --to=<email>     Send only to a specific test recipient\n";
    echo "  --help           Show this help text\n";
    exit(0);
}

$isDryRun = !isset($options['send']);
$db = Connection::getInstance();
$service = new NewsletterService($db);

$slate = $service->detectCurrentSlate();
$seasonYear = isset($options['season']) ? (int) $options['season'] : $slate['season_year'];
$weekNumber = isset($options['week']) ? (int) $options['week'] : $slate['week_number'];

$details = $service->getSlateDetails($seasonYear, $weekNumber);

echo "========================================================\n";
echo "🏈 Atkins Football Weekly Gazette Dispatch\n";
echo "Season: {$seasonYear} | Week: {$weekNumber}\n";
echo "Mode: " . ($isDryRun ? "DRY RUN (Preview only)" : "LIVE SEND") . "\n";
echo "Earliest Kickoff: " . ($details['earliest_kickoff_eastern'] ?? 'N/A') . "\n";
echo "Matchup: " . ($details['earliest_matchup'] ?? 'N/A') . " (" . ($details['is_kickoff_today'] ? 'TONIGHT!' : 'Upcoming') . ")\n";
echo "========================================================\n\n";

$recipients = [];

if (isset($options['to']) && trim($options['to']) !== '') {
    $singleEmail = trim($options['to']);
    $recipients[] = [
        'name' => 'Test Recipient',
        'email' => $singleEmail,
        'team_name' => 'Tester',
    ];
    echo "Targeting single test address: {$singleEmail}\n\n";
} else {
    // 1. Gather all fantasy franchise managers
    $franchises = $db->query("SELECT current_name, current_managers, contact_emails FROM fantasy_franchises WHERE contact_emails IS NOT NULL");
    foreach ($franchises as $f) {
        $team = $f['current_name'];
        $mgrString = $f['current_managers'] ?? '';
        $emailString = $f['contact_emails'] ?? '';

        $mgrs = array_map('trim', explode(',', $mgrString));
        $emails = array_map('trim', explode(',', $emailString));

        foreach ($emails as $idx => $email) {
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $cleanEmail = strtolower($email);
            $name = $mgrs[$idx] ?? ($mgrs[0] ?? 'Manager');
            $recipients[$cleanEmail] = [
                'name' => $name,
                'email' => $cleanEmail,
                'team_name' => $team,
            ];
        }
    }

    // 2. Gather registered app users from users table
    $users = $db->query("SELECT username, email FROM users WHERE email IS NOT NULL AND email != ''");
    foreach ($users as $u) {
        $cleanEmail = strtolower(trim($u['email']));
        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        if (!isset($recipients[$cleanEmail])) {
            $recipients[$cleanEmail] = [
                'name' => $u['username'],
                'email' => $cleanEmail,
                'team_name' => null,
            ];
        }
    }
}

$recipientList = array_values($recipients);
echo "Recipients count: " . count($recipientList) . "\n";
foreach ($recipientList as $r) {
    echo "  - {$r['name']} <{$r['email']}>" . ($r['team_name'] ? " [{$r['team_name']}]" : "") . "\n";
}
echo "\n";

$result = $service->sendNewsletter($seasonYear, $weekNumber, $recipientList, $isDryRun);

echo "Dispatch Result:\n";
echo "  Sent:   {$result['sent']}\n";
echo "  Failed: {$result['failed']}\n";
if ($isDryRun) {
    echo "\nTo dispatch live emails to all members, run with --send:\n";
    echo "  php bin/send-weekly-newsletter.php --send\n";
} else {
    echo "\n✅ Successfully dispatched live newsletter emails via football@wallyatkins.com.\n";
}
