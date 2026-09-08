<?php

declare(strict_types=1);

namespace WallyFootball\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WallyFootball\Services\NotificationService;

class NotificationServiceTest extends TestCase
{
    public function testDefaultRecipientResolvesWallyFiSms(): void
    {
        $service = new NotificationService();
        $this->assertSame('7575933306@msg.fi.google.com', $service->getRecipient());
        $this->assertSame('football@wallyatkins.com', $service->getFromEmail());
    }

    public function testCustomRecipientAndFrom(): void
    {
        $service = new NotificationService('test@vtext.com', 'alerts@example.com');
        $this->assertSame('test@vtext.com', $service->getRecipient());
        $this->assertSame('alerts@example.com', $service->getFromEmail());
    }

    public function testNotifyPicksSubmittedSendsAccurateSmsContent(): void
    {
        $captured = [];
        $mockMailer = function (string $to, string $subject, string $body, string $headers) use (&$captured): bool {
            $captured = [
                'to' => $to,
                'subject' => $subject,
                'body' => $body,
                'headers' => $headers,
            ];
            return true;
        };

        $service = new NotificationService('7575933306@msg.fi.google.com', 'football@wallyatkins.com', $mockMailer);
        $result = $service->notifyPicksSubmitted('derekatkins', 1, 2026, 48, 16);

        $this->assertTrue($result);
        $this->assertSame('7575933306@msg.fi.google.com', $captured['to']);
        $this->assertSame('Week 1 Picks Submitted', $captured['subject']);
        $this->assertStringContainsString('derekatkins locked in Week 1 picks!', $captured['body']);
        $this->assertStringContainsString('Tiebreaker: 48 pts.', $captured['body']);
        $this->assertStringContainsString('(16 games picked)', $captured['body']);
        $this->assertStringContainsString('From: football@wallyatkins.com', $captured['headers']);
    }

    public function testNotifySurvivorPickSubmittedSendsAccurateContent(): void
    {
        $captured = [];
        $mockMailer = function (string $to, string $subject, string $body, string $headers) use (&$captured): bool {
            $captured = [
                'to' => $to,
                'subject' => $subject,
                'body' => $body,
                'headers' => $headers,
            ];
            return true;
        };

        $service = new NotificationService('7575933306@msg.fi.google.com', 'football@wallyatkins.com', $mockMailer);
        $result = $service->notifySurvivorPickSubmitted('derekatkins', 1, 2026, 'KC');

        $this->assertTrue($result);
        $this->assertSame('7575933306@msg.fi.google.com', $captured['to']);
        $this->assertSame('Week 1 Survivor Pick', $captured['subject']);
        $this->assertStringContainsString('derekatkins locked in KC for Week 1!', $captured['body']);
    }
}
