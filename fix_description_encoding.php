<?php
// One-time fix: decode HTML-escaped description values in the DB
// Run once then delete this file
require_once 'config/db.php';

$stmt = $pdo->query("SELECT id, description FROM leads WHERE description IS NOT NULL AND description != ''");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fixed = 0;
foreach ($rows as $row) {
    $decoded = html_entity_decode($row['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($decoded !== $row['description']) {
        $upd = $pdo->prepare("UPDATE leads SET description = ? WHERE id = ?");
        $upd->execute([$decoded, $row['id']]);
        $fixed++;
        echo "Fixed lead ID {$row['id']}<br>";
    }
}

echo "<br>Done. Fixed $fixed records.";
