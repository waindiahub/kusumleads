<?php
require_once 'config.php';
require_once 'whatsapp_cloud.php';

/**
 * WhatsApp Analytics API
 * Complete implementation of messaging, conversation, pricing, and template analytics
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
 * Build analytics URL with filters
 */
function whatsappBuildAnalyticsUrl($wabaId, $field, $filters) {
    $baseUrl = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $wabaId;
    $fields = $field;
    
    // Add filters
    if (isset($filters['start'])) {
        $fields .= '.start(' . $filters['start'] . ')';
    }
    if (isset($filters['end'])) {
        $fields .= '.end(' . $filters['end'] . ')';
    }
    if (isset($filters['granularity'])) {
        $fields .= '.granularity(' . $filters['granularity'] . ')';
    }
    if (isset($filters['phone_numbers']) && !empty($filters['phone_numbers'])) {
        $fields .= '.phone_numbers(' . json_encode($filters['phone_numbers']) . ')';
    }
    if (isset($filters['product_types']) && !empty($filters['product_types'])) {
        $fields .= '.product_types(' . json_encode($filters['product_types']) . ')';
    }
    if (isset($filters['country_codes']) && !empty($filters['country_codes'])) {
        $fields .= '.country_codes(' . json_encode($filters['country_codes']) . ')';
    }
    if (isset($filters['metric_types']) && !empty($filters['metric_types'])) {
        $fields .= '.metric_types(' . json_encode($filters['metric_types']) . ')';
    }
    if (isset($filters['conversation_categories']) && !empty($filters['conversation_categories'])) {
        $fields .= '.conversation_categories(' . json_encode($filters['conversation_categories']) . ')';
    }
    if (isset($filters['conversation_types']) && !empty($filters['conversation_types'])) {
        $fields .= '.conversation_types(' . json_encode($filters['conversation_types']) . ')';
    }
    if (isset($filters['conversation_directions']) && !empty($filters['conversation_directions'])) {
        $fields .= '.conversation_directions(' . json_encode($filters['conversation_directions']) . ')';
    }
    if (isset($filters['dimensions']) && !empty($filters['dimensions'])) {
        $fields .= '.dimensions(' . json_encode($filters['dimensions']) . ')';
    }
    if (isset($filters['template_ids']) && !empty($filters['template_ids'])) {
        $fields .= '.template_ids(' . json_encode($filters['template_ids']) . ')';
    }
    if (isset($filters['template_group_ids']) && !empty($filters['template_group_ids'])) {
        $fields .= '.template_group_ids(' . json_encode($filters['template_group_ids']) . ')';
    }
    
    return $baseUrl . '?fields=' . urlencode($fields);
}

/**
 * Get messaging analytics
 */
function whatsappGetMessagingAnalytics($startTime, $endTime, $granularity = 'DAY', $phoneNumbers = [], $productTypes = [], $countryCodes = []) {
    $wabaId = whatsappWabaId();
    $token = whatsappToken();
    
    if (!$wabaId || !$token) {
        return ['success' => false, 'message' => 'WhatsApp not configured'];
    }
    
    $filters = [
        'start' => $startTime,
        'end' => $endTime,
        'granularity' => $granularity
    ];
    
    if (!empty($phoneNumbers)) {
        $filters['phone_numbers'] = $phoneNumbers;
    }
    if (!empty($productTypes)) {
        $filters['product_types'] = $productTypes;
    }
    if (!empty($countryCodes)) {
        $filters['country_codes'] = $countryCodes;
    }
    
    $url = whatsappBuildAnalyticsUrl($wabaId, 'analytics', $filters);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300 && isset($data['analytics'])) {
        return ['success' => true, 'data' => $data['analytics']];
    }
    
    return ['success' => false, 'message' => 'Failed to get analytics', 'data' => $data];
}

/**
 * Get conversation analytics
 */
function whatsappGetConversationAnalytics($startTime, $endTime, $granularity = 'DAILY', $phoneNumbers = [], $metricTypes = [], $conversationCategories = [], $conversationTypes = [], $conversationDirections = [], $dimensions = [], $countryCodes = []) {
    $wabaId = whatsappWabaId();
    $token = whatsappToken();
    
    if (!$wabaId || !$token) {
        return ['success' => false, 'message' => 'WhatsApp not configured'];
    }
    
    $filters = [
        'start' => $startTime,
        'end' => $endTime,
        'granularity' => $granularity
    ];
    
    if (!empty($phoneNumbers)) {
        $filters['phone_numbers'] = $phoneNumbers;
    }
    if (!empty($metricTypes)) {
        $filters['metric_types'] = $metricTypes;
    }
    if (!empty($conversationCategories)) {
        $filters['conversation_categories'] = $conversationCategories;
    }
    if (!empty($conversationTypes)) {
        $filters['conversation_types'] = $conversationTypes;
    }
    if (!empty($conversationDirections)) {
        $filters['conversation_directions'] = $conversationDirections;
    }
    if (!empty($dimensions)) {
        $filters['dimensions'] = $dimensions;
    }
    if (!empty($countryCodes)) {
        $filters['country_codes'] = $countryCodes;
    }
    
    $url = whatsappBuildAnalyticsUrl($wabaId, 'conversation_analytics', $filters);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300 && isset($data['conversation_analytics'])) {
        return ['success' => true, 'data' => $data['conversation_analytics']];
    }
    
    return ['success' => false, 'message' => 'Failed to get conversation analytics', 'data' => $data];
}

/**
 * Get pricing analytics
 */
function whatsappGetPricingAnalytics($startTime, $endTime, $granularity = 'DAILY', $phoneNumbers = [], $dimensions = [], $countryCodes = []) {
    $wabaId = whatsappWabaId();
    $token = whatsappToken();
    
    if (!$wabaId || !$token) {
        return ['success' => false, 'message' => 'WhatsApp not configured'];
    }
    
    $filters = [
        'start' => $startTime,
        'end' => $endTime,
        'granularity' => $granularity
    ];
    
    if (!empty($phoneNumbers)) {
        $filters['phone_numbers'] = $phoneNumbers;
    }
    if (!empty($dimensions)) {
        $filters['dimensions'] = $dimensions;
    }
    if (!empty($countryCodes)) {
        $filters['country_codes'] = $countryCodes;
    }
    
    $url = whatsappBuildAnalyticsUrl($wabaId, 'pricing_analytics', $filters);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300 && isset($data['pricing_analytics'])) {
        return ['success' => true, 'data' => $data['pricing_analytics']];
    }
    
    return ['success' => false, 'message' => 'Failed to get pricing analytics', 'data' => $data];
}

/**
 * Get template analytics
 */
function whatsappGetTemplateAnalytics($startTime, $endTime, $granularity = 'DAILY', $phoneNumbers = [], $templateIds = []) {
    $wabaId = whatsappWabaId();
    $token = whatsappToken();
    
    if (!$wabaId || !$token) {
        return ['success' => false, 'message' => 'WhatsApp not configured'];
    }
    
    $filters = [
        'start' => $startTime,
        'end' => $endTime,
        'granularity' => $granularity
    ];
    
    if (!empty($phoneNumbers)) {
        $filters['phone_numbers'] = $phoneNumbers;
    }
    if (!empty($templateIds)) {
        $filters['template_ids'] = $templateIds;
    }
    
    $url = whatsappBuildAnalyticsUrl($wabaId, 'template_analytics', $filters);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300 && isset($data['template_analytics'])) {
        return ['success' => true, 'data' => $data['template_analytics']];
    }
    
    return ['success' => false, 'message' => 'Failed to get template analytics', 'data' => $data];
}

/**
 * Get template group analytics
 */
function whatsappGetTemplateGroupAnalytics($startTime, $endTime, $granularity = 'DAILY', $phoneNumbers = [], $templateGroupIds = []) {
    $wabaId = whatsappWabaId();
    $token = whatsappToken();
    
    if (!$wabaId || !$token) {
        return ['success' => false, 'message' => 'WhatsApp not configured'];
    }
    
    $filters = [
        'start' => $startTime,
        'end' => $endTime,
        'granularity' => $granularity
    ];
    
    if (!empty($phoneNumbers)) {
        $filters['phone_numbers'] = $phoneNumbers;
    }
    if (!empty($templateGroupIds)) {
        $filters['template_group_ids'] = $templateGroupIds;
    }
    
    $url = whatsappBuildAnalyticsUrl($wabaId, 'template_group_analytics', $filters);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300 && isset($data['template_group_analytics'])) {
        return ['success' => true, 'data' => $data['template_group_analytics']];
    }
    
    return ['success' => false, 'message' => 'Failed to get template group analytics', 'data' => $data];
}

/**
 * Cache analytics data
 */
function whatsappCacheAnalytics($db, $analyticsType, $filters, $data) {
    $wabaId = whatsappWabaId();
    $phoneNumberId = $filters['phone_numbers'][0] ?? null;
    
    $stmt = $db->prepare('INSERT INTO whatsapp_analytics (waba_id, phone_number_id, analytics_type, start_time, end_time, granularity, data_points, filters_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE data_points = ?, created_at = NOW()');
    
    $stmt->execute([
        $wabaId,
        $phoneNumberId,
        $analyticsType,
        $filters['start'],
        $filters['end'],
        $filters['granularity'],
        json_encode($data),
        json_encode($filters),
        json_encode($data)
    ]);
}

/**
 * Get cached analytics
 */
function whatsappGetCachedAnalytics($db, $analyticsType, $filters) {
    $wabaId = whatsappWabaId();
    $phoneNumberId = $filters['phone_numbers'][0] ?? null;
    
    $stmt = $db->prepare('SELECT data_points FROM whatsapp_analytics WHERE waba_id = ? AND analytics_type = ? AND start_time = ? AND end_time = ? AND granularity = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) LIMIT 1');
    $stmt->execute([
        $wabaId,
        $analyticsType,
        $filters['start'],
        $filters['end'],
        $filters['granularity']
    ]);
    
    $row = $stmt->fetch();
    if ($row) {
        return json_decode($row['data_points'], true);
    }
    
    return null;
}

?>
