<?php
declare(strict_types=1);

namespace WallyFootball\Controllers;

use WallyFootball\Database\Connection;
use WallyFootball\Services\SportsDataService;

class AdminController
{
    private Connection $db;
    private SportsDataService $sports;

    public function __construct(?Connection $db = null, ?SportsDataService $sports = null)
    {
        $this->db = $db ?? Connection::getInstance();
        $this->sports = $sports ?? new SportsDataService($this->db);
    }

    public function payments(int $season, int $week): void
    {
        $admin = $this->requireAdmin();

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

        $survivorEntries = $this->db->query(
            'SELECT s.*, u.username, u.email
             FROM survivor_picks s
             JOIN users u ON u.id = s.user_id
             WHERE s.season_year = :season AND s.week_number = :week
             ORDER BY s.created_at DESC',
            ['season' => $season, 'week' => $week]
        );

        $title = "Admin Payment Dashboard — Wally's NFL Pool";
        require dirname(__DIR__, 2) . '/templates/admin/payments.php';
    }

    public function togglePayment(): void
    {
        $admin = $this->requireAdmin();

        $type = $_POST['type'] ?? 'pickem'; // 'pickem' or 'survivor'
        $id = (int) ($_POST['id'] ?? 0);
        $newStatus = strtolower(trim((string) ($_POST['status'] ?? 'paid')));

        if (!in_array($newStatus, ['paid', 'pending', 'exempt'], true) || $id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            exit;
        }

        if ($type === 'pickem') {
            $this->db->execute(
                'UPDATE pickem_entries SET 
                    payment_status = :status, 
                    payment_verified_at = CASE WHEN :status = "paid" THEN CURRENT_TIMESTAMP ELSE NULL END,
                    payment_verified_by = CASE WHEN :status = "paid" THEN :admin_id ELSE NULL END
                 WHERE id = :id',
                ['status' => $newStatus, 'admin_id' => $admin['id'], 'id' => $id]
            );
        } else {
            $this->db->execute(
                'UPDATE survivor_picks SET payment_status = :status WHERE id = :id',
                ['status' => $newStatus, 'id' => $id]
            );
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'status' => $newStatus]);
            exit;
        }

        $season = (int) ($_POST['season_year'] ?? date('Y'));
        $week = (int) ($_POST['week_number'] ?? 1);
        header("Location: /admin/payments?week={$week}&season={$season}");
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

    private function requireAdmin(): array
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || ($user['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo "Access Denied: Administrator role required.";
            exit;
        }
        return $user;
    }
}
