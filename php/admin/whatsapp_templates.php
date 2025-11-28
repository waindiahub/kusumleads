<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/whatsapp_cloud.php';
require_once '../includes/cache_helper.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$pdo = getDB();

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $source = $_GET['source'] ?? 'local';
    
    if ($source === 'meta') {
        $refreshParam = $_GET['refresh'] ?? $_GET['force'] ?? null;
        $forceRefresh = false;
        if ($refreshParam !== null) {
            $forceRefresh = in_array(strtolower((string)$refreshParam), ['1', 'true', 'yes', 'force', 'refresh'], true);
        }
        handleMetaTemplatesAjax($forceRefresh);
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare("SELECT * FROM whatsapp_templates WHERE is_active = 1 ORDER BY created_at DESC");
        $stmt->execute();
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $result = false;
        
        switch ($input['action']) {
            case 'create':
                $stmt = $pdo->prepare("INSERT INTO whatsapp_templates (name, message, media_type, media_url, buttons, category, language, header_text, header_media_type, header_media_url, footer_text, placeholders, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $result = $stmt->execute([
                    $input['data']['name'],
                    $input['data']['message'],
                    $input['data']['media_type'] ?? 'none',
                    $input['data']['media_url'] ?? null,
                    json_encode($input['data']['buttons'] ?? []),
                    $input['data']['category'] ?? 'Utility',
                    $input['data']['language'] ?? 'en_US',
                    $input['data']['header_text'] ?? null,
                    $input['data']['header_media_type'] ?? 'none',
                    $input['data']['header_media_url'] ?? null,
                    $input['data']['footer_text'] ?? null,
                    json_encode($input['data']['placeholders'] ?? []),
                    $input['data']['status'] ?? 'approved'
                ]);
                break;
            case 'update':
                $stmt = $pdo->prepare("UPDATE whatsapp_templates SET name = ?, message = ?, media_type = ?, media_url = ?, buttons = ?, category = ?, language = ?, header_text = ?, header_media_type = ?, header_media_url = ?, footer_text = ?, placeholders = ?, status = ? WHERE id = ?");
                $result = $stmt->execute([
                    $input['data']['name'],
                    $input['data']['message'],
                    $input['data']['media_type'] ?? 'none',
                    $input['data']['media_url'] ?? null,
                    json_encode($input['data']['buttons'] ?? []),
                    $input['data']['category'] ?? 'Utility',
                    $input['data']['language'] ?? 'en_US',
                    $input['data']['header_text'] ?? null,
                    $input['data']['header_media_type'] ?? 'none',
                    $input['data']['header_media_url'] ?? null,
                    $input['data']['footer_text'] ?? null,
                    json_encode($input['data']['placeholders'] ?? []),
                    $input['data']['status'] ?? 'approved',
                    $input['id']
                ]);
                break;
            case 'delete':
                $stmt = $pdo->prepare("UPDATE whatsapp_templates SET is_active = 0 WHERE id = ?");
                $result = $stmt->execute([$input['id']]);
                break;
            case 'duplicate':
                $stmt = $pdo->prepare("SELECT * FROM whatsapp_templates WHERE id = ?");
                $stmt->execute([$input['id']]);
                $template = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($template) {
                    $stmt = $pdo->prepare("INSERT INTO whatsapp_templates (name, message, media_type, media_url, buttons, category, language, header_text, header_media_type, header_media_url, footer_text, placeholders, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $result = $stmt->execute([
                        $template['name'] . ' (Copy)',
                        $template['message'],
                        $template['media_type'],
                        $template['media_url'],
                        $template['buttons'],
                        $template['category'],
                        $template['language'],
                        $template['header_text'],
                        $template['header_media_type'],
                        $template['header_media_url'],
                        $template['footer_text'],
                        $template['placeholders'],
                        'pending'
                    ]);
                }
                break;
        }
        
        echo json_encode(['success' => $result]);
        exit;
    }
}

$metaBootstrap = fetchMetaTemplatesResponse();
$preloadedTemplates = $metaBootstrap['success'] ? ($metaBootstrap['data'] ?? []) : [];
$metaError = null;
$metaSource = $metaBootstrap['source'] ?? 'live';
$lastMetaSync = $metaBootstrap['cached_at'] ?? null;
if (!$metaBootstrap['success']) {
    $metaError = $metaBootstrap['message'] ?? 'Could not connect to Meta APIs';
} elseif ($metaSource === 'cache') {
    $lastSynced = $lastMetaSync ? date('M j, Y g:i A', $lastMetaSync) : 'an earlier sync';
    $reason = $metaBootstrap['message'] ?? 'Meta API is currently unreachable';
    $metaError = "{$reason}. Showing cached templates from {$lastSynced}.";
}

if ($metaBootstrap['success']) {
    $stats = calculateTemplateStats($preloadedTemplates);
} else {
    $stats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM whatsapp_templates WHERE is_active = 1")->fetchColumn(),
        'approved' => $pdo->query("SELECT COUNT(*) FROM whatsapp_templates WHERE is_active = 1 AND status = 'approved'")->fetchColumn(),
        'pending' => $pdo->query("SELECT COUNT(*) FROM whatsapp_templates WHERE is_active = 1 AND status = 'pending'")->fetchColumn(),
        'rejected' => $pdo->query("SELECT COUNT(*) FROM whatsapp_templates WHERE is_active = 1 AND status = 'rejected'")->fetchColumn()
    ];
}

function handleMetaTemplatesAjax(bool $forceRefresh = false) {
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            echo json_encode(fetchMetaTemplatesResponse(['force_refresh' => $forceRefresh]));
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $action = $input['action'] ?? null;
            switch ($action) {
                case 'create':
                    $result = createMetaTemplateHandler($input['data'] ?? []);
                    if ($result['success']) {
                        AdminCache::clear('meta_templates');
                    }
                    echo json_encode($result);
                    break;
                case 'delete':
                    $result = deleteMetaTemplateHandler($input['name'] ?? null, $input['language'] ?? null);
                    if ($result['success']) {
                        AdminCache::clear('meta_templates');
                    }
                    echo json_encode($result);
                    break;
                default:
                    echo json_encode(['success' => false, 'message' => 'Unsupported action']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Unsupported method']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

function fetchMetaTemplatesResponse(array $options = []) {
    $forceRefresh = $options['force_refresh'] ?? false;
    $token = whatsappToken();
    $wabaId = getSetting('whatsapp_business_account_id');
    if (!$token || !$wabaId) {
        return ['success' => false, 'message' => 'WhatsApp Cloud API credentials are missing'];
    }
    
    $version = whatsappGraphVersion();
    $url = "https://graph.facebook.com/{$version}/{$wabaId}/message_templates?limit=100";
    $templates = [];

    if (!$forceRefresh) {
        $cached = AdminCache::read('meta_templates');
        if ($cached) {
            return [
                'success' => true,
                'data' => $cached['data'],
                'source' => 'cache',
                'cached_at' => $cached['cached_at'] ?? null
            ];
        }
    }
    
    do {
        $response = metaGraphRequest('GET', $url, $token);
        if (!$response['success']) {
            $stale = AdminCache::read('meta_templates', ['allow_stale' => true]);
            if ($stale) {
                return [
                    'success' => true,
                    'data' => $stale['data'],
                    'source' => 'cache',
                    'cached_at' => $stale['cached_at'] ?? null,
                    'message' => $response['message'] ?? 'Meta API error'
                ];
            }
            return $response;
        }
        $payload = $response['data'];
        if (isset($payload['data'])) {
            foreach ($payload['data'] as $template) {
                $templates[] = normalizeMetaTemplate($template);
            }
        }
        $url = $payload['paging']['next'] ?? null;
    } while ($url);
    
    AdminCache::write('meta_templates', $templates);
    return [
        'success' => true,
        'data' => $templates,
        'source' => 'live',
        'cached_at' => time()
    ];
}

function calculateTemplateStats($templates) {
    $counts = [
        'total' => count($templates),
        'approved' => 0,
        'pending' => 0,
        'rejected' => 0
    ];
    
    foreach ($templates as $template) {
        $status = strtolower($template['status'] ?? '');
        if (isset($counts[$status])) {
            $counts[$status]++;
        }
    }
    
    return $counts;
}

function normalizeMetaTemplate($template) {
    $components = $template['components'] ?? [];
    $header = [
        'type' => 'none',
        'format' => 'TEXT',
        'text' => null,
        'example' => null
    ];
    $body = ['text' => null];
    $footer = ['text' => null];
    $buttons = [];
    $carousel = null;
    
    foreach ($components as $component) {
        $type = strtolower($component['type'] ?? '');
        if ($type === 'header') {
            $header['format'] = strtoupper($component['format'] ?? 'TEXT');
            $header['type'] = strtolower($header['format']);
            $header['text'] = $component['text'] ?? null;
            $header['example'] = $component['example']['header_handle'][0] ?? null;
        } elseif ($type === 'body') {
            $body['text'] = $component['text'] ?? null;
        } elseif ($type === 'footer') {
            $footer['text'] = $component['text'] ?? null;
        } elseif ($type === 'buttons') {
            $buttons = mapMetaButtons($component['buttons'] ?? []);
        } elseif ($type === 'carousel') {
            $carousel = $component;
        }
    }
    
    return [
        'id' => $template['id'] ?? null,
        'name' => $template['name'] ?? '',
        'language' => $template['language'] ?? 'en_US',
        'category' => strtoupper($template['category'] ?? 'UTILITY'),
        'status' => strtoupper($template['status'] ?? 'PENDING'),
        'quality' => $template['quality_score_category'] ?? null,
        'rejection_reason' => $template['rejected_reason'] ?? null,
        'type' => detectTemplateType($components),
        'components' => [
            'header' => $header,
            'body' => $body,
            'footer' => $footer,
            'buttons' => $buttons,
            'carousel' => $carousel,
            'raw' => $components
        ],
        'last_updated' => $template['last_updated_time'] ?? ($template['last_update_time'] ?? null)
    ];
}

function detectTemplateType($components) {
    foreach ($components as $component) {
        if (($component['type'] ?? '') === 'CAROUSEL') {
            return 'CAROUSEL';
        }
        if (($component['type'] ?? '') === 'HEADER') {
            $format = strtoupper($component['format'] ?? 'TEXT');
            if ($format !== 'TEXT') {
                return $format;
            }
        }
    }
    return 'TEXT';
}

function mapMetaButtons($buttons) {
    $mapped = [];
    foreach ($buttons as $button) {
        $type = strtoupper($button['type'] ?? 'QUICK_REPLY');
        if ($type === 'QUICK_REPLY') {
            $mapped[] = [
                'type' => 'quick_reply',
                'text' => $button['text'] ?? '',
                'value' => $button['payload'] ?? ''
            ];
        } elseif ($type === 'URL') {
            $mapped[] = [
                'type' => 'url',
                'text' => $button['text'] ?? '',
                'value' => $button['url'] ?? ($button['example']['default'] ?? '')
            ];
        } elseif ($type === 'PHONE_NUMBER') {
            $mapped[] = [
                'type' => 'phone',
                'text' => $button['text'] ?? '',
                'value' => $button['phone_number'] ?? ''
            ];
        } elseif ($type === 'COPY_CODE') {
            $mapped[] = [
                'type' => 'copy_code',
                'text' => $button['text'] ?? '',
                'value' => $button['code'] ?? ''
            ];
        }
    }
    return $mapped;
}

function createMetaTemplateHandler($data) {
    $token = whatsappToken();
    $wabaId = getSetting('whatsapp_business_account_id');
    if (!$token || !$wabaId) {
        return ['success' => false, 'message' => 'WhatsApp Cloud API credentials are missing'];
    }
    
    $name = $data['name'] ?? '';
    $language = $data['language'] ?? 'en_US';
    $category = strtoupper($data['category'] ?? 'UTILITY');
    $message = trim($data['message'] ?? '');
    
    if (!$name || !preg_match('/^[a-z0-9_]+$/', $name)) {
        return ['success' => false, 'message' => 'Template name must be lowercase alphanumeric with underscores'];
    }
    if (!$message) {
        return ['success' => false, 'message' => 'Template body is required'];
    }
    
    try {
        $components = buildMetaComponentsPayload($data);
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
    
    $payload = [
        'name' => $name,
        'language' => $language,
        'category' => $category,
        'components' => $components
    ];
    
    $endpoint = "{$wabaId}/message_templates";
    $response = metaGraphRequest('POST', $endpoint, $token, $payload);
    if (!$response['success']) {
        return $response;
    }
    
    return ['success' => true, 'message' => 'Template submitted to Meta', 'data' => $response['data']];
}

function deleteMetaTemplateHandler($nameOrId, $language = null) {
    $token = whatsappToken();
    $wabaId = getSetting('whatsapp_business_account_id');
    if (!$token || !$wabaId) {
        return ['success' => false, 'message' => 'WhatsApp Cloud API credentials are missing'];
    }
    
    if (!$nameOrId) {
        return ['success' => false, 'message' => 'Template identifier missing'];
    }
    
    if (is_numeric($nameOrId)) {
        $endpoint = $nameOrId;
    } else {
        $language = $language ?? 'en_US';
        $endpoint = "{$wabaId}/message_templates?name={$nameOrId}&language={$language}";
    }
    
    $response = metaGraphRequest('DELETE', $endpoint, $token);
    if (!$response['success']) {
        return $response;
    }
    return ['success' => true, 'message' => 'Template deleted successfully'];
}

function buildMetaComponentsPayload($data) {
    $components = [];
    $templateType = strtoupper($data['template_type'] ?? 'TEXT');
    $headerText = trim($data['header_text'] ?? '');
    $headerMediaUrl = $data['header_media_url'] ?? null;
    $headerMediaHandle = null;
    $placeholderStore = normalizePlaceholderStore($data['placeholders'] ?? []);
    
    if (in_array($templateType, ['IMAGE', 'VIDEO', 'DOCUMENT'])) {
        if (!$headerMediaUrl) {
            throw new Exception('A media URL is required for media templates');
        }
        $headerMediaHandle = ensureHeaderMediaHandle(strtolower($templateType), $headerMediaUrl);
        $components[] = [
            'type' => 'HEADER',
            'format' => $templateType,
            'example' => [
                'header_handle' => [$headerMediaHandle]
            ]
        ];
    } elseif ($templateType === 'TEXT' && $headerText) {
        $headerComponent = [
            'type' => 'HEADER',
            'format' => 'TEXT',
            'text' => $headerText
        ];
        $headerExamples = extractPlaceholderExamples($headerText, $placeholderStore);
        if ($headerExamples) {
            $headerComponent['example'] = ['header_text' => [$headerExamples]];
        }
        $components[] = $headerComponent;
    } elseif ($templateType === 'CAROUSEL') {
        if (empty($data['cards'])) {
            throw new Exception('At least one card is required for carousel templates');
        }
        $cardsPayload = [];
        foreach ($data['cards'] as $card) {
            $cardMedia = $card['media_url'] ?? null;
            if (!$cardMedia) {
                throw new Exception('Each carousel card must include an image URL');
            }
            $mediaHandle = ensureHeaderMediaHandle('image', $cardMedia);
            $cardBody = trim(($card['title'] ?? '') . "\n" . ($card['body'] ?? ''));
            $cardComponents = [
                [
                    'type' => 'HEADER',
                    'format' => 'IMAGE',
                    'example' => ['header_handle' => [$mediaHandle]]
                ],
                [
                    'type' => 'BODY',
                    'text' => $cardBody ?: 'Card body'
                ]
            ];
            if (!empty($card['button_text']) && !empty($card['button_url'])) {
                $cardComponents[] = [
                    'type' => 'BUTTONS',
                    'buttons' => [[
                        'type' => 'URL',
                        'text' => $card['button_text'],
                        'url' => $card['button_url'],
                        'example' => ['default' => $card['button_url']]
                    ]]
                ];
            }
            $cardsPayload[] = ['components' => $cardComponents];
        }
        $components[] = [
            'type' => 'CAROUSEL',
            'cards' => $cardsPayload
        ];
    }
    
    $bodyComponent = [
        'type' => 'BODY',
        'text' => $data['message']
    ];
    $bodyExamples = extractPlaceholderExamples($data['message'] ?? '', $placeholderStore);
    if ($bodyExamples) {
        $bodyComponent['example'] = ['body_text' => [$bodyExamples]];
    }
    $components[] = $bodyComponent;
    
    if (!empty($data['footer_text'])) {
        $components[] = [
            'type' => 'FOOTER',
            'text' => $data['footer_text']
        ];
    }
    
    $buttons = [];
    foreach (($data['buttons'] ?? []) as $button) {
        $btnType = $button['type'] ?? 'quick_reply';
        $text = $button['text'] ?? '';
        $value = $button['value'] ?? '';
        if (!$text) {
            continue;
        }
        if ($btnType === 'quick_reply') {
            $btn = ['type' => 'QUICK_REPLY', 'text' => $text];
            if ($value) {
                $btn['payload'] = $value;
            }
            $buttons[] = $btn;
        } elseif ($btnType === 'url') {
            if (!$value) {
                throw new Exception('URL buttons require a link');
            }
            $buttons[] = ['type' => 'URL', 'text' => $text, 'url' => $value, 'example' => ['default' => $value]];
        } elseif ($btnType === 'phone') {
            if (!$value) {
                throw new Exception('Phone buttons require a phone number');
            }
            $buttons[] = ['type' => 'PHONE_NUMBER', 'text' => $text, 'phone_number' => $value];
        } elseif ($btnType === 'copy_code') {
            if (!$value) {
                throw new Exception('Copy code buttons require a code value');
            }
            $buttons[] = ['type' => 'COPY_CODE', 'text' => $text, 'code' => $value];
        }
    }
    
    if ($buttons) {
        $components[] = [
            'type' => 'BUTTONS',
            'buttons' => $buttons
        ];
    }
    
    return $components;
}

function normalizePlaceholderStore($placeholders) {
    if (is_array($placeholders)) {
        return $placeholders;
    }
    if (is_string($placeholders) && $placeholders !== '') {
        $decoded = json_decode($placeholders, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return [];
}

function extractPlaceholderExamples($text, array $store) {
    if (!$text || empty($store)) {
        return [];
    }
    preg_match_all('/\{\{\s*([^}]+)\s*\}\}/', $text, $matches);
    $values = [];
    foreach ($matches[1] as $key) {
        $key = trim($key);
        if ($key !== '' && isset($store[$key]) && $store[$key] !== '') {
            $values[] = $store[$key];
        }
    }
    return $values;
}

function ensureHeaderMediaHandle($type, $url) {
    $token = whatsappToken();
    $phoneId = whatsappPhoneNumberId();
    if (!$token || !$phoneId) {
        throw new Exception('Phone number ID is missing for media upload');
    }
    $version = whatsappGraphVersion();
    $endpoint = "https://graph.facebook.com/{$version}/{$phoneId}/media";
    $payload = [
        'messaging_product' => 'whatsapp',
        'type' => $type,
        'link' => $url
    ];
    $response = metaGraphRequest('POST', $endpoint, $token, $payload, true);
    if (!$response['success'] || empty($response['data']['id'])) {
        throw new Exception($response['message'] ?? 'Failed to upload media to Meta');
    }
    return $response['data']['id'];
}

function metaGraphRequest($method, $endpoint, $token, $payload = null, $isMultipart = false) {
    $isFullUrl = str_starts_with($endpoint, 'http');
    $url = $isFullUrl ? $endpoint : 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . ltrim($endpoint, '/');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    
    $headers = ['Authorization: Bearer ' . $token];
    if ($payload !== null) {
        if ($isMultipart) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        } else {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'message' => $error];
    }
    
    $data = json_decode($raw, true);
    if ($data === null) {
        return ['success' => false, 'message' => 'Meta API returned an unexpected response', 'data' => $raw];
    }
    if (isset($data['error'])) {
        return [
            'success' => false,
            'message' => $data['error']['message'] ?? 'Meta API error',
            'data' => $data
        ];
    }
    
    return ['success' => true, 'data' => $data];
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return strpos($haystack, $needle) === 0;
    }
}
$metaBootstrap = fetchMetaTemplatesResponse();
$preloadedTemplates = $metaBootstrap['success'] ? ($metaBootstrap['data'] ?? []) : [];
$metaError = $metaBootstrap['success'] ? null : ($metaBootstrap['message'] ?? 'Could not connect to Meta APIs');

if ($metaBootstrap['success']) {
    $stats = calculateTemplateStats($preloadedTemplates);
} else {
    $stats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM whatsapp_templates WHERE is_active = 1")->fetchColumn(),
        'approved' => $pdo->query("SELECT COUNT(*) FROM whatsapp_templates WHERE is_active = 1 AND status = 'approved'")->fetchColumn(),
        'pending' => $pdo->query("SELECT COUNT(*) FROM whatsapp_templates WHERE is_active = 1 AND status = 'pending'")->fetchColumn(),
        'rejected' => $pdo->query("SELECT COUNT(*) FROM whatsapp_templates WHERE is_active = 1 AND status = 'rejected'")->fetchColumn()
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Templates - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/global.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
    <link href="css/admin.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
    <link href="css/templates.css" rel="stylesheet">
    <script>
        window.__metaTemplates = <?php echo json_encode($preloadedTemplates, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        window.__metaError = <?php echo json_encode($metaError, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    </script>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Header -->
                <div class="templates-header">
                    <h1><i class="fab fa-whatsapp me-2"></i>WhatsApp Templates Manager</h1>
                    <p>Create, manage, and organize your WhatsApp message templates</p>
                    
                    <div class="templates-stats">
                        <div class="stat-card">
                            <span class="stat-number"><?= $stats['total'] ?></span>
                            <span class="stat-label">Total Templates</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-number"><?= $stats['approved'] ?></span>
                            <span class="stat-label">Approved</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-number"><?= $stats['pending'] ?></span>
                            <span class="stat-label">Pending</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-number"><?= $stats['rejected'] ?></span>
                            <span class="stat-label">Rejected</span>
                        </div>
                    </div>
                </div>
                
                <?php if ($metaError): ?>
                    <div class="alert alert-warning mt-3">
                        <strong>Meta Sync Warning:</strong> <?= htmlspecialchars($metaError) ?>. Showing cached templates instead.
                    </div>
                <?php endif; ?>

                <!-- Toolbar -->
                <div class="templates-toolbar">
                    <div class="search-filter-group">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" placeholder="Search templates by name or content...">
                        </div>
                        <div class="filter-dropdown">
                            <select id="statusFilter">
                                <option value="">All Status</option>
                                <option value="approved">Approved</option>
                                <option value="pending">Pending</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="filter-dropdown">
                            <select id="categoryFilter">
                                <option value="">All Categories</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Utility">Utility</option>
                                <option value="Authentication">Authentication</option>
                            </select>
                        </div>
                    </div>
                    <a class="btn-create-template" href="whatsapp_template_builder.php">
                        <i class="fas fa-plus"></i>
                        <span>Create Template</span>
                    </a>
                </div>

                <!-- Templates Grid -->
                <div class="templates-grid" id="templatesGrid">
                    <!-- Loading state -->
                    <div class="loading-state">
                        <div class="spinner"></div>
                        <p>Loading templates...</p>
                    </div>
                </div>

                <!-- Empty State -->
                <div class="empty-state" id="emptyState" style="display:none;">
                    <i class="fas fa-file-alt"></i>
                    <h3>No Templates Found</h3>
                    <p>Create your first WhatsApp template to get started</p>
                    <a class="btn-create-template" href="whatsapp_template_builder.php">
                        <i class="fas fa-plus"></i>
                        <span>Create Your First Template</span>
                    </a>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/templates-list.js?v=<?= filemtime(__DIR__ . '/js/templates-list.js'); ?>"></script>
</body>
</html>
