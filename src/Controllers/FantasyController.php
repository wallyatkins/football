<?php

declare(strict_types=1);

namespace WallyFootball\Controllers;

use WallyFootball\Services\FantasyVaultService;

class FantasyController
{
    private FantasyVaultService $vault;

    public function __construct(?FantasyVaultService $vault = null)
    {
        $this->vault = $vault ?? new FantasyVaultService();
    }

    /**
     * Dynasty Vault: Hall of Champions & All-Time Leaderboard
     */
    public function vault(): void
    {
        if (($_GET['tab'] ?? '') === 'pools') {
            $this->poolArchives();
            return;
        }

        $sortBy = $_GET['sort'] ?? 'titles';
        $hallOfFame = $this->vault->getHallOfFame();
        $leaderboard = $this->vault->getAllTimeLeaderboard($sortBy);
        $recordBook = $this->vault->getRecordBook(8);
        $franchises = $this->vault->getAllFranchises();

        $title = "Dynasty Vault | 20-Year League History";
        require dirname(__DIR__, 2) . '/templates/fantasy/vault.php';
    }

    /**
     * Pick'em and Survivor Historical Archives
     */
    public function poolArchives(): void
    {
        $season = isset($_GET['season']) ? (int) $_GET['season'] : (int) (getenv('NFL_CURRENT_SEASON') ?: date('Y'));
        $db = \WallyFootball\Database\Connection::getInstance();
        $scoring = new \WallyFootball\Services\ScoringEngine($db);

        // Find all weeks with games
        $availableWeeks = $db->query(
            'SELECT DISTINCT week_number FROM games WHERE season_year = :season ORDER BY week_number ASC',
            ['season' => $season]
        );
        $weeksList = array_column($availableWeeks, 'week_number');

        $completedWeeks = [];
        foreach ($weeksList as $w) {
            $w = (int) $w;
            $games = $db->query(
                'SELECT id, status FROM games WHERE season_year = :season AND week_number = :w',
                ['season' => $season, 'w' => $w]
            );
            $allFinal = count($games) > 0;
            foreach ($games as $g) {
                if ($g['status'] !== 'final') {
                    $allFinal = false;
                    break;
                }
            }
            if ($allFinal) {
                $pot = $scoring->calculateWeeklyPot($season, $w);
                $completedWeeks[] = [
                    'week' => $w,
                    'games_count' => count($games),
                    'pot' => $pot,
                    'winners' => $pot['winners'] ?? [],
                ];
            }
        }

        // Survivor records
        $survivorPot = $scoring->calculateSurvivorPot($season);
        $survivorStandings = $scoring->getSurvivorStandings($season);

        $title = "Pick'em & Survivor Archives | Dynasty Vault";
        require dirname(__DIR__, 2) . '/templates/fantasy/pool_archive.php';
    }

    /**
     * Head-to-Head Rivalry Matrix
     */
    public function rivalry(): void
    {
        $franchises = $this->vault->getAllFranchises();

        // Default to Archetypo (1) vs Wonder Twins (3) if not provided
        $teamAId = isset($_GET['teamA']) ? (int)$_GET['teamA'] : 1;
        $teamBId = isset($_GET['teamB']) ? (int)$_GET['teamB'] : 3;

        // Ensure different teams
        if ($teamAId === $teamBId) {
            $teamBId = ($teamAId === 1) ? 3 : 1;
        }

        $rivalryData = $this->vault->getRivalry($teamAId, $teamBId);

        $title = "Rivalry Matrix | Head-to-Head History";
        require dirname(__DIR__, 2) . '/templates/fantasy/rivalry.php';
    }

    /**
     * Year-by-Year Season Explorer
     */
    public function seasons(): void
    {
        $allSeasons = $this->vault->getAllSeasons();

        // Default to latest completed season (2024) or from query param
        $year = isset($_GET['year']) ? (int)$_GET['year'] : 2024;
        $seasonData = $this->vault->getSeason($year);

        $title = "{$year} Season History | Dynasty Vault";
        require dirname(__DIR__, 2) . '/templates/fantasy/season.php';
    }
}
