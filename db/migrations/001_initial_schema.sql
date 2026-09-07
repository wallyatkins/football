-- 001_initial_schema.sql: Core schema for NFL Pick'em & Survivor

-- Users Table (Mirrored from WallyAuth)
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT, -- SQLite syntax; Postgres replaces with SERIAL PRIMARY KEY
    oidc_sub VARCHAR(255) UNIQUE NOT NULL,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'player',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_users_sub ON users(oidc_sub);

-- NFL Games & Schedule Table
CREATE TABLE IF NOT EXISTS games (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    season_year INT NOT NULL,
    week_number INT NOT NULL,
    home_team VARCHAR(10) NOT NULL,
    away_team VARCHAR(10) NOT NULL,
    kickoff_time TIMESTAMP WITH TIME ZONE NOT NULL,
    is_mnf BOOLEAN DEFAULT FALSE,
    home_score INT DEFAULT NULL,
    away_score INT DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'scheduled'
);
CREATE INDEX IF NOT EXISTS idx_games_season_week ON games(season_year, week_number);
CREATE INDEX IF NOT EXISTS idx_games_kickoff ON games(kickoff_time);

-- Weekly Pick'em Entries
CREATE TABLE IF NOT EXISTS pickem_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    season_year INT NOT NULL,
    week_number INT NOT NULL,
    mnf_total_points_prediction INT DEFAULT NULL,
    payment_status VARCHAR(20) DEFAULT 'pending',
    payment_verified_at TIMESTAMP WITH TIME ZONE NULL,
    payment_verified_by INT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, season_year, week_number)
);
CREATE INDEX IF NOT EXISTS idx_pickem_entries_lookup ON pickem_entries(season_year, week_number, payment_status);

-- Pick'em Matchup Selections
CREATE TABLE IF NOT EXISTS pickem_picks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entry_id INT NOT NULL REFERENCES pickem_entries(id) ON DELETE CASCADE,
    game_id INT NOT NULL REFERENCES games(id) ON DELETE CASCADE,
    selected_team VARCHAR(10) NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(entry_id, game_id)
);
CREATE INDEX IF NOT EXISTS idx_pickem_picks_game ON pickem_picks(game_id);

-- Survivor Pool Selections
CREATE TABLE IF NOT EXISTS survivor_picks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    season_year INT NOT NULL,
    week_number INT NOT NULL,
    selected_team VARCHAR(10) NOT NULL,
    is_eliminated BOOLEAN DEFAULT FALSE,
    payment_status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, season_year, week_number),
    UNIQUE(user_id, season_year, selected_team)
);
CREATE INDEX IF NOT EXISTS idx_survivor_user_season ON survivor_picks(user_id, season_year);
