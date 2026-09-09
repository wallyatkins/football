<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use WallyFootball\Database\Connection;

$conn = Connection::getInstance();

$teams = [
    1 => [
        'name' => 'Archetypo',
        'managers' => 'Wally Atkins',
        'emails' => 'wallyatkins@gmail.com',
    ],
    2 => [
        'name' => 'No Sweat',
        'managers' => 'Aidan Feather, Richie Richardson',
        'emails' => 'aidanfeather757@gmail.com, harold.richardson@me.com',
    ],
    3 => [
        'name' => 'Wonder Twins',
        'managers' => 'Tamara Atkins',
        'emails' => 'tamarapatkins@gmail.com',
    ],
    4 => [
        'name' => 'Injuries R Us',
        'managers' => 'Allen Baugh, Heath Atkins',
        'emails' => 'allen.baugh@yahoo.com, heath.d.atkins@gmail.com',
    ],
    5 => [
        'name' => 'cocoa is too tuff for u',
        'managers' => 'Divyesh Vallabh, Joshua Matlis',
        'emails' => 'deevallabh23@yahoo.com, joshua.matlis@cesjds.org',
    ],
    6 => [
        'name' => 'Wicked Noles',
        'managers' => 'Alexander Vazquez',
        'emails' => 'bases1616@gmail.com',
    ],
    7 => [
        'name' => 'Schadenfreude',
        'managers' => 'Joseph Findley',
        'emails' => 'whitehootie@yahoo.com',
    ],
    8 => [
        'name' => 'Viridis Bay Packers',
        'managers' => 'Jerry King, Logan Atkins',
        'emails' => 'gerald.king@l-3com.com, m.logan.atkins@gmail.com',
    ],
    9 => [
        'name' => 'Drunken Squids',
        'managers' => 'Chris Yates',
        'emails' => 'roundn3rd@gmail.com',
    ],
    10 => [
        'name' => 'BUCBALL',
        'managers' => 'brian bretzius',
        'emails' => 'bretzius@hotmail.com',
    ],
    11 => [
        'name' => 'Single With Children',
        'managers' => 'Kevin Feather',
        'emails' => 'kevinfeather@hotmail.com, kwfeather@yahoo.com',
    ],
    12 => [
        'name' => 'Mazies Gang',
        'managers' => 'michaux early',
        'emails' => 'mecoastie13@gmail.com',
    ],
];

// Add contact_emails column if it doesn't exist
try {
    $driver = $conn->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $cols = array_column($conn->query("PRAGMA table_info(fantasy_franchises)"), "name");
        if (!in_array('contact_emails', $cols, true)) {
            $conn->getPdo()->exec("ALTER TABLE fantasy_franchises ADD COLUMN contact_emails TEXT DEFAULT NULL;");
        }
    } else {
        $conn->getPdo()->exec("ALTER TABLE fantasy_franchises ADD COLUMN IF NOT EXISTS contact_emails TEXT DEFAULT NULL;");
    }
} catch (\Throwable $e) {
    echo "Notice: " . $e->getMessage() . "\n";
}

foreach ($teams as $id => $data) {
    $conn->execute(
        "UPDATE fantasy_franchises SET current_name = ?, current_managers = ?, contact_emails = ? WHERE id = ?",
        [$data['name'], $data['managers'], $data['emails'], $id]
    );
    echo "Updated Franchise #{$id}: {$data['name']} ({$data['managers']}) -> {$data['emails']}\n";
}

echo "✅ All 12 franchises updated with 2026 team names and contact emails.\n";
