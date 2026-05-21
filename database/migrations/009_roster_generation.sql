ALTER TABLE opleiding_vakken
    ADD COLUMN uren_per_week TINYINT UNSIGNED NOT NULL DEFAULT 3 AFTER vak_id;

CREATE TABLE IF NOT EXISTS leraar_vakken (
    user_id CHAR(36) NOT NULL,
    vak_id CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, vak_id),
    INDEX idx_leraar_vakken_vak (vak_id)
);

CREATE TABLE IF NOT EXISTS roosters (
    id CHAR(36) PRIMARY KEY,
    school_id CHAR(36) NOT NULL,
    schooljaar_id CHAR(36) NOT NULL,
    klas_id CHAR(36) NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'concept',
    generated_by CHAR(36) NULL,
    opmerkingen_json JSON NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_roosters_scope (school_id, schooljaar_id, klas_id, status)
);

CREATE TABLE IF NOT EXISTS rooster_lessen (
    id CHAR(36) PRIMARY KEY,
    rooster_id CHAR(36) NOT NULL,
    klas_id CHAR(36) NOT NULL,
    vak_id CHAR(36) NOT NULL,
    leraar_id CHAR(36) NOT NULL,
    lokaal_id CHAR(36) NOT NULL,
    dag VARCHAR(12) NOT NULL,
    lesuur TINYINT UNSIGNED NOT NULL,
    starttijd CHAR(5) NOT NULL,
    eindtijd CHAR(5) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_rooster_lessen_rooster (rooster_id),
    UNIQUE KEY uniq_rooster_slot_klas (rooster_id, klas_id, dag, lesuur),
    UNIQUE KEY uniq_rooster_slot_leraar (rooster_id, leraar_id, dag, lesuur),
    UNIQUE KEY uniq_rooster_slot_lokaal (rooster_id, lokaal_id, dag, lesuur)
);
