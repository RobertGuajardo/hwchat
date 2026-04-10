<?php
/**
 * GET /api/tenant-config.php?id=TENANT_ID
 *
 * Returns the public-facing configuration for a tenant's widget.
 * This is called by the embedded robchat.js script on page load.
 *
 * Response:
 * {
 *   "id": "acme",
 *   "name": "Acme AI Assistant",
 *   "greeting": "Hey! How can I help?",
 *   "accentColor": "#FF4D2E",
 *   "accentGradient": "linear-gradient(135deg, #FF4D2E, #C850C0)",
 *   "aiAccent": "#8B5CF6",
 *   "position": "bottom-right",
 *   "quickReplies": ["Services", "Pricing", "Book a call"],
 *   "apiEndpoint": "https://api.robchat.io/api/chat.php",
 *   "calendarEnabled": false
 * }
 */

require_once __DIR__ . '/bootstrap.php';

// Only GET allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed.', 405);
}

// Get tenant ID from query string
$tenantId = trim($_GET['id'] ?? '');
if (empty($tenantId)) {
    jsonError('Missing tenant ID.', 400);
}

// CORS — allow the requesting origin if it's in the tenant's allowed list
handleCors($config, $tenantId);

// Fetch tenant config
$tenant = Database::getTenantConfig($tenantId);
if (!$tenant) {
    jsonError('Tenant not found.', 404);
}

// Build the API base URL from the current request
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$apiBase  = "$protocol://$host" . dirname($_SERVER['SCRIPT_NAME']);

// Check if tenant has scheduling set up (has availability rules)
$db = Database::db();
$stmt = $db->prepare('SELECT COUNT(*) FROM availability_rules WHERE tenant_id = :tid AND is_active = TRUE');
$stmt->execute(['tid' => $tenant['id']]);
$hasScheduling = (int)$stmt->fetchColumn() > 0;

// Load active builders
$stmt = $db->prepare('SELECT id, name FROM builders WHERE tenant_id = :tid AND is_active = TRUE ORDER BY sort_order, name');
$stmt->execute(['tid' => $tenant['id']]);
$builders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map DB columns to the widget's TenantConfig interface
jsonResponse([
    'id'              => $tenant['id'],
    'name'            => $tenant['display_name'],
    'greeting'        => $tenant['greeting'],
    'accentColor'     => $tenant['accent_color'],
    'accentGradient'  => $tenant['accent_gradient'],
    'aiAccent'        => $tenant['ai_accent'],
    'position'        => $tenant['widget_position'],
    'quickReplies'    => $tenant['quick_replies'],
    'apiEndpoint'     => "$apiBase/chat.php",
    'leadCaptureEndpoint' => "$apiBase/capture-lead.php",
    'availabilityEndpoint' => $hasScheduling ? "$apiBase/availability.php" : null,
    'bookingEndpoint'      => $hasScheduling ? "$apiBase/book.php" : null,
    'calendarEnabled' => $hasScheduling,
    'builders'        => $builders,
    'poweredByUrl'    => 'https://robertguajardo.com',
    'colorHeaderBg'       => $tenant['color_header_bg'],
    'colorHeaderText'     => $tenant['color_header_text'] ?? '#ffffff',
    'colorSecondary'      => $tenant['color_secondary'],
    'colorQuickBtnBg'     => $tenant['color_quick_btn_bg'],
    'colorQuickBtnText'   => $tenant['color_quick_btn_text'],
    'colorUserBubble'     => $tenant['color_user_bubble'],
    'colorAiBubbleBorder' => $tenant['color_ai_bubble_border'],
    'colorFooterBg'       => $tenant['color_footer_bg'],
    'colorFooterText'     => $tenant['color_footer_text'] ?? '#ffffff',
    'colorSendBtn'        => $tenant['color_send_btn'],
    'communityPhone'      => $tenant['community_phone'] ?? null,
    'communityEmail'      => $tenant['community_email'] ?? null,
    'communityAddress'    => $tenant['community_address'] ?? null,
]);
