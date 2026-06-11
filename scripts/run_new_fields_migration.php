<?php
require_once __DIR__ . '/../config/db.php';

try {
    $sql = file_get_contents(__DIR__ . '/../database/migrations/add_leads_default_fields.sql');
    $pdo->exec($sql);
    echo "Migration executed successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
