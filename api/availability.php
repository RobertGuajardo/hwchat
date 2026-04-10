<?php
/**
 * GET /api/availability.php?tenant_id=TENANT_ID&date=2026-03-20
 *
 * Returns available time slots for a given date.
 * Called by the widget when showing the calendar booking view.
 *
 * Response:
 * {
 *   "date": "2026-03-20",
 *   "day": "Friday",
 *   "timezone": "America/Chicago",
 *   "slots": [
 *     { "time": "9:00 AM", "value": "09:00" },
 *     { "time": "9:30 AM", "value": "09:30" },
 *     ...
 *   ]
 * }
 *
 * GET /api/availability.php?tenant_id=TENANT_ID&range=14
 *
 * Returns available dates with slot counts for the next N days.
 * Used by the widget to show which dates have openings.
 *
 * Response:
 * {
 *   "dates": [
 *     { "date": "2026-03-20", "day": "Fri", "slot_count": 8 },
 *     { "date": "2026-03-21", "day": "Sat", "slot_count": 0 },
 *     ...
 *   ]
 * }
 */

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed.', 405);
}

$tenantId = trim($_GET['tenant_id'] ?? '');
if (empty($tenantId)) {
    jsonError('Missing tenant_id.');
}

handleCors($config, $tenantId);

// Load tenant
$tenant = Database::getTenant($tenantId);
if (!$tenant) {
    jsonError('Tenant not found.', 404);
}

$tz = new DateTimeZone($tenant['booking_timezone'] ?? 'America/Chicago');
$slotMinutes  = (int)($tenant['booking_slot_minutes'] ?? 30);
$bufferMin    = (int)($tenant['booking_buffer_minutes'] ?? 0);
$noticeHours  = (int)($tenant['booking_notice_hours'] ?? 24);
$windowDays   = (int)($tenant['booking_window_days'] ?? 14);

$db = Database::db();

// ─── Range mode: return dates with slot counts ───
if (isset($_GET['range'])) {
    $rangeDays = min((int)$_GET['range'], 60);
    $dates = [];
    $now = new DateTime('now', $tz);

    for ($i = 1; $i <= $rangeDays; $i++) {
        $date = clone $now;
        $date->modify("+{$i} days");
        $dateStr = $date->format('Y-m-d');
        $slots = getAvailableSlots($db, $tenantId, $dateStr, $tz, $slotMinutes, $bufferMin, $noticeHours);
        $dates[] = [
            'date'       => $dateStr,
            'day'        => $date->format('D'),
            'slot_count' => count($slots),
        ];
    }

    jsonResponse(['dates' => $dates, 'timezone' => $tz->getName()]);
}

// ─── Single date mode: return actual slots ───
$date = trim($_GET['date'] ?? '');
if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    jsonError('Missing or invalid date parameter (YYYY-MM-DD).');
}

$slots = getAvailableSlots($db, $tenantId, $date, $tz, $slotMinutes, $bufferMin, $noticeHours);
$dayName = (new DateTime($date, $tz))->format('l');

jsonResponse([
    'date'     => $date,
    'day'      => $dayName,
    'timezone' => $tz->getName(),
    'slots'    => $slots,
]);


// ===========================================================================
// HELPER FUNCTIONS
// ===========================================================================

function getAvailableSlots(PDO $db, string $tenantId, string $date, DateTimeZone $tz, int $slotMinutes, int $bufferMin, int $noticeHours): array
{
    $dateObj = new DateTime($date, $tz);
    $dayOfWeek = (int)$dateObj->format('w'); // 0=Sun, 6=Sat

    // 1. Check for full-day block override
    $stmt = $db->prepare('
        SELECT * FROM availability_overrides
        WHERE tenant_id = :tid AND override_date = :date AND override_type = :type
          AND start_time IS NULL AND end_time IS NULL
    ');
    $stmt->execute(['tid' => $tenantId, 'date' => $date, 'type' => 'blocked']);
    if ($stmt->fetch()) {
        return []; // Full day blocked
    }

    // 2. Load availability rules for this day of week
    $stmt = $db->prepare('
        SELECT start_time, end_time FROM availability_rules
        WHERE tenant_id = :tid AND day_of_week = :dow AND is_active = TRUE
        ORDER BY start_time ASC
    ');
    $stmt->execute(['tid' => $tenantId, 'dow' => $dayOfWeek]);
    $rules = $stmt->fetchAll();

    // 3. Load open overrides for this date (extra availability)
    $stmt = $db->prepare('
        SELECT start_time, end_time FROM availability_overrides
        WHERE tenant_id = :tid AND override_date = :date AND override_type = :type
          AND start_time IS NOT NULL
    ');
    $stmt->execute(['tid' => $tenantId, 'date' => $date, 'type' => 'open']);
    $openOverrides = $stmt->fetchAll();

    // Combine rules + open overrides
    $timeBlocks = array_merge($rules, $openOverrides);

    if (empty($timeBlocks)) {
        return [];
    }

    // 4. Load blocked time overrides for this date
    $stmt = $db->prepare('
        SELECT start_time, end_time FROM availability_overrides
        WHERE tenant_id = :tid AND override_date = :date AND override_type = :type
          AND start_time IS NOT NULL
    ');
    $stmt->execute(['tid' => $tenantId, 'date' => $date, 'type' => 'blocked']);
    $blockedOverrides = $stmt->fetchAll();

    // 5. Load existing bookings for this date
    $stmt = $db->prepare('
        SELECT start_time, end_time FROM bookings
        WHERE tenant_id = :tid AND booking_date = :date AND status = :status
    ');
    $stmt->execute(['tid' => $tenantId, 'date' => $date, 'status' => 'confirmed']);
    $bookings = $stmt->fetchAll();

    // 6. Generate all possible slots from time blocks
    $slots = [];
    $now = new DateTime('now', $tz);
    $earliestSlot = clone $now;
    $earliestSlot->modify("+{$noticeHours} hours");

    foreach ($timeBlocks as $block) {
        $start = new DateTime("{$date} {$block['start_time']}", $tz);
        $end   = new DateTime("{$date} {$block['end_time']}", $tz);

        while ($start < $end) {
            $slotEnd = clone $start;
            $slotEnd->modify("+{$slotMinutes} minutes");

            if ($slotEnd > $end) break;

            // Check minimum notice
            if ($start <= $earliestSlot) {
                $start->modify("+{$slotMinutes} minutes");
                if ($bufferMin > 0) $start->modify("+{$bufferMin} minutes");
                continue;
            }

            // Check against blocked overrides
            $blocked = false;
            foreach ($blockedOverrides as $bo) {
                $boStart = new DateTime("{$date} {$bo['start_time']}", $tz);
                $boEnd   = new DateTime("{$date} {$bo['end_time']}", $tz);
                if ($start < $boEnd && $slotEnd > $boStart) {
                    $blocked = true;
                    break;
                }
            }

            // Check against existing bookings
            if (!$blocked) {
                foreach ($bookings as $b) {
                    $bStart = new DateTime("{$date} {$b['start_time']}", $tz);
                    $bEnd   = new DateTime("{$date} {$b['end_time']}", $tz);
                    // Add buffer before and after booking
                    if ($bufferMin > 0) {
                        $bStart->modify("-{$bufferMin} minutes");
                        $bEnd->modify("+{$bufferMin} minutes");
                    }
                    if ($start < $bEnd && $slotEnd > $bStart) {
                        $blocked = true;
                        break;
                    }
                }
            }

            if (!$blocked) {
                $slots[] = [
                    'time'  => $start->format('g:i A'),
                    'value' => $start->format('H:i'),
                ];
            }

            $start->modify("+{$slotMinutes} minutes");
            if ($bufferMin > 0) $start->modify("+{$bufferMin} minutes");
        }
    }

    return $slots;
}
