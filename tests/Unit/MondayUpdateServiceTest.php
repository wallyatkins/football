<?php

declare(strict_types=1);

namespace WallyFootball\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WallyFootball\Database\Connection;
use WallyFootball\Services\MondayUpdateService;

class MondayUpdateServiceTest extends TestCase
{
    private Connection $db;
    private string $tempDbPath;

    protected function setUp(): void
    {
        $this->tempDbPath = sys_get_temp_dir() . '/test_monday_' . bin2hex(random_bytes(6)) . '.sqlite';
        $this->db = Connection::getInstance($this->tempDbPath);

        // 1. Users
        $this->db->execute("INSERT INTO users (id, oidc_sub, username, email, role) VALUES (1, 'sub-bart', 'Bart', 'bart@example.com', 'user')");
        $this->db->execute("INSERT INTO users (id, oidc_sub, username, email, role) VALUES (2, 'sub-michelle', 'Michelle', 'michelle@example.com', 'user')");

        // 2. Games (1 final, 1 MNF scheduled)
        $this->db->execute(
            "INSERT INTO games (id, season_year, week_number, home_team, away_team, kickoff_time, status, home_score, away_score, is_mnf)
             VALUES (101, 2026, 2, 'TB', 'CLE', '2026-09-20 17:00:00+00', 'final', 19, 23, 0)"
        );
        $this->db->execute(
            "INSERT INTO games (id, season_year, week_number, home_team, away_team, kickoff_time, status, is_mnf)
             VALUES (102, 2026, 2, 'LAR', 'NYG', '2026-09-21 20:15:00-04', 'scheduled', 1)"
        );

        // 3. Pick'em entries & picks
        $this->db->execute(
            "INSERT INTO pickem_entries (id, user_id, season_year, week_number, mnf_total_points_prediction)
             VALUES (1, 1, 2026, 2, 50)"
        );
        $this->db->execute(
            "INSERT INTO pickem_entries (id, user_id, season_year, week_number, mnf_total_points_prediction)
             VALUES (2, 2, 2026, 2, 67)"
        );

        $this->db->execute("INSERT INTO pickem_picks (entry_id, game_id, selected_team) VALUES (1, 101, 'CLE')");
        $this->db->execute("INSERT INTO pickem_picks (entry_id, game_id, selected_team) VALUES (1, 102, 'NYG')");
        $this->db->execute("INSERT INTO pickem_picks (entry_id, game_id, selected_team) VALUES (2, 101, 'TB')");
        $this->db->execute("INSERT INTO pickem_picks (entry_id, game_id, selected_team) VALUES (2, 102, 'LAR')");

        // 4. Survivor picks
        $this->db->execute(
            "INSERT INTO survivor_picks (user_id, season_year, week_number, selected_team, is_eliminated)
             VALUES (1, 2026, 2, 'CIN', 0)"
        );
        $this->db->execute(
            "INSERT INTO survivor_picks (user_id, season_year, week_number, selected_team, is_eliminated)
             VALUES (2, 2026, 2, 'ATL', 1)"
        );
    }

    protected function tearDown(): void
    {
        Connection::resetInstance();
        if (file_exists($this->tempDbPath)) {
            @unlink($this->tempDbPath);
        }
    }

    public function testGetMondayDataAggregatesCorrectly(): void
    {
        $service = new MondayUpdateService($this->db);
        $data = $service->getMondayData(2026, 2);

        $this->assertEquals(2026, $data['season']);
        $this->assertEquals(2, $data['week']);
        $this->assertEquals(2, $data['games_count']);
        $this->assertCount(1, $data['final_games']);
        $this->assertCount(1, $data['mnf_games']);
        $this->assertEquals('LAR', $data['mnf_game']['home_team']);
        $this->assertEquals('NYG', $data['mnf_game']['away_team']);

        // Check standings
        $this->assertCount(2, $data['standings']);
        $bart = $data['standings'][0];
        $this->assertEquals('Bart', $bart['username']);
        $this->assertEquals(1, $bart['correct_picks']);
        $this->assertEquals('NYG', $bart['mnf_pick']);
        $this->assertEquals(50, $bart['predicted_mnf']);

        $michelle = $data['standings'][1];
        $this->assertEquals('Michelle', $michelle['username']);
        $this->assertEquals(0, $michelle['correct_picks']);
        $this->assertEquals('LAR', $michelle['mnf_pick']);
        $this->assertEquals(67, $michelle['predicted_mnf']);

        // Survivor picks
        $this->assertCount(2, $data['survivor_picks']);
    }

    public function testRenderHtmlProducesExpectedContent(): void
    {
        $service = new MondayUpdateService($this->db);
        $data = $service->getMondayData(2026, 2);
        $html = $service->renderHtml($data);

        $this->assertStringContainsString('Week 2 Monday Update', $html);
        $this->assertStringContainsString('NYG @ LAR', $html);
        $this->assertStringContainsString('The 100% Pool Massacre', $html);
        $this->assertStringContainsString('Bart', $html);
        $this->assertStringContainsString('Michelle', $html);
        $this->assertStringContainsString('football@wallyatkins.com', $html);
        $this->assertStringContainsString('https://football.wallyatkins.com/pickem/standings?week=2', $html);
    }

    public function testRenderTextProducesCleanSummary(): void
    {
        $service = new MondayUpdateService($this->db);
        $data = $service->getMondayData(2026, 2);
        $text = $service->renderText($data);

        $this->assertStringContainsString('ATKINS FOOTBALL POOL — WEEK 2 MONDAY UPDATE', $text);
        $this->assertStringContainsString('NYG @ LAR', $text);
        $this->assertStringContainsString('Bart Atkins', $text);
        $this->assertStringContainsString('Michelle Weaver', $text);
        $this->assertStringContainsString('#1', $text);
    }

    public function testDispatchUpdatePreviewMode(): void
    {
        $sentMails = [];
        $mockMailer = function (string $to, string $subject, string $body, string $headers) use (&$sentMails): bool {
            $sentMails[] = [
                'to' => $to,
                'subject' => $subject,
                'body' => $body,
                'headers' => $headers,
            ];
            return true;
        };

        $service = new MondayUpdateService($this->db, null, 'football@wallyatkins.com', "Wally's Football League", $mockMailer);
        $result = $service->dispatchUpdate(2026, 2, 'wallyatkins@gmail.com', false);

        $this->assertEquals('preview', $result['mode']);
        $this->assertEquals(1, $result['sent']);
        $this->assertEquals(0, $result['failed']);
        $this->assertCount(1, $sentMails);
        $this->assertEquals('wallyatkins@gmail.com', $sentMails[0]['to']);
        $this->assertStringContainsString('[PREVIEW]', $sentMails[0]['subject']);
        $this->assertStringContainsString('Week 2 Monday Huddle', $sentMails[0]['subject']);
        $this->assertStringContainsString('Content-Type: multipart/alternative', $sentMails[0]['headers']);
    }

    public function testDispatchUpdateBroadcastMode(): void
    {
        $sentMails = [];
        $mockMailer = function (string $to, string $subject, string $body, string $headers) use (&$sentMails): bool {
            $sentMails[] = ['to' => $to, 'subject' => $subject];
            return true;
        };

        $service = new MondayUpdateService($this->db, null, 'football@wallyatkins.com', "Wally's Football League", $mockMailer);
        $result = $service->dispatchUpdate(2026, 2, null, false);

        $this->assertEquals('broadcast', $result['mode']);
        $this->assertEquals(2, $result['sent']);
        $this->assertEquals(0, $result['failed']);
        $this->assertCount(2, $sentMails);
        $this->assertStringNotContainsString('[PREVIEW]', $sentMails[0]['subject']);
    }

    public function testDispatchUpdateDryRun(): void
    {
        $sentMails = [];
        $mockMailer = function (string $to, string $subject, string $body, string $headers) use (&$sentMails): bool {
            $sentMails[] = ['to' => $to];
            return true;
        };

        $service = new MondayUpdateService($this->db, null, 'football@wallyatkins.com', "Wally's Football League", $mockMailer);
        $result = $service->dispatchUpdate(2026, 2, 'wallyatkins@gmail.com', true);

        $this->assertEquals('dry_run', $result['mode']);
        $this->assertEquals(1, $result['sent']);
        $this->assertCount(0, $sentMails); // No actual emails sent
    }
}
