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
}
