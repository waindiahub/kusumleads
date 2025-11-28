<?php
require_once 'whatsapp_cloud.php';

/**
 * Enhanced WhatsApp Message Types
 * Address, Audio, Contacts, Sticker, Reaction messages
 */

/**
 * Send address message
 */
function whatsappSendAddressMessage($to, $addressData, $phoneNumberIdOverride = null) {
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
        'type' => 'address',
        'address' => [
            'street' => substr($addressData['street'] ?? '', 0, 200),
            'city' => substr($addressData['city'] ?? '', 0, 200),
            'state' => substr($addressData['state'] ?? '', 0, 200),
            'zip' => substr($addressData['zip'] ?? '', 0, 20),
            'country' => substr($addressData['country'] ?? '', 0, 200),
            'country_code' => substr($addressData['country_code'] ?? '', 0, 2)
        ]
    ];
    
    return whatsappPost($url, $payload);
}

/**
 * Send audio message
 */
function whatsappSendAudioMessage($to, $audioUrl, $phoneNumberIdOverride = null) {
    return whatsappSendMedia($to, 'audio', $audioUrl, null, null, $phoneNumberIdOverride);
}

/**
 * Send contacts message
 */
function whatsappSendContactsMessage($to, $contacts, $phoneNumberIdOverride = null) {
    require_once 'whatsapp_advanced.php';
    return whatsappSendContactMessage($to, $contacts, $phoneNumberIdOverride);
}

/**
 * Send sticker message
 */
function whatsappSendStickerMessage($to, $stickerUrl, $phoneNumberIdOverride = null) {
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
        'type' => 'sticker',
        'sticker' => [
            'link' => $stickerUrl
        ]
    ];
    
    return whatsappPost($url, $payload);
}

/**
 * Send reaction message
 */
function whatsappSendReactionMessage($to, $messageId, $emoji, $phoneNumberIdOverride = null) {
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
        'type' => 'reaction',
        'reaction' => [
            'message_id' => $messageId,
            'emoji' => $emoji
        ]
    ];
    
    return whatsappPost($url, $payload);
}

?>

