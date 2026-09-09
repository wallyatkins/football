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

    public function testSurvivorStandingsNotEnteredStatus(): void
    {
        // Alice has paid $10 upfront entry fee
        $this->db->execute("INSERT INTO survivor_entries (user_id, season_year, payment_status, is_eliminated)
            VALUES (1, 2026, 'paid', 0)");
        $this->db->execute("INSERT INTO survivor_picks (user_id, season_year, week_number, selected_team, is_eliminated, payment_status)
            VALUES (1, 2026, 1, 'KC', 0, 'paid')");

        // Bob has NOT paid entry fee
        // Charlie has NOT paid entry fee

        $standings = $this->engine->getSurvivorStandings(2026);

        // Find Alice, Bob, Charlie in standings
        $byUser = [];
        foreach ($standings as $s) {
            $byUser[$s['username']] = $s;
        }

        $this->assertSame('alive', $byUser['Alice']['status']);
        $this->assertTrue($byUser['Alice']['is_alive']);

        $this->assertSame('not_entered', $byUser['Bob']['status']);
        $this->assertFalse($byUser['Bob']['is_alive']);

        $this->assertSame('not_entered', $byUser['Charlie']['status']);
        $this->assertFalse($byUser['Charlie']['is_alive']);
    }

    public function testRandomDesignatedTiebreakerGameResolution(): void
    {
        // Reassign tiebreaker from 103 (SF vs NYJ) to 102 (PHI vs GB, score 34-29 = 63 pts)
        $this->db->execute("UPDATE games SET is_mnf = 0 WHERE id = 103");
        $this->db->execute("UPDATE games SET is_mnf = 1 WHERE id = 102");

        // Alice: 3 correct picks, predicted 60 pts -> delta = |60 - 63| = 3
        $this->db->execute("INSERT INTO pickem_entries (id, user_id, season_year, week_number, mnf_total_points_prediction, payment_status) 
            VALUES (1, 1, 2026, 1, 60, 'paid')");
        $this->db->execute("INSERT INTO pickem_picks (entry_id, game_id, selected_team) VALUES 
            (1, 101, 'KC'), (1, 102, 'PHI'), (1, 103, 'SF')");

        // Bob: 3 correct picks, predicted 65 pts -> delta = |65 - 63| = 2
        $this->db->execute("INSERT INTO pickem_entries (id, user_id, season_year, week_number, mnf_total_points_prediction, payment_status) 
            VALUES (2, 2, 2026, 1, 65, 'paid')");
        $this->db->execute("INSERT INTO pickem_picks (entry_id, game_id, selected_team) VALUES 
            (2, 101, 'KC'), (2, 102, 'PHI'), (2, 103, 'SF')");

        $standings = $this->engine->getWeeklyStandings(2026, 1);

        // Bob should rank #1 because his delta is 2 (vs Alice's delta 3) for the random tiebreaker game
        $this->assertSame('Bob', $standings[0]['username']);
        $this->assertSame(2, $standings[0]['tiebreaker_delta']);
        $this->assertSame(63, $standings[0]['actual_mnf']);

        $this->assertSame('Alice', $standings[1]['username']);
        $this->assertSame(3, $standings[1]['tiebreaker_delta']);
        $this->assertSame(63, $standings[1]['actual_mnf']);
    }

    public function testSurvivorFreeTierPlayAndCashPotIsolation(): void
    {
        // Alice: Verified Paid ($10), Picked KC (won) -> Alive, Cash Eligible
        $this->db->execute("INSERT INTO survivor_entries (user_id, season_year, payment_status, is_eliminated)
            VALUES (1, 2026, 'paid', 0)");
        $this->db->execute("INSERT INTO survivor_picks (user_id, season_year, week_number, selected_team, is_eliminated, payment_status)
            VALUES (1, 2026, 1, 'KC', 0, 'paid')");

        // Bob: Free Tier (unpaid), Picked KC (won) -> Alive, Free Tier
        $this->db->execute("INSERT INTO survivor_entries (user_id, season_year, payment_status, is_eliminated)
            VALUES (2, 2026, 'unpaid', 0)");
        $this->db->execute("INSERT INTO survivor_picks (user_id, season_year, week_number, selected_team, is_eliminated, payment_status)
            VALUES (2, 2026, 1, 'KC', 0, 'unpaid')");

        // Charlie: Free Tier (unpaid), Picked BAL (lost) -> Eliminated, Free Tier
        $this->db->execute("INSERT INTO survivor_entries (user_id, season_year, payment_status, is_eliminated, elimination_week)
            VALUES (3, 2026, 'unpaid', 1, 1)");
        $this->db->execute("INSERT INTO survivor_picks (user_id, season_year, week_number, selected_team, is_eliminated, payment_status)
            VALUES (3, 2026, 1, 'BAL', 1, 'unpaid')");

        $standings = $this->engine->getSurvivorStandings(2026);
        $byUser = [];
        foreach ($standings as $s) {
            $byUser[$s['username']] = $s;
        }

        // Alice: Alive and Cash
        $this->assertSame('alive', $byUser['Alice']['status']);
        $this->assertTrue($byUser['Alice']['is_alive']);
        $this->assertTrue($byUser['Alice']['is_cash_eligible']);
        $this->assertSame('cash', $byUser['Alice']['tier']);

        // Bob: Alive and Free
        $this->assertSame('alive', $byUser['Bob']['status']);
        $this->assertTrue($byUser['Bob']['is_alive']);
        $this->assertFalse($byUser['Bob']['is_cash_eligible']);
        $this->assertSame('free', $byUser['Bob']['tier']);

        // Charlie: Eliminated and Free
        $this->assertSame('eliminated', $byUser['Charlie']['status']);
        $this->assertTrue($byUser['Charlie']['is_eliminated']);
        $this->assertFalse($byUser['Charlie']['is_cash_eligible']);
        $this->assertSame('free', $byUser['Charlie']['tier']);

        // Check Survivor Pot calculation
        $pot = $this->engine->calculateSurvivorPot(2026, 10.0);
        $this->assertEquals(10.0, $pot['total_pot'], 'Only Alice ($10) should be in the cash pot');
        $this->assertSame(1, $pot['cash_entries_count']);
        $this->assertSame(2, $pot['free_entries_count']);
        $this->assertSame(1, $pot['alive_cash_count']);
        $this->assertSame(1, $pot['alive_free_count']);
        $this->assertCount(1, $pot['active_cash_contenders']);
        $this->assertSame('Alice', $pot['active_cash_contenders'][0]['username']);
    }

    public function testSurvivorCurrentWeekPickMaskingForOpponents(): void
    {
        // Add a future game in week 2 that has not kicked off yet
        $this->db->execute("INSERT INTO games (id, season_year, week_number, home_team, away_team, kickoff_time, status) VALUES 
            (201, 2026, 2, 'BUF', 'MIA', '2026-09-15 20:15:00', 'scheduled')");

        // Alice (user 1) picks BUF for week 2
        $this->db->execute("INSERT INTO survivor_picks (user_id, season_year, week_number, selected_team, is_eliminated, payment_status)
            VALUES (1, 2026, 2, 'BUF', 0, 'paid')");

        // Bob (user 2) views standings for week 2
        // Alice's week 2 pick should be HIDDEN from Bob
        $standingsForBob = $this->engine->getSurvivorStandings(2026, 2, 2);
        $aliceRowForBob = array_values(array_filter($standingsForBob, fn($s) => $s['user_id'] === 1))[0];
        $this->assertSame('🔒 Hidden', $aliceRowForBob['history'][0]['display_team']);
        $this->assertTrue($aliceRowForBob['history'][0]['is_hidden']);
        $this->assertNotContains('BUF', $aliceRowForBob['teams_used'], 'Unstarted pick should not appear in opponent teams_used');

        // Alice (user 1) views her own standings for week 2
        // Alice should see her own pick 'BUF'
        $standingsForAlice = $this->engine->getSurvivorStandings(2026, 1, 2);
        $aliceRowForAlice = array_values(array_filter($standingsForAlice, fn($s) => $s['user_id'] === 1))[0];
        $this->assertSame('BUF', $aliceRowForAlice['history'][0]['display_team']);
        $this->assertFalse($aliceRowForAlice['history'][0]['is_hidden']);
        $this->assertContains('BUF', $aliceRowForAlice['teams_used']);

        // Now simulate game kickoff: status = 'in_progress'
        $this->db->execute("UPDATE games SET status = 'in_progress' WHERE id = 201");
        $standingsAfterKickoff = $this->engine->getSurvivorStandings(2026, 2, 2);
        $aliceRowAfterKickoff = array_values(array_filter($standingsAfterKickoff, fn($s) => $s['user_id'] === 1))[0];
        $this->assertSame('BUF', $aliceRowAfterKickoff['history'][0]['display_team'], 'Kickoff unlocks pick visibility');
        $this->assertFalse($aliceRowAfterKickoff['history'][0]['is_hidden']);
    }
}
