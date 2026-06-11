<?php
header("Content-Type: application/json");
require_once '../../config/db.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$data      = json_decode(file_get_contents('php://input'), true);
$org_id    = (int)$_SESSION['org_id'];
$userId    = (int)$_SESSION['user_id'];
$partnerId = (int)($data['partner_id'] ?? 0);
$type      = $data['type'] ?? 'existing';

if (!$partnerId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'partner_id required']);
    exit;
}

// Verify partner belongs to this org
try {
    $chk = $pdo->prepare("SELECT id FROM partners WHERE id = ? AND org_id = ?");
    $chk->execute([$partnerId, $org_id]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Partner not found']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

try {
    if ($type === 'existing') {
        $leadId = (int)($data['lead_id'] ?? 0);
        if (!$leadId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'lead_id required for existing referral']);
            exit;
        }

        // Avoid duplicates
        $dup = $pdo->prepare("SELECT id FROM partner_referrals WHERE partner_id = ? AND lead_id = ?");
        $dup->execute([$partnerId, $leadId]);
        if ($dup->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'This lead is already a referral for this partner']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO partner_referrals (partner_id, org_id, lead_id, type, created_by)
            VALUES (?, ?, ?, 'existing', ?)
        ");
        $stmt->execute([$partnerId, $org_id, $leadId, $userId]);

    } else {
        $refName = trim($data['ref_name'] ?? '');
        if (!$refName) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ref_name required for new referral']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO partner_referrals
                (partner_id, org_id, ref_name, ref_email, ref_phone, ref_company, type, created_by)
            VALUES (?, ?, ?, ?, ?, ?, 'new', ?)
        ");
        $stmt->execute([
            $partnerId,
            $org_id,
            $refName,
            trim($data['ref_email']   ?? '') ?: null,
            trim($data['ref_phone']   ?? '') ?: null,
            trim($data['ref_company'] ?? '') ?: null,
            $userId,
        ]);
    }

    echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
