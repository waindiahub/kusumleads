<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    require_once 'includes/config.php';
    
    // Check if PDO connection exists
    if (!isset($pdo) || !$pdo) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Config error: ' . $e->getMessage()]);
    exit;
}

function getWhatsAppTemplates() {
    global $pdo;
    if (!$pdo) {
        throw new Exception('Database connection not available');
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM whatsapp_templates WHERE is_active = 1 ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching templates: " . $e->getMessage());
        throw new Exception('Database error: ' . $e->getMessage());
    }
}

function createWhatsAppTemplate($data) {
    global $pdo;
    if (!$pdo) {
        throw new Exception('Database connection not available');
    }
    try {
        $stmt = $pdo->prepare("
            INSERT INTO whatsapp_templates (name, message, media_type, media_url, buttons) 
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $data['name'],
            $data['message'],
            $data['media_type'] ?? 'none',
            $data['media_url'] ?? null,
            json_encode($data['buttons'] ?? [])
        ]);
    } catch (PDOException $e) {
        error_log("Error creating template: " . $e->getMessage());
        throw new Exception('Database error: ' . $e->getMessage());
    }
}

function updateWhatsAppTemplate($id, $data) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            UPDATE whatsapp_templates 
            SET name = ?, message = ?, media_type = ?, media_url = ?, buttons = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['name'],
            $data['message'],
            $data['media_type'] ?? 'none',
            $data['media_url'] ?? null,
            json_encode($data['buttons'] ?? []),
            $id
        ]);
    } catch (PDOException $e) {
        error_log("Error updating template: " . $e->getMessage());
        return false;
    }
}

function deleteWhatsAppTemplate($id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE whatsapp_templates SET is_active = 0 WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("Error deleting template: " . $e->getMessage());
        return false;
    }
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $templates = getWhatsAppTemplates();
        echo json_encode([
            'success' => true,
            'data' => $templates
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'GET error: ' . $e->getMessage()
        ]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['action'])) {
            switch ($input['action']) {
                case 'create':
                    $result = createWhatsAppTemplate($input['data']);
                    break;
                case 'update':
                    $result = updateWhatsAppTemplate($input['id'], $input['data']);
                    break;
                case 'delete':
                    $result = deleteWhatsAppTemplate($input['id']);
                    break;
                default:
                    $result = false;
            }
            
            echo json_encode(['success' => $result]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No action specified']);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'POST error: ' . $e->getMessage()
        ]);
    }
    exit;
}
?>