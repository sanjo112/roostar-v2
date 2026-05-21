ALTER TABLE users
    ADD COLUMN force_password_change TINYINT(1) NOT NULL DEFAULT 0 AFTER active,
    ADD COLUMN password_changed_at DATETIME NULL AFTER force_password_change,
    ADD COLUMN deactivated_at DATETIME NULL AFTER last_login_at;
