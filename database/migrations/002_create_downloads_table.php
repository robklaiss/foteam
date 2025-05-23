<?php

class Migration_002_create_downloads_table {
    
    /**
     * Run the migrations
     */
    public function up(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS downloads (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            user_id INTEGER NOT NULL,
            photo_id INTEGER NOT NULL,
            order_id INTEGER,
            download_token VARCHAR(255) NOT NULL UNIQUE,
            download_count INTEGER NOT NULL DEFAULT 0,
            first_downloaded_at TIMESTAMP NULL DEFAULT NULL,
            last_downloaded_at TIMESTAMP NULL DEFAULT NULL,
            expires_at TIMESTAMP NULL DEFAULT NULL,
            first_ip_address VARCHAR(45) DEFAULT NULL,
            last_ip_address VARCHAR(45) DEFAULT NULL,
            first_user_agent TEXT,
            last_user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
            INDEX idx_downloads_user_id (user_id),
            INDEX idx_downloads_photo_id (photo_id),
            INDEX idx_downloads_order_id (order_id),
            INDEX idx_downloads_token (download_token),
            INDEX idx_downloads_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    
    /**
     * Rollback the migrations
     */
    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS downloads');
    }
}
