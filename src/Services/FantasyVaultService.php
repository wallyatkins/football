<?php

declare(strict_types=1);

namespace WallyFootball\Services;

use WallyFootball\Database\Connection;

class FantasyVaultService
{
    private Connection $db;

    public function __construct(?Connection $db = null)
    {
        $this->db = $db ?? Connection::getInstance();
    }

    /**
     * Get all 12 persistent franchises with all-time stats
     */
    public function getAllFranchises(): array
    {
        return $this->db->query("
            SELECT id, current_name, current_managers, wins, losses, ties, win_pct, 
                   points_for, points_against, titles_count, avg_finish, avg_pts_year
            FROM fantasy_franchises
            ORDER BY titles_count DESC, wins DESC;
        ");
    }

    /**
     * Get a single franchise by ID
     */
    public function getFranchise(int $id): ?array
    {
        return $this->db->queryOne("
            SELECT id, current_name, current_managers, wins, losses, ties, win_pct, 
                   points_for, points_against, titles_count, avg_finish, avg_pts_year
            FROM fantasy_franchises
            WHERE id = ?;
        ", [$id]);
    }

    /**
     * Get Hall of Fame records: champions, multi-title owners, yearly roll of honor
     */
    public function getHallOfFame(): array
    {
        $champions = $this->db->query("
            SELECT s.year, s.champion_franchise_id, s.champion_name, 
                   s.runner_up_franchise_id, s.runner_up_name, s.notes,
                   f.current_name as current_champion_name, f.current_managers
            FROM fantasy_seasons s
            LEFT JOIN fantasy_franchises f ON s.champion_franchise_id = f.id
            WHERE s.champion_name IS NOT NULL
            ORDER BY s.year DESC;
        ");

        $ringLeaders = $this->db->query("
            SELECT id, current_name, current_managers, titles_count, wins, losses, win_pct
            FROM fantasy_franchises
            WHERE titles_count > 0
            ORDER BY titles_count DESC, wins DESC;
        ");

        return [
            'champions' => $champions,
            'ring_leaders' => $ringLeaders,
        ];
    }

    /**
     * Get the All-Time Leaderboard with custom sorting
     */
    public function getAllTimeLeaderboard(string $sortBy = 'titles'): array
    {
        $orderClause = match ($sortBy) {
            'wins' => 'wins DESC, win_pct DESC',
            'pct' => 'win_pct DESC, wins DESC',
            'points' => 'points_for DESC',
            'finish' => 'avg_finish ASC',
            default => 'titles_count DESC, wins DESC',
        };

        return $this->db->query("
            SELECT id, current_name, current_managers, wins, losses, ties, win_pct,
                   points_for, points_against, titles_count, avg_finish, avg_pts_year
            FROM fantasy_franchises
            ORDER BY {$orderClause};
        ");
    }

    /**
     * Get Record Book superlatives: highest games, lowest games, biggest blowouts, closest thrillers
     */
    public function getRecordBook(int $limit = 10): array
    {
        // Highest game scores
        $highestScores = $this->db->query("
            SELECT season_year, week_number, away_team_name, away_score, home_team_name, home_score,
                   CASE WHEN away_score >= home_score THEN away_team_name ELSE home_team_name END as team,
                   CASE WHEN away_score >= home_score THEN away_score ELSE home_score END as score,
                   CASE WHEN away_score >= home_score THEN home_team_name ELSE away_team_name END as opponent
            FROM fantasy_matchups
            ORDER BY score DESC
            LIMIT ?;
        ", [$limit]);

        // Lowest game scores (score > 0)
        $lowestScores = $this->db->query("
            SELECT season_year, week_number, away_team_name, away_score, home_team_name, home_score,
                   CASE WHEN away_score <= home_score THEN away_team_name ELSE home_team_name END as team,
                   CASE WHEN away_score <= home_score THEN away_score ELSE home_score END as score,
                   CASE WHEN away_score <= home_score THEN home_team_name ELSE away_team_name END as opponent
            FROM fantasy_matchups
            WHERE away_score > 0 AND home_score > 0
            ORDER BY score ASC
            LIMIT ?;
        ", [$limit]);

        // Closest thrillers (smallest point diff)
        $closestGames = $this->db->query("
            SELECT season_year, week_number, away_team_name, away_score, home_team_name, home_score, point_diff
            FROM fantasy_matchups
            ORDER BY point_diff ASC, (away_score + home_score) DESC
            LIMIT ?;
        ", [$limit]);

        // Biggest blowouts (largest point diff)
        $blowouts = $this->db->query("
            SELECT season_year, week_number, away_team_name, away_score, home_team_name, home_score, point_diff,
                   CASE WHEN away_score > home_score THEN away_team_name ELSE home_team_name END as winner_name,
                   CASE WHEN away_score > home_score THEN home_team_name ELSE away_team_name END as loser_name
            FROM fantasy_matchups
            ORDER BY point_diff DESC
            LIMIT ?;
        ", [$limit]);

        // Highest regular season team scores
        $highestSeasonPoints = $this->db->query("
            SELECT season_year, team_name, points_for, wins, losses, win_pct
            FROM fantasy_standings
            ORDER BY points_for DESC
            LIMIT ?;
        ", [$limit]);

        return [
            'highest_scores' => $highestScores,
            'lowest_scores' => $lowestScores,
            'closest_games' => $closestGames,
            'blowouts' => $blowouts,
            'highest_season_points' => $highestSeasonPoints,
        ];
    }

    /**
     * Compute Head-to-Head Rivalry between two franchises
     */
    public function getRivalry(int $teamAId, int $teamBId): array
    {
        $teamA = $this->getFranchise($teamAId);
        $teamB = $this->getFranchise($teamBId);

        if (!$teamA || !$teamB) {
            return [
                'teamA' => null,
                'teamB' => null,
                'winsA' => 0,
                'winsB' => 0,
                'ties' => 0,
                'pointsA' => 0.0,
                'pointsB' => 0.0,
                'matchups' => [],
            ];
        }

        $matchups = $this->db->query("
            SELECT season_year, week_number, away_franchise_id, away_team_name, away_score,
                   home_franchise_id, home_team_name, home_score, winner_franchise_id, point_diff, is_playoff
            FROM fantasy_matchups
            WHERE (away_franchise_id = ? AND home_franchise_id = ?)
               OR (away_franchise_id = ? AND home_franchise_id = ?)
            ORDER BY season_year DESC, week_number DESC;
        ", [$teamAId, $teamBId, $teamBId, $teamAId]);

        $winsA = 0;
        $winsB = 0;
        $ties = 0;
        $pointsA = 0.0;
        $pointsB = 0.0;

        foreach ($matchups as $m) {
            $isAHome = ((int)$m['home_franchise_id'] === $teamAId);
            $scoreA = $isAHome ? (float)$m['home_score'] : (float)$m['away_score'];
            $scoreB = $isAHome ? (float)$m['away_score'] : (float)$m['home_score'];

            $pointsA += $scoreA;
            $pointsB += $scoreB;

            if ($m['winner_franchise_id'] !== null) {
                if ((int)$m['winner_franchise_id'] === $teamAId) {
                    $winsA++;
                } elseif ((int)$m['winner_franchise_id'] === $teamBId) {
                    $winsB++;
                }
            } else {
                $ties++;
            }
        }

        $totalGames = count($matchups);
        $avgScoreA = $totalGames > 0 ? round($pointsA / $totalGames, 1) : 0.0;
        $avgScoreB = $totalGames > 0 ? round($pointsB / $totalGames, 1) : 0.0;

        return [
            'teamA' => $teamA,
            'teamB' => $teamB,
            'winsA' => $winsA,
            'winsB' => $winsB,
            'ties' => $ties,
            'total_games' => $totalGames,
            'pointsA' => round($pointsA, 1),
            'pointsB' => round($pointsB, 1),
            'avgScoreA' => $avgScoreA,
            'avgScoreB' => $avgScoreB,
            'matchups' => $matchups,
        ];
    }

    /**
     * Get Season Details (Standings, Champion, and Matchups by week)
     */
    public function getSeason(int $year): array
    {
        $season = $this->db->queryOne("
            SELECT year, champion_franchise_id, champion_name, runner_up_franchise_id, runner_up_name, notes
            FROM fantasy_seasons
            WHERE year = ?;
        ", [$year]);

        $standings = $this->db->query("
            SELECT season_year, franchise_id, team_name, division_name, wins, losses, ties, win_pct, points_for, points_against, rank
            FROM fantasy_standings
            WHERE season_year = ?
            ORDER BY rank ASC, wins DESC, points_for DESC;
        ", [$year]);

        $rawMatchups = $this->db->query("
            SELECT season_year, week_number, away_franchise_id, away_team_name, away_score,
                   home_franchise_id, home_team_name, home_score, winner_franchise_id, point_diff, is_playoff
            FROM fantasy_matchups
            WHERE season_year = ?
            ORDER BY week_number ASC, point_diff ASC;
        ", [$year]);

        $matchupsByWeek = [];
        foreach ($rawMatchups as $m) {
            $week = (int)$m['week_number'];
            $matchupsByWeek[$week][] = $m;
        }

        return [
            'season' => $season,
            'standings' => $standings,
            'matchups_by_week' => $matchupsByWeek,
        ];
    }

    /**
     * List all seasons available in history
     */
    public function getAllSeasons(): array
    {
        return $this->db->query("
            SELECT year, champion_franchise_id, champion_name, runner_up_name, notes
            FROM fantasy_seasons
            ORDER BY year DESC;
        ");
    }
}
