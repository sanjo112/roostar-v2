CREATE TABLE IF NOT EXISTS school_settings (
    school_id CHAR(36) PRIMARY KEY,
    login_visual_path VARCHAR(255) NULL,
    login_visual_updated_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_school_settings_login_visual (login_visual_path)
);
