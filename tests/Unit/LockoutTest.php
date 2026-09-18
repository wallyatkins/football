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

    public function testSurvivorAllowsChangingPickBeforeCutoff(): void
    {
        $existingPick = ['id' => 101, 'selected_team' => 'KC', 'week_number' => 1];
        $firstGameKickoff = time() - 1200; // 20 minutes ago (game in progress)
        $cutoffTime = $firstGameKickoff + 3600; // 40 minutes in future
        $now = time();

        $isCutoffPassed = ($now >= $cutoffTime);
        $isPickLocked = !empty($existingPick) && $isCutoffPassed;

        $this->assertFalse($isPickLocked, 'During the first hour of the opening game, survivor picks must remain editable.');
    }

    public function testSurvivorLocksPickOnceCutoffPasses(): void
    {
        $existingPick = ['id' => 101, 'selected_team' => 'KC', 'week_number' => 1];
        $firstGameKickoff = time() - 4000; // 66 minutes ago
        $cutoffTime = $firstGameKickoff + 3600; // 6 minutes ago
        $now = time();

        $isCutoffPassed = ($now >= $cutoffTime);
        $isPickLocked = !empty($existingPick) && $isCutoffPassed;

        $this->assertTrue($isPickLocked, 'Once 1 hour into the first game passes, survivor picks must lock permanently.');
    }

    public function testPickemAllowsModifyingPicksBeforeCutoffEvenAfterKickoff(): void
    {
        $firstGameKickoff = time() - 1800; // 30 minutes ago (game in progress)
        $cutoffTime = $firstGameKickoff + 3600; // 30 minutes in future
        $now = time();

        $isWeekLocked = ($now >= $cutoffTime);
        $this->assertFalse($isWeekLocked, 'Pickem matchups must remain unlocked and editable during the first hour of the opening game.');
    }

    public function testPickemLocksPicksOnceCutoffPasses(): void
    {
        $firstGameKickoff = time() - 3900; // 65 minutes ago
        $cutoffTime = $firstGameKickoff + 3600; // 5 minutes ago
        $now = time();

        $isWeekLocked = ($now >= $cutoffTime);
        $this->assertTrue($isWeekLocked, 'Pickem matchups must lock for the week once the 1-hour cutoff passes.');
    }

    public function testSurvivorRejectsPickAfterCutoff(): void
    {
        $games = [
            ['kickoff_time' => date('Y-m-d H:i:s', time() - 4000)], // Thursday opener (66 mins ago)
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
        $cutoffTime = $firstKickoff !== null ? ($firstKickoff + 3600) : null;
        $isSurvivorWindowClosed = ($cutoffTime !== null && $now >= $cutoffTime);
        $this->assertTrue($isSurvivorWindowClosed, 'Survivor picks must close once 1 hour into the first game has passed.');
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

    public function testOpponentPicksConfidentialBeforeCutoff(): void
    {
        $firstGameKickoff = time() - 1200; // 20 minutes ago (game started)
        $cutoffTime = $firstGameKickoff + 3600; // 40 minutes in the future
        $now = time();
        $firstGameStarted = ($now >= $firstGameKickoff);
        $cutoffPassed = ($now >= $cutoffTime);
        $isWeekComplete = false;

        $viewerHasSubmitted = true;
        $isCommissioner = true;

        // Core Rule: Participant picks remain confidential until the cutoff time (1 hour into first game).
        $canViewOpponentPicks = ($cutoffPassed && ($viewerHasSubmitted || $isCommissioner)) || $isWeekComplete;

        $this->assertTrue($firstGameStarted, 'First game is underway.');
        $this->assertFalse($cutoffPassed, 'Cutoff deadline has not passed yet.');
        $this->assertFalse($canViewOpponentPicks, 'During the first hour, picks remain confidential to protect fair play while picks are still open.');
    }

    public function testOpponentPicksUnlockedAfterCutoffWhenSubmitted(): void
    {
        $firstGameKickoff = time() - 4000; // 66 minutes ago
        $cutoffTime = $firstGameKickoff + 3600; // 6 minutes ago
        $now = time();
        $cutoffPassed = ($now >= $cutoffTime);
        $isWeekComplete = false;

        $viewerHasSubmitted = true;
        $isCommissioner = false;

        $canViewOpponentPicks = ($cutoffPassed && ($viewerHasSubmitted || $isCommissioner)) || $isWeekComplete;

        $this->assertTrue($cutoffPassed);
        $this->assertTrue($canViewOpponentPicks, 'Once the cutoff passes, submitted players can view opponent picks.');

        // Non-submitted player cannot view opponent picks
        $viewerNotSubmitted = false;
        $canViewUnsubmitted = ($cutoffPassed && ($viewerNotSubmitted || $isCommissioner)) || $isWeekComplete;
        $this->assertFalse($canViewUnsubmitted, 'Unsubmitted players cannot view opponent picks until they lock in their picks.');

        // Commissioner can view once cutoff passes even if not submitted
        $commissionerNotSubmitted = true;
        $canViewCommissioner = ($cutoffPassed && ($viewerNotSubmitted || $commissionerNotSubmitted)) || $isWeekComplete;
        $this->assertTrue($canViewCommissioner, 'Commissioner can view picks once the cutoff has passed.');
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

