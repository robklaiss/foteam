<?php

class Migration_005_create_api_keys_table {
    
    /**
     * Run the migrations
     */
    public function up(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS api_keys (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            user_id INTEGER NOT NULL,
            name VARCHAR(100) NOT NULL,
            api_key VARCHAR(64) NOT NULL,
            api_secret VARCHAR(255) NOT NULL,
            scopes TEXT DEFAULT NULL,
            last_used_at TIMESTAMP NULL DEFAULT NULL,
            expires_at TIMESTAMP NULL DEFAULT NULL,
            is_active BOOLEAN DEFAULT 1,
            ip_restrictions TEXT DEFAULT NULL,
            referrer_restrictions TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uk_api_keys_api_key (api_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // Add indexes for common searches
        $pdo->exec('CREATE INDEX idx_api_keys_user_id ON api_keys(user_id)');
        $pdo->exec('CREATE INDEX idx_api_keys_is_active ON api_keys(is_active)');
        $pdo->exec('CREATE INDEX idx_api_keys_expires_at ON api_keys(expires_at)');
    }
    
    /**
     * Rollback the migrations
     */
    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS api_keys');
    }
}
