<?php

declare(strict_types=1);

namespace WallyFootball\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WallyFootball\Database\Connection;
use WallyFootball\Services\SportsDataService;

class SportsDataServiceTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $tempDb = sys_get_temp_dir() . '/test_football_sports_' . uniqid() . '.sqlite';
        $this->db = Connection::getInstance($tempDb);
    }

    public function testEnsureTiebreakerSelectedMarksLatestGame(): void
    {
        $this->db->execute("
            INSERT INTO games (season_year, week_number, home_team, away_team, kickoff_time, is_mnf, status)
            VALUES 
                (2026, 1, 'KC', 'BAL', '2026-09-10 17:00:00+00:00', 0, 'scheduled'),
                (2026, 1, 'SF', 'LAR', '2026-09-14 20:15:00+00:00', 0, 'scheduled')
        ");

        $service = new SportsDataService($this->db);
        $service->ensureTiebreakerSelected(2026, 1);

        $mnfGame = $this->db->queryOne('SELECT * FROM games WHERE season_year = 2026 AND week_number = 1 AND is_mnf = 1');
        $this->assertNotNull($mnfGame);
        $this->assertSame('LAR', $mnfGame['away_team']);
        $this->assertSame('SF', $mnfGame['home_team']);
    }

    public function testDeduplicateWeekRemovesDuplicates(): void
    {
        $this->db->execute("
            INSERT INTO games (season_year, week_number, home_team, away_team, kickoff_time, is_mnf, status)
            VALUES 
                (2026, 1, 'KC', 'BAL', '2026-09-10 17:00:00+00:00', 0, 'scheduled'),
                (2026, 1, 'KC', 'BAL', '2026-09-10 17:00:00+00:00', 0, 'scheduled')
        ");

        $service = new SportsDataService($this->db);
        $service->deduplicateWeek(2026, 1);

        $count = (int) $this->db->queryValue('SELECT COUNT(*) FROM games WHERE season_year = 2026 AND week_number = 1');
        $this->assertSame(1, $count);
    }

    public function testSyncIfNeededHonorsFreshCache(): void
    {
        $cacheDir = dirname(__DIR__, 2) . '/data';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }
        $cacheFile = "{$cacheDir}/.last_sync_2026_99";
        file_put_contents($cacheFile, (string) time());

        $service = new SportsDataService($this->db);
        $result = $service->syncIfNeeded(2026, 99, 3600);
        $this->assertNull($result);

        @unlink($cacheFile);
    }
}
