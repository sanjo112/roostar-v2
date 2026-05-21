CREATE TABLE IF NOT EXISTS locaties (
    id CHAR(36) PRIMARY KEY,
    school_id CHAR(36) NOT NULL,
    naam_encrypted TEXT NOT NULL,
    naam_search_hash CHAR(64) NULL,
    extern TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_locaties_school (school_id, active)
);

ALTER TABLE lokalen
    ADD COLUMN locatie_id CHAR(36) NULL AFTER school_id,
    ADD INDEX idx_lokalen_locatie (locatie_id);
