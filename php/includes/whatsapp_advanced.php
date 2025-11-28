<?php
require_once 'config.php';
require_once 'whatsapp_cloud.php';
require_once 'r2_client.php';

function whatsappSendInteractive($to, $type, $payload, $phoneNumberIdOverride = null) {
    $token = whatsappToken();
    $pnId = $phoneNumberIdOverride ?: whatsappPhoneNumberId();
    if (!$token || !$pnId) return ['success' => false, 'message' => 'WhatsApp not configured'];
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $pnId . '/messages';
    $message = [
        'messaging_product' => 'whatsapp',
        'to' => $to,
        'type' => 'interactive',
        'interactive' => $payload
    ];
    return whatsappPost($url, $message);
}

function whatsappSendListMessage($to, $header, $body, $footer, $options, $phoneNumberIdOverride = null) {
    $buttons = array_map(function($opt, $idx) {
        return [
            'id' => (string)$idx,
            'title' => substr($opt['title'], 0, 24),
            'description' => substr($opt['description'] ?? '', 0, 72)
        ];
    }, $options, array_keys($options));
    
    $payload = [
        'type' => 'list',
        'header' => ['type' => 'text', 'text' => substr($header, 0, 60)],
        'body' => ['text' => $body],
        'footer' => ['text' => substr($footer, 0, 60)],
        'action' => [
            'button' => 'Select',
            'sections' => [
                [
                    'title' => 'Options',
                    'rows' => $buttons
                ]
            ]
        ]
    ];
    
    return whatsappSendInteractive($to, 'list', $payload, $phoneNumberIdOverride);
}

function whatsappSendButtonMessage($to, $body, $buttons, $phoneNumberIdOverride = null, $header = null, $footer = null) {
    if (count($buttons) > 3) $buttons = array_slice($buttons, 0, 3);
    
    $buttonPayload = array_map(function($btn) {
        $buttonId = $btn['id'] ?? (string)uniqid('btn_', true);
        $buttonTitle = substr($btn['title'] ?? 'Button', 0, 20);
        
        return [
            'type' => 'reply',
            'reply' => [
                'id' => $buttonId,
                'title' => $buttonTitle
            ]
        ];
    }, $buttons);
    
    $payload = [
        'type' => 'button',
        'body' => ['text' => substr($body, 0, 1024)],
        'action' => ['buttons' => $buttonPayload]
    ];
    
    // Add header if provided
    if ($header) {
        if (is_string($header)) {
            $payload['header'] = ['type' => 'text', 'text' => substr($header, 0, 60)];
        } elseif (is_array($header)) {
            $payload['header'] = $header;
        }
    }
    
    // Add footer if provided
    if ($footer) {
        $payload['footer'] = ['text' => substr($footer, 0, 60)];
    }
    
    return whatsappSendInteractive($to, 'button', $payload, $phoneNumberIdOverride);
}

function whatsappSendProductMessage($to, $catalogId, $productId, $bodyText = '', $phoneNumberIdOverride = null) {
    $payload = [
        'type' => 'product',
        'product' => [
            'catalog_id' => $catalogId,
            'product_retailer_id' => $productId
        ]
    ];
    
    if ($bodyText) {
        $payload['body'] = ['text' => substr($bodyText, 0, 1024)];
    }
    
    return whatsappSendInteractive($to, 'product', $payload, $phoneNumberIdOverride);
}

function whatsappSendProductListMessage($to, $catalogId, $headerText, $bodyText, $products, $phoneNumberIdOverride = null) {
    $sections = array_map(function($section) {
        return [
            'title' => $section['title'] ?? 'Products',
            'product_items' => array_slice($section['products'] ?? [], 0, 30)
        ];
    }, [['products' => $products]]);
    
    $payload = [
        'type' => 'product_list',
        'header' => ['type' => 'text', 'text' => substr($headerText, 0, 60)],
        'body' => ['text' => substr($bodyText, 0, 1024)],
        'action' => [
            'catalog_id' => $catalogId,
            'sections' => $sections
        ]
    ];
    
    return whatsappSendInteractive($to, 'product_list', $payload, $phoneNumberIdOverride);
}

function whatsappSendLocationMessage($to, $latitude, $longitude, $name, $address, $phoneNumberIdOverride = null) {
    $token = whatsappToken();
    $pnId = $phoneNumberIdOverride ?: whatsappPhoneNumberId();
    if (!$token || !$pnId) return ['success' => false, 'message' => 'WhatsApp not configured'];
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $pnId . '/messages';
    $message = [
        'messaging_product' => 'whatsapp',
        'to' => $to,
        'type' => 'location',
        'location' => [
            'latitude' => (float)$latitude,
            'longitude' => (float)$longitude,
            'name' => substr($name, 0, 1024),
            'address' => substr($address, 0, 1024)
        ]
    ];
    return whatsappPost($url, $message);
}

function whatsappSendContactMessage($to, $contacts, $phoneNumberIdOverride = null) {
    if (count($contacts) > 50) $contacts = array_slice($contacts, 0, 50);
    
    $token = whatsappToken();
    $pnId = $phoneNumberIdOverride ?: whatsappPhoneNumberId();
    if (!$token || !$pnId) return ['success' => false, 'message' => 'WhatsApp not configured'];
    
    $contactPayload = array_map(function($c) {
        return [
            'addresses' => $c['addresses'] ?? [],
            'birthday' => $c['birthday'] ?? '',
            'emails' => $c['emails'] ?? [],
            'name' => [
                'formatted_name' => $c['formatted_name'] ?? $c['name'] ?? '',
                'first_name' => $c['first_name'] ?? '',
                'last_name' => $c['last_name'] ?? ''
            ],
            'org' => ['company' => $c['company'] ?? ''],
            'phones' => $c['phones'] ?? [],
            'urls' => $c['urls'] ?? []
        ];
    }, $contacts);
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $pnId . '/messages';
    $message = [
        'messaging_product' => 'whatsapp',
        'to' => $to,
        'type' => 'contacts',
        'contacts' => $contactPayload
    ];
    return whatsappPost($url, $message);
}

function whatsappSendMedia($to, $type, $mediaUrl, $caption = null, $filename = null, $phoneNumberIdOverride = null) {
    $token = whatsappToken();
    $pnId = $phoneNumberIdOverride ?: whatsappPhoneNumberId();
    if (!$token || !$pnId) return ['success' => false, 'message' => 'WhatsApp not configured'];
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $pnId . '/messages';
    
    $mediaPayload = ['link' => $mediaUrl];
    if ($filename) $mediaPayload['filename'] = $filename;
    if ($caption && in_array($type, ['image', 'video', 'audio'])) {
        $mediaPayload['caption'] = substr($caption, 0, 1024);
    }
    
    $message = [
        'messaging_product' => 'whatsapp',
        'to' => $to,
        'type' => $type,
        $type => $mediaPayload
    ];
    
    return whatsappPost($url, $message);
}

function whatsappGetMediaUrl($mediaId) {
    $token = whatsappToken();
    if (!$token) return ['success' => false, 'message' => 'WhatsApp not configured'];
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $mediaId;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($resp, true);
    if ($code < 200 || $code >= 300) return ['success' => false, 'message' => 'Failed to get media'];
    
    return ['success' => true, 'url' => $data['url'] ?? null, 'mime_type' => $data['mime_type'] ?? null];
}

function whatsappDownloadAndStoreMedia($mediaId, $db) {
    $mediaInfo = whatsappGetMediaUrl($mediaId);
    if (!$mediaInfo['success']) return $mediaInfo;
    
    if (!r2IsConfigured()) return ['success' => false, 'message' => 'R2 not configured'];
    
    $ch = curl_init($mediaInfo['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $mediaData = curl_exec($ch);
    curl_close($ch);
    
    $mimeType = $mediaInfo['mime_type'] ?? 'application/octet-stream';
    $ext = r2GuessExtension($mimeType);
    $key = 'whatsapp/media/' . date('Y/m/d') . '/' . $mediaId . '.' . $ext;
    
    try {
        $r2Result = r2Upload($key, $mediaData, $mimeType);
        return ['success' => true, 'key' => $r2Result['key'], 'url' => $r2Result['url'], 'mime_type' => $mimeType];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function updateMessageWithR2Media($db, $conversationId, $messageId, $r2Key, $r2Url, $mimeType) {
    $stmt = $db->prepare('UPDATE whatsapp_messages SET media_url = ?, media_type = ?, metadata_json = JSON_OBJECT("r2_key", ?) WHERE id = ? AND conversation_id = ?');
    $stmt->execute([$r2Url, $mimeType, $r2Key, $messageId, $conversationId]);
    return $stmt->rowCount() > 0;
}

function whatsappGetConversationMetadata($db, $conversationId) {
    $stmt = $db->prepare('SELECT * FROM whatsapp_conversations WHERE id = ?');
    $stmt->execute([$conversationId]);
    return $stmt->fetch();
}

function whatsappUpdateConversationMetadata($db, $conversationId, $metadata) {
    $stmt = $db->prepare('UPDATE whatsapp_conversations SET metadata_json = ? WHERE id = ?');
    $stmt->execute([json_encode($metadata), $conversationId]);
}

function whatsappMarkMessageAsRead($db, $messageId, $conversationId) {
    $stmt = $db->prepare('UPDATE whatsapp_messages SET status = "read" WHERE id = ? AND conversation_id = ?');
    $stmt->execute([$messageId, $conversationId]);
}

function whatsappGetQualityRating($db, $phoneNumber) {
    $stmt = $db->prepare('SELECT quality_rating, quality_rating_reason FROM whatsapp_phone_numbers WHERE phone_number = ?');
    $stmt->execute([$phoneNumber]);
    return $stmt->fetch();
}

function whatsappGetConversationMetrics($db, $from = null, $to = null) {
    $query = 'SELECT COUNT(*) as total_conversations, SUM(message_count) as total_messages, AVG(avg_response_time) as avg_response_time FROM whatsapp_conversations';
    if ($from) $query .= ' WHERE created_at >= ?';
    if ($to) $query .= ' AND created_at <= ?';
    
    $stmt = $db->prepare($query);
    if ($from && $to) {
        $stmt->execute([$from, $to]);
    } elseif ($from) {
        $stmt->execute([$from]);
    } else {
        $stmt->execute();
    }
    
    return $stmt->fetch();
}

function isWithinCustomerServiceWindow($db, $phone) {
    $stmt = $db->prepare('SELECT last_message_at FROM whatsapp_conversations WHERE phone_number = ? ORDER BY last_message_at DESC LIMIT 1');
    $stmt->execute([$phone]);
    $row = $stmt->fetch();
    if (!$row) return true;
    
    $lastMessageTime = strtotime($row['last_message_at']);
    $now = time();
    return ($now - $lastMessageTime) < (24 * 3600);
}

function whatsappPost($url, $payload) {
    $token = whatsappToken();
    if (!$token) return ['success' => false, 'message' => 'WhatsApp token not configured'];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($resp, true);
    return [
        'success' => $code >= 200 && $code < 300,
        'data' => $data,
        'status_code' => $code
    ];
}
?>
