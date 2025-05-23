<?php

class Migration_008_create_search_history_table {
    
    /**
     * Run the migrations
     */
    public function up(PDO $pdo): void
    {
        // Search history table
        $pdo->exec('CREATE TABLE IF NOT EXISTS search_history (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            session_id VARCHAR(255) DEFAULT NULL,
            query TEXT NOT NULL,
            filters JSON DEFAULT NULL,
            result_count INT UNSIGNED DEFAULT 0,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            referrer VARCHAR(512) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_search_history_user (user_id, created_at),
            INDEX idx_search_history_session (session_id, created_at),
            INDEX idx_search_history_query (query(100), created_at),
            INDEX idx_search_history_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // Search analytics table
        $pdo->exec('CREATE TABLE IF NOT EXISTS search_analytics (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            date DATE NOT NULL,
            query VARCHAR(255) NOT NULL,
            search_count INT UNSIGNED DEFAULT 1,
            result_count_avg DECIMAL(10,2) DEFAULT 0,
            click_count INT UNSIGNED DEFAULT 0,
            UNIQUE KEY uk_search_analytics (date, query(191)),
            INDEX idx_search_analytics_query (query(100)),
            INDEX idx_search_analytics_date (date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // Search click tracking
        $pdo->exec('CREATE TABLE IF NOT EXISTS search_clicks (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            search_id BIGINT UNSIGNED NOT NULL,
            result_type VARCHAR(50) NOT NULL,
            result_id BIGINT UNSIGNED NOT NULL,
            position INT UNSIGNED NOT NULL,
            session_id VARCHAR(255) DEFAULT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (search_id) REFERENCES search_history(id) ON DELETE CASCADE,
            INDEX idx_search_clicks_search (search_id),
            INDEX idx_search_clicks_result (result_type, result_id),
            INDEX idx_search_clicks_user (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    
    /**
     * Rollback the migrations
     */
    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS search_clicks');
        $pdo->exec('DROP TABLE IF EXISTS search_analytics');
        $pdo->exec('DROP TABLE IF EXISTS search_history');
    }
}
