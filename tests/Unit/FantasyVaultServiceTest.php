<?php

declare(strict_types=1);

namespace WallyFootball\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WallyFootball\Database\Connection;
use WallyFootball\Services\FantasyVaultService;

class FantasyVaultServiceTest extends TestCase
{
    private Connection $db;
    private FantasyVaultService $service;

    protected function setUp(): void
    {
        $tempDb = sys_get_temp_dir() . '/test_vault_' . uniqid() . '.sqlite';
        $this->db = Connection::getInstance($tempDb);
        $this->service = new FantasyVaultService($this->db);

        // Seed sample franchises
        $this->db->execute("INSERT INTO fantasy_franchises 
            (id, current_name, current_managers, wins, losses, ties, win_pct, points_for, points_against, titles_count, avg_finish) VALUES
            (1, 'Archetypo', 'Wally Atkins', 143, 176, 0, 0.448, 28904.8, 29266.9, 0, 6.7),
            (3, 'Wonder Twins', 'Tamara Atkins', 171, 154, 0, 0.526, 29934.9, 29377.8, 4, 5.1),
            (7, 'Schadenfreude', 'Joseph Findley', 182, 138, 1, 0.569, 31053.4, 29373.3, 2, 4.2),
            (10, 'BUCBALL', 'Brian Bretzius', 177, 146, 0, 0.548, 30688.6, 29530.7, 2, 4.6),
            (12, 'Mazies Gang', 'Michaux Early', 189, 132, 0, 0.589, 30717.0, 28770.0, 3, 4.0)");

        // Seed seasons
        $this->db->execute("INSERT INTO fantasy_seasons 
            (year, champion_franchise_id, champion_name, runner_up_franchise_id, runner_up_name, notes) VALUES
            (2019, 12, 'Mazies Gang', 1, 'Archetypo', 'Michaux title'),
            (2023, 10, 'BUCBALL', 1, 'Archetypo', 'Wally runner-up finish'),
            (2024, 3, 'Wonder Twins', 7, 'Schadenfreude', 'Tamara 4th ring')");

        // Seed matchups
        $this->db->execute("INSERT INTO fantasy_matchups 
            (season_year, week_number, away_franchise_id, away_team_name, away_score, home_franchise_id, home_team_name, home_score, winner_franchise_id, point_diff, is_playoff) VALUES
            (2023, 11, 3, 'Wonder Twins', 69.9, 1, 'Archetypo', 83.9, 1, 14.0, 0),
            (2024, 11, 3, 'Wonder Twins', 94.7, 1, 'Archetypo', 74.7, 3, 20.0, 0),
            (2019, 5, 1, 'Archetypo', 170.2, 12, 'Mazies Gang', 110.0, 1, 60.2, 0)");

        // Seed standings
        $this->db->execute("INSERT INTO fantasy_standings
            (season_year, franchise_id, team_name, wins, losses, ties, win_pct, points_for, points_against, rank) VALUES
            (2024, 3, 'Wonder Twins', 14, 4, 0, 0.778, 1726.4, 1528.3, 1),
            (2024, 1, 'Archetypo', 9, 9, 0, 0.500, 1716.4, 1791.6, 6)");
    }

    protected function tearDown(): void
    {
        Connection::resetInstance();
    }

    public function testGetAllFranchisesReturnsOrderedByTitlesAndWins(): void
    {
        $franchises = $this->service->getAllFranchises();
        $this->assertCount(5, $franchises);
        $this->assertSame(3, (int)$franchises[0]['id']); // Wonder Twins (4 titles)
        $this->assertSame(12, (int)$franchises[1]['id']); // Mazies Gang (3 titles)
        $this->assertSame(7, (int)$franchises[2]['id']); // Schadenfreude (2 titles)
        $this->assertSame(10, (int)$franchises[3]['id']); // BUCBALL (2 titles)
        $this->assertSame(1, (int)$franchises[4]['id']); // Archetypo (0 titles)
    }

    public function testHallOfFameAndRingLeaders(): void
    {
        $hof = $this->service->getHallOfFame();
        $this->assertCount(3, $hof['champions']);
        $this->assertSame(2024, (int)$hof['champions'][0]['year']);
        $this->assertSame('Wonder Twins', $hof['champions'][0]['champion_name']);

        $this->assertCount(4, $hof['ring_leaders']); // Wonder Twins, Mazies, Schadenfreude, BUCBALL
        $this->assertSame(4, (int)$hof['ring_leaders'][0]['titles_count']);
    }

    public function testRecordBookHighestScores(): void
    {
        $records = $this->service->getRecordBook(5);
        $this->assertNotEmpty($records['highest_scores']);
        $this->assertEqualsWithDelta(170.2, (float)$records['highest_scores'][0]['score'], 0.01);
        $this->assertSame('Archetypo', $records['highest_scores'][0]['team']);
    }

    public function testHeadToHeadRivalryCalculation(): void
    {
        $rivalry = $this->service->getRivalry(1, 3); // Archetypo (1) vs Wonder Twins (3)
        $this->assertSame(2, $rivalry['total_games']);
        $this->assertSame(1, $rivalry['winsA']); // Archetypo won 2023 Wk 11
        $this->assertSame(1, $rivalry['winsB']); // Wonder Twins won 2024 Wk 11
        $this->assertSame(0, $rivalry['ties']);
        $this->assertEqualsWithDelta(158.6, $rivalry['pointsA'], 0.1); // 83.9 + 74.7
        $this->assertEqualsWithDelta(164.6, $rivalry['pointsB'], 0.1); // 69.9 + 94.7
    }

    public function testSeasonDetailsAndWeeklyMatchups(): void
    {
        $season = $this->service->getSeason(2024);
        $this->assertSame(2024, (int)$season['season']['year']);
        $this->assertSame('Wonder Twins', $season['season']['champion_name']);
        $this->assertCount(2, $season['standings']);
        $this->assertArrayHasKey(11, $season['matchups_by_week']);
        $this->assertCount(1, $season['matchups_by_week'][11]);
    }
}
