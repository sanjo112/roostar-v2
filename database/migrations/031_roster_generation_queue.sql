CREATE TABLE IF NOT EXISTS roster_generation_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    max_concurrent TINYINT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL
);

INSERT IGNORE INTO roster_generation_settings (id, max_concurrent, updated_at)
VALUES (1, 1, NOW());

CREATE TABLE IF NOT EXISTS roster_generation_jobs (
    id CHAR(36) PRIMARY KEY,
    school_id CHAR(36) NOT NULL,
    schooljaar_id CHAR(36) NOT NULL,
    periode_id CHAR(36) NOT NULL,
    requested_by CHAR(36) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'queued',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    result_percent TINYINT UNSIGNED NULL,
    lesson_count INT UNSIGNED NOT NULL DEFAULT 0,
    lesson_request_count INT UNSIGNED NOT NULL DEFAULT 0,
    hard_violations INT UNSIGNED NOT NULL DEFAULT 0,
    soft_violations INT UNSIGNED NOT NULL DEFAULT 0,
    stats_json JSON NULL,
    error_message TEXT NULL,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_roster_generation_jobs_status (status, created_at),
    INDEX idx_roster_generation_jobs_period (periode_id, created_at),
    INDEX idx_roster_generation_jobs_school (school_id, created_at)
);
