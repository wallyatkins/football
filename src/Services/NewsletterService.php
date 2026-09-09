<?php

declare(strict_types=1);

namespace WallyFootball\Services;

use DateTimeImmutable;
use DateTimeZone;
use WallyFootball\Database\Connection;

class NewsletterService
{
    private Connection $db;
    private string $fromEmail;
    private string $fromName;
    /** @var callable|null */
    private $mailer;

    public function __construct(
        ?Connection $db = null,
        ?string $fromEmail = null,
        ?string $fromName = null,
        ?callable $mailer = null
    ) {
        $this->db = $db ?? Connection::getInstance();
        $this->fromEmail = $fromEmail ?? (getenv('MAIL_FROM') ?: 'football@wallyatkins.com');
        $this->fromName = $fromName ?? "Wally's Football League";
        $this->mailer = $mailer;
    }

    /**
     * Determines the active NFL season year and week dynamically based on game kickoffs.
     *
     * @return array{season_year: int, week_number: int}
     */
    public function detectCurrentSlate(): array
    {
        $tzUtc = new DateTimeZone('UTC');
        $now = (new DateTimeImmutable('now', $tzUtc))->format('Y-m-d H:i:s');

        // Look for the week containing an upcoming or in-progress game
        $nextGame = $this->db->queryOne(
            "SELECT season_year, week_number FROM games WHERE kickoff_time >= :now ORDER BY kickoff_time ASC LIMIT 1",
            ['now' => $now]
        );

        if ($nextGame) {
            return [
                'season_year' => (int) $nextGame['season_year'],
                'week_number' => (int) $nextGame['week_number'],
            ];
        }

        // Fallback to the latest week available
        $latest = $this->db->queryOne("SELECT season_year, week_number FROM games ORDER BY season_year DESC, week_number DESC LIMIT 1");
        return [
            'season_year' => $latest ? (int) $latest['season_year'] : 2026,
            'week_number' => $latest ? (int) $latest['week_number'] : 1,
        ];
    }

    /**
     * Retrieves dynamic schedule information for the given week.
     *
     * @return array{
     *     season_year: int,
     *     week_number: int,
     *     game_count: int,
     *     earliest_kickoff: ?string,
     *     earliest_kickoff_eastern: ?string,
     *     earliest_matchup: ?string,
     *     is_kickoff_today: bool,
     *     hours_until_kickoff: ?float,
     *     games: array
     * }
     */
    public function getSlateDetails(int $seasonYear, int $week): array
    {
        $games = $this->db->query(
            "SELECT * FROM games WHERE season_year = :year AND week_number = :week ORDER BY kickoff_time ASC",
            ['year' => $seasonYear, 'week' => $week]
        );

        if (empty($games)) {
            return [
                'season_year' => $seasonYear,
                'week_number' => $week,
                'game_count' => 0,
                'earliest_kickoff' => null,
                'earliest_kickoff_eastern' => null,
                'earliest_matchup' => null,
                'is_kickoff_today' => false,
                'hours_until_kickoff' => null,
                'games' => [],
            ];
        }

        $firstGame = $games[0];
        $kickoffRaw = (string) $firstGame['kickoff_time'];

        $tzUtc = new DateTimeZone('UTC');
        $tzEastern = new DateTimeZone('America/New_York');

        $kickoffUtc = new DateTimeImmutable($kickoffRaw, $tzUtc);
        $kickoffEastern = $kickoffUtc->setTimezone($tzEastern);
        $nowEastern = (new DateTimeImmutable('now', $tzUtc))->setTimezone($tzEastern);

        $isToday = $kickoffEastern->format('Y-m-d') === $nowEastern->format('Y-m-d');
        $diffSeconds = $kickoffUtc->getTimestamp() - (new DateTimeImmutable('now', $tzUtc))->getTimestamp();
        $hoursUntil = round($diffSeconds / 3600, 1);

        $earliestMatchup = "{$firstGame['away_team']} @ {$firstGame['home_team']}";
        $easternFormatted = $kickoffEastern->format('l, M j \a\t g:i A T');

        return [
            'season_year' => $seasonYear,
            'week_number' => $week,
            'game_count' => count($games),
            'earliest_kickoff' => $kickoffRaw,
            'earliest_kickoff_eastern' => $easternFormatted,
            'earliest_matchup' => $earliestMatchup,
            'is_kickoff_today' => $isToday,
            'hours_until_kickoff' => $hoursUntil,
            'games' => $games,
        ];
    }

    /**
     * Pulls Dynasty Vault trivia and franchise highlights for the newsletter spotlight.
     */
    public function getVaultSpotlight(): array
    {
        try {
            $totalSeasons = (int) ($this->db->queryOne("SELECT count(*) as c FROM fantasy_seasons")['c'] ?? 0);
            $totalMatchups = (int) ($this->db->queryOne("SELECT count(*) as c FROM fantasy_matchups")['c'] ?? 0);
            $topRings = $this->db->query(
                "SELECT current_name, titles_count, total_wins, total_losses FROM fantasy_franchises ORDER BY titles_count DESC, total_wins DESC LIMIT 3"
            );
            $highestScore = $this->db->queryOne(
                "SELECT m.points_scored, f.current_name, s.season_year, m.week_number 
                 FROM fantasy_matchups m
                 JOIN fantasy_franchises f ON m.franchise_id = f.id
                 JOIN fantasy_seasons s ON m.season_id = s.id
                 ORDER BY m.points_scored DESC LIMIT 1"
            );

            return [
                'total_seasons' => $totalSeasons,
                'total_matchups' => $totalMatchups,
                'ring_leaders' => $topRings,
                'record_score' => $highestScore,
            ];
        } catch (\Throwable) {
            return [
                'total_seasons' => 23,
                'total_matchups' => 1818,
                'ring_leaders' => [],
                'record_score' => null,
            ];
        }
    }

    /**
     * Renders responsive HTML email newsletter.
     */
    public function renderHtml(
        int $seasonYear,
        int $week,
        ?string $recipientName = null,
        ?string $teamName = null
    ): string {
        $slate = $this->getSlateDetails($seasonYear, $week);
        $vault = $this->getVaultSpotlight();

        $nameGreeting = $recipientName ? " {$recipientName}" : "";
        $franchiseNotice = $teamName ? "<div style='font-size: 14px; color: #fbbf24; margin-top: 4px;'>Manager of <strong>{$teamName}</strong></div>" : "";

        $urgentCallout = "";
        if ($slate['is_kickoff_today']) {
            $urgentCallout = <<<HTML
            <div style="background: linear-gradient(135deg, #78350f 0%, #451a03 100%); border: 1px solid #f59e0b; border-radius: 12px; padding: 20px; margin: 24px 0;">
                <div style="font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #fde68a; margin-bottom: 6px;">⚡ Kickoff Alert &bull; TONIGHT</div>
                <div style="font-size: 20px; font-weight: 900; color: #ffffff; margin-bottom: 8px;">
                    {$slate['earliest_matchup']} &bull; {$slate['earliest_kickoff_eastern']}
                </div>
                <div style="font-size: 14px; color: #fed7aa; line-height: 1.5;">
                    The first game of Week {$week} kicks off tonight! Games lock straight-up at kickoff. Be sure to lock in your BAL @ KC pick and verify your CBS fantasy starting lineup before kickoff!
                </div>
            </div>
HTML;
        } else {
            $kickoffTime = $slate['earliest_kickoff_eastern'] ?? 'Upcoming this week';
            $matchup = $slate['earliest_matchup'] ?? 'NFL Slate';
            $urgentCallout = <<<HTML
            <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 20px; margin: 24px 0;">
                <div style="font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #38bdf8; margin-bottom: 6px;">🏈 Upcoming Week {$week} Slate</div>
                <div style="font-size: 18px; font-weight: 800; color: #ffffff; margin-bottom: 6px;">
                    Next Game: {$matchup} ({$kickoffTime})
                </div>
                <div style="font-size: 14px; color: #94a3b8; line-height: 1.5;">
                    {$slate['game_count']} games scheduled for this week. Remember that games lock individually at each game's respective kickoff!
                </div>
            </div>
HTML;
        }

        $ringLeadersHtml = "";
        if (!empty($vault['ring_leaders'])) {
            $ringLeadersHtml .= "<ul style='margin: 8px 0; padding-left: 20px; color: #cbd5e1; font-size: 13px;'>";
            foreach ($vault['ring_leaders'] as $leader) {
                $rings = (int) $leader['titles_count'];
                $ringEmojis = $rings > 0 ? str_repeat('💍', $rings) : '🛡️';
                $ringLeadersHtml .= "<li><strong>{$leader['current_name']}</strong>: {$rings} Rings {$ringEmojis} ({$leader['total_wins']}-{$leader['total_losses']})</li>";
            }
            $ringLeadersHtml .= "</ul>";
        }

        $recordStatHtml = "";
        if (!empty($vault['record_score'])) {
            $rec = $vault['record_score'];
            $recordStatHtml = "<p style='font-size: 13px; color: #94a3b8; margin-top: 10px;'>🌟 <strong>All-Time Single-Game Record:</strong> {$rec['points_scored']} pts by <em>{$rec['current_name']}</em> (Week {$rec['week_number']}, {$rec['season_year']}).</p>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏈 Atkins Football Weekly Gazette &bull; Week {$week}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #020617; color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .wrapper { max-width: 620px; margin: 30px auto; padding: 32px 24px; background-color: #0f172a; border: 1px solid #1e293b; border-radius: 16px; }
        .header-tag { display: inline-block; background-color: #f59e0b; color: #000; font-weight: 800; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; padding: 4px 10px; border-radius: 9999px; margin-bottom: 16px; }
        .title { font-size: 24px; font-weight: 900; line-height: 1.25; color: #ffffff; margin-bottom: 6px; }
        .section-box { background-color: #1e293b55; border: 1px solid #334155; border-radius: 12px; padding: 20px; margin: 20px 0; }
        .section-header { font-size: 14px; font-weight: 800; color: #fbbf24; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
        .section-body { font-size: 14px; color: #cbd5e1; line-height: 1.6; }
        .btn-row { text-align: center; margin: 30px 0; }
        .action-btn { display: inline-block; background-color: #f59e0b; color: #000000 !important; text-decoration: none; padding: 14px 28px; border-radius: 10px; font-weight: 800; font-size: 15px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); margin: 6px; }
        .secondary-btn { display: inline-block; background-color: #1e293b; color: #f8fafc !important; text-decoration: none; padding: 14px 24px; border: 1px solid #475569; border-radius: 10px; font-weight: 700; font-size: 14px; margin: 6px; }
        .footer { font-size: 12px; color: #64748b; line-height: 1.5; border-top: 1px solid #1e293b; padding-top: 20px; margin-top: 32px; text-align: center; }
    </style>
</head>
<body>
    <div class="wrapper">
        <span class="header-tag">🏈 Atkins Football Weekly &bull; {$seasonYear} Season</span>
        <div class="title">Week {$week} Action Briefing</div>
        <div style="font-size: 15px; color: #94a3b8; line-height: 1.5; margin-bottom: 16px;">
            Greetings{$nameGreeting}! Here is your intelligence report for Week {$week} of the NFL season.
        </div>
        {$franchiseNotice}

        {$urgentCallout}

        <div class="section-box">
            <div class="section-header">🎯 Straight Pick'em Action Checklist</div>
            <div class="section-body">
                &bull; <strong>Individual Game Locks:</strong> Unlike old pools with blanket Thursday locks, our system locks each game dynamically right at its scheduled kickoff.<br>
                &bull; <strong>Week {$week} Slate:</strong> {$slate['game_count']} games on the docket.<br>
                &bull; <strong>Tiebreaker Game:</strong> Remember to enter your predicted total points for the designated final game of the week when submitting your card!
            </div>
        </div>

        <div class="section-box">
            <div class="section-header">🛡️ Survivor League Directive</div>
            <div class="section-body">
                &bull; Pick one winner straight-up for Week {$week}.<br>
                &bull; Remember: You can only pick each NFL franchise <strong>once all season long</strong>.<br>
                &bull; Ensure your survival selection is submitted before that team's kickoff!
            </div>
        </div>

        <div class="section-box" style="border-color: #0284c755; background: #082f4922;">
            <div class="section-header" style="color: #38bdf8;">⚡ CBS Sports Fantasy Roster Check</div>
            <div class="section-body">
                Our 23rd season on CBS Sports is underway. If your lineup includes players featured in early games, double-check that your active roster is set before kickoff.<br><br>
                <a href="https://memrdatitans.football.cbssports.com/" style="color: #38bdf8; font-weight: 700; text-decoration: underline;" target="_blank">Open CBS Fantasy League (Remember The Titans) &rarr;</a>
            </div>
        </div>

        <div class="section-box">
            <div class="section-header">🏛️ 20-Year Dynasty Vault Highlight</div>
            <div class="section-body">
                Our private vault holds all <strong>{$vault['total_matchups']} games</strong> across {$vault['total_seasons']} seasons (2003–2026).<br>
                {$ringLeadersHtml}
                {$recordStatHtml}
                <div style="margin-top: 12px;">
                    <a href="https://football.wallyatkins.com/fantasy/rivalry" style="color: #fbbf24; text-decoration: underline;" target="_blank">Explore the All-Time Head-to-Head Rivalry Matrix &rarr;</a>
                </div>
            </div>
        </div>

        <div class="btn-row">
            <a href="https://football.wallyatkins.com/pickem" class="action-btn">Make Week {$week} Picks &rarr;</a>
            <a href="https://football.wallyatkins.com/survivor" class="secondary-btn">Survivor Pool</a>
        </div>

        <div class="footer">
            Sent by Commissioner Wally Atkins via <strong>football@wallyatkins.com</strong>.<br>
            Protected by WallyAuth Single Sign-On &bull; <a href="https://football.wallyatkins.com" style="color: #94a3b8;">football.wallyatkins.com</a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Renders plain-text email version.
     */
    public function renderText(
        int $seasonYear,
        int $week,
        ?string $recipientName = null,
        ?string $teamName = null
    ): string {
        $slate = $this->getSlateDetails($seasonYear, $week);
        $vault = $this->getVaultSpotlight();

        $nameGreeting = $recipientName ? " {$recipientName}" : "";
        $franchiseText = $teamName ? "Manager of: {$teamName}\n" : "";

        $kickoffNotice = $slate['is_kickoff_today']
            ? "⚡ KICKOFF ALERT: TONIGHT!\nFirst Game: {$slate['earliest_matchup']} at {$slate['earliest_kickoff_eastern']}\nGames lock individually at kickoff!"
            : "Upcoming: Next Game {$slate['earliest_matchup']} at {$slate['earliest_kickoff_eastern']}";

        return <<<TEXT
🏈 ATKINS FOOTBALL WEEKLY GAZETTE • WEEK {$week} ({$seasonYear})
========================================================================

Greetings{$nameGreeting}!
{$franchiseText}
{$kickoffNotice}

1. STRAIGHT PICK'EM
- {$slate['game_count']} games on the slate for Week {$week}.
- Each game locks individually at scheduled kickoff time.
- Enter your tiebreaker points prediction with your picks.
Make picks: https://football.wallyatkins.com/pickem

2. SURVIVOR POOL
- Pick 1 winner straight up.
- Each team can only be used once this season.
Survivor: https://football.wallyatkins.com/survivor

3. CBS FANTASY FOOTBALL
- Set your starting roster before kickoff!
CBS League: https://memrdatitans.football.cbssports.com/

4. 20-YEAR DYNASTY VAULT
- 23 seasons (2003-2026) and 1,818 historical matchups are archived in our private vault.
Rivalry Matrix: https://football.wallyatkins.com/fantasy/rivalry
Dynasty Vault: https://football.wallyatkins.com/fantasy/vault

========================================================================
Sent by Commissioner Wally Atkins via football@wallyatkins.com
https://football.wallyatkins.com
TEXT;
    }

    /**
     * Dispatches the newsletter to a list of recipients.
     *
     * @param array<int, array{name: string, email: string, team_name?: string}> $recipients
     * @return array{sent: int, failed: int, dry_run: bool, log: array}
     */
    public function sendNewsletter(
        int $seasonYear,
        int $week,
        array $recipients,
        bool $dryRun = false
    ): array {
        $slate = $this->getSlateDetails($seasonYear, $week);
        $subject = $slate['is_kickoff_today']
            ? "🏈 Atkins Football Week {$week} Gazette — Kickoff TONIGHT ({$slate['earliest_matchup']})!"
            : "🏈 Atkins Football Week {$week} Gazette & Schedule Briefing";

        $sentCount = 0;
        $failedCount = 0;
        $log = [];

        foreach ($recipients as $r) {
            $name = $r['name'] ?? 'Player';
            $email = trim($r['email'] ?? '');
            $team = $r['team_name'] ?? null;

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failedCount++;
                $log[] = "Skipped invalid email: '{$email}' for {$name}";
                continue;
            }

            $html = $this->renderHtml($seasonYear, $week, $name, $team);
            $text = $this->renderText($seasonYear, $week, $name, $team);

            if ($dryRun) {
                $sentCount++;
                $log[] = "[DRY-RUN] Prepared newsletter for {$name} <{$email}> (Team: {$team})";
                continue;
            }

            $boundary = "==Multipart_Boundary_x" . md5((string) microtime()) . "x";
            $headers = [
                "From: {$this->fromName} <{$this->fromEmail}>",
                "Reply-To: {$this->fromEmail}",
                "MIME-Version: 1.0",
                "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
                "X-Mailer: AtkinsFootballGazette/1.0",
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
                $success = (bool) ($this->mailer)($email, $subject, $body, $headersString);
            } else {
                $success = (bool) @mail($email, $subject, $body, $headersString);
            }

            if ($success) {
                $sentCount++;
                $log[] = "Sent newsletter to {$name} <{$email}>";
            } else {
                $failedCount++;
                $log[] = "Failed sending to {$name} <{$email}>";
            }
        }

        return [
            'sent' => $sentCount,
            'failed' => $failedCount,
            'dry_run' => $dryRun,
            'log' => $log,
        ];
    }
}
