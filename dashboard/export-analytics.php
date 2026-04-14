<?php
require_once __DIR__ . '/auth.php';
requireAuth();
if (!canAccessAnalytics()) { http_response_code(403); exit; }

// Scope tenant access
if (isSuperAdmin()) {
    $tenantId = !empty($_GET['tenant']) ? $_GET['tenant'] : null;
} else {
    $tenantId = getTenantId();
}

$after  = $_GET['after'] ?? null;
$before = $_GET['before'] ?? null;

$rows = Database::getAnalyticsExport($tenantId, $after, $before);

// Build filename
$parts = ['hwchat-analytics'];
if ($tenantId) $parts[] = $tenantId;
if ($after)    $parts[] = $after;
if ($before)   $parts[] = $before;
$filename = implode('-', $parts) . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

// Header row
fputcsv($out, [
    'Session ID', 'Tenant ID', 'Community', 'Analyzed At',
    'Session Started', 'Duration (sec)', 'Messages', 'User Messages',
    'Intent', 'Sentiment', 'Lead Captured', 'Tour Booked', 'XO Tool Used',
    'Topics', 'Objections', 'Builders Mentioned', 'Cross Referrals',
    'Price Min', 'Price Max', 'Bedrooms',
    'Summary'
]);

// Data rows
foreach ($rows as $r) {
    // Convert Postgres arrays to readable strings
    $topics     = trim($r['topics'] ?? '{}', '{}');
    $objections = trim($r['objections'] ?? '{}', '{}');
    $builders   = trim($r['builders_mentioned'] ?? '{}', '{}');
    $crossRef   = trim($r['cross_referrals'] ?? '{}', '{}');

    // Strip quotes from Postgres array elements
    $topics     = str_replace('"', '', $topics);
    $objections = str_replace('"', '', $objections);
    $builders   = str_replace('"', '', $builders);
    $crossRef   = str_replace('"', '', $crossRef);

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
