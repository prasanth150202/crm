<?php
/**
 * Database Migration Helper
 * Manages database schema versions and migrations
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/logger.php';

class MigrationHelper {
    private $pdo;
    private $migrationsDir;
    
    public function __construct($pdo, $migrationsDir = null) {
        $this->pdo = $pdo;
        $this->migrationsDir = $migrationsDir ?: __DIR__;
    }
    
    /**
     * Initialize migrations table
     */
    public function init() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration_name VARCHAR(255) NOT NULL UNIQUE,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                execution_time_ms INT DEFAULT NULL
            ) ENGINE=InnoDB
        ");
    }
    
    /**
     * Get executed migrations
     */
    public function getExecutedMigrations() {
        $this->init();
        $stmt = $this->pdo->query("SELECT migration_name FROM migrations ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Get pending migrations
     */
    public function getPendingMigrations() {
        $executed = $this->getExecutedMigrations();
        $all = $this->getAllMigrations();
        
        return array_diff($all, $executed);
    }
    
    /**
     * Get all migration files
     */
    public function getAllMigrations() {
        $files = glob($this->migrationsDir . '/*.sql');
        $migrations = [];
        
        foreach ($files as $file) {
            $migrations[] = basename($file);
        }
        
        sort($migrations);
        return $migrations;
    }
    
    /**
     * Execute a migration
     */
    public function executeMigration($migrationName) {
        $file = $this->migrationsDir . '/' . $migrationName;
        
        if (!file_exists($file)) {
            throw new Exception("Migration file not found: $migrationName");
        }
        
        $sql = file_get_contents($file);
        
        if (empty(trim($sql))) {
            Logger::warning("Empty migration file: $migrationName");
            return false;
        }
        
        $startTime = microtime(true);
        
        try {
            $this->pdo->beginTransaction();
            
            // Split by semicolon and execute each statement
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function($stmt) {
                    return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
                }
            );
            
            foreach ($statements as $statement) {
                if (!empty(trim($statement))) {
                    $this->pdo->exec($statement);
                }
            }
            
            // Record migration
            $stmt = $this->pdo->prepare("
                INSERT INTO migrations (migration_name, execution_time_ms) 
                VALUES (?, ?)
            ");
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            $stmt->execute([$migrationName, $executionTime]);
            
            $this->pdo->commit();
            
            Logger::info("Migration executed: $migrationName", [
                'execution_time_ms' => $executionTime
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            Logger::exception($e, ['migration' => $migrationName]);
            throw $e;
        }
    }
    
    /**
     * Run all pending migrations
     */
    public function runPending() {
        $this->init();
        $pending = $this->getPendingMigrations();
        
        if (empty($pending)) {
            echo "No pending migrations.\n";
            return;
        }
        
        echo "Found " . count($pending) . " pending migration(s).\n";
        
        foreach ($pending as $migration) {
            echo "Executing: $migration... ";
            try {
                $this->executeMigration($migration);
                echo "✓ Done\n";
            } catch (Exception $e) {
                echo "✗ Failed: " . $e->getMessage() . "\n";
                throw $e;
            }
        }
        
        echo "All migrations completed successfully.\n";
    }
    
    /**
     * Rollback last migration (if supported)
     */
    public function rollbackLast() {
        $this->init();
        $stmt = $this->pdo->query("
            SELECT migration_name FROM migrations 
            ORDER BY id DESC LIMIT 1
        ");
        $last = $stmt->fetchColumn();
        
        if (!$last) {
            echo "No migrations to rollback.\n";
            return;
        }
        
        echo "Warning: Manual rollback required for: $last\n";
        echo "Please review the migration file and create a rollback script if needed.\n";
    }
}

// CLI usage
if (php_sapi_name() === 'cli') {
    try {
        require_once __DIR__ . '/../../config/db.php';
        $helper = new MigrationHelper($pdo);
        
        $command = $argv[1] ?? 'run';
        
        switch ($command) {
            case 'run':
                $helper->runPending();
                break;
            case 'status':
                $executed = $helper->getExecutedMigrations();
                $pending = $helper->getPendingMigrations();
                echo "Executed: " . count($executed) . "\n";
                echo "Pending: " . count($pending) . "\n";
                if (!empty($pending)) {
                    echo "Pending migrations:\n";
                    foreach ($pending as $m) {
                        echo "  - $m\n";
                    }
                }
                break;
            case 'rollback':
                $helper->rollbackLast();
                break;
            default:
                echo "Usage: php migration_helper.php [run|status|rollback]\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

