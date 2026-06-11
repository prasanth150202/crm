<?php
/**
 * CRM Enhancements Migration Runner - Standalone Version
 * Runs migrations for meetings module and invitation system
 */

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Database configuration (adjust if needed)
$host = 'localhost';
$db = 'crm';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

try {
    // Create PDO connection
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    $results = [];
    $migrations = [
        '001_create_meetings_table.sql',
        '002_create_invitation_tokens_table.sql',
        '003_add_meeting_feature_knobs.sql'
    ];
    
    foreach ($migrations as $migration) {
        $filePath = __DIR__ . '/migrations/' . $migration;
        
        if (!file_exists($filePath)) {
            $results[] = [
                'migration' => $migration,
                'status' => 'error',
                'message' => 'Migration file not found at: ' . $filePath
            ];
            continue;
        }
        
        try {
            $sql = file_get_contents($filePath);
            
            // Remove comments and split by semicolon
            $lines = explode("\n", $sql);
            $cleanedSql = '';
            foreach ($lines as $line) {
                $line = trim($line);
                // Skip comment lines and empty lines
                if (empty($line) || strpos($line, '--') === 0) {
                    continue;
                }
                $cleanedSql .= $line . "\n";
            }
            
            // Split by semicolon and execute each statement
            $statements = array_filter(
                array_map('trim', explode(';', $cleanedSql)),
                function($stmt) {
                    return !empty($stmt);
                }
            );
            
            $executedCount = 0;
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $pdo->exec($statement);
                    $executedCount++;
                }
            }
            
            $results[] = [
                'migration' => $migration,
                'status' => 'success',
                'message' => "Migration executed successfully ($executedCount statements)"
            ];
            
        } catch (PDOException $e) {
            $results[] = [
                'migration' => $migration,
                'status' => 'error',
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }
    
    // Summary
    $successCount = count(array_filter($results, function($r) { return $r['status'] === 'success'; }));
    $errorCount = count(array_filter($results, function($r) { return $r['status'] === 'error'; }));
    
    echo json_encode([
        'success' => true,
        'summary' => [
            'total' => count($migrations),
            'successful' => $successCount,
            'failed' => $errorCount
        ],
        'results' => $results
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $e->getMessage(),
        'hint' => 'Please check your database credentials in this file'
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
