CREATE TABLE IF NOT EXISTS leraar_profielen (
    user_id CHAR(36) PRIMARY KEY,
    max_uren_per_week TINYINT UNSIGNED NOT NULL DEFAULT 24,
    max_uren_per_dag TINYINT UNSIGNED NOT NULL DEFAULT 6,
    beschikbaarheid_json JSON NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
