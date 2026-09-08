#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use WallyFootball\Database\Connection;

echo "=== Running Database Migrations ===\n";
$db = Connection::getInstance();
echo "Schema verified and up to date!\n";
