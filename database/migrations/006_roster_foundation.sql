CREATE TABLE IF NOT EXISTS schooljaren (
    id CHAR(36) PRIMARY KEY,
    school_id CHAR(36) NOT NULL,
    naam VARCHAR(80) NOT NULL,
    startdatum DATE NOT NULL,
    einddatum DATE NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_schooljaren_school (school_id, active),
    UNIQUE KEY uniq_schooljaar_school_naam (school_id, naam)
);

CREATE TABLE IF NOT EXISTS klassen (
    id CHAR(36) PRIMARY KEY,
    school_id CHAR(36) NOT NULL,
    schooljaar_id CHAR(36) NULL,
    naam_encrypted TEXT NOT NULL,
    naam_search_hash CHAR(64) NULL,
    leerjaar TINYINT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_klassen_school (school_id, active),
    INDEX idx_klassen_schooljaar (schooljaar_id)
);

CREATE TABLE IF NOT EXISTS vakken (
    id CHAR(36) PRIMARY KEY,
    school_id CHAR(36) NOT NULL,
    naam_encrypted TEXT NOT NULL,
    naam_search_hash CHAR(64) NULL,
    code VARCHAR(40) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_vakken_school (school_id, active)
);

CREATE TABLE IF NOT EXISTS lokalen (
    id CHAR(36) PRIMARY KEY,
    school_id CHAR(36) NOT NULL,
    naam_encrypted TEXT NOT NULL,
    naam_search_hash CHAR(64) NULL,
    capaciteit SMALLINT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_lokalen_school (school_id, active)
);
