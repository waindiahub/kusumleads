<?php
require_once 'config.php';
require_once 'r2_client.php';

function whatsappGraphVersion() {
    $v = getSetting('meta_graph_version');
    return $v ? $v : 'v21.0';
}

function whatsappToken() {
    return getSetting('whatsapp_token');
}

function whatsappPhoneNumberId() {
    return getSetting('whatsapp_phone_number_id');
}

function metaAppSecret() {
    return getSetting('meta_app_secret');
}

function metaVerifyToken() {
    return getSetting('meta_verify_token');
}

function whatsappSendText($to, $text, $phoneNumberIdOverride = null, $contextMessageId = null) {
    $token = whatsappToken();
    $pnId = $phoneNumberIdOverride ?: whatsappPhoneNumberId();
    if (!$token || !$pnId) return ['success' => false, 'message' => 'WhatsApp not configured'];
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $pnId . '/messages';
    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $to,
        'type' => 'text',
        'text' => ['body' => $text]
    ];
    
    // Add context for contextual reply
    if ($contextMessageId) {
        $payload['context'] = ['message_id' => $contextMessageId];
    }
    
    return whatsappPost($url, $payload);
}

function whatsappSendTemplate($to, $templateName, $languageCode, $components = [], $phoneNumberIdOverride = null) {
    $token = whatsappToken();
    $pnId = $phoneNumberIdOverride ?: whatsappPhoneNumberId();
    if (!$token || !$pnId) return ['success' => false, 'message' => 'WhatsApp not configured'];
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $pnId . '/messages';
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $to,
        'type' => 'template',
        'template' => [
            'name' => $templateName,
            'language' => ['code' => $languageCode],
            'components' => $components
        ]
    ];
    return whatsappPost($url, $payload);
}

function buildTemplateComponentsFromRow($row, $vars = []) {
    $components = [];
    if (!empty($row['header_text'])) {
        $components[] = ['type' => 'header', 'parameters' => [['type' => 'text', 'text' => interpolateVars($row['header_text'], $vars)]]];
    } elseif (!empty($row['header_media_type']) && $row['header_media_type'] !== 'none' && !empty($row['header_media_url'])) {
        $components[] = ['type' => 'header', 'parameters' => [['type' => $row['header_media_type'], $row['header_media_type'] => ['link' => interpolateVars($row['header_media_url'], $vars)]]]];
    }
    if (!empty($row['message'])) {
        $components[] = ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => interpolateVars($row['message'], $vars)]]];
    }
    if (!empty($row['footer_text'])) {
        $components[] = ['type' => 'footer', 'parameters' => [['type' => 'text', 'text' => interpolateVars($row['footer_text'], $vars)]]];
    }
    $buttonsJson = $row['buttons'] ?? null;
    if ($buttonsJson) {
        $buttons = is_array($buttonsJson) ? $buttonsJson : json_decode($buttonsJson, true);
        $buttonParams = [];
        foreach ($buttons as $idx => $btn) {
            $type = $btn['type'] ?? 'reply';
            $title = $btn['title'] ?? 'Button';
            $value = interpolateVars($btn['value'] ?? '', $vars);
            if ($type === 'url') {
                $buttonParams[] = ['type' => 'button', 'sub_type' => 'url', 'index' => (string)$idx, 'parameters' => [['type' => 'text', 'text' => $value]]];
            } elseif ($type === 'call') {
                $buttonParams[] = ['type' => 'button', 'sub_type' => 'url', 'index' => (string)$idx, 'parameters' => [['type' => 'text', 'text' => 'tel:' . preg_replace('/[^0-9+]/', '', $value)]]];
            } elseif ($type === 'deeplink') {
                $buttonParams[] = ['type' => 'button', 'sub_type' => 'url', 'index' => (string)$idx, 'parameters' => [['type' => 'text', 'text' => $value]]];
            } elseif ($type === 'copy_code') {
                $buttonParams[] = ['type' => 'button', 'sub_type' => 'quick_reply', 'index' => (string)$idx, 'parameters' => [['type' => 'text', 'text' => $value]]];
            } else {
                $buttonParams[] = ['type' => 'button', 'sub_type' => 'quick_reply', 'index' => (string)$idx, 'parameters' => [['type' => 'text', 'text' => $title]]];
            }
        }
        if ($buttonParams) $components[] = ['type' => 'button', 'parameters' => $buttonParams];
    }
    return $components;
}

function interpolateVars($text, $vars) {
    if (!$text) return $text;
    foreach ($vars as $k => $v) {
        $text = str_replace('{{' . $k . '}}', $v, $text);
    }
    return $text;
}

function whatsappSyncTemplatesCategories($db) {
    $wabaId = getSetting('whatsapp_business_account_id');
    $token = whatsappToken();
    if (!$wabaId || !$token) return ['success' => false, 'message' => 'WABA not configured'];
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $wabaId . '/message_templates?limit=100';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($resp, true);
    if ($code < 200 || $code >= 300 || !isset($data['data'])) return ['success' => false, 'message' => 'Sync failed'];
    $updated = 0;
    foreach ($data['data'] as $tpl) {
        $name = $tpl['name'] ?? null;
        $category = $tpl['category'] ?? null;
        $status = $tpl['status'] ?? null;
        if ($name) {
            $stmt = $db->prepare('UPDATE whatsapp_templates SET category = ?, status = ? WHERE name = ?');
            $stmt->execute([$category, $status, $name]);
            $updated += $stmt->rowCount();
        }
    }
    return ['success' => true, 'message' => 'Synced', 'data' => ['updated' => $updated]];
}

function whatsappSendMedia($to, $type, $mediaUrl, $caption = null, $filename = null, $phoneNumberIdOverride = null) {
    $token = whatsappToken();
    $pnId = $phoneNumberIdOverride ?: whatsappPhoneNumberId();
    if (!$token || !$pnId) return ['success' => false, 'message' => 'WhatsApp not configured'];
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $pnId . '/messages';
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $to,
        'type' => $type,
        $type => array_filter(['link' => $mediaUrl, 'caption' => $caption, 'filename' => $filename])
    ];
    return whatsappPost($url, $payload);
}

function whatsappDownloadMedia($mediaId) {
    $token = whatsappToken();
    if (!$token || !$mediaId) {
        return ['success' => false, 'message' => 'Missing token or media id'];
    }
    $metaUrl = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $mediaId;
    $metaCh = curl_init($metaUrl);
    curl_setopt($metaCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($metaCh, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $metaResp = curl_exec($metaCh);
    $metaCode = curl_getinfo($metaCh, CURLINFO_HTTP_CODE);
    curl_close($metaCh);
    $meta = json_decode($metaResp, true);
    if ($metaCode < 200 || $metaCode >= 300 || empty($meta['url'])) {
        return ['success' => false, 'message' => 'Unable to fetch media metadata'];
    }
    $downloadCh = curl_init($meta['url']);
    curl_setopt($downloadCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($downloadCh, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $binary = curl_exec($downloadCh);
    $downloadCode = curl_getinfo($downloadCh, CURLINFO_HTTP_CODE);
    curl_close($downloadCh);
    if ($downloadCode < 200 || $downloadCode >= 300 || $binary === false) {
        return ['success' => false, 'message' => 'Unable to download media binary'];
    }
    return [
        'success' => true,
        'body' => $binary,
        'mime_type' => $meta['mime_type'] ?? 'application/octet-stream'
    ];
}

function isWithinCustomerServiceWindow($db, $phone) {
    $stmt = $db->prepare('SELECT timestamp FROM whatsapp_messages WHERE sender_phone = ? AND direction = "incoming" ORDER BY id DESC LIMIT 1');
    $stmt->execute([$phone]);
    $row = $stmt->fetch();
    if (!$row) return false;
    $last = strtotime($row['timestamp']);
    return (time() - $last) <= (24 * 3600);
}

function whatsappPost($url, $payload) {
    $token = whatsappToken();
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($resp, true);
    return ['success' => $code >= 200 && $code < 300, 'code' => $code, 'data' => $data, 'raw' => $resp];
}

function verifyMetaWebhook() {
    $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? null;
    $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? null;
    $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? null;
    if ($mode === 'subscribe' && $challenge && $token && $token === metaVerifyToken()) {
        header('Content-Type: text/plain');
        echo $challenge;
        exit();
    }
    http_response_code(403);
    echo 'Forbidden';
    exit();
}

function validateMetaSignature($rawBody) {
    $secret = metaAppSecret();
    $sig = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? $_SERVER['X_Hub_Signature_256'] ?? null;
    if (!$secret || !$sig) return false;
    $calc = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
    return hash_equals($calc, $sig);
}

function upsertConversationByPhone($db, $phone, $contactName = null, $phoneNumberId = null, $metaContactId = null) {
    $stmt = $db->prepare('SELECT id, assigned_agent_id, lead_id, contact_name, phone_number_id, meta_contact_id FROM whatsapp_conversations WHERE phone_number = ?');
    $stmt->execute([$phone]);
    $row = $stmt->fetch();
    if ($row) {
        $convId = (int)$row['id'];
        $updates = [];
        $params = [];
        if ($contactName && empty($row['contact_name'])) { $updates[] = 'contact_name = ?'; $params[] = $contactName; }
        if ($phoneNumberId && $phoneNumberId !== $row['phone_number_id']) { $updates[] = 'phone_number_id = ?'; $params[] = $phoneNumberId; }
        if ($metaContactId && empty($row['meta_contact_id'])) { $updates[] = 'meta_contact_id = ?'; $params[] = $metaContactId; }
        if ($updates) {
            $sql = 'UPDATE whatsapp_conversations SET ' . implode(', ', $updates) . ' WHERE id = ?';
            $params[] = $convId;
            $db->prepare($sql)->execute($params);
        }
        return $convId;
    }

    // Ensure a lead exists
    $leadStmt = $db->prepare('SELECT l.id FROM leads l WHERE l.phone_number = ? LIMIT 1');
    $leadStmt->execute([$phone]);
    $lead = $leadStmt->fetch();
    $leadId = $lead ? (int)$lead['id'] : null;
    if (!$leadId) {
        $extId = 'wa:' . $phone . ':' . time();
        $insLead = $db->prepare('INSERT INTO leads (external_id, created_time, platform, full_name, phone_number, is_organic, created_at) VALUES (?, NOW(), ?, ?, ?, 1, NOW())');
        $insLead->execute([$extId, 'whatsapp', $contactName, $phone]);
        $leadId = (int)$db->lastInsertId();
    }

    // Assign agent (round robin)
    $sel = $db->query('SELECT id FROM agents ORDER BY (last_assignment IS NULL) DESC, last_assignment ASC LIMIT 1');
    $agent = $sel->fetch();
    $assigned = $agent ? (int)$agent['id'] : null;
    if ($assigned) {
        $db->prepare('UPDATE agents SET last_assignment = NOW() WHERE id = ?')->execute([$assigned]);
        // Persist assignment to lead_assignments
        $db->prepare('INSERT INTO lead_assignments (lead_id, agent_id, assigned_at) VALUES (?, ?, NOW())')->execute([$leadId, $assigned]);
    }

    // Create conversation
    $ins = $db->prepare('INSERT INTO whatsapp_conversations (phone_number, lead_id, assigned_agent_id, contact_name, phone_number_id, meta_contact_id, last_message_at, first_message_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
    $ins->execute([$phone, $leadId, $assigned, $contactName, $phoneNumberId, $metaContactId]);
    $convId = (int)$db->lastInsertId();
    if ($assigned) {
        notifyConversationUpdate($db, $convId, 'incoming', ['assigned_agent_id' => $assigned]);
    }
    return $convId;
}

function insertMessage($db, $conversationId, $direction, $type, $body, $waMessageId, $senderPhone, $recipientPhone, $status, $metaJson, $mediaUrl = null) {
    $stmt = $db->prepare('INSERT INTO whatsapp_messages (conversation_id, direction, type, body, wa_message_id, sender_phone, recipient_phone, status, media_url, timestamp, meta_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)');
    $stmt->execute([$conversationId, $direction, $type, $body, $waMessageId, $senderPhone, $recipientPhone, $status, $mediaUrl, $metaJson]);
    if ($direction === 'incoming') {
        $db->prepare('UPDATE whatsapp_conversations SET last_message_at = NOW(), last_incoming_at = NOW(), unread_count = unread_count + 1 WHERE id = ?')->execute([$conversationId]);
    } else {
        $db->prepare('UPDATE whatsapp_conversations SET last_message_at = NOW() WHERE id = ?')->execute([$conversationId]);
    }
}

function handleMetaWebhook() {
    $raw = file_get_contents('php://input');
    if (!validateMetaSignature($raw)) {
        http_response_code(403);
        echo json_encode(['success' => false]);
        exit();
    }
    $json = json_decode($raw, true);
    $db = getDB();
    if (!$json || !isset($json['entry'])) {
        http_response_code(200);
        echo json_encode(['success' => true]);
        exit();
    }
    foreach ($json['entry'] as $entry) {
        foreach (($entry['changes'] ?? []) as $change) {
            $field = $change['field'] ?? null;
            $value = $change['value'] ?? [];
            
            // Handle calls webhook
            if ($field === 'calls') {
                require_once 'whatsapp_calling.php';
                whatsappHandleCallWebhook($db, ['value' => $value]);
                continue;
            }
            
            // Handle account settings update webhook
            if ($field === 'account_settings_update') {
                // Handle settings updates if needed
                continue;
            }
            
            // Handle phone number quality update (throughput upgrade)
            if ($field === 'phone_number_quality_update') {
                require_once 'whatsapp_throughput.php';
                whatsappHandleThroughputUpgrade($db, ['value' => $value]);
                continue;
            }
            
            // Handle messages webhook (existing code)
            $contactName = null;
            $metaContactId = null;
            $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
            if (!empty($value['contacts'])) {
                $contact = $value['contacts'][0];
                $metaContactId = $contact['wa_id'] ?? null;
                $contactName = $contact['profile']['name'] ?? null;
            }
            $messages = $value['messages'] ?? [];
            $statuses = $value['statuses'] ?? [];
            
            // Handle call permission webhooks
            foreach ($messages as $m) {
                if (isset($m['interactive']['type']) && $m['interactive']['type'] === 'call_permission_reply') {
                    require_once 'whatsapp_call_permissions.php';
                    whatsappHandleCallPermissionWebhook($db, ['value' => $value, 'messages' => [$m], 'metadata' => $value['metadata'] ?? []]);
                    continue;
                }
            }
            
            foreach ($messages as $m) {
                $from = $m['from'] ?? null;
                $id = $m['id'] ?? null;
                $type = $m['type'] ?? 'unknown';
                $bodyText = null;
                $mediaUrl = null;

                if ($type === 'text') {
                    $bodyText = $m['text']['body'] ?? null;
                } elseif ($type === 'interactive') {
                    // Handle button replies
                    if (isset($m['interactive']['button_reply'])) {
                        $buttonReply = $m['interactive']['button_reply'];
                        $bodyText = 'Button: ' . ($buttonReply['title'] ?? $buttonReply['id'] ?? 'Unknown');
                        // Store button reply data in meta_json
                        $m['button_reply_id'] = $buttonReply['id'] ?? null;
                        $m['button_reply_title'] = $buttonReply['title'] ?? null;
                    }
                    // Handle list replies
                    elseif (isset($m['interactive']['list_reply'])) {
                        $listReply = $m['interactive']['list_reply'];
                        $bodyText = 'List: ' . ($listReply['title'] ?? $listReply['id'] ?? 'Unknown');
                        $m['list_reply_id'] = $listReply['id'] ?? null;
                        $m['list_reply_title'] = $listReply['title'] ?? null;
                        $m['list_reply_description'] = $listReply['description'] ?? null;
                    } else {
                        $bodyText = 'Interactive reply';
                    }
                } elseif ($type === 'location') {
                    $loc = $m['location'] ?? [];
                    $bodyText = trim(($loc['name'] ?? '') . ' ' . ($loc['address'] ?? ''));
                }

                if (in_array($type, ['image','video','audio','document','sticker'], true) && r2IsConfigured()) {
                    $mediaId = $m[$type]['id'] ?? null;
                    if ($mediaId) {
                        $download = whatsappDownloadMedia($mediaId);
                        if ($download['success']) {
                            $ext = r2GuessExtension($download['mime_type']);
                            $key = sprintf('whatsapp/inbox/%s/%s.%s', date('Y/m/d'), $mediaId, $ext);
                            try {
                                $uploaded = r2Upload($key, $download['body'], $download['mime_type']);
                                $mediaUrl = $uploaded['url'];
                            } catch (Exception $e) {
                                error_log('R2 upload failed: ' . $e->getMessage());
                            }
                        }
                    }
                    if (!$bodyText) {
                        $bodyText = $m[$type]['caption'] ?? $m[$type]['filename'] ?? strtoupper($type) . ' message';
                    }
                }

                $conversationId = upsertConversationByPhone($db, $from, $contactName, $phoneNumberId, $metaContactId);
                insertMessage($db, $conversationId, 'incoming', $type, $bodyText, $id, $from, null, 'delivered', json_encode($m), $mediaUrl);
                notifyConversationUpdate($db, $conversationId, 'incoming', $m);
                require_once 'flows.php';
                runIncomingFlows($db, $conversationId, $m, $from);
                // Offline agent alerts
                $q = $db->prepare('SELECT u.email, u.is_online, wc.assigned_agent_id FROM whatsapp_conversations wc JOIN users u ON wc.assigned_agent_id = u.id WHERE wc.id = ?');
                $q->execute([$conversationId]);
                $row = $q->fetch();
                if ($row && ((int)$row['is_online'] === 0)) {
                    $email = $row['email'] ?? null;
                    if ($email && getSetting('email_notifications') === '1') { @mail($email, 'New WhatsApp Message', 'You received a new message from '.$from); }
                    if (getSetting('admin_whatsapp_alerts') === '1') {
                        $nums = getSetting('admin_alert_numbers');
                        if ($nums) {
                            $list = array_filter(array_map('trim', explode(',', $nums)));
                            foreach ($list as $n) { whatsappSendText($n, 'Alert: offline agent has new message from '.$from); }
                        }
                    }
                }
            }
            foreach ($statuses as $s) {
                $id = $s['id'] ?? null;
                if ($id) {
                    $stmt = $db->prepare('UPDATE whatsapp_messages SET status = ? WHERE wa_message_id = ?');
                    $stmt->execute([$s['status'] ?? 'sent', $id]);
                }
            }
        }
    }
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit();
}

function notifyConversationUpdate($db, $conversationId, $direction, $payload) {
    require_once 'pusher.php';
    $q = $db->prepare('SELECT assigned_agent_id FROM whatsapp_conversations WHERE id = ?');
    $q->execute([$conversationId]);
    $row = $q->fetch();
    $agentId = $row ? $row['assigned_agent_id'] : null;
    if ($agentId) {
        pusherTrigger('agent-' . $agentId, 'whatsapp_message', ['conversation_id' => $conversationId, 'direction' => $direction, 'payload' => $payload]);
    }
    pusherTrigger('admin', 'whatsapp_message', ['conversation_id' => $conversationId, 'direction' => $direction, 'payload' => $payload]);
}

?>
