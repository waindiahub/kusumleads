<?php
require_once 'whatsapp_cloud.php';
require_once 'r2_client.php';

/**
 * Enhanced Media Management
 * Upload, download, delete media with proper handling
 */

/**
 * Upload media to WhatsApp
 */
function whatsappUploadMedia($filePath, $mimeType, $phoneNumberIdOverride = null) {
    $token = whatsappToken();
    $pnId = $phoneNumberIdOverride ?: whatsappPhoneNumberId();
    
    if (!$token || !$pnId) {
        return ['success' => false, 'message' => 'WhatsApp not configured'];
    }
    
    if (!file_exists($filePath)) {
        return ['success' => false, 'message' => 'File not found'];
    }
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $pnId . '/media';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token
    ]);
    
    $cfile = new CURLFile($filePath, $mimeType, basename($filePath));
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'messaging_product' => 'whatsapp',
        'type' => whatsappGetMediaTypeFromMime($mimeType),
        'file' => $cfile
    ]);
    
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($resp, true);
    
    if ($code >= 200 && $code < 300 && isset($data['id'])) {
        return ['success' => true, 'media_id' => $data['id']];
    }
    
    return ['success' => false, 'message' => 'Upload failed', 'data' => $data];
}

/**
 * Get media type from MIME type
 */
function whatsappGetMediaTypeFromMime($mimeType) {
    if (strpos($mimeType, 'image/') === 0) return 'image';
    if (strpos($mimeType, 'video/') === 0) return 'video';
    if (strpos($mimeType, 'audio/') === 0) return 'audio';
    return 'document';
}

/**
 * Get media URL from media ID
 */
function whatsappGetMediaUrl($mediaId, $phoneNumberIdOverride = null) {
    $token = whatsappToken();
    $pnId = $phoneNumberIdOverride ?: whatsappPhoneNumberId();
    
    if (!$token || !$mediaId) {
        return ['success' => false, 'message' => 'Missing token or media id'];
    }
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $mediaId;
    if ($pnId) {
        $url .= '?phone_number_id=' . $pnId;
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($resp, true);
    
    if ($code >= 200 && $code < 300 && isset($data['url'])) {
        return [
            'success' => true,
            'url' => $data['url'],
            'mime_type' => $data['mime_type'] ?? null,
            'sha256' => $data['sha256'] ?? null,
            'file_size' => $data['file_size'] ?? null
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to get media URL', 'data' => $data];
}

/**
 * Delete media
 */
function whatsappDeleteMedia($mediaId, $phoneNumberIdOverride = null) {
    $token = whatsappToken();
    $pnId = $phoneNumberIdOverride ?: whatsappPhoneNumberId();
    
    if (!$token || !$mediaId) {
        return ['success' => false, 'message' => 'Missing token or media id'];
    }
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $mediaId;
    if ($pnId) {
        $url .= '?phone_number_id=' . $pnId;
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($resp, true);
    
    if ($code >= 200 && $code < 300) {
        return ['success' => true];
    }
    
    return ['success' => false, 'message' => 'Failed to delete media', 'data' => $data];
}

/**
 * Download media and store in R2
 */
function whatsappDownloadAndStoreMedia($mediaId, $phoneNumberIdOverride = null) {
    $mediaInfo = whatsappGetMediaUrl($mediaId, $phoneNumberIdOverride);
    
    if (!$mediaInfo['success']) {
        return $mediaInfo;
    }
    
    if (!r2IsConfigured()) {
        return ['success' => false, 'message' => 'R2 not configured'];
    }
    
    // Download media
    $ch = curl_init($mediaInfo['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . whatsappToken()]);
    $mediaData = curl_exec($ch);
    $downloadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($downloadCode < 200 || $downloadCode >= 300 || $mediaData === false) {
        return ['success' => false, 'message' => 'Failed to download media'];
    }
    
    $mimeType = $mediaInfo['mime_type'] ?? 'application/octet-stream';
    $ext = r2GuessExtension($mimeType);
    $key = sprintf('whatsapp/media/%s/%s.%s', date('Y/m/d'), $mediaId, $ext);
    
    try {
        $r2Result = r2Upload($key, $mediaData, $mimeType);
        return [
            'success' => true,
            'key' => $r2Result['key'],
            'url' => $r2Result['url'],
            'mime_type' => $mimeType,
            'file_size' => strlen($mediaData)
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

?>

