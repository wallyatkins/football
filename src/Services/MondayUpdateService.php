<?php

declare(strict_types=1);

namespace WallyFootball\Services;

use WallyFootball\Database\Connection;

class MondayUpdateService
{
    private Connection $db;
    private ScoringEngine $scoring;
    private string $fromEmail;
    private string $fromName;
    /** @var callable|null */
    private $mailer;

    public function __construct(
        ?Connection $db = null,
        ?ScoringEngine $scoring = null,
        ?string $fromEmail = null,
        ?string $fromName = null,
        ?callable $mailer = null
    ) {
        $this->db = $db ?? Connection::getInstance();
        $this->scoring = $scoring ?? new ScoringEngine($this->db);
        $this->fromEmail = $fromEmail ?? (getenv('MAIL_FROM') ?: 'football@wallyatkins.com');
        $this->fromName = $fromName ?? "Wally's Football League";
        $this->mailer = $mailer;
    }

    /**
     * Gathers all data for the Monday update report.
     *
     * @return array<string, mixed>
     */
    public function getMondayData(int $season = 2026, int $week = 2): array
    {
        // 1. Fetch all games for the week
        $games = $this->db->query(
            'SELECT * FROM games WHERE season_year = :s AND week_number = :w ORDER BY kickoff_time ASC, id ASC',
            ['s' => $season, 'w' => $week]
        );

        $finalGames = [];
        $mnfGames = [];
        foreach ($games as $g) {
            if ($g['status'] === 'final') {
                $finalGames[] = $g;
            } elseif ((int) ($g['is_mnf'] ?? 0) === 1 || str_contains(strtolower((string) $g['status']), 'sched')) {
                $mnfGames[] = $g;
            }
        }

        // 2. Fetch pick'em entries and standings
        $standings = $this->scoring->getWeeklyStandings($season, $week);

        // 3. Fetch all picks for this week
        $picksRaw = $this->db->query(
            'SELECT pe.id as entry_id, pe.user_id, u.username, u.email, pp.game_id, pp.selected_team
             FROM pickem_entries pe
             JOIN users u ON pe.user_id = u.id
             LEFT JOIN pickem_picks pp ON pp.entry_id = pe.id
             WHERE pe.season_year = :s AND pe.week_number = :w',
            ['s' => $season, 'w' => $week]
        );

        $picksByEntry = [];
        $picksByGame = [];
        foreach ($picksRaw as $p) {
            $eId = (int) $p['entry_id'];
            $gId = (int) ($p['game_id'] ?? 0);
            $team = $p['selected_team'];
            if ($gId > 0 && $team !== null) {
                $picksByEntry[$eId][$gId] = $team;
                $picksByGame[$gId][$team] = ($picksByGame[$gId][$team] ?? 0) + 1;
            }
        }

        // Attach MNF pick to each standing row
        $mnfGame = $mnfGames[0] ?? null;
        $mnfGameId = $mnfGame ? (int) $mnfGame['id'] : 0;
        foreach ($standings as &$st) {
            $eId = (int) $st['entry_id'];
            $st['mnf_pick'] = ($mnfGameId > 0) ? ($picksByEntry[$eId][$mnfGameId] ?? 'None') : 'None';
        }
        unset($st);

        // 4. Survivor picks for the week
        $survivorPicks = $this->db->query(
            'SELECT sp.user_id, u.username, sp.selected_team, sp.is_eliminated
             FROM survivor_picks sp
             JOIN users u ON sp.user_id = u.id
             WHERE sp.season_year = :s AND sp.week_number = :w',
            ['s' => $season, 'w' => $week]
        );

        return [
            'season' => $season,
            'week' => $week,
            'games_count' => count($games),
            'final_games' => $finalGames,
            'mnf_games' => $mnfGames,
            'mnf_game' => $mnfGame,
            'standings' => $standings,
            'picks_by_game' => $picksByGame,
            'survivor_picks' => $survivorPicks,
        ];
    }

    /**
     * Renders responsive HTML for the Monday newsletter.
     *
     * @param array<string, mixed> $data
     */
    public function renderHtml(array $data): string
    {
        $week = (int) $data['week'];
        $season = (int) $data['season'];
        $standings = $data['standings'];
        $mnfGame = $data['mnf_game'];

        // Standings table rows
        $standingsRows = '';
        foreach ($standings as $st) {
            $rank = $st['rank'];
            $username = htmlspecialchars($st['username']);
            $score = "{$st['correct_picks']}-" . ($st['total_graded'] - $st['correct_picks']);
            $pct = $st['total_graded'] > 0 ? round(($st['correct_picks'] / $st['total_graded']) * 100) . '%' : '0%';
            $mnfPick = htmlspecialchars($st['mnf_pick']);
            $predictedMnf = $st['predicted_mnf'] !== null ? "{$st['predicted_mnf']} pts" : '—';

            $rowBg = ($rank === 1 || $rank === 2) ? 'background-color: #1e293b;' : '';
            $rankBadge = $rank === 1 ? '🥇 1' : ($rank === 2 ? '🥈 2' : ($rank === 3 ? '🥉 3' : (string) $rank));
            $mnfColor = ($mnfPick === 'NYG') ? '#3b82f6' : (($mnfPick === 'LAR') ? '#f59e0b' : '#94a3b8');

            $standingsRows .= <<<HTML
            <tr style="border-bottom: 1px solid #334155; {$rowBg}">
                <td style="padding: 10px 12px; font-weight: 800; color: #f8fafc; font-size: 13px;">{$rankBadge}</td>
                <td style="padding: 10px 12px; font-weight: 700; color: #ffffff; font-size: 13px;">{$username}</td>
                <td style="padding: 10px 12px; text-align: center; color: #34d399; font-weight: 800; font-size: 13px;">{$score}</td>
                <td style="padding: 10px 12px; text-align: center; color: #94a3b8; font-size: 12px;">{$pct}</td>
                <td style="padding: 10px 12px; text-align: center; color: {$mnfColor}; font-weight: 800; font-size: 13px;">{$mnfPick}</td>
                <td style="padding: 10px 12px; text-align: right; color: #cbd5e1; font-size: 12px;">{$predictedMnf}</td>
            </tr>
HTML;
        }

        // MNF game details
        $mnfMatchup = $mnfGame ? "{$mnfGame['away_team']} @ {$mnfGame['home_team']}" : "NYG @ LAR";
        $mnfKickoff = "Tonight &bull; 8:15 PM ET / 5:15 PM PT &bull; ESPN / ABC";

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏈 Atkins Football Pool — Week {$week} Monday Huddle</title>
    <style>
        body { margin: 0; padding: 0; background-color: #020617; color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .wrapper { max-width: 640px; margin: 30px auto; padding: 32px 24px; background-color: #0f172a; border: 1px solid #1e293b; border-radius: 16px; }
        .header-tag { display: inline-block; background-color: #f59e0b; color: #000000; font-weight: 800; font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; padding: 4px 12px; border-radius: 9999px; margin-bottom: 16px; }
        .title { font-size: 26px; font-weight: 900; line-height: 1.25; color: #ffffff; margin-bottom: 8px; }
        .subtitle { font-size: 15px; color: #94a3b8; line-height: 1.5; margin-bottom: 24px; }
        .section-box { background-color: #1e293b55; border: 1px solid #334155; border-radius: 12px; padding: 20px; margin: 22px 0; }
        .section-header { font-size: 14px; font-weight: 800; color: #fbbf24; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px; }
        .card { background-color: #1e293b; border-radius: 8px; padding: 14px 16px; margin-bottom: 12px; border-left: 4px solid #38bdf8; }
        .card-danger { border-left-color: #f43f5e; }
        .card-gold { border-left-color: #f59e0b; }
        .card-green { border-left-color: #10b981; }
        .card-title { font-size: 14px; font-weight: 800; color: #ffffff; margin-bottom: 4px; }
        .card-body { font-size: 13.5px; color: #cbd5e1; line-height: 1.5; }
        .table-wrap { overflow-x: auto; margin-top: 12px; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 10px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; background-color: #0f172a; border-bottom: 2px solid #334155; }
        .btn-row { text-align: center; margin: 28px 0 16px; }
        .action-btn { display: inline-block; background-color: #f59e0b; color: #000000 !important; text-decoration: none; padding: 13px 26px; border-radius: 8px; font-weight: 800; font-size: 14px; margin: 5px; }
        .secondary-btn { display: inline-block; background-color: #1e293b; color: #f8fafc !important; text-decoration: none; padding: 13px 22px; border: 1px solid #475569; border-radius: 8px; font-weight: 700; font-size: 14px; margin: 5px; }
        .footer { font-size: 12px; color: #64748b; line-height: 1.5; border-top: 1px solid #1e293b; padding-top: 20px; margin-top: 32px; text-align: center; }
    </style>
</head>
<body>
    <div class="wrapper">
        <span class="header-tag">🏈 Atkins Football Pool &bull; Monday Huddle</span>
        <div class="title">Week {$week} Monday Update: The Chaos, The Standings &amp; Tonight's SoFi Showdown!</div>
        <div class="subtitle">
            Happy Monday, football family! 15 games are officially in the books, only 1 remains, and Week 2 has unleashed absolute, unadulterated NFL chaos across our leaderboard.
        </div>

        <!-- SHOWDOWN SPOTLIGHT CARD -->
        <div style="background: linear-gradient(135deg, #1e3a8a 0%, #1e1b4b 100%); border: 2px solid #60a5fa; border-radius: 14px; padding: 22px; margin: 24px 0;">
            <div style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #93c5fd; margin-bottom: 6px;">
                ⚡ TONIGHT ON MONDAY NIGHT FOOTBALL &bull; WINNER TAKE ALL
            </div>
            <div style="font-size: 22px; font-weight: 900; color: #ffffff; margin-bottom: 6px;">
                {$mnfMatchup}
            </div>
            <div style="font-size: 13px; color: #bfdbfe; margin-bottom: 14px;">
                {$mnfKickoff}
            </div>
            <div style="font-size: 14px; color: #e0f2fe; line-height: 1.6;">
                You couldn't script a tighter finish. <strong>Bart Atkins</strong> and <strong>Michelle Weaver</strong> are tied at the top with <strong>10 wins apiece</strong>—and they are on opposite sides tonight!<br><br>
                &bull; <strong>If the Rams win at SoFi:</strong> Michelle takes 1st place outright at <strong>11-5</strong>!<br>
                &bull; <strong>If the Giants pull the road upset:</strong> Bart takes 1st place outright at <strong>11-5</strong>!<br>
                Meanwhile, the 9-win chasing pack (<strong>Logan</strong>, <strong>Tamara</strong>, and <strong>Rob</strong>) all picked LAR. If the Rams win, they surge to 10-6 to join Bart for a 2nd-place podium finish!
            </div>
        </div>

        <!-- WEEKEND REWIND -->
        <div class="section-box">
            <div class="section-header">🔥 Weekend Rewind: The Games That Shook the Pool</div>

            <div class="card card-danger">
                <div class="card-title">🚨 The 100% Pool Massacre: Browns 23, Buccaneers 19</div>
                <div class="card-body">
                    All 11 pool participants confidently selected Tampa Bay at home. Exactly <strong>zero</strong> picked Cleveland. Baker Mayfield and the Bucs offense stalled, giving the entire league a collective goose egg!
                </div>
            </div>

            <div class="card card-gold">
                <div class="card-title">🧙‍♂️ The Lone Prophet: Saints 24, Ravens 17</div>
                <div class="card-body">
                    10 out of 11 players rode Lamar Jackson and the Ravens. Only <strong>Bart Atkins</strong> had the vision to take New Orleans on the road—and that lone dagger is the exact reason Bart is sitting at #1 today!
                </div>
            </div>

            <div class="card card-gold">
                <div class="card-title">🎰 The Vegas Heist: Raiders 26, Chargers 14</div>
                <div class="card-body">
                    9 players picked the Chargers in their home stadium. Only <strong>Michelle Weaver</strong> and <strong>Charlie Van Dine</strong> backed the Silver &amp; Black, securing a pivotal 2-point swing!
                </div>
            </div>

            <div class="card card-green">
                <div class="card-title">🐾 The Carolina Catastrophe: Panthers 34, Falcons 3</div>
                <div class="card-body">
                    Only <strong>Bart</strong>, <strong>Logan</strong>, and <strong>Rob</strong> believed in Carolina. The Panthers demolished Atlanta by 31 points, simultaneously devastating 8 Pick'em cards and knocking Commissioner Wally completely out of the Survivor Pool!
                </div>
            </div>

            <div class="card">
                <div class="card-title">🧱 NFC North Rockfight: Vikings 9, Bears 3</div>
                <div class="card-body">
                    4 field goals, 0 touchdowns, 12 total points. Minnesota and Chicago treated us to an authentic 1940s defensive grinder at Soldier Field. 7 players had Chicago, but Minnesota's defense held firm.
                </div>
            </div>

            <div class="card">
                <div class="card-title">💓 The Cardiac Finish: Chiefs 33, Colts 30</div>
                <div class="card-body">
                    10 players backed Kansas City, but Anthony Richardson and the Colts made everyone sweat until Harrison Butker's clutch boot sealed it late in the 4th quarter.
                </div>
            </div>
        </div>

        <!-- STANDINGS TABLE -->
        <div class="section-box">
            <div class="section-header">📊 Week 2 Pick'em Leaderboard (Through 15 Games)</div>
            <div style="font-size: 13px; color: #94a3b8; margin-bottom: 12px;">
                Current standings heading into tonight's Giants vs. Rams showdown:
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Player</th>
                            <th style="text-align: center;">Record</th>
                            <th style="text-align: center;">Pct</th>
                            <th style="text-align: center;">MNF Pick</th>
                            <th style="text-align: right;">Tiebreak</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$standingsRows}
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SURVIVOR STATUS -->
        <div class="section-box" style="border-color: #059669; background: #064e3b22;">
            <div class="section-header" style="color: #34d399;">🛡️ Survivor League Casualty Report</div>
            <div style="font-size: 14px; color: #cbd5e1; line-height: 1.6;">
                &bull; <strong>Michaux Early:</strong> Locked in <strong>Cincinnati</strong>, and the Bengals handled Houston 20-6. Michaux moves safely on to Week 3! 🟢<br>
                &bull; <strong>Wally Atkins:</strong> Trusted the <strong>Atlanta Falcons</strong> at home. A 34-3 blowout loss to Carolina sends the Commissioner to the graveyard. Rest in peace! 💀🪦
            </div>
        </div>

        <!-- NEXT STEPS -->
        <div class="section-box" style="border-color: #0284c755; background: #082f4922;">
            <div class="section-header" style="color: #38bdf8;">📅 Week 3 Opens Tuesday Morning</div>
            <div style="font-size: 14px; color: #cbd5e1; line-height: 1.6;">
                Once tonight's game goes final and the official Week 2 payouts and awards are crowned, the <strong>Week 3 slate will open Tuesday morning</strong>. Remember to check your CBS Fantasy matchups and lock in your Thursday night pick early!
            </div>
        </div>

        <div class="btn-row">
            <a href="https://football.wallyatkins.com/pickem/standings?week={$week}" class="action-btn">View Live Standings &rarr;</a>
            <a href="https://football.wallyatkins.com/survivor" class="secondary-btn">Survivor Board</a>
        </div>

        <div class="footer">
            Sent by Commissioner Wally Atkins &bull; <strong>football@wallyatkins.com</strong><br>
            Private League Portal: <a href="https://football.wallyatkins.com" style="color: #94a3b8; text-decoration: underline;">football.wallyatkins.com</a> &bull; Powered by WallyAuth SSO
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Renders plain-text email version.
     *
     * @param array<string, mixed> $data
     */
    public function renderText(array $data): string
    {
        $week = (int) $data['week'];
        $season = (int) $data['season'];
        $standings = $data['standings'];
        $mnfGame = $data['mnf_game'];
        $mnfMatchup = $mnfGame ? "{$mnfGame['away_team']} @ {$mnfGame['home_team']}" : "NYG @ LAR";

        $lines = [];
        $lines[] = "============================================================";
        $lines[] = "🏈 ATKINS FOOTBALL POOL — WEEK {$week} MONDAY UPDATE";
        $lines[] = "============================================================";
        $lines[] = "";
        $lines[] = "Happy Monday, football family! 15 games are in the books, 1 to go, and Week 2 has officially scrambled our leaderboard.";
        $lines[] = "";
        $lines[] = "⚡ TONIGHT'S MONDAY NIGHT SHOWDOWN (WINNER TAKE ALL):";
        $lines[] = "Matchup: {$mnfMatchup} (8:15 PM ET, ESPN/ABC at SoFi Stadium)";
        $lines[] = "- Bart Atkins (10-5) picked NEW YORK GIANTS (Tiebreak: 50 pts)";
        $lines[] = "- Michelle Weaver (10-5) picked LOS ANGELES RAMS (Tiebreak: 67 pts)";
        $lines[] = "If Rams win -> Michelle wins Week 2 outright at 11-5!";
        $lines[] = "If Giants win -> Bart wins Week 2 outright at 11-5!";
        $lines[] = "(The 9-point chasers: Logan, Tamara, Rob all picked LAR. If Rams win, they tie Bart at 10-6 for 2nd!)";
        $lines[] = "";
        $lines[] = "🔥 WEEKEND REWIND — THE GAMES THAT SHOOK THE POOL:";
        $lines[] = "1. BROWNS 23, BUCCANEERS 19 — 100% pool massacre! All 11 players picked TB; 0 picked Cleveland.";
        $lines[] = "2. SAINTS 24, RAVENS 17 — Bart Atkins was the SOLE player in the entire pool to pick New Orleans!";
        $lines[] = "3. RAIDERS 26, CHARGERS 14 — Only Michelle Weaver and Charlie Van Dine picked the Raiders!";
        $lines[] = "4. PANTHERS 34, FALCONS 3 — Only Bart, Logan, and Rob picked Carolina. Falcons loss eliminated Wally from Survivor!";
        $lines[] = "5. VIKINGS 9, BEARS 3 — Defensive rockfight! 4 FGs, 0 TDs, 12 total points.";
        $lines[] = "6. CHIEFS 33, COLTS 30 — Cardiac Chiefs survive an Indy scare late.";
        $lines[] = "";
        $lines[] = "📊 WEEK 2 PICK'EM STANDINGS (Through 15 Games):";
        $lines[] = "------------------------------------------------------------";
        $lines[] = sprintf("%-5s %-16s %-8s %-10s %-8s", "Rank", "Player", "Score", "MNF Pick", "Tiebreak");
        $lines[] = "------------------------------------------------------------";
        foreach ($standings as $st) {
            $score = "{$st['correct_picks']}-" . ($st['total_graded'] - $st['correct_picks']);
            $lines[] = sprintf("%-5s %-16s %-8s %-10s %-8s", "#{$st['rank']}", $st['username'], $score, $st['mnf_pick'], "{$st['predicted_mnf']} pts");
        }
        $lines[] = "------------------------------------------------------------";
        $lines[] = "";
        $lines[] = "🛡️ SURVIVOR POOL REPORT:";
        $lines[] = "- Michaux Early: Picked CIN (Won 20-6) -> ALIVE & MOVING ON! 🟢";
        $lines[] = "- Wally Atkins: Picked ATL (Lost 3-34) -> ELIMINATED! 💀🪦";
        $lines[] = "";
        $lines[] = "📅 Week 3 slate opens Tuesday morning!";
        $lines[] = "Track live: https://football.wallyatkins.com/pickem/standings?week={$week}";
        $lines[] = "============================================================";
        $lines[] = "Sent by Commissioner Wally Atkins • football@wallyatkins.com";

        return implode("\n", $lines);
    }

    /**
     * Dispatches Monday update email.
     *
     * @return array{sent: int, failed: int, mode: string, recipients: array<string>}
     */
    public function dispatchUpdate(
        int $season = 2026,
        int $week = 2,
        ?string $previewTo = null,
        bool $dryRun = false
    ): array {
        $data = $this->getMondayData($season, $week);
        $subject = ($previewTo !== null)
            ? "🏈 [PREVIEW] Atkins Football Week {$week} Monday Huddle: Standings & SoFi Showdown!"
            : "🏈 Atkins Football Week {$week} Monday Huddle: Standings & Tonight's SoFi Showdown!";

        $html = $this->renderHtml($data);
        $text = $this->renderText($data);

        $recipients = [];
        if ($previewTo !== null && trim($previewTo) !== '') {
            $recipients[] = [
                'name' => 'Wally Atkins (Preview)',
                'email' => trim($previewTo),
            ];
        } else {
            // Gather all participants
            $users = $this->db->query(
                'SELECT DISTINCT u.username, u.email
                 FROM pickem_entries pe
                 JOIN users u ON pe.user_id = u.id
                 WHERE pe.season_year = :s AND pe.week_number = :w AND u.email IS NOT NULL AND u.email != ""',
                ['s' => $season, 'w' => $week]
            );
            foreach ($users as $u) {
                $email = strtolower(trim($u['email']));
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $recipients[] = [
                        'name' => $u['username'],
                        'email' => $email,
                    ];
                }
            }
        }

        $sentCount = 0;
        $failedCount = 0;
        $recipientEmails = [];

        foreach ($recipients as $r) {
            $email = $r['email'];
            $recipientEmails[] = $email;

            if ($dryRun) {
                $sentCount++;
                continue;
            }

            $boundary = "==Monday_Update_" . md5((string) microtime()) . "==";
            $headers = [
                "From: {$this->fromName} <{$this->fromEmail}>",
                "Reply-To: {$this->fromEmail}",
                "MIME-Version: 1.0",
                "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
                "X-Mailer: AtkinsMondayHuddle/1.0",
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
            } else {
                $failedCount++;
            }
        }

        return [
            'sent' => $sentCount,
            'failed' => $failedCount,
            'mode' => $dryRun ? 'dry_run' : ($previewTo ? 'preview' : 'broadcast'),
            'recipients' => $recipientEmails,
        ];
    }
}
