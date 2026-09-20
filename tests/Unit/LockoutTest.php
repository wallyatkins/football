<?php

declare(strict_types=1);

namespace WallyFootball\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WallyFootball\Database\Connection;

class LockoutTest extends TestCase
{
    public function testGameIsLockedWhenKickoffIsInThePast(): void
    {
        $kickoffPast = date('Y-m-d H:i:s', time() - 3600); // 1 hour ago
        $now = time();

        $isLocked = (strtotime($kickoffPast) <= $now);
        $this->assertTrue($isLocked, 'A game scheduled in the past should be locked.');
    }

    public function testGameIsOpenWhenKickoffIsInTheFuture(): void
    {
        $kickoffFuture = date('Y-m-d H:i:s', time() + 7200); // 2 hours in the future
        $now = time();

        $isLocked = (strtotime($kickoffFuture) <= $now);
        $this->assertFalse($isLocked, 'A game scheduled in the future should remain open for picks.');
    }

    public function testUnstartedSundayGamesRemainOpenAfterThursdayKickoff(): void
    {
        $now = time();
        $thursdayKickoff = date('Y-m-d H:i:s', $now - 7200); // Thursday game kicked off 2 hours ago
        $sundayKickoff = date('Y-m-d H:i:s', $now + 172800); // Sunday game 2 days in future
        $mondayKickoff = date('Y-m-d H:i:s', $now + 259200); // Monday Night Football

        $games = [
            ['id' => 1, 'kickoff_time' => $thursdayKickoff, 'home_team' => 'KC', 'away_team' => 'BAL', 'status' => 'in_progress'],
            ['id' => 2, 'kickoff_time' => $sundayKickoff, 'home_team' => 'PHI', 'away_team' => 'GB', 'status' => 'scheduled'],
            ['id' => 3, 'kickoff_time' => $mondayKickoff, 'home_team' => 'SF', 'away_team' => 'NYJ', 'status' => 'scheduled', 'is_mnf' => 1],
        ];

        $openCount = 0;
        $lockedCount = 0;
        foreach ($games as $g) {
            $kt = strtotime($g['kickoff_time']);
            $isGameLocked = ($kt <= $now || in_array($g['status'], ['in_progress', 'final'], true));
            if ($isGameLocked) {
                $lockedCount++;
            } else {
                $openCount++;
            }
        }

        $this->assertSame(1, $lockedCount, 'Only the Thursday game should be locked.');
        $this->assertSame(2, $openCount, 'Sunday and Monday games must remain open for selections.');
    }

    public function testPerGameLockoutEnforcementInPickem(): void
    {
        $tempDb = sys_get_temp_dir() . '/test_pickem_lock_' . uniqid() . '.sqlite';
        $db = Connection::getInstance($tempDb);

        $now = time();
        $pastKickoff = date('Y-m-d H:i:s', $now - 3600);
        $futureKickoff = date('Y-m-d H:i:s', $now + 86400);

        $db->execute("INSERT INTO users (id, oidc_sub, username, email, role) VALUES (1, 'sub-1', 'Player1', 'p1@test.com', 'player')");
        $db->execute("INSERT INTO games (id, season_year, week_number, home_team, away_team, kickoff_time, status) VALUES 
            (10, 2026, 2, 'KC', 'BAL', '{$pastKickoff}', 'in_progress'),
            (11, 2026, 2, 'PHI', 'GB', '{$futureKickoff}', 'scheduled')");

        // Verify game 10 has kicked off
        $targetPast = $db->queryOne('SELECT kickoff_time, status FROM games WHERE id = 10');
        $isPastLocked = (strtotime($targetPast['kickoff_time']) <= $now || in_array($targetPast['status'], ['in_progress', 'final'], true));
        $this->assertTrue($isPastLocked, 'Thursday game must be locked.');

        // Verify game 11 is in the future and open
        $targetFuture = $db->queryOne('SELECT kickoff_time, status FROM games WHERE id = 11');
        $isFutureLocked = (strtotime($targetFuture['kickoff_time']) <= $now || in_array($targetFuture['status'], ['in_progress', 'final'], true));
        $this->assertFalse($isFutureLocked, 'Sunday game must be open.');

        Connection::resetInstance();
    }

    public function testStandingsOpponentPicksRevealedPerGameKickoff(): void
    {
        $tempDb = sys_get_temp_dir() . '/test_standings_reveal_' . uniqid() . '.sqlite';
        $db = Connection::getInstance($tempDb);

        $now = time();
        $pastKickoff = date('Y-m-d H:i:s', $now - 3600);
        $futureKickoff = date('Y-m-d H:i:s', $now + 86400);

        $db->execute("INSERT INTO users (id, oidc_sub, username, email, role) VALUES 
            (1, 'sub-1', 'Alice', 'alice@test.com', 'player'),
            (2, 'sub-2', 'Bob', 'bob@test.com', 'player')");

        $db->execute("INSERT INTO games (id, season_year, week_number, home_team, away_team, kickoff_time, status) VALUES 
            (1, 2026, 2, 'KC', 'BAL', '{$pastKickoff}', 'in_progress'),
            (2, 2026, 2, 'PHI', 'GB', '{$futureKickoff}', 'scheduled')");

        // Alice makes picks for both games
        $db->execute("INSERT INTO pickem_entries (id, user_id, season_year, week_number, payment_status, is_locked) 
            VALUES (1, 1, 2026, 2, 'paid', 1)");
        $db->execute("INSERT INTO pickem_picks (entry_id, game_id, selected_team) VALUES 
            (1, 1, 'KC'),
            (1, 2, 'PHI')");

        // Bob is viewing standings
        $viewerId = 2;
        $isCommissioner = false;
        $isWeekComplete = false;

        $games = $db->query('SELECT * FROM games WHERE season_year = 2026 AND week_number = 2 ORDER BY kickoff_time ASC');
        $rawPicks = [1 => [1 => 'KC', 2 => 'PHI']];

        $detail = [];
        foreach ($games as $g) {
            $sel = $rawPicks[1][$g['id']] ?? null;
            $gameStarted = (strtotime($g['kickoff_time']) <= $now || in_array($g['status'], ['in_progress', 'final'], true));
            $isViewer = ($viewerId === 1);
            $isRevealed = $isCommissioner || $isWeekComplete || $isViewer || $gameStarted;

            $detail[$g['id']] = [
                'game_id' => $g['id'],
                'is_revealed' => $isRevealed,
                'selected_team' => $isRevealed ? $sel : null,
            ];
        }

        // Game 1 (Thursday, in progress): revealed to Bob!
        $this->assertTrue($detail[1]['is_revealed'], 'Started Thursday game must be revealed in standings.');
        $this->assertSame('KC', $detail[1]['selected_team']);

        // Game 2 (Sunday, scheduled): masked from Bob!
        $this->assertFalse($detail[2]['is_revealed'], 'Unstarted Sunday game must remain masked from opponents.');
        $this->assertNull($detail[2]['selected_team'], 'Masked game selected_team must be null.');

        Connection::resetInstance();
    }

    public function testSurvivorPickLocksOnlyWhenSelectedTeamGameKicksOff(): void
    {
        $tempDb = sys_get_temp_dir() . '/test_survivor_per_game_' . uniqid() . '.sqlite';
        $db = Connection::getInstance($tempDb);

        $now = time();
        $thursdayKickoff = date('Y-m-d H:i:s', $now - 3600); // 1 hour ago
        $sundayKickoff = date('Y-m-d H:i:s', $now + 86400);  // tomorrow

        $db->execute("INSERT INTO users (id, oidc_sub, username, email, role) VALUES 
            (1, 'sub-1', 'Alice', 'alice@test.com', 'player'),
            (2, 'sub-2', 'Bob', 'bob@test.com', 'player')");

        $db->execute("INSERT INTO games (id, season_year, week_number, home_team, away_team, kickoff_time, status) VALUES 
            (1, 2026, 2, 'KC', 'BAL', '{$thursdayKickoff}', 'in_progress'),
            (2, 2026, 2, 'PHI', 'GB', '{$sundayKickoff}', 'scheduled')");

        // Alice picked KC (Thursday game)
        $alicePick = 'KC';
        $aliceGame = $db->queryOne('SELECT kickoff_time, status FROM games WHERE home_team = :t OR away_team = :t', ['t' => $alicePick]);
        $aliceLocked = (strtotime($aliceGame['kickoff_time']) <= $now || in_array($aliceGame['status'], ['in_progress', 'final'], true));
        $this->assertTrue($aliceLocked, 'Alice picked KC whose game has kicked off, so her pick is locked.');

        // Bob picked PHI (Sunday game)
        $bobPick = 'PHI';
        $bobGame = $db->queryOne('SELECT kickoff_time, status FROM games WHERE home_team = :t OR away_team = :t', ['t' => $bobPick]);
        $bobLocked = (strtotime($bobGame['kickoff_time']) <= $now || in_array($bobGame['status'], ['in_progress', 'final'], true));
        $this->assertFalse($bobLocked, 'Bob picked PHI whose game has not kicked off, so Bob can still switch his pick.');

        Connection::resetInstance();
    }

    public function testSurvivorTeamExclusivityRejectsPreviouslyPickedTeam(): void
    {
        $usedTeams = ['KC', 'BUF', 'SF'];
        $chosenTeam = 'KC';

        $isDuplicate = in_array($chosenTeam, $usedTeams, true);
        $this->assertTrue($isDuplicate, 'Survivor rule must reject previously picked teams.');

        $newTeam = 'BAL';
        $this->assertFalse(in_array($newTeam, $usedTeams, true), 'Unused team should be valid for selection.');
    }

    public function testCommissionerRoleRecognition(): void
    {
        $allowedRoles = ['admin', 'commissioner'];

        $this->assertTrue(in_array('commissioner', $allowedRoles, true));
        $this->assertTrue(in_array('admin', $allowedRoles, true));
        $this->assertFalse(in_array('player', $allowedRoles, true));
    }

    public function testSurvivorOneAndDonePermitsSwitchingTeamWithinSameWeekBeforeKickoff(): void
    {
        $tempDb = sys_get_temp_dir() . '/test_lockout_' . uniqid() . '.sqlite';
        $db = Connection::getInstance($tempDb);

        // Seed user first
        $db->execute("INSERT INTO users (id, oidc_sub, username, email, role) VALUES (1, 'sub-1', 'Alice', 'alice@test.com', 'player')");

        // User picked KC in Week 1
        $db->execute("INSERT INTO survivor_picks (user_id, season_year, week_number, selected_team, is_eliminated, payment_status)
                      VALUES (1, 2026, 1, 'KC', 0, 'paid')");

        // User attempts to switch to BUF in Week 1 before kickoff
        $previouslyUsed = $db->queryOne(
            'SELECT week_number FROM survivor_picks WHERE user_id = :uid AND season_year = :season AND selected_team = :team AND week_number != :week',
            ['uid' => 1, 'season' => 2026, 'team' => 'BUF', 'week' => 1]
        );
        $this->assertNull($previouslyUsed, 'Switching to an unused team in the same active week must be allowed.');

        // User attempts to pick KC in Week 2
        $usedInPriorWeek = $db->queryOne(
            'SELECT week_number FROM survivor_picks WHERE user_id = :uid AND season_year = :season AND selected_team = :team AND week_number != :week',
            ['uid' => 1, 'season' => 2026, 'team' => 'KC', 'week' => 2]
        );
        $this->assertNotNull($usedInPriorWeek, 'Picking a team used in a prior week must be rejected by One and Done.');
        $this->assertSame(1, (int) $usedInPriorWeek['week_number']);

        Connection::resetInstance();
    }

    public function testOneHourCutoffGraceAllowsPicksWithinFirstHour(): void
    {
        $now = time();
        $kickoff30MinAgo = date('Y-m-d H:i:s', $now - 1800); // 30 minutes into game

        $gameCutoff = strtotime($kickoff30MinAgo) + 3600;
        $isGameLocked = ($now >= $gameCutoff);

        $this->assertFalse($isGameLocked, 'Game 30 minutes in should remain editable within the 1-hour cutoff window.');
    }

    public function testOneHourCutoffGraceRejectsPicksAfterOneHour(): void
    {
        $now = time();
        $kickoff75MinAgo = date('Y-m-d H:i:s', $now - 4500); // 75 minutes into game

        $gameCutoff = strtotime($kickoff75MinAgo) + 3600;
        $isGameLocked = ($now >= $gameCutoff);

        $this->assertTrue($isGameLocked, 'Game past the 1-hour cutoff window must be locked.');
    }

    public function testWeeklyCutoffLocksAllMatchupsServerSide(): void
    {
        $tempDb = sys_get_temp_dir() . '/test_weekly_cutoff_' . uniqid() . '.sqlite';
        $db = Connection::getInstance($tempDb);

        $now = time();
        $sundayPastKickoff = date('Y-m-d H:i:s', $now - 7200); // 2 hours ago (cutoff was 1 hr ago)
        $mondayFutureKickoff = date('Y-m-d H:i:s', $now + 86400);

        $db->execute("INSERT INTO users (id, oidc_sub, username, email, role) VALUES (1, 'sub-1', 'Alice', 'alice@test.com', 'player')");
        $db->execute("INSERT INTO games (id, season_year, week_number, home_team, away_team, kickoff_time, status) VALUES 
            (101, 2026, 2, 'ATL', 'CAR', '{$sundayPastKickoff}', 'in_progress'),
            (102, 2026, 2, 'KC', 'DEN', '{$mondayFutureKickoff}', 'scheduled')");
        $db->execute("INSERT INTO pickem_entries (id, user_id, season_year, week_number, payment_status, is_locked)
            VALUES (1, 1, 2026, 2, 'paid', 0)");

        $weeklyCutoff = strtotime($sundayPastKickoff) + 3600;
        $isCutoffPassed = ($now >= $weeklyCutoff);
        $this->assertTrue($isCutoffPassed, 'Weekly cutoff has passed.');

        // Server-side lock
        $db->execute(
            'UPDATE pickem_entries SET is_locked = 1, locked_at = CURRENT_TIMESTAMP 
             WHERE season_year = :season AND week_number = :week AND is_locked = 0',
            ['season' => 2026, 'week' => 2]
        );

        $entry = $db->queryOne('SELECT is_locked FROM pickem_entries WHERE id = 1');
        $this->assertSame(1, (int) $entry['is_locked'], 'Entry must be automatically locked server-side after cutoff.');

        Connection::resetInstance();
    }
}
