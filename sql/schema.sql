CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uploads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    drive_file_id VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    comment TEXT NULL,
    uploader_name VARCHAR(100) NULL,
    uploader_ip VARCHAR(45) NULL,
    captured_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_uploads_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_uploads_event_created (event_id, created_at),
    INDEX idx_uploads_event_captured (event_id, captured_at),
    INDEX idx_uploads_event_uploader (event_id, uploader_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO events (name, slug, active)
VALUES ('Amina & Victor - Marrakech 2026', 'brollop-2026', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), active = VALUES(active);

-- For existing installations, run:
-- ALTER TABLE uploads ADD COLUMN captured_at DATETIME NULL AFTER uploader_ip;
-- CREATE INDEX idx_uploads_event_captured ON uploads(event_id, captured_at);
-- ALTER TABLE uploads ADD COLUMN uploader_name VARCHAR(100) NULL AFTER comment;
-- CREATE INDEX idx_uploads_event_uploader ON uploads(event_id, uploader_name);
