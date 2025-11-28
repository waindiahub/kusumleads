<?php
require_once 'whatsapp_cloud.php';

/**
 * WhatsApp Call Permissions
 * Handle requesting and managing call permissions from users
 */

/**
 * Send free form call permission request
 */
function whatsappSendCallPermissionRequest($to, $bodyText = null, $phoneNumberIdOverride = null) {
    $token = whatsappToken();
    $pnId = $phoneNumberIdOverride ?: whatsappPhoneNumberId();
    
    if (!$token || !$pnId) {
        return ['success' => false, 'message' => 'WhatsApp not configured'];
    }
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $pnId . '/messages';
    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $to,
        'type' => 'interactive',
        'interactive' => [
            'type' => 'call_permission_request',
            'action' => [
                'name' => 'call_permission_request'
            ]
        ]
    ];
    
    if ($bodyText) {
        $payload['interactive']['body'] = ['text' => $bodyText];
    }
    
    return whatsappPost($url, $payload);
}

/**
 * Send template call permission request
 */
function whatsappSendTemplateCallPermissionRequest($to, $templateName, $languageCode, $components = [], $phoneNumberIdOverride = null) {
    $token = whatsappToken();
    $pnId = $phoneNumberIdOverride ?: whatsappPhoneNumberId();
    
    if (!$token || !$pnId) {
        return ['success' => false, 'message' => 'WhatsApp not configured'];
    }
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $pnId . '/messages';
    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
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

/**
 * Store call permission in database
 */
function whatsappStoreCallPermission($db, $phoneNumber, $userWaId, $businessPhoneNumberId, $permissionStatus, $expirationTime = null, $isPermanent = false) {
    // Check if permission already exists
    $stmt = $db->prepare('SELECT id FROM whatsapp_call_permissions WHERE phone_number = ? AND user_wa_id = ? AND business_phone_number_id = ?');
    $stmt->execute([$phoneNumber, $userWaId, $businessPhoneNumberId]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing permission
        $stmt = $db->prepare('UPDATE whatsapp_call_permissions SET permission_status = ?, expiration_time = ?, granted_at = NOW(), revoked_at = NULL, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$permissionStatus, $expirationTime, $existing['id']]);
        return $existing['id'];
    } else {
        // Insert new permission
        $stmt = $db->prepare('INSERT INTO whatsapp_call_permissions (phone_number, user_wa_id, business_phone_number_id, permission_status, expiration_time, granted_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$phoneNumber, $userWaId, $businessPhoneNumberId, $permissionStatus, $expirationTime]);
        return $db->lastInsertId();
    }
}

/**
 * Handle call permission webhook
 */
function whatsappHandleCallPermissionWebhook($db, $webhookData) {
    $messages = $webhookData['messages'] ?? [];
    
    foreach ($messages as $message) {
        if (isset($message['interactive']['type']) && $message['interactive']['type'] === 'call_permission_reply') {
            $permissionReply = $message['interactive']['call_permission_reply'] ?? [];
            $response = $permissionReply['response'] ?? null; // 'accept' or 'reject'
            $isPermanent = $permissionReply['is_permanent'] ?? false;
            $expirationTimestamp = $permissionReply['expiration_timestamp'] ?? null;
            $from = $message['from'] ?? null;
            $contextId = $message['context']['id'] ?? null;
            
            if (!$from || !$response) continue;
            
            $phoneNumberId = $webhookData['metadata']['phone_number_id'] ?? whatsappPhoneNumberId();
            
            if ($response === 'accept') {
                $permissionStatus = $isPermanent ? 'permanent' : 'temporary';
                $expirationTime = $isPermanent ? null : $expirationTimestamp;
                
                whatsappStoreCallPermission($db, $from, $from, $phoneNumberId, $permissionStatus, $expirationTime, $isPermanent);
                
                // Update conversation
                $stmt = $db->prepare('UPDATE whatsapp_conversations SET call_permission_status = ? WHERE phone_number = ?');
                $stmt->execute([$permissionStatus, $from]);
            } else {
                // Permission rejected
                $stmt = $db->prepare('UPDATE whatsapp_call_permissions SET permission_status = "no_permission", revoked_at = NOW() WHERE phone_number = ? AND business_phone_number_id = ?');
                $stmt->execute([$from, $phoneNumberId]);
            }
        }
    }
}

/**
 * Get current call permission state
 */
function whatsappGetCallPermissionState($db, $phoneNumberId, $userWaId) {
    $permission = whatsappGetCallPermission($db, $userWaId, $phoneNumberId);
    
    $state = [
        'messaging_product' => 'whatsapp',
        'permission' => [
            'status' => $permission ? $permission['permission_status'] : 'no_permission',
            'expiration_time' => $permission && $permission['expiration_time'] ? (int)$permission['expiration_time'] : null
        ],
        'actions' => []
    ];
    
    // Check if can send permission request
    $canSendRequest = true;
    $limits = [];
    
    // Check 24h limit (1 request per 24h)
    $stmt = $db->prepare('SELECT COUNT(*) as count FROM whatsapp_call_permissions WHERE phone_number = ? AND business_phone_number_id = ? AND granted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
    $stmt->execute([$userWaId, $phoneNumberId]);
    $dayCount = $stmt->fetch();
    if ($dayCount && (int)$dayCount['count'] >= 1) {
        $canSendRequest = false;
        $limits[] = [
            'time_period' => 'PT24H',
            'max_allowed' => 1,
            'current_usage' => (int)$dayCount['count'],
            'limit_expiration_time' => time() + (24 * 3600)
        ];
    } else {
        $limits[] = [
            'time_period' => 'PT24H',
            'max_allowed' => 1,
            'current_usage' => $dayCount ? (int)$dayCount['count'] : 0
        ];
    }
    
    // Check 7 day limit (2 requests per 7 days)
    $stmt = $db->prepare('SELECT COUNT(*) as count FROM whatsapp_call_permissions WHERE phone_number = ? AND business_phone_number_id = ? AND granted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
    $stmt->execute([$userWaId, $phoneNumberId]);
    $weekCount = $stmt->fetch();
    if ($weekCount && (int)$weekCount['count'] >= 2) {
        $canSendRequest = false;
        $limits[] = [
            'time_period' => 'P7D',
            'max_allowed' => 2,
            'current_usage' => (int)$weekCount['count'],
            'limit_expiration_time' => time() + (7 * 24 * 3600)
        ];
    } else {
        $limits[] = [
            'time_period' => 'P7D',
            'max_allowed' => 2,
            'current_usage' => $weekCount ? (int)$weekCount['count'] : 0
        ];
    }
    
    $state['actions'][] = [
        'action_name' => 'send_call_permission_request',
        'can_perform_action' => $canSendRequest,
        'limits' => $limits
    ];
    
    // Check if can start call
    $canStartCall = $permission && in_array($permission['permission_status'], ['temporary', 'permanent']);
    $callLimits = [];
    
    if ($canStartCall) {
        // Check 24h call limit (10 calls per 24h)
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM whatsapp_calls WHERE phone_number = ? AND business_phone_number_id = ? AND direction = "BUSINESS_INITIATED" AND status = "COMPLETED" AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
        $stmt->execute([$userWaId, $phoneNumberId]);
        $callCount = $stmt->fetch();
        
        if ($callCount && (int)$callCount['count'] >= 10) {
            $canStartCall = false;
            $callLimits[] = [
                'time_period' => 'PT24H',
                'max_allowed' => 10,
                'current_usage' => (int)$callCount['count'],
                'limit_expiration_time' => time() + (24 * 3600)
            ];
        } else {
            $callLimits[] = [
                'time_period' => 'PT24H',
                'max_allowed' => 10,
                'current_usage' => $callCount ? (int)$callCount['count'] : 0
            ];
        }
    }
    
    $state['actions'][] = [
        'action_name' => 'start_call',
        'can_perform_action' => $canStartCall,
        'limits' => $callLimits
    ];
    
    return $state;
}

?>

