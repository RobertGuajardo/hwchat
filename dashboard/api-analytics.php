<?php
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

if (!isAuthenticated() || !canAccessAnalytics()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$chart  = $_GET['chart'] ?? '';
$after  = $_GET['after'] ?? null;
$before = $_GET['before'] ?? null;

$validCharts = ['conversations_over_time', 'topics', 'intent', 'sentiment', 'price_ranges', 'objections', 'builders'];
if (!in_array($chart, $validCharts)) {
    echo json_encode(['success' => false, 'error' => 'Invalid chart type. Valid: ' . implode(', ', $validCharts)]);
    exit;
}

// Scope tenant access
if (isSuperAdmin()) {
    $tenantId = !empty($_GET['tenant']) ? $_GET['tenant'] : null;
} else {
    $tenantId = getTenantId();
}

$data = Database::getAnalyticsChartData($chart, $tenantId, $after, $before);
echo json_encode(['success' => true, 'data' => $data]);
