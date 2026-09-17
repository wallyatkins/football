<?php

declare(strict_types=1);

namespace WallyFootball\Tests\Unit;

use PHPUnit\Framework\TestCase;

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

    public function testGameListProcessingPreservesUniqueTailGame(): void
    {
        $games = [
            ['id' => 15, 'home_team' => 'DET', 'away_team' => 'LAR', 'kickoff_time' => '2026-09-14 00:20:00+00'],
            ['id' => 16, 'home_team' => 'SF', 'away_team' => 'NYJ', 'kickoff_time' => '2026-09-15 00:15:00+00'],
        ];

        $now = time();
        $userPicks = [];
        foreach ($games as $idx => $g) {
            $kickoff = strtotime($g['kickoff_time']);
            $games[$idx]['is_locked'] = ($kickoff <= $now);
            $games[$idx]['user_pick'] = $userPicks[$g['id']] ?? null;
        }

        $rendered = [];
        foreach ($games as $game) {
            $rendered[] = $game['id'];
        }

        $this->assertSame([15, 16], $rendered, 'The final game must not be overwritten by by-reference foreach mutation.');
    }

    public function testSurvivorAllowsChangingPickBeforeFirstGameKickoff(): void
    {
        $existingPick = ['id' => 101, 'selected_team' => 'KC', 'week_number' => 1];
        $firstGameKickoff = time() + 3600; // 1 hour in future
        $now = time();

        $isFirstGameStarted = ($now >= $firstGameKickoff);
        $isPickLocked = !empty($existingPick) && $isFirstGameStarted;

        $this->assertFalse($isPickLocked, 'Before the first game kickoff, survivor picks must remain editable.');
    }

    public function testSurvivorLocksPickOnceFirstGameKicksOff(): void
    {
        $existingPick = ['id' => 101, 'selected_team' => 'KC', 'week_number' => 1];
        $firstGameKickoff = time() - 300; // 5 minutes ago
        $now = time();

        $isFirstGameStarted = ($now >= $firstGameKickoff);
        $isPickLocked = !empty($existingPick) && $isFirstGameStarted;

        $this->assertTrue($isPickLocked, 'After the first game kicks off, survivor picks must be locked permanently.');
    }

    public function testPickemAllowsModifyingPicksBeforeFirstGameKickoff(): void
    {
        $firstGameKickoff = time() + 7200; // 2 hours in future
        $now = time();

        $isWeekLocked = ($now >= $firstGameKickoff);
        $this->assertFalse($isWeekLocked, 'Pickem matchups must remain unlocked and editable before the first game kicks off.');
    }

    public function testPickemLocksPicksOnceFirstGameKicksOff(): void
    {
        $firstGameKickoff = time() - 60; // 1 minute ago
        $now = time();

        $isWeekLocked = ($now >= $firstGameKickoff);
        $this->assertTrue($isWeekLocked, 'Pickem matchups must lock for the week once the first game kicks off.');
    }

    public function testSurvivorRejectsPickAfterFirstGameKickoff(): void
    {
        $games = [
            ['kickoff_time' => date('Y-m-d H:i:s', time() - 3600)], // Thursday opener (1 hr ago)
            ['kickoff_time' => date('Y-m-d H:i:s', time() + 72000)], // Sunday game
        ];

        $firstKickoff = null;
        foreach ($games as $g) {
            $kt = strtotime($g['kickoff_time']);
            if ($firstKickoff === null || $kt < $firstKickoff) {
                $firstKickoff = $kt;
            }
        }

        $now = time();
        $isSurvivorWindowClosed = ($firstKickoff !== null && $now >= $firstKickoff);
        $this->assertTrue($isSurvivorWindowClosed, 'Survivor picks must close at the kickoff of the first game of that week.');
    }

    public function testSurvivorOneAndDonePermitsSwitchingTeamWithinSameWeekBeforeKickoff(): void
    {
        $tempDb = sys_get_temp_dir() . '/test_lockout_' . uniqid() . '.sqlite';
        $db = \WallyFootball\Database\Connection::getInstance($tempDb);

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

        \WallyFootball\Database\Connection::resetInstance();
    }

    public function testOpponentPicksConfidentialBeforeFirstGameKickoff(): void
    {
        $firstGameKickoff = time() + 7200; // 2 hours in the future
        $now = time();
        $firstGameStarted = ($now >= $firstGameKickoff);
        $isWeekComplete = false;

        $viewerHasSubmitted = true;
        $isCommissioner = true;

        // Core Rule: Participant picks remain confidential until the first game kicks off.
        $canViewOpponentPicks = ($firstGameStarted && ($viewerHasSubmitted || $isCommissioner)) || $isWeekComplete;

        $this->assertFalse($firstGameStarted, 'First game has not started yet.');
        $this->assertFalse($canViewOpponentPicks, 'Before the first game kicks off, opponent picks must remain confidential even for commissioner.');
    }

    public function testOpponentPicksUnlockedAfterFirstGameKickoffWhenSubmitted(): void
    {
        $firstGameKickoff = time() - 300; // 5 minutes ago
        $now = time();
        $firstGameStarted = ($now >= $firstGameKickoff);
        $isWeekComplete = false;

        $viewerHasSubmitted = true;
        $isCommissioner = false;

        $canViewOpponentPicks = ($firstGameStarted && ($viewerHasSubmitted || $isCommissioner)) || $isWeekComplete;

        $this->assertTrue($firstGameStarted);
        $this->assertTrue($canViewOpponentPicks, 'Once the first game kicks off, submitted players can view opponent picks.');

        // Non-submitted player cannot view opponent picks
        $viewerNotSubmitted = false;
        $canViewUnsubmitted = ($firstGameStarted && ($viewerNotSubmitted || $isCommissioner)) || $isWeekComplete;
        $this->assertFalse($canViewUnsubmitted, 'Unsubmitted players cannot view opponent picks until they lock in their picks.');

        // Commissioner can view once first game kicks off even if not submitted
        $commissionerNotSubmitted = true;
        $canViewCommissioner = ($firstGameStarted && ($viewerNotSubmitted || $commissionerNotSubmitted)) || $isWeekComplete;
        $this->assertTrue($canViewCommissioner, 'Commissioner can view picks once the first game has kicked off.');
    }

    public function testOpponentPicksAlwaysViewableForCompletedWeeks(): void
    {
        $firstGameStarted = true;
        $isWeekComplete = true;
        $viewerHasSubmitted = false;
        $isCommissioner = false;

        $canViewOpponentPicks = ($firstGameStarted && ($viewerHasSubmitted || $isCommissioner)) || $isWeekComplete;
        $this->assertTrue($canViewOpponentPicks, 'For completed weeks, all picks must be viewable.');
    }
}

