<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/regions.php';
requireAuth();
if (!canAccessAnalytics()) { http_response_code(403); exit; }

$db = Database::db();

$after  = $_GET['after'] ?? null;
$before = $_GET['before'] ?? null;
$region = $_GET['region'] ?? '';
$tenant = $_GET['tenant'] ?? '';

// ─── Role-aware scoping ───
$tenantId = null;
$effectiveRegion = null;

if (isSuperAdmin()) {
    if ($tenant) {
        $tenantId = $tenant;
    } elseif ($region && in_array($region, array_keys(REGIONS))) {
        $effectiveRegion = $region;
    }
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
    $tenantId = getTenantId();
}

// ─── Build WHERE clause ───
$where = 'WHERE 1=1';
$params = [];

if ($tenantId) {
    $where .= ' AND ca.tenant_id = :tenant_id';
    $params['tenant_id'] = $tenantId;
} elseif ($effectiveRegion) {
    $where .= ' AND ca.tenant_id IN (SELECT id FROM tenants WHERE region = :region)';
    $params['region'] = $effectiveRegion;
} elseif (isSuperAdmin()) {
    $where .= ' AND ca.tenant_id IN (SELECT id FROM tenants WHERE region IS NOT NULL)';
}

if ($after) { $where .= ' AND ca.session_started_at >= :after'; $params['after'] = $after; }
if ($before) { $where .= ' AND ca.session_started_at <= :before'; $params['before'] = $before . ' 23:59:59'; }

$stmt = $db->prepare("
    SELECT ca.*, t.display_name AS tenant_name, t.community_name
    FROM chat_analytics ca
    JOIN tenants t ON ca.tenant_id = t.id
    {$where}
    ORDER BY ca.session_started_at DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Build filename
$parts = ['hwchat-analytics'];
if ($tenantId) $parts[] = $tenantId;
if ($effectiveRegion) $parts[] = $effectiveRegion;
if ($after) $parts[] = $after;
if ($before) $parts[] = $before;
$filename = implode('-', $parts) . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

fputcsv($out, [
    'Session ID', 'Tenant ID', 'Community', 'Analyzed At',
    'Session Started', 'Duration (sec)', 'Messages', 'User Messages',
    'Intent', 'Sentiment', 'Lead Captured', 'Tour Booked', 'XO Tool Used',
    'Topics', 'Objections', 'Builders Mentioned', 'Cross Referrals',
    'Price Min', 'Price Max', 'Bedrooms',
    'Summary'
]);

foreach ($rows as $r) {
    $topics     = str_replace('"', '', trim($r['topics'] ?? '{}', '{}'));
    $objections = str_replace('"', '', trim($r['objections'] ?? '{}', '{}'));
    $builders   = str_replace('"', '', trim($r['builders_mentioned'] ?? '{}', '{}'));
    $crossRef   = str_replace('"', '', trim($r['cross_referrals'] ?? '{}', '{}'));

    fputcsv($out, [
        $r['session_id'],
        $r['tenant_id'],
        $r['community_name'] ?: $r['tenant_name'],
        date('Y-m-d H:i:s', strtotime($r['analyzed_at'])),
        date('Y-m-d H:i:s', strtotime($r['session_started_at'])),
        $r['session_duration_sec'] ?? '',
        $r['message_count'],
        $r['user_message_count'],
        $r['intent_level'],
        $r['sentiment'],
        $r['lead_captured'] ? 'Yes' : 'No',
        $r['tour_booked'] ? 'Yes' : 'No',
        $r['xo_tool_called'] ? 'Yes' : 'No',
        $topics,
        $objections,
        $builders,
        $crossRef,
        $r['price_range_min'] ?? '',
        $r['price_range_max'] ?? '',
        $r['bedrooms_requested'] ?? '',
        $r['summary'],
    ]);
}

fclose($out);
