<?php

require_once __DIR__ . '/../vendor/autoload.php';

class MigrationRunner
{
    /**
     * @var PDO
     */
    private $pdo;

    /**
     * @var string
     */
    private $migrationsTable = 'migrations';

    /**
     * @var string
     */
    private $migrationsPath;

    /**
     * MigrationRunner constructor.
     * @param PDO $pdo
     * @param string $migrationsPath
     */
    public function __construct(PDO $pdo, string $migrationsPath)
    {
        $this->pdo = $pdo;
        $this->migrationsPath = rtrim($migrationsPath, '/') . '/';
        
        // Set PDO attributes
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Create migrations table if it doesn't exist
        $this->createMigrationsTable();
    }

    /**
     * Create the migrations table if it doesn't exist
     */
    private function createMigrationsTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$this->migrationsTable}` (
                `migration` VARCHAR(255) NOT NULL,
                `batch` INTEGER NOT NULL,
                `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    /**
     * Get all applied migrations
     * @return array
     */
    public function getAppliedMigrations(): array
    {
        $stmt = $this->pdo->prepare("SELECT migration FROM {$this->migrationsTable} ORDER BY batch, migration");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get all migration files
     * @return array
     */
    public function getMigrationFiles(): array
    {
        $files = [];
        
        if (!is_dir($this->migrationsPath)) {
            return $files;
        }
        
        $items = new DirectoryIterator($this->migrationsPath);
        
        foreach ($items as $item) {
            if ($item->isFile() && $item->getExtension() === 'php') {
                $files[] = $item->getFilename();
            }
        }
        
        sort($files);
        return $files;
    }

    /**
     * Get pending migrations
     * @return array
     */
    public function getPendingMigrations(): array
    {
        $appliedMigrations = $this->getAppliedMigrations();
        $allMigrations = $this->getMigrationFiles();
        
        return array_diff($allMigrations, $appliedMigrations);
    }

    /**
     * Run pending migrations
     * @return array Array of applied migrations
     */
    public function migrate(): array
    {
        $pendingMigrations = $this->getPendingMigrations();
        
        if (empty($pendingMigrations)) {
            return [];
        }
        
        $batch = $this->getNextBatchNumber();
        $appliedMigrations = [];
        
        $this->pdo->beginTransaction();
        
        try {
            foreach ($pendingMigrations as $migration) {
                $this->runMigration($migration, $batch);
                $appliedMigrations[] = $migration;
            }
            
            $this->pdo->commit();
            return $appliedMigrations;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Rollback the last batch of migrations
     * @return array Array of rolled back migrations
     */
    public function rollback(): array
    {
        $batch = $this->getLastBatchNumber();
        
        if ($batch === 0) {
            return [];
        }
        
        $migrations = $this->getMigrationsByBatch($batch);
        
        if (empty($migrations)) {
            return [];
        }
        
        $this->pdo->beginTransaction();
        
        try {
            $rolledBack = [];
            
            // Roll back in reverse order
            foreach (array_reverse($migrations) as $migration) {
                $this->rollbackMigration($migration);
                $rolledBack[] = $migration;
            }
            
            $this->pdo->commit();
            return $rolledBack;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Run a single migration
     * @param string $migration
     * @param int $batch
     */
    private function runMigration(string $migration, int $batch): void
    {
        require_once $this->migrationsPath . $migration;
        
        $className = $this->getMigrationClassName($migration);
        $instance = new $className();
        
        $instance->up($this->pdo);
        
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->migrationsTable} (migration, batch) VALUES (?, ?)"
        );
        
        $stmt->execute([$migration, $batch]);
    }

    /**
     * Rollback a single migration
     * @param string $migration
     */
    private function rollbackMigration(string $migration): void
    {
        require_once $this->migrationsPath . $migration;
        
        $className = $this->getMigrationClassName($migration);
        $instance = new $className();
        
        $instance->down($this->pdo);
        
        $stmt = $this->pdo->prepare(
            "DELETE FROM {$this->migrationsTable} WHERE migration = ?"
        );
        
        $stmt->execute([$migration]);
    }

    /**
     * Get the next batch number
     * @return int
     */
    private function getNextBatchNumber(): int
    {
        $stmt = $this->pdo->query("SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}");
        $result = $stmt->fetch();
        
        return ($result['max_batch'] ?? 0) + 1;
    }

    /**
     * Get the last batch number
     * @return int
     */
    private function getLastBatchNumber(): int
    {
        $stmt = $this->pdo->query("SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}");
        $result = $stmt->fetch();
        
        return (int)($result['max_batch'] ?? 0);
    }

    /**
     * Get migrations by batch number
     * @param int $batch
     * @return array
     */
    private function getMigrationsByBatch(int $batch): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT migration FROM {$this->migrationsTable} WHERE batch = ? ORDER BY migration DESC"
        );
        
        $stmt->execute([$batch]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get the class name from a migration file
     * @param string $migration
     * @return string
     */
    private function getMigrationClassName(string $migration): string
    {
        $name = pathinfo($migration, PATHINFO_FILENAME);
        $parts = explode('_', $name);
        
        // Remove the timestamp
        array_shift($parts);
        
        // Convert to StudlyCase
        $className = '';
        foreach ($parts as $part) {
            $className .= ucfirst(strtolower($part));
        }
        
        return $className;
    }
}

// Example usage:
/*
try {
    $pdo = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password');
    $runner = new MigrationRunner($pdo, __DIR__ . '/migrations');
    
    // Run migrations
    $applied = $runner->migrate();
    echo "Applied migrations: " . implode(", ", $applied) . "\n";
    
    // Rollback last batch
    // $rolledBack = $runner->rollback();
    // echo "Rolled back: " . implode(", ", $rolledBack) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
*/
