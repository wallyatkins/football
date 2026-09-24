<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$pdo = WallyFootball\Database\Connection::getInstance()->getPdo();

echo "=== Clearing User 1 Picks & Resetting Intros for Testing ===\n";

// Ensure backup exists first
$backupPath = dirname(__DIR__) . '/data/backup_user1_picks.sql';
if (!file_exists($backupPath)) {
    echo "Warning: Backup {$backupPath} not found! Aborting.\n";
    exit(1);
}

// 1. Clear survivor picks for user 1
$pdo->exec("DELETE FROM survivor_picks WHERE user_id = 1");
$pdo->exec("DELETE FROM survivor_entries WHERE user_id = 1");

// 2. Clear pickem picks and entries for user 1
$entryIds = $pdo->query("SELECT id FROM pickem_entries WHERE user_id = 1")->fetchAll(PDO::FETCH_COLUMN);
if (!empty($entryIds)) {
    $inClause = implode(',', array_map('intval', $entryIds));
    $pdo->exec("DELETE FROM pickem_picks WHERE entry_id IN ({$inClause})");
    $pdo->exec("DELETE FROM pickem_entries WHERE id IN ({$inClause})");
}

// 3. Reset intro flags so user sees the onboarding modals
$pdo->exec("UPDATE users SET has_seen_pickem_intro = 0, has_seen_survivor_intro = 0 WHERE id = 1");

echo "Cleared survivor picks, pickem picks, and reset intro flags to 0 for user 1!\n";
