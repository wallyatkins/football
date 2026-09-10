#!/usr/bin/env php
<?php

declare(strict_types=1);

$fromEmail = 'football@wallyatkins.com';
$fromName = "Wally Atkins";

// Parse CLI options
$options = getopt('', ['dry-run', 'test-to:', 'user:']);
$isDryRun = isset($options['dry-run']);
$testTo = $options['test-to'] ?? null;
$targetUser = $options['user'] ?? null;

$recipients = [
    [
        'name' => 'Bart Atkins',
        'relation' => 'brother',
        'email' => 'bart.atkins@gmail.com',
        'username' => 'bartatkins',
        'password' => 'AtkinsBart2026!',
    ],
    [
        'name' => 'Sha Atkins',
        'relation' => 'uncle',
        'email' => 'sha.atkins@gmail.com',
        'username' => 'shaatkins',
        'password' => 'AtkinsSha2026!',
    ],
];

if ($targetUser) {
    $recipients = array_filter($recipients, fn($r) => strtolower($r['username']) === strtolower($targetUser));
    if (empty($recipients)) {
        fwrite(STDERR, "No matching recipient found for user '{$targetUser}'\n");
        exit(1);
    }
}

echo "============================================================\n";
echo "Dispatching Welcome Emails for Bart & Sha Atkins\n";
echo "From: {$fromName} <{$fromEmail}>\n";
echo "Dry run: " . ($isDryRun ? "YES" : "NO") . "\n";
if ($testTo) {
    echo "Test override recipient: {$testTo}\n";
}
echo "============================================================\n\n";

$sentCount = 0;
$failedCount = 0;

foreach ($recipients as $recipient) {
    $name = $recipient['name'];
    $email = $testTo ?: $recipient['email'];
    $username = $recipient['username'];
    $password = $recipient['password'];
    $relation = $recipient['relation'];

    $subject = "🏈 Welcome to Atkins Family Apps — NFL Pool, WallyMud, and the Family Wiki!";

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #020617; color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .container { max-width: 660px; margin: 30px auto; padding: 36px 28px; background-color: #0f172a; border: 1px solid #1e293b; border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); }
        .badge { display: inline-block; background-color: #f59e0b; color: #000; font-weight: 900; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; padding: 5px 12px; border-radius: 9999px; margin-bottom: 18px; }
        .title { font-size: 26px; font-weight: 900; line-height: 1.25; color: #ffffff; margin-bottom: 12px; letter-spacing: -0.02em; }
        .subtitle { font-size: 15px; color: #94a3b8; line-height: 1.6; margin-bottom: 24px; }
        .card { background-color: #1e293b77; border: 1px solid #334155; border-radius: 14px; padding: 20px; margin: 20px 0; }
        .card-gold { border-color: #f59e0b66; background: linear-gradient(135deg, rgba(120, 53, 15, 0.25), rgba(15, 23, 42, 0.8)); }
        .card-cyan { border-color: #06b6d466; background: linear-gradient(135deg, rgba(22, 78, 99, 0.25), rgba(15, 23, 42, 0.8)); }
        .card-purple { border-color: #a855f766; background: linear-gradient(135deg, rgba(88, 28, 135, 0.25), rgba(15, 23, 42, 0.8)); }
        .card-title { font-size: 15px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        .card-gold .card-title { color: #fbbf24; }
        .card-cyan .card-title { color: #38bdf8; }
        .card-purple .card-title { color: #c084fc; }
        .card-text { font-size: 14px; color: #cbd5e1; line-height: 1.6; }
        .btn-group { display: flex; gap: 10px; flex-wrap: wrap; margin: 24px 0 16px; justify-content: center; }
        .btn { display: inline-block; padding: 12px 20px; border-radius: 10px; font-weight: 800; font-size: 13px; text-decoration: none; text-align: center; transition: all 0.2s; }
        .btn-gold { background-color: #f59e0b; color: #000000 !important; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35); }
        .btn-cyan { background-color: #0284c7; color: #ffffff !important; box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35); }
        .btn-purple { background-color: #9333ea; color: #ffffff !important; box-shadow: 0 4px 14px rgba(147, 51, 234, 0.35); }
        .btn-outline { background: transparent; color: #cbd5e1 !important; border: 1px solid #475569; }
        .credentials-box { background: #020617; border: 1px dashed #475569; border-radius: 12px; padding: 16px 20px; margin: 20px 0; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 13px; }
        .cred-row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #1e293b; }
        .cred-row:last-child { border-bottom: 0; }
        .cred-label { color: #94a3b8; }
        .cred-val { color: #f8fafc; font-weight: bold; }
        .footer { font-size: 12px; color: #64748b; line-height: 1.6; border-top: 1px solid #1e293b; padding-top: 24px; margin-top: 32px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <span class="badge">🚀 Atkins Family Invitation</span>
        <div class="title">Welcome, {$name}!</div>
        <div class="subtitle">
            Your single sign-on account has been established on <strong>WallyAuth</strong>. You now have full access to our private community platforms, including the <strong>Atkins Football Pool</strong>, <strong>WallyMud Web Edition</strong>, and the <strong>Atkins Family Wiki</strong>.
        </div>

        <!-- Credentials Box -->
        <div class="credentials-box">
            <div style="font-weight: 800; color: #fbbf24; margin-bottom: 8px; font-family: sans-serif; text-transform: uppercase; font-size: 11px; letter-spacing: 0.08em;">🔑 Your WallyAuth Single Sign-On</div>
            <div class="cred-row">
                <span class="cred-label">Username:</span>
                <span class="cred-val">{$username}</span>
            </div>
            <div class="cred-row">
                <span class="cred-label">Email:</span>
                <span class="cred-val">{$recipient['email']}</span>
            </div>
            <div class="cred-row">
                <span class="cred-label">Temporary Password:</span>
                <span class="cred-val">{$password}</span>
            </div>
            <div class="cred-row">
                <span class="cred-label">Passwordless:</span>
                <span class="cred-val" style="color: #38bdf8;">Magic Code via Email Supported</span>
            </div>
        </div>

        <p style="font-size: 13px; color: #94a3b8; line-height: 1.5; margin: 0 0 20px 0;">
            <em>Tip:</em> You can log in using either your password or by requesting an instant 6-digit one-time code sent directly to your email. You can update your password, avatar photo, and account settings anytime at <a href="https://auth.wallyatkins.com/account" style="color: #38bdf8; text-decoration: underline;">auth.wallyatkins.com/account</a>.
        </p>

        <!-- Football App Section -->
        <div class="card card-gold">
            <div class="card-title">🏈 Atkins NFL Pool — football.wallyatkins.com</div>
            <div class="card-text">
                Our NFL community platform features live automated scoring, instant grading, and opponent pick tracking:
                <ul style="margin: 10px 0 10px 20px; padding: 0;">
                    <li style="margin-bottom: 8px;">
                        <strong>Straight Pick'em:</strong> Pick the outright winner (no point spreads) for every game of the week. Games lock individually at kickoff time! Predict the combined Monday Night Football total score as the official tiebreaker. Play for the weekly cash pot ($10 entry) or free for fun.
                    </li>
                    <li style="margin-bottom: 8px;">
                        <strong>Survivor Challenge:</strong> Pick 1 NFL team to win outright each week. The catch: <em>you can only pick each team ONCE all season!</em> Survive as long as you can. Opponents' picks remain secret until kickoff!
                    </li>
                    <li>
                        <strong>Dynasty Vault:</strong> Browse our complete 20-year fantasy league archive (2003–2026), Head-to-Head Rivalry Matrix, and Trophy Room.
                    </li>
                </ul>
            </div>
        </div>

        <!-- MUD App Section -->
        <div class="card card-cyan">
            <div class="card-title">⚔️ WallyMud Web Edition — mud.wallyatkins.com</div>
            <div class="card-text">
                Step into a multiplayer text-based Multi-User Dungeon (MUD) running right in your web browser:
                <ul style="margin: 10px 0 10px 20px; padding: 0;">
                    <li style="margin-bottom: 8px;">
                        <strong>Authentic Retro MUD:</strong> A faithful browser port of legendary <strong>Merc Diku MUD 2.2</strong> (1993) with retro CRT scanlines, command history, and tab completion.
                    </li>
                    <li style="margin-bottom: 8px;">
                        <strong>Massive World:</strong> Roam over <strong>2,500 rooms across 40+ classic zones</strong> starting in the City of Midgaard. Encounter over 780 monsters, NPCs, and merchant shops.
                    </li>
                    <li style="margin-bottom: 8px;">
                        <strong>Classic RPG Mechanics:</strong> D&amp;D THAC0 combat, dice-roll damage, 18 equipment slots, spells, leveling, and alignment.
                    </li>
                    <li>
                        <strong>Profile Saving:</strong> Your characters, inventory, and stats are saved permanently to your WallyAuth profile.
                    </li>
                </ul>
            </div>
        </div>

        <!-- Family Wiki Section -->
        <div class="card card-purple">
            <div class="card-title">📜 Atkins Family Wiki — family.wallyatkins.com</div>
            <div class="card-text">
                Our private, collaborative family genealogy and heritage archive:
                <ul style="margin: 10px 0 10px 20px; padding: 0;">
                    <li style="margin-bottom: 8px;">
                        <strong>"A Long Time Ago" Family History:</strong> Complete digital chapters covering ancestral lines including <strong>Atkins, Paynter, Thorowgood, Ellington, Nicholson, Rivers, Yeardley, Custis, and Lawson</strong>.
                    </li>
                    <li style="margin-bottom: 8px;">
                        <strong>Historical Chronicles:</strong> Detailed stories such as <em>"The Long Journey of Scotch John Michie"</em>, 17th-century Virginia arrivals, Sir George Yeardley, military service records, and historic Virginia homesteads.
                    </li>
                    <li>
                        <strong>Collaborative Heritage:</strong> Log in with WallyAuth to read family records, look up ancestral branches, and contribute family memories, photos, and historical notes.
                    </li>
                </ul>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="btn-group">
            <a href="https://football.wallyatkins.com" class="btn btn-gold">🏈 Football Pool &rarr;</a>
            <a href="https://mud.wallyatkins.com" class="btn btn-cyan">⚔️ Play WallyMud &rarr;</a>
            <a href="https://family.wallyatkins.com" class="btn btn-purple">📜 Family Wiki &rarr;</a>
            <a href="https://auth.wallyatkins.com/account" class="btn btn-outline">⚙️ Account Settings</a>
        </div>

        <div class="footer">
            Sent with love and pride by Wally Atkins via <strong>football@wallyatkins.com</strong>.<br>
            Single Sign-On secured by <a href="https://auth.wallyatkins.com" style="color: #94a3b8; text-decoration: underline;">WallyAuth</a> &bull; <a href="https://wallyatkins.com" style="color: #94a3b8; text-decoration: underline;">wallyatkins.com</a>
        </div>
    </div>
</body>
</html>
HTML;

    $textBody = <<<TEXT
============================================================
ATKINS FAMILY APPS INVITATION — WELCOME, {$name}!
============================================================

Your single sign-on account has been established on WallyAuth.
You now have full access to our private community platforms!

YOUR WALLYAUTH CREDENTIALS:
- Username: {$username}
- Email: {$recipient['email']}
- Temporary Password: {$password}
- Passwordless: Magic 6-digit code via email is also supported!
- Account Management: https://auth.wallyatkins.com/account

------------------------------------------------------------
1. 🏈 ATKINS NFL POOL (https://football.wallyatkins.com)
- Straight Pick'em: Pick outright game winners each week. Games lock
  individually at kickoff. Monday Night Football tiebreaker score.
- Survivor Challenge: Pick 1 NFL team to win outright each week. You
  can only pick each team ONCE all season!
- Dynasty Vault: Browse our complete 20-year fantasy archive (2003-2026),
  head-to-head rivalry matrix, and trophy room.

------------------------------------------------------------
2. ⚔️ WALLYMUD WEB EDITION (https://mud.wallyatkins.com)
- Authentic browser port of classic 1993 Merc Diku MUD 2.2 with retro CRT styling.
- Explore 2,500+ rooms across 40+ zones (Midgaard), 780+ monsters/NPCs.
- Classic THAC0 combat, spells, and 18 equipment slots.
- Characters and progress automatically saved to your WallyAuth account.

------------------------------------------------------------
3. 📜 ATKINS FAMILY WIKI (https://family.wallyatkins.com)
- Private family genealogy and heritage archive.
- Complete "A Long Time Ago" history: Atkins, Paynter, Thorowgood,
  Ellington, Nicholson, Rivers, Yeardley, Custis, and Lawson lines.
- Detailed chronicles like "The Long Journey of Scotch John Michie",
  colonial Virginia arrivals, and military service records.
- Instant login with your WallyAuth account to read and contribute!

------------------------------------------------------------
Quick Links:
- Football Pool: https://football.wallyatkins.com
- WallyMud: https://mud.wallyatkins.com
- Family Wiki: https://family.wallyatkins.com
- Account Settings: https://auth.wallyatkins.com/account

Sent with love by Wally Atkins • football@wallyatkins.com
============================================================
TEXT;

    $boundary = "==Family_Welcome_" . md5((string) microtime()) . "==";
    $headers = [
        "From: {$fromName} <{$fromEmail}>",
        "Reply-To: {$fromEmail}",
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        "X-Mailer: AtkinsFamilyWelcome/1.0",
    ];

    $body = "--{$boundary}\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: 7bit\r\n\r\n"
          . $textBody . "\r\n\r\n"
          . "--{$boundary}\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: 7bit\r\n\r\n"
          . $htmlBody . "\r\n\r\n"
          . "--{$boundary}--";

    echo "Sending welcome email to {$name} ({$relation}) <{$email}>...\n";

    if ($isDryRun) {
        echo "  [DRY-RUN] Prepared welcome message successfully.\n\n";
        $sentCount++;
        continue;
    }

    $success = mail($email, $subject, $body, implode("\r\n", $headers));
    if ($success) {
        echo "  ✅ SUCCESS: Delivered welcome message to {$email}!\n\n";
        $sentCount++;
    } else {
        echo "  ❌ FAILED: mail() call returned false for {$email}.\n\n";
        $failedCount++;
    }
}

echo "Summary: {$sentCount} sent, {$failedCount} failed.\n";
exit($failedCount > 0 ? 1 : 0);
