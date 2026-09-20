<?php

declare(strict_types=1);

namespace WallyFootball\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WallyFootball\Database\Connection;
use WallyFootball\Services\DailyPickemDigestService;
use WallyFootball\Services\ScoringEngine;
use WallyFootball\Services\SportsDataService;

class DailyPickemDigestServiceTest extends TestCase
{
    private Connection $db;
    private string $tempDbPath;
    private int $testSeason = 2099;
    private int $testWeek = 1;

    protected function setUp(): void
    {
        $this->tempDbPath = sys_get_temp_dir() . '/test_digest_' . bin2hex(random_bytes(6)) . '.sqlite';
        $this->db = Connection::getInstance($this->tempDbPath);

        // Seed users
        $this->db->execute("INSERT INTO users (id, oidc_sub, username, email, role) VALUES 
            (1, 'sub-1', 'Alice', 'alice@example.com', 'player'),
            (2, 'sub-2', 'Bob', 'bob@example.com', 'player')");

        // Seed games (away @ home)
        $this->db->execute("INSERT INTO games (id, season_year, week_number, home_team, away_team, kickoff_time, status, home_score, away_score, is_mnf) VALUES 
            (901, {$this->testSeason}, {$this->testWeek}, 'KC', 'BAL', '2099-09-10 00:20:00+00', 'final', 27, 20, 0),
            (902, {$this->testSeason}, {$this->testWeek}, 'PHI', 'GB', '2099-09-11 00:15:00+00', 'final', 34, 29, 0),
            (903, {$this->testSeason}, {$this->testWeek}, 'SF', 'LAR', datetime('now', '+2 hours'), 'scheduled', null, null, 1)");

        // Seed locked pickem entries & picks
        $this->db->execute("INSERT INTO pickem_entries (id, user_id, season_year, week_number, mnf_total_points_prediction, payment_status, is_locked) VALUES 
            (10, 1, {$this->testSeason}, {$this->testWeek}, 45, 'paid', 1),
            (20, 2, {$this->testSeason}, {$this->testWeek}, 50, 'free', 1)");

        // Alice: picked KC (win), GB (loss), SF
        $this->db->execute("INSERT INTO pickem_picks (entry_id, game_id, selected_team) VALUES 
            (10, 901, 'KC'), (10, 902, 'GB'), (10, 903, 'SF')");

        // Bob: picked BAL (loss), PHI (win), LAR
        $this->db->execute("INSERT INTO pickem_picks (entry_id, game_id, selected_team) VALUES 
            (20, 901, 'BAL'), (20, 902, 'PHI'), (20, 903, 'LAR')");
    }

    protected function tearDown(): void
    {
        Connection::resetInstance();
        if (file_exists($this->tempDbPath)) {
            @unlink($this->tempDbPath);
        }

        // Clean up tracking file
        $service = new DailyPickemDigestService($this->db);
        $trackingFile = $service->getTrackingFilePath($this->testSeason, $this->testWeek);
        if (file_exists($trackingFile)) {
            @unlink($trackingFile);
        }
        $trackingFile2 = $service->getTrackingFilePath($this->testSeason, 2);
        if (file_exists($trackingFile2)) {
            @unlink($trackingFile2);
        }
        $wrapupFile = $service->getWrapupTrackingFilePath($this->testSeason, $this->testWeek);
        if (file_exists($wrapupFile)) {
            @unlink($wrapupFile);
        }
        $wrapupFile2 = $service->getWrapupTrackingFilePath($this->testSeason, 2);
        if (file_exists($wrapupFile2)) {
            @unlink($wrapupFile2);
        }
    }

    private function createMockSportsService(): SportsDataService
    {
        $mock = $this->createMock(SportsDataService::class);
        $mock->method('syncIfNeeded')->willReturn(null);
        return $mock;
    }

    public function testTrackingFileAndHasNewResults(): void
    {
        $service = new DailyPickemDigestService($this->db, null, $this->createMockSportsService());

        $this->assertEmpty($service->getReportedGameIds($this->testSeason, $this->testWeek));
        $this->assertTrue($service->hasNewResults($this->testSeason, $this->testWeek));

        // Mark game 901 as reported
        $service->markGamesAsReported($this->testSeason, $this->testWeek, [901]);
        $this->assertEquals([901], $service->getReportedGameIds($this->testSeason, $this->testWeek));
        // Still has 902 unreported
        $this->assertTrue($service->hasNewResults($this->testSeason, $this->testWeek));

        // Mark game 902 as reported
        $service->markGamesAsReported($this->testSeason, $this->testWeek, [902]);
        $this->assertEquals([901, 902], $service->getReportedGameIds($this->testSeason, $this->testWeek));
        // All final games are now reported
        $this->assertFalse($service->hasNewResults($this->testSeason, $this->testWeek));
    }

    public function testGetDigestDataAndRenderOutput(): void
    {
        $scoring = new ScoringEngine($this->db);
        $service = new DailyPickemDigestService($this->db, $scoring, $this->createMockSportsService());

        $data = $service->getDigestData($this->testSeason, $this->testWeek);
        $this->assertCount(3, $data['games']);
        $this->assertCount(2, $data['final_games']);
        $this->assertCount(2, $data['entries']);
        $this->assertCount(2, $data['standings']);

        $aliceEntry = null;
        foreach ($data['entries'] as $entry) {
            if ($entry['username'] === 'Alice') {
                $aliceEntry = $entry;
                break;
            }
        }
        $this->assertNotNull($aliceEntry);

        $html = $service->renderHtml($aliceEntry, $data);
        $text = $service->renderText($aliceEntry, $data);

        // Verify HTML contents
        $this->assertStringContainsString('Alice', $html);
        $this->assertStringContainsString('Week 1 Morning Briefing', $html);
        $this->assertStringContainsString('WIN +1', $html);
        $this->assertStringContainsString('LOSS 0', $html);
        $this->assertStringContainsString('KC', $html);
        $this->assertStringContainsString('BAL', $html);
        $this->assertStringContainsString('LAR @ SF', $html);
        $this->assertStringContainsString('https://football.wallyatkins.com/pickem/standings?week=1', $html);

        // Verify Text contents
        $this->assertStringContainsString('Good morning, Alice!', $text);
        $this->assertStringContainsString('Score: 1 Correct / 2 Graded', $text);
        $this->assertStringContainsString('LAR @ SF', $text);
    }

    public function testSendDigestDispatchesThroughCustomMailerAndMarksReported(): void
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

        $scoring = new ScoringEngine($this->db);
        $service = new DailyPickemDigestService(
            $this->db,
            $scoring,
            $this->createMockSportsService(),
            'football@wallyatkins.com',
            "Wally's NFL Pool",
            $mockMailer
        );

        $result = $service->sendDigest($this->testSeason, $this->testWeek);
        $this->assertSame('success', $result['status']);
        $this->assertSame(2, $result['sent']);
        $this->assertSame(0, $result['failed']);
        $this->assertCount(2, $sentMails);

        $recipients = array_column($sentMails, 'to');
        $this->assertContains('alice@example.com', $recipients);
        $this->assertContains('bob@example.com', $recipients);

        // Should mark games as reported
        $reported = $service->getReportedGameIds($this->testSeason, $this->testWeek);
        $this->assertEquals([901, 902], $reported);

        // A second run without force should be skipped
        $secondResult = $service->sendDigest($this->testSeason, $this->testWeek);
        $this->assertSame('skipped', $secondResult['status']);
        $this->assertStringContainsString('No new completed games', $secondResult['reason']);
    }

    public function testSendDigestDryRunDoesNotDispatchOrMark(): void
    {
        $sentMails = [];
        $mockMailer = function (string $to, string $subject, string $body, string $headers) use (&$sentMails): bool {
            $sentMails[] = ['to' => $to];
            return true;
        };

        $service = new DailyPickemDigestService(
            $this->db,
            null,
            $this->createMockSportsService(),
            null,
            null,
            $mockMailer
        );

        $result = $service->sendDigest($this->testSeason, $this->testWeek, false, null, true);
        $this->assertSame('success', $result['status']);
        $this->assertTrue($result['dry_run']);
        $this->assertCount(0, $sentMails);
        $this->assertEmpty($service->getReportedGameIds($this->testSeason, $this->testWeek));
    }

    public function testSendDigestTestToOnlySendsToOneAndDoesNotMark(): void
    {
        $sentMails = [];
        $mockMailer = function (string $to, string $subject, string $body, string $headers) use (&$sentMails): bool {
            $sentMails[] = ['to' => $to];
            return true;
        };

        $service = new DailyPickemDigestService(
            $this->db,
            null,
            $this->createMockSportsService(),
            null,
            null,
            $mockMailer
        );

        $result = $service->sendDigest($this->testSeason, $this->testWeek, true, 'tester@wallyatkins.com');
        $this->assertSame('success', $result['status']);
        $this->assertSame(1, $result['sent']);
        $this->assertCount(1, $sentMails);
        $this->assertSame('tester@wallyatkins.com', $sentMails[0]['to']);
        // Games should not be marked as reported in test mode
        $this->assertEmpty($service->getReportedGameIds($this->testSeason, $this->testWeek));
    }

    public function testSurvivorStatusRenderingAndDualPoolReporting(): void
    {
        // Seed survivor entries and picks for Alice & Bob
        $this->db->execute("INSERT INTO survivor_entries (user_id, season_year, payment_status, is_eliminated) VALUES 
            (1, {$this->testSeason}, 'paid', 0),
            (2, {$this->testSeason}, 'unpaid', 1)");

        // Alice picked KC (game 901, KC won 27-20 vs BAL)
        $this->db->execute("INSERT INTO survivor_picks (user_id, season_year, week_number, selected_team, is_eliminated) VALUES 
            (1, {$this->testSeason}, {$this->testWeek}, 'KC', 0)");

        // Bob picked BAL (game 901, BAL lost)
        $this->db->execute("INSERT INTO survivor_picks (user_id, season_year, week_number, selected_team, is_eliminated) VALUES 
            (2, {$this->testSeason}, {$this->testWeek}, 'BAL', 1)");

        $scoring = new ScoringEngine($this->db);
        $service = new DailyPickemDigestService($this->db, $scoring, $this->createMockSportsService());

        $data = $service->getDigestData($this->testSeason, $this->testWeek);

        // Verify survivor summary
        $this->assertArrayHasKey('survivor_summary', $data);
        $this->assertEquals(1, $data['survivor_summary']['total_alive']);
        $this->assertEquals(1, $data['survivor_summary']['total_eliminated']);

        $aliceEntry = null;
        $bobEntry = null;
        foreach ($data['entries'] as $e) {
            if ($e['username'] === 'Alice') {
                $aliceEntry = $e;
            } elseif ($e['username'] === 'Bob') {
                $bobEntry = $e;
            }
        }
        $this->assertNotNull($aliceEntry);
        $this->assertNotNull($bobEntry);

        // Alice HTML & Text
        $aliceHtml = $service->renderHtml($aliceEntry, $data);
        $aliceText = $service->renderText($aliceEntry, $data);

        $this->assertStringContainsString('Survivor Pool Status', $aliceHtml);
        $this->assertStringContainsString('ALIVE', $aliceHtml);
        $this->assertStringContainsString('KC', $aliceHtml);
        $this->assertStringContainsString('SURVIVOR STATUS:', $aliceText);
        $this->assertStringContainsString('Pool Status: ALIVE (In the Hunt)', $aliceText);
        $this->assertStringContainsString('Week 1 Pick: KC', $aliceText);

        // Bob HTML & Text
        $bobHtml = $service->renderHtml($bobEntry, $data);
        $bobText = $service->renderText($bobEntry, $data);

        $this->assertStringContainsString('ELIMINATED', $bobHtml);
        $this->assertStringContainsString('Pool Status: ELIMINATED', $bobText);

        // Dual CTAs present
        $this->assertStringContainsString('View Full Standings', $aliceHtml);
        $this->assertStringContainsString('View Survivor Board', $aliceHtml);
        $this->assertStringContainsString('mtm_source=morning_digest', $aliceHtml);
        $this->assertStringContainsString('https://analytics.wallyatkins.com/matomo.php', $aliceHtml);
    }

    public function testSendDigestIncludesUnsubscribeHeaders(): void
    {
        $sentHeaders = '';
        $sentSubject = '';
        $mockMailer = function (string $to, string $subject, string $body, string $headers) use (&$sentHeaders, &$sentSubject): bool {
            $sentHeaders = $headers;
            $sentSubject = $subject;
            return true;
        };

        $service = new DailyPickemDigestService(
            $this->db,
            null,
            $this->createMockSportsService(),
            null,
            null,
            $mockMailer
        );

        $service->sendDigest($this->testSeason, $this->testWeek, true, 'tester@example.com');

        $this->assertStringContainsString("Atkins NFL Pool: Week 1 Morning Update — Pick'em & Survivor Status", $sentSubject);
        $this->assertStringContainsString('List-Unsubscribe: <https://football.wallyatkins.com/preferences?email=tester%40example.com>', $sentHeaders);
        $this->assertStringContainsString('List-Unsubscribe-Post: List-Unsubscribe=One-Click', $sentHeaders);
    }

    public function testWeeklyWrapupAndWinnerCelebrationDigest(): void
    {
        // 1. Conclude Week 1: game 903 final (SF 21, LAR 17). Alice picked SF (correct, 2 total), Bob picked LAR (missed, 1 total)
        $this->db->execute("UPDATE games SET status = 'final', home_score = 21, away_score = 17 WHERE id = 903");
        $service = new DailyPickemDigestService(
            $this->db,
            new ScoringEngine($this->db),
            $this->createMockSportsService()
        );

        // Mark individual games as reported for week 1
        $service->markGamesAsReported($this->testSeason, 1, [901, 902, 903]);
        $this->assertFalse($service->isWrapupReported($this->testSeason, 1));

        // 2. Set up Week 2 slate
        $this->db->execute("INSERT INTO games (id, season_year, week_number, home_team, away_team, kickoff_time, status, home_score, away_score, is_mnf) VALUES 
            (920, {$this->testSeason}, 2, 'BUF', 'MIA', datetime('now', '+24 hours'), 'scheduled', null, null, 0)");
        $this->db->execute("INSERT INTO pickem_entries (id, user_id, season_year, week_number, mnf_total_points_prediction, payment_status, is_locked) VALUES 
            (102, 1, {$this->testSeason}, 2, 45, 'paid', 0),
            (202, 2, {$this->testSeason}, 2, 50, 'paid', 0)");

        // hasNewResults for Week 2 must be true because Week 1 concluded and wrapup is unreported
        $this->assertTrue($service->hasNewResults($this->testSeason, 2));

        $data = $service->getDigestData($this->testSeason, 2);
        $this->assertSame(1, $data['completed_week']);
        $this->assertNotEmpty($data['completed_week_winners']);
        $this->assertSame('Alice', $data['completed_week_winners'][0]['username']);
        $this->assertSame(2, $data['completed_week_winners'][0]['correct_picks']);

        $aliceEntry = $data['entries'][0];
        $html = $service->renderHtml($aliceEntry, $data);
        $text = $service->renderText($aliceEntry, $data);

        $this->assertStringContainsString('Official Week 1 Champion', $html);
        $this->assertStringContainsString('Alice', $html);
        $this->assertStringContainsString('Week 2 Picks are Open!', $html);

        $this->assertStringContainsString('OFFICIAL WEEK 1 CHAMPION:', $text);
        $this->assertStringContainsString('Congratulations, Alice!', $text);
        $this->assertStringContainsString('WEEK 2 PICKS ARE OPEN!', $text);

        // Test sending digest
        $sentMails = [];
        $mockMailer = function (string $to, string $subject, string $body, string $headers) use (&$sentMails): bool {
            $sentMails[] = [
                'to' => $to,
                'subject' => $subject,
                'body' => $body,
            ];
            return true;
        };

        $serviceWithMailer = new DailyPickemDigestService(
            $this->db,
            new ScoringEngine($this->db),
            $this->createMockSportsService(),
            'football@wallyatkins.com',
            "Wally's NFL Pool",
            $mockMailer
        );

        $res = $serviceWithMailer->sendDigest($this->testSeason, 2);
        $this->assertSame('success', $res['status']);
        $this->assertSame(2, $res['sent']);
        $this->assertCount(2, $sentMails);
        $this->assertStringContainsString('Week 1 Winner Alice!', $sentMails[0]['subject']);
        $this->assertStringContainsString('Week 2 Picks Open', $sentMails[0]['subject']);

        // Wrapup is now marked reported
        $this->assertTrue($service->isWrapupReported($this->testSeason, 1));

        // Subsequent check returns false (no more new results or wrapup)
        $this->assertFalse($service->hasNewResults($this->testSeason, 2));
    }
}
