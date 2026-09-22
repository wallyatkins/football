<?php

declare(strict_types=1);

namespace WallyFootball\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WallyFootball\Controllers\ChatController;
use WallyFootball\Database\Connection;
use WallyFootball\Services\ChatService;
use WallyFootball\Services\NotificationService;

class ChatControllerTest extends TestCase
{
    private string $tempDb;
    private Connection $db;
    private ChatService $chatService;
    private ChatController $controller;

    protected function setUp(): void
    {
        $this->tempDb = tempnam(sys_get_temp_dir(), 'test_chat_ctrl_') . '.sqlite';
        Connection::resetInstance();
        $this->db = Connection::getInstance($this->tempDb);

        // Pre-create test user
        $this->db->execute(
            'INSERT INTO users (id, oidc_sub, username, email, role, created_at)
             VALUES (101, "sub_101", "tester", "tester@example.com", "player", CURRENT_TIMESTAMP)'
        );

        $mockSms = fn () => true;
        $mockMailer = fn () => true;
        $notifier = new NotificationService('7575933306@msg.fi.google.com', 'football@wallyatkins.com', $mockSms);
        $this->chatService = new ChatService(
            $this->db,
            $notifier,
            'football@wallyatkins.com',
            'wallyatkins@gmail.com',
            'https://football.wallyatkins.com',
            $mockMailer
        );

        $this->controller = new ChatController($this->db, $this->chatService);

        $_SESSION = [];
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        Connection::resetInstance();
        if (file_exists($this->tempDb)) {
            @unlink($this->tempDb);
        }
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
    }

    public function testStartRequiresAuth(): void
    {
        $_SESSION['user'] = null;

        ob_start();
        $this->controller->start();
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertSame('error', $data['status']);
        $this->assertStringContainsString('log in', $data['message']);
    }

    public function testStartCreatesSessionWhenAuthenticated(): void
    {
        $_SESSION['user'] = [
            'id' => 101,
            'username' => 'tester',
            'email' => 'tester@example.com',
            'role' => 'player',
        ];

        $_POST['message'] = 'Testing start endpoint';

        ob_start();
        $this->controller->start();
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
        $this->assertNotEmpty($data['session_id']);
        $this->assertNotEmpty($data['user_token']);
        $this->assertSame($data['session_id'], $_SESSION['active_chat']['session_id']);
    }

    public function testPollAndSendEndpoints(): void
    {
        $session = $this->chatService->createSession('tester', 'tester@example.com', 101);
        $sid = $session['session_id'];
        $uToken = $session['user_token'];

        // Poll
        $_GET['session_id'] = $sid;
        $_GET['token'] = $uToken;

        ob_start();
        $this->controller->poll();
        $pollOutput = ob_get_clean();

        $pollData = json_decode($pollOutput, true);
        $this->assertSame('success', $pollData['status']);
        $this->assertSame('waiting', $pollData['session_status']);

        // Send message
        $_POST = [
            'session_id' => $sid,
            'token' => $uToken,
            'message' => 'Hello from controller test!',
        ];

        ob_start();
        $this->controller->send();
        $sendOutput = ob_get_clean();

        $sendData = json_decode($sendOutput, true);
        $this->assertSame('success', $sendData['status']);

        // Poll again and verify message present
        ob_start();
        $this->controller->poll();
        $poll2Output = ob_get_clean();

        $poll2Data = json_decode($poll2Output, true);
        $messages = array_column($poll2Data['messages'], 'message');
        $this->assertContains('Hello from controller test!', $messages);
    }

    public function testEndEndpointClosesSession(): void
    {
        $session = $this->chatService->createSession('tester', 'tester@example.com', 101);
        $sid = $session['session_id'];
        $uToken = $session['user_token'];

        $_SESSION['active_chat'] = ['session_id' => $sid, 'token' => $uToken];
        $_POST = ['session_id' => $sid, 'token' => $uToken];

        ob_start();
        $this->controller->end();
        $endOutput = ob_get_clean();

        $endData = json_decode($endOutput, true);
        $this->assertSame('success', $endData['status']);
        $this->assertArrayNotHasKey('active_chat', $_SESSION);

        // Verify session status is ended
        $dbSession = $this->chatService->getSession($sid);
        $this->assertSame('ended', $dbSession['status']);
    }

    public function testSubmitFeedbackEndpoint(): void
    {
        $_SESSION['user'] = [
            'id' => 101,
            'username' => 'tester',
            'email' => 'tester@example.com',
            'role' => 'player',
        ];

        $_POST = [
            'category' => 'bug',
            'message' => 'Found an issue with the scoreboard clock',
        ];

        ob_start();
        $this->controller->submitFeedback();
        $fbOutput = ob_get_clean();

        $fbData = json_decode($fbOutput, true);
        $this->assertSame('success', $fbData['status']);
        $this->assertGreaterThan(0, $fbData['feedback_id']);

        $feedback = $this->chatService->getRecentFeedback(5);
        $this->assertCount(1, $feedback);
        $this->assertSame('Found an issue with the scoreboard clock', $feedback[0]['message']);
    }
}
