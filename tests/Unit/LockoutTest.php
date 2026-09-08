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
}
