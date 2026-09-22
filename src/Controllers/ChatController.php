<?php

declare(strict_types=1);

namespace WallyFootball\Controllers;

use WallyFootball\Database\Connection;
use WallyFootball\Services\ChatService;

class ChatController
{
    private Connection $db;
    private ChatService $chat;

    public function __construct(?Connection $db = null, ?ChatService $chat = null)
    {
        $this->db = $db ?? Connection::getInstance();
        $this->chat = $chat ?? new ChatService($this->db);
    }

    /**
     * POST /api/chat/start
     * Starts a new chat session for an authenticated player.
     */
    public function start(): void
    {
        header('Content-Type: application/json');

        $user = $this->getAuthenticatedUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Please log in to start a chat']);
            return;
        }

        $input = $this->getRequestPayload();
        $initialMessage = !empty($input['message']) ? trim((string) $input['message']) : null;

        $sessionData = $this->chat->createSession(
            (string) $user['username'],
            (string) $user['email'],
            (int) ($user['id'] ?? 0) ?: null,
            $initialMessage
        );

        $_SESSION['active_chat'] = [
            'session_id' => $sessionData['session_id'],
            'token' => $sessionData['user_token'],
        ];

        echo json_encode($sessionData);
    }

    /**
     * GET /api/chat/poll
     * Polls status and messages for an active chat session.
     */
    public function poll(): void
    {
        header('Content-Type: application/json');

        $sessionId = $_GET['session_id'] ?? $_POST['session_id'] ?? '';
        $token = $_GET['token'] ?? $_POST['token'] ?? '';

        if (empty($sessionId)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing session_id']);
            return;
        }

        // If no token provided, check if user is logged in as commissioner
        if (empty($token) && $this->isCommissioner()) {
            $sess = $this->chat->getSession($sessionId);
            if ($sess) {
                $token = $sess['admin_token'];
            }
        }

        $result = $this->chat->pollSession($sessionId, $token);
        if (!$result) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Chat session not found or access denied']);
            return;
        }

        echo json_encode($result);
    }

    /**
     * POST /api/chat/send
     * Sends a message into the chat session.
     */
    public function send(): void
    {
        header('Content-Type: application/json');

        $input = $this->getRequestPayload();
        $sessionId = $input['session_id'] ?? $_POST['session_id'] ?? '';
        $token = $input['token'] ?? $_POST['token'] ?? '';
        $message = $input['message'] ?? $_POST['message'] ?? '';

        if (empty($sessionId) || empty($message)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing session_id or message']);
            return;
        }

        // Allow commissioner without token if authenticated
        if (empty($token) && $this->isCommissioner()) {
            $sess = $this->chat->getSession($sessionId);
            if ($sess) {
                $token = $sess['admin_token'];
            }
        }

        $result = $this->chat->sendMessage($sessionId, $token, (string) $message);
        if (!$result) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Failed to send message or unauthorized']);
            return;
        }

        echo json_encode($result);
    }

    /**
     * POST /api/chat/resend
     * Resends invite alert to Wally.
     */
    public function resend(): void
    {
        header('Content-Type: application/json');

        $input = $this->getRequestPayload();
        $sessionId = $input['session_id'] ?? $_POST['session_id'] ?? '';
        $token = $input['token'] ?? $_POST['token'] ?? '';

        if (empty($sessionId) || empty($token)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing session_id or token']);
            return;
        }

        $success = $this->chat->resendInvite($sessionId, $token);
        if (!$success) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Unable to resend invite']);
            return;
        }

        echo json_encode(['status' => 'success', 'message' => 'Invite resent to Wally']);
    }

    /**
     * POST /api/chat/end
     * Ends the active chat session.
     */
    public function end(): void
    {
        header('Content-Type: application/json');

        $input = $this->getRequestPayload();
        $sessionId = $input['session_id'] ?? $_POST['session_id'] ?? '';
        $token = $input['token'] ?? $_POST['token'] ?? '';

        if (empty($sessionId)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing session_id']);
            return;
        }

        if (empty($token) && $this->isCommissioner()) {
            $sess = $this->chat->getSession($sessionId);
            if ($sess) {
                $token = $sess['admin_token'];
            }
        }

        $success = $this->chat->endSession($sessionId, $token);
        if (isset($_SESSION['active_chat']['session_id']) && $_SESSION['active_chat']['session_id'] === $sessionId) {
            unset($_SESSION['active_chat']);
        }

        echo json_encode(['status' => $success ? 'success' : 'error']);
    }

    /**
     * POST /api/feedback/submit
     * Saves user feedback and alerts Wally.
     */
    public function submitFeedback(): void
    {
        header('Content-Type: application/json');

        $user = $this->getAuthenticatedUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Please log in to submit feedback']);
            return;
        }

        $input = $this->getRequestPayload();
        $category = !empty($input['category']) ? trim((string) $input['category']) : 'general';
        $message = !empty($input['message']) ? trim((string) $input['message']) : '';

        if ($message === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Please enter a feedback message']);
            return;
        }

        $id = $this->chat->saveFeedback(
            (string) $user['username'],
            (string) $user['email'],
            $category,
            $message,
            (int) ($user['id'] ?? 0) ?: null
        );

        echo json_encode([
            'status' => 'success',
            'feedback_id' => $id,
            'message' => 'Thank you! Your feedback has been sent directly to Wally.',
        ]);
    }

    /**
     * GET /chat
     * Handles Wally joining a chat session via email link,
     * or Commissioner viewing the Chat & Feedback command desk.
     */
    public function chatHost(): void
    {
        $sessionId = $_GET['session_id'] ?? '';
        $token = $_GET['token'] ?? '';
        $isCommish = $this->isCommissioner();

        // If no session_id provided, show commissioner overview (requires commissioner)
        if (empty($sessionId)) {
            if (!$isCommish) {
                header('Location: /pickem');
                exit;
            }

            $sessions = $this->chat->getRecentSessions(30);
            $feedback = $this->chat->getRecentFeedback(40);
            $title = "Commissioner Chat & Feedback Desk — Wally's NFL Pool";
            $user = $_SESSION['user'] ?? null;

            require dirname(__DIR__, 2) . '/templates/chat/dashboard.php';
            return;
        }

        // Specific session requested
        $session = $this->chat->getSession($sessionId);
        if (!$session) {
            http_response_code(404);
            $user = $_SESSION['user'] ?? null;
            $title = "Chat Not Found — Wally's NFL Pool";
            ob_start();
            ?>
            <div class="max-w-md mx-auto my-12 text-center p-8 bg-[#162235] border border-[#243247] rounded-2xl">
                <span class="text-4xl block mb-3">💬</span>
                <h1 class="text-xl font-bold text-white mb-2">Chat Session Expired or Not Found</h1>
                <p class="text-sm text-slate-400 mb-6">This chat session is no longer active or the link is invalid.</p>
                <a href="/pickem" class="inline-flex px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold rounded-lg transition text-sm">
                    Return to League &rarr;
                </a>
            </div>
            <?php
            $content = ob_get_clean();
            require dirname(__DIR__, 2) . '/templates/layout.php';
            return;
        }

        // Verify authorization: either valid admin_token, valid user_token, or logged-in commissioner
        $isAdmin = ($token === $session['admin_token']) || $isCommish;
        $isUser = ($token === $session['user_token']);

        if (!$isAdmin && !$isUser) {
            http_response_code(403);
            $user = $_SESSION['user'] ?? null;
            $title = "Unauthorized — Wally's NFL Pool";
            ob_start();
            ?>
            <div class="max-w-md mx-auto my-12 text-center p-8 bg-[#162235] border border-[#243247] rounded-2xl">
                <span class="text-4xl block mb-3">🔒</span>
                <h1 class="text-xl font-bold text-white mb-2">Access Denied</h1>
                <p class="text-sm text-slate-400 mb-6">You do not have permission to access this chat room.</p>
                <a href="/pickem" class="inline-flex px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold rounded-lg transition text-sm">
                    Return to League &rarr;
                </a>
            </div>
            <?php
            $content = ob_get_clean();
            require dirname(__DIR__, 2) . '/templates/layout.php';
            return;
        }

        // Active token to use for the frontend in this host window
        $activeToken = $isAdmin ? $session['admin_token'] : $session['user_token'];
        $playerStats = !empty($session['user_id']) ? $this->chat->getPlayerSummary((int) $session['user_id']) : [];

        $title = "Live Chat: {$session['user_name']} — Wally's NFL Pool";
        $user = $_SESSION['user'] ?? null;

        require dirname(__DIR__, 2) . '/templates/chat/host.php';
    }

    private function getAuthenticatedUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    private function isCommissioner(): bool
    {
        if (empty($_SESSION['user'])) {
            return false;
        }
        $role = $_SESSION['user']['role'] ?? '';
        $email = strtolower($_SESSION['user']['email'] ?? '');
        $username = strtolower($_SESSION['user']['username'] ?? '');

        return in_array($role, ['commissioner', 'admin'], true)
            || in_array($email, ['wallyatkins@gmail.com', 'wally@wallyatkins.com', 'accounts@wallyatkins.com'], true)
            || in_array($username, ['wallyatkins', 'wally'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function getRequestPayload(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }
        return $_POST;
    }
}
