<?php
// Web endpoint for admin panel reports
require_once 'config.php';
require_once 'jwt_helper.php';

$endpoint = $_GET['endpoint'] ?? '';

switch ($endpoint) {
    case 'dashboard':
        getDashboardStats();
        break;
    case 'pnl':
        getPnLReport();
        break;
    case 'top-performers':
        getTopPerformers();
        break;
    case 'activity-feed':
        getActivityFeed();
        break;
    case 'campaign-performance':
        getCampaignPerformance();
        break;
    case 'enhanced-stats':
        getEnhancedStats();
        break;
    default:
        sendResponse(false, 'Invalid endpoint');
}

function getDashboardStats() {
    $db = getDB();
    
    $stmt = $db->query("SELECT COUNT(*) as total_leads FROM leads");
    $totalLeads = $stmt->fetch()['total_leads'];
    
    $stmt = $db->query("SELECT la.status, COUNT(*) as count 
        FROM lead_assignments la 
        GROUP BY la.status");
    $leadsByStatus = $stmt->fetchAll();
    
    $stmt = $db->query("SELECT COUNT(*) as active_agents 
        FROM users WHERE role = 'agent' AND active = 1");
    $activeAgents = $stmt->fetch()['active_agents'];
    
    $stmt = $db->query("SELECT 
        COUNT(*) as today_leads,
        COUNT(CASE WHEN ar.response_status = 'qualified' THEN 1 END) as today_qualified
        FROM leads l
        LEFT JOIN agent_responses ar ON l.id = ar.lead_id
        WHERE DATE(l.created_at) = CURDATE()");
    $todayStats = $stmt->fetch();
    
    sendResponse(true, 'Dashboard stats retrieved', [
        'total_leads' => $totalLeads,
        'leads_by_status' => $leadsByStatus,
        'active_agents' => $activeAgents,
        'today_leads' => $todayStats['today_leads'],
        'today_qualified' => $todayStats['today_qualified']
    ]);
}

function getPnLReport() {
    $period = $_GET['period'] ?? 'month';
    $db = getDB();
    
    $dateFilter = getDateFilter($period, 'ar.created_at');
    
    // Build the query with the date filter
    $sql = "SELECT 
        DATE(ar.created_at) as date,
        SUM(ar.price_offered) as revenue
        FROM agent_responses ar 
        WHERE ar.response_status = 'qualified'";
    
    // Add date filter if it exists
    if (!empty($dateFilter)) {
        $sql .= " " . $dateFilter;
    }
    
    $sql .= " GROUP BY DATE(ar.created_at) ORDER BY date";
    
    $stmt = $db->query($sql);
    $revenue = $stmt->fetchAll();
    
    $pnlData = [];
    foreach ($revenue as $r) {
        $pnlData[] = [
            'date' => $r['date'],
            'revenue' => (float)$r['revenue'],
            'ad_spend' => 0,
            'expenses' => 0,
            'profit' => (float)$r['revenue']
        ];
    }
    
    $totals = [
        'total_revenue' => array_sum(array_column($pnlData, 'revenue')),
        'total_ad_spend' => 0,
        'total_expenses' => 0,
        'total_profit' => array_sum(array_column($pnlData, 'profit'))
    ];
    
    sendResponse(true, 'P&L report generated', [
        'data' => $pnlData,
        'totals' => $totals
    ]);
}

function getTopPerformers() {
    $period = $_GET['period'] ?? 'month';
    $limit = (int)($_GET['limit'] ?? 10);
    
    $dateFilter = getDateFilter($period, 'la.assigned_at');
    
    $db = getDB();
    
    // Build the query with the date filter
    $sql = "SELECT u.name, u.email,
        COUNT(la.id) as total_leads,
        COUNT(CASE WHEN ar.response_status = 'qualified' THEN 1 END) as qualified_leads,
        COALESCE(SUM(ar.price_offered), 0) as total_revenue,
        ROUND(COUNT(CASE WHEN ar.response_status = 'qualified' THEN 1 END) * 100.0 / COUNT(la.id), 2) as conversion_rate
        FROM users u
        JOIN agents a ON u.id = a.id
        LEFT JOIN lead_assignments la ON a.id = la.agent_id
        LEFT JOIN agent_responses ar ON la.lead_id = ar.lead_id AND la.agent_id = ar.agent_id
        WHERE u.role = 'agent' AND u.active = 1";
    
    // Add date filter to WHERE clause if it exists
    if (!empty($dateFilter)) {
        $sql .= " " . $dateFilter;
    }
    
    $sql .= " GROUP BY u.id
        HAVING total_leads > 0
        ORDER BY qualified_leads DESC, total_revenue DESC
        LIMIT $limit";
    
    $stmt = $db->query($sql);
    $performers = $stmt->fetchAll();
    
    sendResponse(true, 'Top performers retrieved', $performers);
}

function getActivityFeed() {
    $limit = (int)($_GET['limit'] ?? 20);
    $db = getDB();
    
    $activities = [];
    
    // Recent lead assignments
    $stmt = $db->prepare("SELECT 
        'lead_assigned' as type,
        CONCAT('Lead assigned to ', u.name) as message,
        la.assigned_at as timestamp,
        l.full_name as lead_name,
        u.name as agent_name
        FROM lead_assignments la
        JOIN leads l ON la.lead_id = l.id
        JOIN users u ON la.agent_id = u.id
        ORDER BY la.assigned_at DESC
        LIMIT ?");
    $stmt->execute([$limit]);
    $assignments = $stmt->fetchAll();
    
    foreach ($assignments as $a) {
        $activities[] = [
            'type' => $a['type'],
            'message' => $a['message'],
            'timestamp' => $a['timestamp'],
            'lead_name' => $a['lead_name'],
            'agent_name' => $a['agent_name']
        ];
    }
    
    // Recent responses
    $stmt = $db->prepare("SELECT 
        'response' as type,
        CONCAT('Response submitted: ', ar.response_status) as message,
        ar.created_at as timestamp,
        l.full_name as lead_name,
        u.name as agent_name
        FROM agent_responses ar
        JOIN leads l ON ar.lead_id = l.id
        JOIN users u ON ar.agent_id = u.id
        ORDER BY ar.created_at DESC
        LIMIT ?");
    $stmt->execute([$limit]);
    $responses = $stmt->fetchAll();
    
    foreach ($responses as $r) {
        $activities[] = [
            'type' => $r['type'],
            'message' => $r['message'],
            'timestamp' => $r['timestamp'],
            'lead_name' => $r['lead_name'],
            'agent_name' => $r['agent_name']
        ];
    }
    
    // Sort by timestamp and limit
    usort($activities, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    $activities = array_slice($activities, 0, $limit);
    
    sendResponse(true, 'Activity feed retrieved', $activities);
}

function getCampaignPerformance() {
    $period = $_GET['period'] ?? 'month';
    $limit = (int)($_GET['limit'] ?? 10);
    $db = getDB();
    
    $dateFilter = getDateFilter($period, 'l.created_at');
    
    $sql = "SELECT 
        l.campaign_name,
        COUNT(l.id) as total_leads,
        COUNT(CASE WHEN ar.response_status = 'qualified' THEN 1 END) as qualified_leads,
        COALESCE(SUM(ar.price_offered), 0) as revenue,
        ROUND(COUNT(CASE WHEN ar.response_status = 'qualified' THEN 1 END) * 100.0 / COUNT(l.id), 2) as conversion_rate
        FROM leads l
        LEFT JOIN agent_responses ar ON l.id = ar.lead_id
        WHERE l.campaign_name IS NOT NULL";
    
    if (!empty($dateFilter)) {
        $sql .= " " . str_replace('ar.created_at', 'l.created_at', $dateFilter);
    }
    
    $sql .= " GROUP BY l.campaign_name
        ORDER BY revenue DESC, qualified_leads DESC
        LIMIT $limit";
    
    $stmt = $db->query($sql);
    $campaigns = $stmt->fetchAll();
    
    sendResponse(true, 'Campaign performance retrieved', $campaigns);
}

function getEnhancedStats() {
    $db = getDB();
    
    // Total revenue
    $stmt = $db->query("SELECT COALESCE(SUM(price_offered), 0) as total_revenue 
        FROM agent_responses WHERE response_status = 'qualified'");
    $totalRevenue = $stmt->fetch()['total_revenue'];
    
    // Today's revenue
    $stmt = $db->query("SELECT COALESCE(SUM(ar.price_offered), 0) as today_revenue
        FROM agent_responses ar
        WHERE ar.response_status = 'qualified' AND DATE(ar.created_at) = CURDATE()");
    $todayRevenue = $stmt->fetch()['today_revenue'];
    
    // Pending leads
    $stmt = $db->query("SELECT COUNT(*) as pending_leads
        FROM lead_assignments WHERE status = 'assigned'");
    $pendingLeads = $stmt->fetch()['pending_leads'];
    
    // Conversion rate
    $stmt = $db->query("SELECT 
        COUNT(CASE WHEN ar.response_status = 'qualified' THEN 1 END) as qualified,
        COUNT(la.id) as total
        FROM lead_assignments la
        LEFT JOIN agent_responses ar ON la.lead_id = ar.lead_id");
    $conv = $stmt->fetch();
    $conversionRate = $conv['total'] > 0 ? round(($conv['qualified'] / $conv['total']) * 100, 2) : 0;
    
    // Online agents (logged in within last 30 minutes)
    $stmt = $db->query("SELECT COUNT(*) as online_agents
        FROM agents a
        JOIN users u ON a.id = u.id
        WHERE u.active = 1 AND a.last_login >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    $onlineAgents = $stmt->fetch()['online_agents'];
    
    // Average response time (hours between assignment and first response)
    $stmt = $db->query("SELECT AVG(TIMESTAMPDIFF(HOUR, la.assigned_at, ar.created_at)) as avg_response_time
        FROM lead_assignments la
        JOIN agent_responses ar ON la.lead_id = ar.lead_id
        WHERE ar.created_at > la.assigned_at");
    $avgResponseTime = $stmt->fetch()['avg_response_time'] ?? 0;
    
    // Leads change (today vs yesterday)
    $stmt = $db->query("SELECT 
        (SELECT COUNT(*) FROM leads WHERE DATE(created_at) = CURDATE()) as today,
        (SELECT COUNT(*) FROM leads WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)) as yesterday");
    $leadChange = $stmt->fetch();
    $leadsChange = $leadChange['yesterday'] > 0 
        ? round((($leadChange['today'] - $leadChange['yesterday']) / $leadChange['yesterday']) * 100, 1)
        : 0;
    
    sendResponse(true, 'Enhanced stats retrieved', [
        'total_revenue' => (float)$totalRevenue,
        'today_revenue' => (float)$todayRevenue,
        'pending_leads' => (int)$pendingLeads,
        'conversion_rate' => (float)$conversionRate,
        'online_agents' => (int)$onlineAgents,
        'avg_response_time' => round((float)$avgResponseTime, 1),
        'leads_change' => (float)$leadsChange
    ]);
}

function getDateFilter($period, $column = 'date') {
    // Whitelist allowed column names to prevent SQL injection
    $allowedColumns = ['date', 'created_at', 'assigned_at', 'ar.created_at', 'la.assigned_at', 'l.created_at'];
    $safeColumn = in_array($column, $allowedColumns) ? $column : 'date';
    
    switch ($period) {
        case 'day':
            return "AND DATE($safeColumn) = CURDATE()";
        case 'week':
            return "AND $safeColumn >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        case 'month':
            return "AND $safeColumn >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        default:
            return "";
    }
}
?>