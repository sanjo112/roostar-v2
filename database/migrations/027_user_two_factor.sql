CREATE TABLE IF NOT EXISTS user_2fa (
    user_id CHAR(36) PRIMARY KEY,
    secret VARCHAR(128) NULL,
    required TINYINT(1) NOT NULL DEFAULT 0,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_user_2fa_required (required, enabled)
);
