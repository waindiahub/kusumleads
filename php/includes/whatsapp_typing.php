<?php
require_once 'whatsapp_cloud.php';

/**
 * WhatsApp Typing Indicators
 * Send typing indicator to show user that agent is preparing a response
 */

function whatsappSendTypingIndicator($messageId, $phoneNumberIdOverride = null) {
    $token = whatsappToken();
    $pnId = $phoneNumberIdOverride ?: whatsappPhoneNumberId();
    
    if (!$token || !$pnId) {
        return ['success' => false, 'message' => 'WhatsApp not configured'];
    }
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $pnId . '/messages';
    $payload = [
        'messaging_product' => 'whatsapp',
        'status' => 'read',
        'message_id' => $messageId,
        'typing_indicator' => [
            'type' => 'text'
        ]
    ];
    
    return whatsappPost($url, $payload);
}

function whatsappMarkMessageAsReadWithTyping($db, $messageId, $conversationId) {
    // First mark as read
    require_once 'whatsapp_advanced.php';
    whatsappMarkMessageAsRead($db, $messageId, $conversationId);
    
    // Then send typing indicator
    $stmt = $db->prepare('SELECT wa_message_id FROM whatsapp_messages WHERE id = ?');
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();
    
    if ($message && $message['wa_message_id']) {
        whatsappSendTypingIndicator($message['wa_message_id']);
    }
}

?>

