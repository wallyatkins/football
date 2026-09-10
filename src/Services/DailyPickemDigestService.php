<?php

declare(strict_types=1);

namespace WallyFootball\Services;

use DateTimeImmutable;
use DateTimeZone;
use WallyFootball\Database\Connection;
use WallyFootball\Support\TeamData;

class DailyPickemDigestService
{
    private Connection $db;
    private ScoringEngine $scoring;
    private SportsDataService $sports;
    private string $fromEmail;
    private string $fromName;
    /** @var callable|null */
    private $mailer;

    public function __construct(
        ?Connection $db = null,
        ?ScoringEngine $scoring = null,
        ?SportsDataService $sports = null,
        ?string $fromEmail = null,
        ?string $fromName = null,
        ?callable $mailer = null
    ) {
        $this->db = $db ?? Connection::getInstance();
        $this->scoring = $scoring ?? new ScoringEngine($this->db);
        $this->sports = $sports ?? new SportsDataService($this->db);
        $this->fromEmail = $fromEmail ?? (getenv('MAIL_FROM') ?: 'football@wallyatkins.com');
        $this->fromName = $fromName ?? "Wally's NFL Pool";
        $this->mailer = $mailer;
    }

    public function getTrackingFilePath(int $season, int $week): string
    {
        $dir = dirname(__DIR__, 2) . '/data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return "{$dir}/.reported_pickem_games_{$season}_{$week}.json";
    }

    public function getReportedGameIds(int $season, int $week): array
    {
        $path = $this->getTrackingFilePath($season, $week);
        if (file_exists($path)) {
            $data = json_decode((string) file_get_contents($path), true);
            if (is_array($data)) {
                return $data;
            }
        }
        return [];
    }

    public function markGamesAsReported(int $season, int $week, array $gameIds): void
    {
        $current = $this->getReportedGameIds($season, $week);
        $merged = array_values(array_unique(array_merge($current, array_map('intval', $gameIds))));
        file_put_contents($this->getTrackingFilePath($season, $week), json_encode($merged));
    }

    public function hasNewResults(int $season, int $week): bool
    {
        $finalGames = $this->db->query(
            "SELECT id FROM games WHERE season_year = :s AND week_number = :w AND status = 'final'",
            ['s' => $season, 'w' => $week]
        );
        if (empty($finalGames)) {
            return false;
        }

        $reportedIds = $this->getReportedGameIds($season, $week);
        foreach ($finalGames as $fg) {
            if (!in_array((int) $fg['id'], $reportedIds, true)) {
                return true;
            }
        }
        return false;
    }

    public function getDigestData(int $season, int $week): array
    {
        // 1. All games for this week
        $games = $this->db->query(
            'SELECT * FROM games WHERE season_year = :s AND week_number = :w ORDER BY kickoff_time ASC, id ASC',
            ['s' => $season, 'w' => $week]
        );

        $reportedIds = $this->getReportedGameIds($season, $week);
        $finalGames = [];
        $newlyCompletedGames = [];
        $upcomingGames = [];
        $liveGames = [];
        $now = time();

        foreach ($games as $g) {
            $gId = (int) $g['id'];
            $winningTeam = null;
            if ($g['status'] === 'final' && $g['home_score'] !== null && $g['away_score'] !== null) {
                if ($g['home_score'] > $g['away_score']) {
                    $winningTeam = $g['home_team'];
                } elseif ($g['away_score'] > $g['home_score']) {
                    $winningTeam = $g['away_team'];
                }
            }
            $g['winning_team'] = $winningTeam;

            if ($g['status'] === 'final') {
                $finalGames[] = $g;
                if (!in_array($gId, $reportedIds, true)) {
                    $newlyCompletedGames[] = $g;
                }
            } elseif ($g['status'] === 'in_progress') {
                $liveGames[] = $g;
            } else {
                // Upcoming games: check if kickoff is within next 24 hours
                $kt = strtotime($g['kickoff_time']);
                if ($kt >= $now && $kt <= ($now + 86400)) {
                    $upcomingGames[] = $g;
                }
            }
        }

        // If newly completed games is empty, use all completed games so far for context
        $gamesToHighlight = !empty($newlyCompletedGames) ? $newlyCompletedGames : $finalGames;

        // 2. Standings & Pot
        $standings = $this->scoring->getWeeklyStandings($season, $week);
        $pot = $this->scoring->calculateWeeklyPot($season, $week);

        // 3. Entrants & their picks
        $entries = $this->db->query(
            'SELECT e.id as entry_id, e.user_id, e.payment_status, e.mnf_total_points_prediction,
                    u.username, u.email
             FROM pickem_entries e
             JOIN users u ON u.id = e.user_id
             WHERE e.season_year = :s AND e.week_number = :w AND e.is_locked = 1',
            ['s' => $season, 'w' => $week]
        );

        $picksByEntryId = [];
        $rawPicks = $this->db->query(
            'SELECT p.entry_id, p.game_id, p.selected_team
             FROM pickem_picks p
             JOIN pickem_entries e ON e.id = p.entry_id
             WHERE e.season_year = :s AND e.week_number = :w',
            ['s' => $season, 'w' => $week]
        );
        foreach ($rawPicks as $rp) {
            $picksByEntryId[$rp['entry_id']][$rp['game_id']] = $rp['selected_team'];
        }

        return [
            'season' => $season,
            'week' => $week,
            'games' => $games,
            'final_games' => $finalGames,
            'newly_completed' => $newlyCompletedGames,
            'games_to_highlight' => $gamesToHighlight,
            'upcoming_today' => $upcomingGames,
            'standings' => $standings,
            'pot' => $pot,
            'entries' => $entries,
            'picks_by_entry' => $picksByEntryId,
        ];
    }

    public function renderHtml(array $userEntry, array $digestData): string
    {
        $username = htmlspecialchars($userEntry['username']);
        $week = $digestData['week'];
        $season = $digestData['season'];
        $entryId = (int) $userEntry['entry_id'];
        $userPicks = $digestData['picks_by_entry'][$entryId] ?? [];

        // Find user standing
        $userStanding = null;
        foreach ($digestData['standings'] as $st) {
            if ((int) $st['entry_id'] === $entryId) {
                $userStanding = $st;
                break;
            }
        }

        $userCorrect = $userStanding['correct_picks'] ?? 0;
        $userGraded = $userStanding['total_graded'] ?? 0;
        $userRank = $userStanding['rank'] ?? '-';
        $userPending = $userStanding['pending_picks'] ?? 0;
        $totalPicks = count($userPicks);

        // Recent results rows
        $resultsHtml = '';
        foreach ($digestData['games_to_highlight'] as $g) {
            $away = $g['away_team'];
            $home = $g['home_team'];
            $awayScore = (int) $g['away_score'];
            $homeScore = (int) $g['home_score'];
            $winner = $g['winning_team'];
            $userPick = $userPicks[$g['id']] ?? null;

            $isCorrect = ($userPick !== null && $winner !== null && $userPick === $winner);
            $isMissed = ($userPick !== null && $winner !== null && $userPick !== $winner);

            $pickBadge = '';
            if ($isCorrect) {
                $pickBadge = "<span style=\"display: inline-block; padding: 3px 8px; border-radius: 6px; background-color: #064e3b; color: #34d399; font-weight: bold; font-size: 11px; border: 1px solid #059669;\">✓ Your Pick: {$userPick} (WIN +1)</span>";
            } elseif ($isMissed) {
                $pickBadge = "<span style=\"display: inline-block; padding: 3px 8px; border-radius: 6px; background-color: #881337; color: #f43f5e; font-weight: bold; font-size: 11px; border: 1px solid #be123c;\">✗ Your Pick: {$userPick} (LOSS 0)</span>";
            } else {
                $pickBadge = "<span style=\"display: inline-block; padding: 3px 8px; border-radius: 6px; background-color: #1e293b; color: #94a3b8; font-size: 11px;\">Pick: " . ($userPick ?: 'None') . "</span>";
            }

            $scoreLine = "<strong>{$away}</strong> {$awayScore} @ <strong>{$home}</strong> {$homeScore}";

            $resultsHtml .= <<<HTML
            <tr style="border-bottom: 1px solid #334155;">
                <td style="padding: 10px 12px; color: #f8fafc; font-size: 13px;">
                    {$scoreLine}
                </td>
                <td style="padding: 10px 12px; text-align: right;">
                    {$pickBadge}
                </td>
            </tr>
HTML;
        }

        // Upcoming games section
        $upcomingHtml = '';
        if (!empty($digestData['upcoming_today'])) {
            $upcomingRows = '';
            foreach ($digestData['upcoming_today'] as $ug) {
                $uAway = $ug['away_team'];
                $uHome = $ug['home_team'];
                $uPick = $userPicks[$ug['id']] ?? '—';
                $tzEt = (new DateTimeImmutable($ug['kickoff_time']))->setTimezone(new DateTimeZone('America/New_York'))->format('g:i A T');

                $upcomingRows .= <<<HTML
                <tr style="border-bottom: 1px solid #334155;">
                    <td style="padding: 8px 12px; color: #f8fafc; font-size: 12px;">
                        <strong>{$uAway} @ {$uHome}</strong>
                        <span style="color: #94a3b8; font-size: 11px; margin-left: 6px;">({$tzEt})</span>
                    </td>
                    <td style="padding: 8px 12px; text-align: right; color: #f59e0b; font-weight: bold; font-size: 12px;">
                        Your Pick: {$uPick}
                    </td>
                </tr>
HTML;
            }

            $upcomingHtml = <<<HTML
            <div style="margin-top: 24px; padding: 16px; background-color: #1e293b; border-radius: 12px; border: 1px solid #334155;">
                <div style="font-size: 13px; font-weight: bold; color: #f59e0b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                    ⚡ On Deck Today / Tonight
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    {$upcomingRows}
                </table>
            </div>
HTML;
        }

        // Leaderboard table
        $standingsRows = '';
        foreach (array_slice($digestData['standings'], 0, 10) as $row) {
            $isYou = ((int) $row['entry_id'] === $entryId);
            $rowBg = $isYou ? 'background-color: #064e3b20; border-left: 3px solid #10b981;' : '';
            $rRank = $row['rank'];
            $rUser = htmlspecialchars($row['username']) . ($isYou ? " (You)" : "");
            $rCorrect = $row['correct_picks'];
            $rGraded = $row['total_graded'];
            $rMode = $row['is_paid'] ? '🟢 $10 Cash' : '🎮 Free';

            $standingsRows .= <<<HTML
            <tr style="border-bottom: 1px solid #1e293b; {$rowBg}">
                <td style="padding: 8px 10px; font-weight: bold; color: #cbd5e1; text-align: center; font-family: monospace;">#{$rRank}</td>
                <td style="padding: 8px 10px; font-weight: bold; color: #f8fafc;">{$rUser}</td>
                <td style="padding: 8px 10px; text-align: center; color: #34d399; font-weight: bold; font-family: monospace;">{$rCorrect} / {$rGraded}</td>
                <td style="padding: 8px 10px; text-align: right; color: #94a3b8; font-size: 11px;">{$rMode}</td>
            </tr>
HTML;
        }

        $dateFormatted = date('l, F j, Y');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Week {$week} Pick'em Morning Update</title>
</head>
<body style="margin: 0; padding: 0; background-color: #090d16; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #f8fafc;">
    <div style="max-width: 620px; margin: 0 auto; background-color: #0f172a; border-radius: 16px; overflow: hidden; border: 1px solid #334155; margin-top: 20px; margin-bottom: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
        
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 24px 24px 20px 24px; border-bottom: 2px solid #f59e0b;">
            <div style="font-size: 11px; font-family: monospace; font-weight: bold; color: #f59e0b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">
                🏈 Atkins NFL Pick'em &bull; Season {$season}
            </div>
            <h1 style="margin: 0; font-size: 22px; font-weight: 900; color: #ffffff; letter-spacing: -0.5px;">
                Week {$week} Morning Briefing
            </h1>
            <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">
                {$dateFormatted}
            </div>
        </div>

        <!-- Body -->
        <div style="padding: 24px;">
            <p style="font-size: 15px; color: #e2e8f0; margin-top: 0; line-height: 1.5;">
                Good morning, <strong>{$username}</strong>! Here is your daily status report on how your NFL picks performed and where you sit on the league leaderboard.
            </p>

            <!-- Scorecard Hero -->
            <div style="background-color: #1e293b; border-radius: 12px; padding: 18px; margin-bottom: 24px; border: 1px solid #475569; display: flex; justify-content: space-around; text-align: center;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="text-align: center; width: 33%;">
                            <div style="font-size: 11px; font-weight: bold; color: #94a3b8; text-transform: uppercase;">Your Score</div>
                            <div style="font-size: 24px; font-weight: 900; color: #34d399; font-family: monospace; margin-top: 4px;">{$userCorrect} / {$userGraded}</div>
                            <div style="font-size: 10px; color: #64748b;">Correct Picks</div>
                        </td>
                        <td style="text-align: center; width: 33%; border-left: 1px solid #334155; border-right: 1px solid #334155;">
                            <div style="font-size: 11px; font-weight: bold; color: #94a3b8; text-transform: uppercase;">Current Rank</div>
                            <div style="font-size: 24px; font-weight: 900; color: #f59e0b; font-family: monospace; margin-top: 4px;">#{$userRank}</div>
                            <div style="font-size: 10px; color: #64748b;">In Week {$week}</div>
                        </td>
                        <td style="text-align: center; width: 33%;">
                            <div style="font-size: 11px; font-weight: bold; color: #94a3b8; text-transform: uppercase;">In Play</div>
                            <div style="font-size: 24px; font-weight: 900; color: #e2e8f0; font-family: monospace; margin-top: 4px;">{$userPending}</div>
                            <div style="font-size: 10px; color: #64748b;">Games Remaining</div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Recent Results -->
            <div style="margin-bottom: 24px;">
                <div style="font-size: 13px; font-weight: bold; color: #f8fafc; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                    🏁 Completed Game Results
                </div>
                <div style="background-color: #1e293b; border-radius: 12px; border: 1px solid #334155; overflow: hidden;">
                    <table style="width: 100%; border-collapse: collapse;">
                        {$resultsHtml}
                    </table>
                </div>
            </div>

            {$upcomingHtml}

            <!-- Standings Snapshot -->
            <div style="margin-top: 24px; margin-bottom: 28px;">
                <div style="font-size: 13px; font-weight: bold; color: #f8fafc; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                    🏆 League Leaderboard Snapshot
                </div>
                <div style="background-color: #1e293b; border-radius: 12px; border: 1px solid #334155; overflow: hidden;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                        <thead>
                            <tr style="background-color: #0f172a; border-bottom: 1px solid #334155; color: #94a3b8; font-size: 10px; text-transform: uppercase;">
                                <th style="padding: 8px 10px; text-align: center;">Rank</th>
                                <th style="padding: 8px 10px; text-align: left;">Player</th>
                                <th style="padding: 8px 10px; text-align: center;">Correct</th>
                                <th style="padding: 8px 10px; text-align: right;">Mode</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$standingsRows}
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Call to Action -->
            <div style="text-align: center; margin: 32px 0 16px 0;">
                <a href="https://football.wallyatkins.com/pickem/standings?week={$week}" 
                   style="display: inline-block; padding: 14px 28px; background-color: #f59e0b; color: #0f172a; font-weight: 900; font-size: 14px; text-decoration: none; border-radius: 12px; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);">
                    View Full Standings &amp; Opponent Picks &rarr;
                </a>
            </div>

        </div>

        <!-- Footer -->
        <div style="background-color: #090d16; padding: 18px 24px; border-top: 1px solid #1e293b; text-align: center; font-size: 11px; color: #64748b; line-height: 1.5;">
            Sent by Commissioner Wally Atkins &bull; <a href="mailto:football@wallyatkins.com" style="color: #94a3b8; text-decoration: underline;">football@wallyatkins.com</a><br>
            Protected by WallyAuth SSO &bull; <a href="https://football.wallyatkins.com" style="color: #94a3b8; text-decoration: underline;">football.wallyatkins.com</a>
        </div>

    </div>
</body>
</html>
HTML;
    }

    public function renderText(array $userEntry, array $digestData): string
    {
        $username = $userEntry['username'];
        $week = $digestData['week'];
        $season = $digestData['season'];
        $entryId = (int) $userEntry['entry_id'];
        $userPicks = $digestData['picks_by_entry'][$entryId] ?? [];

        $userStanding = null;
        foreach ($digestData['standings'] as $st) {
            if ((int) $st['entry_id'] === $entryId) {
                $userStanding = $st;
                break;
            }
        }

        $userCorrect = $userStanding['correct_picks'] ?? 0;
        $userGraded = $userStanding['total_graded'] ?? 0;
        $userRank = $userStanding['rank'] ?? '-';
        $userPending = $userStanding['pending_picks'] ?? 0;

        $lines = [];
        $lines[] = "🏈 ATKINS NFL PICK'EM • WEEK {$week} MORNING UPDATE ({$season})";
        $lines[] = "============================================================";
        $lines[] = "Good morning, {$username}!";
        $lines[] = "";
        $lines[] = "YOUR STATUS:";
        $lines[] = "- Score: {$userCorrect} Correct / {$userGraded} Graded";
        $lines[] = "- Current Rank: #{$userRank}";
        $lines[] = "- Pending Games: {$userPending}";
        $lines[] = "";
        $lines[] = "RECENT RESULTS:";
        foreach ($digestData['games_to_highlight'] as $g) {
            $pick = $userPicks[$g['id']] ?? 'None';
            $winner = $g['winning_team'];
            $res = ($pick === $winner) ? '✓ WIN (+1)' : '✗ LOSS (0)';
            $lines[] = "- {$g['away_team']} ({$g['away_score']}) @ {$g['home_team']} ({$g['home_score']}) — Your Pick: {$pick} [{$res}]";
        }

        if (!empty($digestData['upcoming_today'])) {
            $lines[] = "";
            $lines[] = "TODAY'S SLATE:";
            foreach ($digestData['upcoming_today'] as $ug) {
                $pick = $userPicks[$ug['id']] ?? '—';
                $tzEt = (new DateTimeImmutable($ug['kickoff_time']))->setTimezone(new DateTimeZone('America/New_York'))->format('g:i A T');
                $lines[] = "- {$ug['away_team']} @ {$ug['home_team']} ({$tzEt}) — Your Pick: {$pick}";
            }
        }

        $lines[] = "";
        $lines[] = "LEADERBOARD TOP STANDINGS:";
        foreach (array_slice($digestData['standings'], 0, 5) as $row) {
            $lines[] = "#{$row['rank']} {$row['username']}: {$row['correct_picks']}/{$row['total_graded']} correct";
        }

        $lines[] = "";
        $lines[] = "View live standings & opponent picks:";
        $lines[] = "https://football.wallyatkins.com/pickem/standings?week={$week}";
        $lines[] = "============================================================";
        $lines[] = "Commissioner Wally Atkins • football@wallyatkins.com";

        return implode("\n", $lines);
    }

    public function sendDigest(
        int $season,
        int $week,
        bool $force = false,
        ?string $testTo = null,
        bool $dryRun = false
    ): array {
        // Opportunistically ensure latest scores
        $this->sports->syncIfNeeded($season, $week, 300);

        if (!$force && !$this->hasNewResults($season, $week)) {
            return [
                'status' => 'skipped',
                'reason' => 'No new completed games to report since last digest',
                'sent' => 0,
                'failed' => 0,
                'log' => [],
            ];
        }

        $digestData = $this->getDigestData($season, $week);
        if (empty($digestData['entries'])) {
            return [
                'status' => 'skipped',
                'reason' => 'No locked entries for this week',
                'sent' => 0,
                'failed' => 0,
                'log' => [],
            ];
        }

        $sentCount = 0;
        $failedCount = 0;
        $log = [];
        $subject = "🏈 Atkins NFL Pick'em: Week {$week} Morning Update — Your Picks & Leaderboard";

        // Determine target list:
        $recipients = $digestData['entries'];
        if ($testTo !== null && trim($testTo) !== '') {
            // Test mode: Send only to test recipient using first entry data
            $first = $recipients[0];
            $first['email'] = trim($testTo);
            $recipients = [$first];
        }

        foreach ($recipients as $entry) {
            $email = trim($entry['email'] ?? '');
            $name = $entry['username'] ?? 'Player';

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failedCount++;
                $log[] = "Invalid email for {$name}: '{$email}'";
                continue;
            }

            $html = $this->renderHtml($entry, $digestData);
            $text = $this->renderText($entry, $digestData);

            if ($dryRun) {
                $sentCount++;
                $log[] = "[DRY-RUN] Prepared morning update for {$name} <{$email}>";
                continue;
            }

            $boundary = "==Pickem_Digest_" . md5((string) microtime()) . "==";
            $headers = [
                "From: {$this->fromName} <{$this->fromEmail}>",
                "Reply-To: {$this->fromEmail}",
                "MIME-Version: 1.0",
                "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
                "X-Mailer: AtkinsPickemDigest/1.0",
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
                $log[] = "Sent morning update to {$name} <{$email}>";
            } else {
                $failedCount++;
                $log[] = "Failed sending to {$name} <{$email}>";
            }
        }

        // Only mark games as reported if not test mode and not dry-run
        if ($testTo === null && !$dryRun && $sentCount > 0) {
            $reportedIds = array_column($digestData['final_games'], 'id');
            $this->markGamesAsReported($season, $week, $reportedIds);
        }

        return [
            'status' => 'success',
            'sent' => $sentCount,
            'failed' => $failedCount,
            'dry_run' => $dryRun,
            'log' => $log,
        ];
    }
}
