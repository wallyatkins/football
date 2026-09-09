#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use WallyFootball\Database\Connection;

$options = getopt('', ['send', 'dry-run', 'help']);

if (isset($options['help'])) {
    echo "Usage: php bin/send-welcome-emails.php [--dry-run|--send]\n";
    echo "  --dry-run   Preview all welcome emails and recipient routing without sending (default)\n";
    echo "  --send      Dispatch welcome emails via football@wallyatkins.com\n";
    exit(0);
}

$isDryRun = !isset($options['send']);

// Verified 12 Teams Roster with Managers & Contact Emails
$leagueMembers = [
    [
        'team_id' => 1,
        'team_name' => 'Archetypo',
        'manager' => 'Wally Atkins',
        'email' => 'wallyatkins@gmail.com',
        'is_family' => true,
        'titles' => 0,
        'record' => '143-176',
    ],
    [
        'team_id' => 2,
        'team_name' => 'No Sweat',
        'manager' => 'Aidan Feather',
        'email' => 'aidanfeather757@gmail.com',
        'is_family' => true,
        'titles' => 3,
        'record' => '174-148',
    ],
    [
        'team_id' => 2,
        'team_name' => 'No Sweat',
        'manager' => 'Richie Richardson',
        'email' => 'harold.richardson@me.com',
        'is_family' => false,
        'titles' => 3,
        'record' => '174-148',
    ],
    [
        'team_id' => 3,
        'team_name' => 'Wonder Twins',
        'manager' => 'Tamara Atkins',
        'email' => 'tamarapatkins@gmail.com',
        'is_family' => true,
        'titles' => 4,
        'record' => '171-154',
    ],
    [
        'team_id' => 4,
        'team_name' => 'Injuries R Us',
        'manager' => 'Allen Baugh',
        'email' => 'allen.baugh@yahoo.com',
        'is_family' => false,
        'titles' => 3,
        'record' => '142-180',
    ],
    [
        'team_id' => 4,
        'team_name' => 'Injuries R Us',
        'manager' => 'Heath Atkins',
        'email' => 'heath.d.atkins@gmail.com',
        'is_family' => true,
        'titles' => 3,
        'record' => '142-180',
    ],
    [
        'team_id' => 5,
        'team_name' => 'cocoa is too tuff for u',
        'manager' => 'Divyesh Vallabh',
        'email' => 'deevallabh23@yahoo.com',
        'is_family' => false,
        'titles' => 0,
        'record' => '110-209',
    ],
    [
        'team_id' => 5,
        'team_name' => 'cocoa is too tuff for u',
        'manager' => 'Joshua Matlis',
        'email' => 'joshua.matlis@cesjds.org',
        'is_family' => false,
        'titles' => 0,
        'record' => '110-209',
    ],
    [
        'team_id' => 6,
        'team_name' => 'Wicked Noles',
        'manager' => 'Alexander Vazquez',
        'email' => 'bases1616@gmail.com',
        'is_family' => false,
        'titles' => 1,
        'record' => '149-170',
    ],
    [
        'team_id' => 7,
        'team_name' => 'Schadenfreude',
        'manager' => 'Joseph Findley',
        'email' => 'whitehootie@yahoo.com',
        'is_family' => false,
        'titles' => 2,
        'record' => '182-138',
    ],
    [
        'team_id' => 8,
        'team_name' => 'Viridis Bay Packers',
        'manager' => 'Jerry King',
        'email' => 'gerald.king@l-3com.com',
        'is_family' => false,
        'titles' => 1,
        'record' => '150-169',
    ],
    [
        'team_id' => 8,
        'team_name' => 'Viridis Bay Packers',
        'manager' => 'Logan Atkins',
        'email' => 'm.logan.atkins@gmail.com',
        'is_family' => true,
        'titles' => 1,
        'record' => '150-169',
    ],
    [
        'team_id' => 9,
        'team_name' => 'Drunken Squids',
        'manager' => 'Chris Yates',
        'email' => 'roundn3rd@gmail.com',
        'is_family' => false,
        'titles' => 2,
        'record' => '165-154',
    ],
    [
        'team_id' => 10,
        'team_name' => 'BUCBALL',
        'manager' => 'Brian Bretzius',
        'email' => 'bretzius@hotmail.com',
        'is_family' => false,
        'titles' => 2,
        'record' => '177-146',
    ],
    [
        'team_id' => 11,
        'team_name' => 'Single With Children',
        'manager' => 'Kevin Feather',
        'email' => 'kevinfeather@hotmail.com',
        'is_family' => true,
        'titles' => 4,
        'record' => '172-148',
    ],
    [
        'team_id' => 12,
        'team_name' => 'Mazies Gang',
        'manager' => 'Michaux Early',
        'email' => 'mecoastie13@gmail.com',
        'is_family' => false,
        'titles' => 3,
        'record' => '189-132',
    ],
];

echo "========================================================\n";
echo "🏈 Atkins Football Welcome Invitation & Onboarding Dispatch\n";
echo "Sender: football@wallyatkins.com\n";
echo "Mode: " . ($isDryRun ? "DRY RUN (Preview only)" : "LIVE DISPATCH") . "\n";
echo "========================================================\n\n";

$fromEmail = 'football@wallyatkins.com';
$fromName = "Wally's Football League";
$sentCount = 0;
$skippedCount = 0;

foreach ($leagueMembers as $m) {
    $managerName = $m['manager'];
    $recipientEmail = $m['email'];
    $teamName = $m['team_name'];
    $titles = $m['titles'];
    $record = $m['record'];
    $isFamily = $m['is_family'];

    $subject = "🏈 Welcome to Atkins Football — 20-Year Dynasty Vault & Week 1 Kickoff TONIGHT!";
    
    $authNotice = $isFamily 
        ? "<p style='font-size: 13px; color: #38bdf8; background: #0c4a6e22; border: 1px solid #0284c755; padding: 10px 14px; border-radius: 8px;'><strong>Family Account Notice:</strong> You already have an active WallyAuth account. Simply use your existing WallyAuth password or request a sign-in code at login.</p>"
        : "<p style='font-size: 13px; color: #a1a1aa; background: #27272a55; border: 1px solid #3f3f46; padding: 10px 14px; border-radius: 8px;'><strong>Sign-In Notice:</strong> When signing in for the first time, select <em>Sign In with WallyAuth</em> using this email address (<strong>{$recipientEmail}</strong>). You can request an instant magic code sent directly to your email.</p>";

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #020617; color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .container { max-width: 620px; margin: 30px auto; padding: 32px 24px; background-color: #0f172a; border: 1px solid #1e293b; border-radius: 16px; }
        .badge { display: inline-block; background-color: #f59e0b; color: #000; font-weight: 800; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; padding: 4px 10px; border-radius: 9999px; margin-bottom: 16px; }
        .title { font-size: 24px; font-weight: 900; line-height: 1.25; color: #ffffff; margin-bottom: 12px; }
        .subtitle { font-size: 15px; color: #94a3b8; line-height: 1.6; margin-bottom: 24px; }
        .box { background-color: #1e293b55; border: 1px solid #334155; border-radius: 12px; padding: 18px; margin: 20px 0; }
        .box-title { font-size: 14px; font-weight: 700; color: #fbbf24; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }
        .box-text { font-size: 14px; color: #cbd5e1; line-height: 1.5; }
        .btn-wrapper { text-align: center; margin: 28px 0; }
        .btn { display: inline-block; background-color: #f59e0b; color: #000000 !important; text-decoration: none; padding: 14px 28px; border-radius: 10px; font-weight: 800; font-size: 15px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); }
        .footer { font-size: 12px; color: #64748b; line-height: 1.5; border-top: 1px solid #1e293b; padding-top: 20px; margin-top: 32px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <span class="badge">🏈 Remember The Titans &bull; 2003–2026</span>
        <div class="title">Welcome to the Atkins Football Portal, {$managerName}!</div>
        <div class="subtitle">
            After more than 20 years together on CBS Sports, our entire league history has officially been integrated into our private portal at <strong>football.wallyatkins.com</strong>.
        </div>

        <div class="box">
            <div class="box-title">🏛️ Your 20-Year Dynasty Vault is Live</div>
            <div class="box-text">
                Every single matchup across 23 seasons—all <strong>1,818 games since 2003</strong>—is now archived in our private Dynasty Vault:<br><br>
                &bull; <strong>Franchise:</strong> {$teamName}<br>
                &bull; <strong>Career Record:</strong> {$record}<br>
                &bull; <strong>Championship Rings:</strong> {$titles} " . ($titles > 0 ? str_repeat('💍', $titles) : '🛡️') . "<br><br>
                Check out the <strong>Head-to-Head Rivalry Matrix</strong> to see your all-time record, average scores, and historic games against every single owner in the league!
            </div>
        </div>

        <div class="box" style="border-color: #f59e0b55; background-color: #78350f15;">
            <div class="box-title" style="color: #fbbf24;">⚡ NFL Week 1 Kickoff is TONIGHT!</div>
            <div class="box-text">
                <strong>Baltimore Ravens at Kansas City Chiefs</strong> kicks off <strong>TONIGHT at 8:20 PM EDT</strong>.<br><br>
                1. <strong>Weekly Straight Pick'em:</strong> Lock in your straight-up winner for BAL @ KC before kickoff. Games lock individually at kickoff time!<br>
                2. <strong>Season Survivor Pool:</strong> Make your Week 1 survival pick before game time.<br>
                3. <strong>CBS Fantasy Roster:</strong> Double-check your fantasy lineup before 8:20 PM if you're starting any Ravens or Chiefs!
            </div>
        </div>

        {$authNotice}

        <div class="btn-wrapper">
            <a href="https://football.wallyatkins.com/fantasy/vault" class="btn">Explore the Dynasty Vault & Make Week 1 Picks &rarr;</a>
        </div>

        <div class="footer">
            Sent by Commissioner Wally Atkins via <strong>football@wallyatkins.com</strong>.<br>
            Protected by WallyAuth Single Sign-On &bull; <a href="https://football.wallyatkins.com" style="color: #94a3b8;">football.wallyatkins.com</a>
        </div>
    </div>
</body>
</html>
HTML;

    $headers = [
        "From: {$fromName} <{$fromEmail}>",
        "Reply-To: {$fromEmail}",
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "X-Mailer: AtkinsFootballMailer/1.0",
    ];

    if ($isDryRun) {
        echo "[DRY-RUN] To: {$managerName} <{$recipientEmail}> | Franchise: {$teamName} | Family: " . ($isFamily ? "YES (Preserve Auth)" : "NO (New Auth)") . "\n";
        echo "          Subject: {$subject}\n\n";
    } else {
        $sent = mail($recipientEmail, $subject, $htmlBody, implode("\r\n", $headers));
        if ($sent) {
            echo "✅ [SENT] To: {$managerName} <{$recipientEmail}>\n";
            $sentCount++;
        } else {
            echo "❌ [FAILED] To: {$managerName} <{$recipientEmail}>\n";
        }
    }
}

if ($isDryRun) {
    echo "========================================================\n";
    echo "Summary: 16 email dispatches prepared for all 12 teams.\n";
    echo "To dispatch live emails, run: php bin/send-welcome-emails.php --send\n";
    echo "========================================================\n";
} else {
    echo "\nCompleted: {$sentCount} emails sent via football@wallyatkins.com.\n";
}
