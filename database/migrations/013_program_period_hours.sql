CREATE TABLE IF NOT EXISTS opleiding_vak_periode_uren (
    opleiding_id CHAR(36) NOT NULL,
    vak_id CHAR(36) NOT NULL,
    periode_id CHAR(36) NOT NULL,
    uren_per_week TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (opleiding_id, vak_id, periode_id),
    INDEX idx_opleiding_vak_periode_uren_periode (periode_id),
    INDEX idx_opleiding_vak_periode_uren_vak (vak_id)
);
