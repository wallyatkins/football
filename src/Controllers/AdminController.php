<?php
declare(strict_types=1);

namespace WallyFootball\Controllers;

use WallyFootball\Database\Connection;
use WallyFootball\Services\ScoringEngine;
use WallyFootball\Services\SportsDataService;

class AdminController
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

    public function payments(int $season, int $week): void
    {
        $admin = $this->requireAdmin();
        $user = $admin;

        // 1. Weekly Pick'em Entries
        $entries = $this->db->query(
            'SELECT e.*, u.username, u.email,
                    (SELECT COUNT(*) FROM pickem_picks WHERE entry_id = e.id) as pick_count,
                    v.username as verified_by_username
             FROM pickem_entries e
             JOIN users u ON u.id = e.user_id
             LEFT JOIN users v ON v.id = e.payment_verified_by
             WHERE e.season_year = :season AND e.week_number = :week
             ORDER BY e.created_at DESC',
            ['season' => $season, 'week' => $week]
        );

        // 2. Survivor Pool Roster & Payments (Season-long upfront entries)
        $survivorRoster = $this->db->query(
            'SELECT u.id as user_id, u.username, u.email,
                    se.id as survivor_entry_id, 
                    COALESCE(se.payment_status, "unpaid") as survivor_payment_status,
                    COALESCE(se.is_eliminated, 0) as is_eliminated,
                    se.elimination_week,
                    se.payment_verified_at,
                    v.username as verified_by_username,
                    (SELECT selected_team FROM survivor_picks WHERE user_id = u.id AND season_year = :season AND week_number = :week) as current_week_pick,
                    (SELECT COUNT(*) FROM survivor_picks WHERE user_id = u.id AND season_year = :season) as total_weeks_picked
             FROM users u
             LEFT JOIN survivor_entries se ON se.user_id = u.id AND se.season_year = :season
             LEFT JOIN users v ON v.id = se.payment_verified_by
             ORDER BY 
                CASE WHEN se.payment_status IN ("paid", "exempt") THEN 1 ELSE 2 END,
                u.username ASC',
            ['season' => $season, 'week' => $week]
        );

        // 3. Active Weekly Tiebreaker Game
        $tiebreakerGame = $this->db->queryOne(
            'SELECT * FROM games WHERE season_year = :season AND week_number = :week AND is_mnf = 1',
            ['season' => $season, 'week' => $week]
        );

        $title = "Commissioner Dashboard — Wally's NFL Pool";
        require dirname(__DIR__, 2) . '/templates/admin/payments.php';
    }

    public function randomizeTiebreaker(): void
    {
        $this->requireAdmin();

        $season = (int) ($_POST['season_year'] ?? date('Y'));
        $week = (int) ($_POST['week_number'] ?? 1);

        $games = $this->db->query(
            'SELECT id, home_team, away_team FROM games WHERE season_year = :season AND week_number = :week',
            ['season' => $season, 'week' => $week]
        );

        if (!empty($games)) {
            $currentId = $this->db->queryValue(
                'SELECT id FROM games WHERE season_year = :season AND week_number = :week AND is_mnf = 1',
                ['season' => $season, 'week' => $week]
            );

            $candidates = array_values(array_filter($games, fn($g) => $g['id'] != $currentId));
            if (empty($candidates)) {
                $candidates = $games;
            }

            $picked = $candidates[array_rand($candidates)];
            $selectedId = (int) $picked['id'];

            $this->db->execute(
                'UPDATE games SET is_mnf = 0 WHERE season_year = :season AND week_number = :week',
                ['season' => $season, 'week' => $week]
            );
            $this->db->execute(
                'UPDATE games SET is_mnf = 1 WHERE id = :id',
                ['id' => $selectedId]
            );

            $_SESSION['flash'] = "🎲 Re-rolled tiebreaker game for Week {$week}: {$picked['away_team']} @ {$picked['home_team']}.";
        } else {
            $_SESSION['error'] = "No games found for Week {$week} to randomize.";
        }

        header("Location: /admin/payments?week={$week}&season={$season}");
        exit;
    }

    public function togglePayment(): void
    {
        $admin = $this->requireAdmin();

        $id = (int) ($_POST['id'] ?? 0);
        $newStatus = strtolower(trim((string) ($_POST['status'] ?? 'paid')));

        if (!in_array($newStatus, ['paid', 'pending', 'exempt'], true) || $id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            exit;
        }

        $this->db->execute(
            'UPDATE pickem_entries SET 
                payment_status = :status, 
                payment_verified_at = CASE WHEN :status = "paid" THEN CURRENT_TIMESTAMP ELSE NULL END,
                payment_verified_by = CASE WHEN :status = "paid" THEN :admin_id ELSE NULL END
             WHERE id = :id',
            ['status' => $newStatus, 'admin_id' => $admin['id'], 'id' => $id]
        );

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'status' => $newStatus]);
            exit;
        }

        $season = (int) ($_POST['season_year'] ?? date('Y'));
        $week = (int) ($_POST['week_number'] ?? 1);
        $_SESSION['flash'] = "Payment status updated to {$newStatus}.";
        header("Location: /admin/payments?week={$week}&season={$season}");
        exit;
    }

    public function toggleLock(): void
    {
        $this->requireAdmin();

        $id = (int) ($_POST['id'] ?? 0);
        $lockState = (int) ($_POST['locked'] ?? 0);
        $season = (int) ($_POST['season_year'] ?? date('Y'));
        $week = (int) ($_POST['week_number'] ?? 1);

        if ($id > 0) {
            $this->db->execute(
                'UPDATE pickem_entries SET is_locked = :locked, locked_at = CASE WHEN :locked = 1 THEN CURRENT_TIMESTAMP ELSE NULL END WHERE id = :id',
                ['locked' => $lockState, 'id' => $id]
            );
            $_SESSION['flash'] = ($lockState === 1) ? 'Picks locked for this user.' : 'Picks UNLOCKED! User can now modify their picks.';
        }

        header("Location: /admin/payments?week={$week}&season={$season}");
        exit;
    }

    public function toggleSurvivor(): void
    {
        $admin = $this->requireAdmin();

        $userId = (int) ($_POST['user_id'] ?? 0);
        $season = (int) ($_POST['season_year'] ?? date('Y'));
        $week = (int) ($_POST['week_number'] ?? 1);
        $newStatus = strtolower(trim((string) ($_POST['status'] ?? 'paid')));

        if ($userId <= 0 || !in_array($newStatus, ['paid', 'unpaid', 'exempt'], true)) {
            header("Location: /admin/payments?week={$week}&season={$season}#survivor");
            exit;
        }

        $entry = $this->db->queryOne(
            'SELECT id FROM survivor_entries WHERE user_id = :uid AND season_year = :season',
            ['uid' => $userId, 'season' => $season]
        );

        if ($entry) {
            $this->db->execute(
                'UPDATE survivor_entries SET 
                    payment_status = :status,
                    payment_verified_at = CASE WHEN :status = "paid" THEN CURRENT_TIMESTAMP ELSE NULL END,
                    payment_verified_by = CASE WHEN :status = "paid" THEN :admin_id ELSE NULL END
                 WHERE id = :id',
                ['status' => $newStatus, 'admin_id' => $admin['id'], 'id' => $entry['id']]
            );
        } else {
            $this->db->execute(
                'INSERT INTO survivor_entries (user_id, season_year, payment_status, is_eliminated, payment_verified_at, payment_verified_by)
                 VALUES (:uid, :season, :status, 0, CASE WHEN :status = "paid" THEN CURRENT_TIMESTAMP ELSE NULL END, :admin_id)',
                ['uid' => $userId, 'season' => $season, 'status' => $newStatus, 'admin_id' => $admin['id']]
            );
        }

        $_SESSION['flash'] = "Survivor $10 payment status updated to {$newStatus}.";
        header("Location: /admin/payments?week={$week}&season={$season}#survivor");
        exit;
    }

    public function toggleSurvivorElimination(): void
    {
        $this->requireAdmin();

        $userId = (int) ($_POST['user_id'] ?? 0);
        $season = (int) ($_POST['season_year'] ?? date('Y'));
        $week = (int) ($_POST['week_number'] ?? 1);
        $eliminate = (int) ($_POST['eliminate'] ?? 1);

        if ($userId > 0) {
            if ($eliminate === 1) {
                $this->db->execute(
                    'UPDATE survivor_entries SET is_eliminated = 1, elimination_week = :week WHERE user_id = :uid AND season_year = :season',
                    ['week' => $week, 'uid' => $userId, 'season' => $season]
                );
                $_SESSION['flash'] = "User eliminated from Survivor in Week {$week}.";
            } else {
                $this->db->execute(
                    'UPDATE survivor_entries SET is_eliminated = 0, elimination_week = NULL WHERE user_id = :uid AND season_year = :season',
                    ['uid' => $userId, 'season' => $season]
                );
                $_SESSION['flash'] = "User revived in Survivor pool!";
            }
        }

        header("Location: /admin/payments?week={$week}&season={$season}#survivor");
        exit;
    }

    public function gradeSurvivor(): void
    {
        $this->requireAdmin();

        $season = (int) ($_POST['season_year'] ?? date('Y'));
        $week = (int) ($_POST['week_number'] ?? 1);

        $eliminatedCount = $this->scoring->gradeSurvivorWeek($season, $week);
        $_SESSION['flash'] = "Survivor Week {$week} graded! {$eliminatedCount} player(s) eliminated based on final game scores.";

        header("Location: /admin/payments?week={$week}&season={$season}#survivor");
        exit;
    }

    public function syncSchedule(): void
    {
        $this->requireAdmin();

        $season = (int) ($_POST['season_year'] ?? date('Y'));
        $week = (int) ($_POST['week_number'] ?? 1);

        $result = $this->sports->syncWeek($season, $week);
        $_SESSION['flash'] = "Sync completed! Inserted: {$result['inserted']}, Updated: {$result['updated']}.";

        header("Location: /admin/payments?week={$week}&season={$season}");
        exit;
    }

    public function resetPicks(): void
    {
        $this->requireAdmin();

        $entryId = (int) ($_POST['entry_id'] ?? 0);
        $season = (int) ($_POST['season_year'] ?? date('Y'));
        $week = (int) ($_POST['week_number'] ?? 1);

        if ($entryId > 0) {
            $entry = $this->db->queryOne('SELECT user_id, week_number FROM pickem_entries WHERE id = :id', ['id' => $entryId]);
            if ($entry) {
                $this->db->execute('DELETE FROM pickem_picks WHERE entry_id = :id', ['id' => $entryId]);
                $this->db->execute('DELETE FROM pickem_entries WHERE id = :id', ['id' => $entryId]);
                $_SESSION['flash'] = "Picks and entry reset successfully for Week {$week}. Entrant can now draft fresh picks.";
            }
        }

        header("Location: /admin/payments?week={$week}&season={$season}");
        exit;
    }

    private function requireAdmin(): array
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || !in_array($user['role'] ?? '', ['admin', 'commissioner'], true)) {
            http_response_code(403);
            echo "Access Denied: Commissioner or Administrator role required.";
            exit;
        }
        return $user;
    }
}
