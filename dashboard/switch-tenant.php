<?php
require_once __DIR__ . '/auth.php';
requireAuth();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$tenantId = $input['tenant_id'] ?? '';

if (!$tenantId) {
    echo json_encode(['success' => false, 'error' => 'Missing tenant_id']);
    exit;
}

$success = switchTenant($tenantId);
echo json_encode(['success' => $success]);
