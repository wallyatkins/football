<?php
declare(strict_types=1);

$fontsDir = dirname(__DIR__) . '/public/assets/fonts';
$cssDir = dirname(__DIR__) . '/public/assets/css';
$mirrorFontsDir = dirname(__DIR__) . '/assets/fonts';
$mirrorCssDir = dirname(__DIR__) . '/assets/css';

@mkdir($fontsDir, 0755, true);
@mkdir($cssDir, 0755, true);
@mkdir($mirrorFontsDir, 0755, true);
@mkdir($mirrorCssDir, 0755, true);

$fontUrls = [
    'https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap',
    'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&display=swap'
];

$allCss = "/* Local self-hosted Google Fonts: Inter, JetBrains Mono, Press Start 2P */\n\n";

$downloaded = [];

foreach ($fontUrls as $fUrl) {
    echo "Fetching font CSS from {$fUrl} ...\n";
    $ch = curl_init($fUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    $css = curl_exec($ch);
    curl_close($ch);

    if (empty($css)) {
        echo "Failed to fetch {$fUrl}\n";
        continue;
    }

    // Replace font URLs with local paths
    $css = preg_replace_callback('#url\((https://fonts\.gstatic\.com/[^)]+)\)#i', function($matches) use ($fontsDir, $mirrorFontsDir, &$downloaded) {
        $fontUrl = $matches[1];
        // Parse filename from URL or hash
        $pathParts = pathinfo(parse_url($fontUrl, PHP_URL_PATH));
        $ext = $pathParts['extension'] ?? 'woff2';
        $filename = md5($fontUrl) . '.' . $ext;

        if (!isset($downloaded[$fontUrl])) {
            echo "  Downloading font file: {$fontUrl} -> {$filename} ... ";
            $ch = curl_init($fontUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            $data = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && !empty($data)) {
                file_put_contents($fontsDir . '/' . $filename, $data);
                file_put_contents($mirrorFontsDir . '/' . $filename, $data);
                echo "OK (" . strlen($data) . " bytes)\n";
                $downloaded[$fontUrl] = $filename;
            } else {
                echo "FAILED (HTTP {$httpCode})\n";
            }
        }

        return "url('/assets/fonts/{$filename}')";
    }, $css);

    $allCss .= $css . "\n\n";
}

$cssFile = $cssDir . '/fonts.css';
file_put_contents($cssFile, $allCss);
file_put_contents($mirrorCssDir . '/fonts.css', $allCss);
echo "Wrote fonts.css successfully (" . strlen($allCss) . " bytes)!\n";
