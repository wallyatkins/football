<?php

declare(strict_types=1);

namespace WallyFootball\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WallyFootball\Database\Connection;
use WallyFootball\Services\ChatService;
use WallyFootball\Services\NotificationService;

class ChatServiceTest extends TestCase
{
    private string $tempDb;
    private Connection $db;

    protected function setUp(): void
    {
        $this->tempDb = tempnam(sys_get_temp_dir(), 'test_chat_') . '.sqlite';
        Connection::resetInstance();
        $this->db = Connection::getInstance($this->tempDb);
    }

    protected function tearDown(): void
    {
        Connection::resetInstance();
        if (file_exists($this->tempDb)) {
            @unlink($this->tempDb);
        }
    }

    public function testCreateSessionTriggersAlertsAndStoresInitialMessage(): void
    {
        $capturedEmails = [];
        $capturedSms = [];

        $mockMailer = function (string $to, string $subject, string $body, string $headers) use (&$capturedEmails): bool {
            $capturedEmails[] = compact('to', 'subject', 'body', 'headers');
            return true;
        };

        $mockSmsMailer = function (string $to, string $subject, string $body, string $headers) use (&$capturedSms): bool {
            $capturedSms[] = compact('to', 'subject', 'body', 'headers');
            return true;
        };

        $notifier = new NotificationService('7575933306@msg.fi.google.com', 'football@wallyatkins.com', $mockSmsMailer);
        $service = new ChatService(
            $this->db,
            $notifier,
            'football@wallyatkins.com',
            'wallyatkins@gmail.com',
            'https://football.wallyatkins.com',
            $mockMailer
        );

        $res = $service->createSession('Bart Atkins', 'bart@example.com', 42, 'Quick question about the Rams game!');

        $this->assertSame('success', $res['status']);
        $this->assertNotEmpty($res['session_id']);
        $this->assertNotEmpty($res['user_token']);
        $this->assertNotEmpty($res['admin_token']);
        $this->assertSame('waiting', $res['state']);

        // Check DB session
        $sess = $this->db->queryOne('SELECT * FROM chat_sessions WHERE id = :id', ['id' => $res['session_id']]);
        $this->assertNotNull($sess);
        $this->assertSame('Bart Atkins', $sess['user_name']);
        $this->assertSame('bart@example.com', $sess['user_email']);
        $this->assertSame('waiting', $sess['status']);

        // Check DB messages (system start + initial user message)
        $messages = $this->db->query('SELECT * FROM chat_messages WHERE session_id = :sid ORDER BY id ASC', ['sid' => $res['session_id']]);
        $this->assertCount(2, $messages);
        $this->assertSame('system', $messages[0]['sender']);
        $this->assertSame('user', $messages[1]['sender']);
        $this->assertSame('Quick question about the Rams game!', $messages[1]['message']);

        // Verify captured alert email
        $this->assertCount(1, $capturedEmails);
        $this->assertSame('wallyatkins@gmail.com', $capturedEmails[0]['to']);
        $this->assertStringContainsString('[NFL Pool Live Chat] Bart Atkins wants to chat!', $capturedEmails[0]['subject']);
        $this->assertStringContainsString($res['session_id'], $capturedEmails[0]['body']);
        $this->assertStringContainsString($res['admin_token'], $capturedEmails[0]['body']);
        $this->assertStringContainsString('Quick question about the Rams game!', $capturedEmails[0]['body']);

        // Verify captured SMS alert
        $this->assertCount(1, $capturedSms);
        $this->assertStringContainsString('Bart Atkins is waiting', $capturedSms[0]['body']);
    }

    public function testPollSessionTransitionsStatusWhenAdminConnects(): void
    {
        $mockSms = fn () => true;
        $service = new ChatService(
            $this->db,
            new NotificationService('7575933306@msg.fi.google.com', 'football@wallyatkins.com', $mockSms),
            'football@wallyatkins.com',
            'wallyatkins@gmail.com',
            'https://football.wallyatkins.com',
            fn () => true
        );

        $session = $service->createSession('Michelle', 'michelle@example.com', 12);
        $sid = $session['session_id'];
        $uToken = $session['user_token'];
        $aToken = $session['admin_token'];

        // User polls first
        $userPoll1 = $service->pollSession($sid, $uToken);
        $this->assertNotNull($userPoll1);
        $this->assertSame('waiting', $userPoll1['session_status']);
        $this->assertFalse($userPoll1['other_online']); // Admin hasn't connected yet

        // Admin joins via link and polls
        $adminPoll = $service->pollSession($sid, $aToken);
        $this->assertNotNull($adminPoll);
        $this->assertSame('active', $adminPoll['session_status']);
        $this->assertTrue($adminPoll['other_online']); // User was seen recently

        // User polls again
        $userPoll2 = $service->pollSession($sid, $uToken);
        $this->assertNotNull($userPoll2);
        $this->assertSame('active', $userPoll2['session_status']);
        $this->assertTrue($userPoll2['other_online']); // Admin is now online!

        // Verify system message that Wally joined
        $messages = $userPoll2['messages'];
        $lastMsg = end($messages);
        $this->assertSame('system', $lastMsg['sender']);
        $this->assertStringContainsString('Wally has joined the chat', $lastMsg['message']);
    }

    public function testSendMessagesBidirectionalAndOfflineNotice(): void
    {
        $capturedEmails = [];
        $mockMailer = function (string $to, string $subject, string $body, string $headers) use (&$capturedEmails): bool {
            $capturedEmails[] = compact('to', 'subject', 'body', 'headers');
            return true;
        };

        $mockSms = fn () => true;
        $service = new ChatService(
            $this->db,
            new NotificationService('7575933306@msg.fi.google.com', 'football@wallyatkins.com', $mockSms),
            'football@wallyatkins.com',
            'wallyatkins@gmail.com',
            'https://football.wallyatkins.com',
            $mockMailer
        );

        $session = $service->createSession('Charlie', 'charlie@example.com', 5);
        $sid = $session['session_id'];
        $uToken = $session['user_token'];
        $aToken = $session['admin_token'];

        // User sends a message while admin has never been online (or timeout)
        $capturedEmails = []; // Reset email count from creation
        $res1 = $service->sendMessage($sid, $uToken, 'Hey Wally, could you check my tiebreaker points?');
        $this->assertSame('success', $res1['status']);

        // Since Wally was offline, an email should have been sent to Wally's inbox with this message!
        $this->assertCount(1, $capturedEmails);
        $this->assertStringContainsString('[NFL Pool Message] Charlie left you a chat message', $capturedEmails[0]['subject']);
        $this->assertStringContainsString('could you check my tiebreaker points?', $capturedEmails[0]['body']);

        // Admin responds
        $res2 = $service->sendMessage($sid, $aToken, 'Checking that now Charlie, looks like 45 was recorded.');
        $this->assertSame('success', $res2['status']);

        // Check messages list
        $poll = $service->pollSession($sid, $uToken);
        $msgTexts = array_column($poll['messages'], 'message');
        $this->assertContains('Hey Wally, could you check my tiebreaker points?', $msgTexts);
        $this->assertContains('Checking that now Charlie, looks like 45 was recorded.', $msgTexts);
    }

    public function testResendInviteAndEndSession(): void
    {
        $capturedEmails = [];
        $mockMailer = function (string $to, string $subject, string $body, string $headers) use (&$capturedEmails): bool {
            $capturedEmails[] = compact('to', 'subject', 'body', 'headers');
            return true;
        };

        $mockSms = fn () => true;
        $service = new ChatService(
            $this->db,
            new NotificationService('7575933306@msg.fi.google.com', 'football@wallyatkins.com', $mockSms),
            'football@wallyatkins.com',
            'wallyatkins@gmail.com',
            'https://football.wallyatkins.com',
            $mockMailer
        );

        $session = $service->createSession('Rob', 'rob@example.com', 9);
        $sid = $session['session_id'];
        $uToken = $session['user_token'];

        $capturedEmails = [];
        $resendSuccess = $service->resendInvite($sid, $uToken);
        $this->assertTrue($resendSuccess);
        $this->assertCount(1, $capturedEmails);
        $this->assertStringContainsString('[REMINDER]', $capturedEmails[0]['subject']);

        // End session
        $endSuccess = $service->endSession($sid, $uToken);
        $this->assertTrue($endSuccess);

        $poll = $service->pollSession($sid, $uToken);
        $this->assertSame('ended', $poll['session_status']);
    }

    public function testSaveFeedbackStoresInDbAndDispatchesEmailAndSms(): void
    {
        $capturedEmails = [];
        $capturedSms = [];

        $mockMailer = function (string $to, string $subject, string $body, string $headers) use (&$capturedEmails): bool {
            $capturedEmails[] = compact('to', 'subject', 'body', 'headers');
            return true;
        };

        $mockSmsMailer = function (string $to, string $subject, string $body, string $headers) use (&$capturedSms): bool {
            $capturedSms[] = compact('to', 'subject', 'body', 'headers');
            return true;
        };

        $notifier = new NotificationService('7575933306@msg.fi.google.com', 'football@wallyatkins.com', $mockSmsMailer);
        $service = new ChatService(
            $this->db,
            $notifier,
            'football@wallyatkins.com',
            'wallyatkins@gmail.com',
            'https://football.wallyatkins.com',
            $mockMailer
        );

        $id = $service->saveFeedback('Logan Atkins', 'logan@example.com', 'idea', 'Can we add confidence points mode next season?', 88);
        $this->assertGreaterThan(0, $id);

        // Verify in DB
        $row = $this->db->queryOne('SELECT * FROM feedback_messages WHERE id = :id', ['id' => $id]);
        $this->assertNotNull($row);
        $this->assertSame('Logan Atkins', $row['username']);
        $this->assertSame('idea', $row['category']);
        $this->assertSame('Can we add confidence points mode next season?', $row['message']);

        // Verify email to Wally
        $this->assertCount(1, $capturedEmails);
        $this->assertSame('wallyatkins@gmail.com', $capturedEmails[0]['to']);
        $this->assertStringContainsString('[NFL Pool Feedback — 💡 Feature Idea] from Logan Atkins', $capturedEmails[0]['subject']);
        $this->assertStringContainsString('Can we add confidence points mode next season?', $capturedEmails[0]['body']);

        // Verify SMS to Wally
        $this->assertCount(1, $capturedSms);
        $this->assertStringContainsString('NFL Feedback [idea] from Logan Atkins', $capturedSms[0]['body']);
    }

    public function testGetRecentSessionsAndFeedback(): void
    {
        $mockSms = fn () => true;
        $service = new ChatService(
            $this->db,
            new NotificationService('7575933306@msg.fi.google.com', 'football@wallyatkins.com', $mockSms),
            'football@wallyatkins.com',
            'wallyatkins@gmail.com',
            'https://football.wallyatkins.com',
            fn () => true
        );

        $service->createSession('User 1', 'u1@example.com');
        $service->createSession('User 2', 'u2@example.com');
        $service->saveFeedback('User 3', 'u3@example.com', 'bug', 'Typo on standings header');

        $sessions = $service->getRecentSessions(10);
        $this->assertCount(2, $sessions);

        $feedback = $service->getRecentFeedback(10);
        $this->assertCount(1, $feedback);
        $this->assertSame('bug', $feedback[0]['category']);
    }
}
