<?php

class Migration_009_create_subscriptions_table {
    
    /**
     * Run the migrations
     */
    public function up(PDO $pdo): void
    {
        // Subscription plans table
        $pdo->exec('CREATE TABLE IF NOT EXISTS subscription_plans (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            description TEXT DEFAULT NULL,
            price DECIMAL(10, 2) NOT NULL,
            currency CHAR(3) DEFAULT "USD",
            interval ENUM("day", "week", "month", "year") NOT NULL,
            interval_count INT UNSIGNED DEFAULT 1,
            trial_period_days INT UNSIGNED DEFAULT 0,
            is_active BOOLEAN DEFAULT 1,
            features JSON DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_subscription_plans_slug (slug),
            INDEX idx_subscription_plans_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // User subscriptions table
        $pdo->exec('CREATE TABLE IF NOT EXISTS subscriptions (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            plan_id BIGINT UNSIGNED NOT NULL,
            status ENUM("active", "past_due", "unpaid", "canceled", "incomplete", "incomplete_expired", "trialing", "paused") NOT NULL,
            quantity INT UNSIGNED DEFAULT 1,
            trial_ends_at TIMESTAMP NULL DEFAULT NULL,
            starts_at TIMESTAMP NULL DEFAULT NULL,
            ends_at TIMESTAMP NULL DEFAULT NULL,
            current_period_start TIMESTAMP NULL DEFAULT NULL,
            current_period_end TIMESTAMP NULL DEFAULT NULL,
            cancel_at_period_end BOOLEAN DEFAULT 0,
            canceled_at TIMESTAMP NULL DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE RESTRICT,
            INDEX idx_subscriptions_user (user_id, status),
            INDEX idx_subscriptions_plan (plan_id),
            INDEX idx_subscriptions_status (status),
            INDEX idx_subscriptions_period (current_period_start, current_period_end)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // Subscription items (for plans with multiple items)
        $pdo->exec('CREATE TABLE IF NOT EXISTS subscription_items (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            subscription_id BIGINT UNSIGNED NOT NULL,
            plan_id BIGINT UNSIGNED NOT NULL,
            quantity INT UNSIGNED DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
            FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE RESTRICT,
            UNIQUE KEY uk_subscription_items (subscription_id, plan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // Subscription usage tracking
        $pdo->exec('CREATE TABLE IF NOT EXISTS subscription_usage (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            subscription_item_id BIGINT UNSIGNED NOT NULL,
            feature_key VARCHAR(100) NOT NULL,
            used INT UNSIGNED DEFAULT 0,
            limit_value INT UNSIGNED DEFAULT NULL,
            reset_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (subscription_item_id) REFERENCES subscription_items(id) ON DELETE CASCADE,
            UNIQUE KEY uk_subscription_usage (subscription_item_id, feature_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    
    /**
     * Rollback the migrations
     */
    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS subscription_usage');
        $pdo->exec('DROP TABLE IF EXISTS subscription_items');
        $pdo->exec('DROP TABLE IF EXISTS subscriptions');
        $pdo->exec('DROP TABLE IF EXISTS subscription_plans');
    }
}
