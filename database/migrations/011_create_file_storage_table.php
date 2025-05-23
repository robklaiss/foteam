<?php

class Migration_011_create_file_storage_table {
    
    /**
     * Run the migrations
     */
    public function up(PDO $pdo): void
    {
        // File storage table
        $pdo->exec('CREATE TABLE IF NOT EXISTS file_storage (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            disk VARCHAR(50) NOT NULL DEFAULT "local",
            path VARCHAR(1000) NOT NULL,
            filename VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            extension VARCHAR(20) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size BIGINT UNSIGNED NOT NULL,
            width INT UNSIGNED DEFAULT NULL,
            height INT UNSIGNED DEFAULT NULL,
            duration INT UNSIGNED DEFAULT NULL COMMENT "For video/audio files, in seconds",
            visibility ENUM("public", "private") DEFAULT "public",
            is_temporary BOOLEAN DEFAULT 0,
            expires_at TIMESTAMP NULL DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_file_storage_user (user_id),
            INDEX idx_file_storage_path (path(255)),
            INDEX idx_file_storage_filename (filename),
            INDEX idx_file_storage_mime (mime_type),
            INDEX idx_file_storage_temporary (is_temporary, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // File conversions table (for different versions of the same file)
        $pdo->exec('CREATE TABLE IF NOT EXISTS file_conversions (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            file_id BIGINT UNSIGNED NOT NULL,
            conversion_name VARCHAR(100) NOT NULL,
            disk VARCHAR(50) NOT NULL DEFAULT "local",
            path VARCHAR(1000) NOT NULL,
            filename VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size BIGINT UNSIGNED NOT NULL,
            width INT UNSIGNED DEFAULT NULL,
            height INT UNSIGNED DEFAULT NULL,
            duration INT UNSIGNED DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (file_id) REFERENCES file_storage(id) ON DELETE CASCADE,
            UNIQUE KEY uk_file_conversions (file_id, conversion_name),
            INDEX idx_file_conversions_file (file_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // File usage tracking
        $pdo->exec('CREATE TABLE IF NOT EXISTS file_usage (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            file_id BIGINT UNSIGNED NOT NULL,
            subject_type VARCHAR(255) NOT NULL,
            subject_id BIGINT UNSIGNED NOT NULL,
            relation VARCHAR(100) NOT NULL COMMENT "e.g., thumbnail, attachment, avatar",
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (file_id) REFERENCES file_storage(id) ON DELETE CASCADE,
            UNIQUE KEY uk_file_usage (file_id, subject_type, subject_id, relation),
            INDEX idx_file_usage_file (file_id),
            INDEX idx_file_usage_subject (subject_type, subject_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    
    /**
     * Rollback the migrations
     */
    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS file_usage');
        $pdo->exec('DROP TABLE IF EXISTS file_conversions');
        $pdo->exec('DROP TABLE IF EXISTS file_storage');
    }
}
