<?php
header("Content-Type: application/json");
require_once '../../config/db.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$org_id = (int)$_SESSION['org_id'];

try {
    // Fetch partners
    $stmt = $pdo->prepare("
        SELECT id, name, company, email, phone, website, created_at
        FROM partners
        WHERE org_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$org_id]);
    $partners = $stmt->fetchAll();

    // Fetch referrals for all partners in one query
    $stmt2 = $pdo->prepare("
        SELECT pr.id, pr.partner_id, pr.lead_id, pr.ref_name, pr.ref_email,
               pr.ref_phone, pr.ref_company, pr.type, pr.status, pr.created_at,
               l.name as lead_name
        FROM partner_referrals pr
        LEFT JOIN leads l ON pr.lead_id = l.id
        WHERE pr.org_id = ?
        ORDER BY pr.created_at DESC
    ");
    $stmt2->execute([$org_id]);
    $allReferrals = $stmt2->fetchAll();

    // Group referrals by partner_id
    $referralMap = [];
    foreach ($allReferrals as $r) {
        $pid = $r['partner_id'];
        if (!isset($referralMap[$pid])) $referralMap[$pid] = [];
        $referralMap[$pid][] = [
            'id'          => $r['id'],
            'leadId'      => $r['lead_id'],
            'leadName'    => $r['lead_name'] ?? $r['ref_name'],
            'ref_name'    => $r['ref_name'],
            'ref_email'   => $r['ref_email'],
            'ref_phone'   => $r['ref_phone'],
            'ref_company' => $r['ref_company'],
            'type'        => $r['type'],
            'status'      => $r['status'],
            'createdAt'   => $r['created_at'],
        ];
    }

    // Attach referrals to partners
    foreach ($partners as &$p) {
        $p['referrals'] = $referralMap[$p['id']] ?? [];
    }

    echo json_encode(['success' => true, 'partners' => $partners]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
