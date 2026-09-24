<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$pdo = WallyFootball\Database\Connection::getInstance()->getPdo();

$user = $pdo->query("SELECT id, username, email, has_seen_pickem_intro, has_seen_survivor_intro FROM users WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
echo "USER 1: " . json_encode($user) . PHP_EOL;

$surv = $pdo->query("SELECT * FROM survivor_picks WHERE user_id = 1")->fetchAll(PDO::FETCH_ASSOC);
echo "SURVIVOR PICKS COUNT: " . count($surv) . PHP_EOL;
foreach ($surv as $p) {
    echo "  Week {$p['week']}: team={$p['team']}, status={$p['status']}, handicap=" . ($p['is_handicap'] ?? 0) . PHP_EOL;
}

$pickCount = $pdo->query("SELECT COUNT(*) FROM pickem_picks pp JOIN pickem_entries pe ON pp.entry_id = pe.id WHERE pe.user_id = 1")->fetchColumn();
echo "PICKEM PICKS COUNT: {$pickCount}" . PHP_EOL;
