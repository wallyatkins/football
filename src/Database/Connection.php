<?php
declare(strict_types=1);

namespace WallyFootball\Database;

use PDO;
use PDOException;

class Connection
{
    private static ?Connection $instance = null;
    private PDO $pdo;

    private function __construct(?string $sqlitePath = null)
    {
        $driver = getenv('DB_DRIVER') ?: 'sqlite';

        if ($driver === 'pgsql') {
            $host = getenv('DB_HOST') ?: 'localhost';
            $port = getenv('DB_PORT') ?: '5432';
            $db   = getenv('DB_NAME') ?: 'multili2_football';
            $user = getenv('DB_USER') ?: 'multili2_football';
            $pass = getenv('DB_PASS') ?: '';

            $dsn = "pgsql:host={$host};port={$port};dbname={$db}";
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } else {
            $path = $sqlitePath ?? (getenv('DB_PATH') ?: dirname(__DIR__, 2) . '/data/football.sqlite');
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $this->pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // SQLite optimizations & constraints
            $this->pdo->exec('PRAGMA journal_mode = WAL;');
            $this->pdo->exec('PRAGMA synchronous = NORMAL;');
            $this->pdo->exec('PRAGMA foreign_keys = ON;');
            $this->pdo->exec('PRAGMA busy_timeout = 5000;');
        }

        $this->ensureSchema();
    }

    public static function getInstance(?string $sqlitePath = null): self
    {
        if (self::$instance === null || $sqlitePath !== null) {
            self::$instance = new self($sqlitePath);
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function beginTransaction(): void
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function queryValue(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $val = $stmt->fetchColumn();
        return $val === false ? null : $val;
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function insert(string $sql, array $params = []): int|string
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->pdo->lastInsertId();
    }

    private function ensureSchema(): void
    {
        $migrationFile = dirname(__DIR__, 2) . '/db/migrations/001_initial_schema.sql';
        if (file_exists($migrationFile)) {
            $sql = file_get_contents($migrationFile);
            if ($sql) {
                // Check if users table exists
                try {
                    $this->pdo->query('SELECT 1 FROM users LIMIT 1');
                } catch (\Throwable) {
                    $this->pdo->exec($sql);
                }
            }
        }

        // Ensure survivor_entries table exists
        try {
            $this->pdo->query('SELECT 1 FROM survivor_entries LIMIT 1');
        } catch (\Throwable) {
            $survivorMigration = dirname(__DIR__, 2) . '/db/migrations/002_survivor_and_locks.sql';
            if (file_exists($survivorMigration)) {
                $sql = file_get_contents($survivorMigration);
                if ($sql) {
                    $this->pdo->exec($sql);
                }
            }
        }

        // Ensure pickem_entries has is_locked and locked_at columns
        try {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $cols = array_column($this->pdo->query("PRAGMA table_info(pickem_entries)")->fetchAll(PDO::FETCH_ASSOC), "name");
                if (!in_array('is_locked', $cols, true)) {
                    $this->pdo->exec("ALTER TABLE pickem_entries ADD COLUMN is_locked BOOLEAN DEFAULT 0;");
                }
                if (!in_array('locked_at', $cols, true)) {
                    $this->pdo->exec("ALTER TABLE pickem_entries ADD COLUMN locked_at TIMESTAMP WITH TIME ZONE NULL;");
                }
            } else {
                // Postgres
                $this->pdo->exec("ALTER TABLE pickem_entries ADD COLUMN IF NOT EXISTS is_locked BOOLEAN DEFAULT FALSE;");
                $this->pdo->exec("ALTER TABLE pickem_entries ADD COLUMN IF NOT EXISTS locked_at TIMESTAMP WITH TIME ZONE NULL;");
            }
        } catch (\Throwable) {
            // Ignore if table doesn't exist yet
        }

        // Deduplicate and normalize games in database
        try {
            $aliases = ['LA' => 'LAR', 'STL' => 'LAR', 'WSH' => 'WAS', 'JAC' => 'JAX', 'OAK' => 'LV', 'SD' => 'LAC'];
            foreach ($aliases as $old => $new) {
                $this->pdo->exec("UPDATE games SET home_team = '{$new}' WHERE home_team = '{$old}'");
                $this->pdo->exec("UPDATE games SET away_team = '{$new}' WHERE away_team = '{$old}'");
                $this->pdo->exec("UPDATE pickem_picks SET selected_team = '{$new}' WHERE selected_team = '{$old}'");
                $this->pdo->exec("UPDATE survivor_picks SET selected_team = '{$new}' WHERE selected_team = '{$old}'");
            }

            $games = $this->pdo->query("SELECT id, season_year, week_number, home_team, away_team FROM games ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
            $seenMatchups = [];
            $seenTeams = [];
            $toDelete = [];
            $idRemap = [];

            foreach ($games as $g) {
                $teams = [$g['home_team'], $g['away_team']];
                sort($teams);
                $matchupKey = $g['season_year'] . '_' . $g['week_number'] . '_' . $teams[0] . '_' . $teams[1];
                $teamAKey = $g['season_year'] . '_' . $g['week_number'] . '_' . $teams[0];
                $teamBKey = $g['season_year'] . '_' . $g['week_number'] . '_' . $teams[1];

                if (isset($seenMatchups[$matchupKey])) {
                    $dupId = (int) $g['id'];
                    $toDelete[] = $dupId;
                    $idRemap[$dupId] = $seenMatchups[$matchupKey];
                } elseif (isset($seenTeams[$teamAKey]) || isset($seenTeams[$teamBKey])) {
                    $dupId = (int) $g['id'];
                    $toDelete[] = $dupId;
                    $primary = $seenTeams[$teamAKey] ?? ($seenTeams[$teamBKey] ?? null);
                    if ($primary) {
                        $idRemap[$dupId] = $primary;
                    }
                } else {
                    $seenMatchups[$matchupKey] = (int) $g['id'];
                    $seenTeams[$teamAKey] = (int) $g['id'];
                    $seenTeams[$teamBKey] = (int) $g['id'];
                }
            }

            foreach ($idRemap as $dupId => $primaryId) {
                $picks = $this->pdo->query("SELECT id, entry_id FROM pickem_picks WHERE game_id = {$dupId}")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($picks as $p) {
                    $hasPrimary = $this->pdo->query("SELECT id FROM pickem_picks WHERE entry_id = {$p['entry_id']} AND game_id = {$primaryId}")->fetch();
                    if (!$hasPrimary) {
                        $this->pdo->exec("UPDATE pickem_picks SET game_id = {$primaryId} WHERE id = {$p['id']}");
                    } else {
                        $this->pdo->exec("DELETE FROM pickem_picks WHERE id = {$p['id']}");
                    }
                }
            }

            if (!empty($toDelete)) {
                $delList = implode(',', $toDelete);
                $this->pdo->exec("DELETE FROM games WHERE id IN ({$delList})");
            }

            // Ensure each week has a randomly assigned tiebreaker game (not Monday Night Football)
            $weeks = $this->pdo->query("SELECT DISTINCT season_year, week_number FROM games")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($weeks as $w) {
                $season = (int) $w['season_year'];
                $week = (int) $w['week_number'];

                $weekGames = $this->pdo->query(
                    "SELECT id, kickoff_time, is_mnf, home_team, away_team FROM games WHERE season_year = {$season} AND week_number = {$week} ORDER BY id ASC"
                )->fetchAll(PDO::FETCH_ASSOC);
                if (empty($weekGames)) {
                    continue;
                }

                $tiebreakers = array_filter($weekGames, fn($g) => !empty($g['is_mnf']));
                $count = count($tiebreakers);

                $isLegacyMnf = false;
                if ($count === 1) {
                    $current = reset($tiebreakers);
                    $kickoff = new \DateTimeImmutable($current['kickoff_time']);
                    $kickoffEt = $kickoff->setTimezone(new \DateTimeZone('America/New_York'));
                    if (($kickoffEt->format('N') === '1' && (int)$kickoffEt->format('G') >= 17) || ($current['home_team'] === 'SF' && $current['away_team'] === 'NYJ')) {
                        $isLegacyMnf = true;
                    }
                }

                if ($count === 0 || $count > 1 || $isLegacyMnf) {
                    $seedStr = "random_tiebreaker_{$season}_{$week}";
                    $candidates = array_values(array_filter($weekGames, function($g) {
                        $kickoff = new \DateTimeImmutable($g['kickoff_time']);
                        $kickoffEt = $kickoff->setTimezone(new \DateTimeZone('America/New_York'));
                        return !($kickoffEt->format('N') === '1' && (int)$kickoffEt->format('G') >= 17);
                    }));
                    if (empty($candidates)) {
                        $candidates = $weekGames;
                    }

                    $idx = abs(crc32($seedStr)) % count($candidates);
                    $selectedId = (int) $candidates[$idx]['id'];

                    $this->pdo->exec("UPDATE games SET is_mnf = 0 WHERE season_year = {$season} AND week_number = {$week}");
                    $this->pdo->exec("UPDATE games SET is_mnf = 1 WHERE id = {$selectedId}");
                }
            }
        } catch (\Throwable) {
            // Ignore if tables not yet ready
        }
    }
}
