<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

// Check authentication for API requests
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

function getWhatsAppTemplates() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM whatsapp_templates WHERE is_active = 1 ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching templates: " . $e->getMessage());
        return [];
    }
}

function createWhatsAppTemplate($data) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("\n            INSERT INTO whatsapp_templates (name, message, media_type, media_url, buttons, category, language, header_text, header_media_type, header_media_url, footer_text, placeholders, status) \n            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n        ");
        return $stmt->execute([
            $data['name'],
            $data['message'],
            $data['media_type'] ?? 'none',
            $data['media_url'] ?? null,
            json_encode($data['buttons'] ?? []),
            $data['category'] ?? 'Utility',
            $data['language'] ?? 'en_US',
            $data['header_text'] ?? null,
            $data['header_media_type'] ?? 'none',
            $data['header_media_url'] ?? null,
            $data['footer_text'] ?? null,
            json_encode($data['placeholders'] ?? []),
            $data['status'] ?? 'approved'
        ]);
    } catch (PDOException $e) {
        error_log("Error creating template: " . $e->getMessage());
        return false;
    }
}

function updateWhatsAppTemplate($id, $data) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("\n            UPDATE whatsapp_templates \n            SET name = ?, message = ?, media_type = ?, media_url = ?, buttons = ?, category = ?, language = ?, header_text = ?, header_media_type = ?, header_media_url = ?, footer_text = ?, placeholders = ?, status = ? \n            WHERE id = ?\n        ");
        return $stmt->execute([
            $data['name'],
            $data['message'],
            $data['media_type'] ?? 'none',
            $data['media_url'] ?? null,
            json_encode($data['buttons'] ?? []),
            $data['category'] ?? 'Utility',
            $data['language'] ?? 'en_US',
            $data['header_text'] ?? null,
            $data['header_media_type'] ?? 'none',
            $data['header_media_url'] ?? null,
            $data['footer_text'] ?? null,
            json_encode($data['placeholders'] ?? []),
            $data['status'] ?? 'approved',
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
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => getWhatsAppTemplates()
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        
        header('Content-Type: application/json');
        echo json_encode(['success' => $result]);
        exit;
    }
}
?>
