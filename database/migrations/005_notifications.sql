CREATE TABLE IF NOT EXISTS notifications (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    type VARCHAR(20) NOT NULL,
    title VARCHAR(120) NOT NULL,
    message TEXT NOT NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_notifications_user_unread (user_id, read_at, created_at)
);
