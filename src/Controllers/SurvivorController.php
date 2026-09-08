<?php
declare(strict_types=1);

namespace WallyFootball\Controllers;

use WallyFootball\Database\Connection;
use WallyFootball\Services\ScoringEngine;
use WallyFootball\Services\SportsDataService;

class SurvivorController
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

        // Check if user is eliminated
        $eliminatedRecord = $this->db->queryOne(
            'SELECT week_number FROM survivor_picks WHERE user_id = :uid AND season_year = :season AND is_eliminated = 1 LIMIT 1',
            ['uid' => $user['id'], 'season' => $season]
        );
        $isEliminated = ($eliminatedRecord !== null);
        $eliminationWeek = $eliminatedRecord ? (int) $eliminatedRecord['week_number'] : null;

        // Get all teams used by this user in previous weeks
        $usedPicks = $this->db->query(
            'SELECT week_number, selected_team FROM survivor_picks WHERE user_id = :uid AND season_year = :season AND week_number < :week',
            ['uid' => $user['id'], 'season' => $season, 'week' => $week]
        );
        $usedTeams = array_column($usedPicks, 'selected_team');

        // Current week pick
        $currentPick = $this->db->queryOne(
            'SELECT * FROM survivor_picks WHERE user_id = :uid AND season_year = :season AND week_number = :week',
            ['uid' => $user['id'], 'season' => $season, 'week' => $week]
        );

        // Get games for the week
        $games = $this->db->query(
            'SELECT * FROM games WHERE season_year = :season AND week_number = :week ORDER BY kickoff_time ASC, id ASC',
            ['season' => $season, 'week' => $week]
        );

        if (empty($games)) {
            $this->sports->syncWeek($season, $week);
            $games = $this->db->query(
                'SELECT * FROM games WHERE season_year = :season AND week_number = :week ORDER BY kickoff_time ASC, id ASC',
                ['season' => $season, 'week' => $week]
            );
        }

        $now = time();
        foreach ($games as &$g) {
            $kickoff = strtotime($g['kickoff_time']);
            $g['is_locked'] = ($kickoff <= $now);
            $g['home_used'] = in_array($g['home_team'], $usedTeams, true);
            $g['away_used'] = in_array($g['away_team'], $usedTeams, true);
        }

        $title = "Week {$week} Survivor — Wally's NFL Pool";
        require dirname(__DIR__, 2) . '/templates/survivor/index.php';
    }

    public function save(): void
    {
        $user = $this->requireAuth();

        $season = (int) ($_POST['season_year'] ?? date('Y'));
        $week = (int) ($_POST['week_number'] ?? 1);
        $selectedTeam = strtoupper(trim((string) ($_POST['selected_team'] ?? '')));

        if (!$selectedTeam) {
            $_SESSION['error'] = 'Please select a team for this week.';
            header("Location: /survivor?week={$week}&season={$season}");
            exit;
        }

        // 1. Verify user is not already eliminated
        $eliminated = $this->db->queryOne(
            'SELECT id FROM survivor_picks WHERE user_id = :uid AND season_year = :season AND is_eliminated = 1',
            ['uid' => $user['id'], 'season' => $season]
        );
        if ($eliminated) {
            $_SESSION['error'] = 'You have already been eliminated from this season\'s Survivor pool.';
            header("Location: /survivor?week={$week}&season={$season}");
            exit;
        }

        // 2. Verify team has NOT been used in an earlier week
        $previouslyUsed = $this->db->queryOne(
            'SELECT week_number FROM survivor_picks WHERE user_id = :uid AND season_year = :season AND selected_team = :team AND week_number != :week',
            ['uid' => $user['id'], 'season' => $season, 'team' => $selectedTeam, 'week' => $week]
        );
        if ($previouslyUsed) {
            $_SESSION['error'] = "You already used {$selectedTeam} in Week {$previouslyUsed['week_number']}!";
            header("Location: /survivor?week={$week}&season={$season}");
            exit;
        }

        // 3. Verify game has not kicked off yet
        $game = $this->db->queryOne(
            'SELECT kickoff_time FROM games WHERE season_year = :season AND week_number = :week AND (home_team = :team OR away_team = :team)',
            ['season' => $season, 'week' => $week, 'team' => $selectedTeam]
        );

        if (!$game || strtotime($game['kickoff_time']) <= time()) {
            $_SESSION['error'] = "The game featuring {$selectedTeam} has already started or is locked.";
            header("Location: /survivor?week={$week}&season={$season}");
            exit;
        }

        // 4. Upsert pick
        $existing = $this->db->queryOne(
            'SELECT id FROM survivor_picks WHERE user_id = :uid AND season_year = :season AND week_number = :week',
            ['uid' => $user['id'], 'season' => $season, 'week' => $week]
        );

        if ($existing) {
            $this->db->execute(
                'UPDATE survivor_picks SET selected_team = :team WHERE id = :id',
                ['team' => $selectedTeam, 'id' => $existing['id']]
            );
        } else {
            $this->db->execute(
                'INSERT INTO survivor_picks (user_id, season_year, week_number, selected_team, is_eliminated, payment_status)
                 VALUES (:uid, :season, :week, :team, 0, "pending")',
                ['uid' => $user['id'], 'season' => $season, 'week' => $week, 'team' => $selectedTeam]
            );
        }

        $_SESSION['flash'] = "Your Survivor pick of {$selectedTeam} for Week {$week} is locked in!";
        header("Location: /survivor?week={$week}&season={$season}");
        exit;
    }

    public function standings(int $season): void
    {
        $user = $_SESSION['user'] ?? null;
        $standings = $this->scoring->getSurvivorStandings($season);

        $title = "Survivor Standings — Wally's NFL Pool";
        require dirname(__DIR__, 2) . '/templates/survivor/standings.php';
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
