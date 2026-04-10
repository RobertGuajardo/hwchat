<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/layout.php';
requireSuperAdmin();

$db = Database::db();
$success = '';
$error = '';

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_communities') {
        $ids = $_POST['community_id'] ?? [];
        $names = $_POST['community_name'] ?? [];
        $locations = $_POST['community_location'] ?? [];
        $urls = $_POST['community_url'] ?? [];
        $districts = $_POST['school_district'] ?? [];
        $taglines = $_POST['community_tagline'] ?? [];

        foreach ($ids as $i => $id) {
            Database::updateCommunityFields($id, [
                'community_name'     => trim($names[$i] ?? ''),
                'community_location' => trim($locations[$i] ?? ''),
                'community_url'      => trim($urls[$i] ?? ''),
                'school_district'    => trim($districts[$i] ?? ''),
                'community_tagline'  => trim($taglines[$i] ?? ''),
            ]);
        }
        $success = 'Community directory updated.';
    }

    if ($action === 'save_crossref') {
        $crossRefJson = trim($_POST['cross_referral_groups'] ?? '{}');
        $decoded = json_decode($crossRefJson, true);
        if ($decoded === null && $crossRefJson !== '{}') {
            $error = 'Invalid JSON in cross-referral groups.';
        } else {
            Database::setGlobalSetting('cross_referral_groups', $crossRefJson);
            $success = 'Cross-referral groups saved.';
        }
    }
}

$communities = Database::getCommunities();
$crossRefRaw = Database::getGlobalSetting('cross_referral_groups');
$generatedText = Database::generateDirectoryText();

renderHead('Community Directory');
renderNav('communities');
?>
    <main class="container">
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo e($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="action-bar">
            <div>
                <h2>COMMUNITY DIRECTORY</h2>
                <p style="color:var(--text-muted);font-size:12px;margin-top:4px;">
                    Manage the sibling community reference used for cross-referrals. Edit below, then copy the generated text into the Master Prompt.
                </p>
            </div>
        </div>

        <!-- Community Table Editor -->
        <form method="POST">
            <input type="hidden" name="action" value="save_communities">

            <div class="table-wrap" style="margin-bottom:24px;">
                <table>
                    <thead>
                        <tr>
                            <th>COMMUNITY NAME</th>
                            <th>LOCATION</th>
                            <th>SCHOOL DISTRICT</th>
                            <th>TAGLINE</th>
                            <th>WEBSITE</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($communities as $c): ?>
                        <tr>
                            <td>
                                <input type="hidden" name="community_id[]" value="<?php echo e($c['id']); ?>">
                                <input type="text" name="community_name[]" class="form-input" value="<?php echo e($c['community_name'] ?? ''); ?>" style="min-width:120px;">
                                <div class="tenant-id" style="margin-top:4px;"><?php echo e($c['id']); ?></div>
                            </td>
                            <td>
                                <input type="text" name="community_location[]" class="form-input" value="<?php echo e($c['community_location'] ?? ''); ?>" style="min-width:120px;" placeholder="City, TX">
                            </td>
                            <td>
                                <input type="text" name="school_district[]" class="form-input" value="<?php echo e($c['school_district'] ?? ''); ?>" style="min-width:130px;" placeholder="e.g. Argyle ISD">
                            </td>
                            <td>
                                <input type="text" name="community_tagline[]" class="form-input" value="<?php echo e($c['community_tagline'] ?? ''); ?>" style="min-width:180px;" placeholder="Short description">
                            </td>
                            <td>
                                <input type="text" name="community_url[]" class="form-input" value="<?php echo e($c['community_url'] ?? ''); ?>" style="min-width:180px;" placeholder="https://...">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <button type="submit" class="btn btn-primary" style="padding:10px 24px;">SAVE COMMUNITY INFO</button>
        </form>

        <!-- Cross-Referral Groups -->
        <div style="margin-top:48px;">
            <div class="form-section">
                <h3>CROSS-REFERRAL GROUPS</h3>
                <p style="color:var(--text-muted);font-size:12px;margin-bottom:16px;">
                    JSON groupings for location and lifestyle referrals. Use tenant IDs (e.g. "hw_harvest") as values.
                </p>
                <form method="POST">
                    <input type="hidden" name="action" value="save_crossref">
                    <textarea name="cross_referral_groups" class="prompt-editor" style="min-height:250px;font-size:12px;"><?php echo e($crossRefRaw ?: '{}'); ?></textarea>
                    <div style="margin-top:12px;">
                        <button type="submit" class="btn btn-primary">SAVE GROUPS</button>
                        <button type="button" class="btn" onclick="formatJson()" style="margin-left:8px;">FORMAT JSON</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Generated Directory Text -->
        <div style="margin-top:48px;">
            <div class="form-section" style="border-bottom:none;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <div>
                        <h3>GENERATED DIRECTORY TEXT</h3>
                        <p style="color:var(--text-muted);font-size:12px;margin-top:4px;">
                            Copy this into the Master Prompt to replace the HILLWOOD COMMUNITY DIRECTORY and CROSS-REFERRAL GUIDE sections.
                        </p>
                    </div>
                    <button class="btn btn-primary" onclick="copyDirectory()">COPY TO CLIPBOARD</button>
                </div>
                <textarea id="directoryOutput" class="prompt-editor" style="min-height:350px;" readonly><?php echo e($generatedText); ?></textarea>
                <div class="char-count"><?php echo number_format(strlen($generatedText)); ?> characters</div>
                <div style="margin-top:12px;">
                    <a href="master-prompt.php" class="btn">OPEN MASTER PROMPT EDITOR →</a>
                </div>
            </div>
        </div>
    </main>

    <script>
    function copyDirectory() {
        const ta = document.getElementById('directoryOutput');
        ta.select();
        navigator.clipboard.writeText(ta.value).then(() => {
            const btn = event.target;
            btn.textContent = 'COPIED!';
            setTimeout(() => btn.textContent = 'COPY TO CLIPBOARD', 2000);
        });
    }

    function formatJson() {
        const ta = document.querySelector('textarea[name="cross_referral_groups"]');
        try {
            const obj = JSON.parse(ta.value);
            ta.value = JSON.stringify(obj, null, 2);
        } catch (e) {
            alert('Invalid JSON: ' + e.message);
        }
    }
    </script>
<?php renderFooter(); ?>
