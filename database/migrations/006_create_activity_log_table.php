<?php

class Migration_006_create_activity_log_table {
    
    /**
     * Run the migrations
     */
    public function up(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS activity_log (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            log_name VARCHAR(255) DEFAULT NULL,
            description TEXT NOT NULL,
            subject_type VARCHAR(255) DEFAULT NULL,
            subject_id BIGINT UNSIGNED DEFAULT NULL,
            causer_type VARCHAR(255) DEFAULT NULL,
            causer_id BIGINT UNSIGNED DEFAULT NULL,
            properties JSON DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT NULL,
            updated_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_activity_log_log_name (log_name),
            INDEX idx_activity_log_subject (subject_type, subject_id),
            INDEX idx_activity_log_causer (causer_type, causer_id),
            INDEX idx_activity_log_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // Create a separate table for storing event-specific data
        $pdo->exec('CREATE TABLE IF NOT EXISTS activity_log_properties (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            activity_id BIGINT UNSIGNED NOT NULL,
            property_key VARCHAR(255) NOT NULL,
            string_value TEXT DEFAULT NULL,
            text_value LONGTEXT DEFAULT NULL,
            integer_value BIGINT DEFAULT NULL,
            float_value DOUBLE DEFAULT NULL,
            boolean_value TINYINT(1) DEFAULT NULL,
            json_value JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (activity_id) REFERENCES activity_log(id) ON DELETE CASCADE,
            INDEX idx_activity_log_properties_activity_id (activity_id),
            INDEX idx_activity_log_properties_key (property_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    
    /**
     * Rollback the migrations
     */
    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS activity_log_properties');
        $pdo->exec('DROP TABLE IF EXISTS activity_log');
    }
}
