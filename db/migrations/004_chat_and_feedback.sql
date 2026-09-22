-- 004_chat_and_feedback.sql: Live Chat sessions, messages, and feedback submissions

CREATE TABLE IF NOT EXISTS chat_sessions (
    id VARCHAR(64) PRIMARY KEY,
    user_id INT NULL REFERENCES users(id) ON DELETE SET NULL,
    user_name VARCHAR(100) NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    user_token VARCHAR(64) NOT NULL,
    admin_token VARCHAR(64) NOT NULL,
    status VARCHAR(20) DEFAULT 'waiting', -- 'waiting', 'active', 'ended'
    initial_message TEXT NULL,
    user_last_seen INT DEFAULT 0,
    admin_last_seen INT DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_chat_sessions_status ON chat_sessions(status, created_at);
CREATE INDEX IF NOT EXISTS idx_chat_sessions_user ON chat_sessions(user_id, created_at);

CREATE TABLE IF NOT EXISTS chat_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id VARCHAR(64) NOT NULL REFERENCES chat_sessions(id) ON DELETE CASCADE,
    sender VARCHAR(20) NOT NULL, -- 'user', 'admin', 'system'
    sender_name VARCHAR(100) NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_chat_messages_session ON chat_messages(session_id, created_at);

CREATE TABLE IF NOT EXISTS feedback_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INT NULL REFERENCES users(id) ON DELETE SET NULL,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL, -- 'bug', 'idea', 'scoring', 'general'
    message TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'new', -- 'new', 'reviewed', 'resolved'
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_feedback_created ON feedback_messages(created_at);
