<?php
// api/leads/field_visibility.php
// Manage field visibility (hide/show fields)

session_start();
header("Content-Type: application/json");

$appEnv = strtolower((string)(getenv('APP_ENV') ?: 'production'));
$debugMode = (isset($_GET['debug']) && $_GET['debug'] === '1') || $appEnv !== 'production';

function respondWithError(int $statusCode, string $message, bool $debugMode = false, ?Throwable $e = null): void {
    http_response_code($statusCode);

    $response = ["error" => $message];
    if ($debugMode && $e !== null) {
        $response["debug"] = [
            "type" => get_class($e),
            "message" => $e->getMessage(),
            "file" => $e->getFile(),
            "line" => $e->getLine()
        ];
    }

    echo json_encode($response);
    exit;
}

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e) use ($debugMode): void {
    error_log("Field visibility unhandled exception: " . $e->getMessage());
    respondWithError(500, "Internal server error", $debugMode, $e);
});

register_shutdown_function(function () use ($debugMode): void {
    $fatal = error_get_last();
    if ($fatal === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($fatal['type'], $fatalTypes, true)) {
        return;
    }

    error_log("Field visibility fatal error: {$fatal['message']} in {$fatal['file']}:{$fatal['line']}");
    if (!headers_sent()) {
        header("Content-Type: application/json");
        http_response_code(500);
    }

    $response = ["error" => "Internal server error"];
    if ($debugMode) {
        $response["debug"] = [
            "type" => "FatalError",
            "message" => $fatal['message'],
            "file" => $fatal['file'],
            "line" => $fatal['line']
        ];
    }

    echo json_encode($response);
});

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

try {
    require_once __DIR__ . '/../../config/db.php';
} catch (Throwable $e) {
    error_log("Field visibility DB bootstrap error: " . $e->getMessage());
    respondWithError(500, "Database connection failed", $debugMode, $e);
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];

function ensureFieldVisibilityTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS field_visibility (
            id INT AUTO_INCREMENT PRIMARY KEY,
            org_id INT NOT NULL,
            field_name VARCHAR(100) NOT NULL,
            field_type ENUM('standard', 'custom') DEFAULT 'custom',
            is_visible BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_org_field (org_id, field_name),
            FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

try {
    $stmt = $pdo->prepare("SELECT id, org_id, is_super_admin FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(["error" => "User not found"]);
        exit;
    }

    $org_id = (int)($_SESSION['org_id'] ?? $user['org_id']);

    if ($method === 'GET') {
        try {
            ensureFieldVisibilityTable($pdo);

            $stmt = $pdo->prepare("
                SELECT field_name, field_type, is_visible 
                FROM field_visibility 
                WHERE org_id = :org_id
            ");
            $stmt->execute([':org_id' => $org_id]);
            $settings = array_map(function ($row) {
                $row['is_visible'] = (bool)$row['is_visible'];
                return $row;
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));

            echo json_encode([
                "success" => true,
                "settings" => $settings
            ]);
        } catch (PDOException $e) {
            error_log("Field visibility GET error: " . $e->getMessage());
            respondWithError(500, "Failed to load field visibility settings", $debugMode, $e);
        }
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid JSON payload"]);
            exit;
        }

        $field_name = $input['field_name'] ?? '';
        $field_type = $input['field_type'] ?? 'custom';
        $is_visible = isset($input['is_visible']) ? (bool)$input['is_visible'] : true;

        if (empty($field_name)) {
            http_response_code(400);
            echo json_encode(["error" => "Field name is required"]);
            exit;
        }

        if (!in_array($field_type, ['standard', 'custom'], true)) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid field type"]);
            exit;
        }

        ensureFieldVisibilityTable($pdo);

        $stmt = $pdo->prepare("
            INSERT INTO field_visibility (org_id, field_name, field_type, is_visible)
            VALUES (:org_id, :field_name, :field_type, :is_visible)
            ON DUPLICATE KEY UPDATE is_visible = VALUES(is_visible), field_type = VALUES(field_type)
        ");
        
        $stmt->execute([
            ':org_id' => $org_id,
            ':field_name' => $field_name,
            ':field_type' => $field_type,
            ':is_visible' => $is_visible ? 1 : 0
        ]);

        echo json_encode([
            "success" => true,
            "message" => "Field visibility updated"
        ]);
    } else {
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
    }
} catch (Exception $e) {
    error_log("Field visibility error: " . $e->getMessage());
    respondWithError(500, "Internal server error", $debugMode, $e);
}
