CREATE TABLE IF NOT EXISTS scholengroepen (
    id CHAR(36) PRIMARY KEY,
    naam_encrypted TEXT NOT NULL,
    naam_search_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS scholen (
    id CHAR(36) PRIMARY KEY,
    scholengroep_id CHAR(36) NOT NULL,
    naam_encrypted TEXT NOT NULL,
    naam_search_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_scholen_scholengroep (scholengroep_id)
);

CREATE TABLE IF NOT EXISTS users (
    id CHAR(36) PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    naam_encrypted TEXT NOT NULL,
    naam_search_hash CHAR(64) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(64) NOT NULL,
    scholengroep_id CHAR(36) NULL,
    school_id CHAR(36) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_users_scope (scholengroep_id, school_id)
);

CREATE TABLE IF NOT EXISTS permission_grants (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    permission VARCHAR(120) NOT NULL,
    scope_type VARCHAR(40) NOT NULL,
    scope_id CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_permission_grant (user_id, permission, scope_type, scope_id),
    INDEX idx_permission_scope (permission, scope_type, scope_id)
);

CREATE TABLE IF NOT EXISTS audit_log (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NULL,
    action VARCHAR(120) NOT NULL,
    entity_type VARCHAR(80) NULL,
    entity_id CHAR(36) NULL,
    metadata_json JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_user (user_id)
);
