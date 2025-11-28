<?php
require_once 'config.php';
require_once 'whatsapp_cloud.php';
require_once 'pusher.php';

/**
 * WhatsApp Calling API Implementation
 * Handles both business-initiated and user-initiated calls
 * Routes calls to assigned agents
 */

/**
 * Initiate a business-initiated call
 */
function whatsappInitiateCall($to, $sdpOffer, $phoneNumberIdOverride = null, $bizOpaqueCallbackData = null) {
    $token = whatsappToken();
    $pnId = $phoneNumberIdOverride ?: whatsappPhoneNumberId();
    
    if (!$token || !$pnId) {
        return ['success' => false, 'message' => 'WhatsApp calling not configured'];
    }
    
    // Check if user has call permission
    $db = getDB();
    $permission = whatsappGetCallPermission($db, $to, $pnId);
    if (!$permission || $permission['permission_status'] === 'no_permission') {
        return ['success' => false, 'message' => 'User has not granted call permission', 'code' => 138006];
    }
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $pnId . '/calls';
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $to,
        'action' => 'connect',
        'session' => [
            'sdp_type' => 'offer',
            'sdp' => $sdpOffer
        ]
    ];
    
    if ($bizOpaqueCallbackData) {
        $payload['biz_opaque_callback_data'] = substr($bizOpaqueCallbackData, 0, 512);
    }
    
    $result = whatsappPost($url, $payload);
    
    if ($result['success'] && isset($result['data']['calls'][0]['id'])) {
        $callId = $result['data']['calls'][0]['id'];
        
        // Store call record
        $conversationId = whatsappGetConversationIdByPhone($db, $to);
        $agentId = whatsappGetAssignedAgentId($db, $conversationId);
        
        whatsappStoreCall($db, [
            'call_id' => $callId,
            'conversation_id' => $conversationId,
            'phone_number' => $to,
            'business_phone_number_id' => $pnId,
            'direction' => 'BUSINESS_INITIATED',
            'status' => 'RINGING',
            'assigned_agent_id' => $agentId,
            'sdp_offer' => $sdpOffer,
            'biz_opaque_callback_data' => $bizOpaqueCallbackData
        ]);
        
        // Notify agent if assigned
        if ($agentId) {
            whatsappNotifyAgentCall($db, $agentId, $callId, 'BUSINESS_INITIATED', $to);
        }
    }
    
    return $result;
}

/**
 * Accept a user-initiated call
 */
function whatsappAcceptCall($callId, $sdpAnswer, $phoneNumberIdOverride = null, $bizOpaqueCallbackData = null) {
    $token = whatsappToken();
    $pnId = $phoneNumberIdOverride ?: whatsappPhoneNumberId();
    
    if (!$token || !$pnId) {
        return ['success' => false, 'message' => 'WhatsApp calling not configured'];
    }
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $pnId . '/calls';
    $payload = [
        'messaging_product' => 'whatsapp',
        'call_id' => $callId,
        'action' => 'accept',
        'session' => [
            'sdp_type' => 'answer',
            'sdp' => $sdpAnswer
        ]
    ];
    
    if ($bizOpaqueCallbackData) {
        $payload['biz_opaque_callback_data'] = substr($bizOpaqueCallbackData, 0, 512);
    }
    
    $result = whatsappPost($url, $payload);
    
    if ($result['success']) {
        $db = getDB();
        whatsappUpdateCallStatus($db, $callId, 'ACCEPTED', ['sdp_answer' => $sdpAnswer]);
    }
    
    return $result;
}

/**
 * Pre-accept a user-initiated call (recommended for faster connection)
 */
function whatsappPreAcceptCall($callId, $sdpAnswer, $phoneNumberIdOverride = null) {
    $token = whatsappToken();
    $pnId = $phoneNumberIdOverride ?: whatsappPhoneNumberId();
    
    if (!$token || !$pnId) {
        return ['success' => false, 'message' => 'WhatsApp calling not configured'];
    }
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $pnId . '/calls';
    $payload = [
        'messaging_product' => 'whatsapp',
        'call_id' => $callId,
        'action' => 'pre_accept',
        'session' => [
            'sdp_type' => 'answer',
            'sdp' => $sdpAnswer
        ]
    ];
    
    return whatsappPost($url, $payload);
}

/**
 * Reject a user-initiated call
 */
function whatsappRejectCall($callId, $phoneNumberIdOverride = null) {
    $token = whatsappToken();
    $pnId = $phoneNumberIdOverride ?: whatsappPhoneNumberId();
    
    if (!$token || !$pnId) {
        return ['success' => false, 'message' => 'WhatsApp calling not configured'];
    }
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $pnId . '/calls';
    $payload = [
        'messaging_product' => 'whatsapp',
        'call_id' => $callId,
        'action' => 'reject'
    ];
    
    $result = whatsappPost($url, $payload);
    
    if ($result['success']) {
        $db = getDB();
        whatsappUpdateCallStatus($db, $callId, 'REJECTED');
    }
    
    return $result;
}

/**
 * Terminate a call
 */
function whatsappTerminateCall($callId, $phoneNumberIdOverride = null) {
    $token = whatsappToken();
    $pnId = $phoneNumberIdOverride ?: whatsappPhoneNumberId();
    
    if (!$token || !$pnId) {
        return ['success' => false, 'message' => 'WhatsApp calling not configured'];
    }
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $pnId . '/calls';
    $payload = [
        'messaging_product' => 'whatsapp',
        'call_id' => $callId,
        'action' => 'terminate'
    ];
    
    $result = whatsappPost($url, $payload);
    
    if ($result['success']) {
        $db = getDB();
        whatsappUpdateCallStatus($db, $callId, 'TERMINATED');
    }
    
    return $result;
}

/**
 * Get call permission status
 */
function whatsappGetCallPermission($db, $phoneNumber, $businessPhoneNumberId) {
    $stmt = $db->prepare('SELECT * FROM whatsapp_call_permissions WHERE phone_number = ? AND business_phone_number_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$phoneNumber, $businessPhoneNumberId]);
    $permission = $stmt->fetch();
    
    if ($permission) {
        // Check if temporary permission has expired
        if ($permission['permission_status'] === 'temporary' && $permission['expiration_time']) {
            if (time() > $permission['expiration_time']) {
                // Permission expired
                $stmt = $db->prepare('UPDATE whatsapp_call_permissions SET permission_status = "no_permission", revoked_at = NOW() WHERE id = ?');
                $stmt->execute([$permission['id']]);
                return null;
            }
        }
    }
    
    return $permission;
}

/**
 * Store call record
 */
function whatsappStoreCall($db, $callData) {
    $stmt = $db->prepare('INSERT INTO whatsapp_calls (call_id, conversation_id, phone_number, user_wa_id, business_phone_number_id, direction, status, assigned_agent_id, sdp_offer, sdp_answer, biz_opaque_callback_data, deeplink_payload, cta_payload, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $callData['call_id'],
        $callData['conversation_id'] ?? null,
        $callData['phone_number'],
        $callData['user_wa_id'] ?? null,
        $callData['business_phone_number_id'],
        $callData['direction'],
        $callData['status'] ?? 'RINGING',
        $callData['assigned_agent_id'] ?? null,
        $callData['sdp_offer'] ?? null,
        $callData['sdp_answer'] ?? null,
        $callData['biz_opaque_callback_data'] ?? null,
        $callData['deeplink_payload'] ?? null,
        $callData['cta_payload'] ?? null
    ]);
    
    return $db->lastInsertId();
}

/**
 * Update call status
 */
function whatsappUpdateCallStatus($db, $callId, $status, $additionalData = []) {
    $updates = ['status = ?'];
    $params = [$status];
    
    if (isset($additionalData['sdp_answer'])) {
        $updates[] = 'sdp_answer = ?';
        $params[] = $additionalData['sdp_answer'];
    }
    
    if (isset($additionalData['start_time'])) {
        $updates[] = 'start_time = ?';
        $params[] = $additionalData['start_time'];
    }
    
    if (isset($additionalData['end_time'])) {
        $updates[] = 'end_time = ?';
        $params[] = $additionalData['end_time'];
    }
    
    if (isset($additionalData['duration'])) {
        $updates[] = 'duration = ?';
        $params[] = $additionalData['duration'];
    }
    
    if (isset($additionalData['error_code'])) {
        $updates[] = 'error_code = ?';
        $params[] = $additionalData['error_code'];
    }
    
    if (isset($additionalData['error_message'])) {
        $updates[] = 'error_message = ?';
        $params[] = $additionalData['error_message'];
    }
    
    $updates[] = 'updated_at = NOW()';
    $params[] = $callId;
    
    $sql = 'UPDATE whatsapp_calls SET ' . implode(', ', $updates) . ' WHERE call_id = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->rowCount() > 0;
}

/**
 * Get conversation ID by phone number
 */
function whatsappGetConversationIdByPhone($db, $phone) {
    $stmt = $db->prepare('SELECT id FROM whatsapp_conversations WHERE phone_number = ? LIMIT 1');
    $stmt->execute([$phone]);
    $row = $stmt->fetch();
    return $row ? (int)$row['id'] : null;
}

/**
 * Get assigned agent ID for conversation
 */
function whatsappGetAssignedAgentId($db, $conversationId) {
    if (!$conversationId) return null;
    
    $stmt = $db->prepare('SELECT assigned_agent_id FROM whatsapp_conversations WHERE id = ?');
    $stmt->execute([$conversationId]);
    $row = $stmt->fetch();
    return $row ? (int)$row['assigned_agent_id'] : null;
}

/**
 * Route call to agent - assign call to conversation's agent or round-robin
 */
function whatsappRouteCallToAgent($db, $callId, $phoneNumber, $direction) {
    $conversationId = whatsappGetConversationIdByPhone($db, $phoneNumber);
    $agentId = null;
    
    if ($conversationId) {
        // Use conversation's assigned agent
        $agentId = whatsappGetAssignedAgentId($db, $conversationId);
    }
    
    if (!$agentId) {
        // Round-robin assignment
        $stmt = $db->query('SELECT id FROM agents ORDER BY (last_assignment IS NULL) DESC, last_assignment ASC LIMIT 1');
        $agent = $stmt->fetch();
        $agentId = $agent ? (int)$agent['id'] : null;
        
        if ($agentId) {
            $db->prepare('UPDATE agents SET last_assignment = NOW() WHERE id = ?')->execute([$agentId]);
        }
    }
    
    if ($agentId) {
        $stmt = $db->prepare('UPDATE whatsapp_calls SET assigned_agent_id = ?, conversation_id = ? WHERE call_id = ?');
        $stmt->execute([$agentId, $conversationId, $callId]);
        
        // Notify agent
        whatsappNotifyAgentCall($db, $agentId, $callId, $direction, $phoneNumber);
    }
    
    return $agentId;
}

/**
 * Notify agent about incoming call via Pusher
 */
function whatsappNotifyAgentCall($db, $agentId, $callId, $direction, $phoneNumber) {
    $stmt = $db->prepare('SELECT * FROM whatsapp_calls WHERE call_id = ?');
    $stmt->execute([$callId]);
    $call = $stmt->fetch();
    
    if (!$call) return;
    
    // Get contact name
    $contactName = null;
    if ($call['conversation_id']) {
        $convStmt = $db->prepare('SELECT contact_name FROM whatsapp_conversations WHERE id = ?');
        $convStmt->execute([$call['conversation_id']]);
        $conv = $convStmt->fetch();
        $contactName = $conv['contact_name'] ?? null;
    }
    
    $payload = [
        'call_id' => $callId,
        'direction' => $direction,
        'phone_number' => $phoneNumber,
        'contact_name' => $contactName,
        'status' => $call['status'],
        'sdp_offer' => $call['sdp_offer'],
        'sdp_answer' => $call['sdp_answer'],
        'timestamp' => time()
    ];
    
    // Notify via Pusher
    pusherTrigger('agent-' . $agentId, 'whatsapp_call', $payload);
    pusherTrigger('admin', 'whatsapp_call', $payload);
}

/**
 * Handle call webhook from Meta
 */
function whatsappHandleCallWebhook($db, $webhookData) {
    $value = $webhookData['value'] ?? [];
    $calls = $value['calls'] ?? [];
    $statuses = $value['statuses'] ?? [];
    $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
    
    foreach ($calls as $call) {
        $callId = $call['id'] ?? null;
        $event = $call['event'] ?? null;
        $direction = $call['direction'] ?? null;
        $from = $call['from'] ?? null;
        $to = $call['to'] ?? null;
        
        if (!$callId) continue;
        
        if ($event === 'connect') {
            // New call - route to agent
            $phoneNumber = $direction === 'USER_INITIATED' ? $from : $to;
            $agentId = whatsappRouteCallToAgent($db, $callId, $phoneNumber, $direction);
            
            // Store call
            $callData = [
                'call_id' => $callId,
                'phone_number' => $phoneNumber,
                'user_wa_id' => $direction === 'USER_INITIATED' ? $from : $to,
                'business_phone_number_id' => $phoneNumberId,
                'direction' => $direction,
                'status' => 'RINGING',
                'sdp_offer' => $call['session']['sdp'] ?? null,
                'deeplink_payload' => $call['deeplink_payload'] ?? null,
                'cta_payload' => $call['cta_payload'] ?? null
            ];
            
            whatsappStoreCall($db, $callData);
            
        } elseif ($event === 'terminate') {
            // Call terminated
            $updateData = [
                'status' => $call['status'][0] ?? 'TERMINATED',
                'end_time' => $call['end_time'] ?? time(),
                'duration' => $call['duration'] ?? null
            ];
            
            if (isset($call['start_time'])) {
                $updateData['start_time'] = $call['start_time'];
            }
            
            whatsappUpdateCallStatus($db, $callId, $updateData['status'], $updateData);
        }
    }
    
    foreach ($statuses as $status) {
        $callId = $status['id'] ?? null;
        $callStatus = $status['status'] ?? null;
        
        if ($callId && $callStatus) {
            $updateData = [];
            if ($callStatus === 'ACCEPTED') {
                $updateData['start_time'] = time();
            }
            whatsappUpdateCallStatus($db, $callId, $callStatus, $updateData);
        }
    }
}

/**
 * Get call settings for a phone number
 */
function whatsappGetCallSettings($db, $phoneNumberId) {
    $stmt = $db->prepare('SELECT * FROM whatsapp_call_settings WHERE phone_number_id = ?');
    $stmt->execute([$phoneNumberId]);
    return $stmt->fetch();
}

/**
 * Update call settings
 */
function whatsappUpdateCallSettings($db, $phoneNumberId, $settings) {
    $existing = whatsappGetCallSettings($db, $phoneNumberId);
    
    if ($existing) {
        $stmt = $db->prepare('UPDATE whatsapp_call_settings SET calling_status = ?, call_icon_visibility = ?, callback_permission_status = ?, call_hours_status = ?, call_hours_timezone = ?, call_hours_weekly_schedule = ?, call_hours_holiday_schedule = ?, sip_status = ?, sip_servers = ?, updated_at = NOW() WHERE phone_number_id = ?');
        $stmt->execute([
            $settings['calling_status'] ?? $existing['calling_status'],
            $settings['call_icon_visibility'] ?? $existing['call_icon_visibility'],
            $settings['callback_permission_status'] ?? $existing['callback_permission_status'],
            $settings['call_hours_status'] ?? $existing['call_hours_status'],
            $settings['call_hours_timezone'] ?? $existing['call_hours_timezone'],
            isset($settings['call_hours_weekly_schedule']) ? json_encode($settings['call_hours_weekly_schedule']) : $existing['call_hours_weekly_schedule'],
            isset($settings['call_hours_holiday_schedule']) ? json_encode($settings['call_hours_holiday_schedule']) : $existing['call_hours_holiday_schedule'],
            $settings['sip_status'] ?? $existing['sip_status'],
            isset($settings['sip_servers']) ? json_encode($settings['sip_servers']) : $existing['sip_servers'],
            $phoneNumberId
        ]);
    } else {
        $stmt = $db->prepare('INSERT INTO whatsapp_call_settings (phone_number_id, calling_status, call_icon_visibility, callback_permission_status, call_hours_status, call_hours_timezone, call_hours_weekly_schedule, call_hours_holiday_schedule, sip_status, sip_servers) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $phoneNumberId,
            $settings['calling_status'] ?? 'DISABLED',
            $settings['call_icon_visibility'] ?? 'DEFAULT',
            $settings['callback_permission_status'] ?? 'DISABLED',
            $settings['call_hours_status'] ?? 'DISABLED',
            $settings['call_hours_timezone'] ?? null,
            isset($settings['call_hours_weekly_schedule']) ? json_encode($settings['call_hours_weekly_schedule']) : null,
            isset($settings['call_hours_holiday_schedule']) ? json_encode($settings['call_hours_holiday_schedule']) : null,
            $settings['sip_status'] ?? 'DISABLED',
            isset($settings['sip_servers']) ? json_encode($settings['sip_servers']) : null
        ]);
    }
    
    // Also update via Meta API
    $token = whatsappToken();
    if ($token) {
        $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $phoneNumberId . '/settings';
        $payload = [
            'calling' => [
                'status' => $settings['calling_status'] ?? 'DISABLED',
                'call_icon_visibility' => $settings['call_icon_visibility'] ?? 'DEFAULT',
                'callback_permission_status' => $settings['callback_permission_status'] ?? 'DISABLED'
            ]
        ];
        
        if (isset($settings['call_hours_status']) && $settings['call_hours_status'] === 'ENABLED') {
            $payload['calling']['call_hours'] = [
                'status' => 'ENABLED',
                'timezone_id' => $settings['call_hours_timezone'] ?? 'UTC',
                'weekly_operating_hours' => $settings['call_hours_weekly_schedule'] ?? [],
                'holiday_schedule' => $settings['call_hours_holiday_schedule'] ?? []
            ];
        }
        
        if (isset($settings['sip_status']) && $settings['sip_status'] === 'ENABLED') {
            $payload['calling']['sip'] = [
                'status' => 'ENABLED',
                'servers' => $settings['sip_servers'] ?? []
            ];
        }
        
        whatsappPost($url, $payload);
    }
    
    return true;
}

?>

