<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../../lib/regions.php';
requireSuperAdmin();

$db = Database::db();
$tenantId = $_GET['id'] ?? '';
$success = '';
$error = '';

if (!$tenantId) { header('Location: tenants.php'); exit; }

// Load tenant
$stmt = $db->prepare('SELECT * FROM tenants WHERE id = :id');
$stmt->execute(['id' => $tenantId]);
$tenant = $stmt->fetch();
if (!$tenant) { header('Location: tenants.php'); exit; }

// Decode JSONB
$tenant['quick_replies']   = json_decode($tenant['quick_replies'] ?? '[]', true) ?: [];
$tenant['allowed_origins'] = json_decode($tenant['allowed_origins'] ?? '[]', true) ?: [];

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    try {
        $stmt = $db->prepare('
            UPDATE tenants SET
                display_name = :display_name,
                community_name = :community_name,
                community_url = :community_url,
                community_location = :community_location,
                community_type = :community_type,
                school_district = :school_district,
                community_tagline = :community_tagline,
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
                openai_model = :openai_model,
                anthropic_model = :anthropic_model,
                max_tokens = :max_tokens,
                lead_email = :lead_email,
                xo_enabled = :xo_enabled,
                xo_api_base_url = :xo_api_base_url,
                xo_project_slug = :xo_project_slug,
                hubspot_portal_id = :hubspot_portal_id,
                hubspot_form_id = :hubspot_form_id,
                allowed_origins = :allowed_origins,
                region = :region,
                updated_at = NOW()
            WHERE id = :id
        ');

        $qr = array_filter(array_map('trim', explode("\n", $_POST['quick_replies'] ?? '')));
        $origins = array_filter(array_map('trim', explode("\n", $_POST['allowed_origins'] ?? '')));

        $stmt->execute([
            'display_name'       => trim($_POST['display_name'] ?? $tenant['display_name']),
            'community_name'     => trim($_POST['community_name'] ?? ''),
            'community_url'      => trim($_POST['community_url'] ?? ''),
            'community_location' => trim($_POST['community_location'] ?? ''),
            'community_type'     => $_POST['community_type'] ?? 'community',
            'school_district'    => trim($_POST['school_district'] ?? ''),
            'community_tagline'  => trim($_POST['community_tagline'] ?? ''),
            'greeting'           => trim($_POST['greeting'] ?? ''),
            'accent_color'       => trim($_POST['accent_color'] ?? '#3B7DD8'),
            'accent_gradient'    => trim($_POST['accent_gradient'] ?? ''),
            'ai_accent'          => trim($_POST['ai_accent'] ?? ''),
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
            'widget_position'    => $_POST['widget_position'] ?? 'bottom-right',
            'quick_replies'      => json_encode(array_values($qr)),
            'system_prompt'      => trim($_POST['system_prompt'] ?? ''),
            'primary_llm'        => $_POST['primary_llm'] ?? 'openai',
            'openai_model'       => trim($_POST['openai_model'] ?? 'gpt-4o'),
            'anthropic_model'    => trim($_POST['anthropic_model'] ?? 'claude-sonnet-4-20250514'),
            'max_tokens'         => (int)($_POST['max_tokens'] ?? 500),
            'lead_email'         => trim($_POST['lead_email'] ?? ''),
            'xo_enabled'         => isset($_POST['xo_enabled']) ? 'true' : 'false',
            'xo_api_base_url'    => trim($_POST['xo_api_base_url'] ?? ''),
            'xo_project_slug'    => trim($_POST['xo_project_slug'] ?? ''),
            'hubspot_portal_id'  => trim($_POST['hubspot_portal_id'] ?? ''),
            'hubspot_form_id'    => trim($_POST['hubspot_form_id'] ?? ''),
            'allowed_origins'    => json_encode(array_values($origins)),
            'region'             => ($r = trim($_POST['region'] ?? '')) !== '' && in_array($r, array_keys(REGIONS)) ? $r : null,
            'id'                 => $tenantId,
        ]);

        $success = 'Settings saved for ' . e($tenant['display_name']) . '.';

        // Reload tenant
        $stmt = $db->prepare('SELECT * FROM tenants WHERE id = :id');
        $stmt->execute(['id' => $tenantId]);
        $tenant = $stmt->fetch();
        $tenant['quick_replies']   = json_decode($tenant['quick_replies'] ?? '[]', true) ?: [];
        $tenant['allowed_origins'] = json_decode($tenant['allowed_origins'] ?? '[]', true) ?: [];

    } catch (PDOException $e) {
        $error = 'Save failed: ' . $e->getMessage();
    }
}

renderHead('Edit: ' . ($tenant['community_name'] ?: $tenant['display_name']));
renderNav('tenants');
?>
    <main class="container" style="max-width:900px;">
        <div style="margin-bottom:24px;">
            <a href="tenants.php" class="btn btn-sm btn-ghost">← BACK TO TENANTS</a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo e($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
            <h2 style="font-size:18px;color:var(--text-bright);"><?php echo e($tenant['community_name'] ?: $tenant['display_name']); ?></h2>
            <span class="tenant-id"><?php echo e($tenantId); ?></span>
            <?php if ($tenant['is_active']): ?>
                <span class="badge badge-active">ACTIVE</span>
            <?php else: ?>
                <span class="badge badge-inactive">OFF</span>
            <?php endif; ?>
        </div>

        <!-- Embed Code -->
        <div class="embed-box">
            <span class="meta-label" style="margin-bottom:8px;display:block;">EMBED CODE</span>
            <code>&lt;script src="https://hwchat.robertguajardo.com/widget/robchat.js" data-robchat-id="<?php echo e($tenantId); ?>" defer&gt;&lt;/script&gt;</code>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="save">

            <!-- Community Info -->
            <div class="form-section">
                <h3>COMMUNITY INFO</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">DISPLAY NAME</label>
                        <input type="text" name="display_name" class="form-input" value="<?php echo e($tenant['display_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">COMMUNITY NAME</label>
                        <input type="text" name="community_name" class="form-input" value="<?php echo e($tenant['community_name'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">COMMUNITY URL</label>
                        <input type="text" name="community_url" class="form-input" value="<?php echo e($tenant['community_url'] ?? ''); ?>" placeholder="https://www.example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">LOCATION</label>
                        <input type="text" name="community_location" class="form-input" value="<?php echo e($tenant['community_location'] ?? ''); ?>" placeholder="City, TX">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">SCHOOL DISTRICT</label>
                        <input type="text" name="school_district" class="form-input" value="<?php echo e($tenant['school_district'] ?? ''); ?>" placeholder="e.g. Argyle ISD">
                    </div>
                    <div class="form-group">
                        <label class="form-label">COMMUNITY TAGLINE</label>
                        <input type="text" name="community_tagline" class="form-input" value="<?php echo e($tenant['community_tagline'] ?? ''); ?>" placeholder="Short description for directory">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">COMMUNITY TYPE</label>
                        <select name="community_type" class="form-select">
                            <?php foreach (['community','standard','parent','realtor','kiosk'] as $ct): ?>
                                <option value="<?php echo $ct; ?>" <?php echo ($tenant['community_type'] ?? '') === $ct ? 'selected' : ''; ?>><?php echo strtoupper($ct); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">REGION</label>
                        <select name="region" class="form-select">
                            <option value="">None</option>
                            <?php foreach (REGIONS as $key => $label): ?>
                                <option value="<?php echo e($key); ?>" <?php echo ($tenant['region'] ?? '') === $key ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">Tenants with no region are excluded from the scope dropdown and aggregate views.</div>
                    </div>
                </div>
            </div>

            <!-- Widget Branding -->
            <div class="form-section">
                <h3>WIDGET BRANDING</h3>
                <div class="form-group">
                    <label class="form-label">GREETING</label>
                    <input type="text" name="greeting" class="form-input" value="<?php echo e($tenant['greeting']); ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">ACCENT COLOR</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="text" name="accent_color" class="form-input" value="<?php echo e($tenant['accent_color']); ?>" style="flex:1;">
                            <div style="width:36px;height:36px;border:1px solid var(--border-light);background:<?php echo e($tenant['accent_color']); ?>;flex-shrink:0;"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">AI ACCENT (LINKS)</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="text" name="ai_accent" class="form-input" value="<?php echo e($tenant['ai_accent'] ?? ''); ?>" style="flex:1;">
                            <div style="width:36px;height:36px;border:1px solid var(--border-light);background:<?php echo e($tenant['ai_accent'] ?? '#8B5CF6'); ?>;flex-shrink:0;"></div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">ACCENT GRADIENT</label>
                    <input type="text" name="accent_gradient" class="form-input" value="<?php echo e($tenant['accent_gradient'] ?? ''); ?>" placeholder="linear-gradient(135deg, #xxx, #yyy)">
                </div>

                <!-- Extended Widget Colors -->
                <h4 style="font-size:12px;color:var(--text-muted);letter-spacing:0.08em;margin:20px 0 12px;">WIDGET COLOR CONTROLS</h4>
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
                            <input type="text" name="color_header_text" class="form-input" value="<?php echo e($tenant['color_header_text'] ?? '#ffffff'); ?>" style="flex:1;">
                            <div style="width:36px;height:36px;border:1px solid var(--border-light);background:<?php echo e($tenant['color_header_text'] ?? '#ffffff'); ?>;flex-shrink:0;border-radius:4px;"></div>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">QUICK REPLY BTN COLOR</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="text" name="color_quick_btn_text" class="form-input" value="<?php echo e($tenant['color_quick_btn_text'] ?? ''); ?>" style="flex:1;">
                            <div style="width:36px;height:36px;border:1px solid var(--border-light);background:<?php echo e($tenant['color_quick_btn_text'] ?? '#3B7DD8'); ?>;flex-shrink:0;border-radius:4px;"></div>
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
                            <input type="text" name="color_send_btn" class="form-input" value="<?php echo e($tenant['color_send_btn'] ?? ''); ?>" style="flex:1;">
                            <div style="width:36px;height:36px;border:1px solid var(--border-light);background:<?php echo e($tenant['color_send_btn'] ?? '#3B7DD8'); ?>;flex-shrink:0;border-radius:4px;"></div>
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
                            <input type="text" name="color_ai_bubble_border" class="form-input" value="<?php echo e($tenant['color_ai_bubble_border'] ?? ''); ?>" style="flex:1;">
                            <div style="width:36px;height:36px;border:1px solid var(--border-light);background:<?php echo e($tenant['color_ai_bubble_border'] ?? '#3B7DD8'); ?>;flex-shrink:0;border-radius:4px;"></div>
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
                            <input type="text" name="color_footer_text" class="form-input" value="<?php echo e($tenant['color_footer_text'] ?? '#ffffff'); ?>" style="flex:1;">
                            <div style="width:36px;height:36px;border:1px solid var(--border-light);background:<?php echo e($tenant['color_footer_text'] ?? '#ffffff'); ?>;flex-shrink:0;border-radius:4px;"></div>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">WIDGET POSITION</label>
                        <select name="widget_position" class="form-select">
                            <option value="bottom-right" <?php echo $tenant['widget_position'] === 'bottom-right' ? 'selected' : ''; ?>>Bottom Right</option>
                            <option value="bottom-left" <?php echo $tenant['widget_position'] === 'bottom-left' ? 'selected' : ''; ?>>Bottom Left</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">QUICK REPLIES (ONE PER LINE)</label>
                    <textarea name="quick_replies" class="form-textarea" rows="4"><?php echo e(implode("\n", $tenant['quick_replies'])); ?></textarea>
                </div>
            </div>

            <!-- AI / System Prompt -->
            <div class="form-section">
                <h3>TENANT SYSTEM PROMPT</h3>
                <p style="color:var(--text-muted);font-size:12px;margin-bottom:12px;">
                    This is appended after the master prompt. Should contain community-specific info only.
                </p>
                <div class="form-group">
                    <textarea name="system_prompt" class="prompt-editor" style="min-height:300px;"><?php echo e($tenant['system_prompt']); ?></textarea>
                    <div class="char-count"><?php echo number_format(strlen($tenant['system_prompt'])); ?> characters</div>
                </div>
            </div>

            <!-- LLM Config -->
            <div class="form-section">
                <h3>LLM CONFIG</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">PRIMARY LLM</label>
                        <select name="primary_llm" class="form-select">
                            <option value="openai" <?php echo $tenant['primary_llm'] === 'openai' ? 'selected' : ''; ?>>OpenAI</option>
                            <option value="anthropic" <?php echo $tenant['primary_llm'] === 'anthropic' ? 'selected' : ''; ?>>Anthropic</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">MAX TOKENS</label>
                        <input type="number" name="max_tokens" class="form-input" value="<?php echo (int)$tenant['max_tokens']; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">OPENAI MODEL</label>
                        <input type="text" name="openai_model" class="form-input" value="<?php echo e($tenant['openai_model']); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ANTHROPIC MODEL</label>
                        <input type="text" name="anthropic_model" class="form-input" value="<?php echo e($tenant['anthropic_model']); ?>">
                    </div>
                </div>
            </div>

            <!-- XO / HubSpot -->
            <div class="form-section">
                <h3>INTEGRATIONS</h3>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="xo_enabled" <?php echo $tenant['xo_enabled'] ? 'checked' : ''; ?>>
                        <span class="form-label" style="margin:0;">XO INVENTORY ENABLED</span>
                    </label>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">XO API BASE URL</label>
                        <input type="text" name="xo_api_base_url" class="form-input" value="<?php echo e($tenant['xo_api_base_url'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">XO PROJECT SLUG</label>
                        <input type="text" name="xo_project_slug" class="form-input" value="<?php echo e($tenant['xo_project_slug'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">HUBSPOT PORTAL ID</label>
                        <input type="text" name="hubspot_portal_id" class="form-input" value="<?php echo e($tenant['hubspot_portal_id'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">HUBSPOT FORM ID</label>
                        <input type="text" name="hubspot_form_id" class="form-input" value="<?php echo e($tenant['hubspot_form_id'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Lead / CORS -->
            <div class="form-section">
                <h3>LEADS & SECURITY</h3>
                <div class="form-group">
                    <label class="form-label">LEAD NOTIFICATION EMAIL</label>
                    <input type="email" name="lead_email" class="form-input" value="<?php echo e($tenant['lead_email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">ALLOWED ORIGINS (ONE PER LINE)</label>
                    <textarea name="allowed_origins" class="form-textarea" rows="4"><?php echo e(implode("\n", $tenant['allowed_origins'])); ?></textarea>
                    <div class="form-hint">Include https:// prefix. Leave empty to use global defaults.</div>
                </div>
            </div>

            <div style="padding-top:16px;">
                <a href="tenants.php" class="btn">CANCEL</a>
            </div>
        </form>
    </main>
    <form id="sticky-save-form"></form>
    <button form="sticky-save" type="submit" class="btn btn-primary" style="position:fixed;bottom:24px;right:32px;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,0.3);padding:12px 28px;font-size:14px;" onclick="document.querySelector('form').submit();">SAVE SETTINGS</button>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.querySelector('form');
    var btn = document.createElement('button');
    btn.type = 'submit';
    btn.textContent = 'SAVE SETTINGS';
    btn.style.cssText = 'position:fixed;bottom:24px;right:32px;z-index:99999;background:var(--blue);color:#fff;border:none;border-radius:8px;padding:14px 28px;font-size:14px;font-weight:600;font-family:DM Sans,sans-serif;cursor:pointer;box-shadow:0 4px 20px rgba(0,0,0,0.4);letter-spacing:0.04em;';
    btn.addEventListener('click', function() { form.submit(); });
    document.body.appendChild(btn);
});
</script>
<?php renderFooter(); ?>
