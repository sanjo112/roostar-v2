CREATE TABLE IF NOT EXISTS security_tokens (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NULL,
    purpose VARCHAR(80) NOT NULL,
    selector CHAR(32) NOT NULL UNIQUE,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_security_tokens_user (user_id),
    INDEX idx_security_tokens_purpose (purpose, expires_at)
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id CHAR(36) PRIMARY KEY,
    email_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL,
    INDEX idx_login_attempts_window (email_hash, ip_address, attempted_at)
);
