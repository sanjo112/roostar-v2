CREATE TABLE IF NOT EXISTS rooster_week_wijzigingen (
    id CHAR(36) PRIMARY KEY,
    rooster_les_id CHAR(36) NOT NULL,
    week_nummer TINYINT UNSIGNED NOT NULL,
    dag VARCHAR(12) NOT NULL,
    lesuur TINYINT UNSIGNED NOT NULL,
    starttijd CHAR(5) NOT NULL,
    eindtijd CHAR(5) NOT NULL,
    created_by CHAR(36) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_rooster_week_les (rooster_les_id, week_nummer),
    KEY idx_rooster_week_slot (week_nummer, dag, lesuur)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
