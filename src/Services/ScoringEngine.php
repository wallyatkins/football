<?php

declare(strict_types=1);

namespace WallyFootball\Services;

use WallyFootball\Database\Connection;

class ScoringEngine
{
    private Connection $db;

    public function __construct(?Connection $db = null)
    {
        $this->db = $db ?? Connection::getInstance();
    }

    /**
     * Compute weekly standings for Pick'em
     */
    public function getWeeklyStandings(int $season, int $week): array
    {
        // 1. Get all games for this week
        $games = $this->db->query(
            'SELECT id, home_team, away_team, home_score, away_score, status, is_mnf 
             FROM games 
             WHERE season_year = :season AND week_number = :week',
            ['season' => $season, 'week' => $week]
        );

        $gameMap = [];
        $mnfGame = null;
        foreach ($games as $g) {
            $gameMap[$g['id']] = $g;
            if ($g['is_mnf']) {
                $mnfGame = $g;
            }
        }

        $actualMnfTotal = null;
        if ($mnfGame && $mnfGame['status'] === 'final' && $mnfGame['home_score'] !== null && $mnfGame['away_score'] !== null) {
            $actualMnfTotal = (int) $mnfGame['home_score'] + (int) $mnfGame['away_score'];
        }

        // 2. Fetch all entries
        $entries = $this->db->query(
            'SELECT e.id as entry_id, e.user_id, e.mnf_total_points_prediction, e.payment_status,
                    u.username, u.email
             FROM pickem_entries e
             JOIN users u ON u.id = e.user_id
             WHERE e.season_year = :season AND e.week_number = :week',
            ['season' => $season, 'week' => $week]
        );

        $standings = [];

        foreach ($entries as $entry) {
            $picks = $this->db->query(
                'SELECT game_id, selected_team FROM pickem_picks WHERE entry_id = :eid',
                ['eid' => $entry['entry_id']]
            );

            $correctCount = 0;
            $gradedCount = 0;
            $pendingCount = 0;

            foreach ($picks as $p) {
                $game = $gameMap[$p['game_id']] ?? null;
                if (!$game) {
                    continue;
                }

                if ($game['status'] === 'final') {
                    $gradedCount++;
                    $winningTeam = null;
                    if ($game['home_score'] > $game['away_score']) {
                        $winningTeam = $game['home_team'];
                    } elseif ($game['away_score'] > $game['home_score']) {
                        $winningTeam = $game['away_team'];
                    }

                    if ($winningTeam !== null && $p['selected_team'] === $winningTeam) {
                        $correctCount++;
                    }
                } else {
                    $pendingCount++;
                }
            }

            $delta = null;
            if ($actualMnfTotal !== null && $entry['mnf_total_points_prediction'] !== null) {
                $delta = abs((int) $entry['mnf_total_points_prediction'] - $actualMnfTotal);
            }

            $isPaid = in_array($entry['payment_status'], ['paid', 'exempt'], true);

            $standings[] = [
                'entry_id' => $entry['entry_id'],
                'user_id' => $entry['user_id'],
                'username' => $entry['username'],
                'email' => $entry['email'],
                'payment_status' => $entry['payment_status'],
                'is_paid' => $isPaid,
                'tier' => $isPaid ? 'cash' : 'free',
                'is_cash_eligible' => $isPaid,
                'correct_picks' => $correctCount,
                'total_graded' => $gradedCount,
                'pending_picks' => $pendingCount,
                'total_picks' => count($picks),
                'predicted_mnf' => $entry['mnf_total_points_prediction'],
                'actual_mnf' => $actualMnfTotal,
                'tiebreaker_delta' => $delta,
            ];
        }

        // Sort: 1) correct_picks DESC, 2) tiebreaker_delta ASC (nulls last)
        usort($standings, function ($a, $b) {
            if ($a['correct_picks'] !== $b['correct_picks']) {
                return $b['correct_picks'] <=> $a['correct_picks'];
            }

            if ($a['tiebreaker_delta'] !== null && $b['tiebreaker_delta'] !== null) {
                return $a['tiebreaker_delta'] <=> $b['tiebreaker_delta'];
            }

            if ($a['tiebreaker_delta'] !== null) {
                return -1;
            }
            if ($b['tiebreaker_delta'] !== null) {
                return 1;
            }

            return 0;
        });

        // Assign ranks
        $rank = 1;
        foreach ($standings as $i => &$row) {
            $row['rank'] = $rank++;
        }

        return $standings;
    }

    /**
     * Calculate Pot and Winners for Pick'em
     */
    public function calculateWeeklyPot(int $season, int $week, float $entryStake = 10.0): array
    {
        $standings = $this->getWeeklyStandings($season, $week);

        $verifiedEntries = array_filter($standings, fn ($s) => $s['is_paid']);
        $totalPot = count($verifiedEntries) * $entryStake;

        $winners = [];
        if (!empty($verifiedEntries)) {
            $topScore = reset($verifiedEntries)['correct_picks'];
            $bestDelta = reset($verifiedEntries)['tiebreaker_delta'];

            foreach ($verifiedEntries as $entry) {
                if ($entry['correct_picks'] === $topScore) {
                    if ($bestDelta === null || $entry['tiebreaker_delta'] === $bestDelta) {
                        $winners[] = $entry;
                    }
                }
            }
        }

        $payoutPerWinner = count($winners) > 0 ? round($totalPot / count($winners), 2) : 0.0;

        return [
            'total_pot' => $totalPot,
            'entry_stake' => $entryStake,
            'verified_entries_count' => count($verifiedEntries),
            'total_entries_count' => count($standings),
            'winners' => $winners,
            'payout_per_winner' => $payoutPerWinner,
            'is_split' => count($winners) > 1,
        ];
    }

    /**
     * Evaluate survivor picks for completed games
     */
    public function gradeSurvivorWeek(int $season, int $week): int
    {
        $picks = $this->db->query(
            'SELECT s.id, s.user_id, s.selected_team, s.is_eliminated,
                    g.home_team, g.away_team, g.home_score, g.away_score, g.status
             FROM survivor_picks s
             JOIN games g ON g.season_year = s.season_year 
                         AND g.week_number = s.week_number 
                         AND (g.home_team = s.selected_team OR g.away_team = s.selected_team)
             WHERE s.season_year = :season AND s.week_number = :week AND s.is_eliminated = 0 AND g.status = "final"',
            ['season' => $season, 'week' => $week]
        );

        $eliminated = 0;
        foreach ($picks as $p) {
            $won = false;
            if ($p['selected_team'] === $p['home_team'] && $p['home_score'] > $p['away_score']) {
                $won = true;
            } elseif ($p['selected_team'] === $p['away_team'] && $p['away_score'] > $p['home_score']) {
                $won = true;
            }

            if (!$won) {
                $this->db->execute(
                    'UPDATE survivor_picks SET is_eliminated = 1 WHERE id = :id',
                    ['id' => $p['id']]
                );
                // Also update survivor_entries season record
                $this->db->execute(
                    'UPDATE survivor_entries SET is_eliminated = 1, elimination_week = :week 
                     WHERE user_id = :uid AND season_year = :season AND is_eliminated = 0',
                    ['week' => $week, 'uid' => $p['user_id'], 'season' => $season]
                );
                $eliminated++;
            }
        }

        return $eliminated;
    }

    /**
     * Get season survivor standings
     */
    public function getSurvivorStandings(int $season, ?int $viewingUserId = null, ?int $currentWeek = null): array
    {
        if ($currentWeek === null) {
            $currentWeek = (int) (getenv('NFL_CURRENT_WEEK') ?: 1);
        }

        // Check which games for the current week have already started
        $games = $this->db->query(
            'SELECT home_team, away_team, kickoff_time, status FROM games WHERE season_year = :season AND week_number = :week',
            ['season' => $season, 'week' => $currentWeek]
        );
        $now = time();
        $unlockedTeams = [];
        foreach ($games as $g) {
            if (strtotime($g['kickoff_time']) <= $now || in_array($g['status'], ['in_progress', 'final'], true)) {
                $unlockedTeams[$g['home_team']] = true;
                $unlockedTeams[$g['away_team']] = true;
            }
        }

        $users = $this->db->query(
            'SELECT u.id, u.username, u.email, se.payment_status as survivor_payment_status, 
                    se.is_eliminated as entry_eliminated, se.elimination_week as entry_elim_week
             FROM users u
             LEFT JOIN survivor_entries se ON se.user_id = u.id AND se.season_year = :season
             ORDER BY u.username ASC',
            ['season' => $season]
        );
        $result = [];

        foreach ($users as $user) {
            $picks = $this->db->query(
                'SELECT week_number, selected_team, is_eliminated, payment_status, created_at 
                 FROM survivor_picks 
                 WHERE user_id = :uid AND season_year = :season 
                 ORDER BY week_number ASC',
                ['uid' => $user['id'], 'season' => $season]
            );

            $isPaid = in_array($user['survivor_payment_status'] ?? '', ['paid', 'exempt'], true);
            $isEliminated = false;
            $eliminationWeek = null;
            $teamsUsed = [];
            $isViewer = ($viewingUserId !== null && (int)$user['id'] === $viewingUserId);
            $processedPicks = [];

            foreach ($picks as $p) {
                $pWeek = (int) $p['week_number'];
                $isCurrentWeek = ($pWeek >= $currentWeek);
                $hasStarted = isset($unlockedTeams[$p['selected_team']]);

                // Mask current week pick for other users if game has not kicked off yet
                if ($isCurrentWeek && !$isViewer && !$hasStarted) {
                    $p['display_team'] = '🔒 Hidden';
                    $p['is_hidden'] = true;
                } else {
                    $p['display_team'] = $p['selected_team'];
                    $p['is_hidden'] = false;
                    $teamsUsed[] = $p['selected_team'];
                }

                if ($p['is_eliminated']) {
                    $isEliminated = true;
                    if ($eliminationWeek === null) {
                        $eliminationWeek = $pWeek;
                    }
                }
                $processedPicks[] = $p;
            }

            if (!empty($user['entry_eliminated'])) {
                $isEliminated = true;
                if ($eliminationWeek === null && !empty($user['entry_elim_week'])) {
                    $eliminationWeek = (int) $user['entry_elim_week'];
                }
            }

            $hasEntered = $isPaid || !empty($picks) || !empty($user['survivor_payment_status']);
            if (!$hasEntered) {
                $status = 'not_entered';
            } else {
                $status = $isEliminated ? 'eliminated' : 'alive';
            }

            $result[] = [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'status' => $status,
                'is_paid' => $isPaid,
                'tier' => $isPaid ? 'cash' : 'free',
                'is_cash_eligible' => $isPaid,
                'is_alive' => ($status === 'alive'),
                'is_eliminated' => ($status === 'eliminated'),
                'elimination_week' => $eliminationWeek,
                'picks_count' => count($picks),
                'teams_used' => $teamsUsed,
                'history' => $processedPicks,
            ];
        }

        // Sort: 1) Alive, 2) Eliminated (latest week first), 3) Not Entered
        usort($result, function ($a, $b) {
            $statusOrder = ['alive' => 1, 'eliminated' => 2, 'not_entered' => 3];
            $orderA = $statusOrder[$a['status']] ?? 4;
            $orderB = $statusOrder[$b['status']] ?? 4;

            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            if ($a['status'] === 'eliminated' && $b['status'] === 'eliminated') {
                if ($a['elimination_week'] !== $b['elimination_week']) {
                    return ($b['elimination_week'] ?? 0) <=> ($a['elimination_week'] ?? 0);
                }
            }

            return strcmp($a['username'], $b['username']);
        });

        return $result;
    }

    /**
     * Calculate Pot and Contenders for Survivor Pool
     */
    public function calculateSurvivorPot(int $season, float $entryStake = 10.0): array
    {
        $standings = $this->getSurvivorStandings($season);
        $cashEntries = array_filter($standings, fn ($s) => $s['is_paid']);
        $totalPot = count($cashEntries) * $entryStake;

        $aliveCash = array_filter($cashEntries, fn ($s) => $s['is_alive']);
        $eliminatedCash = array_filter($cashEntries, fn ($s) => $s['is_eliminated']);

        $freeEntries = array_filter($standings, fn ($s) => !$s['is_paid'] && $s['status'] !== 'not_entered');
        $aliveFree = array_filter($freeEntries, fn ($s) => $s['is_alive']);

        return [
            'total_pot' => $totalPot,
            'entry_stake' => $entryStake,
            'cash_entries_count' => count($cashEntries),
            'free_entries_count' => count($freeEntries),
            'total_active_count' => count($cashEntries) + count($freeEntries),
            'alive_cash_count' => count($aliveCash),
            'alive_free_count' => count($aliveFree),
            'active_cash_contenders' => array_values($aliveCash),
            'active_free_contenders' => array_values($aliveFree),
        ];
    }
}
