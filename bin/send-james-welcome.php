#!/usr/bin/env php
<?php

declare(strict_types=1);

$recipientEmail = '4aburns@gmail.com';
$recipientName = 'James Burns';
$fromEmail = 'football@wallyatkins.com';
$fromName = "Wally Atkins";

$subject = "🏈 Welcome to Atkins Apps — NFL Pick'em & Survivor + WallyMud Web Edition!";

$htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #020617; color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .container { max-width: 640px; margin: 30px auto; padding: 36px 28px; background-color: #0f172a; border: 1px solid #1e293b; border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); }
        .badge { display: inline-block; background-color: #f59e0b; color: #000; font-weight: 900; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; padding: 5px 12px; border-radius: 9999px; margin-bottom: 18px; }
        .title { font-size: 26px; font-weight: 900; line-height: 1.25; color: #ffffff; margin-bottom: 12px; letter-spacing: -0.02em; }
        .subtitle { font-size: 15px; color: #94a3b8; line-height: 1.6; margin-bottom: 24px; }
        .card { background-color: #1e293b77; border: 1px solid #334155; border-radius: 14px; padding: 20px; margin: 20px 0; }
        .card-gold { border-color: #f59e0b66; background: linear-gradient(135deg, rgba(120, 53, 15, 0.25), rgba(15, 23, 42, 0.8)); }
        .card-emerald { border-color: #10b98166; background: linear-gradient(135deg, rgba(6, 78, 59, 0.25), rgba(15, 23, 42, 0.8)); }
        .card-cyan { border-color: #06b6d466; background: linear-gradient(135deg, rgba(22, 78, 99, 0.25), rgba(15, 23, 42, 0.8)); }
        .card-title { font-size: 15px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        .card-gold .card-title { color: #fbbf24; }
        .card-emerald .card-title { color: #34d399; }
        .card-cyan .card-title { color: #38bdf8; }
        .card-text { font-size: 14px; color: #cbd5e1; line-height: 1.6; }
        .btn-group { display: flex; gap: 12px; flex-wrap: wrap; margin: 24px 0 16px; justify-content: center; }
        .btn { display: inline-block; padding: 13px 24px; border-radius: 10px; font-weight: 800; font-size: 14px; text-decoration: none; text-align: center; transition: all 0.2s; }
        .btn-gold { background-color: #f59e0b; color: #000000 !important; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35); }
        .btn-cyan { background-color: #0284c7; color: #ffffff !important; box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35); }
        .btn-outline { background: transparent; color: #cbd5e1 !important; border: 1px solid #475569; }
        .credentials-box { background: #020617; border: 1px dashed #475569; border-radius: 12px; padding: 16px 20px; margin: 20px 0; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 13px; }
        .cred-row { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #1e293b; }
        .cred-row:last-child { border-bottom: 0; }
        .cred-label { color: #94a3b8; }
        .cred-val { color: #f8fafc; font-weight: bold; }
        .footer { font-size: 12px; color: #64748b; line-height: 1.6; border-top: 1px solid #1e293b; padding-top: 24px; margin-top: 32px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <span class="badge">🚀 You're Invited</span>
        <div class="title">Welcome, {$recipientName}!</div>
        <div class="subtitle">
            Your single sign-on account has been established on <strong>WallyAuth</strong>. You now have full access to our private community platforms, including <strong>Atkins Football</strong> and <strong>WallyMud Web Edition</strong>.
        </div>

        <!-- Credentials Box -->
        <div class="credentials-box">
            <div style="font-weight: 800; color: #fbbf24; margin-bottom: 8px; font-family: sans-serif; text-transform: uppercase; font-size: 11px; letter-spacing: 0.08em;">🔑 Your WallyAuth Credentials</div>
            <div class="cred-row">
                <span class="cred-label">Username:</span>
                <span class="cred-val">jamesburns</span>
            </div>
            <div class="cred-row">
                <span class="cred-label">Email:</span>
                <span class="cred-val">{$recipientEmail}</span>
            </div>
            <div class="cred-row">
                <span class="cred-label">Temporary Password:</span>
                <span class="cred-val">BurnsFootball2026!</span>
            </div>
            <div class="cred-row">
                <span class="cred-label">Passwordless:</span>
                <span class="cred-val" style="color: #38bdf8;">Magic Code via Email Supported</span>
            </div>
        </div>

        <p style="font-size: 13px; color: #94a3b8; line-height: 1.5; margin: 0 0 20px 0;">
            <em>Tip:</em> You can log in using either your password or by requesting an instant 6-digit one-time code sent directly to your email. You can update your password, avatar photo, and mobile notification settings anytime at <a href="https://auth.wallyatkins.com/account" style="color: #38bdf8; text-decoration: underline;">auth.wallyatkins.com/account</a>.
        </p>

        <!-- Football App Section -->
        <div class="card card-gold">
            <div class="card-title">🏈 Atkins NFL Pool — football.wallyatkins.com</div>
            <div class="card-text">
                Our NFL community app is live with single-week focused picking and automated live scoring:
                <ul style="margin: 10px 0 10px 20px; padding: 0;">
                    <li style="margin-bottom: 8px;">
                        <strong>Straight Pick'em:</strong> Pick the outright winner (no point spreads) for every game of the week. Games lock individually at kickoff time! Predict the combined Monday Night Football total points as the official tiebreaker. Play for the weekly cash pot ($10 entry) or free for fun.
                    </li>
                    <li style="margin-bottom: 8px;">
                        <strong>Survivor Challenge:</strong> Pick 1 NFL team to win outright each week. The catch: <em>you can only pick each team ONCE all season!</em> Survive if they win; you are eliminated if they lose or tie. Opponents' picks are kept secret until kickoff!
                    </li>
                    <li>
                        <strong>Dynasty Vault:</strong> Browse our complete 20-year fantasy league archive (2003–2026), Head-to-Head Rivalry Matrix, Trophy Room, and Pick'em Honor Roll.
                    </li>
                </ul>
            </div>
        </div>

        <!-- MUD App Section -->
        <div class="card card-cyan">
            <div class="card-title">⚔️ WallyMud Web Edition — mud.wallyatkins.com</div>
            <div class="card-text">
                Step into a classic, multiplayer text-based Multi-User Dungeon (MUD) running directly in your browser:
                <ul style="margin: 10px 0 10px 20px; padding: 0;">
                    <li style="margin-bottom: 8px;">
                        <strong>Authentic Retro MUD:</strong> A faithful, zero-dependency browser port of legendary <strong>Merc Diku MUD 2.2</strong> (1993). Experience retro CRT terminal styling with green/amber scanlines, tab completion, and command history right in your browser.
                    </li>
                    <li style="margin-bottom: 8px;">
                        <strong>Explore &amp; Conquer:</strong> Roam over <strong>2,500 rooms across 40+ classic zones</strong> (starting in the grand City of Midgaard). Encounter over 780 monsters, NPCs, and merchant shops.
                    </li>
                    <li style="margin-bottom: 8px;">
                        <strong>D&amp;D Mechanics:</strong> Authentic THAC0 combat, dice-roll damage, 18 equipment wear slots, magical spells, leveling, and alignment.
                    </li>
                    <li>
                        <strong>Immortal Character Saving:</strong> Because your WallyAuth account is linked, your characters, inventory, and stats are saved permanently to your profile!
                    </li>
                </ul>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="btn-group">
            <a href="https://football.wallyatkins.com" class="btn btn-gold">🏈 Enter Football Pool &rarr;</a>
            <a href="https://mud.wallyatkins.com" class="btn btn-cyan">⚔️ Play WallyMud &rarr;</a>
            <a href="https://auth.wallyatkins.com/account" class="btn btn-outline">⚙️ Account Settings</a>
        </div>

        <div class="footer">
            Sent with pride by Commissioner Wally Atkins via <strong>football@wallyatkins.com</strong>.<br>
            Identity secured by <a href="https://auth.wallyatkins.com" style="color: #94a3b8; text-decoration: underline;">WallyAuth</a> &bull; <a href="https://wallyatkins.com" style="color: #94a3b8; text-decoration: underline;">wallyatkins.com</a>
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
    "X-Mailer: AtkinsWelcomeMailer/1.0",
];

echo "Dispatching welcome email to {$recipientName} <{$recipientEmail}>...\n";
$success = mail($recipientEmail, $subject, $htmlBody, implode("\r\n", $headers));

if ($success) {
    echo "✅ SUCCESS: Welcome email delivered to {$recipientEmail} via {$fromEmail}!\n";
    exit(0);
} else {
    echo "❌ FAILED: mail() returned false.\n";
    exit(1);
}
