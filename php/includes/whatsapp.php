<?php
require_once 'config.php';
require_once 'jwt_helper.php';
require_once 'whatsapp_cloud.php';
require_once 'pusher.php';
require_once 'campaigns.php';
require_once 'r2_client.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($method === 'GET' && strpos($path, '/whatsapp/webhook') !== false) {
    verifyMetaWebhook();
} elseif ($method === 'POST' && strpos($path, '/whatsapp/webhook') !== false) {
    handleMetaWebhook();
} elseif ($method === 'POST' && strpos($path, '/whatsapp/send') !== false) {
    whatsappSendEndpoint();
} elseif ($method === 'GET' && strpos($path, '/whatsapp/session/check') !== false) {
    whatsappSessionCheckEndpoint();
    } elseif (strpos($path, '/whatsapp/media') !== false) {
        handleMediaRoutes($method, $path);
} elseif (strpos($path, '/whatsapp/campaigns') !== false) {
    handleCampaignRoutes($method, $path);
} elseif (strpos($path, '/whatsapp/flows') !== false) {
    handleFlowRoutes($method, $path);
} elseif ($method === 'POST' && strpos($path, '/r2/presign-put') !== false) {
    r2PresignPutEndpoint();
} elseif ($method === 'GET' && strpos($path, '/r2/presign-get') !== false) {
    r2PresignGetEndpoint();
} elseif ($method === 'GET' && strpos($path, '/r2/public-url') !== false) {
    r2PublicUrlEndpoint();
} elseif ($method === 'GET' && strpos($path, '/whatsapp/templates') !== false) {
    if (strpos($path, '/whatsapp/templates/sync') !== false) {
        syncWhatsAppTemplateCategories();
    } else {
        listWhatsAppTemplates();
    }
} elseif ($method === 'GET' && strpos($path, '/whatsapp/conversations/') !== false) {
    if (preg_match('/\/whatsapp\/conversations\/(\d+)\/messages/', $path, $m)) {
        getConversationMessages((int)$m[1]);
    } else {
        sendResponse(false, 'Invalid endpoint');
    }
} elseif ($method === 'GET' && strpos($path, '/whatsapp/conversations') !== false) {
    if (isset($_GET['lead_id'])) {
        $db = getDB();
        $st = $db->prepare('SELECT * FROM whatsapp_conversations WHERE lead_id = ? ORDER BY last_message_at DESC');
        $st->execute([(int)$_GET['lead_id']]);
        sendResponse(true, 'OK', $st->fetchAll());
    } else {
        getConversations();
    }
} elseif ($method === 'POST' && preg_match('/\/whatsapp\/conversations\/(\d+)\/messages\/(\w+)\/read$/', $path, $m)) {
    markMessageAsRead((int)$m[1], $m[2]);
} elseif ($method === 'POST' && strpos($path, '/whatsapp/followups/process') !== false) {
    $token = validateJWT();
    if (!$token || $token['role'] !== 'admin') sendResponse(false, 'Admin required');
    require_once 'followups.php';
    $db = getDB();
    scheduleAbandonedFollowUps($db);
    $res = processFollowUps($db);
    sendResponse(true, 'Processed', $res);
} elseif (strpos($path, '/whatsapp/calls') !== false) {
    handleCallRoutes($method, $path);
} else {
    sendResponse(false, 'Invalid endpoint');
}

function handleCallRoutes($method, $path) {
    require_once 'whatsapp_calling.php';
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($method === 'POST' && preg_match('/\/whatsapp\/calls\/initiate$/', $path)) {
        // Initiate business call
        $to = $input['to'] ?? null;
        $sdpOffer = $input['sdp_offer'] ?? null;
        $phoneNumberId = $input['phone_number_id'] ?? null;
        $bizOpaqueCallbackData = $input['biz_opaque_callback_data'] ?? null;
        
        if (!$to || !$sdpOffer) sendResponse(false, 'to and sdp_offer required');
        
        $result = whatsappInitiateCall($to, $sdpOffer, $phoneNumberId, $bizOpaqueCallbackData);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/calls\/([^\/]+)\/accept$/', $path, $m)) {
        // Accept user call
        $callId = $m[1];
        $sdpAnswer = $input['sdp_answer'] ?? null;
        $phoneNumberId = $input['phone_number_id'] ?? null;
        $bizOpaqueCallbackData = $input['biz_opaque_callback_data'] ?? null;
        
        if (!$sdpAnswer) sendResponse(false, 'sdp_answer required');
        
        // Check if agent is assigned to this call
        if ($token['role'] === 'agent') {
            $stmt = $db->prepare('SELECT assigned_agent_id FROM whatsapp_calls WHERE call_id = ?');
            $stmt->execute([$callId]);
            $call = $stmt->fetch();
            if (!$call || (int)$call['assigned_agent_id'] !== (int)$token['user_id']) {
                sendResponse(false, 'Access denied');
            }
        }
        
        $result = whatsappAcceptCall($callId, $sdpAnswer, $phoneNumberId, $bizOpaqueCallbackData);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/calls\/([^\/]+)\/pre_accept$/', $path, $m)) {
        // Pre-accept user call
        $callId = $m[1];
        $sdpAnswer = $input['sdp_answer'] ?? null;
        $phoneNumberId = $input['phone_number_id'] ?? null;
        
        if (!$sdpAnswer) sendResponse(false, 'sdp_answer required');
        
        // Check if agent is assigned to this call
        if ($token['role'] === 'agent') {
            $stmt = $db->prepare('SELECT assigned_agent_id FROM whatsapp_calls WHERE call_id = ?');
            $stmt->execute([$callId]);
            $call = $stmt->fetch();
            if (!$call || (int)$call['assigned_agent_id'] !== (int)$token['user_id']) {
                sendResponse(false, 'Access denied');
            }
        }
        
        $result = whatsappPreAcceptCall($callId, $sdpAnswer, $phoneNumberId);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/calls\/([^\/]+)\/reject$/', $path, $m)) {
        // Reject user call
        $callId = $m[1];
        $phoneNumberId = $input['phone_number_id'] ?? null;
        
        // Check if agent is assigned to this call
        if ($token['role'] === 'agent') {
            $stmt = $db->prepare('SELECT assigned_agent_id FROM whatsapp_calls WHERE call_id = ?');
            $stmt->execute([$callId]);
            $call = $stmt->fetch();
            if (!$call || (int)$call['assigned_agent_id'] !== (int)$token['user_id']) {
                sendResponse(false, 'Access denied');
            }
        }
        
        $result = whatsappRejectCall($callId, $phoneNumberId);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/calls\/([^\/]+)\/terminate$/', $path, $m)) {
        // Terminate call
        $callId = $m[1];
        $phoneNumberId = $input['phone_number_id'] ?? null;
        
        // Check if agent is assigned to this call
        if ($token['role'] === 'agent') {
            $stmt = $db->prepare('SELECT assigned_agent_id FROM whatsapp_calls WHERE call_id = ?');
            $stmt->execute([$callId]);
            $call = $stmt->fetch();
            if (!$call || (int)$call['assigned_agent_id'] !== (int)$token['user_id']) {
                sendResponse(false, 'Access denied');
            }
        }
        
        $result = whatsappTerminateCall($callId, $phoneNumberId);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'GET' && preg_match('/\/whatsapp\/calls\/([^\/]+)$/', $path, $m)) {
        // Get call details
        $callId = $m[1];
        $stmt = $db->prepare('SELECT * FROM whatsapp_calls WHERE call_id = ?');
        $stmt->execute([$callId]);
        $call = $stmt->fetch();
        
        if (!$call) sendResponse(false, 'Call not found');
        
        // Check access
        if ($token['role'] === 'agent' && (int)$call['assigned_agent_id'] !== (int)$token['user_id']) {
            sendResponse(false, 'Access denied');
        }
        
        sendResponse(true, 'OK', $call);
        
    } elseif ($method === 'GET' && preg_match('/\/whatsapp\/calls$/', $path)) {
        // List calls
        if ($token['role'] === 'admin') {
            $stmt = $db->query('SELECT * FROM whatsapp_calls ORDER BY created_at DESC LIMIT 100');
        } else {
            $stmt = $db->prepare('SELECT * FROM whatsapp_calls WHERE assigned_agent_id = ? ORDER BY created_at DESC LIMIT 100');
            $stmt->execute([$token['user_id']]);
        }
        sendResponse(true, 'OK', $stmt->fetchAll());
        
    } elseif ($method === 'GET' && preg_match('/\/whatsapp\/call\/permissions$/', $path)) {
        // Get call permissions
        $userWaId = $_GET['user_wa_id'] ?? null;
        $phoneNumberId = $_GET['phone_number_id'] ?? whatsappCallingPhoneNumberId();
        
        if (!$userWaId) sendResponse(false, 'user_wa_id required');
        
        $permission = whatsappGetCallPermission($db, $userWaId, $phoneNumberId);
        sendResponse(true, 'OK', $permission ?: ['permission_status' => 'no_permission']);
        
    } elseif (strpos($path, '/whatsapp/call/settings') !== false) {
        handleCallSettingsRoutes($method, $path);
        
    } elseif (strpos($path, '/whatsapp/call/permission') !== false) {
        handleCallPermissionRoutes($method, $path);
        
    } elseif (strpos($path, '/whatsapp/carousel') !== false) {
        handleCarouselRoutes($method, $path);
        
    } elseif (strpos($path, '/whatsapp/message/') !== false) {
        handleMessageTypeRoutes($method, $path);
        
    } elseif (strpos($path, '/whatsapp/analytics') !== false) {
        handleAnalyticsRoutes($method, $path);
        
    } elseif (strpos($path, '/whatsapp/templates') !== false && strpos($path, '/whatsapp/templates/sync') === false && strpos($path, '/whatsapp/templates/') === false) {
        handleTemplateManagementRoutes($method, $path);
        
    } elseif (strpos($path, '/whatsapp/payments') !== false) {
        handlePaymentRoutes($method, $path);
        
    } elseif (strpos($path, '/whatsapp/throughput') !== false) {
        handleThroughputRoutes($method, $path);
        
    } elseif (strpos($path, '/whatsapp/welcome_sequences') !== false) {
        handleWelcomeSequenceRoutes($method, $path);
        
    } else {
        sendResponse(false, 'Invalid endpoint');
    }
}

function handlePaymentRoutes($method, $path) {
    require_once 'cashfree_payment.php';
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($method === 'POST' && preg_match('/\/whatsapp\/payments\/orders$/', $path)) {
        // Create payment order
        $orderData = [
            'order_id' => $input['order_id'] ?? uniqid('order_'),
            'order_amount' => $input['order_amount'] ?? null,
            'order_currency' => $input['order_currency'] ?? 'INR',
            'order_note' => $input['order_note'] ?? null,
            'customer_id' => $input['customer_id'] ?? null,
            'customer_email' => $input['customer_email'] ?? null,
            'customer_phone' => $input['customer_phone'] ?? null,
            'customer_name' => $input['customer_name'] ?? null,
            'notify_url' => $input['notify_url'] ?? null,
            'payment_methods' => $input['payment_methods'] ?? 'upi',
            'order_expiry_time' => $input['order_expiry_time'] ?? null,
            'order_tags' => $input['order_tags'] ?? ['channel' => 'WhatsApp']
        ];
        
        if (!$orderData['order_amount']) {
            sendResponse(false, 'order_amount required');
        }
        
        $result = cashfreeCreateOrder($orderData);
        
        if ($result['success']) {
            // Store in database
            $orderData['cf_order_id'] = $result['data']['cf_order_id'] ?? null;
            $orderData['payment_session_id'] = $result['data']['payment_session_id'] ?? null;
            cashfreeStoreOrder($db, $orderData);
        }
        
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/payments\/sessions$/', $path)) {
        // Get payment session (UPI intent URL)
        $paymentSessionId = $input['payment_session_id'] ?? null;
        $upiId = $input['upi_id'] ?? null;
        $upiExpiryMinutes = $input['upi_expiry_minutes'] ?? 10;
        
        if (!$paymentSessionId) {
            sendResponse(false, 'payment_session_id required');
        }
        
        $result = cashfreeGetPaymentSession($paymentSessionId, $upiId, $upiExpiryMinutes);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'GET' && preg_match('/\/whatsapp\/payments\/status\/([^\/]+)$/', $path, $m)) {
        // Get payment status
        $orderId = $m[1];
        $result = cashfreeGetPaymentStatus($orderId);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/payments\/refund$/', $path)) {
        // Process refund
        $refundData = [
            'order_id' => $input['order_id'] ?? null,
            'refund_id' => $input['refund_id'] ?? uniqid('refund_'),
            'refund_amount' => $input['refund_amount'] ?? null,
            'refund_note' => $input['refund_note'] ?? null,
            'refund_splits' => $input['refund_splits'] ?? []
        ];
        
        if (!$refundData['order_id'] || !$refundData['refund_amount']) {
            sendResponse(false, 'order_id and refund_amount required');
        }
        
        $result = cashfreeProcessRefund($refundData);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/payments\/webhook$/', $path)) {
        // Handle Cashfree webhook
        $webhookData = json_decode(file_get_contents('php://input'), true);
        
        if (isset($webhookData['data']['payment'])) {
            $payment = $webhookData['data']['payment'];
            $orderId = $webhookData['data']['order']['order_id'] ?? null;
            $paymentStatus = $payment['payment_status'] ?? null;
            $cfPaymentId = $payment['cf_payment_id'] ?? null;
            
            if ($orderId && $paymentStatus) {
                cashfreeUpdatePaymentStatus($db, $orderId, $paymentStatus, $cfPaymentId, [
                    'payment_method' => $payment['payment_method'] ?? null,
                    'payment_time' => isset($payment['payment_time']) ? date('Y-m-d H:i:s', strtotime($payment['payment_time'])) : null
                ]);
                
                // Store webhook data
                $stmt = $db->prepare('UPDATE whatsapp_payment_orders SET webhook_data = ? WHERE order_id = ?');
                $stmt->execute([json_encode($webhookData), $orderId]);
            }
        }
        
        sendResponse(true, 'Webhook processed');
        
    } else {
        sendResponse(false, 'Invalid payment endpoint');
    }
}

function handleThroughputRoutes($method, $path) {
    require_once 'whatsapp_throughput.php';
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $db = getDB();
    
    if ($method === 'GET' && preg_match('/\/whatsapp\/throughput$/', $path)) {
        // Get current throughput
        $phoneNumberId = $_GET['phone_number_id'] ?? null;
        $result = whatsappGetThroughput($phoneNumberId);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result);
        
    } elseif ($method === 'GET' && preg_match('/\/whatsapp\/throughput\/monitor$/', $path)) {
        // Monitor message rate
        $phoneNumberId = $_GET['phone_number_id'] ?? null;
        if (!$phoneNumberId) {
            $phoneNumberId = whatsappPhoneNumberId();
        }
        
        $metrics = whatsappMonitorMessageRate($db, $phoneNumberId);
        whatsappStoreThroughputMetrics($db, $phoneNumberId, $metrics);
        sendResponse(true, 'OK', $metrics);
        
    } elseif ($method === 'GET' && preg_match('/\/whatsapp\/throughput\/history$/', $path)) {
        // Get throughput history
        $phoneNumberId = $_GET['phone_number_id'] ?? null;
        $hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 24;
        
        if (!$phoneNumberId) {
            $phoneNumberId = whatsappPhoneNumberId();
        }
        
        $history = whatsappGetThroughputHistory($db, $phoneNumberId, $hours);
        sendResponse(true, 'OK', $history);
        
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/throughput\/queue\/process$/', $path)) {
        // Process message queue
        $input = json_decode(file_get_contents('php://input'), true);
        $phoneNumberId = $input['phone_number_id'] ?? null;
        $limit = $input['limit'] ?? 10;
        
        if (!$phoneNumberId) {
            $phoneNumberId = whatsappPhoneNumberId();
        }
        
        $result = whatsappProcessMessageQueue($db, $phoneNumberId, $limit);
        sendResponse(true, 'OK', $result);
        
    } else {
        sendResponse(false, 'Invalid throughput endpoint');
    }
}

function handleWelcomeSequenceRoutes($method, $path) {
    require_once 'whatsapp_welcome_sequences.php';
    $token = validateJWT();
    if (!$token || $token['role'] !== 'admin') sendResponse(false, 'Admin access required');
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($method === 'POST' && preg_match('/\/whatsapp\/welcome_sequences$/', $path)) {
        // Create or update welcome sequence
        $name = $input['name'] ?? null;
        $text = $input['text'] ?? null;
        $autofillMessage = $input['autofill_message'] ?? null;
        $iceBreakers = $input['ice_breakers'] ?? [];
        $sequenceId = $input['sequence_id'] ?? null;
        
        if (!$name || !$text) {
            sendResponse(false, 'name and text required');
        }
        
        $sequenceData = whatsappBuildWelcomeSequence($text, $autofillMessage, $iceBreakers);
        
        if ($sequenceId) {
            // Update existing sequence
            $result = whatsappUpdateWelcomeSequence($sequenceId, $name, $sequenceData);
        } else {
            // Create new sequence
            $result = whatsappCreateWelcomeSequence($name, $sequenceData);
        }
        
        if ($result['success']) {
            // Store in database
            $storeData = [
                'sequence_id' => $result['sequence_id'] ?? $sequenceId,
                'name' => $name,
                'text' => $text,
                'autofill_message' => $autofillMessage,
                'ice_breakers' => $iceBreakers
            ];
            whatsappStoreWelcomeSequence($db, $storeData);
        }
        
        sendResponse($result['success'], $result['message'] ?? 'OK', $result);
        
    } elseif ($method === 'GET' && preg_match('/\/whatsapp\/welcome_sequences$/', $path)) {
        // Get welcome sequences
        $sequenceId = $_GET['sequence_id'] ?? null;
        $result = whatsappGetWelcomeSequences($sequenceId);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'DELETE' && preg_match('/\/whatsapp\/welcome_sequences\/([^\/]+)$/', $path, $m)) {
        // Delete welcome sequence
        $sequenceId = $m[1];
        $result = whatsappDeleteWelcomeSequence($sequenceId);
        
        if ($result['success']) {
            // Delete from database
            $stmt = $db->prepare('DELETE FROM whatsapp_welcome_sequences WHERE sequence_id = ?');
            $stmt->execute([$sequenceId]);
        }
        
        sendResponse($result['success'], $result['message'] ?? 'OK', $result);
        
    } else {
        sendResponse(false, 'Invalid welcome sequence endpoint');
    }
}

function handleTemplateManagementRoutes($method, $path) {
    require_once 'whatsapp_template_management.php';
    $token = validateJWT();
    if (!$token || $token['role'] !== 'admin') sendResponse(false, 'Admin access required');
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($method === 'POST' && preg_match('/\/whatsapp\/templates\/create$/', $path)) {
        // Create template via Meta API
        $name = $input['name'] ?? null;
        $category = $input['category'] ?? 'utility';
        $language = $input['language'] ?? 'en_US';
        $components = $input['components'] ?? [];
        $parameterFormat = $input['parameter_format'] ?? 'positional';
        
        if (!$name || empty($components)) {
            sendResponse(false, 'Template name and components required');
        }
        
        $result = whatsappCreateTemplate($name, $category, $language, $components, $parameterFormat);
        
        if ($result['success']) {
            // Store in database
            $templateData = [
                'name' => $name,
                'category' => $category,
                'language' => $language,
                'parameter_format' => $parameterFormat,
                'components' => $components,
                'meta_template_id' => $result['template_id'],
                'status' => $result['status']
            ];
            whatsappStoreTemplate($db, $templateData);
        }
        
        sendResponse($result['success'], $result['message'] ?? 'OK', $result);
        
    } elseif ($method === 'GET' && preg_match('/\/whatsapp\/templates\/([^\/]+)\/status$/', $path, $m)) {
        // Get template status
        $templateId = $m[1];
        $result = whatsappGetTemplateStatus($templateId);
        
        if ($result['success']) {
            // Update database
            whatsappUpdateTemplateStatus($db, $templateId, $result['status'], $result['quality_rating'] ?? null, $result['rejected_reason'] ?? null);
        }
        
        sendResponse($result['success'], $result['message'] ?? 'OK', $result);
        
    } elseif ($method === 'DELETE' && preg_match('/\/whatsapp\/templates\/([^\/]+)$/', $path, $m)) {
        // Delete template
        $templateId = $m[1];
        $result = whatsappDeleteTemplate($templateId);
        
        if ($result['success']) {
            // Delete from database
            $stmt = $db->prepare('DELETE FROM whatsapp_templates WHERE meta_template_id = ?');
            $stmt->execute([$templateId]);
        }
        
        sendResponse($result['success'], $result['message'] ?? 'OK', $result);
        
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/templates\/send\/named$/', $path)) {
        // Send template with named parameters
        $to = $input['to'] ?? null;
        $templateName = $input['template_name'] ?? null;
        $language = $input['language'] ?? 'en_US';
        $parameters = $input['parameters'] ?? [];
        $phoneNumberId = $input['phone_number_id'] ?? null;
        
        if (!$to || !$templateName || empty($parameters)) {
            sendResponse(false, 'to, template_name, and parameters required');
        }
        
        $result = whatsappSendTemplateNamed($to, $templateName, $language, $parameters, $phoneNumberId);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/templates\/send\/positional$/', $path)) {
        // Send template with positional parameters
        $to = $input['to'] ?? null;
        $templateName = $input['template_name'] ?? null;
        $language = $input['language'] ?? 'en_US';
        $parameters = $input['parameters'] ?? [];
        $phoneNumberId = $input['phone_number_id'] ?? null;
        
        if (!$to || !$templateName || empty($parameters)) {
            sendResponse(false, 'to, template_name, and parameters required');
        }
        
        $result = whatsappSendTemplatePositional($to, $templateName, $language, $parameters, $phoneNumberId);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } else {
        sendResponse(false, 'Invalid template endpoint');
    }
}

function handleAnalyticsRoutes($method, $path) {
    require_once 'whatsapp_analytics.php';
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $db = getDB();
    
    if ($method === 'GET' && preg_match('/\/whatsapp\/analytics\/messaging$/', $path)) {
        // Get messaging analytics
        $startTime = isset($_GET['start']) ? (int)$_GET['start'] : strtotime('-30 days');
        $endTime = isset($_GET['end']) ? (int)$_GET['end'] : time();
        $granularity = $_GET['granularity'] ?? 'DAY';
        $phoneNumbers = isset($_GET['phone_numbers']) ? json_decode($_GET['phone_numbers'], true) : [];
        $productTypes = isset($_GET['product_types']) ? json_decode($_GET['product_types'], true) : [];
        $countryCodes = isset($_GET['country_codes']) ? json_decode($_GET['country_codes'], true) : [];
        
        $filters = [
            'start' => $startTime,
            'end' => $endTime,
            'granularity' => $granularity
        ];
        if (!empty($phoneNumbers)) $filters['phone_numbers'] = $phoneNumbers;
        if (!empty($productTypes)) $filters['product_types'] = $productTypes;
        if (!empty($countryCodes)) $filters['country_codes'] = $countryCodes;
        
        // Check cache first
        $cached = whatsappGetCachedAnalytics($db, 'messaging', $filters);
        if ($cached) {
            sendResponse(true, 'Analytics retrieved from cache', $cached);
            return;
        }
        
        $result = whatsappGetMessagingAnalytics($startTime, $endTime, $granularity, $phoneNumbers, $productTypes, $countryCodes);
        
        if ($result['success']) {
            whatsappCacheAnalytics($db, 'messaging', $filters, $result['data']);
        }
        
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'GET' && preg_match('/\/whatsapp\/analytics\/conversation$/', $path)) {
        // Get conversation analytics
        $startTime = isset($_GET['start']) ? (int)$_GET['start'] : strtotime('-30 days');
        $endTime = isset($_GET['end']) ? (int)$_GET['end'] : time();
        $granularity = $_GET['granularity'] ?? 'DAILY';
        $phoneNumbers = isset($_GET['phone_numbers']) ? json_decode($_GET['phone_numbers'], true) : [];
        $metricTypes = isset($_GET['metric_types']) ? json_decode($_GET['metric_types'], true) : [];
        $conversationCategories = isset($_GET['conversation_categories']) ? json_decode($_GET['conversation_categories'], true) : [];
        $conversationTypes = isset($_GET['conversation_types']) ? json_decode($_GET['conversation_types'], true) : [];
        $conversationDirections = isset($_GET['conversation_directions']) ? json_decode($_GET['conversation_directions'], true) : [];
        $dimensions = isset($_GET['dimensions']) ? json_decode($_GET['dimensions'], true) : [];
        $countryCodes = isset($_GET['country_codes']) ? json_decode($_GET['country_codes'], true) : [];
        
        $filters = [
            'start' => $startTime,
            'end' => $endTime,
            'granularity' => $granularity
        ];
        if (!empty($phoneNumbers)) $filters['phone_numbers'] = $phoneNumbers;
        if (!empty($metricTypes)) $filters['metric_types'] = $metricTypes;
        if (!empty($conversationCategories)) $filters['conversation_categories'] = $conversationCategories;
        if (!empty($conversationTypes)) $filters['conversation_types'] = $conversationTypes;
        if (!empty($conversationDirections)) $filters['conversation_directions'] = $conversationDirections;
        if (!empty($dimensions)) $filters['dimensions'] = $dimensions;
        if (!empty($countryCodes)) $filters['country_codes'] = $countryCodes;
        
        // Check cache first
        $cached = whatsappGetCachedAnalytics($db, 'conversation', $filters);
        if ($cached) {
            sendResponse(true, 'Analytics retrieved from cache', $cached);
            return;
        }
        
        $result = whatsappGetConversationAnalytics($startTime, $endTime, $granularity, $phoneNumbers, $metricTypes, $conversationCategories, $conversationTypes, $conversationDirections, $dimensions, $countryCodes);
        
        if ($result['success']) {
            whatsappCacheAnalytics($db, 'conversation', $filters, $result['data']);
        }
        
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'GET' && preg_match('/\/whatsapp\/analytics\/pricing$/', $path)) {
        // Get pricing analytics
        $startTime = isset($_GET['start']) ? (int)$_GET['start'] : strtotime('-30 days');
        $endTime = isset($_GET['end']) ? (int)$_GET['end'] : time();
        $granularity = $_GET['granularity'] ?? 'DAILY';
        $phoneNumbers = isset($_GET['phone_numbers']) ? json_decode($_GET['phone_numbers'], true) : [];
        $dimensions = isset($_GET['dimensions']) ? json_decode($_GET['dimensions'], true) : [];
        $countryCodes = isset($_GET['country_codes']) ? json_decode($_GET['country_codes'], true) : [];
        
        $filters = [
            'start' => $startTime,
            'end' => $endTime,
            'granularity' => $granularity
        ];
        if (!empty($phoneNumbers)) $filters['phone_numbers'] = $phoneNumbers;
        if (!empty($dimensions)) $filters['dimensions'] = $dimensions;
        if (!empty($countryCodes)) $filters['country_codes'] = $countryCodes;
        
        // Check cache first
        $cached = whatsappGetCachedAnalytics($db, 'pricing', $filters);
        if ($cached) {
            sendResponse(true, 'Analytics retrieved from cache', $cached);
            return;
        }
        
        $result = whatsappGetPricingAnalytics($startTime, $endTime, $granularity, $phoneNumbers, $dimensions, $countryCodes);
        
        if ($result['success']) {
            whatsappCacheAnalytics($db, 'pricing', $filters, $result['data']);
        }
        
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'GET' && preg_match('/\/whatsapp\/analytics\/template$/', $path)) {
        // Get template analytics
        $startTime = isset($_GET['start']) ? (int)$_GET['start'] : strtotime('-90 days');
        $endTime = isset($_GET['end']) ? (int)$_GET['end'] : time();
        $granularity = $_GET['granularity'] ?? 'DAILY';
        $phoneNumbers = isset($_GET['phone_numbers']) ? json_decode($_GET['phone_numbers'], true) : [];
        $templateIds = isset($_GET['template_ids']) ? json_decode($_GET['template_ids'], true) : [];
        
        $filters = [
            'start' => $startTime,
            'end' => $endTime,
            'granularity' => $granularity
        ];
        if (!empty($phoneNumbers)) $filters['phone_numbers'] = $phoneNumbers;
        if (!empty($templateIds)) $filters['template_ids'] = $templateIds;
        
        // Check cache first
        $cached = whatsappGetCachedAnalytics($db, 'template', $filters);
        if ($cached) {
            sendResponse(true, 'Analytics retrieved from cache', $cached);
            return;
        }
        
        $result = whatsappGetTemplateAnalytics($startTime, $endTime, $granularity, $phoneNumbers, $templateIds);
        
        if ($result['success']) {
            whatsappCacheAnalytics($db, 'template', $filters, $result['data']);
        }
        
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'GET' && preg_match('/\/whatsapp\/analytics\/template_group$/', $path)) {
        // Get template group analytics
        $startTime = isset($_GET['start']) ? (int)$_GET['start'] : strtotime('-90 days');
        $endTime = isset($_GET['end']) ? (int)$_GET['end'] : time();
        $granularity = $_GET['granularity'] ?? 'DAILY';
        $phoneNumbers = isset($_GET['phone_numbers']) ? json_decode($_GET['phone_numbers'], true) : [];
        $templateGroupIds = isset($_GET['template_group_ids']) ? json_decode($_GET['template_group_ids'], true) : [];
        
        $filters = [
            'start' => $startTime,
            'end' => $endTime,
            'granularity' => $granularity
        ];
        if (!empty($phoneNumbers)) $filters['phone_numbers'] = $phoneNumbers;
        if (!empty($templateGroupIds)) $filters['template_group_ids'] = $templateGroupIds;
        
        // Check cache first
        $cached = whatsappGetCachedAnalytics($db, 'template_group', $filters);
        if ($cached) {
            sendResponse(true, 'Analytics retrieved from cache', $cached);
            return;
        }
        
        $result = whatsappGetTemplateGroupAnalytics($startTime, $endTime, $granularity, $phoneNumbers, $templateGroupIds);
        
        if ($result['success']) {
            whatsappCacheAnalytics($db, 'template_group', $filters, $result['data']);
        }
        
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } else {
        sendResponse(false, 'Invalid analytics endpoint');
    }
}

function handleMessageTypeRoutes($method, $path) {
    require_once 'whatsapp_message_types.php';
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($method === 'POST' && preg_match('/\/whatsapp\/message\/address$/', $path)) {
        // Send address message
        $to = $input['to'] ?? null;
        $addressData = $input['address'] ?? null;
        $phoneNumberId = $input['phone_number_id'] ?? null;
        
        if (!$to || !$addressData) sendResponse(false, 'to and address required');
        
        $result = whatsappSendAddressMessage($to, $addressData, $phoneNumberId);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/message\/audio$/', $path)) {
        // Send audio message
        $to = $input['to'] ?? null;
        $audioUrl = $input['audio_url'] ?? null;
        $phoneNumberId = $input['phone_number_id'] ?? null;
        
        if (!$to || !$audioUrl) sendResponse(false, 'to and audio_url required');
        
        $result = whatsappSendAudioMessage($to, $audioUrl, $phoneNumberId);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/message\/contacts$/', $path)) {
        // Send contacts message
        $to = $input['to'] ?? null;
        $contacts = $input['contacts'] ?? null;
        $phoneNumberId = $input['phone_number_id'] ?? null;
        
        if (!$to || !$contacts) sendResponse(false, 'to and contacts required');
        
        $result = whatsappSendContactsMessage($to, $contacts, $phoneNumberId);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/message\/sticker$/', $path)) {
        // Send sticker message
        $to = $input['to'] ?? null;
        $stickerUrl = $input['sticker_url'] ?? null;
        $phoneNumberId = $input['phone_number_id'] ?? null;
        
        if (!$to || !$stickerUrl) sendResponse(false, 'to and sticker_url required');
        
        $result = whatsappSendStickerMessage($to, $stickerUrl, $phoneNumberId);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/message\/reaction$/', $path)) {
        // Send reaction message
        $to = $input['to'] ?? null;
        $messageId = $input['message_id'] ?? null;
        $emoji = $input['emoji'] ?? null;
        $phoneNumberId = $input['phone_number_id'] ?? null;
        
        if (!$to || !$messageId || !$emoji) sendResponse(false, 'to, message_id and emoji required');
        
        $result = whatsappSendReactionMessage($to, $messageId, $emoji, $phoneNumberId);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'GET' && preg_match('/\/whatsapp\/link\/preview$/', $path)) {
        // Get link preview
        require_once 'whatsapp_link_preview.php';
        $url = $_GET['url'] ?? null;
        if (!$url) sendResponse(false, 'url required');
        
        $preview = whatsappExtractLinkPreview($url);
        sendResponse($preview['success'], $preview['message'] ?? 'OK', $preview['data'] ?? []);
        
    } else {
        sendResponse(false, 'Invalid endpoint');
    }
}

function handleCallPermissionRoutes($method, $path) {
    require_once 'whatsapp_call_permissions.php';
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($method === 'POST' && preg_match('/\/whatsapp\/call\/permission\/request$/', $path)) {
        // Send call permission request
        $to = $input['to'] ?? null;
        $bodyText = $input['body_text'] ?? null;
        $phoneNumberId = $input['phone_number_id'] ?? null;
        
        if (!$to) sendResponse(false, 'to required');
        
        $result = whatsappSendCallPermissionRequest($to, $bodyText, $phoneNumberId);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } elseif ($method === 'GET' && preg_match('/\/whatsapp\/call\/permission\/state$/', $path)) {
        // Get call permission state
        $userWaId = $_GET['user_wa_id'] ?? null;
        $phoneNumberId = $_GET['phone_number_id'] ?? whatsappPhoneNumberId();
        
        if (!$userWaId) sendResponse(false, 'user_wa_id required');
        
        $state = whatsappGetCallPermissionState($db, $phoneNumberId, $userWaId);
        sendResponse(true, 'OK', $state);
        
    } else {
        sendResponse(false, 'Invalid endpoint');
    }
}

function handleCarouselRoutes($method, $path) {
    require_once 'whatsapp_interactive.php';
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($method === 'POST' && preg_match('/\/whatsapp\/carousel\/send$/', $path)) {
        $to = $input['to'] ?? null;
        $cards = $input['cards'] ?? [];
        $bodyText = $input['body_text'] ?? 'Check out our offers';
        $phoneNumberId = $input['phone_number_id'] ?? null;
        
        if (!$to || empty($cards)) sendResponse(false, 'to and cards required');
        
        $result = whatsappSendMediaCarousel($to, $cards, $bodyText, $phoneNumberId);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
        
    } else {
        sendResponse(false, 'Invalid endpoint');
    }
}

function handleCallSettingsRoutes($method, $path) {
    require_once 'whatsapp_calling.php';
    $token = validateJWT();
    if (!$token || $token['role'] !== 'admin') sendResponse(false, 'Admin required');
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $phoneNumberId = $_GET['phone_number_id'] ?? $input['phone_number_id'] ?? whatsappCallingPhoneNumberId();
    
    if ($method === 'GET' && preg_match('/\/whatsapp\/call\/settings$/', $path)) {
        // Get call settings
        $settings = whatsappGetCallSettings($db, $phoneNumberId);
        if (!$settings) {
            // Return default settings
            sendResponse(true, 'OK', [
                'phone_number_id' => $phoneNumberId,
                'calling_status' => 'DISABLED',
                'call_icon_visibility' => 'DEFAULT',
                'callback_permission_status' => 'DISABLED',
                'call_hours_status' => 'DISABLED',
                'sip_status' => 'DISABLED'
            ]);
        } else {
            // Parse JSON fields
            if ($settings['call_hours_weekly_schedule']) {
                $settings['call_hours_weekly_schedule'] = json_decode($settings['call_hours_weekly_schedule'], true);
            }
            if ($settings['call_hours_holiday_schedule']) {
                $settings['call_hours_holiday_schedule'] = json_decode($settings['call_hours_holiday_schedule'], true);
            }
            if ($settings['sip_servers']) {
                $settings['sip_servers'] = json_decode($settings['sip_servers'], true);
            }
            sendResponse(true, 'OK', $settings);
        }
        
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/call\/settings$/', $path)) {
        // Update call settings
        if (!$phoneNumberId) sendResponse(false, 'phone_number_id required');
        
        $settings = [
            'calling_status' => $input['calling_status'] ?? null,
            'call_icon_visibility' => $input['call_icon_visibility'] ?? null,
            'callback_permission_status' => $input['callback_permission_status'] ?? null,
            'call_hours_status' => $input['call_hours_status'] ?? null,
            'call_hours_timezone' => $input['call_hours_timezone'] ?? null,
            'call_hours_weekly_schedule' => $input['call_hours_weekly_schedule'] ?? null,
            'call_hours_holiday_schedule' => $input['call_hours_holiday_schedule'] ?? null,
            'sip_status' => $input['sip_status'] ?? null,
            'sip_servers' => $input['sip_servers'] ?? null
        ];
        
        // Remove null values
        $settings = array_filter($settings, function($v) { return $v !== null; });
        
        if (empty($settings)) {
            sendResponse(false, 'No settings provided');
        }
        
        $result = whatsappUpdateCallSettings($db, $phoneNumberId, $settings);
        sendResponse($result, $result ? 'Settings updated' : 'Failed to update settings');
        
    } else {
        sendResponse(false, 'Invalid endpoint');
    }
}

function getConversations() {
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $db = getDB();
    if ($token['role'] === 'admin') {
        $stmt = $db->query('SELECT * FROM whatsapp_conversations ORDER BY last_message_at DESC');
        sendResponse(true, 'OK', $stmt->fetchAll());
    } else {
        $stmt = $db->prepare('SELECT * FROM whatsapp_conversations WHERE assigned_agent_id = ? ORDER BY last_message_at DESC');
        $stmt->execute([$token['user_id']]);
        sendResponse(true, 'OK', $stmt->fetchAll());
    }
}

function getConversationMessages($conversationId) {
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $db = getDB();
    if ($token['role'] !== 'admin') {
        $stmt = $db->prepare('SELECT assigned_agent_id FROM whatsapp_conversations WHERE id = ?');
        $stmt->execute([$conversationId]);
        $row = $stmt->fetch();
        if (!$row || (int)$row['assigned_agent_id'] !== (int)$token['user_id']) sendResponse(false, 'Access denied');
    }
    $stmt = $db->prepare('SELECT * FROM whatsapp_messages WHERE conversation_id = ? ORDER BY id ASC');
    $stmt->execute([$conversationId]);
    sendResponse(true, 'OK', $stmt->fetchAll());
}

function whatsappSendEndpoint() {
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $input = json_decode(file_get_contents('php://input'), true);
    $to = $input['to'] ?? null;
    $type = $input['type'] ?? 'text';
    $pnId = $input['phone_number_id'] ?? null;
    if (!$to) sendResponse(false, 'Recipient required');
    $db = getDB();
    if ($token['role'] === 'agent') {
        $q = $db->prepare('SELECT la.agent_id FROM leads l JOIN lead_assignments la ON la.lead_id = l.id WHERE l.phone_number = ? ORDER BY la.assigned_at DESC LIMIT 1');
        $q->execute([$to]);
        $r = $q->fetch();
        if (!$r || (int)$r['agent_id'] !== (int)$token['user_id']) sendResponse(false, 'Access denied');
    }
    if ($type !== 'template') {
        if (!isWithinCustomerServiceWindow($db, $to)) sendResponse(false, '24-hour window closed. Use template');
    }
    $result = null;
    if ($type === 'template') {
        $template = $input['template_name'] ?? null;
        $language = $input['language_code'] ?? 'en_US';
        $components = $input['components'] ?? [];
        if (isset($input['template_id'])) {
            $stmt = $db->prepare('SELECT * FROM whatsapp_templates WHERE id = ?');
            $stmt->execute([(int)$input['template_id']]);
            $row = $stmt->fetch();
            if ($row) {
                $template = $row['name'];
                $language = $row['language'] ?? $language;
                $components = buildTemplateComponentsFromRow($row, $input['variables'] ?? []);
            }
        }
        if (!$template) sendResponse(false, 'Template name required');
        $result = whatsappSendTemplate($to, $template, $language, $components, $pnId);
    } elseif ($type === 'text') {
        $text = $input['text'] ?? null;
        if (!$text) sendResponse(false, 'Text required');
        $contextMessageId = $input['context_message_id'] ?? null;
        $result = whatsappSendText($to, $text, $pnId, $contextMessageId);
        
        // Extract and store link preview if URL detected
        if ($result['success'] && preg_match('/https?:\/\/[^\s]+/', $text, $urlMatches)) {
            require_once 'whatsapp_link_preview.php';
            $preview = whatsappExtractLinkPreview($urlMatches[0]);
            if ($preview['success']) {
                $waId = $result['data']['messages'][0]['id'] ?? null;
                if ($waId) {
                    $stmt = $db->prepare('UPDATE whatsapp_messages SET link_preview_data = ? WHERE wa_message_id = ?');
                    $stmt->execute([json_encode($preview['data']), $waId]);
                }
            }
        }
        } elseif ($type === 'interactive') {
            $payload = $input['interactive'] ?? null;
            if (!$payload) sendResponse(false, 'Interactive payload required');
            
            // Support button messages with header/footer
            if (isset($payload['type']) && $payload['type'] === 'button') {
                require_once 'whatsapp_advanced.php';
                $body = $payload['body']['text'] ?? '';
                $buttons = [];
                foreach (($payload['action']['buttons'] ?? []) as $btn) {
                    $buttons[] = [
                        'id' => $btn['reply']['id'] ?? null,
                        'title' => $btn['reply']['title'] ?? ''
                    ];
                }
                $header = $payload['header'] ?? null;
                $footer = $payload['footer']['text'] ?? null;
                $result = whatsappSendButtonMessage($to, $body, $buttons, $pnId, $header, $footer);
            } else {
                $result = whatsappPost('https://graph.facebook.com/' . whatsappGraphVersion() . '/' . ($pnId ?: whatsappPhoneNumberId()) . '/messages', [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'interactive',
                    'interactive' => $payload
                ]);
            }
        } else {
            $mediaUrl = $input['media_url'] ?? null;
            $caption = $input['caption'] ?? null;
            $filename = $input['filename'] ?? null;
            if (!$mediaUrl) sendResponse(false, 'Media URL required');
            $result = whatsappSendMedia($to, $type, $mediaUrl, $caption, $filename, $pnId);
        }
    $convId = upsertConversationByPhone($db, $to);
    $waId = $result['data']['messages'][0]['id'] ?? null;
    $body = $type === 'text' ? ($input['text'] ?? '') : ($type === 'template' ? ($input['template_name'] ?? '') : ($input['caption'] ?? $type));
    insertMessage($db, $convId, 'outgoing', $type, $body, $waId, null, $to, $result['success'] ? 'sent' : 'failed', json_encode($result['data'] ?? []), null);
    notifyConversationUpdate($db, $convId, 'outgoing', $result['data'] ?? []);
    sendResponse($result['success'], $result['success'] ? 'Message sent' : 'Send failed', $result['data']);
}

function whatsappSessionCheckEndpoint() {
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $phone = $_GET['phone'] ?? null;
    if (!$phone) sendResponse(false, 'Phone required');
    $db = getDB();
    $open = isWithinCustomerServiceWindow($db, $phone);
    sendResponse(true, 'OK', ['session_open' => $open]);
}

function handleMediaRoutes($method, $path) {
    require_once 'whatsapp_media_enhanced.php';
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($method === 'GET' && preg_match('/\/whatsapp\/media\/([^\/]+)\/url$/', $path, $m)) {
        // Get media URL
        $mediaId = $m[1];
        $phoneNumberId = $_GET['phone_number_id'] ?? null;
        $result = whatsappGetMediaUrl($mediaId, $phoneNumberId);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result);
        
    } elseif ($method === 'DELETE' && preg_match('/\/whatsapp\/media\/([^\/]+)$/', $path, $m)) {
        // Delete media
        $mediaId = $m[1];
        $phoneNumberId = $_GET['phone_number_id'] ?? null;
        $result = whatsappDeleteMedia($mediaId, $phoneNumberId);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result);
        
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/media\/upload$/', $path)) {
        // Upload media
        $filePath = $input['file_path'] ?? null;
        $mimeType = $input['mime_type'] ?? null;
        $phoneNumberId = $input['phone_number_id'] ?? null;
        
        if (!$filePath || !$mimeType) sendResponse(false, 'file_path and mime_type required');
        
        $result = whatsappUploadMedia($filePath, $mimeType, $phoneNumberId);
        sendResponse($result['success'], $result['message'] ?? 'OK', $result);
        
    } elseif ($method === 'GET' && strpos($path, '/whatsapp/media/download') !== false) {
        // Download and store media (existing endpoint)
        $mediaId = $_GET['media_id'] ?? null;
        if (!$mediaId) sendResponse(false, 'media_id required');
        if (!r2IsConfigured()) sendResponse(false, 'Cloudflare R2 not configured');
        $download = whatsappDownloadMedia($mediaId);
        if (!$download['success']) sendResponse(false, $download['message'] ?? 'Download failed');
        $ext = r2GuessExtension($download['mime_type']);
        $key = sprintf('whatsapp/inbox/%s/%s.%s', date('Y/m/d'), $mediaId, $ext);
        try {
            $uploaded = r2Upload($key, $download['body'], $download['mime_type']);
        } catch (Exception $e) {
            sendResponse(false, $e->getMessage());
        }
        sendResponse(true, 'Saved', ['key' => $uploaded['key'], 'url' => $uploaded['url'], 'mime_type' => $download['mime_type']]);
        
    } else {
        sendResponse(false, 'Invalid endpoint');
    }
}

function listWhatsAppTemplates() {
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    try {
        $db = getDB();
        $stmt = $db->query("SELECT id, name, message, media_type, media_url, buttons, category, language, header_text, header_media_type, header_media_url, footer_text, placeholders, status FROM whatsapp_templates WHERE is_active = 1 ORDER BY name");
        $rows = $stmt->fetchAll();
        sendResponse(true, 'OK', $rows);
    } catch (Exception $e) {
        sendResponse(false, 'Failed to fetch templates');
    }
}

function syncWhatsAppTemplateCategories() {
    $token = validateJWT();
    if (!$token || $token['role'] !== 'admin') sendResponse(false, 'Admin required');
    $db = getDB();
    $res = whatsappSyncTemplatesCategories($db);
    sendResponse($res['success'], $res['message'] ?? 'OK', $res['data'] ?? []);
}

function handleCampaignRoutes($method, $path) {
    $token = validateJWT();
    if (!$token || $token['role'] !== 'admin') sendResponse(false, 'Admin required');
    $db = getDB();
    if ($method === 'POST' && preg_match('/\/whatsapp\/campaigns$/', $path)) {
        $input = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare('INSERT INTO whatsapp_campaigns (name, template_name, language_code, filters_json, scheduled_at, status) VALUES (?, ?, ?, ?, ?, "draft")');
        $stmt->execute([
            $input['name'] ?? 'Campaign',
            $input['template_name'] ?? null,
            $input['language_code'] ?? 'en_US',
            json_encode($input['filters'] ?? []),
            $input['scheduled_at'] ?? null
        ]);
        sendResponse(true, 'Campaign created', ['id' => $db->lastInsertId()]);
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/campaigns\/(\d+)\/launch$/', $path, $m)) {
        $cid = (int)$m[1];
        launchCampaign($db, $cid);
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/campaigns\/(\d+)\/start$/', $path, $m)) {
        $cid = (int)$m[1];
        $res = dispatchCampaign($db, $cid);
        sendResponse($res['success'], $res['message'] ?? 'OK', $res['data'] ?? []);
    } elseif ($method === 'GET' && preg_match('/\/whatsapp\/campaigns\/(\d+)\/recipients$/', $path, $m)) {
        $cid = (int)$m[1];
        $st = $db->prepare('SELECT * FROM whatsapp_campaign_recipients WHERE campaign_id = ? ORDER BY id DESC');
        $st->execute([$cid]);
        sendResponse(true, 'OK', $st->fetchAll());
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/campaigns\/(\d+)\/recipients\/retry_failed$/', $path, $m)) {
        $cid = (int)$m[1];
        $res = retryFailedRecipients($db, $cid);
        sendResponse(true, 'Queued', $res);
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/campaigns\/(\d+)\/pause$/', $path, $m)) {
        $cid = (int)$m[1];
        $db->prepare('UPDATE whatsapp_campaigns SET status = "paused" WHERE id = ?')->execute([$cid]);
        sendResponse(true, 'Paused', ['id' => $cid]);
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/campaigns\/(\d+)\/resume$/', $path, $m)) {
        $cid = (int)$m[1];
        $db->prepare('UPDATE whatsapp_campaigns SET status = "scheduled" WHERE id = ?')->execute([$cid]);
        $res = dispatchCampaign($db, $cid);
        sendResponse($res['success'], $res['message'] ?? 'OK', $res['data'] ?? []);
    } else {
        sendResponse(false, 'Invalid endpoint');
    }
}

function launchCampaign($db, $campaignId) {
    $result = queueCampaignRecipients($db, $campaignId);
    sendResponse($result['success'], $result['message'] ?? 'OK', $result['data'] ?? []);
}

function r2PresignPutEndpoint() {
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $input = json_decode(file_get_contents('php://input'), true);
    $contentType = $input['content_type'] ?? 'application/octet-stream';
    $suggested = trim((string)($input['suggested_name'] ?? 'upload.bin'));
    if (!r2IsConfigured()) sendResponse(false, 'Cloudflare R2 not configured');
    $ext = r2GuessExtension($contentType);
    $key = sprintf('whatsapp/uploads/%s/%s', date('Y/m/d'), preg_replace('/[^a-zA-Z0-9._-]/', '', $suggested));
    if (!str_contains($key, '.')) { $key .= '.' . $ext; }
    try {
        $presigned = r2PresignPut($key, $contentType, 900);
    } catch (Exception $e) {
        sendResponse(false, $e->getMessage());
    }
    sendResponse(true, 'OK', $presigned);
}

function r2PresignGetEndpoint() {
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $key = $_GET['key'] ?? '';
    if (!$key) sendResponse(false, 'key required');
    if (!r2IsConfigured()) sendResponse(false, 'Cloudflare R2 not configured');
    try {
        $url = r2PresignGet($key, 900);
    } catch (Exception $e) {
        sendResponse(false, $e->getMessage());
    }
    sendResponse(true, 'OK', ['url' => $url]);
}

function r2PublicUrlEndpoint() {
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    $key = $_GET['key'] ?? '';
    if (!$key) sendResponse(false, 'key required');
    $url = r2PublicUrl($key);
    if (!$url) sendResponse(false, 'Public URL unavailable');
    sendResponse(true, 'OK', ['url' => $url]);
}

function handleFlowRoutes($method, $path) {
    $token = validateJWT();
    if (!$token || $token['role'] !== 'admin') sendResponse(false, 'Admin required');
    $db = getDB();
    if ($method === 'GET' && preg_match('/\/whatsapp\/flows$/', $path)) {
        $st = $db->query('SELECT id, name, active, created_at FROM whatsapp_flows ORDER BY created_at DESC');
        sendResponse(true, 'OK', $st->fetchAll());
    } elseif ($method === 'GET' && preg_match('/\/whatsapp\/flows\/(\d+)$/', $path, $m)) {
        $id = (int)$m[1];
        $st = $db->prepare('SELECT * FROM whatsapp_flows WHERE id = ?');
        $st->execute([$id]);
        sendResponse(true, 'OK', $st->fetch());
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/flows$/', $path)) {
        $input = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare('INSERT INTO whatsapp_flows (name, definition_json, active) VALUES (?, ?, ?)');
        $stmt->execute([$input['name'] ?? 'Flow', json_encode($input['definition'] ?? []), (int)($input['active'] ?? 1)]);
        sendResponse(true, 'Flow created', ['id' => $db->lastInsertId()]);
    } elseif ($method === 'PUT' && preg_match('/\/whatsapp\/flows\/(\d+)$/', $path, $m)) {
        $id = (int)$m[1];
        $input = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare('UPDATE whatsapp_flows SET name = ?, definition_json = ?, active = ? WHERE id = ?');
        $stmt->execute([$input['name'] ?? 'Flow', json_encode($input['definition'] ?? []), (int)($input['active'] ?? 1), $id]);
        sendResponse(true, 'Flow updated', ['id' => $id]);
    } elseif ($method === 'POST' && preg_match('/\/whatsapp\/flows\/process_delays$/', $path)) {
        require_once 'flows.php';
        $res = processFlowDelays($db);
        sendResponse(true, 'Processed', $res);
    } else {
        sendResponse(false, 'Invalid endpoint');
    }
}

function markMessageAsRead($conversationId, $messageId) {
    $token = validateJWT();
    if (!$token) sendResponse(false, 'Authentication required');
    require_once 'whatsapp_typing.php';
    $db = getDB();
    if ($token['role'] !== 'admin') {
        $stmt = $db->prepare('SELECT assigned_agent_id FROM whatsapp_conversations WHERE id = ?');
        $stmt->execute([$conversationId]);
        $row = $stmt->fetch();
        if (!$row || (int)$row['assigned_agent_id'] !== (int)$token['user_id']) {
            sendResponse(false, 'Access denied');
        }
    }
    whatsappMarkMessageAsReadWithTyping($db, $messageId, $conversationId);
    sendResponse(true, 'Message marked as read and typing indicator sent');
}

?>
