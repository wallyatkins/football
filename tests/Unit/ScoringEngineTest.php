<?php
declare(strict_types=1);

namespace WallyFootball\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WallyFootball\Database\Connection;
use WallyFootball\Services\ScoringEngine;

class ScoringEngineTest extends TestCase
{
    private Connection $db;
    private ScoringEngine $engine;

    protected function setUp(): void
    {
        // Use an isolated temporary SQLite database for tests
        $tempDb = sys_get_temp_dir() . '/test_football_' . uniqid() . '.sqlite';
        $this->db = Connection::getInstance($tempDb);
        $this->engine = new ScoringEngine($this->db);

        // Seed users
        $this->db->execute("INSERT INTO users (id, oidc_sub, username, email, role) VALUES 
            (1, 'sub-1', 'Alice', 'alice@test.com', 'player'),
            (2, 'sub-2', 'Bob', 'bob@test.com', 'player'),
            (3, 'sub-3', 'Charlie', 'charlie@test.com', 'player')");

        // Seed games for Season 2026, Week 1
        $this->db->execute("INSERT INTO games (id, season_year, week_number, home_team, away_team, kickoff_time, status, home_score, away_score, is_mnf) VALUES 
            (101, 2026, 1, 'KC', 'BAL', '2026-09-03 20:20:00', 'final', 27, 20, 0),
            (102, 2026, 1, 'PHI', 'GB', '2026-09-04 20:15:00', 'final', 34, 29, 0),
            (103, 2026, 1, 'SF', 'NYJ', '2026-09-07 20:15:00', 'final', 32, 19, 1)");
    }

    protected function tearDown(): void
    {
        Connection::resetInstance();
    }

    public function testWeeklyStandingsAndTiebreakerResolution(): void
    {
        // Alice: Paid, Picked KC (win), PHI (win), SF (win) -> 3 correct. MNF prediction: 50 (actual: 32+19=51, delta: 1)
        $this->db->execute("INSERT INTO pickem_entries (id, user_id, season_year, week_number, mnf_total_points_prediction, payment_status) 
            VALUES (1, 1, 2026, 1, 50, 'paid')");
        $this->db->execute("INSERT INTO pickem_picks (entry_id, game_id, selected_team) VALUES 
            (1, 101, 'KC'), (1, 102, 'PHI'), (1, 103, 'SF')");

        // Bob: Paid, Picked KC (win), PHI (win), SF (win) -> 3 correct. MNF prediction: 45 (actual: 51, delta: 6)
        $this->db->execute("INSERT INTO pickem_entries (id, user_id, season_year, week_number, mnf_total_points_prediction, payment_status) 
            VALUES (2, 2, 2026, 1, 45, 'paid')");
        $this->db->execute("INSERT INTO pickem_picks (entry_id, game_id, selected_team) VALUES 
            (2, 101, 'KC'), (2, 102, 'PHI'), (2, 103, 'SF')");

        // Charlie: Pending, Picked BAL (loss), GB (loss), SF (win) -> 1 correct.
        $this->db->execute("INSERT INTO pickem_entries (id, user_id, season_year, week_number, mnf_total_points_prediction, payment_status) 
            VALUES (3, 3, 2026, 1, 40, 'pending')");
        $this->db->execute("INSERT INTO pickem_picks (entry_id, game_id, selected_team) VALUES 
            (3, 101, 'BAL'), (3, 102, 'GB'), (3, 103, 'SF')");

        $standings = $this->engine->getWeeklyStandings(2026, 1);

        $this->assertCount(3, $standings);
        // Rank 1 should be Alice due to smaller MNF tiebreaker delta (1 vs 6)
        $this->assertSame('Alice', $standings[0]['username']);
        $this->assertSame(3, $standings[0]['correct_picks']);
        $this->assertSame(1, $standings[0]['tiebreaker_delta']);
        $this->assertSame(1, $standings[0]['rank']);

        // Rank 2 should be Bob
        $this->assertSame('Bob', $standings[1]['username']);
        $this->assertSame(3, $standings[1]['correct_picks']);
        $this->assertSame(6, $standings[1]['tiebreaker_delta']);
        $this->assertSame(2, $standings[1]['rank']);

        // Rank 3 should be Charlie
        $this->assertSame('Charlie', $standings[2]['username']);
        $this->assertSame(1, $standings[2]['correct_picks']);
    }

    public function testPotCalculationExcludesPendingEntries(): void
    {
        // 2 paid entries @ $10 = $20 pot. Alice wins.
        $this->db->execute("INSERT INTO pickem_entries (id, user_id, season_year, week_number, mnf_total_points_prediction, payment_status) 
            VALUES (1, 1, 2026, 1, 50, 'paid'), (2, 2, 2026, 1, 45, 'paid'), (3, 3, 2026, 1, 50, 'pending')");
        $this->db->execute("INSERT INTO pickem_picks (entry_id, game_id, selected_team) VALUES 
            (1, 101, 'KC'), (2, 101, 'BAL'), (3, 101, 'KC')");

        $pot = $this->engine->calculateWeeklyPot(2026, 1, 10.0);

        $this->assertEquals(20.0, $pot['total_pot']);
        $this->assertSame(2, $pot['verified_entries_count']);
        $this->assertSame(3, $pot['total_entries_count']);
        $this->assertCount(1, $pot['winners']);
        $this->assertSame('Alice', $pot['winners'][0]['username']);
        $this->assertEquals(20.0, $pot['payout_per_winner']);
    }

    public function testSurvivorEliminationGrading(): void
    {
        // Alice picked KC (KC won 27-20 vs BAL) -> Should survive
        $this->db->execute("INSERT INTO survivor_picks (user_id, season_year, week_number, selected_team, is_eliminated, payment_status)
            VALUES (1, 2026, 1, 'KC', 0, 'paid')");

        // Bob picked BAL (BAL lost 20-27 to KC) -> Should be eliminated
        $this->db->execute("INSERT INTO survivor_picks (user_id, season_year, week_number, selected_team, is_eliminated, payment_status)
            VALUES (2, 2026, 1, 'BAL', 0, 'paid')");

        $eliminatedCount = $this->engine->gradeSurvivorWeek(2026, 1);
        $this->assertSame(1, $eliminatedCount);

        $alicePick = $this->db->queryOne("SELECT is_eliminated FROM survivor_picks WHERE user_id = 1");
        $bobPick = $this->db->queryOne("SELECT is_eliminated FROM survivor_picks WHERE user_id = 2");

        $this->assertEquals(0, $alicePick['is_eliminated']);
        $this->assertEquals(1, $bobPick['is_eliminated']);
    }
}
