<?php
// database/seeders/update_existing_organizations_plan.php

echo "Running seeder: update_existing_organizations_plan...\n";

// Ensure a PDO connection is available or attempt to establish one
if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo "PDO connection not found. Attempting to load config and connect...\n";
    $dbConfigPath = __DIR__ . '/../../config/db.php';
    if (!file_exists($dbConfigPath)) {
        die("Error: Database configuration file not found at " . $dbConfigPath . "\n");
    }
    
    require_once $dbConfigPath; 

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        die("Error: PDO connection could not be established after including db.php. Check your db.php configuration.\n");
    }
    echo "Database connection established via db.php.\n";
}

try {
    $pdo->beginTransaction();

    // Get the Professional plan ID
    $stmt = $pdo->prepare("SELECT id FROM `plans` WHERE name = 'Professional Plan' LIMIT 1");
    $stmt->execute();
    $professionalPlan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$professionalPlan) {
        throw new Exception("Professional Plan not found in database. Please run the plans seeder first.");
    }

    $professionalPlanId = $professionalPlan['id'];
    echo "Found Professional Plan with ID: {$professionalPlanId}\n";

    // Update all organizations that don't have a current_plan_id set
    $updateStmt = $pdo->prepare("
        UPDATE `organizations` 
        SET current_plan_id = :plan_id 
        WHERE current_plan_id IS NULL
    ");
    $updateStmt->execute([':plan_id' => $professionalPlanId]);
    
    $updatedCount = $updateStmt->rowCount();
    echo "Updated {$updatedCount} organizations to Professional Plan.\n";

    // Display the updated organizations
    if ($updatedCount > 0) {
        $listStmt = $pdo->prepare("
            SELECT id, name, current_plan_id 
            FROM `organizations` 
            WHERE current_plan_id = :plan_id
        ");
        $listStmt->execute([':plan_id' => $professionalPlanId]);
        $orgs = $listStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nOrganizations now on Professional Plan:\n";
        foreach ($orgs as $org) {
            echo "  - ID: {$org['id']}, Name: {$org['name']}\n";
        }
    }

    $pdo->commit();
    echo "\nSeeder update_existing_organizations_plan completed successfully.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Seeder failed: " . $e->getMessage() . "\n");
}
