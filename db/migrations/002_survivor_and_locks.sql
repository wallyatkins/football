-- 002_survivor_and_locks.sql: Survivor upfront payment entries and pick locking

CREATE TABLE IF NOT EXISTS survivor_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    season_year INT NOT NULL,
    payment_status VARCHAR(20) DEFAULT 'unpaid',
    is_eliminated BOOLEAN DEFAULT 0,
    elimination_week INT DEFAULT NULL,
    payment_verified_at TIMESTAMP WITH TIME ZONE NULL,
    payment_verified_by INT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, season_year)
);
CREATE INDEX IF NOT EXISTS idx_survivor_entries_lookup ON survivor_entries(season_year, payment_status);
