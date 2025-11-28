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

require_once 'config.php';
require_once 'jwt_helper.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($method === 'GET' && str_contains($path, '/reports/dashboard')) {
    getDashboardStats();
} elseif ($method === 'GET' && str_contains($path, '/reports/weekly')) {
    getWeeklyStats();
} else {
    sendResponse(false, 'Invalid endpoint');
}

function getDashboardStats() {
    $token = validateJWT();
    if (!$token) sendResponse(false, "Authentication required");
    
    $db = getDB();
    
    try {
        $agentId = $token['role'] === 'agent' ? $token['user_id'] : null;
        
        // Total leads
        if ($agentId) {
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM lead_assignments WHERE agent_id = ?");
            $stmt->execute([$agentId]);
        } else {
            $stmt = $db->query("SELECT COUNT(*) as total FROM leads");
        }
        $totalLeads = $stmt->fetch()['total'];
        
        // Qualified leads
        if ($agentId) {
            $stmt = $db->prepare("SELECT COUNT(*) as qualified FROM lead_assignments WHERE agent_id = ? AND status = 'qualified'");
            $stmt->execute([$agentId]);
        } else {
            $stmt = $db->query("SELECT COUNT(*) as qualified FROM lead_assignments WHERE status = 'qualified'");
        }
        $qualifiedLeads = $stmt->fetch()['qualified'];
        
        // Pending leads
        if ($agentId) {
            $stmt = $db->prepare("SELECT COUNT(*) as pending FROM lead_assignments WHERE agent_id = ? AND status = 'assigned'");
            $stmt->execute([$agentId]);
        } else {
            $stmt = $db->query("SELECT COUNT(*) as pending FROM lead_assignments WHERE status = 'assigned'");
        }
        $pendingLeads = $stmt->fetch()['pending'];
        
        // Conversion rate
        $conversionRate = $totalLeads > 0 ? round(($qualifiedLeads / $totalLeads) * 100, 2) : 0;
        
        // Today's stats
        if ($agentId) {
            $stmt = $db->prepare("SELECT COUNT(*) as contacted_today FROM lead_assignments WHERE agent_id = ? AND status = 'contacted' AND DATE(assigned_at) = CURDATE()");
            $stmt->execute([$agentId]);
        } else {
            $stmt = $db->query("SELECT COUNT(*) as contacted_today FROM lead_assignments WHERE status = 'contacted' AND DATE(assigned_at) = CURDATE()");
        }
        $contactedToday = $stmt->fetch()['contacted_today'];
        
        if ($agentId) {
            $stmt = $db->prepare("SELECT COUNT(*) as qualified_today FROM lead_assignments WHERE agent_id = ? AND status = 'qualified' AND DATE(assigned_at) = CURDATE()");
            $stmt->execute([$agentId]);
        } else {
            $stmt = $db->query("SELECT COUNT(*) as qualified_today FROM lead_assignments WHERE status = 'qualified' AND DATE(assigned_at) = CURDATE()");
        }
        $qualifiedToday = $stmt->fetch()['qualified_today'];
        
        sendResponse(true, 'Dashboard stats retrieved', [
            'total_leads' => $totalLeads,
            'qualified_leads' => $qualifiedLeads,
            'pending_leads' => $pendingLeads,
            'conversion_rate' => $conversionRate,
            'contacted_today' => $contactedToday,
            'qualified_today' => $qualifiedToday
        ]);
        
    } catch (Exception $e) {
        sendResponse(false, 'Failed to get dashboard stats: ' . $e->getMessage());
    }
}

function getWeeklyStats() {
    $token = validateJWT();
    if (!$token) sendResponse(false, "Authentication required");
    
    $db = getDB();
    $agentId = $token['role'] === 'agent' ? $token['user_id'] : ($_GET['agent_id'] ?? null);
    
    try {
        $whereClause = $agentId ? "WHERE la.agent_id = ?" : "";
        $params = $agentId ? [$agentId] : [];
        
        $stmt = $db->prepare("
            SELECT 
                DAYNAME(la.assigned_at) as day_name,
                DAYOFWEEK(la.assigned_at) as day_order,
                COUNT(CASE WHEN la.status = 'contacted' THEN 1 END) as contacted,
                COUNT(CASE WHEN la.status = 'qualified' THEN 1 END) as qualified,
                COUNT(CASE WHEN la.status = 'payment_completed' THEN 1 END) as payment_completed
            FROM lead_assignments la
            $whereClause
            AND la.assigned_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(la.assigned_at), DAYNAME(la.assigned_at), DAYOFWEEK(la.assigned_at)
            ORDER BY day_order
        ");
        
        $stmt->execute($params);
        $weeklyData = $stmt->fetchAll();
        
        // Fill missing days with zeros
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $result = [];
        
        foreach ($days as $index => $dayName) {
            $found = false;
            foreach ($weeklyData as $data) {
                if ($data['day_name'] === $dayName) {
                    $result[] = [
                        'day_name' => substr($dayName, 0, 3),
                        'contacted' => (int)$data['contacted'],
                        'qualified' => (int)$data['qualified'],
                        'payment_completed' => (int)$data['payment_completed']
                    ];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $result[] = [
                    'day_name' => substr($dayName, 0, 3),
                    'contacted' => 0,
                    'qualified' => 0,
                    'payment_completed' => 0
                ];
            }
        }
        
        sendResponse(true, 'Weekly stats retrieved', $result);
        
    } catch (Exception $e) {
        sendResponse(false, 'Failed to get weekly stats: ' . $e->getMessage());
    }
}
?>