CREATE TABLE IF NOT EXISTS leerling_profielen (
    user_id CHAR(36) PRIMARY KEY,
    klas_id CHAR(36) NULL,
    leerlingnummer VARCHAR(60) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_leerling_profielen_klas (klas_id)
);
