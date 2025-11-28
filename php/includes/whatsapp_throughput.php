<?php
require_once 'config.php';
require_once 'whatsapp_cloud.php';

/**
 * WhatsApp Throughput Management
 * Monitor and handle 80-1000 mps throughput levels
 */

/**
 * Get current throughput level for a phone number
 */
function whatsappGetThroughput($phoneNumberId = null) {
    $token = whatsappToken();
    $pnId = $phoneNumberId ?: whatsappPhoneNumberId();
    
    if (!$token || !$pnId) {
        return ['success' => false, 'message' => 'WhatsApp not configured'];
    }
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $pnId . '?fields=throughput';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300 && isset($data['throughput'])) {
        return [
            'success' => true,
            'throughput' => $data['throughput'],
            'data' => $data
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to get throughput', 'data' => $data];
}

/**
 * Monitor message sending rate
 */
function whatsappMonitorMessageRate($db, $phoneNumberId) {
    // Get messages sent in the last second
    $stmt = $db->prepare('SELECT COUNT(*) as count FROM whatsapp_messages WHERE business_phone_number_id = ? AND direction = "outgoing" AND timestamp > DATE_SUB(NOW(), INTERVAL 1 SECOND)');
    $stmt->execute([$phoneNumberId]);
    $result = $stmt->fetch();
    $currentRate = (int)$result['count'];
    
    // Get throughput level
    $throughputInfo = whatsappGetThroughput($phoneNumberId);
    $maxThroughput = 80; // Default
    
    if ($throughputInfo['success'] && isset($throughputInfo['throughput']['level'])) {
        $level = $throughputInfo['throughput']['level'];
        if ($level === 'STANDARD') {
            $maxThroughput = 80;
        } elseif ($level === 'HIGH') {
            $maxThroughput = 1000;
        } elseif ($level === 'COEXISTENCE') {
            $maxThroughput = 20;
        }
    }
    
    return [
        'current_rate' => $currentRate,
        'max_throughput' => $maxThroughput,
        'available_capacity' => max(0, $maxThroughput - $currentRate),
        'utilization_percent' => ($currentRate / $maxThroughput) * 100,
        'can_send' => $currentRate < $maxThroughput
    ];
}

/**
 * Check if we can send message based on throughput
 */
function whatsappCanSendMessage($db, $phoneNumberId) {
    $monitor = whatsappMonitorMessageRate($db, $phoneNumberId);
    return $monitor['can_send'];
}

/**
 * Store throughput metrics
 */
function whatsappStoreThroughputMetrics($db, $phoneNumberId, $metrics) {
    $stmt = $db->prepare('INSERT INTO whatsapp_throughput_metrics (phone_number_id, current_rate, max_throughput, utilization_percent, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->execute([
        $phoneNumberId,
        $metrics['current_rate'],
        $metrics['max_throughput'],
        $metrics['utilization_percent']
    ]);
}

/**
 * Get throughput history
 */
function whatsappGetThroughputHistory($db, $phoneNumberId, $hours = 24) {
    $stmt = $db->prepare('SELECT * FROM whatsapp_throughput_metrics WHERE phone_number_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR) ORDER BY created_at DESC');
    $stmt->execute([$phoneNumberId, $hours]);
    return $stmt->fetchAll();
}

/**
 * Handle throughput upgrade webhook
 */
function whatsappHandleThroughputUpgrade($db, $webhookData) {
    $value = $webhookData['value'] ?? [];
    $phoneNumberId = $value['phone_number_id'] ?? null;
    $event = $value['event'] ?? null;
    $maxDailyConversations = $value['max_daily_conversations_per_business'] ?? null;
    
    if ($event === 'THROUGHPUT_UPGRADE' && $phoneNumberId) {
        $stmt = $db->prepare('INSERT INTO whatsapp_throughput_upgrades (phone_number_id, event, max_daily_conversations, created_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$phoneNumberId, $event, $maxDailyConversations]);
        
        // Update phone number throughput level
        $stmt = $db->prepare('UPDATE whatsapp_phone_numbers SET throughput_level = "HIGH", max_throughput = 1000, updated_at = NOW() WHERE phone_number_id = ?');
        $stmt->execute([$phoneNumberId]);
        
        return true;
    }
    
    return false;
}

/**
 * Queue message if throughput limit reached
 */
function whatsappQueueMessage($db, $messageData) {
    $stmt = $db->prepare('INSERT INTO whatsapp_message_queue (phone_number_id, to_phone, message_type, message_data, priority, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $messageData['phone_number_id'],
        $messageData['to_phone'],
        $messageData['message_type'],
        json_encode($messageData['message_data']),
        $messageData['priority'] ?? 0
    ]);
    
    return $db->lastInsertId();
}

/**
 * Process queued messages when throughput available
 */
function whatsappProcessMessageQueue($db, $phoneNumberId, $limit = 10) {
    $monitor = whatsappMonitorMessageRate($db, $phoneNumberId);
    
    if (!$monitor['can_send']) {
        return ['processed' => 0, 'remaining' => 0];
    }
    
    $availableCapacity = min($monitor['available_capacity'], $limit);
    
    if ($availableCapacity <= 0) {
        return ['processed' => 0, 'remaining' => 0];
    }
    
    $stmt = $db->prepare('SELECT * FROM whatsapp_message_queue WHERE phone_number_id = ? AND status = "pending" ORDER BY priority DESC, created_at ASC LIMIT ?');
    $stmt->execute([$phoneNumberId, $availableCapacity]);
    $queued = $stmt->fetchAll();
    
    $processed = 0;
    foreach ($queued as $queuedMessage) {
        $messageData = json_decode($queuedMessage['message_data'], true);
        
        // Send message based on type
        require_once 'whatsapp_cloud.php';
        $result = null;
        
        if ($messageData['type'] === 'text') {
            $result = whatsappSendText($queuedMessage['to_phone'], $messageData['text'], $phoneNumberId);
        } elseif ($messageData['type'] === 'template') {
            $result = whatsappSendTemplate($queuedMessage['to_phone'], $messageData['template_name'], $messageData['language'], $messageData['components'] ?? [], $phoneNumberId);
        }
        
        if ($result && $result['success']) {
            $updateStmt = $db->prepare('UPDATE whatsapp_message_queue SET status = "sent", sent_at = NOW() WHERE id = ?');
            $updateStmt->execute([$queuedMessage['id']]);
            $processed++;
        } else {
            $updateStmt = $db->prepare('UPDATE whatsapp_message_queue SET status = "failed", error_message = ?, updated_at = NOW() WHERE id = ?');
            $updateStmt->execute([$result['message'] ?? 'Unknown error', $queuedMessage['id']]);
        }
    }
    
    $stmt = $db->prepare('SELECT COUNT(*) as remaining FROM whatsapp_message_queue WHERE phone_number_id = ? AND status = "pending"');
    $stmt->execute([$phoneNumberId]);
    $remaining = $stmt->fetch()['remaining'];
    
    return ['processed' => $processed, 'remaining' => $remaining];
}

?>

