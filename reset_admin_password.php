<?php
/**
 * One-time admin password reset script.
 * DELETE THIS FILE after use.
 */

require_once __DIR__ . '/config/db.php';

// ── Configure these before running ──────────────────────────────────────────
$target_email = 'admin@gmail.com'; // change if needed
$new_password = 'Admin@1234';             // change to your desired password
// ────────────────────────────────────────────────────────────────────────────

$new_hash = password_hash($new_password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare(
    "UPDATE users SET password_hash = ? WHERE email = ? AND role IN ('owner','admin','super_admin')"
);
$stmt->execute([$new_hash, $target_email]);

if ($stmt->rowCount() > 0) {
    echo "Password reset successfully for: " . htmlspecialchars($target_email) . "<br>";
    echo "<strong>Delete this file immediately after use.</strong>";
} else {
    echo "No matching admin user found for: " . htmlspecialchars($target_email);
}
