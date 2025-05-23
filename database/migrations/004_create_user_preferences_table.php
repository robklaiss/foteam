<?php

class Migration_004_create_user_preferences_table {
    
    /**
     * Run the migrations
     */
    public function up(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS user_preferences (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            user_id INTEGER NOT NULL,
            notification_email BOOLEAN DEFAULT 1,
            notification_sms BOOLEAN DEFAULT 0,
            notification_newsletter BOOLEAN DEFAULT 1,
            email_digest_frequency ENUM("never", "daily", "weekly", "monthly") DEFAULT "weekly",
            ui_theme VARCHAR(50) DEFAULT "light",
            items_per_page INTEGER DEFAULT 24,
            preferred_currency CHAR(3) DEFAULT "USD",
            language VARCHAR(10) DEFAULT "en",
            timezone VARCHAR(50) DEFAULT "UTC",
            date_format VARCHAR(20) DEFAULT "Y-m-d",
            time_format VARCHAR(20) DEFAULT "H:i:s",
            show_watermark BOOLEAN DEFAULT 1,
            download_quality ENUM("original", "high", "medium", "low") DEFAULT "high",
            custom_fields JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uk_user_preferences_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // Add index for common search fields
        $pdo->exec('CREATE INDEX idx_user_preferences_user_id ON user_preferences(user_id)');
    }
    
    /**
     * Rollback the migrations
     */
    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS user_preferences');
    }
}
