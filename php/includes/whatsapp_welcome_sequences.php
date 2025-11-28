<?php
require_once 'config.php';
require_once 'whatsapp_cloud.php';

/**
 * Welcome Message Sequences Management
 * Create, update, delete welcome message sequences for Click-to-WhatsApp ads
 */

/**
 * Get WABA ID from settings
 */
function whatsappWabaId() {
    $wabaId = getSetting('whatsapp_waba_id');
    if (!$wabaId) {
        // Fallback to whatsapp_business_account_id for backward compatibility
        $wabaId = getSetting('whatsapp_business_account_id');
    }
    return $wabaId;
}

/**
 * Create welcome message sequence
 */
function whatsappCreateWelcomeSequence($name, $sequenceData) {
    $wabaId = whatsappWabaId();
    $token = whatsappToken();
    
    if (!$wabaId || !$token) {
        return ['success' => false, 'message' => 'WhatsApp not configured'];
    }
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $wabaId . '/welcome_message_sequences';
    
    // Build multipart form data
    $boundary = uniqid();
    $body = '';
    
    $body .= '--' . $boundary . "\r\n";
    $body .= 'Content-Disposition: form-data; name="name"' . "\r\n\r\n";
    $body .= $name . "\r\n";
    
    $body .= '--' . $boundary . "\r\n";
    $body .= 'Content-Disposition: form-data; name="welcome_message_sequence"' . "\r\n\r\n";
    $body .= json_encode($sequenceData) . "\r\n";
    
    $body .= '--' . $boundary . '--';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: multipart/form-data; boundary=' . $boundary
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300 && isset($data['sequence_id'])) {
        return [
            'success' => true,
            'sequence_id' => $data['sequence_id'],
            'data' => $data
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to create sequence', 'data' => $data];
}

/**
 * Update welcome message sequence
 */
function whatsappUpdateWelcomeSequence($sequenceId, $name = null, $sequenceData = null) {
    $wabaId = whatsappWabaId();
    $token = whatsappToken();
    
    if (!$wabaId || !$token) {
        return ['success' => false, 'message' => 'WhatsApp not configured'];
    }
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $wabaId . '/welcome_message_sequences';
    
    // Build multipart form data
    $boundary = uniqid();
    $body = '';
    
    $body .= '--' . $boundary . "\r\n";
    $body .= 'Content-Disposition: form-data; name="sequence_id"' . "\r\n\r\n";
    $body .= $sequenceId . "\r\n";
    
    if ($name !== null) {
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="name"' . "\r\n\r\n";
        $body .= $name . "\r\n";
    }
    
    if ($sequenceData !== null) {
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="welcome_message_sequence"' . "\r\n\r\n";
        $body .= json_encode($sequenceData) . "\r\n";
    }
    
    $body .= '--' . $boundary . '--';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: multipart/form-data; boundary=' . $boundary
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $data];
    }
    
    return ['success' => false, 'message' => 'Failed to update sequence', 'data' => $data];
}

/**
 * Get welcome message sequences
 */
function whatsappGetWelcomeSequences($sequenceId = null) {
    $wabaId = whatsappWabaId();
    $token = whatsappToken();
    
    if (!$wabaId || !$token) {
        return ['success' => false, 'message' => 'WhatsApp not configured'];
    }
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $wabaId . '/welcome_message_sequences';
    if ($sequenceId) {
        $url .= '?id=' . urlencode($sequenceId);
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $data];
    }
    
    return ['success' => false, 'message' => 'Failed to get sequences', 'data' => $data];
}

/**
 * Delete welcome message sequence
 */
function whatsappDeleteWelcomeSequence($sequenceId) {
    $wabaId = whatsappWabaId();
    $token = whatsappToken();
    
    if (!$wabaId || !$token) {
        return ['success' => false, 'message' => 'WhatsApp not configured'];
    }
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $wabaId . '/welcome_message_sequences';
    
    // Build multipart form data
    $boundary = uniqid();
    $body = '';
    
    $body .= '--' . $boundary . "\r\n";
    $body .= 'Content-Disposition: form-data; name="sequence_id"' . "\r\n\r\n";
    $body .= $sequenceId . "\r\n";
    
    $body .= '--' . $boundary . "\r\n";
    $body .= 'Content-Disposition: form-data; name="delete"' . "\r\n\r\n";
    $body .= 'true' . "\r\n";
    
    $body .= '--' . $boundary . '--';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: multipart/form-data; boundary=' . $boundary
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $data];
    }
    
    return ['success' => false, 'message' => 'Failed to delete sequence', 'data' => $data];
}

/**
 * Build welcome message sequence data
 */
function whatsappBuildWelcomeSequence($text, $autofillMessage = null, $iceBreakers = []) {
    $sequence = [
        'text' => $text
    ];
    
    if ($autofillMessage) {
        $sequence['autofill_message'] = [
            'content' => $autofillMessage
        ];
    }
    
    if (!empty($iceBreakers)) {
        $sequence['ice_breakers'] = array_map(function($breaker) {
            return ['title' => $breaker];
        }, $iceBreakers);
    }
    
    return $sequence;
}

/**
 * Store welcome sequence in database
 */
function whatsappStoreWelcomeSequence($db, $sequenceData) {
    $stmt = $db->prepare('INSERT INTO whatsapp_welcome_sequences (sequence_id, name, text, autofill_message, ice_breakers_json, created_at) VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE name = ?, text = ?, autofill_message = ?, ice_breakers_json = ?, updated_at = NOW()');
    
    $stmt->execute([
        $sequenceData['sequence_id'],
        $sequenceData['name'],
        $sequenceData['text'],
        $sequenceData['autofill_message'] ?? null,
        json_encode($sequenceData['ice_breakers'] ?? []),
        $sequenceData['name'],
        $sequenceData['text'],
        $sequenceData['autofill_message'] ?? null,
        json_encode($sequenceData['ice_breakers'] ?? [])
    ]);
    
    return $db->lastInsertId();
}

?>
