<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/cache_helper.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = getDB();
$success = '';
$error = '';
$cacheRegistry = AdminCache::registry();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_settings') {
        try {
            $db->beginTransaction();
            
            foreach ($_POST['settings'] as $key => $value) {
                $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
                $stmt->execute([$key, $value]);
            }
            
            $db->commit();
            $success = "Settings updated successfully";
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Failed to update settings: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'clear_cache') {
        $segment = $_POST['cache_segment'] ?? 'all';
        $segmentKey = $segment === 'all' ? null : $segment;
        if ($segmentKey && !isset($cacheRegistry[$segmentKey])) {
            $error = "Unknown cache segment selected.";
        } else {
            $result = AdminCache::clear($segmentKey);
            $files = $result['files'];
            $size = AdminCache::formatBytes($result['bytes']);
            $timestamp = date('Y-m-d H:i:s');
            try {
                $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
                $stmt->execute(['cache_last_cleared', $timestamp]);
            } catch (Exception $e) {
                // Swallow insert errors but still show message.
            }
            $success = "Cleared {$files} cache file(s) ({$size}).";
        }
    }
}

// Get all settings
$stmt = $db->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Default settings if not set
$defaultSettings = [
    'auto_assign_leads' => '1',
    'lead_scoring_enabled' => '1',
    'notification_enabled' => '1',
    'reminder_enabled' => '1',
    'default_lead_priority' => 'medium',
    'max_leads_per_agent' => '50',
    'response_time_threshold' => '24',
    'qualification_threshold' => '70',
    'system_timezone' => 'Asia/Kolkata',
    'email_notifications' => '0',
    'sms_notifications' => '0',
    'whatsapp_enabled' => '1',
    'whatsapp_token' => '',
    'whatsapp_phone_number_id' => '',
    'meta_app_secret' => '',
    'meta_verify_token' => '',
    'meta_graph_version' => 'v21.0',
    'pusher_app_id' => '',
    'pusher_key' => '',
    'pusher_secret' => '',
    'pusher_cluster' => '',
    'r2_access_key' => '',
    'r2_secret_key' => '',
    'r2_account_id' => '',
    'r2_bucket' => '',
    'r2_endpoint' => '',
    'r2_region' => 'auto',
    'r2_custom_domain' => '',
    'cache_last_cleared' => '',
    'whatsapp_waba_id' => '',
    'cashfree_client_id' => '',
    'cashfree_client_secret' => '',
    'cashfree_sandbox' => '1'
];

foreach ($defaultSettings as $key => $default) {
    if (!isset($settings[$key])) {
        $settings[$key] = $default;
    }
}

$cacheInventory = AdminCache::inventory();
$lastCacheClear = $settings['cache_last_cleared'] ?? null;
$lastCacheClearHuman = $lastCacheClear ? date('M j, Y g:i A', strtotime($lastCacheClear)) : 'Never';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - CRM Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/global.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
    <link href="css/admin.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">System Settings</h1>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <!-- Lead Management Settings -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-users"></i> Lead Management</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Auto Assign Leads</label>
                                    <select name="settings[auto_assign_leads]" class="form-select">
                                        <option value="1" <?= $settings['auto_assign_leads'] == '1' ? 'selected' : '' ?>>Enabled</option>
                                        <option value="0" <?= $settings['auto_assign_leads'] == '0' ? 'selected' : '' ?>>Disabled</option>
                                    </select>
                                    <small class="text-muted">Automatically assign new leads to agents</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Lead Scoring Enabled</label>
                                    <select name="settings[lead_scoring_enabled]" class="form-select">
                                        <option value="1" <?= $settings['lead_scoring_enabled'] == '1' ? 'selected' : '' ?>>Enabled</option>
                                        <option value="0" <?= $settings['lead_scoring_enabled'] == '0' ? 'selected' : '' ?>>Disabled</option>
                                    </select>
                                    <small class="text-muted">Automatically score leads based on criteria</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Default Lead Priority</label>
                                    <select name="settings[default_lead_priority]" class="form-select">
                                        <option value="low" <?= $settings['default_lead_priority'] == 'low' ? 'selected' : '' ?>>Low</option>
                                        <option value="medium" <?= $settings['default_lead_priority'] == 'medium' ? 'selected' : '' ?>>Medium</option>
                                        <option value="high" <?= $settings['default_lead_priority'] == 'high' ? 'selected' : '' ?>>High</option>
                                        <option value="hot" <?= $settings['default_lead_priority'] == 'hot' ? 'selected' : '' ?>>Hot</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Max Leads Per Agent</label>
                                    <input type="number" name="settings[max_leads_per_agent]" class="form-control" 
                                        value="<?= htmlspecialchars($settings['max_leads_per_agent']) ?>" min="1" max="1000">
                                    <small class="text-muted">Maximum number of leads assigned to each agent</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Response Time Threshold (hours)</label>
                                    <input type="number" name="settings[response_time_threshold]" class="form-control" 
                                        value="<?= htmlspecialchars($settings['response_time_threshold']) ?>" min="1">
                                    <small class="text-muted">Alert if agent doesn't respond within this time</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Qualification Threshold (score)</label>
                                    <input type="number" name="settings[qualification_threshold]" class="form-control" 
                                        value="<?= htmlspecialchars($settings['qualification_threshold']) ?>" min="0" max="100">
                                    <small class="text-muted">Minimum lead score to be considered qualified</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notification Settings -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-bell"></i> Notifications</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Push Notifications</label>
                                    <select name="settings[notification_enabled]" class="form-select">
                                        <option value="1" <?= $settings['notification_enabled'] == '1' ? 'selected' : '' ?>>Enabled</option>
                                        <option value="0" <?= $settings['notification_enabled'] == '0' ? 'selected' : '' ?>>Disabled</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email Notifications</label>
                                    <select name="settings[email_notifications]" class="form-select">
                                        <option value="1" <?= $settings['email_notifications'] == '1' ? 'selected' : '' ?>>Enabled</option>
                                        <option value="0" <?= $settings['email_notifications'] == '0' ? 'selected' : '' ?>>Disabled</option>
                                    </select>
                                    <small class="text-muted">Send email fallback to agents</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">SMS Notifications</label>
                                    <select name="settings[sms_notifications]" class="form-select">
                                        <option value="1" <?= $settings['sms_notifications'] == '1' ? 'selected' : '' ?>>Enabled</option>
                                        <option value="0" <?= $settings['sms_notifications'] == '0' ? 'selected' : '' ?>>Disabled</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">WhatsApp Enabled</label>
                                    <select name="settings[whatsapp_enabled]" class="form-select">
                                        <option value="1" <?= $settings['whatsapp_enabled'] == '1' ? 'selected' : '' ?>>Enabled</option>
                                        <option value="0" <?= $settings['whatsapp_enabled'] == '0' ? 'selected' : '' ?>>Disabled</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Admin WhatsApp Alerts</label>
                                    <select name="settings[admin_whatsapp_alerts]" class="form-select">
                                        <option value="1" <?= ($settings['admin_whatsapp_alerts'] ?? '0') == '1' ? 'selected' : '' ?>>Enabled</option>
                                        <option value="0" <?= ($settings['admin_whatsapp_alerts'] ?? '0') == '0' ? 'selected' : '' ?>>Disabled</option>
                                    </select>
                                    <small class="text-muted">Send alerts to admin numbers when assigned agent is offline</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Admin Alert Numbers (comma separated)</label>
                                    <input type="text" name="settings[admin_alert_numbers]" class="form-control" value="<?= htmlspecialchars($settings['admin_alert_numbers'] ?? '') ?>" placeholder="919XXXXXXXXX, 919YYYYYYYYY">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reminder Settings -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-clock"></i> Reminders</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Reminder System</label>
                                    <select name="settings[reminder_enabled]" class="form-select">
                                        <option value="1" <?= $settings['reminder_enabled'] == '1' ? 'selected' : '' ?>>Enabled</option>
                                        <option value="0" <?= $settings['reminder_enabled'] == '0' ? 'selected' : '' ?>>Disabled</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- WhatsApp Cloud API -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fab fa-whatsapp"></i> WhatsApp Cloud API</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label class="form-label">Lead Scoring Weights</label>
                                    <div class="row g-2">
                                        <div class="col-md-3"><input type="number" name="settings[score_very_fresh_bonus]" class="form-control" value="<?= htmlspecialchars($settings['score_very_fresh_bonus'] ?? 25) ?>" placeholder="Very Fresh +"></div>
                                        <div class="col-md-3"><input type="number" name="settings[score_fresh_bonus]" class="form-control" value="<?= htmlspecialchars($settings['score_fresh_bonus'] ?? 15) ?>" placeholder="Fresh +"></div>
                                        <div class="col-md-3"><input type="number" name="settings[score_old_penalty]" class="form-control" value="<?= htmlspecialchars($settings['score_old_penalty'] ?? 10) ?>" placeholder="Old -"></div>
                                        <div class="col-md-3"><input type="number" name="settings[score_city_bonus]" class="form-control" value="<?= htmlspecialchars($settings['score_city_bonus'] ?? 10) ?>" placeholder="Metro +"></div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Graph API Version</label>
                                    <input type="text" name="settings[meta_graph_version]" class="form-control" value="<?= htmlspecialchars($settings['meta_graph_version']) ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Verify Token</label>
                                    <input type="text" name="settings[meta_verify_token]" class="form-control" value="<?= htmlspecialchars($settings['meta_verify_token']) ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">App Secret</label>
                                    <input type="text" name="settings[meta_app_secret]" class="form-control" value="<?= htmlspecialchars($settings['meta_app_secret']) ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Access Token</label>
                                    <input type="text" name="settings[whatsapp_token]" class="form-control" value="<?= htmlspecialchars($settings['whatsapp_token']) ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number ID</label>
                                    <input type="text" name="settings[whatsapp_phone_number_id]" class="form-control" value="<?= htmlspecialchars($settings['whatsapp_phone_number_id']) ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Business Account ID (WABA ID)</label>
                                    <input type="text" name="settings[whatsapp_waba_id]" class="form-control" value="<?= htmlspecialchars($settings['whatsapp_waba_id'] ?? '') ?>" placeholder="Required for Analytics">
                                    <small class="text-muted">WhatsApp Business Account ID for analytics and welcome sequences</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cashfree Payment Gateway -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-credit-card"></i> Cashfree Payment Gateway</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Client ID</label>
                                    <input type="text" name="settings[cashfree_client_id]" class="form-control" value="<?= htmlspecialchars($settings['cashfree_client_id'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Client Secret</label>
                                    <input type="password" name="settings[cashfree_client_secret]" class="form-control" value="<?= htmlspecialchars($settings['cashfree_client_secret'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Sandbox Mode</label>
                                    <select name="settings[cashfree_sandbox]" class="form-select">
                                        <option value="1" <?= ($settings['cashfree_sandbox'] ?? '1') == '1' ? 'selected' : '' ?>>Enabled (Sandbox)</option>
                                        <option value="0" <?= ($settings['cashfree_sandbox'] ?? '1') == '0' ? 'selected' : '' ?>>Disabled (Production)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pusher Configuration -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-broadcast-tower"></i> Pusher</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">App ID</label>
                                    <input type="text" name="settings[pusher_app_id]" class="form-control" value="<?= htmlspecialchars($settings['pusher_app_id']) ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Key</label>
                                    <input type="text" name="settings[pusher_key]" class="form-control" value="<?= htmlspecialchars($settings['pusher_key']) ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Secret</label>
                                    <input type="text" name="settings[pusher_secret]" class="form-control" value="<?= htmlspecialchars($settings['pusher_secret']) ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Cluster</label>
                                    <input type="text" name="settings[pusher_cluster]" class="form-control" value="<?= htmlspecialchars($settings['pusher_cluster']) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Settings -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-cog"></i> System</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">System Timezone</label>
                                    <select name="settings[system_timezone]" class="form-select">
                                        <option value="Asia/Kolkata" <?= $settings['system_timezone'] == 'Asia/Kolkata' ? 'selected' : '' ?>>Asia/Kolkata (IST)</option>
                                        <option value="UTC" <?= $settings['system_timezone'] == 'UTC' ? 'selected' : '' ?>>UTC</option>
                                        <option value="America/New_York" <?= $settings['system_timezone'] == 'America/New_York' ? 'selected' : '' ?>>America/New_York (EST)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Storage & Media -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-cloud-upload-alt"></i> Cloudflare R2 Storage</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Files uploaded from chats or templates are hosted on your Cloudflare R2 bucket for WhatsApp delivery.</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Access Key ID</label>
                                    <input type="text" name="settings[r2_access_key]" class="form-control" value="<?= htmlspecialchars($settings['r2_access_key']) ?>" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Secret Access Key</label>
                                    <input type="password" name="settings[r2_secret_key]" class="form-control" value="<?= htmlspecialchars($settings['r2_secret_key']) ?>" autocomplete="new-password">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Account ID</label>
                                    <input type="text" name="settings[r2_account_id]" class="form-control" value="<?= htmlspecialchars($settings['r2_account_id']) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Bucket Name</label>
                                    <input type="text" name="settings[r2_bucket]" class="form-control" value="<?= htmlspecialchars($settings['r2_bucket']) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Region</label>
                                    <input type="text" name="settings[r2_region]" class="form-control" value="<?= htmlspecialchars($settings['r2_region'] ?: 'auto') ?>" placeholder="auto">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">API Endpoint</label>
                                    <input type="text" name="settings[r2_endpoint]" class="form-control" value="<?= htmlspecialchars($settings['r2_endpoint']) ?>" placeholder="https://{account}.r2.cloudflarestorage.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Public CDN Domain (optional)</label>
                                    <input type="text" name="settings[r2_custom_domain]" class="form-control" value="<?= htmlspecialchars($settings['r2_custom_domain']) ?>" placeholder="https://cdn.example.com">
                                    <small class="text-muted">Used for serving media to WhatsApp and agents if you have a custom domain mapped to R2.</small>
                                </div>
                            </div>
                            <div class="alert alert-warning mt-3 mb-0">
                                <i class="fas fa-lock me-2"></i>Keep these credentials safe. They are stored encrypted and only used for uploading WhatsApp media.
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                        <a href="index.php" class="btn btn-secondary btn-lg ms-2">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>

                <!-- Cache & Maintenance -->
                <section class="card mb-5 maintenance-card">
                    <div class="card-header">
                        <h5><i class="fas fa-broom"></i> Cache & Performance Maintenance</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-4">
                            Keep every admin page in sync by clearing stale snapshots, exports, and WhatsApp template caches.
                            Clear selectively or wipe everything in one go.
                        </p>
                        <div class="cache-grid mb-4">
                            <?php foreach ($cacheInventory as $cacheKey => $cacheMeta): ?>
                                <article class="cache-chip">
                                    <div class="cache-chip-icon">
                                        <i class="<?= htmlspecialchars($cacheMeta['icon']) ?>"></i>
                                    </div>
                                    <div>
                                        <p class="cache-chip-label"><?= htmlspecialchars($cacheMeta['label']) ?></p>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($cacheMeta['size_human']) ?>
                                            <?= $cacheMeta['updated_human'] ? ' · Updated ' . htmlspecialchars($cacheMeta['updated_human']) : ' · Empty' ?>
                                        </small>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <form method="POST" class="row g-3 align-items-end">
                            <input type="hidden" name="action" value="clear_cache">
                            <div class="col-md-4">
                                <label class="form-label">Scope</label>
                                <select name="cache_segment" class="form-select">
                                    <option value="all">All caches</option>
                                    <?php foreach ($cacheRegistry as $key => $meta): ?>
                                        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($meta['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8 d-flex flex-wrap gap-3 align-items-center">
                                <button type="submit" class="btn btn-danger btn-lg">
                                    <i class="fas fa-trash-restore"></i> Clear Selected Cache
                                </button>
                                <span class="text-muted small">
                                    Last cleared: <?= htmlspecialchars($lastCacheClearHuman) ?>
                                </span>
                            </div>
                        </form>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

