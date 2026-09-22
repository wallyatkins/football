<?php

declare(strict_types=1);

namespace WallyFootball\Services;

use DateTimeImmutable;
use DateTimeZone;
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
     * Gathers all data for the update report.
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
        $mnfGame = null;
        foreach ($games as $g) {
            if ((int) ($g['is_mnf'] ?? 0) === 1) {
                $mnfGame = $g;
            }
            if ($g['status'] === 'final') {
                $finalGames[] = $g;
            } elseif ((int) ($g['is_mnf'] ?? 0) === 1 || str_contains(strtolower((string) $g['status']), 'sched')) {
                $mnfGames[] = $g;
            }
        }

        // Fallback to last game if no is_mnf flag set
        if ($mnfGame === null && !empty($games)) {
            $mnfGame = end($games);
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

        // 5. Pot & Winners
        $pot = $this->scoring->calculateWeeklyPot($season, $week);
        $winnersOverall = $this->scoring->getWeeklyWinnersByMode($season, $week, null);
        $winnersPaid = $this->scoring->getWeeklyWinnersByMode($season, $week, 'paid');
        $winnersFree = $this->scoring->getWeeklyWinnersByMode($season, $week, 'free');

        $isMnfFinal = ($mnfGame !== null && $mnfGame['status'] === 'final');
        $isWeekComplete = count($games) > 0 && count($finalGames) === count($games);
        $isRecapMode = $isMnfFinal || $isWeekComplete;

        $actualMnfScore = null;
        $actualMnfTotal = null;
        if ($mnfGame && $mnfGame['status'] === 'final' && $mnfGame['home_score'] !== null && $mnfGame['away_score'] !== null) {
            $actualMnfScore = "{$mnfGame['away_team']} {$mnfGame['away_score']}, {$mnfGame['home_team']} {$mnfGame['home_score']}";
            $actualMnfTotal = (int) $mnfGame['home_score'] + (int) $mnfGame['away_score'];
        }

        // 6. Next week details
        $nextWeek = $week + 1;
        $nextWeekGames = $this->db->query(
            'SELECT * FROM games WHERE season_year = :s AND week_number = :w ORDER BY kickoff_time ASC, id ASC',
            ['s' => $season, 'w' => $nextWeek]
        );
        $nextWeekFirstGame = $nextWeekGames[0] ?? null;
        $nextWeekKickoffFormatted = null;
        $nextWeekMatchup = null;
        if ($nextWeekFirstGame) {
            $nextWeekMatchup = "{$nextWeekFirstGame['away_team']} @ {$nextWeekFirstGame['home_team']}";
            $kt = strtotime((string) $nextWeekFirstGame['kickoff_time']);
            $nextWeekKickoffFormatted = (new DateTimeImmutable("@{$kt}"))
                ->setTimezone(new DateTimeZone('America/New_York'))
                ->format('l, M j @ g:i A T');
        }

        return [
            'season' => $season,
            'week' => $week,
            'games_count' => count($games),
            'final_games' => $finalGames,
            'mnf_games' => $mnfGames,
            'mnf_game' => $mnfGame,
            'is_mnf_final' => $isMnfFinal,
            'is_week_complete' => $isWeekComplete,
            'is_recap_mode' => $isRecapMode,
            'actual_mnf_score' => $actualMnfScore,
            'actual_mnf_total' => $actualMnfTotal,
            'standings' => $standings,
            'podium' => array_slice($standings, 0, 3),
            'pot' => $pot,
            'winners_overall' => $winnersOverall,
            'winners_paid' => $winnersPaid,
            'winners_free' => $winnersFree,
            'picks_by_game' => $picksByGame,
            'survivor_picks' => $survivorPicks,
            'next_week' => $nextWeek,
            'next_week_games_count' => count($nextWeekGames),
            'next_week_first_game' => $nextWeekFirstGame,
            'next_week_matchup' => $nextWeekMatchup,
            'next_week_kickoff' => $nextWeekKickoffFormatted,
        ];
    }

    /**
     * Renders responsive HTML email dispatch.
     * Automatically chooses Recap vs Pre-MNF mode.
     *
     * @param array<string, mixed> $data
     */
    public function renderHtml(array $data): string
    {
        if (!empty($data['is_recap_mode'])) {
            return $this->renderRecapHtml($data);
        }
        return $this->renderPreMnfHtml($data);
    }

    /**
     * Renders plain-text email version.
     * Automatically chooses Recap vs Pre-MNF mode.
     *
     * @param array<string, mixed> $data
     */
    public function renderText(array $data): string
    {
        if (!empty($data['is_recap_mode'])) {
            return $this->renderRecapText($data);
        }
        return $this->renderPreMnfText($data);
    }

    /**
     * Renders post-MNF Tuesday Celebration & Weekly Recap HTML.
     *
     * @param array<string, mixed> $data
     */
    public function renderRecapHtml(array $data): string
    {
        $week = (int) $data['week'];
        $season = (int) $data['season'];
        $standings = $data['standings'];
        $podium = $data['podium'];
        $mnfGame = $data['mnf_game'];
        $mnfMatchup = $mnfGame ? "{$mnfGame['away_team']} @ {$mnfGame['home_team']}" : "NYG @ LAR";
        $actualScore = $data['actual_mnf_score'] ?? "Rams 28, Giants 6";
        $actualTotal = $data['actual_mnf_total'] ?? 34;
        $pot = $data['pot'];
        $winnersPaid = $data['winners_paid'];

        $overallWinner = $podium[0] ?? null;
        $silverWinner = $podium[1] ?? null;
        $bronzeWinner = $podium[2] ?? null;
        $cashWinner = $winnersPaid[0] ?? null;

        $overallName = $overallWinner ? htmlspecialchars($overallWinner['username']) : 'Champion';
        $overallScore = $overallWinner ? "{$overallWinner['correct_picks']}-" . ($overallWinner['total_graded'] - $overallWinner['correct_picks']) : '';
        $overallPct = ($overallWinner && $overallWinner['total_graded'] > 0)
            ? round(($overallWinner['correct_picks'] / $overallWinner['total_graded']) * 100) . '%'
            : '';

        $silverName = $silverWinner ? htmlspecialchars($silverWinner['username']) : 'Silver Medalist';
        $silverScore = $silverWinner ? "{$silverWinner['correct_picks']}-" . ($silverWinner['total_graded'] - $silverWinner['correct_picks']) : '';
        $silverTbDelta = $silverWinner['tiebreaker_delta'] ?? null;
        $silverTbText = $silverWinner
            ? (($silverTbDelta === 0) ? "🎯 Exact Bullseye ({$silverWinner['predicted_mnf']} pts)" : "TB: {$silverWinner['predicted_mnf']} pts (&Delta;{$silverTbDelta})")
            : '—';

        $bronzeName = $bronzeWinner ? htmlspecialchars($bronzeWinner['username']) : 'Bronze Medalist';
        $bronzeScore = $bronzeWinner ? "{$bronzeWinner['correct_picks']}-" . ($bronzeWinner['total_graded'] - $bronzeWinner['correct_picks']) : '';
        $bronzeTbDelta = $bronzeWinner['tiebreaker_delta'] ?? null;
        $bronzeTbText = $bronzeWinner ? "TB: {$bronzeWinner['predicted_mnf']} pts (&Delta;{$bronzeTbDelta})" : '—';

        $cashName = $cashWinner ? htmlspecialchars($cashWinner['username']) : 'Michaux Early';
        $cashScore = $cashWinner ? "{$cashWinner['correct_picks']}-" . ($cashWinner['total_graded'] - $cashWinner['correct_picks']) : '8-8';
        $cashPayout = number_format((float) ($pot['payout_per_winner'] ?? 20.0), 2);

        $nextWeek = (int) ($data['next_week'] ?? ($week + 1));
        $nextKickoff = $data['next_week_kickoff'] ?? 'Thursday Night @ 8:15 PM ET';
        $nextMatchup = $data['next_week_matchup'] ?? 'ATL @ GB';

        // Standings table rows
        $standingsRows = '';
        foreach ($standings as $st) {
            $rank = $st['rank'];
            $username = htmlspecialchars($st['username']);
            $score = "{$st['correct_picks']}-" . ($st['total_graded'] - $st['correct_picks']);
            $pct = $st['total_graded'] > 0 ? round(($st['correct_picks'] / $st['total_graded']) * 100) . '%' : '0%';
            $mnfPick = htmlspecialchars((string) ($st['mnf_pick'] ?? '—'));
            $predMnf = $st['predicted_mnf'] !== null ? "{$st['predicted_mnf']} pts" : '—';
            $delta = $st['tiebreaker_delta'];

            $rowBg = ($rank === 1) ? 'background-color: rgba(234, 179, 8, 0.12);' : (($rank === 2 || $rank === 3) ? 'background-color: #1e293b;' : '');
            $rankBadge = $rank === 1 ? '🥇 #1' : ($rank === 2 ? '🥈 #2' : ($rank === 3 ? '🥉 #3' : "#{$rank}"));

            $deltaBadge = '';
            if ($delta !== null) {
                if ($delta === 0) {
                    $deltaBadge = ' <span style="display:inline-block; background-color:#065f46; color:#34d399; font-size:10px; font-weight:800; padding:2px 6px; border-radius:4px; margin-left:4px;">🎯 BULLSEYE (&Delta;0)</span>';
                } else {
                    $deltaBadge = " <span style=\"color:#94a3b8; font-size:11px;\">(&Delta;{$delta})</span>";
                }
            }

            $tierBadge = $st['is_paid']
                ? '<span style="display:inline-block; background-color:#064e3b; color:#34d399; font-size:10px; font-weight:800; padding:2px 6px; border-radius:4px; text-transform:uppercase;">💰 Cash</span>'
                : '<span style="display:inline-block; background-color:#1e293b; color:#94a3b8; font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px;">Free</span>';

            $mnfColor = ($mnfPick === 'LAR') ? '#34d399' : (($mnfPick === 'NYG') ? '#f43f5e' : '#94a3b8');

            $standingsRows .= <<<HTML
            <tr style="border-bottom: 1px solid #334155; {$rowBg}">
                <td style="padding: 10px 12px; font-weight: 800; color: #f8fafc; font-size: 13px;">{$rankBadge}</td>
                <td style="padding: 10px 12px; font-weight: 700; color: #ffffff; font-size: 13px;">
                    {$username}
                    <div style="margin-top:2px;">{$tierBadge}</div>
                </td>
                <td style="padding: 10px 12px; text-align: center; color: #34d399; font-weight: 800; font-size: 13px;">{$score}</td>
                <td style="padding: 10px 12px; text-align: center; color: #94a3b8; font-size: 12px;">{$pct}</td>
                <td style="padding: 10px 12px; text-align: center; color: {$mnfColor}; font-weight: 800; font-size: 13px;">{$mnfPick}</td>
                <td style="padding: 10px 12px; text-align: right; color: #cbd5e1; font-size: 12px;">
                    {$predMnf}{$deltaBadge}
                </td>
            </tr>
HTML;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏆 Atkins Football Pool — Week {$week} Final Recap &amp; Champions</title>
    <style>
        body { margin: 0; padding: 0; background-color: #020617; color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .wrapper { max-width: 640px; margin: 30px auto; padding: 32px 24px; background-color: #0f172a; border: 1px solid #1e293b; border-radius: 16px; }
        .header-tag { display: inline-block; background-color: #f59e0b; color: #000000; font-weight: 900; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; padding: 4px 12px; border-radius: 9999px; margin-bottom: 16px; }
        .title { font-size: 26px; font-weight: 900; line-height: 1.25; color: #ffffff; margin-bottom: 8px; }
        .subtitle { font-size: 15px; color: #94a3b8; line-height: 1.5; margin-bottom: 24px; }
        .podium-container { display: flex; flex-wrap: wrap; gap: 12px; margin: 20px 0; }
        .podium-card { flex: 1 1 180px; background-color: #1e293b; border-radius: 12px; padding: 16px; text-align: center; border: 1px solid #334155; }
        .podium-card-gold { background: linear-gradient(180deg, #2e2611 0%, #1e293b 100%); border: 2px solid #eab308; }
        .podium-card-silver { background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%); border: 2px solid #94a3b8; }
        .podium-card-bronze { background: linear-gradient(180deg, #2a1b12 0%, #1e293b 100%); border: 2px solid #b45309; }
        .section-box { background-color: #1e293b55; border: 1px solid #334155; border-radius: 12px; padding: 20px; margin: 22px 0; }
        .section-header { font-size: 14px; font-weight: 800; color: #fbbf24; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px; }
        .card { background-color: #1e293b; border-radius: 8px; padding: 14px 16px; margin-bottom: 12px; border-left: 4px solid #38bdf8; }
        .card-danger { border-left-color: #f43f5e; }
        .card-gold { border-left-color: #f59e0b; }
        .card-green { border-left-color: #10b981; }
        .card-purple { border-left-color: #a855f7; }
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
        <span class="header-tag">🏆 OFFICIAL FINAL RESULTS &bull; WEEK {$week} RECAP</span>
        <div class="title">Week {$week} Champions Crowned: Michelle Takes the Crown, Tamara Nails the Bullseye &amp; Michaux Cashes In!</div>
        <div class="subtitle">
            What an electric conclusion to Week {$week}! Monday Night Football is in the books ({$actualScore} &bull; {$actualTotal} total points), the podium finishes came down to a razor-thin tiebreaker bullseye, and Week {$nextWeek} is officially open for picks!
        </div>

        <!-- OVERALL CHAMPION SPOTLIGHT -->
        <div style="background: linear-gradient(135deg, #1e3a8a 0%, #1e1b4b 100%); border: 2px solid #eab308; border-radius: 14px; padding: 22px; margin: 24px 0; text-align: center;">
            <div style="font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; color: #fbbf24; margin-bottom: 6px;">
                🏆 WEEK {$week} OVERALL CHAMPION
            </div>
            <div style="font-size: 28px; font-weight: 900; color: #ffffff; margin-bottom: 4px;">
                {$overallName}
            </div>
            <div style="font-size: 15px; color: #34d399; font-weight: 800; margin-bottom: 12px;">
                {$overallScore} ({$overallPct} Accuracy) &bull; Sole 1st Place Outright!
            </div>
            <div style="font-size: 13.5px; color: #e0f2fe; line-height: 1.6; text-align: left; background: rgba(0,0,0,0.25); padding: 14px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                Entering Monday Night tied with Bart Atkins at 10 wins, Michelle backed the Rams at SoFi while Bart rode the Giants. LA's 28-6 blowout vaulted Michelle into solo 1st place with 11 victories to claim the Week {$week} Crown!
            </div>
        </div>

        <!-- THE PODIUM (GOLD, SILVER, BRONZE) -->
        <div class="section-box">
            <div class="section-header">🥇🥈🥉 The Week {$week} Podium Showcase</div>
            <div style="font-size: 13px; color: #94a3b8; margin-bottom: 14px;">
                A historic 4-way deadlock at 10 wins was settled down to the decimal point by the Monday Night total points tiebreaker ({$actualTotal} pts):
            </div>
            <div class="podium-container">
                <!-- GOLD -->
                <div class="podium-card podium-card-gold">
                    <div style="font-size: 24px; margin-bottom: 4px;">🥇</div>
                    <div style="font-size: 11px; font-weight: 900; text-transform: uppercase; color: #eab308; letter-spacing: 0.05em;">Gold &bull; 1st Place</div>
                    <div style="font-size: 16px; font-weight: 800; color: #ffffff; margin: 4px 0 2px;">{$overallName}</div>
                    <div style="font-size: 13px; font-weight: 700; color: #34d399;">{$overallScore}</div>
                    <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">Outright Champion</div>
                </div>

                <!-- SILVER -->
                <div class="podium-card podium-card-silver">
                    <div style="font-size: 24px; margin-bottom: 4px;">🥈</div>
                    <div style="font-size: 11px; font-weight: 900; text-transform: uppercase; color: #cbd5e1; letter-spacing: 0.05em;">Silver &bull; 2nd Place</div>
                    <div style="font-size: 16px; font-weight: 800; color: #ffffff; margin: 4px 0 2px;">{$silverName}</div>
                    <div style="font-size: 13px; font-weight: 700; color: #34d399;">{$silverScore}</div>
                    <div style="font-size: 11px; color: #34d399; font-weight: 700; margin-top: 4px;">{$silverTbText}</div>
                </div>

                <!-- BRONZE -->
                <div class="podium-card podium-card-bronze">
                    <div style="font-size: 24px; margin-bottom: 4px;">🥉</div>
                    <div style="font-size: 11px; font-weight: 900; text-transform: uppercase; color: #f59e0b; letter-spacing: 0.05em;">Bronze &bull; 3rd Place</div>
                    <div style="font-size: 16px; font-weight: 800; color: #ffffff; margin: 4px 0 2px;">{$bronzeName}</div>
                    <div style="font-size: 13px; font-weight: 700; color: #34d399;">{$bronzeScore}</div>
                    <div style="font-size: 11px; color: #cbd5e1; margin-top: 4px;">{$bronzeTbText}</div>
                </div>
            </div>

            <div style="font-size: 12.5px; color: #94a3b8; line-height: 1.5; margin-top: 10px;">
                <strong>Tiebreaker Note:</strong> Four participants finished at 10 wins (Tamara, Logan, Rob Pappas, and Bart Atkins). Tamara Atkins predicted <em>exactly 34 combined points</em>—an unbelievable single-digit bullseye! Logan Atkins was right behind at 31 points (&Delta;3) to secure the bronze medal.
            </div>
        </div>

        <!-- CASH POOL WINNER SPOTLIGHT -->
        <div class="section-box" style="border-color: #059669; background: #064e3b22;">
            <div class="section-header" style="color: #34d399;">💰 Week {$week} Cash Pool Champion</div>
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                <div>
                    <div style="font-size: 20px; font-weight: 900; color: #ffffff;">{$cashName}</div>
                    <div style="font-size: 13px; color: #34d399; font-weight: 700; margin-top: 2px;">
                        {$cashScore} Record &bull; Week {$week} Cash Champion
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 24px; font-weight: 900; color: #fbbf24; font-family: monospace;">\${$cashPayout}</div>
                    <div style="font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: 700;">100% Pot Payout</div>
                </div>
            </div>
            <div style="font-size: 13px; color: #cbd5e1; margin-top: 12px; line-height: 1.5;">
                Congratulations to Michaux Early for taking down the verified Week {$week} cash prize pot!
            </div>
        </div>

        <!-- COMPLETE STANDINGS TABLE -->
        <div class="section-box">
            <div class="section-header">📊 Official Week {$week} Final Standings (All 16 Games)</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Player</th>
                            <th style="text-align: center;">Record</th>
                            <th style="text-align: center;">Pct</th>
                            <th style="text-align: center;">MNF</th>
                            <th style="text-align: right;">Tiebreak</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$standingsRows}
                    </tbody>
                </table>
            </div>
        </div>

        <!-- WEEKEND REWIND -->
        <div class="section-box">
            <div class="section-header">🔥 Weekend Rewind: The Games That Shook the Pool</div>

            <div class="card card-danger">
                <div class="card-title">🚨 The 100% Pool Massacre: Browns 23, Buccaneers 19</div>
                <div class="card-body">
                    All 11 pool participants confidently selected Tampa Bay at home. Exactly <strong>zero</strong> picked Cleveland. Baker Mayfield and the Bucs stalled, dealing the league a collective goose egg!
                </div>
            </div>

            <div class="card card-gold">
                <div class="card-title">🧙‍♂️ The Lone Prophet: Saints 24, Ravens 17</div>
                <div class="card-body">
                    10 out of 11 players rode Lamar Jackson and Baltimore. Only <strong>Bart Atkins</strong> had the vision to take New Orleans on the road—a clutch solo swing point!
                </div>
            </div>

            <div class="card card-gold">
                <div class="card-title">🎰 The Vegas Heist: Raiders 26, Chargers 14</div>
                <div class="card-body">
                    9 players picked the Chargers. Only <strong>Michelle Weaver</strong> and <strong>Charlie Van Dine</strong> backed the Silver &amp; Black, netting a huge 2-point boost!
                </div>
            </div>

            <div class="card card-green">
                <div class="card-title">🐾 The Carolina Catastrophe: Panthers 34, Falcons 3</div>
                <div class="card-body">
                    Only <strong>Bart</strong>, <strong>Logan</strong>, and <strong>Rob</strong> believed in Carolina. The Panthers demolished Atlanta by 31 points, devastating 8 Pick'em cards and knocking Commissioner Wally out of Survivor!
                </div>
            </div>
        </div>

        <!-- FORWARD TO WEEK 3 & PICK RULES -->
        <div class="section-box" style="border-color: #0284c7; background: #082f4922;">
            <div class="section-header" style="color: #38bdf8;">⚡ Week {$nextWeek} Is Officially Open — Make Your Picks!</div>
            <div style="font-size: 14px; color: #f8fafc; font-weight: 700; margin-bottom: 8px;">
                Kickoff: {$nextMatchup} ({$nextKickoff})
            </div>
            <div style="font-size: 13.5px; color: #cbd5e1; line-height: 1.6; margin-bottom: 14px;">
                The Week {$nextWeek} board is live! Whether you prefer our new immersive <strong>Pick Wizard</strong> or our lightning-fast <strong>Standard Grid</strong>, getting your picks in is 100% frictionless:
            </div>
            <ul style="margin: 0 0 16px; padding-left: 20px; font-size: 13px; color: #cbd5e1; line-height: 1.7;">
                <li><strong>⚡ Auto-Save on Tap:</strong> Every pick you tap is saved instantly behind the scenes—no submit button needed!</li>
                <li><strong>✏️ Change Anytime Before Kickoff:</strong> You can edit any pick as often as you like right up until that specific game kicks off.</li>
                <li><strong>⏱️ Cutoff Rules:</strong> Thursday Night Football locks strictly at kickoff ({$nextKickoff}). The Sunday slate locks at Sunday 1:00 PM ET.</li>
            </ul>
            <div class="btn-row" style="margin-top: 10px;">
                <a href="https://football.wallyatkins.com/pickem/wizard?week={$nextWeek}" class="action-btn">Launch Pick Wizard &rarr;</a>
                <a href="https://football.wallyatkins.com/pickem?week={$nextWeek}" class="secondary-btn">Standard Grid</a>
            </div>
        </div>

        <!-- SURVIVOR REPORT & LATE-JOIN RULES -->
        <div class="section-box" style="border-color: #059669; background: #064e3b22;">
            <div class="section-header" style="color: #34d399;">🛡️ Survivor Pool: Casualty Report &amp; Late-Join Rules</div>
            <div style="font-size: 13.5px; color: #cbd5e1; line-height: 1.6; margin-bottom: 12px;">
                &bull; <strong>Michaux Early:</strong> Locked in <strong>Cincinnati</strong>, and the Bengals handled Houston 20-6. Michaux is ALIVE and moving on! 🟢<br>
                &bull; <strong>Wally Atkins:</strong> Trusted Atlanta at home. A 34-3 blowout loss knocks the Commissioner out! 💀🪦
            </div>

            <div style="background: rgba(0,0,0,0.3); border: 1px solid #05966944; border-radius: 8px; padding: 14px; margin-top: 10px;">
                <div style="font-size: 12px; font-weight: 800; color: #fbbf24; text-transform: uppercase; margin-bottom: 6px;">
                    ⚖️ Late-Join Survivor Fairness Proposals
                </div>
                <div style="font-size: 12.5px; color: #cbd5e1; line-height: 1.6;">
                    Want to jump into the Survivor pool mid-season? To keep things 100% fair to Week 1 starters (who risked elimination and already burned elite teams), here are the options available for late entrants:
                    <ol style="margin: 6px 0 0; padding-left: 18px;">
                        <li><strong>The "Used Teams" Handicap:</strong> Late joiners enter but forfeit 1 consensus top team per missed week (e.g. they cannot pick Cincinnati or Buffalo), facing equal team scarcity.</li>
                        <li><strong>Flight B Second-Chance Pool:</strong> A secondary Survivor pool starting in Week 4 with a fresh pot for all newcomers and eliminated players.</li>
                        <li><strong>Sudden-Death Buy-In:</strong> Late entrants start with zero safety strikes (any loss eliminates you instantly).</li>
                    </ol>
                    <em>Note: An optional \$20 Survivor Season Cash Pool is also running alongside the free bracket!</em>
                </div>
            </div>
        </div>

        <!-- CASH POOL OPT-IN (SUBTLE & FRIENDLY) -->
        <div class="section-box" style="border-color: #334155; background: #1e293b33;">
            <div class="section-header" style="color: #94a3b8; font-size: 12px;">💡 How the Weekly Cash Pool Works (Optional)</div>
            <div style="font-size: 13px; color: #94a3b8; line-height: 1.6;">
                Everyone plays the free pick'em pool automatically for weekly glory and season standings. If you want a little extra skin in the game, our <strong>\$10/week Cash Pool</strong> is 100% optional:
                <br><br>
                &bull; <strong>100% Payout:</strong> Every dollar collected goes directly to that week's highest-scoring cash participant.<br>
                &bull; <strong>How to Join:</strong> Send \$10 before Thursday kickoff via <strong>Venmo (@WallyAtkins)</strong> or <strong>Cash App (\$WallyAtkins)</strong> with the memo <em>"Week {$nextWeek} Cash Pool"</em>. Commissioner Wally verifies your entry in the app.
            </div>
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
     * Renders post-MNF Tuesday Celebration & Weekly Recap Plain Text.
     *
     * @param array<string, mixed> $data
     */
    public function renderRecapText(array $data): string
    {
        $week = (int) $data['week'];
        $nextWeek = (int) ($data['next_week'] ?? ($week + 1));
        $standings = $data['standings'];
        $podium = $data['podium'];
        $overallWinner = $podium[0] ?? null;
        $silverWinner = $podium[1] ?? null;
        $bronzeWinner = $podium[2] ?? null;
        $cashWinner = $data['winners_paid'][0] ?? null;

        $overallName = $overallWinner ? $overallWinner['username'] : 'Champion';
        $overallScore = $overallWinner ? "{$overallWinner['correct_picks']}-" . ($overallWinner['total_graded'] - $overallWinner['correct_picks']) : '';
        $silverName = $silverWinner ? $silverWinner['username'] : 'Silver';
        $silverScore = $silverWinner ? "{$silverWinner['correct_picks']}-" . ($silverWinner['total_graded'] - $silverWinner['correct_picks']) : '';
        $bronzeName = $bronzeWinner ? $bronzeWinner['username'] : 'Bronze';
        $bronzeScore = $bronzeWinner ? "{$bronzeWinner['correct_picks']}-" . ($bronzeWinner['total_graded'] - $bronzeWinner['correct_picks']) : '';

        $cashName = $cashWinner ? $cashWinner['username'] : 'Michaux Early';
        $cashScore = $cashWinner ? "{$cashWinner['correct_picks']}-" . ($cashWinner['total_graded'] - $cashWinner['correct_picks']) : '8-8';
        $cashPayout = number_format((float) ($data['pot']['payout_per_winner'] ?? 20.0), 2);

        $actualScore = $data['actual_mnf_score'] ?? "Rams 28, Giants 6";
        $actualTotal = $data['actual_mnf_total'] ?? 34;

        $lines = [];
        $lines[] = "============================================================";
        $lines[] = "🏆 ATKINS FOOTBALL POOL — WEEK {$week} FINAL RECAP & CHAMPIONS";
        $lines[] = "============================================================";
        $lines[] = "";
        $lines[] = "Monday Night Football is final: {$actualScore} (Total: {$actualTotal} pts).";
        $lines[] = "Our Week {$week} champions are crowned, podium drama is settled, and Week {$nextWeek} is open!";
        $lines[] = "";
        $lines[] = "🏆 OVERALL WEEK {$week} CHAMPION: {$overallName} ({$overallScore})";
        $lines[] = "Michelle breaks the tie with the Rams victory to take solo 1st place outright!";
        $lines[] = "";
        $lines[] = "🥇🥈🥉 THE WEEK {$week} PODIUM:";
        $silverTb = $silverWinner
            ? (($silverWinner['tiebreaker_delta'] === 0) ? "🎯 EXACT 34 PTS BULLSEYE!" : "TB Delta: {$silverWinner['tiebreaker_delta']}")
            : "—";
        $bronzeTb = $bronzeWinner ? "TB Delta: {$bronzeWinner['tiebreaker_delta']}" : "—";
        $lines[] = "  🥈 2nd (Silver): {$silverName} ({$silverScore}) - {$silverTb}";
        $lines[] = "  🥉 3rd (Bronze): {$bronzeName} ({$bronzeScore}) - {$bronzeTb}";
        $lines[] = "  (Top 5 Chasers: Rob Pappas 10-5, Bart Atkins 10-6)";
        $lines[] = "";
        $lines[] = "💰 CASH POOL WINNER: {$cashName} ({$cashScore})";
        $lines[] = "Takes home the verified Week {$week} cash prize pot: \${$cashPayout}!";
        $lines[] = "";
        $lines[] = "📊 OFFICIAL WEEK {$week} FINAL STANDINGS (All 16 Games):";
        $lines[] = "------------------------------------------------------------";
        $lines[] = sprintf("%-6s %-16s %-8s %-8s %-14s", "Rank", "Player", "Score", "Tier", "Tiebreak");
        $lines[] = "------------------------------------------------------------";
        foreach ($standings as $st) {
            $score = "{$st['correct_picks']}-" . ($st['total_graded'] - $st['correct_picks']);
            $tier = $st['is_paid'] ? 'CASH' : 'FREE';
            $tb = "{$st['predicted_mnf']} pts";
            if ($st['tiebreaker_delta'] === 0) {
                $tb .= " 🎯(BULLSEYE)";
            } elseif ($st['tiebreaker_delta'] !== null) {
                $tb .= " (&Delta;{$st['tiebreaker_delta']})";
            }
            $lines[] = sprintf("%-6s %-16s %-8s %-8s %-14s", "#{$st['rank']}", $st['username'], $score, $tier, $tb);
        }
        $lines[] = "------------------------------------------------------------";
        $lines[] = "";
        $lines[] = "🔥 WEEKEND REWIND — THE GAMES THAT SHOOK THE POOL:";
        $lines[] = "1. BROWNS 23, BUCCANEERS 19 — 100% pool massacre! 0 of 11 picked Cleveland.";
        $lines[] = "2. SAINTS 24, RAVENS 17 — Bart Atkins was the SOLE player in the league to pick NO!";
        $lines[] = "3. RAIDERS 26, CHARGERS 14 — Only Michelle Weaver and Charlie Van Dine picked LV!";
        $lines[] = "4. PANTHERS 34, FALCONS 3 — Bart, Logan, and Rob picked Carolina. Knocked Wally from Survivor!";
        $lines[] = "";
        $lines[] = "⚡ WEEK {$nextWeek} IS OFFICIALLY OPEN — MAKE YOUR PICKS!";
        $lines[] = "Kickoff: " . ($data['next_week_matchup'] ?? 'ATL @ GB') . " (" . ($data['next_week_kickoff'] ?? 'Thursday Night @ 8:15 PM ET') . ")";
        $lines[] = "- Auto-Save on Tap: Every pick saves instantly behind the scenes. No submit button required.";
        $lines[] = "- Edit anytime before kickoff: You can modify your picks anytime prior to game time.";
        $lines[] = "- Cutoffs: Thursday game locks Thursday kickoff; Sunday games lock Sunday 1:00 PM ET.";
        $lines[] = "Interactive Wizard: https://football.wallyatkins.com/pickem/wizard?week={$nextWeek}";
        $lines[] = "Standard Picks Grid: https://football.wallyatkins.com/pickem?week={$nextWeek}";
        $lines[] = "";
        $lines[] = "🛡️ SURVIVOR POOL REPORT & LATE-JOIN RULES:";
        $lines[] = "- Michaux Early: Picked CIN (Won 20-6) -> ALIVE & MOVING ON! 🟢";
        $lines[] = "- Wally Atkins: Picked ATL (Lost 3-34) -> ELIMINATED! 💀🪦";
        $lines[] = "Late-Join Fairness Options:";
        $lines[] = "1. Used Teams Handicap: Late joiners forfeit 1 consensus top team per missed week (e.g. CIN/BUF).";
        $lines[] = "2. Flight B Second-Chance Pool: Secondary tournament launching Week 4.";
        $lines[] = "3. Sudden Death: Late entrants enter with zero safety strikes.";
        $lines[] = "(Subtle note: Optional \$20 Survivor Season Cash Pool is also available!)";
        $lines[] = "";
        $lines[] = "💡 HOW THE WEEKLY CASH POOL WORKS (OPTIONAL):";
        $lines[] = "Friendly \$10/week buy-in. 100% payout to that week's highest-scoring cash player.";
        $lines[] = "To join for Week {$nextWeek}: Send \$10 before Thursday kickoff via Venmo (@WallyAtkins) or Cash App (\$WallyAtkins) with memo 'Week {$nextWeek} Cash Pool'.";
        $lines[] = "============================================================";
        $lines[] = "Sent by Commissioner Wally Atkins • football@wallyatkins.com";

        return implode("\n", $lines);
    }

    /**
     * Renders pre-MNF Monday Huddle HTML.
     *
     * @param array<string, mixed> $data
     */
    public function renderPreMnfHtml(array $data): string
    {
        $week = (int) $data['week'];
        $standings = $data['standings'];
        $mnfGame = $data['mnf_game'];

        $standingsRows = '';
        foreach ($standings as $st) {
            $rank = $st['rank'];
            $username = htmlspecialchars($st['username']);
            $score = "{$st['correct_picks']}-" . ($st['total_graded'] - $st['correct_picks']);
            $pct = $st['total_graded'] > 0 ? round(($st['correct_picks'] / $st['total_graded']) * 100) . '%' : '0%';
            $mnfPick = htmlspecialchars((string) ($st['mnf_pick'] ?? '—'));
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
            Happy Monday, football family! 15 games are officially in the books, only 1 remains, and Week {$week} has unleashed absolute, unadulterated NFL chaos across our leaderboard.
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
            <div class="section-header">📊 Week {$week} Pick'em Leaderboard (Through 15 Games)</div>
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
                Once tonight's game goes final and the official Week {$week} payouts and awards are crowned, the <strong>Week 3 slate will open Tuesday morning</strong>. Remember to check your CBS Fantasy matchups and lock in your Thursday night pick early!
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
     * Renders pre-MNF Monday Huddle Plain Text.
     *
     * @param array<string, mixed> $data
     */
    public function renderPreMnfText(array $data): string
    {
        $week = (int) $data['week'];
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
        $lines[] = "📊 WEEK {$week} PICK'EM STANDINGS (Through 15 Games):";
        $lines[] = "------------------------------------------------------------";
        $lines[] = sprintf("%-5s %-16s %-8s %-10s %-8s", "Rank", "Player", "Score", "MNF Pick", "Tiebreak");
        $lines[] = "------------------------------------------------------------";
        foreach ($standings as $st) {
            $score = "{$st['correct_picks']}-" . ($st['total_graded'] - $st['correct_picks']);
            $lines[] = sprintf("%-5s %-16s %-8s %-10s %-8s", "#{$st['rank']}", $st['username'], $score, (string) ($st['mnf_pick'] ?? '—'), "{$st['predicted_mnf']} pts");
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
     * Dispatches update email.
     *
     * @return array{sent: int, failed: int, mode: string, recipients: array<string>}
     */
    public function dispatchUpdate(
        int $season = 2026,
        int $week = 2,
        ?string $previewTo = null,
        bool $dryRun = false,
        ?bool $forceRecap = null
    ): array {
        $data = $this->getMondayData($season, $week);
        $isRecap = ($forceRecap !== null) ? $forceRecap : !empty($data['is_recap_mode']);
        $data['is_recap_mode'] = $isRecap;

        if ($isRecap) {
            $subject = ($previewTo !== null)
                ? "🏈 [PREVIEW] Atkins Football Week {$week} Recap: Champion Crowned, Podium Drama & Week 3 Launch!"
                : "🏆 Atkins Football Week {$week} Recap: Champion Crowned, Podium Drama & Week 3 Launch!";
        } else {
            $subject = ($previewTo !== null)
                ? "🏈 [PREVIEW] Atkins Football Week {$week} Monday Huddle: Standings & SoFi Showdown!"
                : "🏈 Atkins Football Week {$week} Monday Huddle: Standings & Tonight's SoFi Showdown!";
        }

        $html = $this->renderHtml($data);
        $text = $this->renderText($data);

        $recipients = [];
        if ($previewTo !== null && trim($previewTo) !== '') {
            $recipients[] = [
                'name' => 'Wally Atkins (Preview)',
                'email' => trim($previewTo),
            ];
        } else {
            // Gather all registered football app members
            $users = $this->db->query(
                'SELECT DISTINCT username, email
                 FROM users
                 WHERE email IS NOT NULL AND email != ""
                 ORDER BY username ASC'
            );
            $seen = [];
            foreach ($users as $u) {
                $email = strtolower(trim((string) $u['email']));
                if (filter_var($email, FILTER_VALIDATE_EMAIL) && !isset($seen[$email])) {
                    $seen[$email] = true;
                    $recipients[] = [
                        'name' => (string) $u['username'],
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

            $boundary = "==Recap_Update_" . md5((string) microtime()) . "==";
            $headers = [
                "From: {$this->fromName} <{$this->fromEmail}>",
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
