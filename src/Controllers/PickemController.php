<?php
declare(strict_types=1);

namespace WallyFootball\Controllers;

use DateTimeImmutable;
use WallyFootball\Database\Connection;
use WallyFootball\Services\ScoringEngine;
use WallyFootball\Services\SportsDataService;

class PickemController
{
    private Connection $db;
    private SportsDataService $sports;
    private ScoringEngine $scoring;

    public function __construct(
        ?Connection $db = null,
        ?SportsDataService $sports = null,
        ?ScoringEngine $scoring = null
    ) {
        $this->db = $db ?? Connection::getInstance();
        $this->sports = $sports ?? new SportsDataService($this->db);
        $this->scoring = $scoring ?? new ScoringEngine($this->db);
    }

    public function index(int $season, int $week): void
    {
        $user = $this->requireAuth();

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

        // Check lock status per game
        $now = time();
        foreach ($games as &$game) {
            $kickoff = strtotime($game['kickoff_time']);
            $game['is_locked'] = ($kickoff <= $now);
            $game['user_pick'] = $userPicks[$game['id']] ?? null;
        }

        // Commissioner handles
        $venmoHandle = getenv('COMMISSIONER_VENMO') ?: 'Wally-Atkins';
        $cashAppHandle = getenv('COMMISSIONER_CASHAPP') ?: 'WallyAtkins';

        $title = "Week {$week} Pick'em — Atkins NFL Pool";
        require dirname(__DIR__, 2) . '/templates/pickem/grid.php';
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
        $validGames = [];
        $mnfGame = null;
        foreach ($games as $g) {
            $validGames[$g['id']] = $g;
            if ($g['is_mnf']) {
                $mnfGame = $g;
            }
        }

        // Ensure or create entry
        $entry = $this->db->queryOne(
            'SELECT id, mnf_total_points_prediction FROM pickem_entries WHERE user_id = :uid AND season_year = :season AND week_number = :week',
            ['uid' => $user['id'], 'season' => $season, 'week' => $week]
        );

        if (!$entry) {
            $entryId = (int) $this->db->insert(
                'INSERT INTO pickem_entries (user_id, season_year, week_number, mnf_total_points_prediction, payment_status)
                 VALUES (:uid, :season, :week, :mnf, "pending")',
                ['uid' => $user['id'], 'season' => $season, 'week' => $week, 'mnf' => $mnfPrediction]
            );
        } else {
            $entryId = (int) $entry['id'];
            // Only update MNF tiebreaker if MNF has not kicked off
            $mnfLocked = $mnfGame && strtotime($mnfGame['kickoff_time']) <= $now;
            if (!$mnfLocked && $mnfPrediction !== null) {
                $this->db->execute(
                    'UPDATE pickem_entries SET mnf_total_points_prediction = :mnf WHERE id = :id',
                    ['mnf' => $mnfPrediction, 'id' => $entryId]
                );
            }
        }

        // Process submitted picks
        foreach ($submittedPicks as $gameId => $selectedTeam) {
            $gameId = (int) $gameId;
            $selectedTeam = strtoupper(trim((string) $selectedTeam));

            $game = $validGames[$gameId] ?? null;
            if (!$game) {
                continue;
            }

            // Reject updates if game has already kicked off (Lockout rule)
            if (strtotime($game['kickoff_time']) <= $now) {
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
        }

        $_SESSION['flash'] = 'Your picks have been saved successfully!';
        header("Location: /pickem?week={$week}&season={$season}");
        exit;
    }

    public function standings(int $season, int $week): void
    {
        $user = $_SESSION['user'] ?? null;
        $standings = $this->scoring->getWeeklyStandings($season, $week);
        $pot = $this->scoring->calculateWeeklyPot($season, $week);

        $title = "Week {$week} Standings — Atkins NFL Pool";
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
