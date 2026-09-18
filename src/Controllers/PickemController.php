<?php

declare(strict_types=1);

namespace WallyFootball\Controllers;

use WallyFootball\Database\Connection;
use WallyFootball\Services\NotificationService;
use WallyFootball\Services\ScoringEngine;
use WallyFootball\Services\SportsDataService;

class PickemController
{
    private Connection $db;
    private SportsDataService $sports;
    private ScoringEngine $scoring;
    private NotificationService $notifier;

    public function __construct(
        ?Connection $db = null,
        ?SportsDataService $sports = null,
        ?ScoringEngine $scoring = null,
        ?NotificationService $notifier = null
    ) {
        $this->db = $db ?? Connection::getInstance();
        $this->sports = $sports ?? new SportsDataService($this->db);
        $this->scoring = $scoring ?? new ScoringEngine($this->db);
        $this->notifier = $notifier ?? new NotificationService();
    }

    public function index(int $season, int $week): void
    {
        $user = $this->requireAuth();

        $this->sports->syncIfNeeded($season, $week);

        $games = $this->db->query(
            'SELECT * FROM games WHERE season_year = :season AND week_number = :week ORDER BY kickoff_time ASC, id ASC',
            ['season' => $season, 'week' => $week]
        );

        if (empty($games)) {
            // Auto-sync games if empty
            $this->sports->syncWeek($season, $week);
            $games = $this->db->query(
                'SELECT * FROM games WHERE season_year = :season AND week_number = :week ORDER BY kickoff_time ASC, id ASC',
                ['season' => $season, 'week' => $week]
            );
        }

        // Deduplicate in memory as an ironclad safety check against identical matchups
        $uniqueGames = [];
        $seenMatchups = [];
        foreach ($games as $g) {
            $h = \WallyFootball\Support\TeamData::normalize($g['home_team']);
            $a = \WallyFootball\Support\TeamData::normalize($g['away_team']);
            $teams = [$h, $a];
            sort($teams);
            $key = $teams[0] . '_' . $teams[1];
            if (isset($seenMatchups[$key])) {
                continue;
            }
            $seenMatchups[$key] = true;
            $g['home_team'] = $h;
            $g['away_team'] = $a;
            $uniqueGames[] = $g;
        }
        $games = $uniqueGames;

        // Get user entry
        $entry = $this->db->queryOne(
            'SELECT * FROM pickem_entries WHERE user_id = :uid AND season_year = :season AND week_number = :week',
            ['uid' => $user['id'], 'season' => $season, 'week' => $week]
        );

        $userPicks = [];
        if ($entry) {
            $picks = $this->db->query(
                'SELECT game_id, selected_team FROM pickem_picks WHERE entry_id = :eid',
                ['eid' => $entry['id']]
            );
            foreach ($picks as $p) {
                $userPicks[$p['game_id']] = $p['selected_team'];
            }
        }

        // Check lock status and pick grading per game
        $now = time();
        $userCorrectCount = 0;
        $userIncorrectCount = 0;
        $userGradedCount = 0;
        $userPendingCount = 0;

        // Check first game kickoff and calculate 1-hour cutoff deadline for the entire week
        $firstGameKickoff = null;
        foreach ($games as $g) {
            $kt = strtotime($g['kickoff_time']);
            if ($firstGameKickoff === null || $kt < $firstGameKickoff) {
                $firstGameKickoff = $kt;
            }
        }
        $cutoffTime = $firstGameKickoff !== null ? ($firstGameKickoff + 3600) : null;
        $isWeekLocked = ($cutoffTime !== null && $now >= $cutoffTime);
        $firstKickoffFormatted = $firstGameKickoff
            ? (new \DateTimeImmutable("@{$firstGameKickoff}"))->setTimezone(new \DateTimeZone('America/New_York'))->format('D, M j @ g:i A T')
            : 'Kickoff of Week ' . $week;
        $cutoffFormatted = $cutoffTime
            ? (new \DateTimeImmutable("@{$cutoffTime}"))->setTimezone(new \DateTimeZone('America/New_York'))->format('D, M j @ g:i A T')
            : 'Cutoff of Week ' . $week;

        foreach ($games as $idx => $g) {
            $games[$idx]['is_locked'] = $isWeekLocked;
            $userPick = $userPicks[$g['id']] ?? null;
            $games[$idx]['user_pick'] = $userPick;

            $winningTeam = null;
            if ($g['status'] === 'final' && $g['home_score'] !== null && $g['away_score'] !== null) {
                if ($g['home_score'] > $g['away_score']) {
                    $winningTeam = $g['home_team'];
                } elseif ($g['away_score'] > $g['home_score']) {
                    $winningTeam = $g['away_team'];
                }
            }
            $games[$idx]['winning_team'] = $winningTeam;

            $pickResult = 'pending';
            if ($g['status'] === 'final') {
                if ($userPick !== null && $winningTeam !== null) {
                    if ($userPick === $winningTeam) {
                        $pickResult = 'correct';
                        $userCorrectCount++;
                    } else {
                        $pickResult = 'incorrect';
                        $userIncorrectCount++;
                    }
                    $userGradedCount++;
                } elseif ($winningTeam === null) {
                    $pickResult = 'push';
                    $userGradedCount++;
                } else {
                    $pickResult = 'unpicked';
                }
            } else {
                $userPendingCount++;
            }
            $games[$idx]['pick_result'] = $pickResult;
        }

        // Find designated tiebreaker game
        $tiebreakerGame = null;
        foreach ($games as $g) {
            if (!empty($g['is_mnf'])) {
                $tiebreakerGame = $g;
                break;
            }
        }

        // Determine if week is fully complete (all games played & final)
        $isWeekComplete = count($games) > 0;
        foreach ($games as $g) {
            if ($g['status'] !== 'final') {
                $isWeekComplete = false;
                break;
            }
        }

        $potInfo = null;
        $weeklyWinners = [];
        $weeklyWinnersOverall = [];
        $weeklyWinnersPaid = [];
        $weeklyWinnersFree = [];
        if ($isWeekComplete) {
            $potInfo = $this->scoring->calculateWeeklyPot($season, $week);
            $weeklyWinners = $potInfo['winners'] ?? [];
            $weeklyWinnersOverall = $this->scoring->getWeeklyWinnersByMode($season, $week, null);
            $weeklyWinnersPaid = $this->scoring->getWeeklyWinnersByMode($season, $week, 'paid');
            $weeklyWinnersFree = $this->scoring->getWeeklyWinnersByMode($season, $week, 'free');
        }

        // Check for prior completed week to celebrate last week's champions
        $lastCompletedWeek = null;
        $lastWeekPotInfo = null;
        $lastWeekWinners = [];
        $lastWeekWinnersOverall = [];
        $lastWeekWinnersPaid = [];
        $lastWeekWinnersFree = [];
        $checkPriorWeek = ($week > 1) ? ($week - 1) : 0;
        if ($checkPriorWeek >= 1) {
            $priorGames = $this->db->query(
                'SELECT status FROM games WHERE season_year = :s AND week_number = :w',
                ['s' => $season, 'w' => $checkPriorWeek]
            );
            $priorComplete = !empty($priorGames);
            foreach ($priorGames as $pg) {
                if ($pg['status'] !== 'final') {
                    $priorComplete = false;
                    break;
                }
            }
            if ($priorComplete) {
                $lastCompletedWeek = $checkPriorWeek;
                $lastWeekPotInfo = $this->scoring->calculateWeeklyPot($season, $checkPriorWeek);
                $lastWeekWinners = $lastWeekPotInfo['winners'] ?? [];
                $lastWeekWinnersOverall = $this->scoring->getWeeklyWinnersByMode($season, $checkPriorWeek, null);
                $lastWeekWinnersPaid = $this->scoring->getWeeklyWinnersByMode($season, $checkPriorWeek, 'paid');
                $lastWeekWinnersFree = $this->scoring->getWeeklyWinnersByMode($season, $checkPriorWeek, 'free');
            }
        }

        // Commissioner payment links (exact verified links)
        $venmoUrl = 'https://account.venmo.com/u/WallyAtkins';
        $payPalUrl = 'https://paypal.me/WallyAtkins';
        $cashAppUrl = 'https://cash.app/$WallyAtkins';

        $title = "Week {$week} Pick'em — Wally's NFL Pool";
        require dirname(__DIR__, 2) . '/templates/pickem/grid.php';
    }

    public function autoSave(): void
    {
        header('Content-Type: application/json');

        if (empty($_SESSION['user']['id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Please log in to save picks.']);
            exit;
        }
        $user = $_SESSION['user'];

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $season = (int) ($input['season_year'] ?? date('Y'));
        $week = (int) ($input['week_number'] ?? 1);
        $gameId = isset($input['game_id']) ? (int) $input['game_id'] : null;
        $selectedTeam = isset($input['selected_team']) ? strtoupper(trim((string) $input['selected_team'])) : null;
        $mnfPoints = isset($input['mnf_total_points']) && $input['mnf_total_points'] !== ''
            ? (int) $input['mnf_total_points']
            : null;

        // Check first game kickoff deadline (cutoff is 1 hour into the first game)
        $firstGame = $this->db->queryOne(
            'SELECT MIN(kickoff_time) as first_kickoff FROM games WHERE season_year = :season AND week_number = :week',
            ['season' => $season, 'week' => $week]
        );
        $firstKickoff = !empty($firstGame['first_kickoff']) ? strtotime($firstGame['first_kickoff']) : null;
        $cutoffTime = $firstKickoff !== null ? ($firstKickoff + 3600) : null;
        if ($cutoffTime !== null && time() >= $cutoffTime) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Picks for Week {$week} are locked (cutoff deadline of 1 hour into the first game has passed)."]);
            exit;
        }

        // Get or create pickem_entries record
        $entry = $this->db->queryOne(
            'SELECT id, mnf_total_points_prediction FROM pickem_entries WHERE user_id = :uid AND season_year = :season AND week_number = :week',
            ['uid' => $user['id'], 'season' => $season, 'week' => $week]
        );

        if (!$entry) {
            $entryId = (int) $this->db->insert(
                "INSERT INTO pickem_entries (user_id, season_year, week_number, payment_status, is_locked)
                 VALUES (:uid, :season, :week, 'pending', 0)",
                ['uid' => $user['id'], 'season' => $season, 'week' => $week]
            );
        } else {
            $entryId = (int) $entry['id'];
        }

        // Update tiebreaker if explicitly passed in payload
        if (array_key_exists('mnf_total_points', $input)) {
            $this->db->execute(
                'UPDATE pickem_entries SET mnf_total_points_prediction = :mnf WHERE id = :id',
                ['mnf' => $mnfPoints, 'id' => $entryId]
            );
        }

        // Save individual game pick if provided
        $savedPick = null;
        if ($gameId !== null && !empty($selectedTeam)) {
            $game = $this->db->queryOne(
                'SELECT home_team, away_team, kickoff_time FROM games WHERE id = :gid AND season_year = :season AND week_number = :week',
                ['gid' => $gameId, 'season' => $season, 'week' => $week]
            );

            if ($game && ($selectedTeam === $game['home_team'] || $selectedTeam === $game['away_team'])) {
                $existingPick = $this->db->queryOne(
                    'SELECT id FROM pickem_picks WHERE entry_id = :eid AND game_id = :gid',
                    ['eid' => $entryId, 'gid' => $gameId]
                );

                if ($existingPick) {
                    $this->db->execute(
                        'UPDATE pickem_picks SET selected_team = :team WHERE id = :id',
                        ['team' => $selectedTeam, 'id' => $existingPick['id']]
                    );
                } else {
                    $this->db->insert(
                        'INSERT INTO pickem_picks (entry_id, game_id, selected_team) VALUES (:eid, :gid, :team)',
                        ['eid' => $entryId, 'gid' => $gameId, 'team' => $selectedTeam]
                    );
                }
                $savedPick = [
                    'game_id' => $gameId,
                    'selected_team' => $selectedTeam,
                ];
            }
        }

        $pickCount = (int) $this->db->queryValue(
            'SELECT count(*) FROM pickem_picks WHERE entry_id = :eid',
            ['eid' => $entryId]
        );
        $totalGames = (int) $this->db->queryValue(
            'SELECT count(*) FROM games WHERE season_year = :season AND week_number = :week',
            ['season' => $season, 'week' => $week]
        );

        echo json_encode([
            'success' => true,
            'message' => 'Auto-saved successfully',
            'saved_pick' => $savedPick,
            'pick_count' => $pickCount,
            'total_games' => $totalGames,
            'entry_id' => $entryId,
            'mnf_total_points' => $mnfPoints,
        ]);
        exit;
    }

    public function save(): void
    {
        $user = $this->requireAuth();

        $season = (int) ($_POST['season_year'] ?? date('Y'));
        $week = (int) ($_POST['week_number'] ?? 1);
        $mnfPrediction = isset($_POST['mnf_total_points']) && $_POST['mnf_total_points'] !== ''
            ? (int) $_POST['mnf_total_points']
            : null;
        $submittedPicks = $_POST['picks'] ?? [];

        $games = $this->db->query(
            'SELECT id, home_team, away_team, kickoff_time, is_mnf FROM games WHERE season_year = :season AND week_number = :week',
            ['season' => $season, 'week' => $week]
        );

        $now = time();
        $firstGameKickoff = null;
        $validGames = [];
        $mnfGame = null;
        foreach ($games as $g) {
            $validGames[$g['id']] = $g;
            if ($g['is_mnf']) {
                $mnfGame = $g;
            }
            $kt = strtotime($g['kickoff_time']);
            if ($firstGameKickoff === null || $kt < $firstGameKickoff) {
                $firstGameKickoff = $kt;
            }
        }

        $cutoffTime = $firstGameKickoff !== null ? ($firstGameKickoff + 3600) : null;
        $firstKickoffFormatted = $firstGameKickoff
            ? (new \DateTimeImmutable("@{$firstGameKickoff}"))->setTimezone(new \DateTimeZone('America/New_York'))->format('D, M j @ g:i A T')
            : 'Kickoff of Week ' . $week;
        $cutoffFormatted = $cutoffTime
            ? (new \DateTimeImmutable("@{$cutoffTime}"))->setTimezone(new \DateTimeZone('America/New_York'))->format('D, M j @ g:i A T')
            : 'Cutoff of Week ' . $week;

        // Check if week is locked (Cutoff is 1 hour into the first game)
        if ($cutoffTime !== null && $now >= $cutoffTime) {
            $_SESSION['error'] = "Picks for Week {$week} closed at {$cutoffFormatted} (1 hour after the week's first game kicked off).";
            header("Location: /pickem?week={$week}&season={$season}");
            exit;
        }

        // Validate that all games are picked for final submission
        $unpickedCount = 0;
        foreach ($games as $g) {
            $pick = $submittedPicks[$g['id']] ?? null;
            if (empty($pick) || ($pick !== $g['home_team'] && $pick !== $g['away_team'])) {
                $unpickedCount++;
            }
        }

        // Validate tiebreaker if designated game is present
        $tiebreakerMissing = false;
        if ($mnfGame) {
            if ($mnfPrediction === null || $mnfPrediction <= 0) {
                $tiebreakerMissing = true;
            }
        }

        if ($unpickedCount > 0 || $tiebreakerMissing) {
            $reasons = [];
            if ($unpickedCount > 0) {
                $reasons[] = "select a winner for all {$unpickedCount} remaining game(s)";
            }
            if ($tiebreakerMissing) {
                $tbDesc = $mnfGame ? "{$mnfGame['away_team']} @ {$mnfGame['home_team']}" : "Game of the Week";
                $reasons[] = "enter the combined total points tiebreaker for {$tbDesc}";
            }
            $_SESSION['error'] = 'Incomplete submission: You must ' . implode(' and ', $reasons) . '.';
            header("Location: /pickem?week={$week}&season={$season}");
            exit;
        }

        // Ensure or update entry (Mark as submitted/confirmed)
        $entry = $this->db->queryOne(
            'SELECT id, mnf_total_points_prediction, is_locked FROM pickem_entries WHERE user_id = :uid AND season_year = :season AND week_number = :week',
            ['uid' => $user['id'], 'season' => $season, 'week' => $week]
        );

        if (!$entry) {
            $entryId = (int) $this->db->insert(
                "INSERT INTO pickem_entries (user_id, season_year, week_number, mnf_total_points_prediction, payment_status, is_locked, locked_at)
                 VALUES (:uid, :season, :week, :mnf, 'pending', 1, CURRENT_TIMESTAMP)",
                ['uid' => $user['id'], 'season' => $season, 'week' => $week, 'mnf' => $mnfPrediction]
            );
        } else {
            $entryId = (int) $entry['id'];
            $this->db->execute(
                'UPDATE pickem_entries SET mnf_total_points_prediction = :mnf, is_locked = 1, locked_at = CURRENT_TIMESTAMP WHERE id = :id',
                ['mnf' => $mnfPrediction, 'id' => $entryId]
            );
        }

        // Process submitted picks
        $savedCount = 0;
        foreach ($submittedPicks as $gameId => $selectedTeam) {
            $gameId = (int) $gameId;
            $selectedTeam = strtoupper(trim((string) $selectedTeam));

            $game = $validGames[$gameId] ?? null;
            if (!$game) {
                continue;
            }

            // Verify team is in matchup
            if ($selectedTeam !== $game['home_team'] && $selectedTeam !== $game['away_team']) {
                continue;
            }

            // Upsert pick
            $existingPick = $this->db->queryOne(
                'SELECT id FROM pickem_picks WHERE entry_id = :eid AND game_id = :gid',
                ['eid' => $entryId, 'gid' => $gameId]
            );

            if ($existingPick) {
                $this->db->execute(
                    'UPDATE pickem_picks SET selected_team = :team, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                    ['team' => $selectedTeam, 'id' => $existingPick['id']]
                );
            } else {
                $this->db->execute(
                    'INSERT INTO pickem_picks (entry_id, game_id, selected_team) VALUES (:eid, :gid, :team)',
                    ['eid' => $entryId, 'gid' => $gameId, 'team' => $selectedTeam]
                );
            }
            $savedCount++;
        }

        try {
            $this->notifier->notifyPicksSubmitted(
                $user['username'] ?? 'Player',
                $week,
                $season,
                $mnfPrediction,
                $savedCount
            );
        } catch (\Throwable) {
            // Notification failures should never disrupt player experience
        }

        $_SESSION['flash'] = "✅ Your Week {$week} picks are confirmed and saved! You can adjust your picks anytime before the cutoff deadline ({$cutoffFormatted}).";
        header("Location: /pickem?week={$week}&season={$season}");
        exit;
    }

    public function standings(int $season, int $week): void
    {
        $user = $_SESSION['user'] ?? null;
        $this->sports->syncIfNeeded($season, $week);
        $standings = $this->scoring->getWeeklyStandings($season, $week);
        $pot = $this->scoring->calculateWeeklyPot($season, $week);
        $tiebreakerGame = $this->db->queryOne(
            'SELECT * FROM games WHERE season_year = :season AND week_number = :week AND is_mnf = 1',
            ['season' => $season, 'week' => $week]
        );

        $games = $this->db->query(
            'SELECT * FROM games WHERE season_year = :season AND week_number = :week ORDER BY kickoff_time ASC, id ASC',
            ['season' => $season, 'week' => $week]
        );
        $isWeekComplete = count($games) > 0;
        foreach ($games as $g) {
            if ($g['status'] !== 'final') {
                $isWeekComplete = false;
                break;
            }
        }

        // Three-way winner categories (only meaningful once week is complete)
        $winnersOverall = [];
        $winnersPaid    = [];
        $winnersFree    = [];
        if ($isWeekComplete) {
            $winnersOverall = $this->scoring->getWeeklyWinnersByMode($season, $week, null);
            $winnersPaid    = $this->scoring->getWeeklyWinnersByMode($season, $week, 'paid');
            $winnersFree    = $this->scoring->getWeeklyWinnersByMode($season, $week, 'free');
        }

        // Available weeks for navigation (weeks that have at least one game)
        $availableWeeks = $this->db->query(
            'SELECT DISTINCT week_number FROM games WHERE season_year = :season ORDER BY week_number ASC',
            ['season' => $season]
        );
        $availableWeeks = array_column($availableWeeks, 'week_number');

        // Determine opponent picks visibility:
        $firstGameKickoff = null;
        foreach ($games as $g) {
            $kt = strtotime($g['kickoff_time']);
            if ($firstGameKickoff === null || $kt < $firstGameKickoff) {
                $firstGameKickoff = $kt;
            }
        }
        $now = time();
        $firstGameStarted = ($firstGameKickoff !== null && $now >= $firstGameKickoff);
        $cutoffTime = $firstGameKickoff !== null ? ($firstGameKickoff + 3600) : null;
        $cutoffPassed = ($cutoffTime !== null && $now >= $cutoffTime);
        $firstKickoffFormatted = $firstGameKickoff 
            ? (new \DateTimeImmutable("@{$firstGameKickoff}"))->setTimezone(new \DateTimeZone('America/New_York'))->format('D, M j @ g:i A T')
            : 'Kickoff';
        $cutoffFormatted = $cutoffTime 
            ? (new \DateTimeImmutable("@{$cutoffTime}"))->setTimezone(new \DateTimeZone('America/New_York'))->format('D, M j @ g:i A T')
            : 'Cutoff';

        $viewerId = !empty($user['id']) ? (int) $user['id'] : null;
        $viewerEntry = null;
        $viewerHasSubmitted = false;
        if ($viewerId) {
            $viewerEntry = $this->db->queryOne(
                'SELECT id, is_locked FROM pickem_entries WHERE user_id = :uid AND season_year = :s AND week_number = :w',
                ['uid' => $viewerId, 's' => $season, 'w' => $week]
            );
            $viewerHasSubmitted = !empty($viewerEntry['is_locked']);
        }
        $isCommissioner = in_array($user['role'] ?? '', ['admin', 'commissioner'], true);

        // Core Rule: Participant picks remain confidential until the selection cutoff (1 hour into first game).
        // Once the cutoff time passes, users who have submitted their picks (or the commissioner)
        // can view opponent picks. For past/completed weeks, picks are always visible.
        $canViewOpponentPicks = ($cutoffPassed && ($viewerHasSubmitted || $isCommissioner)) || $isWeekComplete;

        // Fetch picks mapped by entry_id
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

        // Enrich standing rows with detailed game picks
        foreach ($standings as $idx => $st) {
            $ePicks = $picksByEntryId[$st['entry_id']] ?? [];
            $userPicksDetail = [];
            foreach ($games as $g) {
                $sel = $ePicks[$g['id']] ?? null;
                $win = null;
                if ($g['status'] === 'final' && $g['home_score'] !== null && $g['away_score'] !== null) {
                    if ($g['home_score'] > $g['away_score']) {
                        $win = $g['home_team'];
                    } elseif ($g['away_score'] > $g['home_score']) {
                        $win = $g['away_team'];
                    }
                }
                $res = 'pending';
                if ($g['status'] === 'final') {
                    if ($sel !== null && $win !== null) {
                        $res = ($sel === $win) ? 'correct' : 'incorrect';
                    } else {
                        $res = 'push';
                    }
                }
                $userPicksDetail[$g['id']] = [
                    'game_id' => $g['id'],
                    'away_team' => $g['away_team'],
                    'home_team' => $g['home_team'],
                    'away_score' => $g['away_score'],
                    'home_score' => $g['home_score'],
                    'status' => $g['status'],
                    'selected_team' => $sel,
                    'winning_team' => $win,
                    'result' => $res,
                ];
            }
            $standings[$idx]['picks_detail'] = $userPicksDetail;
        }

        $title = "Week {$week} Standings — Wally's NFL Pool";
        require dirname(__DIR__, 2) . '/templates/pickem/standings.php';
    }


    private function requireAuth(): array
    {
        if (empty($_SESSION['user'])) {
            header('Location: /auth/login');
            exit;
        }
        return $_SESSION['user'];
    }
}
