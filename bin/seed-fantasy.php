#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use WallyFootball\Database\Connection;

$seedFile = dirname(__DIR__) . '/data/fantasy_seed.sql';

if (!file_exists($seedFile)) {
    echo "❌ Seed file not found at: {$seedFile}\n";
    exit(1);
}

echo "=== Seeding Fantasy Vault from fantasy_seed.sql ===\n";
$conn = Connection::getInstance();
$sql = file_get_contents($seedFile);

if (!$sql) {
    echo "❌ Seed file is empty.\n";
    exit(1);
}

$conn->beginTransaction();
try {
    $conn->getPdo()->exec($sql);
    $conn->commit();
    echo "✅ Successfully seeded 20-year fantasy vault data!\n";
} catch (\Throwable $e) {
    $conn->rollBack();
    echo "❌ Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
