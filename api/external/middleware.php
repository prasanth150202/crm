<?php
// api/external/middleware.php

function validateApiKey($pdo) {
    $headers = getallheaders();
    $apiKey = null;
    
    if (isset($headers['Authorization'])) {
        $auth = $headers['Authorization'];
        if (strpos($auth, 'Bearer ') === 0) {
            $apiKey = substr($auth, 7);
        }
    }
    
    if (!$apiKey) {
        http_response_code(401);
        echo json_encode(["error" => "Missing API key in Authorization header"]);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM organizations WHERE api_key = ?");
        $stmt->execute([$apiKey]);
        $org = $stmt->fetch();
        
        if (!$org) {
            http_response_code(401);
            echo json_encode(["error" => "Invalid API key"]);
            exit;
        }
        
        return $org['id'];
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "API key validation failed"]);
        exit;
    }
}

function corsHeaders() {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}