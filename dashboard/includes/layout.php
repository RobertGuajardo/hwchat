<?php
/**
 * Shared layout functions for the HWChat dashboard.
 * Keeps the design system consistent across all pages.
 */

function renderHead(string $title): void {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($title); ?> — HWChat</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
    /* ── Hillwood Mode (default) ── */
    :root, [data-theme="hillwood"] {
        --navy:        #1B2A4A;
        --navy-deep:   #0F1D36;
        --navy-light:  #2A3D63;
        --navy-card:   #162240;
        --blue:        #3B7DD8;
        --blue-light:  #5B9BE6;
        --blue-hover:  #93bef0;
        --green:       #4A8C5C;
        --green-light: #68A97A;
        --gold:        #C9A96E;
        --text:        #CBD5E1;
        --text-muted:  #6B7A94;
        --text-bright: #F1F5F9;
        --placeholder: rgba(255,255,255,0.2);
        --border:      rgba(255,255,255,0.08);
        --border-light: rgba(255,255,255,0.12);
        --bg-body:     #0F1D36;
        --bg-topbar:   #1B2A4A;
        --bg-card:     #162240;
        --bg-input:    rgba(255,255,255,0.04);
        --bg-hover:    rgba(255,255,255,0.05);
        --bg-hover2:   rgba(255,255,255,0.08);
    }

    /* ── Light Mode ── */
    [data-theme="light"] {
        --navy:        #3B7DD8;
        --navy-deep:   #F1F5F9;
        --navy-light:  #E2EAF4;
        --navy-card:   #FFFFFF;
        --blue:        #2563EB;
        --blue-light:  #3B7DD8;
        --blue-hover:  #1D4ED8;
        --green:       #4A8C5C;
        --green-light: #3D7A52;
        --gold:        #B45309;
        --text:        #334155;
        --text-muted:  #64748B;
        --text-bright: #0F172A;
        --placeholder: rgba(0,0,0,0.3);
        --border:      rgba(0,0,0,0.08);
        --border-light: rgba(0,0,0,0.13);
        --bg-body:     #F1F5F9;
        --bg-topbar:   #FFFFFF;
        --bg-card:     #FFFFFF;
        --bg-input:    rgba(0,0,0,0.03);
        --bg-hover:    rgba(0,0,0,0.04);
        --bg-hover2:   rgba(0,0,0,0.07);
    }

    /* ── Dark Mode ── */
    [data-theme="dark"] {
        --navy:        #111111;
        --navy-deep:   #0A0A0A;
        --navy-light:  #1A1A1A;
        --navy-card:   #161616;
        --blue:        #3B7DD8;
        --blue-light:  #5B9BE6;
        --blue-hover:  #93bef0;
        --green:       #4A8C5C;
        --green-light: #68A97A;
        --gold:        #C9A96E;
        --text:        #CBD5E1;
        --text-muted:  #4B5563;
        --text-bright: #F8FAFC;
        --placeholder: rgba(255,255,255,0.2);
        --border:      rgba(255,255,255,0.06);
        --border-light: rgba(255,255,255,0.10);
        --bg-body:     #0A0A0A;
        --bg-topbar:   #111111;
        --bg-card:     #161616;
        --bg-input:    rgba(255,255,255,0.05);
        --bg-hover:    rgba(255,255,255,0.04);
        --bg-hover2:   rgba(255,255,255,0.07);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        background: var(--bg-body); color: var(--text);
        font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        font-size: 14px; line-height: 1.5; min-height: 100vh;
    }
    h1, h2, h3 { font-family: 'DM Sans', sans-serif; font-weight: 600; letter-spacing: 0.02em; color: var(--text-bright); }
    a { color: var(--blue-light); text-decoration: none; }
    a:hover { color: var(--blue-hover); }

    /* Topbar */
    .topbar {
        display: flex; align-items: center; justify-content: space-between;
        padding: 16px 32px; border-bottom: 1px solid var(--border);
        background: var(--bg-topbar); position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 0 var(--border);
    }
    .topbar-left { display: flex; align-items: center; gap: 16px; }
    .topbar-stamp {
        font-family: 'DM Sans', sans-serif; font-weight: 700; font-size: 13px;
        color: var(--blue); border: 2px solid var(--blue);
        padding: 4px 10px; letter-spacing: 0.06em;
    }
    .topbar h1 { font-size: 13px; letter-spacing: 0.08em; color: var(--text-bright); font-weight: 600; }
    .topbar-right { display: flex; gap: 8px; align-items: center; }

    /* Navigation */
    .nav-tabs {
        display: flex; gap: 0; border-bottom: 1px solid var(--border);
        padding: 0 32px; background: var(--bg-topbar);
    }
    .nav-tab {
        padding: 12px 20px; font-family: 'DM Sans', sans-serif; font-size: 12px; font-weight: 500;
        color: var(--text-muted); text-decoration: none; letter-spacing: 0.05em;
        border-bottom: 2px solid transparent; transition: all 0.15s;
    }
    .nav-tab:hover { color: var(--text); }
    .nav-tab.active { color: var(--blue-light); border-bottom-color: var(--blue); }

    /* Container */
    .container { max-width: 1200px; margin: 0 auto; padding: 32px; }

    /* Buttons */
    .btn {
        display: inline-flex; align-items: center; padding: 8px 16px;
        background: var(--bg-hover); border: 1px solid var(--border-light);
        color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 12px; font-weight: 500;
        cursor: pointer; text-decoration: none; transition: all 0.15s; letter-spacing: 0.03em;
    }
    .btn:hover { background: var(--bg-hover2); color: var(--text-bright); }
    .btn-sm { padding: 6px 12px; font-size: 11px; }
    .btn-ghost { background: transparent; border-color: transparent; }
    .btn-ghost:hover { background: var(--bg-hover); }
    .btn-danger { color: #f87171; border-color: rgba(239,68,68,0.3); }
    .btn-danger:hover { background: rgba(239,68,68,0.12); }
    .btn-primary {
        background: var(--blue); border: none; color: #fff;
        font-family: 'DM Sans', sans-serif; font-weight: 600; letter-spacing: 0.04em;
    }
    .btn-primary:hover { background: var(--blue-light); color: #fff; }
    .btn:disabled { opacity: 0.3; cursor: not-allowed; }

    /* Filter Bar */
    .filter-bar { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
    .filter-label { font-family: 'DM Sans', sans-serif; font-size: 11px; font-weight: 500; color: var(--text-muted); letter-spacing: 0.08em; }
    .filter-pills { display: flex; gap: 4px; }
    .pill {
        padding: 6px 14px; background: var(--bg-input); border: 1px solid var(--border);
        color: var(--text-muted); font-family: 'DM Sans', sans-serif; font-size: 11px; font-weight: 500; cursor: pointer; transition: all 0.15s;
    }
    .pill:hover { color: var(--text); border-color: var(--border-light); }
    .pill.active { background: rgba(59,125,216,0.12); border-color: rgba(59,125,216,0.35); color: var(--blue-light); }

    /* Stats Grid */
    .stats-grid {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px; margin-bottom: 32px;
    }
    .stat-card {
        background: var(--bg-card); border: 1px solid var(--border);
        padding: 24px; display: flex; flex-direction: column; gap: 8px;
    }
    .stat-value {
        font-family: 'Playfair Display', serif; font-weight: 700; font-size: 32px;
        color: var(--blue-light);
    }
    .stat-label { font-family: 'DM Sans', sans-serif; font-size: 11px; font-weight: 500; color: var(--text-muted); letter-spacing: 0.08em; }

    /* Table */
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th {
        font-family: 'DM Sans', sans-serif; font-size: 11px; font-weight: 600; color: var(--text-muted); letter-spacing: 0.06em;
        padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border-light);
    }
    td { padding: 12px 16px; border-bottom: 1px solid var(--border); }
    tr:hover td { background: rgba(59,125,216,0.04); }
    .center { text-align: center; }
    .truncate { max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .mono { font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 400; }
    .empty-state { text-align: center; color: var(--text-muted); padding: 48px 16px; }
    input[type="checkbox"] { accent-color: var(--blue); width: 16px; height: 16px; cursor: pointer; }

    /* Badge */
    .badge { font-family: 'DM Sans', sans-serif; font-size: 10px; font-weight: 600; padding: 3px 8px; letter-spacing: 0.06em; }
    .badge-lead { background: rgba(74,140,92,0.15); color: var(--green-light); border: 1px solid rgba(74,140,92,0.3); }
    .badge-booking { background: rgba(59,125,216,0.15); color: var(--blue-light); border: 1px solid rgba(59,125,216,0.3); }
    .badge-none { color: var(--text-muted); }

    /* Pagination */
    .pagination { display: flex; align-items: center; justify-content: center; gap: 16px; margin-top: 24px; padding: 16px 0; }
    .page-info { color: var(--text-muted); font-size: 12px; }

    /* Forms */
    .form-group { margin-bottom: 20px; }
    .form-label {
        display: block; font-family: 'DM Sans', sans-serif; font-size: 11px; font-weight: 600;
        color: var(--text-muted); letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 6px;
    }
    .form-input, .form-textarea, .form-select {
        width: 100%; padding: 10px 12px;
        background: var(--bg-input); border: 1px solid var(--border-light);
        color: var(--text-bright); font-size: 14px; font-family: 'DM Sans', sans-serif; outline: none;
    }
    .form-input:focus, .form-textarea:focus, .form-select:focus { border-color: var(--blue); box-shadow: 0 0 0 2px rgba(59,125,216,0.15); }
    .form-input::placeholder, .form-textarea::placeholder { color: var(--placeholder); }
    .form-textarea { min-height: 120px; resize: vertical; font-family: 'DM Sans', sans-serif; font-size: 13px; line-height: 1.6; }
    .form-select { cursor: pointer; }
    .form-select option { background: var(--navy-card); }
    .form-hint { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-section { margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid var(--border); }
    .form-section h3 { font-size: 14px; color: var(--text-bright); margin-bottom: 16px; letter-spacing: 0.06em; }

    /* Alert */
    .alert { padding: 10px 14px; margin-bottom: 20px; font-size: 13px; }
    .alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); color: #f87171; }
    .alert-success { background: rgba(74,140,92,0.12); border: 1px solid rgba(74,140,92,0.3); color: var(--green-light); }

    /* Lead card */
    .lead-card {
        background: rgba(74,140,92,0.06); border: 1px solid rgba(74,140,92,0.2); padding: 24px; margin-bottom: 24px;
    }
    .lead-card h3 { font-size: 13px; color: var(--green-light); margin-bottom: 16px; letter-spacing: 0.06em; }
    .lead-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
    .lead-field { display: flex; flex-direction: column; gap: 2px; }

    /* Session meta */
    .session-meta {
        background: var(--bg-card); border: 1px solid var(--border); padding: 24px; margin-bottom: 24px;
        display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;
    }
    .meta-item { display: flex; flex-direction: column; gap: 4px; }
    .meta-label { font-family: 'DM Sans', sans-serif; font-size: 10px; font-weight: 600; color: var(--text-muted); letter-spacing: 0.08em; text-transform: uppercase; }
    .meta-value { color: var(--text); font-size: 14px; }

    /* Messages thread */
    .message { padding: 16px 20px; margin-bottom: 8px; border: 1px solid var(--border); }
    .message-user { background: rgba(59,125,216,0.06); border-color: rgba(59,125,216,0.15); }
    .message-assistant { background: var(--bg-card); border-color: var(--border); }
    .message-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
    .message-role { font-family: 'DM Sans', sans-serif; font-size: 10px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; }
    .message-user .message-role { color: var(--blue-light); }
    .message-assistant .message-role { color: var(--green-light); }
    .message-time { font-family: 'DM Sans', sans-serif; font-size: 11px; color: var(--text-muted); }
    .message-provider { font-family: 'DM Sans', sans-serif; font-size: 10px; color: var(--text-muted); margin-left: 8px; }
    .message-content { color: var(--text); line-height: 1.7; white-space: pre-wrap; word-break: break-word; }

    /* Login */
    .login-container { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
    .login-box {
        background: var(--bg-topbar); border: 1px solid var(--border-light);
        padding: 48px 40px; width: 100%; max-width: 420px; text-align: center;
    }
    .login-stamp {
        font-family: 'DM Sans', sans-serif; font-weight: 700; font-size: 18px;
        color: var(--blue); border: 2px solid var(--blue); padding: 6px 14px;
        display: inline-block; margin-bottom: 24px; letter-spacing: 0.06em;
    }
    .login-box h1 { font-size: 18px; margin-bottom: 4px; color: var(--text-bright); }
    .login-sub { font-family: 'DM Sans', sans-serif; font-size: 12px; color: var(--text-muted); margin-bottom: 32px; }
    .login-box .form-input { margin-bottom: 12px; text-align: left; }
    .login-links { margin-top: 20px; font-size: 13px; color: var(--text-muted); }
    .login-links a { color: var(--blue-light); }

    /* Embed code box */
    .embed-box {
        background: var(--bg-card); border: 1px solid var(--border); padding: 20px;
        margin-bottom: 24px;
    }
    .embed-box code {
        display: block; font-family: 'DM Sans', monospace; font-size: 12px;
        color: var(--blue-light); word-break: break-all; line-height: 1.6;
    }

    /* ── Utility Classes ── */
    /* Text colors (replace inline color:#fff, color:#ccc, color:#666, etc.) */
    .text-bright { color: var(--text-bright); }
    .text-default { color: var(--text); }
    .text-muted { color: var(--text-muted); }

    /* Background + border cards (replace inline background:#141414 + border patterns) */
    .card { background: var(--bg-card); border: 1px solid var(--border); }
    .card-subtle { background: var(--bg-body); border: 1px solid var(--border); }

    /* Common section header pattern (replace inline font-size:16px;color:#fff) */
    .section-title { font-size: 16px; font-weight: 600; color: var(--text-bright); letter-spacing: 0.02em; }
    .section-subtitle { font-size: 13px; color: var(--text-muted); }

    /* Label/value pattern used in detail views */
    .label { font-size: 11px; font-weight: 600; color: var(--text-muted); letter-spacing: 0.06em; text-transform: uppercase; }
    .value { font-size: 14px; color: var(--text); }
    .value-bright { font-size: 14px; color: var(--text-bright); }

    /* Responsive */
    @media (max-width: 768px) {
        .topbar { padding: 12px 16px; }
        .nav-tabs { padding: 0 16px; }
        .container { padding: 16px; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .stat-value { font-size: 24px; }
        .filter-bar { flex-direction: column; align-items: flex-start; }
        .form-row { grid-template-columns: 1fr; }
    }

    /* Superadmin tenant table */
    .tenant-row td { vertical-align: middle; }
    .tenant-name { font-weight: 600; color: var(--text-bright); }
    .tenant-id { font-size: 11px; color: var(--text-muted); font-family: 'DM Sans', monospace; }
    .tenant-community { font-size: 12px; color: var(--text); }
    .badge-active { background: rgba(74,140,92,0.15); color: var(--green-light); border: 1px solid rgba(74,140,92,0.3); }
    .badge-inactive { background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.25); }
    .badge-super { background: rgba(201,169,110,0.15); color: var(--gold); border: 1px solid rgba(201,169,110,0.3); }
    .badge-type { background: rgba(59,125,216,0.1); color: var(--blue-light); border: 1px solid rgba(59,125,216,0.2); }

    /* Master prompt editor */
    .prompt-editor {
        width: 100%; min-height: 500px; padding: 16px;
        background: var(--bg-input); border: 1px solid var(--border-light);
        color: var(--text-bright); font-family: 'DM Sans', monospace; font-size: 13px; line-height: 1.7;
        resize: vertical; outline: none;
    }
    .prompt-editor:focus { border-color: var(--blue); box-shadow: 0 0 0 2px rgba(59,125,216,0.15); }
    .char-count { font-size: 11px; color: var(--text-muted); margin-top: 8px; }

    /* Action bar */
    .action-bar {
        display: flex; align-items: center; justify-content: space-between;
        padding: 16px 0; margin-bottom: 24px; border-bottom: 1px solid var(--border);
    }
    .action-bar h2 { font-size: 16px; color: var(--text-bright); }
    </style>
    <script>
    // Apply theme immediately to prevent flash
    (function() {
        var t = localStorage.getItem('hwchat_theme') || 'hillwood';
        document.documentElement.setAttribute('data-theme', t);
    })();
    </script>
</head>
<body>
<?php
}

function renderNav(string $active = 'overview'): void {
    $isSuper = isSuperAdmin();
    $inSuperDir = basename(dirname($_SERVER['SCRIPT_NAME'])) === 'super';

    if ($isSuper && $inSuperDir) {
        $tabs = [
            'overview'      => ['url' => 'index.php',          'label' => 'OVERVIEW'],
            'tenants'       => ['url' => 'tenants.php',        'label' => 'TENANTS'],
            'communities'   => ['url' => 'communities.php',    'label' => 'COMMUNITIES'],
            'master'        => ['url' => 'master-prompt.php',  'label' => 'MASTER PROMPT'],
            'leads'         => ['url' => 'leads.php',          'label' => 'LEADS'],
        ];
    } else {
        $tabs = [
            'overview'  => ['url' => 'index.php',           'label' => 'OVERVIEW'],
            'leads'     => ['url' => 'leads.php',           'label' => 'LEADS'],
            'bookings'  => ['url' => 'bookings.php',        'label' => 'BOOKINGS'],
            'knowledge' => ['url' => 'knowledge-base.php',  'label' => 'KNOWLEDGE'],
            'settings'  => ['url' => 'settings.php',        'label' => 'SETTINGS'],
        ];
    }
?>
    <header class="topbar">
        <div class="topbar-left">
            <span class="topbar-stamp">HW</span>
            <?php if ($isSuper): ?>
                <span style="font-family:'DM Sans',sans-serif;font-size:10px;font-weight:700;color:#C9A96E;border:1px solid rgba(201,169,110,0.4);padding:2px 8px;letter-spacing:0.08em;">SUPER</span>
            <?php endif; ?>
            <h1><?php echo e(strtoupper(getTenantName())); ?></h1>
        </div>
        <div class="topbar-right">
            <?php if ($isSuper && !$inSuperDir): ?>
                <a href="super/index.php" class="btn btn-sm" style="color:var(--gold);border-color:rgba(201,169,110,0.3);">ADMIN PANEL</a>
            <?php elseif ($isSuper && $inSuperDir): ?>
                <a href="../index.php" class="btn btn-sm btn-ghost">TENANT VIEW</a>
            <?php endif; ?>
            <!-- Theme Switcher -->
            <div style="display:flex;gap:4px;align-items:center;margin:0 4px;" title="Switch theme">
                <button onclick="setTheme('hillwood')" data-theme-btn="hillwood" title="Hillwood"
                    style="width:22px;height:22px;border-radius:50%;background:#1B2A4A;border:2px solid transparent;cursor:pointer;transition:border-color 0.15s;padding:0;"></button>
                <button onclick="setTheme('light')" data-theme-btn="light" title="Light"
                    style="width:22px;height:22px;border-radius:50%;background:#F1F5F9;border:2px solid transparent;cursor:pointer;transition:border-color 0.15s;padding:0;"></button>
                <button onclick="setTheme('dark')" data-theme-btn="dark" title="Dark"
                    style="width:22px;height:22px;border-radius:50%;background:#111111;border:2px solid transparent;cursor:pointer;transition:border-color 0.15s;padding:0;"></button>
            </div>
            <?php
            $userTenants = $_SESSION['user_tenants'] ?? [];
            $switchBase = $inSuperDir ? '../' : '';
            if (count($userTenants) > 1): ?>
            <select id="tenant-switcher" style="background:var(--bg-input);border:1px solid var(--border);color:var(--text);font-family:'DM Sans',sans-serif;font-size:11px;padding:5px 8px;cursor:pointer;outline:none;letter-spacing:0.03em;">
                <?php foreach ($userTenants as $ut): ?>
                    <option value="<?php echo e($ut['id']); ?>" <?php echo ($ut['id'] === ($_SESSION['tenant_id'] ?? '')) ? 'selected' : ''; ?>>
                        <?php echo e($ut['community_name'] ?: $ut['display_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <script>
            document.getElementById('tenant-switcher').addEventListener('change', function() {
                fetch('<?php echo $switchBase; ?>switch-tenant.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({tenant_id: this.value})
                }).then(function(r) { return r.json(); }).then(function(d) {
                    if (d.success) window.location.reload();
                });
            });
            </script>
            <?php endif; ?>
            <span style="font-family:'DM Sans',sans-serif;font-size:11px;color:var(--text-muted);"><?php echo e($_SESSION['tenant_email'] ?? ''); ?></span>
            <a href="<?php echo $inSuperDir ? '../' : ''; ?>logout.php" class="btn btn-ghost btn-sm">LOGOUT</a>
        </div>
    </header>
    <script>
    function setTheme(t) {
        document.documentElement.setAttribute('data-theme', t);
        localStorage.setItem('hwchat_theme', t);
        document.querySelectorAll('[data-theme-btn]').forEach(function(b) {
            b.style.borderColor = b.dataset.themeBtn === t ? '#3B7DD8' : 'transparent';
        });
    }
    (function() {
        var saved = localStorage.getItem('hwchat_theme') || 'hillwood';
        document.documentElement.setAttribute('data-theme', saved);
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-theme-btn]').forEach(function(b) {
                b.style.borderColor = b.dataset.themeBtn === saved ? '#3B7DD8' : 'transparent';
            });
        });
    })();
    </script>
    <nav class="nav-tabs">
        <?php foreach ($tabs as $key => $tab): ?>
            <a href="<?php echo $tab['url']; ?>" class="nav-tab <?php echo $key === $active ? 'active' : ''; ?>"><?php echo $tab['label']; ?></a>
        <?php endforeach; ?>
    </nav>
<?php
}

function renderFooter(): void {
?>
</body>
</html>
<?php
}
