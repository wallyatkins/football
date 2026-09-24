<?php
declare(strict_types=1);

$teamsFile = dirname(__DIR__) . '/src/Support/teams.json';
$teams = json_decode(file_get_contents($teamsFile), true);

foreach ($teams as $abbr => &$team) {
    $filename = strtolower($abbr) . '.png';
    // If the old logo was pointing to another team abbreviation (e.g. OAK -> lv.png)
    if (preg_match('#/([a-z0-9_-]+)\.png$#i', $team['logo'], $m)) {
        $filename = strtolower($m[1]) . '.png';
    }
    $team['logo'] = '/assets/logos/' . $filename;
}
unset($team);

file_put_contents($teamsFile, json_encode($teams, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo "Updated src/Support/teams.json to local logo paths!\n";
