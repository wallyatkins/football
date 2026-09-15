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

    public function getWrapupTrackingFilePath(int $season, int $week): string
    {
        $dir = dirname(__DIR__, 2) . '/data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return "{$dir}/.reported_wrapup_{$season}_{$week}.json";
    }

    public function markWrapupAsReported(int $season, int $week): void
    {
        file_put_contents($this->getWrapupTrackingFilePath($season, $week), json_encode(['timestamp' => time(), 'date' => date('c')]));
    }

    public function isWrapupReported(int $season, int $week): bool
    {
        return file_exists($this->getWrapupTrackingFilePath($season, $week));
    }

    public function hasNewResults(int $season, int $week): bool
    {
        $finalGames = $this->db->query(
            "SELECT id FROM games WHERE season_year = :s AND week_number = :w AND status = 'final'",
            ['s' => $season, 'w' => $week]
        );
        $reportedIds = $this->getReportedGameIds($season, $week);
        foreach ($finalGames as $fg) {
            if (!in_array((int) $fg['id'], $reportedIds, true)) {
                return true;
            }
        }

        // Check if prior week concluded and needs wrapup
        $priorWeek = ($week > 1) ? ($week - 1) : 0;
        if ($priorWeek >= 1 && !$this->isWrapupReported($season, $priorWeek)) {
            $priorTotal = (int) $this->db->queryValue(
                "SELECT count(*) FROM games WHERE season_year = :s AND week_number = :w",
                ['s' => $season, 'w' => $priorWeek]
            );
            $priorFinal = (int) $this->db->queryValue(
                "SELECT count(*) FROM games WHERE season_year = :s AND week_number = :w AND status = 'final'",
                ['s' => $season, 'w' => $priorWeek]
            );
            if ($priorTotal > 0 && $priorFinal === $priorTotal) {
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

        // 2. Pick'em Standings & Pot
        $standings = $this->scoring->getWeeklyStandings($season, $week);
        $pot = $this->scoring->calculateWeeklyPot($season, $week);

        // 2a. Check if there is a completed prior week (e.g. Week 1 just completed, active week is Week 2)
        $completedWeek = null;
        $completedWeekPot = null;
        $completedWeekWinners = [];
        $completedWeekStandings = [];
        $priorWeek = ($week > 1) ? ($week - 1) : 0;
        if ($priorWeek >= 1) {
            $priorTotal = (int) $this->db->queryValue(
                "SELECT count(*) FROM games WHERE season_year = :s AND week_number = :w",
                ['s' => $season, 'w' => $priorWeek]
            );
            $priorFinal = (int) $this->db->queryValue(
                "SELECT count(*) FROM games WHERE season_year = :s AND week_number = :w AND status = 'final'",
                ['s' => $season, 'w' => $priorWeek]
            );
            if ($priorTotal > 0 && $priorFinal === $priorTotal) {
                $completedWeek = $priorWeek;
                $completedWeekPot = $this->scoring->calculateWeeklyPot($season, $priorWeek);
                $completedWeekWinners = $completedWeekPot['winners'] ?? [];
                $completedWeekStandings = $this->scoring->getWeeklyStandings($season, $priorWeek);
            }
        } elseif (!empty($finalGames) && count($finalGames) === count($games)) {
            $completedWeek = $week;
            $completedWeekPot = $pot;
            $completedWeekWinners = $pot['winners'] ?? [];
            $completedWeekStandings = $standings;
        }

        // Earliest kickoff for upcoming week
        $firstKickoff = null;
        foreach ($games as $g) {
            if ($g['status'] !== 'final') {
                $kt = strtotime($g['kickoff_time']);
                if ($firstKickoff === null || $kt < $firstKickoff) {
                    $firstKickoff = $kt;
                }
            }
        }
        $firstKickoffFormatted = $firstKickoff 
            ? (new DateTimeImmutable("@{$firstKickoff}"))->setTimezone(new DateTimeZone('America/New_York'))->format('D, M j @ g:i A T')
            : 'Thursday Kickoff';

        // 2b. Survivor Standings, Pot, and User Picks
        $survivorStandings = $this->scoring->getSurvivorStandings($season, null, $week);
        $survivorPot = $this->scoring->calculateSurvivorPot($season);

        $rawSurvivorPicks = $this->db->query(
            'SELECT user_id, week_number, selected_team, is_eliminated, payment_status
             FROM survivor_picks
             WHERE season_year = :s AND week_number = :w',
            ['s' => $season, 'w' => $week]
        );
        $survivorPicksByUserId = [];
        foreach ($rawSurvivorPicks as $sp) {
            $survivorPicksByUserId[(int) $sp['user_id']] = $sp;
        }

        $survivorByUser = [];
        $aliveCount = 0;
        $eliminatedCount = 0;
        foreach ($survivorStandings as $st) {
            $uid = (int) $st['user_id'];
            if ($st['is_alive']) {
                $aliveCount++;
            } elseif ($st['is_eliminated']) {
                $eliminatedCount++;
            }

            $currentPick = $survivorPicksByUserId[$uid]['selected_team'] ?? null;
            $pickStatus = 'none';
            $pickGame = null;
            $pickResultDesc = '';

            if ($currentPick !== null) {
                foreach ($games as $g) {
                    if ($g['home_team'] === $currentPick || $g['away_team'] === $currentPick) {
                        $pickGame = $g;
                        break;
                    }
                }

                if ($pickGame !== null) {
                    $opponent = ($pickGame['home_team'] === $currentPick) ? $pickGame['away_team'] : $pickGame['home_team'];
                    $isHome = ($pickGame['home_team'] === $currentPick);
                    $vsPrefix = $isHome ? "vs {$opponent}" : "@ {$opponent}";

                    if ($pickGame['status'] === 'final') {
                        $winner = $pickGame['winning_team'] ?? null;
                        if ($winner === $currentPick) {
                            $pickStatus = 'win';
                            $pickResultDesc = "✓ WIN (Survives!) • {$currentPick} {$vsPrefix} ({$pickGame['away_score']}-{$pickGame['home_score']})";
                        } else {
                            $pickStatus = 'loss';
                            $pickResultDesc = "✗ LOSS (Eliminated) • {$currentPick} {$vsPrefix} ({$pickGame['away_score']}-{$pickGame['home_score']})";
                        }
                    } elseif ($pickGame['status'] === 'in_progress') {
                        $pickStatus = 'in_progress';
                        $pickResultDesc = "⚡ In Progress • {$currentPick} {$vsPrefix} ({$pickGame['away_score']}-{$pickGame['home_score']})";
                    } else {
                        $pickStatus = 'scheduled';
                        $kickoffEt = (new DateTimeImmutable($pickGame['kickoff_time']))->setTimezone(new DateTimeZone('America/New_York'))->format('D g:i A T');
                        $pickResultDesc = "Upcoming • {$currentPick} {$vsPrefix} ({$kickoffEt})";
                    }
                } else {
                    $pickStatus = 'picked';
                    $pickResultDesc = "Selected: {$currentPick}";
                }
            } elseif ($st['is_alive']) {
                $pickStatus = 'missing';
                $pickResultDesc = "⚠️ No Survivor pick locked for Week {$week}!";
            }

            $survivorByUser[$uid] = [
                'status' => $st['status'],
                'is_alive' => $st['is_alive'],
                'is_eliminated' => $st['is_eliminated'],
                'elimination_week' => $st['elimination_week'],
                'is_paid' => $st['is_paid'],
                'tier' => $st['tier'],
                'teams_used' => $st['teams_used'] ?? [],
                'current_pick' => $currentPick,
                'pick_status' => $pickStatus,
                'pick_game' => $pickGame,
                'pick_result_desc' => $pickResultDesc,
            ];
        }

        $survivorSummary = [
            'total_alive' => $aliveCount,
            'total_eliminated' => $eliminatedCount,
            'cash_pot' => (float) ($survivorPot['total_pot'] ?? 0.0),
            'cash_alive' => count($survivorPot['alive_cash_contenders'] ?? []),
            'cash_eliminated' => count($survivorPot['eliminated_cash_contenders'] ?? []),
        ];

        // 3. Entrants & their picks (Pick'em and Survivor participants)
        $entries = $this->db->query(
            'SELECT u.id as user_id, u.username, u.email,
                    e.id as entry_id, e.payment_status, e.mnf_total_points_prediction, e.is_locked
             FROM users u
             LEFT JOIN pickem_entries e ON e.user_id = u.id AND e.season_year = :s AND e.week_number = :w
             LEFT JOIN survivor_entries se ON se.user_id = u.id AND se.season_year = :s
             WHERE (e.id IS NOT NULL 
                    OR se.id IS NOT NULL 
                    OR u.id IN (SELECT user_id FROM pickem_entries WHERE season_year = :s)
                    OR u.id IN (SELECT user_id FROM survivor_picks WHERE season_year = :s))
               AND u.email IS NOT NULL AND u.email != ""
             ORDER BY u.username ASC',
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
            'completed_week' => $completedWeek,
            'completed_week_pot' => $completedWeekPot,
            'completed_week_winners' => $completedWeekWinners,
            'completed_week_standings' => $completedWeekStandings,
            'first_kickoff_formatted' => $firstKickoffFormatted,
            'survivor_standings' => $survivorStandings,
            'survivor_pot' => $survivorPot,
            'survivor_by_user' => $survivorByUser,
            'survivor_summary' => $survivorSummary,
            'entries' => $entries,
            'picks_by_entry' => $picksByEntryId,
        ];
    }

    public function renderHtml(array $userEntry, array $digestData): string
    {
        $username = htmlspecialchars($userEntry['username'] ?? 'Player');
        $week = (int) $digestData['week'];
        $season = (int) $digestData['season'];
        $entryId = isset($userEntry['entry_id']) ? (int) $userEntry['entry_id'] : 0;
        $userId = isset($userEntry['user_id']) ? (int) $userEntry['user_id'] : 0;

        if ($userId === 0 && $entryId > 0) {
            foreach ($digestData['entries'] as $en) {
                if ((int) ($en['entry_id'] ?? 0) === $entryId) {
                    $userId = (int) ($en['user_id'] ?? 0);
                    break;
                }
            }
            if ($userId === 0) {
                $userId = (int) ($this->db->queryValue('SELECT user_id FROM pickem_entries WHERE id = :id', ['id' => $entryId]) ?: 0);
            }
        }

        $userPicks = ($entryId > 0) ? ($digestData['picks_by_entry'][$entryId] ?? []) : [];

        // 1. Pick'em user standing
        $userStanding = null;
        if ($entryId > 0) {
            foreach ($digestData['standings'] as $st) {
                if ((int) $st['entry_id'] === $entryId) {
                    $userStanding = $st;
                    break;
                }
            }
        }

        $userCorrect = $userStanding['correct_picks'] ?? 0;
        $userGraded = $userStanding['total_graded'] ?? 0;
        $userRank = $userStanding['rank'] ?? '-';
        $userPending = $userStanding['pending_picks'] ?? 0;

        // 2. Survivor user data & summary
        $survivorData = ($userId > 0) ? ($digestData['survivor_by_user'][$userId] ?? null) : null;
        $survivorSummary = $digestData['survivor_summary'] ?? [
            'total_alive' => 0,
            'total_eliminated' => 0,
            'cash_pot' => 0.0,
        ];

        $isSurvivorAlive = ($survivorData['is_alive'] ?? false);
        $isSurvivorEliminated = ($survivorData['is_eliminated'] ?? false);
        $survElimWeek = $survivorData['elimination_week'] ?? null;
        $survPick = $survivorData['current_pick'] ?? null;
        $survPickDesc = $survivorData['pick_result_desc'] ?? '';
        $survTeamsUsed = $survivorData['teams_used'] ?? [];
        $survUsedStr = !empty($survTeamsUsed) ? implode(', ', $survTeamsUsed) : 'None';

        if ($isSurvivorAlive) {
            $survivorBadge = '<span style="display: inline-block; padding: 4px 10px; border-radius: 6px; background-color: #064e3b; color: #34d399; font-weight: bold; font-size: 11px; border: 1px solid #059669;">🟢 ALIVE &bull; In the Hunt</span>';
        } elseif ($isSurvivorEliminated) {
            $elimLabel = $survElimWeek ? "Week {$survElimWeek}" : "Eliminated";
            $survivorBadge = "<span style=\"display: inline-block; padding: 4px 10px; border-radius: 6px; background-color: #881337; color: #f43f5e; font-weight: bold; font-size: 11px; border: 1px solid #be123c;\">💀 ELIMINATED ({$elimLabel})</span>";
        } else {
            $survivorBadge = '<span style="display: inline-block; padding: 4px 10px; border-radius: 6px; background-color: #334155; color: #94a3b8; font-weight: bold; font-size: 11px; border: 1px solid #475569;">⚪ Not Entered</span>';
        }

        if ($survPick !== null) {
            $survivorPickHtml = "<strong style=\"color: #f59e0b; font-size: 14px;\">{$survPick}</strong> <span style=\"color: #cbd5e1; font-size: 12px; margin-left: 6px;\">({$survPickDesc})</span>";
        } elseif ($isSurvivorAlive) {
            $survivorPickHtml = '<span style="color: #f43f5e; font-weight: bold;">⚠️ No pick locked yet!</span> <a href="https://football.wallyatkins.com/survivor?week=' . $week . '&mtm_campaign=week_' . $week . '&mtm_source=morning_digest&mtm_medium=email" style="color: #38bdf8; text-decoration: underline; margin-left: 6px; font-size: 12px;">Lock Pick &rarr;</a>';
        } else {
            $survivorPickHtml = '<span style="color: #64748b;">N/A</span>';
        }

        $potFormatted = '$' . number_format((float) ($survivorSummary['cash_pot'] ?? 0), 0);
        $potHtml = ($survivorSummary['cash_pot'] ?? 0) > 0 ? " &bull; <strong style=\"color: #34d399;\">{$potFormatted} Pot</strong>" : "";

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

        // Weekly Champion celebration banner
        $completedWeekBannerHtml = '';
        if (!empty($digestData['completed_week']) && !empty($digestData['completed_week_winners'])) {
            $cWeek = (int) $digestData['completed_week'];
            $cWinners = $digestData['completed_week_winners'];
            $cNames = array_map(fn($w) => htmlspecialchars($w['username'] ?? 'Champion'), $cWinners);
            $cNamesStr = implode(' &amp; ', $cNames);
            $cScore = $cWinners[0]['correct_picks'] ?? 0;
            $cPot = $digestData['completed_week_pot'] ?? null;
            $cPotPerWinner = ($cPot && !empty($cPot['payout_per_winner']) && $cPot['payout_per_winner'] > 0)
                ? " &bull; <span style=\"color: #34d399; font-weight: 900;\">Won $" . number_format((float)$cPot['payout_per_winner'], 2) . "</span>"
                : '';

            $completedWeekBannerHtml = <<<HTML
            <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border: 2px solid #f59e0b; border-radius: 14px; padding: 20px; margin-bottom: 24px; text-align: center; box-shadow: 0 4px 20px rgba(245, 158, 11, 0.25);">
                <div style="font-size: 28px; line-height: 1; margin-bottom: 6px;">👑 🏆 👑</div>
                <div style="font-size: 11px; font-weight: 900; color: #f59e0b; text-transform: uppercase; letter-spacing: 1.5px;">
                    Official Week {$cWeek} Champion
                </div>
                <div style="font-size: 22px; font-weight: 900; color: #ffffff; margin-top: 4px; letter-spacing: -0.5px;">
                    Congratulations, {$cNamesStr}!
                </div>
                <div style="font-size: 13px; color: #cbd5e1; margin-top: 6px;">
                    Finished #1 with <strong style="color: #34d399;">{$cScore} correct picks</strong>{$cPotPerWinner}
                </div>
            </div>
HTML;
        }

        $firstKickoff = htmlspecialchars($digestData['first_kickoff_formatted'] ?? 'Upcoming Kickoff');
        $upcomingActionBannerHtml = <<<HTML
        <div style="background: linear-gradient(135deg, #064e3b 0%, #065f46 100%); border-radius: 14px; padding: 18px 20px; margin-bottom: 24px; border: 1px solid #059669; text-align: center; box-shadow: 0 4px 15px rgba(5, 150, 105, 0.25);">
            <div style="font-size: 11px; font-weight: 900; color: #a7f3d0; text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 4px;">
                ⚡ Week {$week} Picks are Open!
            </div>
            <div style="font-size: 13px; color: #ffffff; margin-bottom: 14px; line-height: 1.4;">
                First kickoff is <strong>{$firstKickoff}</strong>. Be sure to lock in your Pick'em selections and choose your Survivor team!
            </div>
            <div>
                <a href="https://football.wallyatkins.com/pickem?week={$week}&mtm_campaign=week_{$week}&mtm_source=morning_digest&mtm_medium=email" 
                   style="display: inline-block; margin: 4px; padding: 11px 20px; background-color: #ffffff; color: #064e3b; font-weight: 900; font-size: 12px; text-decoration: none; border-radius: 8px; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                    🏈 Submit Week {$week} Picks &rarr;
                </a>
                <a href="https://football.wallyatkins.com/survivor?week={$week}&mtm_campaign=week_{$week}&mtm_source=morning_digest&mtm_medium=email" 
                   style="display: inline-block; margin: 4px; padding: 11px 20px; background-color: #047857; color: #ffffff; font-weight: 900; font-size: 12px; text-decoration: none; border-radius: 8px; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid #10b981;">
                    🛡️ Lock Survivor Pick &rarr;
                </a>
            </div>
        </div>
HTML;

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Week {$week} Morning Update — Pick'em &amp; Survivor</title>
</head>
<body style="margin: 0; padding: 0; background-color: #090d16; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #f8fafc;">
    <div style="max-width: 620px; margin: 0 auto; background-color: #0f172a; border-radius: 16px; overflow: hidden; border: 1px solid #334155; margin-top: 20px; margin-bottom: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
        
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 24px 24px 20px 24px; border-bottom: 2px solid #f59e0b;">
            <div style="font-size: 11px; font-family: monospace; font-weight: bold; color: #f59e0b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">
                🏈 Atkins NFL Pool &bull; Season {$season}
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
            {$completedWeekBannerHtml}
            {$upcomingActionBannerHtml}

            <p style="font-size: 15px; color: #e2e8f0; margin-top: 0; line-height: 1.5;">
                Good morning, <strong>{$username}</strong>! Here is your daily status report on how your NFL Pick'em and Survivor picks performed and where you sit on the league leaderboards.
            </p>

            <!-- Pick'em Scorecard Hero -->
            <div style="background-color: #1e293b; border-radius: 12px; padding: 18px; margin-bottom: 16px; border: 1px solid #475569;">
                <div style="font-size: 11px; font-weight: 800; color: #f59e0b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; border-bottom: 1px solid #334155; padding-bottom: 8px;">
                    🏈 Week {$week} Pick'em Performance
                </div>
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

            <!-- Survivor Pool Status Hero -->
            <div style="background-color: #1e293b; border-radius: 12px; padding: 18px; margin-bottom: 24px; border: 1px solid #475569;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #334155; padding-bottom: 8px;">
                    <span style="font-size: 11px; font-weight: 800; color: #f59e0b; text-transform: uppercase; letter-spacing: 0.5px;">
                        🛡️ Survivor Pool Status
                    </span>
                    <span>
                        {$survivorBadge}
                    </span>
                </div>
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <tr>
                        <td style="padding: 6px 0; color: #94a3b8; width: 32%;">Week {$week} Pick:</td>
                        <td style="padding: 6px 0; color: #f8fafc;">
                            {$survivorPickHtml}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #94a3b8;">Teams Burned:</td>
                        <td style="padding: 6px 0; color: #cbd5e1; font-family: monospace; font-size: 12px;">
                            {$survUsedStr}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #94a3b8;">League Field:</td>
                        <td style="padding: 6px 0; color: #94a3b8; font-size: 12px;">
                            <strong style="color: #34d399;">{$survivorSummary['total_alive']} Alive</strong> &bull; 
                            <span style="color: #f43f5e;">{$survivorSummary['total_eliminated']} Eliminated</span>
                            {$potHtml}
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

            <!-- Call to Action (Dual Links) -->
            <div style="text-align: center; margin: 32px 0 16px 0;">
                <a href="https://football.wallyatkins.com/pickem/standings?week={$week}&mtm_campaign=week_{$week}&mtm_source=morning_digest&mtm_medium=email" 
                   style="display: inline-block; margin: 4px; padding: 13px 22px; background-color: #f59e0b; color: #0f172a; font-weight: 900; font-size: 13px; text-decoration: none; border-radius: 10px; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);">
                    View Full Standings &amp; Opponent Picks &rarr;
                </a>
                <a href="https://football.wallyatkins.com/survivor/standings?season={$season}&mtm_campaign=week_{$week}&mtm_source=morning_digest&mtm_medium=email" 
                   style="display: inline-block; margin: 4px; padding: 13px 22px; background-color: #334155; color: #f8fafc; font-weight: 800; font-size: 13px; text-decoration: none; border-radius: 10px; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid #475569;">
                    View Survivor Board &rarr;
                </a>
            </div>

        </div>

        <!-- Footer -->
        <div style="background-color: #090d16; padding: 18px 24px; border-top: 1px solid #1e293b; text-align: center; font-size: 11px; color: #64748b; line-height: 1.6;">
            Sent by Commissioner Wally Atkins &bull; <a href="mailto:football@wallyatkins.com" style="color: #94a3b8; text-decoration: underline;">football@wallyatkins.com</a><br>
            Protected by WallyAuth SSO &bull; <a href="https://football.wallyatkins.com" style="color: #94a3b8; text-decoration: underline;">football.wallyatkins.com</a><br>
            <div style="margin-top: 8px; color: #475569; font-size: 10px;">
                You are receiving this daily morning briefing as a participant in Wally's NFL Pool.<br>
                To manage notifications or unsubscribe, visit your <a href="https://football.wallyatkins.com/preferences" style="color: #64748b; text-decoration: underline;">account preferences</a>.
            </div>
        </div>

        <!-- Matomo Analytics Open Tracking Pixel (Site ID 9: Email Newsletters & Telemetry) -->
        <img src="https://analytics.wallyatkins.com/matomo.php?idsite=9&amp;rec=1&amp;action_name=email%2Fmorning_digest_week_{$week}&amp;url=https%3A%2F%2Femail.wallyatkins.com%2Fdigest%2Fweek_{$week}" width="1" height="1" style="display:none; width:1px; height:1px; border:0;" alt="" />

    </div>
</body>
</html>
HTML;
    }

    public function renderText(array $userEntry, array $digestData): string
    {
        $username = $userEntry['username'] ?? 'Player';
        $week = (int) $digestData['week'];
        $season = (int) $digestData['season'];
        $entryId = isset($userEntry['entry_id']) ? (int) $userEntry['entry_id'] : 0;
        $userId = isset($userEntry['user_id']) ? (int) $userEntry['user_id'] : 0;

        if ($userId === 0 && $entryId > 0) {
            foreach ($digestData['entries'] as $en) {
                if ((int) ($en['entry_id'] ?? 0) === $entryId) {
                    $userId = (int) ($en['user_id'] ?? 0);
                    break;
                }
            }
            if ($userId === 0) {
                $userId = (int) ($this->db->queryValue('SELECT user_id FROM pickem_entries WHERE id = :id', ['id' => $entryId]) ?: 0);
            }
        }

        $userPicks = ($entryId > 0) ? ($digestData['picks_by_entry'][$entryId] ?? []) : [];

        $userStanding = null;
        if ($entryId > 0) {
            foreach ($digestData['standings'] as $st) {
                if ((int) $st['entry_id'] === $entryId) {
                    $userStanding = $st;
                    break;
                }
            }
        }

        $userCorrect = $userStanding['correct_picks'] ?? 0;
        $userGraded = $userStanding['total_graded'] ?? 0;
        $userRank = $userStanding['rank'] ?? '-';
        $userPending = $userStanding['pending_picks'] ?? 0;

        // Survivor Data
        $survivorData = ($userId > 0) ? ($digestData['survivor_by_user'][$userId] ?? null) : null;
        $survivorSummary = $digestData['survivor_summary'] ?? [
            'total_alive' => 0,
            'total_eliminated' => 0,
            'cash_pot' => 0.0,
        ];

        $isSurvivorAlive = ($survivorData['is_alive'] ?? false);
        $isSurvivorEliminated = ($survivorData['is_eliminated'] ?? false);
        $survElimWeek = $survivorData['elimination_week'] ?? null;
        $survPick = $survivorData['current_pick'] ?? null;
        $survPickDesc = $survivorData['pick_result_desc'] ?? '';
        $survTeamsUsed = $survivorData['teams_used'] ?? [];
        $survUsedStr = !empty($survTeamsUsed) ? implode(', ', $survTeamsUsed) : 'None';

        if ($isSurvivorAlive) {
            $survivorStatusText = "ALIVE (In the Hunt)";
        } elseif ($isSurvivorEliminated) {
            $elimLabel = $survElimWeek ? "Week {$survElimWeek}" : "Eliminated";
            $survivorStatusText = "ELIMINATED ({$elimLabel})";
        } else {
            $survivorStatusText = "Not Entered";
        }

        if ($survPick !== null) {
            $survivorPickText = "{$survPick} ({$survPickDesc})";
        } elseif ($isSurvivorAlive) {
            $survivorPickText = "⚠️ No pick locked yet! (Lock at https://football.wallyatkins.com/survivor)";
        } else {
            $survivorPickText = "N/A";
        }

        $potText = ($survivorSummary['cash_pot'] ?? 0) > 0 ? " • $" . number_format((float) $survivorSummary['cash_pot'], 0) . " Pot" : "";

        $lines = [];
        $lines[] = "🏈 ATKINS NFL POOL • WEEK {$week} MORNING UPDATE ({$season})";
        $lines[] = "============================================================";
        $lines[] = "Good morning, {$username}!";
        $lines[] = "";

        if (!empty($digestData['completed_week']) && !empty($digestData['completed_week_winners'])) {
            $cWeek = (int) $digestData['completed_week'];
            $cWinners = $digestData['completed_week_winners'];
            $cNamesStr = implode(' & ', array_column($cWinners, 'username'));
            $cScore = $cWinners[0]['correct_picks'] ?? 0;
            $lines[] = "👑 OFFICIAL WEEK {$cWeek} CHAMPION:";
            $lines[] = "Congratulations, {$cNamesStr}! Finished #1 with {$cScore} correct picks.";
            $lines[] = "";
        }

        $lines[] = "⚡ WEEK {$week} PICKS ARE OPEN!";
        $lines[] = "First kickoff: " . ($digestData['first_kickoff_formatted'] ?? 'Kickoff');
        $lines[] = "Lock in your selections before games start:";
        $lines[] = "- Pick'em: https://football.wallyatkins.com/pickem?week={$week}";
        $lines[] = "- Survivor: https://football.wallyatkins.com/survivor?week={$week}";
        $lines[] = "";
        $lines[] = "YOUR STATUS:";
        $lines[] = "- Score: {$userCorrect} Correct / {$userGraded} Graded";
        $lines[] = "- Current Rank: #{$userRank}";
        $lines[] = "- Pending Games: {$userPending}";
        $lines[] = "";
        $lines[] = "SURVIVOR STATUS:";
        $lines[] = "- Pool Status: {$survivorStatusText}";
        $lines[] = "- Week {$week} Pick: {$survivorPickText}";
        $lines[] = "- Teams Burned: {$survUsedStr}";
        $lines[] = "- League Contenders: {$survivorSummary['total_alive']} Alive / {$survivorSummary['total_eliminated']} Eliminated{$potText}";
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
        $lines[] = "View live Pick'em standings & opponent picks:";
        $lines[] = "https://football.wallyatkins.com/pickem/standings?week={$week}";
        $lines[] = "View Survivor board & contenders:";
        $lines[] = "https://football.wallyatkins.com/survivor/standings?season={$season}";
        $lines[] = "============================================================";
        $lines[] = "Commissioner Wally Atkins • football@wallyatkins.com";
        $lines[] = "Manage notifications or unsubscribe: https://football.wallyatkins.com/preferences";

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
                'reason' => 'No active participants found for this week',
                'sent' => 0,
                'failed' => 0,
                'log' => [],
            ];
        }

        $sentCount = 0;
        $failedCount = 0;
        $log = [];

        if (!empty($digestData['completed_week']) && !empty($digestData['completed_week_winners'])) {
            $cWeek = (int) $digestData['completed_week'];
            $wNames = implode(', ', array_column($digestData['completed_week_winners'], 'username'));
            $subject = "🏆 Atkins NFL Pool: Week {$cWeek} Winner {$wNames}! Week {$week} Picks Open";
        } else {
            $subject = "🏈 Atkins NFL Pool: Week {$week} Morning Update — Pick'em & Survivor Status";
        }

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
                "X-Mailer: AtkinsNFLPoolDigest/1.0",
                "List-Unsubscribe: <https://football.wallyatkins.com/preferences?email=" . urlencode($email) . ">",
                "List-Unsubscribe-Post: List-Unsubscribe=One-Click",
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
            if (!empty($digestData['completed_week'])) {
                $this->markWrapupAsReported($season, (int) $digestData['completed_week']);
            }
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
