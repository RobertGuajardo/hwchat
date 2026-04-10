<?php
/**
 * POST /api/book.php
 *
 * Books a time slot for a visitor. Validates the slot is still
 * available, saves to the bookings table, and sends confirmation.
 *
 * Request:
 * {
 *   "tenant_id": "acme",
 *   "session_id": "uuid",
 *   "date": "2026-03-20",
 *   "time": "09:00",
 *   "name": "Jane Doe",
 *   "email": "jane@company.com",
 *   "phone": "555-1234",
 *   "notes": "Want to discuss a website redesign",
 *   "timezone": "America/Chicago"
 * }
 *
 * Response:
 * {
 *   "booked": true,
 *   "booking_id": 42,
 *   "date": "March 20, 2026",
 *   "time": "9:00 AM",
 *   "duration": 30
 * }
 */

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed.', 405);
}

$input = getJsonInput();
$tenantId  = trim($input['tenant_id'] ?? '');
$sessionId = trim($input['session_id'] ?? '');
$date      = trim($input['date'] ?? '');
$time      = trim($input['time'] ?? '');
$name      = trim($input['name'] ?? '');
$email     = trim($input['email'] ?? '');
$phone     = trim($input['phone'] ?? '');
$notes     = trim($input['notes'] ?? '');
$builderId = !empty($input['builder_id']) ? (int)$input['builder_id'] : null;

// Validate required fields
if (empty($tenantId))  jsonError('Missing tenant_id.');
if (empty($date))      jsonError('Missing date.');
if (empty($time))      jsonError('Missing time.');
if (empty($name))      jsonError('Name is required.');
if (empty($email) && empty($phone)) jsonError('Email or phone number is required.');
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) jsonError('Invalid email.');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) jsonError('Invalid date format.');
if (!preg_match('/^\d{2}:\d{2}$/', $time)) jsonError('Invalid time format.');

handleCors($config, $tenantId);

// Load tenant
$tenant = Database::getTenant($tenantId);
if (!$tenant) {
    jsonError('Tenant not found.', 404);
}

$db = Database::db();
$tz = new DateTimeZone($tenant['booking_timezone'] ?? 'America/Chicago');
$slotMinutes = (int)($tenant['booking_slot_minutes'] ?? 30);
$bufferMin   = (int)($tenant['booking_buffer_minutes'] ?? 0);
$noticeHours = (int)($tenant['booking_notice_hours'] ?? 24);

// Calculate end time
$startDt = new DateTime("{$date} {$time}", $tz);
$endDt   = clone $startDt;
$endDt->modify("+{$slotMinutes} minutes");

// Validate: not in the past / within notice period
$now = new DateTime('now', $tz);
$earliest = clone $now;
$earliest->modify("+{$noticeHours} hours");
if ($startDt <= $earliest) {
    jsonError('This time slot is no longer available. Please choose a later time.');
}

// Validate: slot doesn't conflict with existing bookings
$stmt = $db->prepare('
    SELECT id FROM bookings
    WHERE tenant_id = :tid AND booking_date = :date AND status = :status
      AND start_time < :end_time AND end_time > :start_time
');
$stmt->execute([
    'tid'        => $tenantId,
    'date'       => $date,
    'status'     => 'confirmed',
    'end_time'   => $endDt->format('H:i'),
    'start_time' => $startDt->format('H:i'),
]);
if ($stmt->fetch()) {
    jsonError('This time slot is no longer available. Someone just booked it.');
}

// Validate: slot falls within availability rules or open overrides
$dayOfWeek = (int)$startDt->format('w');

$stmt = $db->prepare('
    SELECT COUNT(*) FROM availability_rules
    WHERE tenant_id = :tid AND day_of_week = :dow AND is_active = TRUE
      AND start_time <= :start AND end_time >= :end
');
$stmt->execute([
    'tid'   => $tenantId,
    'dow'   => $dayOfWeek,
    'start' => $startDt->format('H:i'),
    'end'   => $endDt->format('H:i'),
]);
$inRules = (int)$stmt->fetchColumn() > 0;

if (!$inRules) {
    // Check open overrides
    $stmt = $db->prepare('
        SELECT COUNT(*) FROM availability_overrides
        WHERE tenant_id = :tid AND override_date = :date AND override_type = :type
          AND start_time <= :start AND end_time >= :end
    ');
    $stmt->execute([
        'tid'   => $tenantId,
        'date'  => $date,
        'type'  => 'open',
        'start' => $startDt->format('H:i'),
        'end'   => $endDt->format('H:i'),
    ]);
    if ((int)$stmt->fetchColumn() === 0) {
        jsonError('This time slot is not within available hours.');
    }
}

// Check blocked overrides
$stmt = $db->prepare('
    SELECT COUNT(*) FROM availability_overrides
    WHERE tenant_id = :tid AND override_date = :date AND override_type = :type
      AND (
        (start_time IS NULL) OR
        (start_time < :end_time AND end_time > :start_time)
      )
');
$stmt->execute([
    'tid'        => $tenantId,
    'date'       => $date,
    'type'       => 'blocked',
    'end_time'   => $endDt->format('H:i'),
    'start_time' => $startDt->format('H:i'),
]);
if ((int)$stmt->fetchColumn() > 0) {
    jsonError('This time slot is blocked off.');
}

// ─── All checks passed — create the booking ───

// Look up builder name if provided
$builderName = null;
if ($builderId) {
    $stmt = $db->prepare('SELECT name FROM builders WHERE id = :id AND tenant_id = :tid');
    $stmt->execute(['id' => $builderId, 'tid' => $tenantId]);
    $builderName = $stmt->fetchColumn() ?: null;
}

$stmt = $db->prepare('
    INSERT INTO bookings (tenant_id, session_id, booking_date, start_time, end_time, timezone, guest_name, guest_email, guest_phone, guest_notes, builder_id, status)
    VALUES (:tid, :sid, :date, :start, :end, :tz, :name, :email, :phone, :notes, :builder_id, :status)
');
$stmt->execute([
    'tid'        => $tenantId,
    'sid'        => $sessionId ?: null,
    'date'       => $date,
    'start'      => $startDt->format('H:i'),
    'end'        => $endDt->format('H:i'),
    'tz'         => $tz->getName(),
    'name'       => $name,
    'email'      => $email,
    'phone'      => $phone ?: null,
    'notes'      => $notes ?: null,
    'builder_id' => $builderId,
    'status'     => 'confirmed',
]);
$bookingId = (int)$db->lastInsertId();

// ─── Also save as a lead (bookings are the highest-quality leads) ───
$leadId = Database::saveLead($tenantId, [
    'session_id'   => $sessionId ?: null,
    'name'         => $name,
    'email'        => $email ?: null,
    'phone'        => $phone ?: null,
    'message'      => 'Booked tour: ' . $startDt->format('M j, Y') . ' at ' . $startDt->format('g:i A') . ($builderName ? " with $builderName" : '') . ($notes ? " — $notes" : ''),
]);

// Send confirmation email to guest (only if they provided an email)
$confirmSent = false;
if (!empty($email)) {
    $confirmSent = sendBookingConfirmation($tenant, [
        'booking_id' => $bookingId,
        'date'       => $startDt->format('l, F j, Y'),
        'time'       => $startDt->format('g:i A'),
        'duration'   => $slotMinutes,
        'name'       => $name,
        'email'      => $email,
        'timezone'   => $tz->getName(),
        'builder'    => $builderName,
    ]);
}

if ($confirmSent) {
    $stmt = $db->prepare('UPDATE bookings SET confirmation_sent = TRUE WHERE id = :id');
    $stmt->execute(['id' => $bookingId]);
}

// Notify tenant
if (!empty($tenant['lead_email'])) {
    sendBookingNotification($tenant, [
        'booking_id' => $bookingId,
        'date'       => $startDt->format('l, F j, Y'),
        'time'       => $startDt->format('g:i A'),
        'duration'   => $slotMinutes,
        'name'       => $name,
        'email'      => $email,
        'phone'      => $phone,
        'notes'      => $notes,
        'builder'    => $builderName,
    ]);
}

jsonResponse([
    'booked'     => true,
    'booking_id' => $bookingId,
    'date'       => $startDt->format('F j, Y'),
    'time'       => $startDt->format('g:i A'),
    'duration'   => $slotMinutes,
    'builder'    => $builderName,
]);


// ===========================================================================
// EMAIL FUNCTIONS
// ===========================================================================

function sendBookingConfirmation(array $tenant, array $booking): bool
{
    $to = $booking['email'];
    $tenantName = $tenant['display_name'];
    $subject = "Booking Confirmed — {$booking['date']} at {$booking['time']}";

    $body  = "Hi {$booking['name']},\n\n";
    $body .= "Your booking with {$tenantName} is confirmed!\n\n";
    $body .= "Date: {$booking['date']}\n";
    $body .= "Time: {$booking['time']} ({$booking['timezone']})\n";
    $body .= "Duration: {$booking['duration']} minutes\n";
    if (!empty($booking['builder'])) $body .= "Builder: {$booking['builder']}\n";
    $body .= "\nIf you need to cancel or reschedule, please reply to this email.\n\n";
    $body .= "— {$tenantName}\nPowered by RobChat";

    $headers  = "From: {$tenantName} <noreply@robchat.io>\r\n";
    $headers .= "Reply-To: " . ($tenant['lead_email'] ?? 'noreply@robchat.io') . "\r\n";

    return @mail($to, $subject, $body, $headers);
}

function sendBookingNotification(array $tenant, array $booking): bool
{
    $to = $tenant['lead_email'];
    $subject = "New Booking — {$booking['name']} on {$booking['date']}";

    $body  = "New booking from your RobChat widget:\n\n";
    $body .= "Name: {$booking['name']}\n";
    $body .= "Email: {$booking['email']}\n";
    if ($booking['phone']) $body .= "Phone: {$booking['phone']}\n";
    $body .= "Date: {$booking['date']}\n";
    $body .= "Time: {$booking['time']}\n";
    $body .= "Duration: {$booking['duration']} minutes\n";
    if (!empty($booking['builder'])) $body .= "Builder: {$booking['builder']}\n";
    if ($booking['notes']) $body .= "Notes: {$booking['notes']}\n";
    $body .= "\n---\nPowered by RobChat";

    $headers = "From: RobChat <noreply@robchat.io>\r\n";
    $headers .= "Reply-To: {$booking['email']}\r\n";

    return @mail($to, $subject, $body, $headers);
}
