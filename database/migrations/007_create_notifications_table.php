<?php

class Migration_007_create_notifications_table {
    
    /**
     * Run the migrations
     */
    public function up(PDO $pdo): void
    {
        // Main notifications table
        $pdo->exec('CREATE TABLE IF NOT EXISTS notifications (
            id CHAR(36) PRIMARY KEY,
            type VARCHAR(255) NOT NULL,
            notifiable_type VARCHAR(255) NOT NULL,
            notifiable_id BIGINT UNSIGNED NOT NULL,
            data TEXT NOT NULL,
            read_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT NULL,
            updated_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_notifications_notifiable (notifiable_type, notifiable_id),
            INDEX idx_notifications_read (notifiable_type, notifiable_id, read_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // Notification preferences table
        $pdo->exec('CREATE TABLE IF NOT EXISTS notification_preferences (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            notification_type VARCHAR(255) NOT NULL,
            email BOOLEAN DEFAULT 1,
            in_app BOOLEAN DEFAULT 1,
            sms BOOLEAN DEFAULT 0,
            push BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uk_notification_preferences (user_id, notification_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // Notification queue for background processing
        $pdo->exec('CREATE TABLE IF NOT EXISTS notification_queue (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            notification_id CHAR(36) NOT NULL,
            channel ENUM("email", "sms", "push") NOT NULL,
            recipient VARCHAR(255) NOT NULL,
            subject VARCHAR(255) DEFAULT NULL,
            message TEXT NOT NULL,
            status ENUM("pending", "processing", "sent", "failed") DEFAULT "pending",
            retry_count INT DEFAULT 0,
            max_retries INT DEFAULT 3,
            scheduled_at TIMESTAMP NULL DEFAULT NULL,
            sent_at TIMESTAMP NULL DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
            INDEX idx_notification_queue_status (status),
            INDEX idx_notification_queue_scheduled (scheduled_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    
    /**
     * Rollback the migrations
     */
    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS notification_queue');
        $pdo->exec('DROP TABLE IF EXISTS notification_preferences');
        $pdo->exec('DROP TABLE IF EXISTS notifications');
    }
}
