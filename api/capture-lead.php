<?php
/**
 * POST /api/capture-lead.php
 *
 * Saves lead data to the database and sends an email notification
 * to the tenant's configured lead_email address.
 *
 * Request:
 * {
 *   "tenant_id": "acme",
 *   "conversation_id": "uuid",
 *   "name": "Jane Doe",
 *   "email": "jane@company.com",
 *   "company": "Company Inc",
 *   "phone": "555-1234",
 *   "project_type": "website redesign",
 *   "budget": "$5k-$10k"
 * }
 *
 * Response:
 * { "success": true, "lead_id": 42 }
 */

require_once __DIR__ . '/bootstrap.php';

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed.', 405);
}

// Parse input
$input = getJsonInput();
$tenantId = trim($input['tenant_id'] ?? '');

if (empty($tenantId)) {
    jsonError('Missing tenant_id.');
}

// CORS
handleCors($config, $tenantId);

// Load tenant
$tenant = Database::getTenant($tenantId);
if (!$tenant) {
    jsonError('Tenant not found.', 404);
}

// Validate required fields — need name + at least email or phone
$name  = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');

if (empty($name)) {
    jsonError('Name is required.');
}

if (empty($email) && empty($phone)) {
    jsonError('Email or phone number is required.');
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Invalid email address.');
}

// Save lead
$leadId = Database::saveLead($tenantId, [
    'session_id'   => $input['conversation_id'] ?? null,
    'name'         => $name,
    'email'        => $email,
    'company'      => $input['company'] ?? null,
    'phone'        => $input['phone'] ?? null,
    'project_type' => $input['project_type'] ?? null,
    'budget'       => $input['budget'] ?? null,
    'message'      => $input['message'] ?? null,
    'source_page'  => $input['source_page'] ?? null,
]);

// Send email notification to tenant
$emailSent = false;
if (!empty($tenant['lead_email'])) {
    $emailSent = sendLeadNotification($tenant, [
        'name'         => $name,
        'email'        => $email,
        'company'      => $input['company'] ?? '',
        'phone'        => $input['phone'] ?? '',
        'project_type' => $input['project_type'] ?? '',
        'budget'       => $input['budget'] ?? '',
        'message'      => $input['message'] ?? '',
    ]);

    if ($emailSent) {
        $stmt = Database::db()->prepare('UPDATE leads SET email_sent = TRUE WHERE id = :id');
        $stmt->execute(['id' => $leadId]);
    }
}

// Fire webhook if configured
if (!empty($tenant['lead_webhook'])) {
    fireWebhook($tenant['lead_webhook'], [
        'event'     => 'lead_captured',
        'tenant_id' => $tenantId,
        'lead_id'   => $leadId,
        'name'      => $name,
        'email'     => $email,
        'company'   => $input['company'] ?? '',
        'phone'     => $input['phone'] ?? '',
    ]);
}

jsonResponse([
    'success' => true,
    'lead_id' => $leadId,
]);


// ===========================================================================
// HELPER FUNCTIONS
// ===========================================================================

function sendLeadNotification(array $tenant, array $lead): bool
{
    $tenantName = $tenant['display_name'];
    $to = $tenant['lead_email'];

    $subject = "New Lead from RobChat — {$lead['name']}";

    $body = "New lead captured by your RobChat widget:\n\n";
    $body .= "Name: {$lead['name']}\n";
    $body .= "Email: {$lead['email']}\n";
    if ($lead['company'])      $body .= "Company: {$lead['company']}\n";
    if ($lead['phone'])        $body .= "Phone: {$lead['phone']}\n";
    if ($lead['project_type']) $body .= "Project Type: {$lead['project_type']}\n";
    if ($lead['budget'])       $body .= "Budget: {$lead['budget']}\n";
    if ($lead['message'])      $body .= "Message: {$lead['message']}\n";
    $body .= "\n---\nPowered by RobChat";

    $headers = "From: RobChat <noreply@robchat.io>\r\n";
    if (!empty($lead['email'])) {
        $headers .= "Reply-To: {$lead['email']}\r\n";
    }

    return @mail($to, $subject, $body, $headers);
}

function fireWebhook(string $url, array $data): void
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_TIMEOUT        => 5,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
