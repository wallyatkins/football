<?php

declare(strict_types=1);

namespace WallyFootball\Services;

class NotificationService
{
    private string $recipient;
    private string $fromEmail;
    /** @var callable|null */
    private $mailer;

    /**
     * @param string|null $recipient Direct SMS gateway email, e.g. 7575933306@msg.fi.google.com
     * @param string|null $fromEmail Sender address
     * @param callable|null $mailer Custom mail callable for testing: fn(string $to, string $subject, string $body, string $headers): bool
     */
    public function __construct(?string $recipient = null, ?string $fromEmail = null, ?callable $mailer = null)
    {
        if ($recipient !== null && $recipient !== '') {
            $this->recipient = $recipient;
        } else {
            $smsEmail = getenv('ADMIN_SMS_EMAIL');
            if (is_string($smsEmail) && trim($smsEmail) !== '') {
                $this->recipient = trim($smsEmail);
            } else {
                $phone = getenv('ADMIN_ALERT_PHONE');
                $phone = is_string($phone) && trim($phone) !== '' ? trim($phone) : '7575933306';

                $gateway = getenv('ADMIN_ALERT_GATEWAY');
                $gateway = is_string($gateway) && trim($gateway) !== '' ? trim($gateway) : 'msg.fi.google.com';

                $digits = preg_replace('/\D+/', '', $phone) ?? '';
                if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
                    $digits = substr($digits, 1);
                }
                $this->recipient = "{$digits}@{$gateway}";
            }
        }

        $from = getenv('MAIL_FROM');
        $this->fromEmail = $fromEmail ?: (is_string($from) && trim($from) !== '' ? trim($from) : 'football@wallyatkins.com');
        $this->mailer = $mailer;
    }

    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public function getFromEmail(): string
    {
        return $this->fromEmail;
    }

    public function sendSms(string $message, string $subject = 'NFL Pool Alert'): bool
    {
        $headers = [
            'From: ' . $this->fromEmail,
            'Reply-To: ' . $this->fromEmail,
            'X-Mailer: PHP/' . phpversion(),
            'Content-Type: text/plain; charset=UTF-8',
        ];
        $headersString = implode("\r\n", $headers);

        if ($this->mailer !== null) {
            return (bool) ($this->mailer)($this->recipient, $subject, $message, $headersString);
        }

        return (bool) @mail($this->recipient, $subject, $message, $headersString);
    }

    public function notifyPicksSubmitted(string $username, int $week, int $season, ?int $tiebreaker, int $pickCount): bool
    {
        $tbText = $tiebreaker !== null ? "{$tiebreaker} pts" : 'N/A';
        $msg = "NFL Pick'em: {$username} locked in Week {$week} picks! Tiebreaker: {$tbText}. ({$pickCount} games picked)";
        return $this->sendSms($msg, "Week {$week} Picks Submitted");
    }

    public function notifySurvivorPickSubmitted(string $username, int $week, int $season, string $team): bool
    {
        $msg = "NFL Survivor: {$username} locked in {$team} for Week {$week}!";
        return $this->sendSms($msg, "Week {$week} Survivor Pick");
    }
}
