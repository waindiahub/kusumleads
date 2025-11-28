<?php
require_once 'config.php';
require_once 'jwt_helper.php';
require_once 'whatsapp_cloud.php';
require_once 'pusher.php';
require_once 'campaigns.php';
require_once 'r2_client.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($method === 'GET' && strpos($path, '/whatsapp/webhook') !== false) {
    verifyMetaWebhook();
} elseif ($method === 'POST' && strpos($path, '/whatsapp/webhook') !== false) {
    handleMetaWebhook();
} elseif ($method === 'POST' && strpos($path, '/whatsapp/send') !== false) {
    whatsappSendEndpoint();
} elseif ($method === 'GET' && strpos($path, '/whatsapp/session/check') !== false) {
    whatsappSessionCheckEndpoint();
} elseif ($method === 'GET' && strpos($path, '/whatsapp/media/download') !== false) {
    whatsappMediaDownloadEndpoint();
} elseif (strpos($path, '/whatsapp/campaigns') !== false) {
    handleCampaignRoutes($method, $path);
} elseif (strpos($path, '/whatsapp/flows') !== false) {
    handleFlowRoutes($method, $path);
} elseif ($method === 'POST' && strpos($path, '/r2/presign-put') !== false) {
    r2PresignPutEndpoint();
} elseif ($method === 'GET' && strpos($path, '/r2/presign-get') !== false) {
    r2PresignGetEndpoint();
} elseif ($method === 'GET' && strpos($path, '/r2/public-url') !== false) {
    r2PublicUrlEndpoint();
} elseif ($method === 'GET' && strpos($path, '/whatsapp/templates') !== false) {
    if (strpos($path, '/whatsapp/templates/sync') !== false) {
        syncWhatsAppTemplateCategories();
    } else {
        listWhatsAppTemplates();
    }
} elseif ($method === 'GET' && strpos($path, '/whatsapp/conversations/') !== false) {
    if (preg_match('/\/whatsapp\/conversations\/(\d+)\/messages/', $path, $m)) {
        getConversationMessages((int)$m[1]);
    } else {
        sendResponse(false, 'Invalid endpoint');
    }
} elseif ($method === 'GET' && strpos($path, '/whatsapp/conversations') !== false) {
    if (isset($_GET['lead_id'])) {
        $db = getDB();
        $st = $db->prepare('SELECT * FROM whatsapp_conversations WHERE lead_id = ? ORDER BY last_message_at DESC');
        $st->execute([(int)$_GET['lead_id']]);
        sendResponse(true, 'OK', $st->fetchAll());
    } else {
        getConversations();
    }
} elseif ($method === 'POST' && preg_match('/\/whatsapp\/conversations\/(\d+)\/messages\/(\w+)\/read$/', $path, $m)) {
    markMessageAsRead((int)$m[1], $m[2]);
} elseif ($method === 'POST' && strpos($path, '/whatsapp/followups/process') !== false) {
    $token = validateJWT();
    if (!$token || $token['role'] !== 'admin') sendResponse(false, 'Admin required');
    require_once 'followups.php';
    $db = getDB();
    scheduleAbandonedFollowUps($db);
    $res = processFollowUps($db);
    sendResponse(true, 'Processed', $res);
} else {
    sendResponse(false, 'Invalid endpoint');
}

function getConversations() {
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $db = getDB();
    if ($token['role'] === 'admin') {
        $stmt = $db->query('SELECT * FROM whatsapp_conversations ORDER BY last_message_at DESC');
        sendResponse(true, 'OK', $stmt->fetchAll());
    } else {
        $stmt = $db->prepare('SELECT * FROM whatsapp_conversations WHERE assigned_agent_id = ? ORDER BY last_message_at DESC');
        $stmt->execute([$token['user_id']]);
        sendResponse(true, 'OK', $stmt->fetchAll());
    }
}

function getConversationMessages($conversationId) {
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $db = getDB();
    if ($token['role'] !== 'admin') {
        $stmt = $db->prepare('SELECT assigned_agent_id FROM whatsapp_conversations WHERE id = ?');
        $stmt->execute([$conversationId]);
        $row = $stmt->fetch();
        if (!$row || (int)$row['assigned_agent_id'] !== (int)$token['user_id']) sendResponse(false, 'Access denied');
    }
    $stmt = $db->prepare('SELECT * FROM whatsapp_messages WHERE conversation_id = ? ORDER BY id ASC');
    $stmt->execute([$conversationId]);
    sendResponse(true, 'OK', $stmt->fetchAll());
}

function whatsappSendEndpoint() {
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $input = json_decode(file_get_contents('php://input'), true);
    $to = $input['to'] ?? null;
    $type = $input['type'] ?? 'text';
    $pnId = $input['phone_number_id'] ?? null;
    if (!$to) sendResponse(false, 'Recipient required');
    $db = getDB();
    if ($token['role'] === 'agent') {
        $q = $db->prepare('SELECT la.agent_id FROM leads l JOIN lead_assignments la ON la.lead_id = l.id WHERE l.phone_number = ? ORDER BY la.assigned_at DESC LIMIT 1');
        $q->execute([$to]);
        $r = $q->fetch();
        if (!$r || (int)$r['agent_id'] !== (int)$token['user_id']) sendResponse(false, 'Access denied');
    }
    if ($type !== 'template') {
        if (!isWithinCustomerServiceWindow($db, $to)) sendResponse(false, '24-hour window closed. Use template');
    }
    $result = null;
    if ($type === 'template') {
        $template = $input['template_name'] ?? null;
        $language = $input['language_code'] ?? 'en_US';
        $components = $input['components'] ?? [];
        if (isset($input['template_id'])) {
            $stmt = $db->prepare('SELECT * FROM whatsapp_templates WHERE id = ?');
            $stmt->execute([(int)$input['template_id']]);
            $row = $stmt->fetch();
            if ($row) {
                $template = $row['name'];
                $language = $row['language'] ?? $language;
                $components = buildTemplateComponentsFromRow($row, $input['variables'] ?? []);
            }
        }
        if (!$template) sendResponse(false, 'Template name required');
        $result = whatsappSendTemplate($to, $template, $language, $components, $pnId);
    } elseif ($type === 'text') {
        $text = $input['text'] ?? null;
        if (!$text) sendResponse(false, 'Text required');
        $result = whatsappSendText($to, $text, $pnId);
    } else {
        $mediaUrl = $input['media_url'] ?? null;
        $caption = $input['caption'] ?? null;
        $filename = $input['filename'] ?? null;
        if (!$mediaUrl) sendResponse(false, 'Media URL required');
        if ($type === 'interactive') {
            $payload = $input['interactive'] ?? null;
            if (!$payload) sendResponse(false, 'Interactive payload required');
            $result = whatsappPost('https://graph.facebook.com/' . whatsappGraphVersion() . '/' . ($pnId ?: whatsappPhoneNumberId()) . '/messages', [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'interactive',
                'interactive' => $payload
            ]);
        } else {
            $result = whatsappSendMedia($to, $type, $mediaUrl, $caption, $filename, $pnId);
        }
    }
    $convId = upsertConversationByPhone($db, $to);
    $waId = $result['data']['messages'][0]['id'] ?? null;
    $body = $type === 'text' ? ($input['text'] ?? '') : ($type === 'template' ? ($input['template_name'] ?? '') : ($input['caption'] ?? $type));
    insertMessage($db, $convId, 'outgoing', $type, $body, $waId, null, $to, $result['success'] ? 'sent' : 'failed', json_encode($result['data'] ?? []), null);
    notifyConversationUpdate($db, $convId, 'outgoing', $result['data'] ?? []);
    sendResponse($result['success'], $result['success'] ? 'Message sent' : 'Send failed', $result['data']);
}

function whatsappSessionCheckEndpoint() {
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $phone = $_GET['phone'] ?? null;
    if (!$phone) sendResponse(false, 'Phone required');
    $db = getDB();
    $open = isWithinCustomerServiceWindow($db, $phone);
    sendResponse(true, 'OK', ['session_open' => $open]);
}

function whatsappMediaDownloadEndpoint() {
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $mediaId = $_GET['media_id'] ?? null;
    if (!$mediaId) sendResponse(false, 'media_id required');
    if (!r2IsConfigured()) sendResponse(false, 'Cloudflare R2 not configured');
    $download = whatsappDownloadMedia($mediaId);
    if (!$download['success']) sendResponse(false, $download['message'] ?? 'Download failed');
    $ext = r2GuessExtension($download['mime_type']);
    $key = sprintf('whatsapp/inbox/%s/%s.%s', date('Y/m/d'), $mediaId, $ext);
    try {
        $uploaded = r2Upload($key, $download['body'], $download['mime_type']);
    } catch (Exception $e) {
        sendResponse(false, $e->getMessage());
    }
    sendResponse(true, 'Saved', ['key' => $uploaded['key'], 'url' => $uploaded['url'], 'mime_type' => $download['mime_type']]);
}

function listWhatsAppTemplates() {
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    try {
        $db = getDB();
        $stmt = $db->query("SELECT id, name, message, media_type, media_url, buttons, category, language, header_text, header_media_type, header_media_url, footer_text, placeholders, status FROM whatsapp_templates WHERE is_active = 1 ORDER BY name");
        $rows = $stmt->fetchAll();
        sendResponse(true, 'OK', $rows);
    } catch (Exception $e) {
        sendResponse(false, 'Failed to fetch templates');
    }
}

function syncWhatsAppTemplateCategories() {
    $token = validateJWT();
    if (!$token || $token['role'] !== 'admin') sendResponse(false, 'Admin required');
    $db = getDB();
    $res = whatsappSyncTemplatesCategories($db);
    sendResponse($res['success'], $res['message'] ?? 'OK', $res['data'] ?? []);
}

function handleCampaignRoutes($method, $path) {
    $token = validateJWT();
    if (!$token || $token['role'] !== 'admin') sendResponse(false, 'Admin required');
    $db = getDB();
    if ($method === 'POST' && preg_match('/\/whatsapp\/campaigns$/', $path)) {
        $input = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare('INSERT INTO whatsapp_campaigns (name, template_name, language_code, filters_json, scheduled_at, status) VALUES (?, ?, ?, ?, ?, "draft")');
        $stmt->execute([
            $input['name'] ?? 'Campaign',
            $input['template_name'] ?? null,
            $input['language_code'] ?? 'en_US',
            json_encode($input['filters'] ?? []),
            $input['scheduled_at'] ?? null
        ]);
        sendResponse(true, 'Campaign created', ['id' => $db->lastInsertId()]);
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/campaigns\/(\d+)\/launch$/', $path, $m)) {
        $cid = (int)$m[1];
        launchCampaign($db, $cid);
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/campaigns\/(\d+)\/start$/', $path, $m)) {
        $cid = (int)$m[1];
        $res = dispatchCampaign($db, $cid);
        sendResponse($res['success'], $res['message'] ?? 'OK', $res['data'] ?? []);
    } elseif ($method === 'GET' && preg_match('/\/whatsapp\/campaigns\/(\d+)\/recipients$/', $path, $m)) {
        $cid = (int)$m[1];
        $st = $db->prepare('SELECT * FROM whatsapp_campaign_recipients WHERE campaign_id = ? ORDER BY id DESC');
        $st->execute([$cid]);
        sendResponse(true, 'OK', $st->fetchAll());
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/campaigns\/(\d+)\/recipients\/retry_failed$/', $path, $m)) {
        $cid = (int)$m[1];
        $res = retryFailedRecipients($db, $cid);
        sendResponse(true, 'Queued', $res);
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/campaigns\/(\d+)\/pause$/', $path, $m)) {
        $cid = (int)$m[1];
        $db->prepare('UPDATE whatsapp_campaigns SET status = "paused" WHERE id = ?')->execute([$cid]);
        sendResponse(true, 'Paused', ['id' => $cid]);
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/campaigns\/(\d+)\/resume$/', $path, $m)) {
        $cid = (int)$m[1];
        $db->prepare('UPDATE whatsapp_campaigns SET status = "scheduled" WHERE id = ?')->execute([$cid]);
        $res = dispatchCampaign($db, $cid);
        sendResponse($res['success'], $res['message'] ?? 'OK', $res['data'] ?? []);
    } else {
        sendResponse(false, 'Invalid endpoint');
    }
}

function launchCampaign($db, $campaignId) {
    $result = queueCampaignRecipients($db, $campaignId);
    sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
}

function r2PresignPutEndpoint() {
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $input = json_decode(file_get_contents('php://input'), true);
    $contentType = $input['content_type'] ?? 'application/octet-stream';
    $suggested = trim((string)($input['suggested_name'] ?? 'upload.bin'));
    if (!r2IsConfigured()) sendResponse(false, 'Cloudflare R2 not configured');
    $ext = r2GuessExtension($contentType);
    $key = sprintf('whatsapp/uploads/%s/%s', date('Y/m/d'), preg_replace('/[^a-zA-Z0-9._-]/', '', $suggested));
    if (!str_contains($key, '.')) { $key .= '.' . $ext; }
    try {
        $presigned = r2PresignPut($key, $contentType, 900);
    } catch (Exception $e) {
        sendResponse(false, $e->getMessage());
    }
    sendResponse(true, 'OK', $presigned);
}

function r2PresignGetEndpoint() {
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $key = $_GET['key'] ?? '';
    if (!$key) sendResponse(false, 'key required');
    if (!r2IsConfigured()) sendResponse(false, 'Cloudflare R2 not configured');
    try {
        $url = r2PresignGet($key, 900);
    } catch (Exception $e) {
        sendResponse(false, $e->getMessage());
    }
    sendResponse(true, 'OK', ['url' => $url]);
}

function r2PublicUrlEndpoint() {
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $key = $_GET['key'] ?? '';
    if (!$key) sendResponse(false, 'key required');
    $url = r2PublicUrl($key);
    if (!$url) sendResponse(false, 'Public URL unavailable');
    sendResponse(true, 'OK', ['url' => $url]);
}

function handleFlowRoutes($method, $path) {
    $token = validateJWT();
    if (!$token || $token['role'] !== 'admin') sendResponse(false, 'Admin required');
    $db = getDB();
    if ($method === 'GET' && preg_match('/\/whatsapp\/flows$/', $path)) {
        $st = $db->query('SELECT id, name, active, created_at FROM whatsapp_flows ORDER BY created_at DESC');
        sendResponse(true, 'OK', $st->fetchAll());
    } elseif ($method === 'GET' && preg_match('/\/whatsapp\/flows\/(\d+)$/', $path, $m)) {
        $id = (int)$m[1];
        $st = $db->prepare('SELECT * FROM whatsapp_flows WHERE id = ?');
        $st->execute([$id]);
        sendResponse(true, 'OK', $st->fetch());
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/flows$/', $path)) {
        $input = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare('INSERT INTO whatsapp_flows (name, definition_json, active) VALUES (?, ?, ?)');
        $stmt->execute([$input['name'] ?? 'Flow', json_encode($input['definition'] ?? []), (int)($input['active'] ?? 1)]);
        sendResponse(true, 'Flow created', ['id' => $db->lastInsertId()]);
    } elseif ($method === 'PUT' && preg_match('/\/whatsapp\/flows\/(\d+)$/', $path, $m)) {
        $id = (int)$m[1];
        $input = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare('UPDATE whatsapp_flows SET name = ?, definition_json = ?, active = ? WHERE id = ?');
        $stmt->execute([$input['name'] ?? 'Flow', json_encode($input['definition'] ?? []), (int)($input['active'] ?? 1), $id]);
        sendResponse(true, 'Flow updated', ['id' => $id]);
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/flows\/process_delays$/', $path)) {
        require_once 'flows.php';
        $res = processFlowDelays($db);
        sendResponse(true, 'Processed', $res);
    } else {
        sendResponse(false, 'Invalid endpoint');
    }
}

function markMessageAsRead($conversationId, $messageId) {
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    require_once 'whatsapp_advanced.php';
    $db = getDB();
    if ($token['role'] !== 'admin') {
        $stmt = $db->prepare('SELECT assigned_agent_id FROM whatsapp_conversations WHERE id = ?');
        $stmt->execute([$conversationId]);
        $row = $stmt->fetch();
        if (!$row || (int)$row['assigned_agent_id'] !== (int)$token['user_id']) {
            sendResponse(false, 'Access denied');
        }
    }
    whatsappMarkMessageAsRead($db, $messageId, $conversationId);
    sendResponse(true, 'Message marked as read');
}

?>
