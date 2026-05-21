CREATE TABLE IF NOT EXISTS ziekteperiodes (
    id CHAR(36) PRIMARY KEY,
    school_id CHAR(36) NOT NULL,
    leraar_id CHAR(36) NOT NULL,
    datum_van DATE NOT NULL,
    datum_tot DATE NULL,
    opmerking TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    ingevoerd_door CHAR(36) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_ziekte_school_status (school_id, status),
    KEY idx_ziekte_leraar_datum (leraar_id, datum_van, datum_tot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ziekte_les_wijzigingen (
    id CHAR(36) PRIMARY KEY,
    ziekteperiode_id CHAR(36) NOT NULL,
    rooster_les_id CHAR(36) NOT NULL,
    datum DATE NOT NULL,
    week_nummer TINYINT UNSIGNED NOT NULL,
    oplossing VARCHAR(30) NOT NULL DEFAULT 'uitgevallen',
    vervanger_id CHAR(36) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_ziekte_les_datum (ziekteperiode_id, rooster_les_id, datum),
    KEY idx_ziekte_wijziging_les (rooster_les_id),
    KEY idx_ziekte_wijziging_vervanger (vervanger_id, datum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
