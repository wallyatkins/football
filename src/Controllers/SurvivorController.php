<?php

declare(strict_types=1);

namespace WallyFootball\Controllers;

use WallyFootball\Database\Connection;
use WallyFootball\Services\NotificationService;
use WallyFootball\Services\ScoringEngine;
use WallyFootball\Services\SportsDataService;

class SurvivorController
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

        // Check survivor upfront entry status
        $survivorEntry = $this->db->queryOne(
            'SELECT * FROM survivor_entries WHERE user_id = :uid AND season_year = :season',
            ['uid' => $user['id'], 'season' => $season]
        );

        $isPaid = $survivorEntry && in_array($survivorEntry['payment_status'], ['paid', 'exempt'], true);
        $isEliminated = false;
        $eliminationWeek = null;

        // Check if eliminated
        $entryRevived = ($survivorEntry && isset($survivorEntry['is_eliminated']) && (int) $survivorEntry['is_eliminated'] === 0);
        if ($entryRevived) {
            // Commissioner has revived this user. Auto-repair any stale pick flags.
            $this->db->execute(
                'UPDATE survivor_picks SET is_eliminated = 0 WHERE user_id = :uid AND season_year = :season',
                ['uid' => $user['id'], 'season' => $season]
            );
            $eliminatedRecord = null;
            $isEliminated = false;
            $eliminationWeek = null;
            $survivorStatus = 'alive';
        } else {
            $eliminatedRecord = $this->db->queryOne(
                'SELECT week_number FROM survivor_picks WHERE user_id = :uid AND season_year = :season AND is_eliminated = 1 LIMIT 1',
                ['uid' => $user['id'], 'season' => $season]
            );
            if ($eliminatedRecord || !empty($survivorEntry['is_eliminated'])) {
                $isEliminated = true;
                $eliminationWeek = $eliminatedRecord ? (int) $eliminatedRecord['week_number'] : (int) ($survivorEntry['elimination_week'] ?? 1);
                $survivorStatus = 'eliminated';
            } else {
                $survivorStatus = 'alive';
            }
        }

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

        $this->sports->syncIfNeeded($season, $week);

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

        // Deduplicate in memory as an ironclad safety check against duplicate matchups
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

        $now = time();
        $openTeamsCount = 0;
        foreach ($games as $idx => $g) {
            $kickoff = strtotime($g['kickoff_time']);
            $isGameStarted = ($kickoff <= $now || in_array($g['status'], ['in_progress', 'final'], true));
            $games[$idx]['is_locked'] = $isGameStarted;
            $games[$idx]['home_used'] = in_array($g['home_team'], $usedTeams, true);
            $games[$idx]['away_used'] = in_array($g['away_team'], $usedTeams, true);
            if (!$isGameStarted) {
                if (!$games[$idx]['home_used']) {
                    $openTeamsCount++;
                }
                if (!$games[$idx]['away_used']) {
                    $openTeamsCount++;
                }
            }
        }

        // Current pick lock status: locks when that team's game kicks off
        $isPickLocked = false;
        if (!empty($currentPick['selected_team'])) {
            $pickedTeam = $currentPick['selected_team'];
            foreach ($games as $g) {
                if ($g['home_team'] === $pickedTeam || $g['away_team'] === $pickedTeam) {
                    $isPickLocked = (strtotime($g['kickoff_time']) <= $now || in_array($g['status'], ['in_progress', 'final'], true));
                    break;
                }
            }
        }

        $isSurvivorClosed = ($openTeamsCount === 0);

        $venmoUrl = 'https://account.venmo.com/u/WallyAtkins';
        $payPalUrl = 'https://paypal.me/WallyAtkins';
        $cashAppUrl = 'https://cash.app/$WallyAtkins';

        $title = "Week {$week} Survivor — Wally's NFL Pool";
        require dirname(__DIR__, 2) . '/templates/survivor/index.php';
    }

    public function autoSave(): void
    {
        header('Content-Type: application/json');

        if (empty($_SESSION['user']['id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Please log in to save your survivor pick.']);
            exit;
        }
        $user = $_SESSION['user'];

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $season = (int) ($input['season_year'] ?? date('Y'));
        $week = (int) ($input['week_number'] ?? 1);
        $selectedTeam = strtoupper(trim((string) ($input['selected_team'] ?? '')));

        if (!$selectedTeam) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No team selected.']);
            exit;
        }

        // Check if game exists in week and has not kicked off yet
        $game = $this->db->queryOne(
            'SELECT kickoff_time FROM games WHERE season_year = :season AND week_number = :week AND (home_team = :team OR away_team = :team)',
            ['season' => $season, 'week' => $week, 'team' => $selectedTeam]
        );
        if (!$game) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Game not found for {$selectedTeam} in Week {$week}."]);
            exit;
        }
        if (strtotime($game['kickoff_time']) <= time()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "The game for {$selectedTeam} has already kicked off and cannot be selected."]);
            exit;
        }

        // Initialize survivor entry if needed
        $survivorEntry = $this->db->queryOne(
            'SELECT * FROM survivor_entries WHERE user_id = :uid AND season_year = :season',
            ['uid' => $user['id'], 'season' => $season]
        );
        if (!$survivorEntry) {
            $this->db->execute(
                "INSERT INTO survivor_entries (user_id, season_year, payment_status, is_eliminated)
                 VALUES (:uid, :season, 'unpaid', 0)",
                ['uid' => $user['id'], 'season' => $season]
            );
            $isPaid = false;
        } else {
            $isPaid = in_array($survivorEntry['payment_status'], ['paid', 'exempt'], true);
        }

        // Check elimination
        if (!empty($survivorEntry['is_eliminated'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "You have been eliminated from this season's Survivor pool."]);
            exit;
        }

        // Check if previously used in another week
        $previouslyUsed = $this->db->queryOne(
            'SELECT week_number FROM survivor_picks WHERE user_id = :uid AND season_year = :season AND selected_team = :team AND week_number != :week',
            ['uid' => $user['id'], 'season' => $season, 'team' => $selectedTeam, 'week' => $week]
        );
        if ($previouslyUsed) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "You already used {$selectedTeam} in Week {$previouslyUsed['week_number']}!"]);
            exit;
        }

        // Check if existing pick has already kicked off (prevent modifying already-started pick)
        $existing = $this->db->queryOne(
            'SELECT id, selected_team FROM survivor_picks WHERE user_id = :uid AND season_year = :season AND week_number = :week',
            ['uid' => $user['id'], 'season' => $season, 'week' => $week]
        );
        if ($existing && !empty($existing['selected_team'])) {
            $priorGame = $this->db->queryOne(
                'SELECT kickoff_time FROM games WHERE season_year = :season AND week_number = :week AND (home_team = :team OR away_team = :team)',
                ['season' => $season, 'week' => $week, 'team' => $existing['selected_team']]
            );
            if ($priorGame && strtotime($priorGame['kickoff_time']) <= time()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "Your previous selection ({$existing['selected_team']}) has already kicked off and cannot be changed."]);
                exit;
            }
        }

        $paymentStatus = $isPaid ? 'paid' : 'unpaid';
        if ($existing) {
            $this->db->execute(
                'UPDATE survivor_picks SET selected_team = :team, created_at = CURRENT_TIMESTAMP WHERE id = :id',
                ['team' => $selectedTeam, 'id' => $existing['id']]
            );
        } else {
            $this->db->execute(
                "INSERT INTO survivor_picks (user_id, season_year, week_number, selected_team, is_eliminated, payment_status)
                 VALUES (:uid, :season, :week, :team, 0, :ps)",
                ['uid' => $user['id'], 'season' => $season, 'week' => $week, 'team' => $selectedTeam, 'ps' => $paymentStatus]
            );
        }

        echo json_encode([
            'success' => true,
            'message' => "Survivor pick for Week {$week} saved as {$selectedTeam}",
            'selected_team' => $selectedTeam,
            'week_number' => $week,
        ]);
        exit;
    }

    public function burnHandicap(): void
    {
        header('Content-Type: application/json');

        if (empty($_SESSION['user']['id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Please log in to register handicap picks.']);
            return;
        }
        $user = $_SESSION['user'];

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $season = (int) ($input['season_year'] ?? date('Y'));
        $currentWeek = (int) ($input['current_week'] ?? ($input['week_number'] ?? 1));
        $burnedTeam = strtoupper(trim((string) ($input['burned_team'] ?? ($input['selected_team'] ?? ''))));

        if (!$burnedTeam) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No team specified for elimination.']);
            return;
        }

        // Initialize survivor entry if needed
        $survivorEntry = $this->db->queryOne(
            'SELECT * FROM survivor_entries WHERE user_id = :uid AND season_year = :season',
            ['uid' => $user['id'], 'season' => $season]
        );
        if (!$survivorEntry) {
            $this->db->execute(
                "INSERT INTO survivor_entries (user_id, season_year, payment_status, is_eliminated)
                 VALUES (:uid, :season, 'unpaid', 0)",
                ['uid' => $user['id'], 'season' => $season]
            );
            $isPaid = false;
        } else {
            $isPaid = in_array($survivorEntry['payment_status'], ['paid', 'exempt'], true);
            if (!empty($survivorEntry['is_eliminated'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "You have been eliminated from this season's Survivor pool."]);
                return;
            }
        }
        $paymentStatus = $isPaid ? 'paid' : 'unpaid';

        // Check if team already used by user this season
        $previouslyUsed = $this->db->queryOne(
            'SELECT week_number FROM survivor_picks WHERE user_id = :uid AND season_year = :season AND selected_team = :team',
            ['uid' => $user['id'], 'season' => $season, 'team' => $burnedTeam]
        );
        if ($previouslyUsed) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "You already used {$burnedTeam} in Week {$previouslyUsed['week_number']}!"]);
            return;
        }

        // Find existing survivor picks for previous weeks
        $existingPicks = $this->db->query(
            'SELECT week_number FROM survivor_picks WHERE user_id = :uid AND season_year = :season ORDER BY week_number ASC',
            ['uid' => $user['id'], 'season' => $season]
        );
        $usedWeeks = array_map('intval', array_column($existingPicks, 'week_number'));

        // Identify target missed week (< currentWeek)
        $targetWeek = null;
        if (!empty($input['target_week'])) {
            $reqWeek = (int) $input['target_week'];
            if ($reqWeek < $currentWeek && !in_array($reqWeek, $usedWeeks, true)) {
                $targetWeek = $reqWeek;
            }
        }

        if ($targetWeek === null) {
            for ($w = 1; $w < $currentWeek; $w++) {
                if (!in_array($w, $usedWeeks, true)) {
                    $targetWeek = $w;
                    break;
                }
            }
        }

        if ($targetWeek === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No remaining missed weeks require a handicap elimination.']);
            return;
        }

        // Insert handicap pick
        $this->db->execute(
            "INSERT INTO survivor_picks (user_id, season_year, week_number, selected_team, is_eliminated, payment_status)
             VALUES (:uid, :season, :week, :team, 0, :ps)",
            ['uid' => $user['id'], 'season' => $season, 'week' => $targetWeek, 'team' => $burnedTeam, 'ps' => $paymentStatus]
        );

        // Fetch updated list of burned teams and remaining missed weeks
        $updatedPicks = $this->db->query(
            'SELECT week_number, selected_team FROM survivor_picks WHERE user_id = :uid AND season_year = :season AND week_number < :curWeek ORDER BY week_number ASC',
            ['uid' => $user['id'], 'season' => $season, 'curWeek' => $currentWeek]
        );
        $allBurned = array_column($updatedPicks, 'selected_team');
        $burnedWeeks = array_map('intval', array_column($updatedPicks, 'week_number'));
        $remainingWeeks = [];
        for ($w = 1; $w < $currentWeek; $w++) {
            if (!in_array($w, $burnedWeeks, true)) {
                $remainingWeeks[] = $w;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Eliminated {$burnedTeam} for Week {$targetWeek} handicap.",
            'burned_team' => $burnedTeam,
            'week_number' => $targetWeek,
            'burned_teams' => $allBurned,
            'remaining_missed_weeks' => $remainingWeeks,
        ]);
        return;
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

        // 1. Verify chosen game exists in this week and has NOT kicked off yet
        $game = $this->db->queryOne(
            'SELECT kickoff_time FROM games WHERE season_year = :season AND week_number = :week AND (home_team = :team OR away_team = :team)',
            ['season' => $season, 'week' => $week, 'team' => $selectedTeam]
        );

        if (!$game) {
            $_SESSION['error'] = "The game featuring {$selectedTeam} was not found for Week {$week}.";
            header("Location: /survivor?week={$week}&season={$season}");
            exit;
        }

        if (strtotime($game['kickoff_time']) <= time()) {
            $_SESSION['error'] = "The game featuring {$selectedTeam} has already kicked off and cannot be selected.";
            header("Location: /survivor?week={$week}&season={$season}");
            exit;
        }

        // 2. Get or initialize survivor entry
        $survivorEntry = $this->db->queryOne(
            'SELECT * FROM survivor_entries WHERE user_id = :uid AND season_year = :season',
            ['uid' => $user['id'], 'season' => $season]
        );

        if (!$survivorEntry) {
            $this->db->execute(
                "INSERT INTO survivor_entries (user_id, season_year, payment_status, is_eliminated)
                 VALUES (:uid, :season, 'unpaid', 0)",
                ['uid' => $user['id'], 'season' => $season]
            );
            $isPaid = false;
        } else {
            $isPaid = in_array($survivorEntry['payment_status'], ['paid', 'exempt'], true);
        }

        // 3. Verify user is not already eliminated
        $entryRevived = ($survivorEntry && isset($survivorEntry['is_eliminated']) && (int) $survivorEntry['is_eliminated'] === 0);
        if (!$entryRevived) {
            $eliminated = $this->db->queryOne(
                'SELECT id FROM survivor_picks WHERE user_id = :uid AND season_year = :season AND is_eliminated = 1',
                ['uid' => $user['id'], 'season' => $season]
            );
            if ($eliminated || !empty($survivorEntry['is_eliminated'])) {
                $_SESSION['error'] = 'You have already been eliminated from this season\'s Survivor pool.';
                header("Location: /survivor?week={$week}&season={$season}");
                exit;
            }
        }

        // 4. Verify team has NOT been used in an earlier week (One and Done rule across weeks)
        $previouslyUsed = $this->db->queryOne(
            'SELECT week_number FROM survivor_picks WHERE user_id = :uid AND season_year = :season AND selected_team = :team AND week_number != :week',
            ['uid' => $user['id'], 'season' => $season, 'team' => $selectedTeam, 'week' => $week]
        );
        if ($previouslyUsed) {
            $_SESSION['error'] = "You already used {$selectedTeam} in Week {$previouslyUsed['week_number']}!";
            header("Location: /survivor?week={$week}&season={$season}");
            exit;
        }

        // 5. Check existing pick for this week (Allow modifying pick before that game kicks off!)
        $existing = $this->db->queryOne(
            'SELECT id, selected_team FROM survivor_picks WHERE user_id = :uid AND season_year = :season AND week_number = :week',
            ['uid' => $user['id'], 'season' => $season, 'week' => $week]
        );

        if ($existing && !empty($existing['selected_team'])) {
            $priorGame = $this->db->queryOne(
                'SELECT kickoff_time FROM games WHERE season_year = :season AND week_number = :week AND (home_team = :team OR away_team = :team)',
                ['season' => $season, 'week' => $week, 'team' => $existing['selected_team']]
            );
            if ($priorGame && strtotime($priorGame['kickoff_time']) <= time()) {
                $_SESSION['error'] = "Your previous selection ({$existing['selected_team']}) has already kicked off and cannot be changed.";
                header("Location: /survivor?week={$week}&season={$season}");
                exit;
            }
        }

        $paymentStatus = $isPaid ? 'paid' : 'unpaid';

        if ($existing) {
            $this->db->execute(
                'UPDATE survivor_picks SET selected_team = :team, created_at = CURRENT_TIMESTAMP WHERE id = :id',
                ['team' => $selectedTeam, 'id' => $existing['id']]
            );
            $actionWord = 'updated to';
        } else {
            $this->db->execute(
                "INSERT INTO survivor_picks (user_id, season_year, week_number, selected_team, is_eliminated, payment_status)
                 VALUES (:uid, :season, :week, :team, 0, :ps)",
                ['uid' => $user['id'], 'season' => $season, 'week' => $week, 'team' => $selectedTeam, 'ps' => $paymentStatus]
            );
            $actionWord = 'saved as';
        }

        try {
            $this->notifier->notifySurvivorPickSubmitted(
                $user['username'] ?? 'Player',
                $week,
                $season,
                $selectedTeam
            );
        } catch (\Throwable) {
            // Notification failures should never disrupt player experience
        }

        $_SESSION['flash'] = "Your Survivor selection for Week {$week} has been {$actionWord} {$selectedTeam}! The selection locks when {$selectedTeam}'s game kicks off.";
        header("Location: /survivor?week={$week}&season={$season}");
        exit;
    }

    public function standings(int $season): void
    {
        $user = $_SESSION['user'] ?? null;
        $viewingUserId = !empty($user['id']) ? (int) $user['id'] : null;
        $currentWeek = (int) (getenv('NFL_CURRENT_WEEK') ?: 0);
        if ($currentWeek <= 0) {
            $active = $this->db->queryValue(
                'SELECT MIN(week_number) FROM games WHERE season_year = :s AND status != "final"',
                ['s' => $season]
            );
            $currentWeek = ($active && (int)$active > 0) ? (int)$active : 1;
        }

        $this->sports->syncIfNeeded($season, $currentWeek);
        $standings = $this->scoring->getSurvivorStandings($season, $viewingUserId, $currentWeek);
        $pot = $this->scoring->calculateSurvivorPot($season);

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
