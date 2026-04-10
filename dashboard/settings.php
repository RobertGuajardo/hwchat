<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireAuth();

$db = Database::db();
$tenantId = getTenantId();
$success = '';
$error = '';

// Load current tenant config
$stmt = $db->prepare('SELECT * FROM tenants WHERE id = :id');
$stmt->execute(['id' => $tenantId]);
$tenant = $stmt->fetch();

if (!$tenant) { header('Location: index.php'); exit; }

// Decode JSONB fields
$tenant['quick_replies']   = json_decode($tenant['quick_replies'] ?? '[]', true) ?: [];
$tenant['allowed_origins'] = json_decode($tenant['allowed_origins'] ?? '[]', true) ?: [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        try {
            $stmt = $db->prepare('
                UPDATE tenants SET
                    display_name = :display_name,
                    greeting = :greeting,
                    accent_color = :accent_color,
                    accent_gradient = :accent_gradient,
                    ai_accent = :ai_accent,
                    color_header_bg = :color_header_bg,
                    color_header_text = :color_header_text,
                    color_secondary = :color_secondary,
                    color_quick_btn_bg = :color_quick_btn_bg,
                    color_quick_btn_text = :color_quick_btn_text,
                    color_user_bubble = :color_user_bubble,
                    color_ai_bubble_border = :color_ai_bubble_border,
                    color_footer_bg = :color_footer_bg,
                    color_footer_text = :color_footer_text,
                    color_send_btn = :color_send_btn,
                    widget_position = :widget_position,
                    quick_replies = :quick_replies,
                    system_prompt = :system_prompt,
                    primary_llm = :primary_llm,
                    openai_api_key = :openai_api_key,
                    anthropic_api_key = :anthropic_api_key,
                    openai_model = :openai_model,
                    anthropic_model = :anthropic_model,
                    max_tokens = :max_tokens,
                    lead_email = :lead_email,
                    lead_webhook = :lead_webhook,
                    allowed_origins = :allowed_origins,
                    rate_limit_per_minute = :rate_limit_per_minute,
                    rate_limit_per_hour = :rate_limit_per_hour,
                    max_conversation_length = :max_conversation_length,
                    booking_slot_minutes = :booking_slot_minutes,
                    booking_buffer_minutes = :booking_buffer_minutes,
                    booking_notice_hours = :booking_notice_hours,
                    booking_window_days = :booking_window_days,
                    xo_enabled = :xo_enabled,
                    xo_api_base_url = :xo_api_base_url,
                    xo_project_slug = :xo_project_slug,
                    hubspot_portal_id = :hubspot_portal_id,
                    hubspot_form_id = :hubspot_form_id,
                    community_type = :community_type,
                    community_name = :community_name,
                    community_url = :community_url,
                    community_location = :community_location
                WHERE id = :id
            ');

            $qr      = array_filter(array_map('trim', explode("\n", $_POST['quick_replies'] ?? '')));
            $origins = array_filter(array_map('trim', explode("\n", $_POST['allowed_origins'] ?? '')));

            $stmt->execute([
                'display_name'           => trim($_POST['display_name'] ?? $tenant['display_name']),
                'greeting'               => trim($_POST['greeting'] ?? $tenant['greeting']),
                'accent_color'           => trim($_POST['accent_color'] ?? $tenant['accent_color']),
                'accent_gradient'        => trim($_POST['accent_gradient'] ?? $tenant['accent_gradient']),
                'ai_accent'              => trim($_POST['ai_accent'] ?? $tenant['ai_accent']),
                'color_header_bg'        => trim($_POST['color_header_bg'] ?? ''),
                'color_header_text'      => trim($_POST['color_header_text'] ?? '#ffffff'),
                'color_secondary'        => trim($_POST['color_secondary'] ?? ''),
                'color_quick_btn_bg'     => trim($_POST['color_quick_btn_bg'] ?? 'transparent'),
                'color_quick_btn_text'   => trim($_POST['color_quick_btn_text'] ?? ''),
                'color_user_bubble'      => trim($_POST['color_user_bubble'] ?? ''),
                'color_ai_bubble_border' => trim($_POST['color_ai_bubble_border'] ?? ''),
                'color_footer_bg'        => trim($_POST['color_footer_bg'] ?? ''),
                'color_footer_text'      => trim($_POST['color_footer_text'] ?? '#ffffff'),
                'color_send_btn'         => trim($_POST['color_send_btn'] ?? ''),
                'widget_position'        => $_POST['widget_position'] ?? 'bottom-right',
                'quick_replies'          => json_encode(array_values($qr)),
                'system_prompt'          => trim($_POST['system_prompt'] ?? ''),
                'primary_llm'            => $_POST['primary_llm'] ?? 'openai',
                'openai_api_key'         => trim($_POST['openai_api_key'] ?? ''),
                'anthropic_api_key'      => trim($_POST['anthropic_api_key'] ?? ''),
                'openai_model'           => trim($_POST['openai_model'] ?? 'gpt-4o'),
                'anthropic_model'        => trim($_POST['anthropic_model'] ?? 'claude-sonnet-4-20250514'),
                'max_tokens'             => (int)($_POST['max_tokens'] ?? 500),
                'lead_email'             => trim($_POST['lead_email'] ?? ''),
                'lead_webhook'           => trim($_POST['lead_webhook'] ?? ''),
                'allowed_origins'        => json_encode(array_values($origins)),
                'rate_limit_per_minute'  => (int)($_POST['rate_limit_per_minute'] ?? 10),
                'rate_limit_per_hour'    => (int)($_POST['rate_limit_per_hour'] ?? 60),
                'max_conversation_length'=> (int)($_POST['max_conversation_length'] ?? 50),
                'booking_slot_minutes'   => (int)($_POST['booking_slot_minutes'] ?? 30),
                'booking_buffer_minutes' => (int)($_POST['booking_buffer_minutes'] ?? 0),
                'booking_notice_hours'   => (int)($_POST['booking_notice_hours'] ?? 24),
                'booking_window_days'    => (int)($_POST['booking_window_days'] ?? 14),
                'xo_enabled'             => !empty($_POST['xo_enabled']) ? 'true' : 'false',
                'xo_api_base_url'        => trim($_POST['xo_api_base_url'] ?? ''),
                'xo_project_slug'        => trim($_POST['xo_project_slug'] ?? ''),
                'hubspot_portal_id'      => trim($_POST['hubspot_portal_id'] ?? ''),
                'hubspot_form_id'        => trim($_POST['hubspot_form_id'] ?? ''),
                'community_type'         => $_POST['community_type'] ?? 'standard',
                'community_name'         => trim($_POST['community_name'] ?? ''),
                'community_url'          => trim($_POST['community_url'] ?? ''),
                'community_location'     => trim($_POST['community_location'] ?? ''),
                'id'                     => $tenantId,
            ]);

            // Save availability rules
            $db->prepare('DELETE FROM availability_rules WHERE tenant_id = :tid')->execute(['tid' => $tenantId]);
            $insertRule = $db->prepare('INSERT INTO availability_rules (tenant_id, day_of_week, start_time, end_time) VALUES (:tid, :dow, :start, :end)');
            for ($dow = 0; $dow <= 6; $dow++) {
                if (!empty($_POST["avail_day_{$dow}_enabled"])) {
                    $start = $_POST["avail_day_{$dow}_start"] ?? '09:00';
                    $end   = $_POST["avail_day_{$dow}_end"] ?? '17:00';
                    if ($start < $end) {
                        $insertRule->execute(['tid' => $tenantId, 'dow' => $dow, 'start' => $start, 'end' => $end]);
                    }
                }
            }

            $success = 'Settings saved.';
            $stmt = $db->prepare('SELECT * FROM tenants WHERE id = :id');
            $stmt->execute(['id' => $tenantId]);
            $tenant = $stmt->fetch();
            $tenant['quick_replies']   = json_decode($tenant['quick_replies'] ?? '[]', true) ?: [];
            $tenant['allowed_origins'] = json_decode($tenant['allowed_origins'] ?? '[]', true) ?: [];

        } catch (Exception $ex) {
            $error = 'Failed to save: ' . $ex->getMessage();
        }
    }

    if ($action === 'add_builder') {
        $builderName = trim($_POST['builder_name'] ?? '');
        if (!empty($builderName)) {
            $stmt = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM builders WHERE tenant_id = :tid');
            $stmt->execute(['tid' => $tenantId]);
            $nextSort = (int)$stmt->fetchColumn();
            $stmt = $db->prepare('INSERT INTO builders (tenant_id, name, sort_order) VALUES (:tid, :name, :sort)');
            $stmt->execute(['tid' => $tenantId, 'name' => $builderName, 'sort' => $nextSort]);
            $success = "Builder \"{$builderName}\" added.";
        }
    }

    if ($action === 'remove_builder') {
        $builderId = (int)($_POST['builder_id'] ?? 0);
        if ($builderId > 0) {
            $stmt = $db->prepare('DELETE FROM builders WHERE id = :id AND tenant_id = :tid');
            $stmt->execute(['id' => $builderId, 'tid' => $tenantId]);
            $success = 'Builder removed.';
        }
    }
}

// Load availability rules
$stmt = $db->prepare('SELECT * FROM availability_rules WHERE tenant_id = :tid ORDER BY day_of_week, start_time');
$stmt->execute(['tid' => $tenantId]);
$allRules = $stmt->fetchAll();
$rulesByDay = [];
foreach ($allRules as $r) {
    $rulesByDay[(int)$r['day_of_week']][] = $r;
}

// Load builders
$stmt = $db->prepare('SELECT * FROM builders WHERE tenant_id = :tid ORDER BY sort_order, name');
$stmt->execute(['tid' => $tenantId]);
$builders = $stmt->fetchAll();

// Active tab from query string or default
$activeTab = $_GET['tab'] ?? 'branding';

renderHead('Settings');
renderNav('settings');
?>
<style>
.tab-nav {
    display: flex;
    gap: 2px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.tab-btn {
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    color: var(--text-muted);
    font-family: 'DM Sans', sans-serif;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.08em;
    padding: 10px 16px;
    cursor: pointer;
    transition: color 0.15s, border-color 0.15s;
    white-space: nowrap;
}
.tab-btn:hover { color: var(--text-bright); }
.tab-btn.active {
    color: var(--blue);
    border-bottom-color: var(--blue);
}
.tab-panel { display: none; }
.tab-panel.active { display: block; }
.save-row {
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.06);
    margin-top: 8px;
}
</style>

<main class="container" style="max-width:800px;">

    <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

    <!-- Embed Code (always visible) -->
    <div class="embed-box" style="margin-bottom:24px;">
        <span class="meta-label" style="margin-bottom:8px;display:block;">YOUR EMBED CODE</span>
        <code>&lt;script src="https://hwchat.robertguajardo.com/widget/robchat.js" data-robchat-id="<?php echo e($tenantId); ?>" defer&gt;&lt;/script&gt;</code>
        <p class="form-hint" style="margin-top:8px;">Paste this before &lt;/body&gt; on any page where you want the chatbot.</p>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-nav">
        <button class="tab-btn" data-tab="branding">BRANDING</button>
        <button class="tab-btn" data-tab="ai">AI &amp; CONTENT</button>
        <button class="tab-btn" data-tab="leads">LEADS &amp; CRM</button>
        <button class="tab-btn" data-tab="scheduling">SCHEDULING</button>
        <button class="tab-btn" data-tab="security">SECURITY</button>
        <button class="tab-btn" data-tab="builders">BUILDERS</button>
    </div>

    <form method="POST" id="main-form">
        <input type="hidden" name="action" value="save">

        <!-- ═══ TAB: BRANDING ═══ -->
        <div class="tab-panel" data-panel="branding">

            <!-- Messages & Text -->
            <div class="form-section">
                <h3>WIDGET BRANDING</h3>
                <div class="form-group">
                    <label class="form-label">Display Name</label>
                    <input type="text" name="display_name" class="form-input" value="<?php echo e($tenant['display_name']); ?>" required>
                    <p class="form-hint">Shown in the chat header</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Greeting Message</label>
                    <input type="text" name="greeting" class="form-input" value="<?php echo e($tenant['greeting']); ?>">
                    <p class="form-hint">First message visitors see when they open the chat</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Quick Reply Buttons (one per line)</label>
                    <textarea name="quick_replies" class="form-textarea" style="min-height:80px;"><?php echo e(implode("\n", $tenant['quick_replies'])); ?></textarea>
                </div>
            </div>

            <!-- Colors — top to bottom widget flow -->
            <div class="form-section">
                <h3>WIDGET COLORS</h3>
                <p class="form-hint" style="margin-top:-8px;margin-bottom:16px;">Colors follow the widget top to bottom — header, buttons, bubbles, footer.</p>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">HEADER BACKGROUND</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="text" name="color_header_bg" class="form-input" value="<?php echo e($tenant['color_header_bg'] ?? ''); ?>" placeholder="hex or gradient" style="flex:1;">
                            <div style="width:36px;height:36px;border:1px solid var(--border-light);background:<?php echo e($tenant['color_header_bg'] ?? '#3B7DD8'); ?>;flex-shrink:0;border-radius:4px;"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">HEADER TEXT COLOR</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" value="<?php echo e($tenant['color_header_text'] ?? '#ffffff'); ?>" style="width:40px;height:36px;border:none;background:none;cursor:pointer;padding:0;" onchange="document.querySelector('[name=color_header_text]').value=this.value">
                            <input type="text" name="color_header_text" class="form-input" value="<?php echo e($tenant['color_header_text'] ?? '#ffffff'); ?>" style="flex:1;">
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">QUICK REPLY BTN COLOR</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" value="<?php echo e($tenant['color_quick_btn_text'] ?? '#3B7DD8'); ?>" style="width:40px;height:36px;border:none;background:none;cursor:pointer;padding:0;" onchange="document.querySelector('[name=color_quick_btn_text]').value=this.value">
                            <input type="text" name="color_quick_btn_text" class="form-input" value="<?php echo e($tenant['color_quick_btn_text'] ?? ''); ?>" style="flex:1;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">QUICK REPLY BTN BG</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="text" name="color_quick_btn_bg" class="form-input" value="<?php echo e($tenant['color_quick_btn_bg'] ?? 'transparent'); ?>" style="flex:1;">
                            <div style="width:36px;height:36px;border:1px solid var(--border-light);background:<?php echo e($tenant['color_quick_btn_bg'] ?? 'transparent'); ?>;flex-shrink:0;border-radius:4px;"></div>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">SEND BUTTON COLOR</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" value="<?php echo e($tenant['color_send_btn'] ?? '#3B7DD8'); ?>" style="width:40px;height:36px;border:none;background:none;cursor:pointer;padding:0;" onchange="document.querySelector('[name=color_send_btn]').value=this.value">
                            <input type="text" name="color_send_btn" class="form-input" value="<?php echo e($tenant['color_send_btn'] ?? ''); ?>" style="flex:1;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">USER BUBBLE COLOR</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="text" name="color_user_bubble" class="form-input" value="<?php echo e($tenant['color_user_bubble'] ?? ''); ?>" placeholder="hex or gradient" style="flex:1;">
                            <div style="width:36px;height:36px;border:1px solid var(--border-light);background:<?php echo e($tenant['color_user_bubble'] ?? '#3B7DD8'); ?>;flex-shrink:0;border-radius:4px;"></div>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">AI BUBBLE BORDER</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" value="<?php echo e($tenant['color_ai_bubble_border'] ?? '#3B7DD8'); ?>" style="width:40px;height:36px;border:none;background:none;cursor:pointer;padding:0;" onchange="document.querySelector('[name=color_ai_bubble_border]').value=this.value">
                            <input type="text" name="color_ai_bubble_border" class="form-input" value="<?php echo e($tenant['color_ai_bubble_border'] ?? ''); ?>" style="flex:1;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">FOOTER BACKGROUND</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="text" name="color_footer_bg" class="form-input" value="<?php echo e($tenant['color_footer_bg'] ?? ''); ?>" style="flex:1;">
                            <div style="width:36px;height:36px;border:1px solid var(--border-light);background:<?php echo e($tenant['color_footer_bg'] ?? 'transparent'); ?>;flex-shrink:0;border-radius:4px;"></div>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">FOOTER TEXT COLOR</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" value="<?php echo e($tenant['color_footer_text'] ?? '#ffffff'); ?>" style="width:40px;height:36px;border:none;background:none;cursor:pointer;padding:0;" onchange="document.querySelector('[name=color_footer_text]').value=this.value">
                            <input type="text" name="color_footer_text" class="form-input" value="<?php echo e($tenant['color_footer_text'] ?? '#ffffff'); ?>" style="flex:1;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ACCENT COLOR (bubble &amp; legacy)</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" value="<?php echo e($tenant['accent_color']); ?>" style="width:40px;height:36px;border:none;background:none;cursor:pointer;padding:0;" onchange="document.querySelector('[name=accent_color]').value=this.value">
                            <input type="text" name="accent_color" class="form-input" value="<?php echo e($tenant['accent_color']); ?>" style="flex:1;">
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">ACCENT GRADIENT</label>
                        <input type="text" name="accent_gradient" class="form-input" value="<?php echo e($tenant['accent_gradient'] ?? ''); ?>" placeholder="linear-gradient(135deg, #xxx, #yyy)">
                    </div>
                    <div class="form-group">
                        <label class="form-label">AI ACCENT (links)</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" value="<?php echo e($tenant['ai_accent'] ?? '#8B5CF6'); ?>" style="width:40px;height:36px;border:none;background:none;cursor:pointer;padding:0;" onchange="document.querySelector('[name=ai_accent]').value=this.value">
                            <input type="text" name="ai_accent" class="form-input" value="<?php echo e($tenant['ai_accent'] ?? '#8B5CF6'); ?>" style="flex:1;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Widget Settings -->
            <div class="form-section">
                <h3>WIDGET SETTINGS</h3>
                <div class="form-group">
                    <label class="form-label">Widget Position</label>
                    <select name="widget_position" class="form-select">
                        <option value="bottom-right" <?php echo $tenant['widget_position'] === 'bottom-right' ? 'selected' : ''; ?>>Bottom Right</option>
                        <option value="bottom-left" <?php echo $tenant['widget_position'] === 'bottom-left' ? 'selected' : ''; ?>>Bottom Left</option>
                    </select>
                </div>
            </div>

            <div class="save-row">
                <button type="submit" class="btn btn-primary" style="padding:10px 28px;">SAVE SETTINGS</button>
            </div>
        </div>

        <!-- ═══ TAB: AI & CONTENT ═══ -->
        <div class="tab-panel" data-panel="ai">
            <div class="form-section">
                <h3>AI BEHAVIOR</h3>
                <div class="form-group">
                    <label class="form-label">System Prompt</label>
                    <textarea name="system_prompt" class="form-textarea" style="min-height:280px;"><?php echo e($tenant['system_prompt'] ?? ''); ?></textarea>
                    <p class="form-hint">Defines your chatbot's personality and knowledge. Community-specific info only — the master prompt is prepended automatically.</p>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Primary LLM</label>
                        <select name="primary_llm" class="form-select">
                            <option value="openai" <?php echo $tenant['primary_llm'] === 'openai' ? 'selected' : ''; ?>>OpenAI</option>
                            <option value="anthropic" <?php echo $tenant['primary_llm'] === 'anthropic' ? 'selected' : ''; ?>>Anthropic Claude</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Tokens</label>
                        <input type="number" name="max_tokens" class="form-input" value="<?php echo (int)$tenant['max_tokens']; ?>" min="100" max="4000">
                    </div>
                </div>
            </div>
            <div class="form-section">
                <h3>API KEYS</h3>
                <p class="form-hint" style="margin-top:-8px;margin-bottom:16px;">Optional — if blank, default shared keys are used.</p>
                <div class="form-group">
                    <label class="form-label">OpenAI API Key</label>
                    <input type="password" name="openai_api_key" class="form-input" value="<?php echo e($tenant['openai_api_key'] ?? ''); ?>" placeholder="sk-...">
                    <p class="form-hint">Model: <input type="text" name="openai_model" class="form-input" value="<?php echo e($tenant['openai_model']); ?>" style="display:inline;width:200px;padding:4px 8px;font-size:12px;margin-left:4px;"></p>
                </div>
                <div class="form-group">
                    <label class="form-label">Anthropic API Key</label>
                    <input type="password" name="anthropic_api_key" class="form-input" value="<?php echo e($tenant['anthropic_api_key'] ?? ''); ?>" placeholder="sk-ant-...">
                    <p class="form-hint">Model: <input type="text" name="anthropic_model" class="form-input" value="<?php echo e($tenant['anthropic_model']); ?>" style="display:inline;width:200px;padding:4px 8px;font-size:12px;margin-left:4px;"></p>
                </div>
            </div>
            <div class="save-row">
                <button type="submit" class="btn btn-primary" style="padding:10px 28px;">SAVE SETTINGS</button>
            </div>
        </div>

        <!-- ═══ TAB: LEADS & CRM ═══ -->
        <div class="tab-panel" data-panel="leads">
            <div class="form-section">
                <h3>LEAD NOTIFICATIONS</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Notification Email</label>
                        <input type="email" name="lead_email" class="form-input" value="<?php echo e($tenant['lead_email'] ?? ''); ?>" placeholder="you@company.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Webhook URL (optional)</label>
                        <input type="url" name="lead_webhook" class="form-input" value="<?php echo e($tenant['lead_webhook'] ?? ''); ?>" placeholder="https://hooks.zapier.com/...">
                    </div>
                </div>
            </div>
            <div class="form-section">
                <h3>HUBSPOT INTEGRATION</h3>
                <p class="form-hint" style="margin-top:-8px;margin-bottom:16px;">Route leads to HubSpot with conversation context.</p>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">HubSpot Portal ID</label>
                        <input type="text" name="hubspot_portal_id" class="form-input" value="<?php echo e($tenant['hubspot_portal_id'] ?? ''); ?>" placeholder="12345678">
                    </div>
                    <div class="form-group">
                        <label class="form-label">HubSpot Form ID</label>
                        <input type="text" name="hubspot_form_id" class="form-input" value="<?php echo e($tenant['hubspot_form_id'] ?? ''); ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                    </div>
                </div>
            </div>
            <div class="form-section">
                <h3>PROPERTY INVENTORY (Cecilian XO)</h3>
                <p class="form-hint" style="margin-top:-8px;margin-bottom:16px;">Connect the chatbot to live property inventory. When enabled, the AI can search and present available homes.</p>
                <div class="form-group">
                    <label class="form-label" style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="xo_enabled" value="1" <?php echo !empty($tenant['xo_enabled']) ? 'checked' : ''; ?>>
                        Enable Property Inventory Search
                    </label>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">XO API Base URL</label>
                        <input type="text" name="xo_api_base_url" class="form-input" value="<?php echo e($tenant['xo_api_base_url'] ?? ''); ?>" placeholder="https://hillwood.thexo.io/o/api/v2/map/consumer">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Project Slug</label>
                        <input type="text" name="xo_project_slug" class="form-input" value="<?php echo e($tenant['xo_project_slug'] ?? ''); ?>" placeholder="harvest">
                    </div>
                </div>
            </div>
            <div class="form-section">
                <h3>COMMUNITY INFO</h3>
                <p class="form-hint" style="margin-top:-8px;margin-bottom:16px;">Used for cross-community features and portfolio referrals.</p>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Community Type</label>
                        <select name="community_type" class="form-select">
                            <option value="standard" <?php echo ($tenant['community_type'] ?? '') === 'standard' ? 'selected' : ''; ?>>Standard</option>
                            <option value="community" <?php echo ($tenant['community_type'] ?? '') === 'community' ? 'selected' : ''; ?>>Community</option>
                            <option value="parent" <?php echo ($tenant['community_type'] ?? '') === 'parent' ? 'selected' : ''; ?>>Parent (Portfolio Concierge)</option>
                            <option value="realtor" <?php echo ($tenant['community_type'] ?? '') === 'realtor' ? 'selected' : ''; ?>>Realtor Program</option>
                            <option value="kiosk" <?php echo ($tenant['community_type'] ?? '') === 'kiosk' ? 'selected' : ''; ?>>On-Property Kiosk</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Community Name</label>
                        <input type="text" name="community_name" class="form-input" value="<?php echo e($tenant['community_name'] ?? ''); ?>" placeholder="Harvest">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Community Website URL</label>
                        <input type="url" name="community_url" class="form-input" value="<?php echo e($tenant['community_url'] ?? ''); ?>" placeholder="https://HarvestByHillwood.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Location</label>
                        <input type="text" name="community_location" class="form-input" value="<?php echo e($tenant['community_location'] ?? ''); ?>" placeholder="Argyle, TX">
                    </div>
                </div>
            </div>
            <div class="save-row">
                <button type="submit" class="btn btn-primary" style="padding:10px 28px;">SAVE SETTINGS</button>
            </div>
        </div>

        <!-- ═══ TAB: SCHEDULING ═══ -->
        <div class="tab-panel" data-panel="scheduling">
            <div class="form-section">
                <h3>SCHEDULING</h3>
                <p class="form-hint" style="margin-top:-8px;margin-bottom:16px;">Set your available hours. Visitors can book directly through the chatbot.</p>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Slot Duration</label>
                        <select name="booking_slot_minutes" class="form-select">
                            <?php foreach ([15, 20, 30, 45, 60, 90] as $min): ?>
                                <option value="<?php echo $min; ?>" <?php echo (int)($tenant['booking_slot_minutes'] ?? 30) === $min ? 'selected' : ''; ?>><?php echo $min; ?> minutes</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Buffer Between Bookings</label>
                        <select name="booking_buffer_minutes" class="form-select">
                            <?php foreach ([0, 5, 10, 15, 30] as $min): ?>
                                <option value="<?php echo $min; ?>" <?php echo (int)($tenant['booking_buffer_minutes'] ?? 0) === $min ? 'selected' : ''; ?>><?php echo $min ? "{$min} minutes" : 'None'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Minimum Advance Notice</label>
                        <select name="booking_notice_hours" class="form-select">
                            <?php foreach ([1 => '1 hour', 2 => '2 hours', 4 => '4 hours', 12 => '12 hours', 24 => '24 hours', 48 => '48 hours'] as $h => $label): ?>
                                <option value="<?php echo $h; ?>" <?php echo (int)($tenant['booking_notice_hours'] ?? 24) === $h ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Booking Window</label>
                        <select name="booking_window_days" class="form-select">
                            <?php foreach ([7 => '1 week', 14 => '2 weeks', 21 => '3 weeks', 30 => '1 month', 60 => '2 months'] as $d => $label): ?>
                                <option value="<?php echo $d; ?>" <?php echo (int)($tenant['booking_window_days'] ?? 14) === $d ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">WEEKLY AVAILABILITY</label>
                    <p class="form-hint" style="margin-bottom:12px;">Set your available hours for each day. Leave unchecked for days off.</p>
                    <div id="availability-grid">
                    <?php
                        $dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                        foreach ($dayNames as $dow => $dayName):
                            $rules    = $rulesByDay[$dow] ?? [];
                            $hasRules = !empty($rules);
                            $startTime = $hasRules ? substr($rules[0]['start_time'], 0, 5) : '09:00';
                            $endTime   = $hasRules ? substr($rules[count($rules) - 1]['end_time'], 0, 5) : '17:00';
                    ?>
                        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.04);">
                            <label style="width:36px;">
                                <input type="checkbox" name="avail_day_<?php echo $dow; ?>_enabled" value="1" <?php echo $hasRules ? 'checked' : ''; ?>>
                            </label>
                            <span style="width:100px;font-size:13px;color:<?php echo $hasRules ? '#fff' : '#555'; ?>;"><?php echo $dayName; ?></span>
                            <input type="time" name="avail_day_<?php echo $dow; ?>_start" value="<?php echo $startTime; ?>" class="form-input" style="width:120px;padding:6px 8px;font-size:12px;">
                            <span style="color:#555;">to</span>
                            <input type="time" name="avail_day_<?php echo $dow; ?>_end" value="<?php echo $endTime; ?>" class="form-input" style="width:120px;padding:6px 8px;font-size:12px;">
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="save-row">
                <button type="submit" class="btn btn-primary" style="padding:10px 28px;">SAVE SETTINGS</button>
            </div>
        </div>

        <!-- ═══ TAB: SECURITY ═══ -->
        <div class="tab-panel" data-panel="security">
            <div class="form-section">
                <h3>SECURITY &amp; LIMITS</h3>
                <div class="form-group">
                    <label class="form-label">Allowed Origins (one per line)</label>
                    <textarea name="allowed_origins" class="form-textarea" style="min-height:100px;"><?php echo e(implode("\n", $tenant['allowed_origins'])); ?></textarea>
                    <p class="form-hint">Domains that can embed your widget (e.g. https://yoursite.com). Leave empty to allow all.</p>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Rate Limit / Minute</label>
                        <input type="number" name="rate_limit_per_minute" class="form-input" value="<?php echo (int)$tenant['rate_limit_per_minute']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Rate Limit / Hour</label>
                        <input type="number" name="rate_limit_per_hour" class="form-input" value="<?php echo (int)$tenant['rate_limit_per_hour']; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Max Messages Per Conversation</label>
                    <input type="number" name="max_conversation_length" class="form-input" value="<?php echo (int)$tenant['max_conversation_length']; ?>" style="max-width:200px;">
                </div>
            </div>
            <div class="save-row">
                <button type="submit" class="btn btn-primary" style="padding:10px 28px;">SAVE SETTINGS</button>
            </div>
        </div>

    </form><!-- end main form -->

    <!-- ═══ TAB: BUILDERS (outside main form — has its own POST actions) ═══ -->
    <div class="tab-panel" data-panel="builders">
        <div class="form-section">
            <h3>BUILDERS</h3>
            <p class="form-hint" style="margin-top:-8px;margin-bottom:16px;">Manage the builders available at this community. Visitors will select a builder when booking a tour.</p>

            <?php if (!empty($builders)): ?>
            <div style="margin-bottom:16px;">
                <?php foreach ($builders as $b): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:10px 12px;border-bottom:1px solid rgba(255,255,255,0.04);">
                    <span style="flex:1;font-size:14px;color:#fff;"><?php echo e($b['name']); ?></span>
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Remove <?php echo e(addslashes($b['name'])); ?>?');">
                        <input type="hidden" name="action" value="remove_builder">
                        <input type="hidden" name="builder_id" value="<?php echo (int)$b['id']; ?>">
                        <button type="submit" style="background:none;border:1px solid rgba(196,93,79,0.4);color:#C45D4F;font-size:12px;padding:4px 12px;border-radius:4px;cursor:pointer;">Remove</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color:#555;font-size:13px;margin-bottom:16px;">No builders added yet.</p>
            <?php endif; ?>

            <form method="POST" style="display:flex;gap:8px;align-items:flex-end;">
                <input type="hidden" name="action" value="add_builder">
                <div style="flex:1;">
                    <label class="form-label">Add Builder</label>
                    <input type="text" name="builder_name" class="form-input" placeholder="e.g. Highland Homes" required>
                </div>
                <button type="submit" class="btn btn-primary" style="padding:8px 20px;white-space:nowrap;">ADD</button>
            </form>
        </div>
    </div>

</main>

<script>
(function() {
    var tabs   = document.querySelectorAll('.tab-btn');
    var panels = document.querySelectorAll('.tab-panel');

    function activate(tabName) {
        tabs.forEach(function(t) { t.classList.toggle('active', t.dataset.tab === tabName); });
        panels.forEach(function(p) { p.classList.toggle('active', p.dataset.panel === tabName); });
        // Update URL without reload
        var url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        history.replaceState(null, '', url);
    }

    tabs.forEach(function(t) {
        t.addEventListener('click', function() { activate(t.dataset.tab); });
    });

    // Restore tab from URL or default to branding
    var initialTab = new URLSearchParams(window.location.search).get('tab') || 'branding';
    activate(initialTab);
})();
</script>

<?php renderFooter(); ?>
