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
    }
}
