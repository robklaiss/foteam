<?php

class Migration_012_create_jobs_table {
    
    /**
     * Run the migrations
     */
    public function up(PDO $pdo): void
    {
        // Jobs table for queued jobs
        $pdo->exec('CREATE TABLE IF NOT EXISTS jobs (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            queue VARCHAR(255) NOT NULL,
            payload LONGTEXT NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL,
            reserved_at INT UNSIGNED DEFAULT NULL,
            available_at INT UNSIGNED NOT NULL,
            created_at INT UNSIGNED NOT NULL,
            INDEX idx_jobs_queue (queue),
            INDEX idx_jobs_reserved (reserved_at, queue, available_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // Failed jobs table
        $pdo->exec('CREATE TABLE IF NOT EXISTS failed_jobs (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            connection TEXT NOT NULL,
            queue TEXT NOT NULL,
            payload LONGTEXT NOT NULL,
            exception LONGTEXT NOT NULL,
            failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_failed_jobs_failed_at (failed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        
        // Job batches table
        $pdo->exec('CREATE TABLE IF NOT EXISTS job_batches (
            id VARCHAR(255) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            total_jobs INT NOT NULL,
            pending_jobs INT NOT NULL,
            failed_jobs INT NOT NULL DEFAULT 0,
            failed_job_ids LONGTEXT NOT NULL,
            options LONGTEXT NULL,
            cancelled_at INT NULL,
            created_at INT NOT NULL,
            finished_at INT NULL,
            INDEX idx_job_batches_finished (finished_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    
    /**
     * Rollback the migrations
     */
    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS job_batches');
        $pdo->exec('DROP TABLE IF EXISTS failed_jobs');
        $pdo->exec('DROP TABLE IF EXISTS jobs');
    }
}
