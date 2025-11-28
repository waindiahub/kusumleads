<?php
// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Error logging
error_log('Leaderboard API called - Method: ' . $_SERVER['REQUEST_METHOD'] . ', URI: ' . $_SERVER['REQUEST_URI']);

try {
    require_once 'includes/config.php';
    require_once 'includes/jwt_helper.php';
    
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    error_log('Parsed path: ' . $path);
    
    if ($method === 'GET' && strpos($path, '/leaderboard') !== false) {
        error_log('Calling getLeaderboard function');
        getLeaderboard();
    } else {
        error_log('Invalid endpoint - Method: ' . $method . ', Path: ' . $path);
        sendResponse(false, 'Invalid endpoint - Method: ' . $method . ', Path: ' . $path);
    }
} catch (Exception $e) {
    error_log('Leaderboard API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function getLeaderboard() {
    error_log('getLeaderboard function started');
    
    try {
        $token = validateJWT();
        error_log('JWT validation result: ' . ($token ? 'valid' : 'invalid'));
        
        if (!$token) {
            error_log('Authentication failed - no valid token');
            sendResponse(false, "Authentication required");
            return;
        }
    
        $period = $_GET['period'] ?? 'week';
        $db = getDB();
        $dateFilter = '';
        switch ($period) {
            case 'week':
                $dateFilter = 'AND la.assigned_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                break;
            case 'month':
                $dateFilter = 'AND la.assigned_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                break;
            case 'today':
                $dateFilter = 'AND DATE(la.assigned_at) = CURDATE()';
                break;
        }
        
        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.name,
                COUNT(la.id) as total_leads,
                COUNT(CASE WHEN la.status = 'contacted' THEN 1 END) as contacted_leads,
                COUNT(CASE WHEN la.status = 'qualified' THEN 1 END) as qualified_leads,
                COUNT(CASE WHEN la.status = 'payment_completed' THEN 1 END) as payment_leads,
                COALESCE(SUM(ar.price_offered), 0) as total_revenue,
                ROUND(
                    CASE 
                        WHEN COUNT(la.id) > 0 
                        THEN (COUNT(CASE WHEN la.status IN ('qualified', 'payment_completed') THEN 1 END) * 100.0 / COUNT(la.id))
                        ELSE 0 
                    END, 2
                ) as conversion_rate
            FROM users u
            JOIN agents a ON u.id = a.id
            LEFT JOIN lead_assignments la ON u.id = la.agent_id $dateFilter
            LEFT JOIN agent_responses ar ON la.lead_id = ar.lead_id AND ar.agent_id = u.id
            WHERE u.role = 'agent' AND u.active = 1
            GROUP BY u.id, u.name
            HAVING total_leads > 0
            ORDER BY 
                payment_leads DESC,
                qualified_leads DESC, 
                conversion_rate DESC,
                total_revenue DESC
            LIMIT 20
        ");
        
        $stmt->execute();
        $leaderboard = $stmt->fetchAll();
        
        foreach ($leaderboard as $index => &$agent) {
            $agent['rank'] = $index + 1;
            $agent['badge'] = getBadge($agent['rank'], $agent);
            $agent['points'] = calculatePoints($agent);
        }
        
        sendResponse(true, 'Leaderboard retrieved', $leaderboard);
        
    } catch (Exception $e) {
        sendResponse(false, 'Failed to get leaderboard: ' . $e->getMessage());
    }
}

function getBadge($rank, $agent) {
    if ($rank === 1) return 'gold';
    if ($rank === 2) return 'silver';
    if ($rank === 3) return 'bronze';
    if ($agent['conversion_rate'] >= 50) return 'star';
    if ($agent['total_leads'] >= 20) return 'active';
    return 'member';
}

function calculatePoints($agent) {
    $points = 0;
    $points += $agent['contacted_leads'] * 2;
    $points += $agent['qualified_leads'] * 10;
    $points += $agent['payment_leads'] * 25;
    $points += ($agent['total_revenue'] / 1000) * 5;
    return round($points);
}
?>