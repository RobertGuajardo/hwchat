<?php
require_once __DIR__ . '/../auth.php';
requireAuth();

header('Content-Type: application/json');

if (!isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Superadmin only']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$scopeType  = $input['scope_type'] ?? '';
$scopeValue = $input['scope_value'] ?? null;

if (!in_array($scopeType, ['all', 'tenant'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid scope_type']);
    exit;
}

if ($scopeType === 'tenant') {
    if (empty($scopeValue)) {
        echo json_encode(['success' => false, 'error' => 'scope_value required for tenant scope']);
        exit;
    }
    // Validate tenant exists and has a region
    $db = Database::db();
    $stmt = $db->prepare('SELECT id FROM tenants WHERE id = :id AND region IS NOT NULL');
    $stmt->execute(['id' => $scopeValue]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Invalid tenant or tenant has no region']);
        exit;
    }
}

$_SESSION['scope_type']  = $scopeType;
$_SESSION['scope_value'] = $scopeType === 'all' ? null : $scopeValue;

echo json_encode(['success' => true]);
