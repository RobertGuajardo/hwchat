<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/regions.php';

header('Content-Type: application/json');

if (!isAuthenticated() || !canAccessAnalytics()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$chart  = $_GET['chart'] ?? '';
$after  = $_GET['after'] ?? null;
$before = $_GET['before'] ?? null;
$region = $_GET['region'] ?? '';
$tenant = $_GET['tenant'] ?? '';

$validCharts = ['conversations_over_time', 'topics', 'intent', 'sentiment', 'price_ranges', 'objections', 'builders'];
if (!in_array($chart, $validCharts)) {
    echo json_encode(['success' => false, 'error' => 'Invalid chart type']);
    exit;
}

$db = Database::db();

// ─── Determine effective tenantId and region ───
$tenantId = null;
$effectiveRegion = null;

if (isSuperAdmin()) {
    if ($tenant) {
        $tenantId = $tenant;
    } elseif ($region && in_array($region, array_keys(REGIONS))) {
        $effectiveRegion = $region;
    }
    // else: all communities (tenantId null, no region filter)
} elseif (isRegionalAdmin()) {
    $myRegion = getUserRegion();
    if ($tenant) {
        $stmt = $db->prepare('SELECT id FROM tenants WHERE id = :id AND region = :region');
        $stmt->execute(['id' => $tenant, 'region' => $myRegion]);
        $tenantId = $stmt->fetch() ? $tenant : null;
    } else {
        $effectiveRegion = $myRegion;
    }
} else {
    // tenant_admin / builder
    $tenantId = getTenantId();
}

// ─── If specific tenant, use the standard method ───
if ($tenantId) {
    $data = Database::getAnalyticsChartData($chart, $tenantId, $after, $before);
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// ─── For region-scoped or all-communities queries, get tenant IDs and iterate ───
if ($effectiveRegion) {
    $stmt = $db->prepare('SELECT id FROM tenants WHERE region = :r');
    $stmt->execute(['r' => $effectiveRegion]);
    $regionIds = array_column($stmt->fetchAll(), 'id');
} else {
    // Superadmin "all" — all tenants with region
    $stmt = $db->query('SELECT id FROM tenants WHERE region IS NOT NULL');
    $regionIds = array_column($stmt->fetchAll(), 'id');
}

if (empty($regionIds)) {
    echo json_encode(['success' => true, 'data' => ['labels' => [], 'datasets' => []]]);
    exit;
}

// Build a parameterized tenant_id IN clause and query chat_analytics directly
$placeholders = [];
$params = [];
foreach ($regionIds as $i => $id) {
    $key = "tid_{$i}";
    $placeholders[] = ":{$key}";
    $params[$key] = $id;
}
$inClause = implode(',', $placeholders);

$where = "WHERE tenant_id IN ({$inClause})";
if ($after) { $where .= ' AND session_started_at >= :after'; $params['after'] = $after; }
if ($before) { $where .= ' AND session_started_at <= :before'; $params['before'] = $before . ' 23:59:59'; }

// Execute the chart-specific query (mirrors Database::getAnalyticsChartData logic)
switch ($chart) {
    case 'conversations_over_time':
        $stmt = $db->prepare("SELECT session_started_at::date AS day, COUNT(*)::int AS count FROM chat_analytics {$where} GROUP BY day ORDER BY day ASC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $data = ['labels' => array_column($rows, 'day'), 'datasets' => [['label' => 'Conversations', 'data' => array_map('intval', array_column($rows, 'count'))]]];
        break;
    case 'topics':
        $stmt = $db->prepare("SELECT t AS label, COUNT(*)::int AS count FROM chat_analytics, unnest(topics) AS t {$where} GROUP BY t ORDER BY count DESC LIMIT 15");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $data = ['labels' => array_column($rows, 'label'), 'datasets' => [['label' => 'Topics', 'data' => array_map('intval', array_column($rows, 'count'))]]];
        break;
    case 'intent':
        $stmt = $db->prepare("SELECT intent_level AS label, COUNT(*)::int AS count FROM chat_analytics {$where} GROUP BY intent_level ORDER BY count DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $data = ['labels' => array_column($rows, 'label'), 'datasets' => [['label' => 'Intent', 'data' => array_map('intval', array_column($rows, 'count'))]]];
        break;
    case 'sentiment':
        $stmt = $db->prepare("SELECT sentiment AS label, COUNT(*)::int AS count FROM chat_analytics {$where} GROUP BY sentiment ORDER BY count DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $data = ['labels' => array_column($rows, 'label'), 'datasets' => [['label' => 'Sentiment', 'data' => array_map('intval', array_column($rows, 'count'))]]];
        break;
    case 'price_ranges':
        $stmt = $db->prepare("SELECT CASE WHEN price_range_max < 300000 THEN 'Under 300k' WHEN price_range_min < 400000 AND price_range_max >= 300000 THEN '300k-400k' WHEN price_range_min < 500000 AND price_range_max >= 400000 THEN '400k-500k' ELSE '500k+' END AS label, COUNT(*)::int AS count FROM chat_analytics {$where} AND price_range_min IS NOT NULL GROUP BY label ORDER BY count DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $data = ['labels' => array_column($rows, 'label'), 'datasets' => [['label' => 'Price Ranges', 'data' => array_map('intval', array_column($rows, 'count'))]]];
        break;
    case 'objections':
        $stmt = $db->prepare("SELECT o AS label, COUNT(*)::int AS count FROM chat_analytics, unnest(objections) AS o {$where} GROUP BY o ORDER BY count DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $data = ['labels' => array_column($rows, 'label'), 'datasets' => [['label' => 'Objections', 'data' => array_map('intval', array_column($rows, 'count'))]]];
        break;
    case 'builders':
        $stmt = $db->prepare("SELECT b AS label, COUNT(*)::int AS count FROM chat_analytics, unnest(builders_mentioned) AS b {$where} GROUP BY b ORDER BY count DESC LIMIT 15");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $data = ['labels' => array_column($rows, 'label'), 'datasets' => [['label' => 'Builders', 'data' => array_map('intval', array_column($rows, 'count'))]]];
        break;
    default:
        $data = ['labels' => [], 'datasets' => []];
}

echo json_encode(['success' => true, 'data' => $data]);
