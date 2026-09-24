<?php

declare(strict_types=1);

/**
 * Script to restore Wally's original picks to production from the backup.
 */

$sshHost = "multili2@wallyatkins.com";
$remoteDb = "/home4/multili2/public_html/football/data/football.sqlite";
$remoteBackup = "/home4/multili2/public_html/football/data/backup_user1_picks.sql";

echo "=== Restoring User 1 Picks on Production ===\n";

$restoreCmd = <<<CMD
if [ -f "{$remoteBackup}" ]; then
    sqlite3 {$remoteDb} < {$remoteBackup}
    echo "Picks restored successfully from {$remoteBackup}!"
else
    echo "Error: Backup file {$remoteBackup} not found!"
    exit 1
fi
CMD;

passthru("ssh {$sshHost} " . escapeshellarg($restoreCmd));
