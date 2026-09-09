-- 003_fantasy_vault.sql: Complete Fantasy Football Historical Dynasty Vault Schema

-- Franchises Table (12 persistent franchises across league history)
CREATE TABLE IF NOT EXISTS fantasy_franchises (
    id INTEGER PRIMARY KEY, -- CBS Franchise ID 1 to 12
    current_name VARCHAR(100) NOT NULL,
    current_managers VARCHAR(255) NOT NULL,
    wins INT DEFAULT 0,
    losses INT DEFAULT 0,
    ties INT DEFAULT 0,
    win_pct REAL DEFAULT 0.0,
    points_for REAL DEFAULT 0.0,
    points_against REAL DEFAULT 0.0,
    titles_count INT DEFAULT 0,
    avg_finish REAL DEFAULT NULL,
    avg_pts_year REAL DEFAULT NULL,
    contact_emails TEXT DEFAULT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Historical Name Changes for each Franchise
CREATE TABLE IF NOT EXISTS fantasy_franchise_names (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    franchise_id INT NOT NULL REFERENCES fantasy_franchises(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    first_year INT NOT NULL,
    last_year INT NOT NULL,
    UNIQUE(franchise_id, name)
);
CREATE INDEX IF NOT EXISTS idx_franchise_names_lookup ON fantasy_franchise_names(franchise_id);

-- Seasons Archive Table
CREATE TABLE IF NOT EXISTS fantasy_seasons (
    year INT PRIMARY KEY,
    champion_franchise_id INT NULL REFERENCES fantasy_franchises(id) ON DELETE SET NULL,
    champion_name VARCHAR(100) NULL,
    runner_up_franchise_id INT NULL REFERENCES fantasy_franchises(id) ON DELETE SET NULL,
    runner_up_name VARCHAR(100) NULL,
    notes TEXT NULL
);

-- Yearly Standings Table
CREATE TABLE IF NOT EXISTS fantasy_standings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    season_year INT NOT NULL REFERENCES fantasy_seasons(year) ON DELETE CASCADE,
    franchise_id INT NOT NULL REFERENCES fantasy_franchises(id) ON DELETE CASCADE,
    team_name VARCHAR(100) NOT NULL,
    division_name VARCHAR(50) NULL,
    wins INT NOT NULL DEFAULT 0,
    losses INT NOT NULL DEFAULT 0,
    ties INT NOT NULL DEFAULT 0,
    win_pct REAL NOT NULL DEFAULT 0.0,
    points_for REAL NOT NULL DEFAULT 0.0,
    points_against REAL NOT NULL DEFAULT 0.0,
    rank INT NOT NULL DEFAULT 0,
    UNIQUE(season_year, franchise_id)
);
CREATE INDEX IF NOT EXISTS idx_fantasy_standings_year ON fantasy_standings(season_year);

-- Complete Historical Matchups (1,820 head-to-head weekly box scores)
CREATE TABLE IF NOT EXISTS fantasy_matchups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    season_year INT NOT NULL REFERENCES fantasy_seasons(year) ON DELETE CASCADE,
    week_number INT NOT NULL,
    away_franchise_id INT NOT NULL REFERENCES fantasy_franchises(id) ON DELETE CASCADE,
    away_team_name VARCHAR(100) NOT NULL,
    away_score REAL NOT NULL,
    home_franchise_id INT NOT NULL REFERENCES fantasy_franchises(id) ON DELETE CASCADE,
    home_team_name VARCHAR(100) NOT NULL,
    home_score REAL NOT NULL,
    winner_franchise_id INT NULL REFERENCES fantasy_franchises(id) ON DELETE SET NULL,
    point_diff REAL NOT NULL,
    is_playoff BOOLEAN DEFAULT 0,
    recap_id VARCHAR(50) NULL,
    UNIQUE(season_year, week_number, away_franchise_id, home_franchise_id)
);
CREATE INDEX IF NOT EXISTS idx_fantasy_matchups_lookup ON fantasy_matchups(season_year, week_number);
CREATE INDEX IF NOT EXISTS idx_fantasy_matchups_rivalry ON fantasy_matchups(away_franchise_id, home_franchise_id);
