<?php
require_once 'config.php';

/**
 * WhatsApp Link Preview
 * Extract and cache Open Graph meta tags for link previews
 */

/**
 * Extract link preview data from URL
 */
function whatsappExtractLinkPreview($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['success' => false, 'message' => 'Invalid URL'];
    }
    
    // Check cache first
    $db = getDB();
    $cacheKey = 'link_preview_' . md5($url);
    $stmt = $db->prepare('SELECT preview_data FROM link_preview_cache WHERE url_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)');
    $stmt->execute([md5($url)]);
    $cached = $stmt->fetch();
    
    if ($cached) {
        return ['success' => true, 'data' => json_decode($cached['preview_data'], true)];
    }
    
    // Fetch URL content
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'WhatsApp/2.22.20.72');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept-Language: en']);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode < 200 || $httpCode >= 300 || !$html) {
        return ['success' => false, 'message' => 'Failed to fetch URL'];
    }
    
    // Extract first 300KB for head section
    $headEnd = strpos($html, '</head>');
    if ($headEnd === false) {
        $headEnd = min(strlen($html), 300 * 1024);
    }
    $headSection = substr($html, 0, $headEnd);
    
    // Extract Open Graph tags
    $preview = [
        'title' => null,
        'description' => null,
        'url' => $url,
        'image' => null
    ];
    
    // Extract og:title
    if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\']([^"\']+)["\']/i', $headSection, $m)) {
        $preview['title'] = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }
    
    // Extract og:description
    if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\']([^"\']+)["\']/i', $headSection, $m)) {
        $preview['description'] = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }
    
    // Extract og:url
    if (preg_match('/<meta\s+property=["\']og:url["\']\s+content=["\']([^"\']+)["\']/i', $headSection, $m)) {
        $preview['url'] = $m[1];
    }
    
    // Extract og:image
    if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']+)["\']/i', $headSection, $m)) {
        $preview['image'] = $m[1];
    }
    
    // Cache the result
    if ($preview['title'] || $preview['description']) {
        $stmt = $db->prepare('INSERT INTO link_preview_cache (url_hash, url, preview_data, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE preview_data = ?, created_at = NOW()');
        $stmt->execute([md5($url), $url, json_encode($preview), json_encode($preview)]);
    }
    
    return ['success' => true, 'data' => $preview];
}

/**
 * Store link preview data in message
 */
function whatsappStoreLinkPreviewInMessage($db, $messageId, $previewData) {
    $stmt = $db->prepare('UPDATE whatsapp_messages SET link_preview_data = ? WHERE id = ?');
    $stmt->execute([json_encode($previewData), $messageId]);
}

/**
 * Get link preview from message
 */
function whatsappGetLinkPreviewFromMessage($db, $messageId) {
    $stmt = $db->prepare('SELECT link_preview_data FROM whatsapp_messages WHERE id = ?');
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();
    
    if ($message && $message['link_preview_data']) {
        return json_decode($message['link_preview_data'], true);
    }
    
    return null;
}

?>

