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

        // Check lock status per game
        $now = time();
        foreach ($games as $idx => $g) {
            $kickoff = strtotime($g['kickoff_time']);
            $games[$idx]['is_locked'] = ($kickoff <= $now);
            $games[$idx]['user_pick'] = $userPicks[$g['id']] ?? null;
        }

        // Find designated tiebreaker game
        $tiebreakerGame = null;
        foreach ($games as $g) {
            if (!empty($g['is_mnf'])) {
                $tiebreakerGame = $g;
                break;
            }
        }

        // Commissioner payment links (exact verified links)
        $venmoUrl = 'https://account.venmo.com/u/WallyAtkins';
        $payPalUrl = 'https://paypal.me/WallyAtkins';
        $cashAppUrl = 'https://cash.app/$WallyAtkins';

        $title = "Week {$week} Pick'em — Wally's NFL Pool";
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

        // Check if user entry already exists and is locked
        $entry = $this->db->queryOne(
            'SELECT id, mnf_total_points_prediction, is_locked FROM pickem_entries WHERE user_id = :uid AND season_year = :season AND week_number = :week',
            ['uid' => $user['id'], 'season' => $season, 'week' => $week]
        );

        if ($entry && !empty($entry['is_locked'])) {
            $_SESSION['error'] = "Your picks for Week {$week} are already locked in and cannot be modified.";
            header("Location: /pickem?week={$week}&season={$season}");
            exit;
        }

        // Validate that all open/unlocked games are picked
        $unlockedGames = array_filter($games, fn($g) => strtotime($g['kickoff_time']) > $now);
        $unpickedCount = 0;
        foreach ($unlockedGames as $g) {
            $pick = $submittedPicks[$g['id']] ?? null;
            if (empty($pick) || ($pick !== $g['home_team'] && $pick !== $g['away_team'])) {
                $unpickedCount++;
            }
        }

        // Validate tiebreaker if designated game has not kicked off
        $tiebreakerMissing = false;
        if ($mnfGame && strtotime($mnfGame['kickoff_time']) > $now) {
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

        // Ensure or update entry as locked
        if (!$entry) {
            $entryId = (int) $this->db->insert(
                'INSERT INTO pickem_entries (user_id, season_year, week_number, mnf_total_points_prediction, payment_status, is_locked, locked_at)
                 VALUES (:uid, :season, :week, :mnf, "pending", 1, CURRENT_TIMESTAMP)',
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

        $_SESSION['flash'] = "Your Week {$week} picks are officially LOCKED IN! Don't forget to send your $10.00 entry fee.";
        header("Location: /pickem?week={$week}&season={$season}");
        exit;
    }

    public function standings(int $season, int $week): void
    {
        $user = $_SESSION['user'] ?? null;
        $standings = $this->scoring->getWeeklyStandings($season, $week);
        $pot = $this->scoring->calculateWeeklyPot($season, $week);
        $tiebreakerGame = $this->db->queryOne(
            'SELECT * FROM games WHERE season_year = :season AND week_number = :week AND is_mnf = 1',
            ['season' => $season, 'week' => $week]
        );

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
