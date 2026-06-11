<?php
header("Content-Type: application/json");
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

// Support both JSON and Form Data
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    $data = $_POST;
}

$name     = isset($data['name'])     ? trim($data['name'])     : '';
$email    = isset($data['email'])    ? trim($data['email'])    : '';
$org      = isset($data['org'])      ? trim($data['org'])      : '';
$message  = isset($data['message'])  ? trim($data['message'])  : '';
$plan_tag = isset($data['plan_tag']) ? trim($data['plan_tag']) : '';

if (empty($name) || empty($email) || empty($message)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Name, email and message are required."]);
    exit;
}

try {
    $pdo = getDb();

    // Check if plan_tag column exists, add it if not
    $columns = $pdo->query("SHOW COLUMNS FROM site_leads LIKE 'plan_tag'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE site_leads ADD COLUMN `plan_tag` VARCHAR(100) NULL DEFAULT NULL AFTER `message`");
    }

    $stmt = $pdo->prepare("INSERT INTO site_leads (name, email, organization, message, plan_tag) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, $org, $message, $plan_tag ?: null]);

    $successMsg = $plan_tag === 'Enterprise Plan'
        ? "Thank you for your interest in our Enterprise Plan! Our team will reach out with a custom quote shortly."
        : "Thank you! Your inquiry has been received.";

    echo json_encode(["success" => true, "message" => $successMsg]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server error: " . $e->getMessage()]);
}
