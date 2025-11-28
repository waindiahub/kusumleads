<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$period = $_GET['period'] ?? 'month';
$db = getDB();

// Get date filter
$dateFilter = '';
switch ($period) {
    case 'day':
        $dateFilter = "AND DATE(ar.created_at) = CURDATE()";
        break;
    case 'week':
        $dateFilter = "AND ar.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $dateFilter = "AND ar.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
}

// Get P&L data
$sql = "SELECT 
    DATE(ar.created_at) as date,
    SUM(ar.price_offered) as revenue
    FROM agent_responses ar 
    WHERE ar.response_status = 'qualified' $dateFilter
    GROUP BY DATE(ar.created_at) ORDER BY date";

$stmt = $db->query($sql);
$pnlData = $stmt->fetchAll();

// Get top performers
$sql = "SELECT u.name, u.email,
    COUNT(la.id) as total_leads,
    COUNT(CASE WHEN ar.response_status = 'qualified' THEN 1 END) as qualified_leads,
    COALESCE(SUM(ar.price_offered), 0) as total_revenue,
    ROUND(COUNT(CASE WHEN ar.response_status = 'qualified' THEN 1 END) * 100.0 / COUNT(la.id), 2) as conversion_rate
    FROM users u
    JOIN agents a ON u.id = a.id
    LEFT JOIN lead_assignments la ON a.id = la.agent_id
    LEFT JOIN agent_responses ar ON la.lead_id = ar.lead_id AND la.agent_id = ar.agent_id
    WHERE u.role = 'agent' AND u.active = 1 $dateFilter
    GROUP BY u.id
    HAVING total_leads > 0
    ORDER BY qualified_leads DESC, total_revenue DESC
    LIMIT 10";

$stmt = $db->query($sql);
$performers = $stmt->fetchAll();

// Calculate totals
$totalRevenue = array_sum(array_column($pnlData, 'revenue'));
$totalLeads = array_sum(array_column($performers, 'total_leads'));
$totalQualified = array_sum(array_column($performers, 'qualified_leads'));

// Export as CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="report_' . $period . '_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

// Header
fputcsv($output, ['CRM Report - ' . ucfirst($period)]);
fputcsv($output, ['Generated: ' . date('Y-m-d H:i:s')]);
fputcsv($output, []);

// Summary
fputcsv($output, ['Summary']);
fputcsv($output, ['Total Revenue', '₹' . number_format($totalRevenue)]);
fputcsv($output, ['Total Leads', $totalLeads]);
fputcsv($output, ['Total Qualified', $totalQualified]);
fputcsv($output, ['Conversion Rate', $totalLeads > 0 ? round(($totalQualified / $totalLeads) * 100, 2) . '%' : '0%']);
fputcsv($output, []);

// P&L Data
fputcsv($output, ['Profit & Loss Data']);
fputcsv($output, ['Date', 'Revenue']);
foreach ($pnlData as $row) {
    fputcsv($output, [$row['date'], '₹' . number_format($row['revenue'])]);
}
fputcsv($output, []);

// Top Performers
fputcsv($output, ['Top Performing Agents']);
fputcsv($output, ['Agent Name', 'Total Leads', 'Qualified Leads', 'Conversion Rate', 'Revenue']);
foreach ($performers as $performer) {
    fputcsv($output, [
        $performer['name'],
        $performer['total_leads'],
        $performer['qualified_leads'],
        $performer['conversion_rate'] . '%',
        '₹' . number_format($performer['total_revenue'])
    ]);
}

fclose($output);
exit();

