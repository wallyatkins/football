<?php

declare(strict_types=1);

namespace WallyFootball\Services;

use WallyFootball\Database\Connection;

class ChatService
{
    private Connection $db;
    private NotificationService $notifier;
    private string $fromEmail;
    private string $adminEmail;
    private string $appUrl;
    /** @var callable|null */
    private $mailer;

    public function __construct(
        ?Connection $db = null,
        ?NotificationService $notifier = null,
        ?string $fromEmail = null,
        ?string $adminEmail = null,
        ?string $appUrl = null,
        ?callable $mailer = null
    ) {
        $this->db = $db ?? Connection::getInstance();
        $this->notifier = $notifier ?? new NotificationService();
        $this->fromEmail = $fromEmail ?? (getenv('MAIL_FROM') ?: 'football@wallyatkins.com');
        $this->adminEmail = $adminEmail ?? (getenv('ADMIN_EMAIL') ?: 'wallyatkins@gmail.com');
        $this->appUrl = rtrim($appUrl ?? (getenv('APP_URL') ?: 'https://football.wallyatkins.com'), '/');
        $this->mailer = $mailer;
    }

    /**
     * Creates a new live chat session and triggers email + SMS notifications to Wally.
     *
     * @return array<string, mixed>
     */
    public function createSession(
        string $userName,
        string $userEmail,
        ?int $userId = null,
        ?string $initialMessage = null
    ): array {
        $sessionId = 'chat_' . bin2hex(random_bytes(10));
        $userToken = bin2hex(random_bytes(16));
        $adminToken = bin2hex(random_bytes(16));
        $now = time();

        $validUserId = null;
        if ($userId !== null && $userId > 0) {
            $userExists = $this->db->queryValue('SELECT id FROM users WHERE id = :id', ['id' => $userId]);
            if ($userExists) {
                $validUserId = (int) $userExists;
            }
        }

        $this->db->execute(
            'INSERT INTO chat_sessions (id, user_id, user_name, user_email, user_token, admin_token, status, initial_message, user_last_seen, admin_last_seen, created_at, updated_at)
             VALUES (:id, :uid, :uname, :uemail, :utoken, :atoken, :status, :msg, :seen, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            [
                'id' => $sessionId,
                'uid' => $validUserId,
                'uname' => $userName,
                'uemail' => $userEmail,
                'utoken' => $userToken,
                'atoken' => $adminToken,
                'status' => 'waiting',
                'msg' => $initialMessage,
                'seen' => $now,
            ]
        );

        // System start message
        $this->db->execute(
            'INSERT INTO chat_messages (session_id, sender, sender_name, message, created_at)
             VALUES (:sid, :sender, :sname, :msg, CURRENT_TIMESTAMP)',
            [
                'sid' => $sessionId,
                'sender' => 'system',
                'sname' => 'System',
                'msg' => "Chat session started. Reaching out to Wally...",
            ]
        );

        if (!empty($initialMessage)) {
            $this->db->execute(
                'INSERT INTO chat_messages (session_id, sender, sender_name, message, created_at)
                 VALUES (:sid, :sender, :sname, :msg, CURRENT_TIMESTAMP)',
                [
                    'sid' => $sessionId,
                    'sender' => 'user',
                    'sname' => $userName,
                    'msg' => $initialMessage,
                ]
            );
        }

        // Notify Wally
        $this->sendChatAlert($sessionId, $adminToken, $userName, $userEmail, $initialMessage, false);

        return [
            'status' => 'success',
            'session_id' => $sessionId,
            'user_token' => $userToken,
            'admin_token' => $adminToken,
            'state' => 'waiting',
            'user_name' => $userName,
            'user_email' => $userEmail,
        ];
    }

    /**
     * Polls the chat session for new messages and updates activity timestamps.
     *
     * @return array<string, mixed>|null
     */
    public function pollSession(string $sessionId, string $token): ?array
    {
        $session = $this->db->queryOne(
            'SELECT * FROM chat_sessions WHERE id = :id',
            ['id' => $sessionId]
        );

        if (!$session) {
            return null;
        }

        $isAdmin = ($token === $session['admin_token']);
        $isUser = ($token === $session['user_token']);

        if (!$isAdmin && !$isUser) {
            return null;
        }

        $now = time();
        $status = $session['status'];

        if ($isAdmin) {
            if ($status === 'waiting') {
                $status = 'active';
                $this->db->execute(
                    'UPDATE chat_sessions SET admin_last_seen = :now, status = "active", updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                    ['now' => $now, 'id' => $sessionId]
                );
                // System message that Wally joined
                $this->db->execute(
                    'INSERT INTO chat_messages (session_id, sender, sender_name, message, created_at)
                     VALUES (:sid, "system", "System", "Wally has joined the chat!", CURRENT_TIMESTAMP)',
                    ['sid' => $sessionId]
                );
            } else {
                $this->db->execute(
                    'UPDATE chat_sessions SET admin_last_seen = :now, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                    ['now' => $now, 'id' => $sessionId]
                );
            }
            $session['admin_last_seen'] = $now;
        } else {
            $this->db->execute(
                'UPDATE chat_sessions SET user_last_seen = :now, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                ['now' => $now, 'id' => $sessionId]
            );
            $session['user_last_seen'] = $now;
        }

        $otherLastSeen = $isAdmin ? (int) $session['user_last_seen'] : (int) $session['admin_last_seen'];
        $isOtherOnline = ($now - $otherLastSeen) < 15;

        // If admin is online, ensure user sees active status
        if (!$isAdmin && $isOtherOnline && $status === 'waiting') {
            $status = 'active';
        }

        $rawMessages = $this->db->query(
            'SELECT id, session_id, sender, sender_name, message, created_at
             FROM chat_messages
             WHERE session_id = :sid
             ORDER BY id ASC',
            ['sid' => $sessionId]
        );

        $messages = array_map(function ($m) {
            $ts = strtotime($m['created_at'] ?? 'now');
            return [
                'id' => (int) $m['id'],
                'sender' => $m['sender'],
                'sender_name' => $m['sender_name'] ?? ($m['sender'] === 'admin' ? 'Wally Atkins' : 'Player'),
                'message' => $m['message'],
                'time' => $ts,
                'time_formatted' => date('g:i A', $ts),
            ];
        }, $rawMessages);

        return [
            'status' => 'success',
            'session_id' => $sessionId,
            'session_status' => $status,
            'messages' => $messages,
            'other_online' => $isOtherOnline,
            'user_name' => $session['user_name'],
            'user_email' => $session['user_email'],
            'is_admin' => $isAdmin,
        ];
    }

    /**
     * Appends a message to the chat session.
     *
     * @return array<string, mixed>|null
     */
    public function sendMessage(string $sessionId, string $token, string $message): ?array
    {
        $message = trim($message);
        if ($message === '') {
            return ['status' => 'error', 'message' => 'Empty message content'];
        }

        $session = $this->db->queryOne(
            'SELECT * FROM chat_sessions WHERE id = :id',
            ['id' => $sessionId]
        );

        if (!$session) {
            return null;
        }

        $isAdmin = ($token === $session['admin_token']);
        $isUser = ($token === $session['user_token']);

        if (!$isAdmin && !$isUser) {
            return null;
        }

        $sender = $isAdmin ? 'admin' : 'user';
        $senderName = $isAdmin ? 'Wally Atkins' : $session['user_name'];
        $now = time();

        $msgId = $this->db->insert(
            'INSERT INTO chat_messages (session_id, sender, sender_name, message, created_at)
             VALUES (:sid, :sender, :sname, :msg, CURRENT_TIMESTAMP)',
            [
                'sid' => $sessionId,
                'sender' => $sender,
                'sname' => $senderName,
                'msg' => $message,
            ]
        );

        // If admin sent message and session was waiting, make active
        if ($isAdmin && $session['status'] === 'waiting') {
            $this->db->execute(
                'UPDATE chat_sessions SET status = "active", admin_last_seen = :now, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                ['now' => $now, 'id' => $sessionId]
            );
        } else {
            $col = $isAdmin ? 'admin_last_seen' : 'user_last_seen';
            $this->db->execute(
                "UPDATE chat_sessions SET {$col} = :now, updated_at = CURRENT_TIMESTAMP WHERE id = :id",
                ['now' => $now, 'id' => $sessionId]
            );
        }

        // If user is sending a message while Wally is NOT online (e.g. left message during/after timeout),
        // email Wally so he receives the message directly in his inbox!
        $adminLastSeen = (int) $session['admin_last_seen'];
        $isAdminOnline = ($now - $adminLastSeen) < 15;
        if ($isUser && !$isAdminOnline) {
            $this->sendOfflineChatEmail($sessionId, $session['admin_token'], $session['user_name'], $session['user_email'], $message);
        }

        return [
            'status' => 'success',
            'message_id' => (int) $msgId,
            'sender' => $sender,
            'time' => $now,
            'time_formatted' => date('g:i A', $now),
        ];
    }

    /**
     * Resends the chat invite alert to Wally.
     */
    public function resendInvite(string $sessionId, string $token): bool
    {
        $session = $this->db->queryOne(
            'SELECT * FROM chat_sessions WHERE id = :id',
            ['id' => $sessionId]
        );

        if (!$session || $token !== $session['user_token']) {
            return false;
        }

        $this->db->execute(
            'INSERT INTO chat_messages (session_id, sender, sender_name, message, created_at)
             VALUES (:sid, "system", "System", "Reminder alert dispatched to Wally.", CURRENT_TIMESTAMP)',
            ['sid' => $sessionId]
        );

        return $this->sendChatAlert(
            $sessionId,
            $session['admin_token'],
            $session['user_name'],
            $session['user_email'],
            $session['initial_message'],
            true
        );
    }

    /**
     * Ends the chat session.
     */
    public function endSession(string $sessionId, string $token): bool
    {
        $session = $this->db->queryOne(
            'SELECT * FROM chat_sessions WHERE id = :id',
            ['id' => $sessionId]
        );

        if (!$session) {
            return false;
        }

        $isAdmin = ($token === $session['admin_token']);
        $isUser = ($token === $session['user_token']);

        if (!$isAdmin && !$isUser) {
            return false;
        }

        $ender = $isAdmin ? 'Wally' : $session['user_name'];

        $this->db->execute(
            'UPDATE chat_sessions SET status = "ended", updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            ['id' => $sessionId]
        );

        $this->db->execute(
            'INSERT INTO chat_messages (session_id, sender, sender_name, message, created_at)
             VALUES (:sid, "system", "System", :msg, CURRENT_TIMESTAMP)',
            [
                'sid' => $sessionId,
                'msg' => "Chat session ended by {$ender}.",
            ]
        );

        return true;
    }

    /**
     * Saves user feedback/bug report and alerts Wally via email and SMS.
     */
    public function saveFeedback(
        string $username,
        string $email,
        string $category,
        string $message,
        ?int $userId = null
    ): int {
        $username = trim($username);
        $email = trim($email);
        $category = trim(strtolower($category));
        $message = trim($message);

        $allowedCategories = ['bug', 'idea', 'scoring', 'general'];
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'general';
        }

        $validUserId = null;
        if ($userId !== null && $userId > 0) {
            $userExists = $this->db->queryValue('SELECT id FROM users WHERE id = :id', ['id' => $userId]);
            if ($userExists) {
                $validUserId = (int) $userExists;
            }
        }

        $feedbackId = (int) $this->db->insert(
            'INSERT INTO feedback_messages (user_id, username, email, category, message, status, created_at)
             VALUES (:uid, :uname, :uemail, :cat, :msg, "new", CURRENT_TIMESTAMP)',
            [
                'uid' => $validUserId,
                'uname' => $username,
                'uemail' => $email,
                'cat' => $category,
                'msg' => $message,
            ]
        );

        // Send Email notification to Wally
        $catLabels = [
            'bug' => '🐞 Bug / Issue',
            'idea' => '💡 Feature Idea',
            'scoring' => '🏈 Scoring / Picks Issue',
            'general' => '💬 General Feedback',
        ];
        $catLabel = $catLabels[$category] ?? ucfirst($category);

        $subject = "[NFL Pool Feedback — {$catLabel}] from {$username}";

        $adminPortalUrl = "{$this->appUrl}/admin/chat";

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0b1626; color: #f8fafc; padding: 24px; }
.card { background-color: #162235; border: 1px solid #243247; border-radius: 12px; max-width: 600px; margin: 0 auto; padding: 24px; }
.badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: bold; background: #3b82f6; color: #ffffff; }
.btn { display: inline-block; background: #eab308; color: #0b1626; font-weight: bold; text-decoration: none; padding: 10px 18px; border-radius: 8px; margin-top: 16px; }
.msg-box { background: #0b1626; border: 1px solid #243247; border-radius: 8px; padding: 16px; margin: 16px 0; white-space: pre-wrap; color: #e2e8f0; font-size: 15px; }
</style>
</head>
<body>
<div class="card">
    <span class="badge">{$catLabel}</span>
    <h2 style="color: #ffffff; margin-top: 12px; margin-bottom: 8px;">New Football Pool Feedback</h2>
    <p style="color: #94a3b8; margin-top: 0; font-size: 14px;">Submitted by <strong>{$username}</strong> (<a href="mailto:{$email}" style="color: #38bdf8;">{$email}</a>)</p>
    <div class="msg-box">{$message}</div>
    <a href="{$adminPortalUrl}" class="btn">View in Commissioner Desk &rarr;</a>
</div>
</body>
</html>
HTML;

        $textBody = "New NFL Pool Feedback [{$catLabel}]\n"
            . "From: {$username} ({$email})\n\n"
            . "Message:\n{$message}\n\n"
            . "Portal: {$adminPortalUrl}";

        $this->dispatchEmail($this->adminEmail, $subject, $htmlBody, $textBody);

        // SMS Alert
        $previewMsg = substr(str_replace(["\r", "\n"], ' ', $message), 0, 100);
        $this->notifier->sendSms("NFL Feedback [{$category}] from {$username}: \"{$previewMsg}\"", "NFL Pool Feedback");

        return $feedbackId;
    }

    /**
     * Sends an email + SMS notification to Wally when a live chat is requested.
     */
    public function sendChatAlert(
        string $sessionId,
        string $adminToken,
        string $userName,
        string $userEmail,
        ?string $initialMessage = null,
        bool $isReminder = false
    ): bool {
        $adminUrl = "{$this->appUrl}/host?cs={$sessionId}&ct={$adminToken}";
        $prefix = $isReminder ? "[REMINDER] " : "";
        $subject = "{$prefix}[NFL Pool Live Chat] {$userName} wants to chat!";

        $htmlIntro = $isReminder
            ? "<h2 style='color: #ef4444; margin-top: 0;'>⚠️ Chat Reminder: User is still waiting!</h2>"
            : "<h2 style='color: #ffffff; margin-top: 0;'>💬 New Live Chat Request!</h2>";

        $msgBlock = !empty($initialMessage)
            ? "<div style='background: #0b1626; border: 1px solid #243247; border-radius: 8px; padding: 14px; margin: 16px 0; color: #f1f5f9; font-size: 15px;'><strong>Initial message:</strong><br>" . nl2br(htmlspecialchars($initialMessage)) . "</div>"
            : "";

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0b1626; color: #f8fafc; padding: 24px; }
.card { background-color: #162235; border: 1px solid #243247; border-radius: 14px; max-width: 580px; margin: 0 auto; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
.join-btn { display: inline-block; background: #15803d; color: #ffffff; font-weight: 800; font-size: 16px; text-decoration: none; padding: 14px 24px; border-radius: 10px; margin: 16px 0; text-align: center; }
</style>
</head>
<body>
<div class="card">
    {$htmlIntro}
    <p style="color: #cbd5e1; font-size: 16px; line-height: 1.5;">
        <strong>{$userName}</strong> (<a href="mailto:{$userEmail}" style="color: #38bdf8;">{$userEmail}</a>) is currently waiting in the Football Pool chat room to speak with you!
    </p>
    {$msgBlock}
    <div style="text-align: center; margin: 24px 0;">
        <a href="{$adminUrl}" class="join-btn">🚀 CLICK HERE TO JOIN LIVE CHAT &rarr;</a>
    </div>
    <hr style="border: 0; border-top: 1px solid #243247; margin: 20px 0;">
    <p style="color: #94a3b8; font-size: 13px; margin: 0;">
        If you are away, the user has a 2-minute countdown and can leave a message directly to your inbox.
    </p>
</div>
</body>
</html>
HTML;

        $textBody = ($isReminder ? "REMINDER: " : "") . "NFL Pool Live Chat Request!\n\n"
            . "{$userName} ({$userEmail}) is waiting in the chat room.\n"
            . (!empty($initialMessage) ? "Message: {$initialMessage}\n" : "")
            . "Join here: {$adminUrl}";

        $emailSent = $this->dispatchEmail($this->adminEmail, $subject, $htmlBody, $textBody);

        // Direct SMS alert to phone
        $smsMsg = "NFL Chat Alert: {$userName} is waiting! Link: {$adminUrl}";
        $this->notifier->sendSms($smsMsg, "NFL Live Chat Alert");

        return $emailSent;
    }

    /**
     * Sends an email notification to Wally when a user leaves a message while Wally is away.
     */
    public function sendOfflineChatEmail(
        string $sessionId,
        string $adminToken,
        string $userName,
        string $userEmail,
        string $message
    ): bool {
        $adminUrl = "{$this->appUrl}/host?cs={$sessionId}&ct={$adminToken}";
        $subject = "[NFL Pool Message] {$userName} left you a chat message";

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0b1626; color: #f8fafc; padding: 24px; }
.card { background-color: #162235; border: 1px solid #243247; border-radius: 12px; max-width: 600px; margin: 0 auto; padding: 24px; }
.btn { display: inline-block; background: #eab308; color: #0b1626; font-weight: bold; text-decoration: none; padding: 10px 18px; border-radius: 8px; margin-top: 16px; }
.msg-box { background: #0b1626; border: 1px solid #243247; border-radius: 8px; padding: 16px; margin: 16px 0; white-space: pre-wrap; color: #e2e8f0; font-size: 15px; }
</style>
</head>
<body>
<div class="card">
    <h2 style="color: #ffffff; margin-top: 0;">💬 Message from {$userName}</h2>
    <p style="color: #94a3b8; font-size: 14px;">Left while you were offline in the Football Pool chat room (<a href="mailto:{$userEmail}" style="color: #38bdf8;">{$userEmail}</a>):</p>
    <div class="msg-box">{$message}</div>
    <a href="{$adminUrl}" class="btn">View Chat Transcript &rarr;</a>
</div>
</body>
</html>
HTML;

        $textBody = "Message from {$userName} ({$userEmail}):\n\n{$message}\n\nTranscript / Reply: {$adminUrl}";

        return $this->dispatchEmail($this->adminEmail, $subject, $htmlBody, $textBody);
    }

    /**
     * Dispatches multipart HTML/text email using mail() or injected mailer.
     */
    private function dispatchEmail(string $to, string $subject, string $html, string $text): bool
    {
        $boundary = "==Wally_Chat_" . md5((string) microtime()) . "==";
        $headers = [
            "From: Wally's Football Pool <{$this->fromEmail}>",
            "Reply-To: {$this->fromEmail}",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "X-Mailer: AtkinsFootballPool/1.0",
        ];

        $body = "--{$boundary}\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: 7bit\r\n\r\n"
              . $text . "\r\n\r\n"
              . "--{$boundary}\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: 7bit\r\n\r\n"
              . $html . "\r\n\r\n"
              . "--{$boundary}--";

        $headersString = implode("\r\n", $headers);

        if ($this->mailer !== null) {
            return (bool) ($this->mailer)($to, $subject, $body, $headersString);
        }

        return (bool) @mail($to, $subject, $body, $headersString);
    }

    /**
     * Fetches recent chat sessions for commissioner overview.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentSessions(int $limit = 25): array
    {
        return $this->db->query(
            'SELECT s.*, 
                    (SELECT COUNT(*) FROM chat_messages WHERE session_id = s.id) as message_count,
                    (SELECT message FROM chat_messages WHERE session_id = s.id ORDER BY id DESC LIMIT 1) as last_message,
                    (SELECT created_at FROM chat_messages WHERE session_id = s.id ORDER BY id DESC LIMIT 1) as last_message_at
             FROM chat_sessions s
             ORDER BY s.updated_at DESC, s.created_at DESC
             LIMIT :lim',
            ['lim' => $limit]
        );
    }

    /**
     * Fetches recent feedback submissions for commissioner overview.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentFeedback(int $limit = 30): array
    {
        return $this->db->query(
            'SELECT * FROM feedback_messages ORDER BY created_at DESC LIMIT :lim',
            ['lim' => $limit]
        );
    }

    /**
     * Fetches a session by ID.
     */
    public function getSession(string $sessionId): ?array
    {
        return $this->db->queryOne(
            'SELECT * FROM chat_sessions WHERE id = :id',
            ['id' => $sessionId]
        );
    }

    /**
     * Fetches quick player summary stats for the host chat sidebar.
     *
     * @return array<string, mixed>
     */
    public function getPlayerSummary(int $userId, int $season = 2026, int $week = 2): array
    {
        $user = $this->db->queryOne('SELECT id, username, email, role FROM users WHERE id = :id', ['id' => $userId]);
        if (!$user) {
            return [];
        }

        $pickemEntry = $this->db->queryOne(
            'SELECT * FROM pickem_entries WHERE user_id = :uid AND season_year = :s AND week_number = :w',
            ['uid' => $userId, 's' => $season, 'w' => $week]
        );

        $survivorPick = $this->db->queryOne(
            'SELECT selected_team FROM survivor_picks WHERE user_id = :uid AND season_year = :s AND week_number = :w',
            ['uid' => $userId, 's' => $season, 'w' => $week]
        );

        $survivorEntry = $this->db->queryOne(
            'SELECT is_eliminated, elimination_week, payment_status FROM survivor_entries WHERE user_id = :uid AND season_year = :s',
            ['uid' => $userId, 's' => $season]
        );

        return [
            'user' => $user,
            'pickem' => [
                'has_entry' => !empty($pickemEntry),
                'predicted_mnf' => $pickemEntry['predicted_mnf_total'] ?? null,
                'is_locked' => !empty($pickemEntry['is_locked']),
            ],
            'survivor' => [
                'pick' => $survivorPick['selected_team'] ?? null,
                'is_eliminated' => !empty($survivorEntry['is_eliminated']),
                'elimination_week' => $survivorEntry['elimination_week'] ?? null,
            ],
        ];
    }
}
