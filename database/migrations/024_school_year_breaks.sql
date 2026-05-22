CREATE TABLE IF NOT EXISTS schooljaar_vrije_dagen (
    id CHAR(36) PRIMARY KEY,
    schooljaar_id CHAR(36) NOT NULL,
    naam VARCHAR(120) NOT NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'vrije_dag',
    startdatum DATE NOT NULL,
    einddatum DATE NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_schooljaar_vrije_dagen_schooljaar (schooljaar_id, active),
    INDEX idx_schooljaar_vrije_dagen_datums (startdatum, einddatum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
