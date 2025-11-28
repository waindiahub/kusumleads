<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/whatsapp_cloud.php';
require_once '../includes/r2_client.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();

if ($action === 'list_conversations') {
    try {
        $sql = "SELECT * FROM whatsapp_conversations ORDER BY last_message_at DESC";
        $q = $db->query($sql);
        $data = $q->fetchAll();
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
if ($action === 'upload_media') {
    if (!r2IsConfigured()) {
        echo json_encode(['success' => false, 'message' => 'Cloudflare R2 is not configured. Add credentials in Settings > Storage.']);
        exit;
    }
    if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Upload failed. Please choose a file.']);
        exit;
    }
    $file = $_FILES['file'];
    if ($file['size'] > 15 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File exceeds 15MB WhatsApp limit.']);
        exit;
    }
    $tmp = $file['tmp_name'];
    $contents = file_get_contents($tmp);
    if ($contents === false) {
        echo json_encode(['success' => false, 'message' => 'Unable to read uploaded file.']);
        exit;
    }
    $mime = mime_content_type($tmp) ?: ($file['type'] ?? 'application/octet-stream');
    $ext = r2GuessExtension($mime);
    $mediaType = r2DetectMediaType($mime);
    $key = sprintf('whatsapp/uploads/%s/%s.%s', date('Y/m/d'), bin2hex(random_bytes(8)), $ext);
    try {
        $upload = r2Upload($key, $contents, $mime);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    echo json_encode([
        'success' => true,
        'data' => [
            'url' => $upload['url'],
            'key' => $upload['key'],
            'media_type' => $mediaType,
            'mime_type' => $mime,
            'filename' => $file['name'] ?: basename($upload['key'])
        ]
    ]);
    exit;
}
if ($action === 'get_messages') {
    $id = (int)($_GET['conversation_id'] ?? 0);
    $st = $db->prepare('SELECT * FROM whatsapp_messages WHERE conversation_id = ? ORDER BY id ASC');
    $st->execute([$id]);
    echo json_encode(['success' => true, 'data' => $st->fetchAll()]);
    exit;
}
if ($action === 'intervene') {
    $id = (int)($_POST['conversation_id'] ?? 0);
    if ($id) {
        $db->prepare('UPDATE whatsapp_conversations SET intervened = 1, intervened_by = ?, intervened_at = NOW() WHERE id = ?')->execute([$_SESSION['user']['id'], $id]);
        echo json_encode(['success' => true]);
        exit;
    }
}
if ($action === 'resolve') {
    $id = (int)($_POST['conversation_id'] ?? 0);
    if ($id) {
        $db->prepare('UPDATE whatsapp_conversations SET intervened = 0 WHERE id = ?')->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }
}
if ($action === 'update_lead_attr') {
    $leadId = (int)($_POST['lead_id'] ?? 0);
    $email = $_POST['email'] ?? null;
    $city = $_POST['city'] ?? null;
    if ($leadId) {
        $st = $db->prepare('UPDATE leads SET email = COALESCE(?, email), city = COALESCE(?, city) WHERE id = ?');
        $ok = $st->execute([$email, $city, $leadId]);
        echo json_encode(['success' => (bool)$ok]);
        exit;
    }
}
if ($action === 'get_journey') {
    $id = (int)($_GET['conversation_id'] ?? 0);
    $journey = [];
    $conv = $db->prepare('SELECT lead_id FROM whatsapp_conversations WHERE id = ?');
    $conv->execute([$id]);
    $row = $conv->fetch();
    $leadId = $row ? (int)$row['lead_id'] : 0;
    $st = $db->prepare('SELECT * FROM whatsapp_assignment_history WHERE conversation_id = ? ORDER BY changed_at DESC');
    $st->execute([$id]);
    foreach ($st->fetchAll() as $a) { $journey[] = ['type'=>'assignment','at'=>$a['changed_at'],'meta'=>$a]; }
    $st = $db->prepare('SELECT tag, created_at FROM whatsapp_conversation_tags WHERE conversation_id = ? ORDER BY id DESC');
    $st->execute([$id]);
    foreach ($st->fetchAll() as $t) { $journey[] = ['type'=>'tag','at'=>$t['created_at'],'meta'=>$t]; }
    if ($leadId) {
        $st = $db->prepare('SELECT status, created_at FROM whatsapp_campaign_recipients WHERE lead_id = ? ORDER BY id DESC');
        $st->execute([$leadId]);
        foreach ($st->fetchAll() as $c) { $journey[] = ['type'=>'campaign','at'=>$c['created_at'],'meta'=>$c]; }
    }
    usort($journey, function($a,$b){ return strcmp($b['at']??'', $a['at']??''); });
    echo json_encode(['success'=>true,'data'=>$journey]);
    exit;
}
if ($action === 'list_notes' || $action === 'get_notes') {
    $id = (int)($_GET['conversation_id'] ?? 0);
    $st = $db->prepare('SELECT wn.*, u.name AS author_name FROM whatsapp_notes wn JOIN users u ON wn.author_user_id = u.id WHERE conversation_id = ? ORDER BY id DESC');
    $st->execute([$id]);
    echo json_encode(['success' => true, 'data' => $st->fetchAll()]);
    exit;
}
if ($action === 'add_note') {
    $conversationId = (int)($_POST['conversation_id'] ?? 0);
    $noteText = $_POST['note_text'] ?? '';
    $isPrivate = isset($_POST['is_private']) ? (int)$_POST['is_private'] : 1;
    $st = $db->prepare('INSERT INTO whatsapp_notes (conversation_id, author_user_id, note_text, is_private) VALUES (?, ?, ?, ?)');
    $ok = $st->execute([$conversationId, $_SESSION['user']['id'], $noteText, $isPrivate]);
    echo json_encode(['success' => (bool)$ok]);
    exit;
}
if ($action === 'send_text') {
    $to = $_POST['to'] ?? '';
    $text = $_POST['text'] ?? '';
    if (!$to || !trim($text)) {
        echo json_encode(['success' => false, 'message' => 'Recipient and message are required.']);
        exit;
    }
    if (!isWithinCustomerServiceWindow($db, $to)) {
        echo json_encode(['success' => false, 'message' => '24-hour window closed. Please send a template.']);
        exit;
    }
    $res = whatsappSendText($to, $text);
    $convId = upsertConversationByPhone($db, $to);
    $waId = $res['data']['messages'][0]['id'] ?? null;
    insertMessage($db, $convId, 'outgoing', 'text', $text, $waId, null, $to, $res['success'] ? 'sent' : 'failed', json_encode($res['data'] ?? []), null);
    notifyConversationUpdate($db, $convId, 'outgoing', $res['data'] ?? []);
    echo json_encode(['success' => $res['success'], 'data' => $res['data']]);
    exit;
}
if ($action === 'send_template') {
    $to = trim($_POST['to'] ?? '');
    if (!$to) {
        echo json_encode(['success' => false, 'message' => 'Recipient is required.']);
        exit;
    }
    $name = $_POST['template_name'] ?? '';
    $lang = $_POST['language_code'] ?? 'en_US';
    $components = isset($_POST['components']) ? json_decode($_POST['components'], true) : [];
    $variables = isset($_POST['variables']) ? json_decode($_POST['variables'], true) : [];
    $templateId = (int)($_POST['template_id'] ?? 0);
    $renderedBody = $name;

    if ($templateId) {
        $tplStmt = $db->prepare('SELECT * FROM whatsapp_templates WHERE id = ?');
        $tplStmt->execute([$templateId]);
        $row = $tplStmt->fetch();
        if ($row) {
            $name = $row['name'];
            $lang = $row['language'] ?? $lang;
            $components = buildTemplateComponentsFromRow($row, $variables ?? []);
            $renderedBody = interpolateVars($row['message'], $variables ?? []);
        }
    }

    if (!$components) {
        echo json_encode(['success' => false, 'message' => 'Template components missing.']);
        exit;
    }

    $res = whatsappSendTemplate($to, $name, $lang, $components);
    $convId = upsertConversationByPhone($db, $to);
    $waId = $res['data']['messages'][0]['id'] ?? null;
    insertMessage(
        $db,
        $convId,
        'outgoing',
        'template',
        $renderedBody ?: $name,
        $waId,
        null,
        $to,
        $res['success'] ? 'sent' : 'failed',
        json_encode($res['data'] ?? []),
        null
    );
    notifyConversationUpdate($db, $convId, 'outgoing', $res['data'] ?? []);
    echo json_encode(['success' => $res['success'], 'data' => $res['data']]);
    exit;
}
if ($action === 'send_media') {
    $to = $_POST['to'] ?? '';
    $type = $_POST['type'] ?? 'image';
    $url = $_POST['media_url'] ?? '';
    $caption = $_POST['caption'] ?? null;
    $filename = $_POST['filename'] ?? null;
    $allowedTypes = ['image','video','audio','document','sticker'];
    if (!$to || !$url || !in_array($type, $allowedTypes, true)) {
        echo json_encode(['success' => false, 'message' => 'Media type, recipient, or URL missing.']);
        exit;
    }
    if (!isWithinCustomerServiceWindow($db, $to)) {
        echo json_encode(['success' => false, 'message' => '24-hour window closed. Please send a template.']);
        exit;
    }
    $res = whatsappSendMedia($to, $type, $url, $caption, $filename);
    $convId = upsertConversationByPhone($db, $to);
    $waId = $res['data']['messages'][0]['id'] ?? null;
    insertMessage($db, $convId, 'outgoing', $type, $caption ?? $type, $waId, null, $to, $res['success'] ? 'sent' : 'failed', json_encode($res['data'] ?? []), $url);
    notifyConversationUpdate($db, $convId, 'outgoing', $res['data'] ?? []);
    echo json_encode(['success' => $res['success'], 'data' => $res['data']]);
    exit;
}
if ($action === 'update_status') {
    $leadId = (int)($_POST['lead_id'] ?? 0);
    $status = $_POST['status'] ?? 'assigned';
    if ($leadId) {
        $db->prepare('UPDATE lead_assignments SET status = ? WHERE lead_id = ?')->execute([$status, $leadId]);
        echo json_encode(['success' => true]);
        exit;
    }
}
if ($action === 'update_contact') {
    $id = (int)($_POST['conversation_id'] ?? 0);
    $name = $_POST['contact_name'] ?? null;
    if ($id) { $db->prepare('UPDATE whatsapp_conversations SET contact_name = ? WHERE id = ?')->execute([$name, $id]); echo json_encode(['success'=>true]); exit; }
}
if ($action === 'add_tag') {
    $id = (int)($_POST['conversation_id'] ?? 0);
    $tag = $_POST['tag'] ?? '';
    if ($id && $tag) { $db->prepare('INSERT INTO whatsapp_conversation_tags (conversation_id, tag) VALUES (?, ?)')->execute([$id, $tag]); echo json_encode(['success'=>true]); exit; }
}
if ($action === 'remove_tag') {
    $id = (int)($_POST['conversation_id'] ?? 0);
    $tag = $_POST['tag'] ?? '';
    if ($id && $tag) { $db->prepare('DELETE FROM whatsapp_conversation_tags WHERE conversation_id = ? AND tag = ?')->execute([$id, $tag]); echo json_encode(['success'=>true]); exit; }
}
if ($action === 'get_tags') {
    $id = (int)($_GET['conversation_id'] ?? 0);
    $st = $db->prepare('SELECT tag FROM whatsapp_conversation_tags WHERE conversation_id = ? ORDER BY id ASC');
    $st->execute([$id]); echo json_encode(['success'=>true,'data'=>$st->fetchAll()]); exit;
}
// Legacy aliases handled above for get_notes.
if ($action === 'list_agents') {
    $st = $db->query("SELECT u.id, u.name FROM users u WHERE u.role='agent' ORDER BY u.name");
    echo json_encode(['success'=>true,'data'=>$st->fetchAll()]); exit;
}
if ($action === 'reassign_agent') {
    $id = (int)($_POST['conversation_id'] ?? 0);
    $agent = (int)($_POST['agent_id'] ?? 0);
    if ($id && $agent) {
        $prev = $db->prepare('SELECT assigned_agent_id FROM whatsapp_conversations WHERE id=?'); $prev->execute([$id]); $row=$prev->fetch();
        $db->prepare('UPDATE whatsapp_conversations SET assigned_agent_id = ? WHERE id = ?')->execute([$agent, $id]);
        $db->prepare('INSERT INTO whatsapp_assignment_history (conversation_id, previous_agent_id, new_agent_id, changed_by_user_id) VALUES (?, ?, ?, ?)')->execute([$id, $row['assigned_agent_id'] ?? null, $agent, $_SESSION['user']['id']]);
        echo json_encode(['success'=>true]); exit;
    }
}
if ($action === 'mark_read') {
    $id = (int)($_POST['conversation_id'] ?? 0);
    if ($id) { $db->prepare('UPDATE whatsapp_conversations SET unread_count = 0 WHERE id=?')->execute([$id]); echo json_encode(['success'=>true]); exit; }
}

http_response_code(400);
echo json_encode(['success' => false]);
?>
