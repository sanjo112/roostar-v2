ALTER TABLE school_settings
    ADD COLUMN school_logo_path VARCHAR(255) NULL AFTER login_visual_updated_at,
    ADD COLUMN school_logo_updated_at DATETIME NULL AFTER school_logo_path,
    ADD INDEX idx_school_settings_school_logo (school_logo_path);
