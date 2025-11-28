<?php
require_once 'config.php';
require_once 'whatsapp_cloud.php';

/**
 * Enhanced WhatsApp Template Management
 * Supports named/positional parameters, template review, status tracking
 */

/**
 * Create template via Meta API
 */
function whatsappCreateTemplate($name, $category, $language, $components, $parameterFormat = 'positional') {
    $wabaId = whatsappWabaId();
    $token = whatsappToken();
    
    if (!$wabaId || !$token) {
        return ['success' => false, 'message' => 'WhatsApp not configured'];
    }
    
    // Validate template name
    if (!preg_match('/^[a-z0-9_]+$/', $name) || strlen($name) > 512) {
        return ['success' => false, 'message' => 'Invalid template name'];
    }
    
    // Validate category
    if (!in_array(strtolower($category), ['authentication', 'marketing', 'utility'])) {
        return ['success' => false, 'message' => 'Invalid category'];
    }
    
    // Validate parameter format
    if (!in_array($parameterFormat, ['named', 'positional'])) {
        $parameterFormat = 'positional';
    }
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $wabaId . '/message_templates';
    
    $payload = [
        'name' => $name,
        'category' => strtolower($category),
        'language' => $language,
        'parameter_format' => $parameterFormat,
        'components' => $components
    ];
    
    $result = whatsappPost($url, $payload);
    
    if ($result['success'] && isset($result['data']['id'])) {
        return [
            'success' => true,
            'template_id' => $result['data']['id'],
            'status' => $result['data']['status'] ?? 'PENDING',
            'data' => $result['data']
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to create template', 'data' => $result['data'] ?? []];
}

/**
 * Get template status
 */
function whatsappGetTemplateStatus($templateId) {
    $wabaId = whatsappWabaId();
    $token = whatsappToken();
    
    if (!$wabaId || !$token) {
        return ['success' => false, 'message' => 'WhatsApp not configured'];
    }
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $templateId;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300 && isset($data['status'])) {
        return [
            'success' => true,
            'status' => $data['status'],
            'quality_rating' => $data['quality_rating'] ?? null,
            'rejected_reason' => $data['rejected_reason'] ?? null,
            'data' => $data
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to get template status', 'data' => $data];
}

/**
 * Delete template
 */
function whatsappDeleteTemplate($templateId) {
    $token = whatsappToken();
    
    if (!$token) {
        return ['success' => false, 'message' => 'WhatsApp not configured'];
    }
    
    $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $templateId;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true];
    }
    
    $data = json_decode($response, true);
    return ['success' => false, 'message' => 'Failed to delete template', 'data' => $data];
}

/**
 * Build template components with named parameters
 */
function whatsappBuildTemplateComponentsNamed($bodyText, $header = null, $footer = null, $buttons = [], $exampleParams = []) {
    $components = [];
    
    // Header
    if ($header) {
        if (isset($header['type']) && in_array($header['type'], ['TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT'])) {
            $headerComponent = ['type' => 'HEADER'];
            
            if ($header['type'] === 'TEXT') {
                $headerComponent['format'] = 'TEXT';
                $headerComponent['text'] = $header['text'];
                if (isset($header['example'])) {
                    $headerComponent['example'] = ['header_text' => [$header['example']]];
                }
            } else {
                $headerComponent['format'] = $header['type'];
                if (isset($header['example'])) {
                    $headerComponent['example'] = ['header_handle' => [$header['example']]];
                }
            }
            
            $components[] = $headerComponent;
        }
    }
    
    // Body
    if ($bodyText) {
        $bodyComponent = [
            'type' => 'BODY',
            'text' => $bodyText
        ];
        
        if (!empty($exampleParams)) {
            $bodyComponent['example'] = [
                'body_text_named_params' => array_map(function($param) {
                    return [
                        'param_name' => $param['name'],
                        'example' => $param['example']
                    ];
                }, $exampleParams)
            ];
        }
        
        $components[] = $bodyComponent;
    }
    
    // Footer
    if ($footer) {
        $components[] = [
            'type' => 'FOOTER',
            'text' => $footer
        ];
    }
    
    // Buttons
    foreach ($buttons as $button) {
        $buttonComponent = ['type' => 'BUTTONS'];
        
        if (isset($button['type'])) {
            if ($button['type'] === 'QUICK_REPLY') {
                $buttonComponent['buttons'][] = [
                    'type' => 'QUICK_REPLY',
                    'text' => $button['text']
                ];
            } elseif ($button['type'] === 'URL') {
                $buttonComponent['buttons'][] = [
                    'type' => 'URL',
                    'text' => $button['text'],
                    'url' => $button['url']
                ];
            } elseif ($button['type'] === 'PHONE_NUMBER') {
                $buttonComponent['buttons'][] = [
                    'type' => 'PHONE_NUMBER',
                    'text' => $button['text'],
                    'phone_number' => $button['phone_number']
                ];
            }
        }
        
        if (!empty($buttonComponent['buttons'])) {
            $components[] = $buttonComponent;
        }
    }
    
    return $components;
}

/**
 * Build template components with positional parameters
 */
function whatsappBuildTemplateComponentsPositional($bodyText, $header = null, $footer = null, $buttons = [], $exampleParams = []) {
    $components = [];
    
    // Header
    if ($header) {
        if (isset($header['type']) && in_array($header['type'], ['TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT'])) {
            $headerComponent = ['type' => 'HEADER'];
            
            if ($header['type'] === 'TEXT') {
                $headerComponent['format'] = 'TEXT';
                $headerComponent['text'] = $header['text'];
                if (isset($header['example'])) {
                    $headerComponent['example'] = ['header_text' => [$header['example']]];
                }
            } else {
                $headerComponent['format'] = $header['type'];
                if (isset($header['example'])) {
                    $headerComponent['example'] = ['header_handle' => [$header['example']]];
                }
            }
            
            $components[] = $headerComponent;
        }
    }
    
    // Body
    if ($bodyText) {
        $bodyComponent = [
            'type' => 'BODY',
            'text' => $bodyText
        ];
        
        if (!empty($exampleParams)) {
            $bodyComponent['example'] = [
                'body_text' => [array_map(function($param) {
                    return $param['example'];
                }, $exampleParams)]
            ];
        }
        
        $components[] = $bodyComponent;
    }
    
    // Footer
    if ($footer) {
        $components[] = [
            'type' => 'FOOTER',
            'text' => $footer
        ];
    }
    
    // Buttons
    foreach ($buttons as $button) {
        $buttonComponent = ['type' => 'BUTTONS'];
        
        if (isset($button['type'])) {
            if ($button['type'] === 'QUICK_REPLY') {
                $buttonComponent['buttons'][] = [
                    'type' => 'QUICK_REPLY',
                    'text' => $button['text']
                ];
            } elseif ($button['type'] === 'URL') {
                $buttonComponent['buttons'][] = [
                    'type' => 'URL',
                    'text' => $button['text'],
                    'url' => $button['url']
                ];
            } elseif ($button['type'] === 'PHONE_NUMBER') {
                $buttonComponent['buttons'][] = [
                    'type' => 'PHONE_NUMBER',
                    'text' => $button['text'],
                    'phone_number' => $button['phone_number']
                ];
            }
        }
        
        if (!empty($buttonComponent['buttons'])) {
            $components[] = $buttonComponent;
        }
    }
    
    return $components;
}

/**
 * Store template in database
 */
function whatsappStoreTemplate($db, $templateData) {
    $stmt = $db->prepare('INSERT INTO whatsapp_templates (name, category, language, parameter_format, components_json, meta_template_id, status, quality_rating, rejected_reason, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE status = ?, quality_rating = ?, rejected_reason = ?, updated_at = NOW()');
    
    $stmt->execute([
        $templateData['name'],
        $templateData['category'],
        $templateData['language'],
        $templateData['parameter_format'] ?? 'positional',
        json_encode($templateData['components'] ?? []),
        $templateData['meta_template_id'] ?? null,
        $templateData['status'] ?? 'PENDING',
        $templateData['quality_rating'] ?? null,
        $templateData['rejected_reason'] ?? null,
        $templateData['status'] ?? 'PENDING',
        $templateData['quality_rating'] ?? null,
        $templateData['rejected_reason'] ?? null
    ]);
    
    return $db->lastInsertId();
}

/**
 * Update template status from Meta webhook
 */
function whatsappUpdateTemplateStatus($db, $templateId, $status, $qualityRating = null, $rejectedReason = null) {
    $stmt = $db->prepare('UPDATE whatsapp_templates SET status = ?, quality_rating = ?, rejected_reason = ?, updated_at = NOW() WHERE meta_template_id = ?');
    $stmt->execute([$status, $qualityRating, $rejectedReason, $templateId]);
    return $stmt->rowCount() > 0;
}

/**
 * Send template with named parameters
 */
function whatsappSendTemplateNamed($to, $templateName, $language, $parameters, $phoneNumberIdOverride = null) {
    $components = [];
    
    foreach ($parameters as $param) {
        $components[] = [
            'type' => $param['component_type'] ?? 'body',
            'parameters' => array_map(function($p) {
                $paramData = [
                    'type' => $p['type'],
                    'parameter_name' => $p['name']
                ];
                
                if ($p['type'] === 'text') {
                    $paramData['text'] = $p['value'];
                } elseif ($p['type'] === 'currency') {
                    $paramData['currency'] = $p['currency'];
                    $paramData['amount_1000'] = $p['amount_1000'];
                } elseif ($p['type'] === 'date_time') {
                    $paramData['date_time'] = $p['date_time'];
                } elseif (in_array($p['type'], ['image', 'video', 'document'])) {
                    $paramData[$p['type']] = ['id' => $p['media_id']];
                }
                
                return $paramData;
            }, $param['params'])
        ];
    }
    
    return whatsappSendTemplate($to, $templateName, $language, $components, $phoneNumberIdOverride);
}

/**
 * Send template with positional parameters
 */
function whatsappSendTemplatePositional($to, $templateName, $language, $parameters, $phoneNumberIdOverride = null) {
    $components = [];
    
    foreach ($parameters as $param) {
        $components[] = [
            'type' => $param['component_type'] ?? 'body',
            'parameters' => array_map(function($p) {
                $paramData = ['type' => $p['type']];
                
                if ($p['type'] === 'text') {
                    $paramData['text'] = $p['value'];
                } elseif ($p['type'] === 'currency') {
                    $paramData['currency'] = $p['currency'];
                    $paramData['amount_1000'] = $p['amount_1000'];
                } elseif ($p['type'] === 'date_time') {
                    $paramData['date_time'] = $p['date_time'];
                } elseif (in_array($p['type'], ['image', 'video', 'document'])) {
                    $paramData[$p['type']] = ['id' => $p['media_id']];
                }
                
                return $paramData;
            }, $param['params'])
        ];
    }
    
    return whatsappSendTemplate($to, $templateName, $language, $components, $phoneNumberIdOverride);
}

?>

