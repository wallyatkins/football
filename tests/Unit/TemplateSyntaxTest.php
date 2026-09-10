<?php

declare(strict_types=1);

namespace WallyFootball\Tests\Unit;

use PHPUnit\Framework\TestCase;

class TemplateSyntaxTest extends TestCase
{
    public function testAllPhpFilesHaveValidSyntax(): void
    {
        $root = dirname(__DIR__, 2);
        $directories = ['src', 'templates', 'bin'];

        foreach ($directories as $dir) {
            $path = $root . '/' . $dir;
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $filePath = $file->getRealPath();
                    $output = [];
                    $returnCode = 0;
                    exec('php -l ' . escapeshellarg($filePath) . ' 2>&1', $output, $returnCode);
                    $this->assertSame(0, $returnCode, "PHP syntax error in {$filePath}: " . implode("\n", $output));
                }
            }
        }
    }
}
