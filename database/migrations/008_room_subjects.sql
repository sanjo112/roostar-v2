CREATE TABLE IF NOT EXISTS lokaal_vakken (
    lokaal_id CHAR(36) NOT NULL,
    vak_id CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (lokaal_id, vak_id),
    INDEX idx_lokaal_vakken_vak (vak_id)
);
