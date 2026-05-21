CREATE TABLE IF NOT EXISTS opleidingen (
    id CHAR(36) PRIMARY KEY,
    school_id CHAR(36) NOT NULL,
    naam_encrypted TEXT NOT NULL,
    naam_search_hash CHAR(64) NULL,
    code VARCHAR(40) NULL,
    niveau VARCHAR(80) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_opleidingen_school (school_id, active)
);

CREATE TABLE IF NOT EXISTS opleiding_vakken (
    opleiding_id CHAR(36) NOT NULL,
    vak_id CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (opleiding_id, vak_id),
    INDEX idx_opleiding_vakken_vak (vak_id)
);

ALTER TABLE klassen
    ADD COLUMN opleiding_id CHAR(36) NULL AFTER schooljaar_id,
    ADD INDEX idx_klassen_opleiding (opleiding_id);
