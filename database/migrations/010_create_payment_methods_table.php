<?php

class Migration_010_create_payment_methods_table {
    
    /**
     * Run the migrations
     */
    public function up(PDO $pdo): void
    {
        // Payment methods table
        $pdo->exec('CREATE TABLE IF NOT EXISTS payment_methods (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            payment_processor VARCHAR(50) NOT NULL,
            processor_customer_id VARCHAR(255) DEFAULT NULL,
            payment_method_id VARCHAR(255) NOT NULL,
            type ENUM("card", "bank_account", "paypal", "other") NOT NULL,
            is_default BOOLEAN DEFAULT 0,
            card_brand VARCHAR(50) DEFAULT NULL,
            card_last4 CHAR(4) DEFAULT NULL,
            card_exp_month TINYINT UNSIGNED DEFAULT NULL,
            card_exp_year SMALLINT UNSIGNED DEFAULT NULL,
            card_country CHAR(2) DEFAULT NULL,
            bank_name VARCHAR(100) DEFAULT NULL,
            bank_routing_number VARCHAR(50) DEFAULT NULL,
            bank_account_last4 CHAR(4) DEFAULT NULL,
            billing_details JSON DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uk_payment_methods_processor (payment_processor, payment_method_id),
            INDEX idx_payment_methods_user (user_id, is_default)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // Payment intents table
        $pdo->exec('CREATE TABLE IF NOT EXISTS payment_intents (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED DEFAULT NULL,
            subscription_id BIGINT UNSIGNED DEFAULT NULL,
            payment_method_id BIGINT UNSIGNED DEFAULT NULL,
            payment_processor VARCHAR(50) NOT NULL,
            processor_intent_id VARCHAR(255) NOT NULL,
            amount INT UNSIGNED NOT NULL,
            currency CHAR(3) DEFAULT "USD",
            status VARCHAR(50) NOT NULL,
            client_secret VARCHAR(255) DEFAULT NULL,
            next_action JSON DEFAULT NULL,
            last_payment_error TEXT DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
            FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL,
            FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL,
            UNIQUE KEY uk_payment_intents_processor (payment_processor, processor_intent_id),
            INDEX idx_payment_intents_user (user_id),
            INDEX idx_payment_intents_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // Payment transactions table
        $pdo->exec('CREATE TABLE IF NOT EXISTS payment_transactions (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            payment_intent_id BIGINT UNSIGNED DEFAULT NULL,
            order_id BIGINT UNSIGNED DEFAULT NULL,
            subscription_id BIGINT UNSIGNED DEFAULT NULL,
            payment_method_id BIGINT UNSIGNED DEFAULT NULL,
            payment_processor VARCHAR(50) NOT NULL,
            processor_transaction_id VARCHAR(255) NOT NULL,
            amount INT UNSIGNED NOT NULL,
            currency CHAR(3) DEFAULT "USD",
            status VARCHAR(50) NOT NULL,
            type ENUM("payment", "refund", "dispute", "transfer", "payout", "other") NOT NULL,
            description TEXT DEFAULT NULL,
            failure_code VARCHAR(50) DEFAULT NULL,
            failure_message TEXT DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (payment_intent_id) REFERENCES payment_intents(id) ON DELETE SET NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
            FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL,
            FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL,
            UNIQUE KEY uk_payment_transactions_processor (payment_processor, processor_transaction_id),
            INDEX idx_payment_transactions_user (user_id),
            INDEX idx_payment_transactions_status (status),
            INDEX idx_payment_transactions_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // Refunds table
        $pdo->exec('CREATE TABLE IF NOT EXISTS refunds (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            payment_transaction_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED DEFAULT NULL,
            payment_processor VARCHAR(50) NOT NULL,
            processor_refund_id VARCHAR(255) NOT NULL,
            amount INT UNSIGNED NOT NULL,
            currency CHAR(3) DEFAULT "USD",
            reason TEXT DEFAULT NULL,
            status VARCHAR(50) NOT NULL,
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (payment_transaction_id) REFERENCES payment_transactions(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
            UNIQUE KEY uk_refunds_processor (payment_processor, processor_refund_id),
            INDEX idx_refunds_user (user_id),
            INDEX idx_refunds_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    
    /**
     * Rollback the migrations
     */
    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS refunds');
        $pdo->exec('DROP TABLE IF EXISTS payment_transactions');
        $pdo->exec('DROP TABLE IF EXISTS payment_intents');
        $pdo->exec('DROP TABLE IF EXISTS payment_methods');
    }
}
