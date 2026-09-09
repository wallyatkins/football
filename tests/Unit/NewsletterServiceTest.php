<?php

declare(strict_types=1);

namespace WallyFootball\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WallyFootball\Database\Connection;
use WallyFootball\Services\NewsletterService;

class NewsletterServiceTest extends TestCase
{
    private Connection $db;
    private string $tempDbPath;

    protected function setUp(): void
    {
        $this->tempDbPath = sys_get_temp_dir() . '/test_newsletter_' . bin2hex(random_bytes(6)) . '.sqlite';
        $this->db = Connection::getInstance($this->tempDbPath);

        // Seed mock games
        $this->db->execute(
            "INSERT INTO games (season_year, week_number, home_team, away_team, kickoff_time, status)
             VALUES (2026, 1, 'KC', 'BAL', '2026-09-10 00:20:00+00', 'scheduled')"
        );
        $this->db->execute(
            "INSERT INTO games (season_year, week_number, home_team, away_team, kickoff_time, status)
             VALUES (2026, 1, 'PHI', 'GB', '2026-09-11 00:15:00+00', 'scheduled')"
        );
    }

    protected function tearDown(): void
    {
        Connection::resetInstance();
        if (file_exists($this->tempDbPath)) {
            @unlink($this->tempDbPath);
        }
    }

    public function testDetectCurrentSlateReturnsExpectedWeek(): void
    {
        $service = new NewsletterService($this->db);
        $slate = $service->detectCurrentSlate();

        $this->assertEquals(2026, $slate['season_year']);
        $this->assertEquals(1, $slate['week_number']);
    }

    public function testGetSlateDetailsExtractsEarliestKickoffCorrectly(): void
    {
        $service = new NewsletterService($this->db);
        $details = $service->getSlateDetails(2026, 1);

        $this->assertEquals(2, $details['game_count']);
        $this->assertEquals('BAL @ KC', $details['earliest_matchup']);
        $this->assertStringContainsString('8:20 PM', $details['earliest_kickoff_eastern']);
    }

    public function testRenderHtmlAndTextGenerateValidContent(): void
    {
        $service = new NewsletterService($this->db);
        $html = $service->renderHtml(2026, 1, 'Wally', 'Archetypo');
        $text = $service->renderText(2026, 1, 'Wally', 'Archetypo');

        $this->assertStringContainsString('BAL @ KC', $html);
        $this->assertStringContainsString('Wally', $html);
        $this->assertStringContainsString('Archetypo', $html);
        $this->assertStringContainsString('https://football.wallyatkins.com/pickem', $html);
        $this->assertStringContainsString('https://memrdatitans.football.cbssports.com/', $html);

        $this->assertStringContainsString('BAL @ KC', $text);
        $this->assertStringContainsString('Archetypo', $text);
    }

    public function testSendNewsletterDispatchesThroughCustomMailer(): void
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

        $service = new NewsletterService($this->db, 'football@wallyatkins.com', "Wally's Football League", $mockMailer);

        $recipients = [
            ['name' => 'Wally Atkins', 'email' => 'wallyatkins@gmail.com', 'team_name' => 'Archetypo'],
            ['name' => 'Invalid Person', 'email' => 'not-an-email'],
        ];

        $result = $service->sendNewsletter(2026, 1, $recipients, false);

        $this->assertEquals(1, $result['sent']);
        $this->assertEquals(1, $result['failed']);
        $this->assertCount(1, $sentMails);
        $this->assertEquals('wallyatkins@gmail.com', $sentMails[0]['to']);
        $this->assertStringContainsString('Atkins Football Week 1 Gazette', $sentMails[0]['subject']);
        $this->assertStringContainsString('BAL @ KC', $sentMails[0]['body']);
    }
}
