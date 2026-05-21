CREATE TABLE IF NOT EXISTS toetsweken (
    id CHAR(36) PRIMARY KEY,
    school_id CHAR(36) NOT NULL,
    schooljaar_id CHAR(36) NOT NULL,
    periode_id CHAR(36) NULL,
    naam VARCHAR(120) NOT NULL,
    week_nummer TINYINT UNSIGNED NOT NULL,
    les_percentage TINYINT UNSIGNED NOT NULL DEFAULT 50,
    verkort_rooster TINYINT(1) NOT NULL DEFAULT 0,
    lesuren_per_dag TINYINT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_toetsweken_schooljaar_week (school_id, schooljaar_id, week_nummer),
    INDEX idx_toetsweken_school (school_id, active),
    INDEX idx_toetsweken_schooljaar (schooljaar_id),
    INDEX idx_toetsweken_periode (periode_id)
);

CREATE TABLE IF NOT EXISTS toetsen (
    id CHAR(36) PRIMARY KEY,
    toetsweek_id CHAR(36) NOT NULL,
    vak_id CHAR(36) NOT NULL,
    opleiding_id CHAR(36) NULL,
    naam VARCHAR(180) NOT NULL,
    datum DATE NULL,
    tijdslot VARCHAR(10) NOT NULL,
    duur_minuten SMALLINT UNSIGNED NOT NULL DEFAULT 50,
    lokaal_id CHAR(36) NULL,
    aantal_surveillance TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_toetsen_toetsweek (toetsweek_id),
    INDEX idx_toetsen_moment (datum, tijdslot),
    INDEX idx_toetsen_vak (vak_id)
);

CREATE TABLE IF NOT EXISTS toets_surveillance (
    toets_id CHAR(36) NOT NULL,
    leraar_id CHAR(36) NOT NULL,
    voorstel TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (toets_id, leraar_id),
    INDEX idx_toets_surveillance_leraar (leraar_id)
);
