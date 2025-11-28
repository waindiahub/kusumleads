<?php
require_once 'config.php';
require_once 'jwt_helper.php';
require_once 'onesignal_helper.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Route handling
if ($method === 'GET' && strpos($path, '/agents/pusher_config') !== false) {
    getPusherConfig();
} elseif ($method === 'GET' && strpos($path, '/agents') !== false) {
    getAgents();
} elseif ($method === 'POST' && preg_match('/\/agents\/(\d+)\/player_id/', $path, $matches)) {
    updateAgentPlayerId($matches[1]);
} elseif ($method === 'POST' && strpos($path, '/agents') !== false) {
    createAgent();
} else {
    sendResponse(false, 'Invalid endpoint');
}

function getAgents() {
    try {
        $token = validateJWT();
        if (!$token || $token['role'] !== 'admin') {
            error_log("Unauthorized access to getAgents");
            sendResponse(false, 'Admin access required');
        }
        
        $db = getDB();
        $stmt = $db->prepare("SELECT u.id, u.name, u.email, u.phone, u.active, 
            a.device_token, a.assigned_forms, a.last_assignment,
            COUNT(la.id) as total_leads,
            COUNT(CASE WHEN la.status = 'qualified' THEN 1 END) as qualified_leads
            FROM users u
            LEFT JOIN agents a ON u.id = a.id
            LEFT JOIN lead_assignments la ON a.id = la.agent_id
            WHERE u.role = 'agent'
            GROUP BY u.id
            ORDER BY u.name");
        $stmt->execute();
        $agents = $stmt->fetchAll();
        
        error_log("API getAgents: Found " . count($agents) . " agents");
        
        // Parse assigned_forms JSON
        foreach ($agents as &$agent) {
            $agent['assigned_forms'] = json_decode($agent['assigned_forms'] ?? '[]', true);
            $agent['device_connected'] = !empty($agent['device_token']);
        }
        
        sendResponse(true, 'Agents retrieved successfully', $agents);
    } catch (Exception $e) {
        error_log("Error in getAgents: " . $e->getMessage());
        sendResponse(false, 'Failed to retrieve agents');
    }
}

function createAgent() {
    $token = validateJWT();
    if (!$token || $token['role'] !== 'admin') {
        sendResponse(false, 'Admin access required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['name']) || !isset($input['email']) || !isset($input['password'])) {
        sendResponse(false, 'Name, email and password required');
    }
    
    $db = getDB();
    
    // Check if email exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$input['email']]);
    if ($stmt->fetch()) {
        sendResponse(false, 'Email already exists');
    }
    
    $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);
    
    try {
        $db->beginTransaction();
        
        // Insert user
        $stmt = $db->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'agent')");
        $stmt->execute([$input['name'], $input['email'], $input['phone'] ?? null, $passwordHash]);
        $userId = $db->lastInsertId();
        
        // Insert agent
        $assignedForms = json_encode($input['assigned_forms'] ?? []);
        $stmt = $db->prepare("INSERT INTO agents (id, assigned_forms) VALUES (?, ?)");
        $stmt->execute([$userId, $assignedForms]);
        
        $db->commit();
        
        sendResponse(true, 'Agent created successfully', ['agent_id' => $userId]);
    } catch (Exception $e) {
        $db->rollBack();
        sendResponse(false, 'Failed to create agent: ' . $e->getMessage());
    }
}

function updateAgentPlayerId($agentId) {
    $token = validateJWT();
    if (!$token) {
        sendResponse(false, 'Authentication required');
    }
    
    // Agents can only update their own player ID, admins can update any
    if ($token['role'] === 'agent' && $token['user_id'] != $agentId) {
        sendResponse(false, 'Access denied');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['player_id'])) {
        sendResponse(false, 'Player ID required');
    }
    
    if (updateOneSignalPlayerId($agentId, $input['player_id'])) {
        sendResponse(true, 'Player ID updated successfully');
    } else {
        sendResponse(false, 'Failed to update player ID');
    }
}

function getPusherConfig() {
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $key = getSetting('pusher_key');
    $cluster = getSetting('pusher_cluster');
    if (!$key || !$cluster) sendResponse(false, 'Pusher not configured');
    sendResponse(true, 'OK', ['key' => $key, 'cluster' => $cluster, 'agent_id' => $token['user_id']]);
}
?>
