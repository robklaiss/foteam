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
    
    // Ask for confirmation
    echo "\n⚠️  WARNING: This will delete all existing data in the database.\n";
    echo "Do you want to continue? (yes/no) [no]: ";
    
    $handle = fopen('php://stdin', 'r');
    $line = strtolower(trim(fgets($handle)));
    
    if ($line !== 'yes' && $line !== 'y') {
        echo "\n❌ Operation cancelled.\n";
        exit(0);
    }
    
    // Include model files
    $modelPath = __DIR__ . '/../app/models';
    foreach (glob("$modelPath/*.php") as $filename) {
        require_once $filename;
    }
    
    // Run seeder
    $seeder = new FoTeam\Database\Seeders\DatabaseSeeder($pdo);
    $seeder->run();
    
    echo "\n🎉 Database seeding completed successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    if (isset($e->getPrevious())) {
        echo "  - " . $e->getPrevious()->getMessage() . "\n";
    }
    exit(1);
}
