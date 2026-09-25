<?php

declare(strict_types=1);

namespace WallyFootball\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WallyFootball\Controllers\SurvivorController;
use WallyFootball\Database\Connection;

class SurvivorHandicapTest extends TestCase
{
    private string $tempDb;
    private Connection $db;
    private SurvivorController $controller;

    protected function setUp(): void
    {
        $this->tempDb = tempnam(sys_get_temp_dir(), 'test_survivor_') . '.sqlite';
        Connection::resetInstance();
        $this->db = Connection::getInstance($this->tempDb);

        // Pre-create test user
        $this->db->execute(
            'INSERT INTO users (id, oidc_sub, username, email, role, created_at)
             VALUES (10, "sub_10", "tester10", "tester10@example.com", "player", CURRENT_TIMESTAMP)'
        );

        $this->controller = new SurvivorController($this->db);

        $_SESSION = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        Connection::resetInstance();
        if (file_exists($this->tempDb)) {
            @unlink($this->tempDb);
        }
        $_SESSION = [];
        $_POST = [];
    }

    public function testUnauthorizedReturns401(): void
    {
        $_SESSION = [];

        // Catch output and HTTP response code
        ob_start();
        try {
            $this->controller->burnHandicap();
        } catch (\Throwable) {
        }
        $out = ob_get_clean();

        $this->assertSame(401, http_response_code());
        $data = json_decode($out, true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('log in', $data['error']);
    }

    public function testNoTeamSpecifiedReturns400(): void
    {
        $_SESSION['user'] = ['id' => 10, 'username' => 'tester10'];
        $_POST = ['season_year' => 2026, 'current_week' => 3, 'burned_team' => ''];

        ob_start();
        try {
            $this->controller->burnHandicap();
        } catch (\Throwable) {
        }
        $out = ob_get_clean();

        $this->assertSame(400, http_response_code());
        $data = json_decode($out, true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('No team specified', $data['error']);
    }

    public function testLateJoinerBurnsHandicapSuccessfully(): void
    {
        $_SESSION['user'] = ['id' => 10, 'username' => 'tester10'];

        // Missed weeks for week 3: weeks 1 and 2
        // First burn: KC for Week 1
        $_POST = ['season_year' => 2026, 'current_week' => 3, 'burned_team' => 'KC'];
        ob_start();
        try {
            $this->controller->burnHandicap();
        } catch (\Throwable) {
        }
        $out1 = ob_get_clean();

        $data1 = json_decode($out1, true);
        $this->assertTrue($data1['success']);
        $this->assertSame('KC', $data1['burned_team']);
        $this->assertSame(1, $data1['week_number']);
        $this->assertSame(['KC'], $data1['burned_teams']);
        $this->assertSame([2], $data1['remaining_missed_weeks']);

        // Second burn: BAL for Week 2
        $_POST = ['season_year' => 2026, 'current_week' => 3, 'burned_team' => 'BAL'];
        ob_start();
        try {
            $this->controller->burnHandicap();
        } catch (\Throwable) {
        }
        $out2 = ob_get_clean();

        $data2 = json_decode($out2, true);
        $this->assertTrue($data2['success']);
        $this->assertSame('BAL', $data2['burned_team']);
        $this->assertSame(2, $data2['week_number']);
        $this->assertSame(['KC', 'BAL'], $data2['burned_teams']);
        $this->assertSame([], $data2['remaining_missed_weeks']);

        // Check database
        $picks = $this->db->query(
            'SELECT * FROM survivor_picks WHERE user_id = 10 AND season_year = 2026 ORDER BY week_number ASC'
        );
        $this->assertCount(2, $picks);
        $this->assertSame(1, (int) $picks[0]['week_number']);
        $this->assertSame('KC', $picks[0]['selected_team']);
        $this->assertSame(0, (int) $picks[0]['is_eliminated']);

        $this->assertSame(2, (int) $picks[1]['week_number']);
        $this->assertSame('BAL', $picks[1]['selected_team']);
        $this->assertSame(0, (int) $picks[1]['is_eliminated']);
    }

    public function testCannotBurnSameTeamTwice(): void
    {
        $_SESSION['user'] = ['id' => 10, 'username' => 'tester10'];

        $_POST = ['season_year' => 2026, 'current_week' => 3, 'burned_team' => 'SF'];
        ob_start();
        try {
            $this->controller->burnHandicap();
        } catch (\Throwable) {
        }
        ob_end_clean();

        // Try to burn SF again
        $_POST = ['season_year' => 2026, 'current_week' => 3, 'burned_team' => 'SF'];
        ob_start();
        try {
            $this->controller->burnHandicap();
        } catch (\Throwable) {
        }
        $out = ob_get_clean();

        $this->assertSame(400, http_response_code());
        $data = json_decode($out, true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('already used SF', $data['error']);
    }

    public function testNoRemainingMissedWeeksReturns400(): void
    {
        $_SESSION['user'] = ['id' => 10, 'username' => 'tester10'];

        // Week 1 has no missed weeks (< 1)
        $_POST = ['season_year' => 2026, 'current_week' => 1, 'burned_team' => 'DET'];
        ob_start();
        try {
            $this->controller->burnHandicap();
        } catch (\Throwable) {
        }
        $out = ob_get_clean();

        $this->assertSame(400, http_response_code());
        $data = json_decode($out, true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('No remaining missed weeks', $data['error']);
    }

    public function testCannotBurnIfEliminated(): void
    {
        $_SESSION['user'] = ['id' => 10, 'username' => 'tester10'];
        $this->db->execute(
            'INSERT INTO survivor_entries (user_id, season_year, payment_status, is_eliminated)
             VALUES (10, 2026, "paid", 1)'
        );

        $_POST = ['season_year' => 2026, 'current_week' => 3, 'burned_team' => 'BUF'];
        ob_start();
        try {
            $this->controller->burnHandicap();
        } catch (\Throwable) {
        }
        $out = ob_get_clean();

        $this->assertSame(400, http_response_code());
        $data = json_decode($out, true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('eliminated', $data['error']);
    }

    public function testBurnHandicapSupportsEliminatedTeamAlias(): void
    {
        $_SESSION['user'] = ['id' => 10, 'username' => 'tester10'];
        $_POST = [
            'season_year' => 2026,
            'current_week' => 3,
            'week_number' => 1,
            'eliminated_team' => 'KC',
        ];

        ob_start();
        try {
            $this->controller->burnHandicap();
        } catch (\Throwable) {
        }
        $out = ob_get_clean();

        $data = json_decode($out, true);
        $this->assertTrue($data['success']);
        $this->assertSame('KC', $data['burned_team']);
        $this->assertSame(1, $data['week_number']);
    }
}
