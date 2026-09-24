<?php
declare(strict_types=1);

$teamsFile = dirname(__DIR__) . '/src/Support/teams.json';
$teams = json_decode(file_get_contents($teamsFile), true);

$destDir = dirname(__DIR__) . '/public/assets/logos';
if (!is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}

$mirrorDir = dirname(__DIR__) . '/assets/logos';
if (!is_dir($mirrorDir)) {
    mkdir($mirrorDir, 0755, true);
}

echo "Downloading " . count($teams) . " team logos...\n";

foreach ($teams as $abbr => $team) {
    $url = $team['logo'];
    $filename = strtolower($abbr) . '.png';
    $targetPath = $destDir . '/' . $filename;
    $mirrorPath = $mirrorDir . '/' . $filename;

    echo "Fetching {$abbr} from {$url} ... ";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && !empty($data)) {
        file_put_contents($targetPath, $data);
        file_put_contents($mirrorPath, $data);
        echo "OK (" . strlen($data) . " bytes)\n";
    } else {
        echo "FAILED (HTTP {$httpCode})\n";
    }
}

echo "All done!\n";
