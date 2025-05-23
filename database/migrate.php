#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Database configuration
$dbConfig = [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => $_ENV['DB_PORT'] ?? '3306',
    'database' => $_ENV['DB_DATABASE'] ?? 'foteam',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
];

// Create database connection
try {
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    echo "✅ Connected to database: {$dbConfig['database']}\n";
    
    // Initialize migration runner
    $migrationsPath = __DIR__ . '/migrations';
    $runner = new MigrationRunner($pdo, $migrationsPath);
    
    // Run migrations
    $appliedMigrations = $runner->migrate();
    
    if (empty($appliedMigrations)) {
        echo "✅ No new migrations to run.\n";
    } else {
        echo "✅ Applied " . count($appliedMigrations) . " migration(s):\n";
        foreach ($appliedMigrations as $migration) {
            echo "  - $migration\n";
        }
    }
    
    echo "\n🎉 Database migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
