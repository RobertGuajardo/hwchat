<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireAuth();

$db = Database::db();
$tenantId = getTenantId();

// Handle cancel action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    if ($bookingId) {
        $stmt = $db->prepare('UPDATE bookings SET status = :status, cancelled_at = NOW(), cancel_reason = :reason WHERE id = :id AND tenant_id = :tid');
        $stmt->execute(['status' => 'cancelled', 'reason' => $_POST['reason'] ?? '', 'id' => $bookingId, 'tid' => $tenantId]);
    }
    header('Location: bookings.php?view=' . urlencode($_GET['view'] ?? 'calendar') . '&month=' . urlencode($_GET['month'] ?? date('Y-m')) . '&date=' . urlencode($_GET['date'] ?? ''));
    exit;
}

// Handle block-off actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'block_day') {
    $blockDate = trim($_POST['block_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    if ($blockDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $blockDate)) {
        // Remove any existing full-day block for this date first (avoid duplicates)
        $stmt = $db->prepare('DELETE FROM availability_overrides WHERE tenant_id = :tid AND override_date = :date AND override_type = :type AND start_time IS NULL');
        $stmt->execute(['tid' => $tenantId, 'date' => $blockDate, 'type' => 'blocked']);
        // Insert full-day block
        $stmt = $db->prepare('INSERT INTO availability_overrides (tenant_id, override_date, override_type, reason) VALUES (:tid, :date, :type, :reason)');
        $stmt->execute(['tid' => $tenantId, 'date' => $blockDate, 'type' => 'blocked', 'reason' => $reason ?: null]);
    }
    $month = substr($blockDate, 0, 7) ?: ($_GET['month'] ?? date('Y-m'));
    header('Location: bookings.php?view=calendar&month=' . urlencode($month) . '&date=' . urlencode($blockDate));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'block_time') {
    $blockDate  = trim($_POST['block_date'] ?? '');
    $startTime  = trim($_POST['start_time'] ?? '');
    $endTime    = trim($_POST['end_time'] ?? '');
    $reason     = trim($_POST['reason'] ?? '');
    if ($blockDate && $startTime && $endTime && $startTime < $endTime) {
        $stmt = $db->prepare('INSERT INTO availability_overrides (tenant_id, override_date, start_time, end_time, override_type, reason) VALUES (:tid, :date, :start, :end, :type, :reason)');
        $stmt->execute(['tid' => $tenantId, 'date' => $blockDate, 'start' => $startTime, 'end' => $endTime, 'type' => 'blocked', 'reason' => $reason ?: null]);
    }
    $month = substr($blockDate, 0, 7) ?: ($_GET['month'] ?? date('Y-m'));
    header('Location: bookings.php?view=calendar&month=' . urlencode($month) . '&date=' . urlencode($blockDate));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_block') {
    $blockId = (int)($_POST['block_id'] ?? 0);
    if ($blockId) {
        $stmt = $db->prepare('DELETE FROM availability_overrides WHERE id = :id AND tenant_id = :tid');
        $stmt->execute(['id' => $blockId, 'tid' => $tenantId]);
    }
    $blockDate = trim($_POST['block_date'] ?? '');
    $month = substr($blockDate, 0, 7) ?: ($_GET['month'] ?? date('Y-m'));
    header('Location: bookings.php?view=calendar&month=' . urlencode($month) . '&date=' . urlencode($blockDate));
    exit;
}

$viewMode = $_GET['view'] ?? 'calendar';

// ─── Calendar month navigation ───
$monthParam = $_GET['month'] ?? date('Y-m');
$calYear  = (int)substr($monthParam, 0, 4);
$calMonth = (int)substr($monthParam, 5, 2);
if ($calYear < 2020 || $calYear > 2099) $calYear = (int)date('Y');
if ($calMonth < 1 || $calMonth > 12) $calMonth = (int)date('m');

$firstDay = new DateTime("{$calYear}-{$calMonth}-01");
$lastDay  = (clone $firstDay)->modify('last day of this month');
$prevMonth = (clone $firstDay)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $firstDay)->modify('+1 month')->format('Y-m');
$monthLabel = $firstDay->format('F Y');
$startDow = (int)$firstDay->format('w');
$daysInMonth = (int)$lastDay->format('d');

// Load bookings for calendar month
$stmt = $db->prepare('
    SELECT id, booking_date, start_time, end_time, guest_name, guest_email, guest_phone, guest_notes, status, session_id
    FROM bookings WHERE tenant_id = :tid AND booking_date >= :start AND booking_date <= :end
    ORDER BY booking_date ASC, start_time ASC
');
$stmt->execute(['tid' => $tenantId, 'start' => $firstDay->format('Y-m-d'), 'end' => $lastDay->format('Y-m-d')]);
$allBookings = $stmt->fetchAll();

$bookingsByDate = [];
foreach ($allBookings as $b) {
    $bookingsByDate[$b['booking_date']][] = $b;
}

// Selected day
$selectedDate = $_GET['date'] ?? null;
$selectedBookings = $selectedDate ? ($bookingsByDate[$selectedDate] ?? []) : [];

// Load availability overrides (blocked days/times) for calendar month
$stmt = $db->prepare('
    SELECT id, override_date, start_time, end_time, override_type, reason
    FROM availability_overrides WHERE tenant_id = :tid AND override_date >= :start AND override_date <= :end
    ORDER BY override_date ASC, start_time ASC
');
$stmt->execute(['tid' => $tenantId, 'start' => $firstDay->format('Y-m-d'), 'end' => $lastDay->format('Y-m-d')]);
$allOverrides = $stmt->fetchAll();

$overridesByDate = [];
foreach ($allOverrides as $o) {
    $overridesByDate[$o['override_date']][] = $o;
}

$selectedOverrides = $selectedDate ? ($overridesByDate[$selectedDate] ?? []) : [];
$selectedIsFullDayBlocked = false;
foreach ($selectedOverrides as $o) {
    if ($o['override_type'] === 'blocked' && $o['start_time'] === null) {
        $selectedIsFullDayBlocked = true;
        break;
    }
}

// ─── List view data ───
$filter = $_GET['filter'] ?? 'upcoming';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;
$where = 'WHERE b.tenant_id = :tid';
$params = ['tid' => $tenantId];

if ($filter === 'upcoming') {
    $where .= ' AND b.booking_date >= CURRENT_DATE AND b.status = :status';
    $params['status'] = 'confirmed';
    $orderBy = 'b.booking_date ASC, b.start_time ASC';
} elseif ($filter === 'past') {
    $where .= ' AND (b.booking_date < CURRENT_DATE OR b.status != :status)';
    $params['status'] = 'confirmed';
    $orderBy = 'b.booking_date DESC, b.start_time DESC';
} else {
    $orderBy = 'b.booking_date DESC, b.start_time DESC';
}

$stmt = $db->prepare("SELECT COUNT(*) FROM bookings b $where");
$stmt->execute($params);
$totalRows = (int)$stmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

$stmt = $db->prepare("SELECT b.* FROM bookings b $where ORDER BY $orderBy LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$listBookings = $stmt->fetchAll();

renderHead('Bookings');
?>
    <header class="topbar">
        <div class="topbar-left">
            <span class="topbar-stamp">RC</span>
            <h1><?php echo e(strtoupper(getTenantName())); ?></h1>
        </div>
        <div class="topbar-right">
            <span style="font-family:'Space Mono',monospace;font-size:11px;color:#555;"><?php echo e($_SESSION['tenant_email'] ?? ''); ?></span>
            <a href="logout.php" class="btn btn-ghost btn-sm">LOGOUT</a>
        </div>
    </header>
    <nav class="nav-tabs">
        <a href="index.php" class="nav-tab">OVERVIEW</a>
        <a href="leads.php" class="nav-tab">LEADS</a>
        <a href="bookings.php" class="nav-tab active">BOOKINGS</a>
        <a href="settings.php" class="nav-tab">SETTINGS</a>
    </nav>

    <main class="container">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
            <div style="display:flex;gap:4px;">
                <a href="?view=calendar&month=<?php echo e($monthParam); ?>" class="pill <?php echo $viewMode === 'calendar' ? 'active' : ''; ?>">CALENDAR</a>
                <a href="?view=list&filter=upcoming" class="pill <?php echo $viewMode === 'list' ? 'active' : ''; ?>">LIST</a>
            </div>
        </div>

    <?php if ($viewMode === 'calendar'): ?>

        <!-- Month navigation -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <a href="?view=calendar&month=<?php echo $prevMonth; ?>" class="btn btn-sm">← <?php echo (clone $firstDay)->modify('-1 month')->format('M'); ?></a>
            <h2 style="font-size:18px;color:#fff;font-family:'Syne',sans-serif;letter-spacing:0.04em;"><?php echo strtoupper($monthLabel); ?></h2>
            <a href="?view=calendar&month=<?php echo $nextMonth; ?>" class="btn btn-sm"><?php echo (clone $firstDay)->modify('+1 month')->format('M'); ?> →</a>
        </div>

        <!-- Calendar grid -->
        <div class="cal-grid">
            <?php foreach (['SUN','MON','TUE','WED','THU','FRI','SAT'] as $dh): ?>
                <div class="cal-header"><?php echo $dh; ?></div>
            <?php endforeach; ?>

            <?php for ($i = 0; $i < $startDow; $i++): ?>
                <div class="cal-cell cal-empty"></div>
            <?php endfor; ?>

            <?php for ($d = 1; $d <= $daysInMonth; $d++):
                $dateStr = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $d);
                $dayBookings = $bookingsByDate[$dateStr] ?? [];
                $dayOverrides = $overridesByDate[$dateStr] ?? [];
                $confirmedCount = count(array_filter($dayBookings, fn($b) => $b['status'] === 'confirmed'));
                $cancelledCount = count(array_filter($dayBookings, fn($b) => $b['status'] === 'cancelled'));
                $isToday = $dateStr === date('Y-m-d');
                $isSelected = $dateStr === $selectedDate;
                $hasBookings = $confirmedCount > 0;
                $isFullDayBlocked = false;
                $hasTimeBlocks = false;
                foreach ($dayOverrides as $o) {
                    if ($o['override_type'] === 'blocked' && $o['start_time'] === null) $isFullDayBlocked = true;
                    if ($o['override_type'] === 'blocked' && $o['start_time'] !== null) $hasTimeBlocks = true;
                }
            ?>
                <a href="?view=calendar&month=<?php echo $monthParam; ?>&date=<?php echo $dateStr; ?>"
                   class="cal-cell <?php echo $isToday ? 'cal-today' : ''; ?> <?php echo $isSelected ? 'cal-selected' : ''; ?> <?php echo $hasBookings ? 'cal-has-bookings' : ''; ?> <?php echo $isFullDayBlocked ? 'cal-blocked' : ''; ?>">
                    <span class="cal-day-num"><?php echo $d; ?></span>
                    <?php if ($confirmedCount > 0 || $isFullDayBlocked || $hasTimeBlocks): ?>
                        <span class="cal-dot-row">
                            <?php if ($isFullDayBlocked): ?>
                                <span class="cal-dot cal-dot-blocked"></span>
                            <?php elseif ($hasTimeBlocks): ?>
                                <span class="cal-dot cal-dot-partial"></span>
                            <?php endif; ?>
                            <?php for ($dot = 0; $dot < min($confirmedCount, 3); $dot++): ?>
                                <span class="cal-dot"></span>
                            <?php endfor; ?>
                            <?php if ($confirmedCount > 3): ?>
                                <span class="cal-dot-more">+<?php echo $confirmedCount - 3; ?></span>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endfor; ?>
        </div>

        <!-- Selected day detail -->
        <?php if ($selectedDate): ?>
        <div style="margin-top:24px;">
            <h3 style="font-size:14px;color:#fff;font-family:'Syne',sans-serif;margin-bottom:16px;letter-spacing:0.06em;">
                <?php echo e(date('l, F j, Y', strtotime($selectedDate))); ?>
                <span style="color:#666;font-weight:400;margin-left:8px;">(<?php echo count($selectedBookings); ?> booking<?php echo count($selectedBookings) !== 1 ? 's' : ''; ?>)</span>
                <?php if ($selectedIsFullDayBlocked): ?>
                    <span class="badge" style="background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3);margin-left:8px;">BLOCKED</span>
                <?php endif; ?>
            </h3>

            <!-- Existing blocks for this day -->
            <?php if (!empty($selectedOverrides)): ?>
            <div style="margin-bottom:16px;">
                <?php foreach ($selectedOverrides as $o): ?>
                <div style="background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.2);padding:12px 16px;margin-bottom:6px;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <span style="font-family:'Space Mono',monospace;font-size:12px;color:#f87171;">
                            <?php if ($o['start_time'] === null): ?>
                                FULL DAY BLOCKED
                            <?php else: ?>
                                BLOCKED <?php echo e((new DateTime($o['start_time']))->format('g:i A')); ?> – <?php echo e((new DateTime($o['end_time']))->format('g:i A')); ?>
                            <?php endif; ?>
                        </span>
                        <?php if ($o['reason']): ?>
                            <span style="color:#888;font-size:12px;margin-left:8px;">— <?php echo e($o['reason']); ?></span>
                        <?php endif; ?>
                    </div>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this block?');">
                        <input type="hidden" name="action" value="remove_block">
                        <input type="hidden" name="block_id" value="<?php echo (int)$o['id']; ?>">
                        <input type="hidden" name="block_date" value="<?php echo e($selectedDate); ?>">
                        <button type="submit" class="btn btn-sm btn-danger">REMOVE</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Block-off controls -->
            <div style="background:#141414;border:1px solid rgba(255,255,255,0.06);padding:20px;margin-bottom:16px;">
                <h4 style="font-family:'Space Mono',monospace;font-size:11px;color:#666;letter-spacing:0.08em;margin-bottom:12px;">BLOCK OFF TIME</h4>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
                    <!-- Full day block -->
                    <?php if (!$selectedIsFullDayBlocked): ?>
                    <form method="POST" style="display:flex;gap:8px;align-items:flex-end;">
                        <input type="hidden" name="action" value="block_day">
                        <input type="hidden" name="block_date" value="<?php echo e($selectedDate); ?>">
                        <div>
                            <label class="form-label" style="margin-bottom:4px;">REASON (OPTIONAL)</label>
                            <input type="text" name="reason" placeholder="e.g. Day off, Personal" class="form-input" style="width:200px;padding:6px 10px;font-size:13px;">
                        </div>
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Block off the entire day?');">BLOCK FULL DAY</button>
                    </form>
                    <?php else: ?>
                    <span style="font-size:12px;color:#888;">Full day is already blocked. Remove the block above to modify.</span>
                    <?php endif; ?>
                </div>

                <?php if (!$selectedIsFullDayBlocked): ?>
                <div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.06);">
                    <form method="POST" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                        <input type="hidden" name="action" value="block_time">
                        <input type="hidden" name="block_date" value="<?php echo e($selectedDate); ?>">
                        <div>
                            <label class="form-label" style="margin-bottom:4px;">FROM</label>
                            <select name="start_time" class="form-select" style="width:120px;padding:6px 10px;font-size:13px;">
                                <?php for ($h = 6; $h <= 20; $h++): foreach (['00','30'] as $m): $val = sprintf('%02d:%s', $h, $m); $lbl = (new DateTime($val))->format('g:i A'); ?>
                                    <option value="<?php echo $val; ?>" <?php echo $val === '09:00' ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                <?php endforeach; endfor; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="margin-bottom:4px;">TO</label>
                            <select name="end_time" class="form-select" style="width:120px;padding:6px 10px;font-size:13px;">
                                <?php for ($h = 6; $h <= 20; $h++): foreach (['00','30'] as $m): $val = sprintf('%02d:%s', $h, $m); $lbl = (new DateTime($val))->format('g:i A'); ?>
                                    <option value="<?php echo $val; ?>" <?php echo $val === '17:00' ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                <?php endforeach; endfor; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="margin-bottom:4px;">REASON</label>
                            <input type="text" name="reason" placeholder="e.g. Client appt" class="form-input" style="width:160px;padding:6px 10px;font-size:13px;">
                        </div>
                        <button type="submit" class="btn btn-sm" style="color:#f87171;border-color:rgba(239,68,68,0.3);">BLOCK TIME RANGE</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <?php if (empty($selectedBookings)): ?>
                <div style="padding:24px;color:#555;text-align:center;background:#141414;border:1px solid rgba(255,255,255,0.06);">No bookings on this day.</div>
            <?php else: ?>
                <?php foreach ($selectedBookings as $b):
                    $st = new DateTime($b['start_time']);
                    $et = new DateTime($b['end_time']);
                ?>
                <div style="background:#141414;border:1px solid rgba(255,255,255,0.06);padding:16px 20px;margin-bottom:8px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;">
                    <div style="flex:1;">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                            <span style="font-family:'Space Mono',monospace;font-size:13px;color:#A78BFA;"><?php echo e($st->format('g:i A') . ' – ' . $et->format('g:i A')); ?></span>
                            <?php if ($b['status'] === 'confirmed'): ?>
                                <span class="badge" style="background:rgba(34,197,94,0.15);color:#4ade80;border:1px solid rgba(34,197,94,0.3);">CONFIRMED</span>
                            <?php elseif ($b['status'] === 'cancelled'): ?>
                                <span class="badge" style="background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3);">CANCELLED</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:14px;color:#ddd;margin-bottom:4px;"><?php echo e($b['guest_name']); ?></div>
                        <div style="font-size:12px;color:#888;">
                            <a href="mailto:<?php echo e($b['guest_email']); ?>" style="color:#FF4D2E;"><?php echo e($b['guest_email']); ?></a>
                            <?php if ($b['guest_phone']): ?> · <?php echo e($b['guest_phone']); ?><?php endif; ?>
                        </div>
                        <?php if ($b['guest_notes']): ?>
                            <div style="font-size:12px;color:#666;margin-top:6px;">Notes: <?php echo e($b['guest_notes']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;gap:4px;flex-shrink:0;">
                        <?php if ($b['status'] === 'confirmed'): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this booking?');">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="booking_id" value="<?php echo (int)$b['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">CANCEL</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($b['session_id']): ?>
                            <a href="session.php?id=<?php echo urlencode($b['session_id']); ?>" class="btn btn-sm">CHAT</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- List View -->
        <div style="display:flex;gap:4px;margin-bottom:24px;">
            <a href="?view=list&filter=upcoming" class="pill <?php echo $filter === 'upcoming' ? 'active' : ''; ?>">UPCOMING</a>
            <a href="?view=list&filter=past" class="pill <?php echo $filter === 'past' ? 'active' : ''; ?>">PAST</a>
            <a href="?view=list&filter=all" class="pill <?php echo $filter === 'all' ? 'active' : ''; ?>">ALL</a>
        </div>

        <?php if (empty($listBookings)): ?>
            <div class="empty-state">No bookings found.</div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>DATE</th><th>TIME</th><th>GUEST</th><th>EMAIL</th><th>STATUS</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($listBookings as $b): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?php echo e(date('M j, Y', strtotime($b['booking_date']))); ?></td>
                        <td style="white-space:nowrap;"><?php $st=new DateTime($b['start_time']); $et=new DateTime($b['end_time']); echo e($st->format('g:i A').' – '.$et->format('g:i A')); ?></td>
                        <td><?php echo e($b['guest_name']); ?><?php if($b['guest_phone']): ?><span style="color:#555;font-size:12px;"> · <?php echo e($b['guest_phone']); ?></span><?php endif; ?></td>
                        <td><a href="mailto:<?php echo e($b['guest_email']); ?>"><?php echo e($b['guest_email']); ?></a></td>
                        <td>
                            <?php if ($b['status']==='confirmed'): ?><span class="badge" style="background:rgba(34,197,94,0.15);color:#4ade80;border:1px solid rgba(34,197,94,0.3);">CONFIRMED</span>
                            <?php elseif ($b['status']==='cancelled'): ?><span class="badge" style="background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3);">CANCELLED</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($b['status']==='confirmed'): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel?');"><input type="hidden" name="action" value="cancel"><input type="hidden" name="booking_id" value="<?php echo (int)$b['id']; ?>"><button type="submit" class="btn btn-sm btn-danger">CANCEL</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?><a href="?view=list&filter=<?php echo e($filter); ?>&page=<?php echo $page-1; ?>" class="btn btn-sm">← PREV</a><?php endif; ?>
            <span class="page-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
            <?php if ($page < $totalPages): ?><a href="?view=list&filter=<?php echo e($filter); ?>&page=<?php echo $page+1; ?>" class="btn btn-sm">NEXT →</a><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
    </main>

    <style>
    .cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:1px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.06); }
    .cal-header { padding:10px 4px; text-align:center; font-family:'Space Mono',monospace; font-size:11px; color:#666; letter-spacing:0.06em; background:#111; }
    .cal-cell { min-height:72px; padding:8px; background:#0c0c0c; display:flex; flex-direction:column; align-items:flex-start; text-decoration:none; transition:background 0.15s; cursor:pointer; }
    .cal-cell:hover { background:#141414; }
    .cal-empty { cursor:default; }
    .cal-empty:hover { background:#0c0c0c; }
    .cal-today { background:rgba(255,77,46,0.06); border-left:2px solid #FF4D2E; }
    .cal-today:hover { background:rgba(255,77,46,0.1); }
    .cal-selected { background:rgba(139,92,246,0.1) !important; border-left:2px solid #8B5CF6; }
    .cal-day-num { font-size:14px; font-weight:600; color:rgba(255,255,255,0.6); font-family:'Syne',sans-serif; }
    .cal-today .cal-day-num { color:#FF4D2E; }
    .cal-selected .cal-day-num { color:#A78BFA; }
    .cal-has-bookings .cal-day-num { color:#fff; }
    .cal-dot-row { display:flex; gap:3px; margin-top:6px; align-items:center; }
    .cal-dot { width:6px; height:6px; border-radius:50%; background:#4ade80; }
    .cal-dot-blocked { background:#f87171; width:8px; height:8px; }
    .cal-dot-partial { background:#fbbf24; width:8px; height:8px; }
    .cal-dot-more { font-size:10px; color:#4ade80; font-family:'Space Mono',monospace; }
    .cal-blocked { background:rgba(239,68,68,0.06); }
    .cal-blocked:hover { background:rgba(239,68,68,0.1); }
    .cal-blocked .cal-day-num { color:#f87171; text-decoration:line-through; }
    @media (max-width:768px) { .cal-cell { min-height:56px; padding:4px; } .cal-day-num { font-size:12px; } }
    </style>
<?php renderFooter(); ?>
