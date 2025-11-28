<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/audience_filters.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = getDB();
$period = in_array($_GET['period'] ?? 'month', ['day', 'week', 'month']) ? $_GET['period'] : 'month';

$dateFilter = '';
switch ($period) {
    case 'day':
        $dateFilter = "AND DATE(l.created_at) = CURDATE()";
        break;
    case 'week':
        $dateFilter = "AND l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    default:
        $dateFilter = "AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$campaignStmt = $db->query("SELECT 
    l.campaign_id,
    l.campaign_name,
    COUNT(l.id) as total_leads,
    COUNT(CASE WHEN la.status = 'qualified' THEN 1 END) as qualified_leads,
    COUNT(CASE WHEN la.status = 'assigned' THEN 1 END) as pending_leads,
    COALESCE(SUM(ar.price_offered), 0) as revenue,
    ROUND(COUNT(CASE WHEN la.status = 'qualified' THEN 1 END) * 100.0 / NULLIF(COUNT(l.id),0), 2) as conversion_rate,
    COALESCE(SUM(ab.spend_amount), 0) as ad_spend,
    COALESCE(SUM(ar.price_offered), 0) - COALESCE(SUM(ab.spend_amount), 0) as roi
    FROM leads l
    LEFT JOIN lead_assignments la ON l.id = la.lead_id
    LEFT JOIN agent_responses ar ON l.id = ar.lead_id AND ar.response_status = 'qualified'
    LEFT JOIN ad_budgets ab ON l.campaign_id = ab.campaign_id AND DATE(ab.date) = DATE(l.created_at)
    WHERE l.campaign_name IS NOT NULL $dateFilter
    GROUP BY l.campaign_id, l.campaign_name
    ORDER BY revenue DESC, qualified_leads DESC");
$campaigns = $campaignStmt->fetchAll();

$totalRevenue = array_sum(array_column($campaigns, 'revenue'));
$totalLeads = array_sum(array_column($campaigns, 'total_leads'));
$avgConversion = count($campaigns) ? number_format(array_sum(array_column($campaigns, 'conversion_rate')) / count($campaigns), 2) : 0;

$campaignOptions = $db->query("SELECT DISTINCT campaign_name FROM leads WHERE campaign_name IS NOT NULL ORDER BY campaign_name")->fetchAll(PDO::FETCH_COLUMN);
$cityOptions = $db->query("SELECT DISTINCT city FROM leads WHERE city IS NOT NULL AND city <> '' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
$formOptions = $db->query("SELECT DISTINCT form_name FROM leads WHERE form_name IS NOT NULL AND form_name <> '' ORDER BY form_name")->fetchAll(PDO::FETCH_COLUMN);
$platformOptions = $db->query("SELECT DISTINCT platform FROM leads WHERE platform IS NOT NULL AND platform <> '' ORDER BY platform")->fetchAll(PDO::FETCH_COLUMN);
$tagOptions = $db->query("SELECT DISTINCT tag FROM whatsapp_conversation_tags ORDER BY tag")->fetchAll(PDO::FETCH_COLUMN);
$templateOptions = $db->query("SELECT name, language FROM whatsapp_templates WHERE is_active = 1 ORDER BY name")->fetchAll();

function sanitizeMulti(array|string|null $input): array {
    return array_values(array_filter(array_map('trim', (array)$input), fn($val) => $val !== ''));
}

$searchQuery = trim($_GET['search'] ?? '');
$selectedCampaigns = sanitizeMulti($_GET['campaign'] ?? []);
$selectedCities = sanitizeMulti($_GET['city'] ?? []);
$selectedForms = sanitizeMulti($_GET['form'] ?? []);
$selectedPlatforms = sanitizeMulti($_GET['platform'] ?? []);
$selectedPriorities = sanitizeMulti($_GET['priority'] ?? []);
$selectedStatuses = sanitizeMulti($_GET['status'] ?? []);
$selectedTags = sanitizeMulti($_GET['tags'] ?? []);

$scoreMinDefault = 0;
$scoreMaxDefault = 100;
$scoreMin = isset($_GET['score_min']) ? max(0, (int)$_GET['score_min']) : $scoreMinDefault;
$scoreMax = isset($_GET['score_max']) ? min(100, (int)$_GET['score_max']) : $scoreMaxDefault;
if ($scoreMin > $scoreMax) {
    [$scoreMin, $scoreMax] = [$scoreMax, $scoreMin];
}

$createdFrom = trim($_GET['created_from'] ?? '');
$createdTo = trim($_GET['created_to'] ?? '');
$onlyUnassigned = isset($_GET['only_unassigned']);
$onlyHot = isset($_GET['only_hot']);

$audienceFilter = buildAudienceFilter($filterPayload);
$audienceWhere = $audienceFilter['where'] ? 'WHERE ' . $audienceFilter['where'] : '';
$audienceParams = $audienceFilter['params'];

$audienceSql = "SELECT 
        l.id,
        l.full_name,
        l.phone_number,
        l.city,
        l.priority_level,
        l.lead_score,
        l.campaign_name,
        l.form_name,
        l.platform,
        l.created_at,
        la.status AS assignment_status,
        GROUP_CONCAT(DISTINCT wct.tag ORDER BY wct.tag SEPARATOR ',') AS tags
    FROM leads l
    LEFT JOIN lead_assignments la ON la.lead_id = l.id
    LEFT JOIN whatsapp_conversations wc ON wc.lead_id = l.id
    LEFT JOIN whatsapp_conversation_tags wct ON wct.conversation_id = wc.id
    $audienceWhere
    GROUP BY l.id
    ORDER BY l.created_at DESC
    LIMIT 300";

$audienceStmt = $db->prepare($audienceSql);
$audienceStmt->execute($audienceParams);
$audience = $audienceStmt->fetchAll();

$countSql = "SELECT COUNT(DISTINCT l.id)
    FROM leads l
    LEFT JOIN lead_assignments la ON la.lead_id = l.id
    LEFT JOIN whatsapp_conversations wc ON wc.lead_id = l.id
    LEFT JOIN whatsapp_conversation_tags wct ON wct.conversation_id = wc.id
    $audienceWhere";
$countStmt = $db->prepare($countSql);
$countStmt->execute($audienceParams);
$audienceCount = (int)$countStmt->fetchColumn();

$filterChips = [];
if ($searchQuery) $filterChips[] = "Search: $searchQuery";
if ($selectedCampaigns) $filterChips[] = 'Campaigns (' . count($selectedCampaigns) . ')';
if ($selectedCities) $filterChips[] = 'Cities (' . count($selectedCities) . ')';
if ($selectedForms) $filterChips[] = 'Forms (' . count($selectedForms) . ')';
if ($selectedPlatforms) $filterChips[] = 'Platforms (' . count($selectedPlatforms) . ')';
if ($selectedPriorities) $filterChips[] = 'Priority (' . implode(', ', $selectedPriorities) . ')';
if ($selectedStatuses) $filterChips[] = 'Status (' . implode(', ', $selectedStatuses) . ')';
if ($selectedTags) $filterChips[] = 'Tags (' . count($selectedTags) . ')';
if ($scoreMin > $scoreMinDefault || $scoreMax < $scoreMaxDefault) $filterChips[] = "Score {$scoreMin}-{$scoreMax}";
if ($createdFrom || $createdTo) $filterChips[] = "Created " . ($createdFrom ?: 'start') . " → " . ($createdTo ?: 'now');
if ($onlyUnassigned) $filterChips[] = 'Only unassigned';
if ($onlyHot) $filterChips[] = 'Hot/high only';

$filterPayload = [
    'search' => $searchQuery ?: null,
    'campaign' => $selectedCampaigns ?: null,
    'city' => $selectedCities ?: null,
    'form' => $selectedForms ?: null,
    'platform' => $selectedPlatforms ?: null,
    'priority' => $selectedPriorities ?: null,
    'status' => $selectedStatuses ?: null,
    'tags' => $selectedTags ?: null,
    'score_min' => $scoreMin > $scoreMinDefault ? $scoreMin : null,
    'score_max' => $scoreMax < $scoreMaxDefault ? $scoreMax : null,
    'created_from' => $createdFrom ?: null,
    'created_to' => $createdTo ?: null,
    'only_unassigned' => $onlyUnassigned ? 1 : null,
    'only_hot' => $onlyHot ? 1 : null
];
$filterPayload = array_filter($filterPayload, function ($value) {
    if (is_array($value)) {
        return count($value) > 0;
    }
    return $value !== null;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaigns Command Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="css/global.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
    <link href="css/admin.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        <main class="col-md-10 ms-sm-auto px-md-4">
            <div class="page-heading">
                <div>
                    <p class="eyebrow">WhatsApp Campaigns</p>
                    <h1>Campaigns Command Center</h1>
                    <p class="text-muted mb-0">Build AiSensy-style segments, preview contacts, and trigger precision broadcasts.</p>
                </div>
                <div class="page-heading__actions">
                    <div class="btn-group">
                        <button class="btn btn-outline-primary <?= $period === 'day' ? 'active' : '' ?>" onclick="changePeriod('day')">Today</button>
                        <button class="btn btn-outline-primary <?= $period === 'week' ? 'active' : '' ?>" onclick="changePeriod('week')">Week</button>
                        <button class="btn btn-outline-primary <?= $period === 'month' ? 'active' : '' ?>" onclick="changePeriod('month')">Month</button>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#broadcastModal">
                        <i class="fas fa-paper-plane me-1"></i> Launch Broadcast
                    </button>
                </div>
            </div>

            <?php if ($filterChips): ?>
                <div class="filter-chip-row mb-3">
                    <span class="text-muted small me-2">Active filters:</span>
                    <?php foreach ($filterChips as $chip): ?>
                        <span class="filter-chip"><?= htmlspecialchars($chip) ?></span>
                    <?php endforeach; ?>
                    <a href="campaigns.php" class="ghost-button ghost-button-sm ms-2">Clear filters</a>
                </div>
            <?php endif; ?>

            <section class="lead-stats-grid mb-4">
                <article class="lead-stat-card">
                    <p class="label">Active Campaigns</p>
                    <h3><?= count($campaigns) ?></h3>
                    <small><?= ucfirst($period) ?></small>
                </article>
                <article class="lead-stat-card">
                    <p class="label">Total Leads</p>
                    <h3><?= number_format($totalLeads) ?></h3>
                    <small>Collected in period</small>
                </article>
                <article class="lead-stat-card">
                    <p class="label">Revenue</p>
                    <h3>₹<?= number_format($totalRevenue) ?></h3>
                    <small>Qualified contributions</small>
                </article>
                <article class="lead-stat-card">
                    <p class="label">Avg Conversion</p>
                    <h3><?= $avgConversion ?>%</h3>
                    <small>Qualified / total</small>
                </article>
            </section>

            <form class="card campaign-filter-card mb-4" method="GET">
                <div class="card-body">
                    <div class="campaign-filter-grid">
                        <div>
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" placeholder="Name, phone..." value="<?= htmlspecialchars($searchQuery) ?>">
                        </div>
                        <div>
                            <label class="form-label">Campaigns</label>
                            <select class="form-select" name="campaign[]" multiple>
                                <?php foreach ($campaignOptions as $option): ?>
                                    <option value="<?= htmlspecialchars($option) ?>" <?= in_array($option, $selectedCampaigns) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($option) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Cities</label>
                            <select class="form-select" name="city[]" multiple>
                                <?php foreach ($cityOptions as $option): ?>
                                    <option value="<?= htmlspecialchars($option) ?>" <?= in_array($option, $selectedCities) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($option) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Forms</label>
                            <select class="form-select" name="form[]" multiple>
                                <?php foreach ($formOptions as $option): ?>
                                    <option value="<?= htmlspecialchars($option) ?>" <?= in_array($option, $selectedForms) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($option) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Platforms</label>
                            <select class="form-select" name="platform[]" multiple>
                                <?php foreach ($platformOptions as $option): ?>
                                    <option value="<?= htmlspecialchars($option) ?>" <?= in_array($option, $selectedPlatforms) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($option) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="priority[]" multiple>
                                <?php foreach (['hot','high','medium','low'] as $priority): ?>
                                    <option value="<?= $priority ?>" <?= in_array($priority, $selectedPriorities) ? 'selected' : '' ?>>
                                        <?= ucfirst($priority) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Lead status</label>
                            <select class="form-select" name="status[]" multiple>
                                <?php foreach (['assigned','contacted','qualified','not_qualified','call_not_picked','payment_completed'] as $status): ?>
                                    <option value="<?= $status ?>" <?= in_array($status, $selectedStatuses) ? 'selected' : '' ?>>
                                        <?= ucwords(str_replace('_',' ', $status)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Tags</label>
                            <select class="form-select" name="tags[]" multiple>
                                <?php foreach ($tagOptions as $tag): ?>
                                    <option value="<?= htmlspecialchars($tag) ?>" <?= in_array($tag, $selectedTags) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tag) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Lead score</label>
                            <div class="input-group">
                                <input type="number" class="form-control" min="0" max="100" name="score_min" value="<?= $scoreMin ?>">
                                <span class="input-group-text">to</span>
                                <input type="number" class="form-control" min="0" max="100" name="score_max" value="<?= $scoreMax ?>">
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Created from</label>
                            <input type="date" class="form-control" name="created_from" value="<?= htmlspecialchars($createdFrom) ?>">
                        </div>
                        <div>
                            <label class="form-label">Created to</label>
                            <input type="date" class="form-control" name="created_to" value="<?= htmlspecialchars($createdTo) ?>">
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-3 align-items-center mt-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="onlyUnassigned" name="only_unassigned" <?= $onlyUnassigned ? 'checked' : '' ?>>
                            <label class="form-check-label" for="onlyUnassigned">Only unassigned leads</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="onlyHot" name="only_hot" <?= $onlyHot ? 'checked' : '' ?>>
                            <label class="form-check-label" for="onlyHot">Only hot/high priority</label>
                        </div>
                        <button type="submit" class="btn btn-primary ms-auto">
                            <i class="fas fa-filter me-1"></i> Apply filters
                        </button>
                    </div>
                </div>
            </form>

            <section class="card audience-card mb-4">
                <div class="card-header audience-head">
                    <div>
                        <h5 class="mb-0">Audience Preview</h5>
                        <small class="text-muted"><?= $audienceCount ?> contact(s) match these filters</small>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="ghost-button" onclick="location.reload()"><i class="fas fa-rotate me-1"></i> Refresh</button>
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#broadcastModal">
                            <i class="fas fa-bolt me-1"></i> Send to audience
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($audience)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users-slash"></i>
                            <h3>No contacts match these filters</h3>
                            <p>Try removing one or more filters to widen the reach.</p>
                            <a href="campaigns.php" class="btn btn-outline-primary">Reset filters</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table audience-table align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Contact</th>
                                    <th>City</th>
                                    <th>Priority</th>
                                    <th>Score</th>
                                    <th>Campaign</th>
                                    <th>Tags</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($audience as $contact): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($contact['full_name'] ?? 'Unknown') ?></strong>
                                            <div class="text-muted small"><?= htmlspecialchars($contact['phone_number']) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($contact['city'] ?? '-') ?></td>
                                        <td>
                                            <span class="badge bg-<?= ($contact['priority_level'] === 'hot' ? 'danger' : ($contact['priority_level'] === 'high' ? 'warning text-dark' : 'info')) ?>">
                                                <?= htmlspecialchars($contact['priority_level'] ?? 'medium') ?>
                                            </span>
                                        </td>
                                        <td><?= (int)$contact['lead_score'] ?>/100</td>
                                        <td><?= htmlspecialchars($contact['campaign_name'] ?? '-') ?></td>
                                        <td>
                                            <?php if (!empty($contact['tags'])): ?>
                                                <?php foreach (array_slice(array_filter(array_map('trim', explode(',', $contact['tags']))), 0, 4) as $tag): ?>
                                                    <span class="tag-chip"><?= htmlspecialchars($tag) ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars(ucwords(str_replace('_',' ', $contact['assignment_status'] ?? 'Unassigned'))) ?></td>
                                        <td><?= date('M j, Y', strtotime($contact['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Campaign Performance</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="campaignChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card mb-5">
                <div class="card-header">
                    <h5 class="mb-0">Campaign Details</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th>Campaign</th>
                                <th>Total Leads</th>
                                <th>Qualified</th>
                                <th>Pending</th>
                                <th>Conversion</th>
                                <th>Revenue</th>
                                <th>Ad Spend</th>
                                <th>ROI</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($campaigns as $campaign): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($campaign['campaign_name']) ?></strong></td>
                                    <td><?= (int)$campaign['total_leads'] ?></td>
                                    <td><span class="badge bg-success"><?= (int)$campaign['qualified_leads'] ?></span></td>
                                    <td><span class="badge bg-warning text-dark"><?= (int)$campaign['pending_leads'] ?></span></td>
                                    <td><?= (float)$campaign['conversion_rate'] ?>%</td>
                                    <td>₹<?= number_format($campaign['revenue']) ?></td>
                                    <td>₹<?= number_format($campaign['ad_spend']) ?></td>
                                    <td class="<?= $campaign['roi'] >= 0 ? 'text-success' : 'text-danger' ?>">₹<?= number_format($campaign['roi']) ?></td>
                                    <td class="text-end">
                                        <a href="leads.php?campaign=<?= urlencode($campaign['campaign_name']) ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i> Leads
                                        </a>
                                        <a class="btn btn-sm btn-outline-secondary" href="campaign_recipients.php?id=<?= (int)$campaign['campaign_id'] ?>">
                                            <i class="fas fa-list me-1"></i> Recipients
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($campaigns)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted">No campaign data for this period.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<div class="modal fade" id="broadcastModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Launch WhatsApp Broadcast</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Campaign Name</label>
                        <input type="text" class="form-control" id="bcName" placeholder="Midnight Sale Blast">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Schedule (optional)</label>
                        <input type="datetime-local" class="form-control" id="bcSchedule">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">WhatsApp Template</label>
                        <select class="form-select" id="bcTemplate">
                            <option value="">Select template</option>
                            <?php foreach ($templateOptions as $tpl): ?>
                            <option value="<?= htmlspecialchars($tpl['name']) ?>" data-language="<?= htmlspecialchars($tpl['language']) ?>">
                                    <?= htmlspecialchars($tpl['name']) ?> (<?= htmlspecialchars($tpl['language']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Language Code</label>
                        <input type="text" class="form-control" id="bcLang" value="en_US">
                    </div>
                    <div class="col-12">
                        <div class="alert alert-info d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Audience size:</strong> <span id="audienceSizeLabel"><?= $audienceCount ?></span> contact(s)
                            </div>
                            <small class="text-muted">Matches current filters</small>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Manual numbers (one per line)</label>
                        <textarea class="form-control" rows="4" id="bcNumbers" placeholder="9190xxxxxxx\n9191xxxxxxx"></textarea>
                        <small class="text-muted">Optional: add extra recipients beyond the filtered audience</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Default template variables (JSON)</label>
                        <textarea class="form-control" rows="3" id="bcVars" placeholder='{"name":"John","url":"https://..."}'></textarea>
                        <small class="text-muted">Optional: values for {{placeholders}} used in the template</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" rows="3" placeholder="Optional notes for your team" id="bcNotes"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="createBroadcast">
                    <i class="fas fa-rocket me-1"></i> Send Broadcast
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const appliedFilters = <?= json_encode($filterPayload) ?>;
const audienceCount = <?= (int)$audienceCount ?>;

function changePeriod(period) {
    const url = new URL(window.location.href);
    url.searchParams.set('period', period);
    window.location.href = url.toString();
}

document.getElementById('bcTemplate')?.addEventListener('change', function () {
    const selected = this.options[this.selectedIndex];
    if (selected && selected.dataset.language) {
        document.getElementById('bcLang').value = selected.dataset.language;
    }
});

document.getElementById('createBroadcast').addEventListener('click', async () => {
    if (!audienceCount) {
        alert('No contacts available for the selected filters.');
        return;
    }
    const templateName = document.getElementById('bcTemplate').value;
    if (!templateName) {
        alert('Please select a WhatsApp template.');
        return;
    }
    const payload = {
        name: document.getElementById('bcName').value || `Campaign ${new Date().toLocaleString()}`,
        template_name: templateName,
        language_code: document.getElementById('bcLang').value || 'en_US',
        filters: (function(){
            const f = Object.assign({}, appliedFilters);
            const numsText = document.getElementById('bcNumbers').value || '';
            const nums = numsText.split(/\r?\n/).map(t => t.trim()).filter(Boolean);
            if (nums.length) f.numbers = nums;
            try {
                const v = JSON.parse(document.getElementById('bcVars').value || '{}');
                if (v && typeof v === 'object') f.variables = v;
            } catch {}
            return f;
        })(),
        scheduled_at: document.getElementById('bcSchedule').value || null,
        notes: document.getElementById('bcNotes').value || null
    };
    try {
        const jwt = sessionStorage.getItem('jwt') || '';
        if (!jwt) {
            alert('JWT missing. Please login again.');
            return;
        }
        const res = await fetch('../includes/whatsapp.php/whatsapp/campaigns', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${jwt}`
            },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to create campaign');

        const launch = await fetch(`../includes/whatsapp.php/whatsapp/campaigns/${json.data.id}/launch`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${jwt}` }
        });
        const launchJson = await launch.json();
        if (!launchJson.success) throw new Error(launchJson.message || 'Failed to prepare audience');

        const start = await fetch(`../includes/whatsapp.php/whatsapp/campaigns/${json.data.id}/start`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${jwt}` }
        });
        const startJson = await start.json();
        if (!startJson.success) throw new Error(startJson.message || 'Failed to start campaign');

        bootstrap.Modal.getInstance(document.getElementById('broadcastModal')).hide();
        alert('Broadcast scheduled successfully!');
    } catch (error) {
        alert(error.message);
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const campaignData = <?= json_encode($campaigns) ?>;
    if (!campaignData.length) return;
    const ctx = document.getElementById('campaignChart');
    if (!ctx) return;
    new Chart(ctx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: campaignData.map(c => c.campaign_name || 'Unknown'),
            datasets: [
                {
                    label: 'Total Leads',
                    data: campaignData.map(c => c.total_leads),
                    backgroundColor: 'rgba(54, 162, 235, 0.6)'
                },
                {
                    label: 'Qualified Leads',
                    data: campaignData.map(c => c.qualified_leads),
                    backgroundColor: 'rgba(75, 192, 192, 0.6)'
                },
                {
                    label: 'Revenue (₹)',
                    data: campaignData.map(c => c.revenue),
                    backgroundColor: 'rgba(255, 206, 86, 0.6)',
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            stacked: false,
            scales: {
                y: {
                    beginAtZero: true
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: { drawOnChartArea: false }
                }
            }
        }
    });
});
</script>
</body>
</html>

