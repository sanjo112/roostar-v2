ALTER TABLE opleiding_vakken
    ADD COLUMN keuzevak TINYINT(1) NOT NULL DEFAULT 0 AFTER uren_per_week;

CREATE TABLE IF NOT EXISTS leerling_keuzevakken (
    user_id CHAR(36) NOT NULL,
    vak_id CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, vak_id),
    INDEX idx_leerling_keuzevakken_vak (vak_id)
);
