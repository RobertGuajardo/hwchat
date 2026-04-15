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

// Restore saved admin scope (used when returning from Tenant View to Admin Panel)
if (!empty($input['restore_admin_scope'])) {
    $_SESSION['scope_type']  = $_SESSION['last_admin_scope_type'] ?? 'all';
    $_SESSION['scope_value'] = $_SESSION['last_admin_scope_value'] ?? null;
    echo json_encode(['success' => true]);
    exit;
}

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

// Save current admin scope before entering Tenant View
if (!empty($input['save_admin_scope'])) {
    $_SESSION['last_admin_scope_type']  = $_SESSION['scope_type'] ?? 'all';
    $_SESSION['last_admin_scope_value'] = $_SESSION['scope_value'] ?? null;
}

$_SESSION['scope_type']  = $scopeType;
$_SESSION['scope_value'] = $scopeType === 'all' ? null : $scopeValue;

echo json_encode(['success' => true]);
