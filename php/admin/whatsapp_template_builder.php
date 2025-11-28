<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/whatsapp_cloud.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$metaError = null;
if (!whatsappToken() || !getSetting('whatsapp_business_account_id') || !whatsappPhoneNumberId()) {
    $metaError = 'WhatsApp Cloud API credentials are missing. Templates cannot be submitted until they are configured.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create WhatsApp Template</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/global.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
    <link href="css/admin.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
    <link href="css/templates.css" rel="stylesheet">
    <script>
        window.__metaError = <?php echo json_encode($metaError, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    </script>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="builder-header">
                    <div>
                        <p class="builder-eyebrow">Meta Cloud Templates</p>
                        <h1>Create WhatsApp Template</h1>
                        <p>Design Meta-approved templates with a live WhatsApp preview.</p>
                    </div>
                    <div class="builder-actions">
                        <a href="whatsapp_templates.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Templates
                        </a>
                        <button class="btn btn-primary" onclick="saveTemplate()">
                            <i class="fas fa-save me-2"></i>Submit to Meta
                        </button>
                    </div>
                </div>

                <?php if ($metaError): ?>
                    <div class="alert alert-warning">
                        <strong>Meta Sync Warning:</strong> <?= htmlspecialchars($metaError); ?>
                    </div>
                <?php endif; ?>

                <div class="template-panel-body builder-page">
                    <div class="panel-form">
                        <div class="form-grid">
                            <div class="form-field">
                                <label for="templateCategory">Template Category</label>
                                <select id="templateCategory" class="form-input">
                                    <option value="UTILITY">Utility</option>
                                    <option value="MARKETING">Marketing</option>
                                    <option value="AUTHENTICATION">Authentication</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label for="templateLanguage">Template Language</label>
                                <select id="templateLanguage" class="form-input">
                                    <option value="en_US">English (US)</option>
                                    <option value="en_GB">English (UK)</option>
                                    <option value="hi">Hindi</option>
                                    <option value="es">Spanish</option>
                                    <option value="fr">French</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label for="templateName">Template Name *</label>
                                <input type="text" id="templateName" class="form-input" placeholder="e.g., order_update" autocomplete="off">
                                <small>Only lowercase letters, numbers, underscores</small>
                            </div>
                            <div class="form-field">
                                <label for="templateType">Template Type</label>
                                <select id="templateType" class="form-input">
                                    <option value="TEXT">Text</option>
                                    <option value="CAROUSEL">Carousel</option>
                                    <option value="IMAGE">Image</option>
                                    <option value="VIDEO">Video</option>
                                    <option value="DOCUMENT">Document</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-field" id="headerTextWrapper">
                            <label for="headerText">Header Text</label>
                            <input type="text" id="headerText" class="form-input" maxlength="60" placeholder="e.g., Appointment Reminder">
                            <div class="variable-buttons">
                                <button type="button" class="btn-variable" onclick="insertVariable('headerText', '{{1}}')">{{1}}</button>
                                <button type="button" class="btn-variable" onclick="insertVariable('headerText', '{{name}}')">{{name}}</button>
                            </div>
                        </div>

                        <div class="form-field" id="headerMediaWrapper" style="display:none;">
                            <label for="headerMediaUrl">Media Sample URL</label>
                            <div class="input-group">
                                <input type="url" id="headerMediaUrl" class="form-input" placeholder="https://cdn.proschool360.com/sample.jpg">
                                <button type="button" class="btn btn-outline-primary" id="uploadHeaderMediaBtn">
                                    <i class="fas fa-cloud-upload-alt me-1"></i>Upload
                                </button>
                            </div>
                            <input type="file" id="headerMediaFile" class="d-none" accept="image/*,video/*,application/pdf">
                            <small id="headerMediaHint">Upload an image/video/document and we'll host it on R2 for Meta review.</small>
                        </div>

                        <div class="form-field">
                            <label for="templateMessage">Template Format</label>
                            <textarea id="templateMessage" class="form-input textarea" maxlength="1024" placeholder="Write the main body message..."></textarea>
                            <div class="textarea-footer">
                                <div class="variable-buttons">
                                    <button type="button" class="btn-variable" onclick="insertVariable('templateMessage', '{{1}}')">{{1}}</button>
                                    <button type="button" class="btn-variable" onclick="insertVariable('templateMessage', '{{2}}')">{{2}}</button>
                                    <button type="button" class="btn-variable" onclick="insertVariable('templateMessage', '{{name}}')">{{name}}</button>
                                    <button type="button" class="btn-variable" onclick="insertVariable('templateMessage', '{{phone}}')">{{phone}}</button>
                                    <button type="button" class="btn-variable" onclick="insertVariable('templateMessage', '{{email}}')">{{email}}</button>
                                </div>
                                <span id="bodyCounter">0 / 1024</span>
                            </div>
                        </div>

                        <div class="form-field">
                            <label for="footerText">Template Footer (Optional)</label>
                            <input type="text" id="footerText" class="form-input" maxlength="60" placeholder="e.g., Reply STOP to unsubscribe">
                        </div>

                        <div class="interactive-section">
                            <div class="section-header">
                                <div>
                                    <label>Interactive Actions</label>
                                    <p>Configure Call-To-Action or Quick Replies</p>
                                </div>
                                <div class="action-modes">
                                    <label><input type="radio" name="actionMode" value="none" checked> None</label>
                                    <label><input type="radio" name="actionMode" value="cta"> Call to Actions</label>
                                    <label><input type="radio" name="actionMode" value="quick"> Quick Replies</label>
                                    <label><input type="radio" name="actionMode" value="all"> All</label>
                                </div>
                            </div>
                            <div class="action-chips">
                                <button type="button" class="action-chip" onclick="addInteractiveAction('quick_reply')">+ Quick Replies <span id="quickReplyCount">0/10</span></button>
                                <button type="button" class="action-chip" onclick="addInteractiveAction('url')">+ URL <span id="urlCount">0/2</span></button>
                                <button type="button" class="action-chip" onclick="addInteractiveAction('phone')">+ Phone Number <span id="phoneCount">0/1</span></button>
                                <button type="button" class="action-chip" onclick="addInteractiveAction('copy_code')">+ Copy Code <span id="copyCount">0/1</span></button>
                            </div>
                            <div id="interactiveList"></div>
                        </div>

                        <div class="form-field">
                            <label>Variable Sample Values</label>
                            <p class="text-muted small mb-2">Meta requires sample values for every {{placeholder}} before approval.</p>
                            <div id="placeholderValues" class="placeholder-values card p-3">
                                <div class="text-muted">Start typing in header or body to add variables.</div>
                            </div>
                        </div>

                        <div class="carousel-builder" id="carouselBuilder" style="display:none;">
                            <div class="carousel-builder-header">
                                <div>
                                    <h5>Carousel Cards</h5>
                                    <p class="text-muted mb-0">Add up to 10 cards with media, text, and CTAs.</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="addCarouselCardBtn">
                                    <i class="fas fa-plus me-1"></i>Add Card
                                </button>
                            </div>
                            <div id="carouselCardList"></div>
                        </div>
                        <input type="file" id="carouselMediaInput" class="d-none" accept="image/*,video/*">
                    </div>
                    <div class="panel-preview">
                        <div class="preview-card">
                            <div class="preview-meta">
                                <span class="preview-status" id="previewStatus">UTILITY · en_US</span>
                                <span class="preview-type" id="previewType">TEXT TEMPLATE</span>
                            </div>
                            <div class="preview-body">
                                <div id="previewHeader" class="preview-header"></div>
                                <div id="previewBody" class="preview-message">Start typing to see the preview...</div>
                                <div id="previewFooter" class="preview-footer"></div>
                            </div>
                            <div id="previewButtons" class="preview-buttons"></div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/template-builder.js?v=<?= filemtime(__DIR__ . '/js/template-builder.js'); ?>"></script>
</body>
</html>

