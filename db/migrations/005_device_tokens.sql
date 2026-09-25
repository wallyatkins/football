-- 005_device_tokens.sql: Persistent device tokens for seamless auto-login and trusted device persistence
CREATE TABLE IF NOT EXISTS user_device_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    last_seen_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_device_tokens_hash ON user_device_tokens(token_hash);
CREATE INDEX IF NOT EXISTS idx_device_tokens_user ON user_device_tokens(user_id);
