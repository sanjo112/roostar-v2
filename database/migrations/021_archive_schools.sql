ALTER TABLE scholen
    ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER naam_search_hash,
    ADD COLUMN archived_at DATETIME NULL AFTER active,
    ADD INDEX idx_scholen_active (active);
