<?php
// api/payment_webhooks/get_payment_history.php
// Returns payment history for the logged-in user (API endpoint)

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$pdo = getDb();

$stmt = $pdo->prepare('SELECT p.id, p.payment_id, p.amount, p.status, p.paid_at, p.subscription_id
                      FROM payments p
                      WHERE p.user_id = ?
                      ORDER BY p.paid_at DESC');
$stmt->execute([$userId]);
$payments = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'payments' => $payments
]);
