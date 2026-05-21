CREATE TABLE IF NOT EXISTS schooljaar_periodes (
    id CHAR(36) PRIMARY KEY,
    schooljaar_id CHAR(36) NOT NULL,
    naam VARCHAR(80) NOT NULL,
    week_van TINYINT UNSIGNED NOT NULL,
    week_tot TINYINT UNSIGNED NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_schooljaar_periode_naam (schooljaar_id, naam),
    KEY idx_schooljaar_periodes_schooljaar (schooljaar_id),
    KEY idx_schooljaar_periodes_weken (week_van, week_tot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE roosters
    ADD COLUMN periode_id CHAR(36) NULL AFTER schooljaar_id,
    ADD KEY idx_roosters_periode (periode_id);
