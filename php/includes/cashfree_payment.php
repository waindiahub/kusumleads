<?php
require_once 'config.php';
require_once 'whatsapp_cloud.php';

/**
 * Cashfree Payment Gateway Integration
 * Order creation, payment processing, webhooks
 */

/**
 * Get Cashfree credentials from settings
 */
function cashfreeClientId() {
    return getSetting('cashfree_client_id');
}

function cashfreeClientSecret() {
    return getSetting('cashfree_client_secret');
}

function cashfreeIsSandbox() {
    return getSetting('cashfree_sandbox') === '1' || getSetting('cashfree_sandbox') === 'true';
}

function cashfreeBaseUrl() {
    return cashfreeIsSandbox() ? 'https://sandbox.cashfree.com' : 'https://api.cashfree.com';
}

/**
 * Create payment order
 */
function cashfreeCreateOrder($orderData) {
    $clientId = cashfreeClientId();
    $clientSecret = cashfreeClientSecret();
    
    if (!$clientId || !$clientSecret) {
        return ['success' => false, 'message' => 'Cashfree not configured'];
    }
    
    $url = cashfreeBaseUrl() . '/pg/orders';
    
    $payload = [
        'order_id' => $orderData['order_id'],
        'order_amount' => $orderData['order_amount'],
        'order_currency' => $orderData['order_currency'] ?? 'INR',
        'order_note' => $orderData['order_note'] ?? null,
        'customer_details' => [
            'customer_id' => $orderData['customer_id'] ?? null,
            'customer_email' => $orderData['customer_email'] ?? null,
            'customer_phone' => $orderData['customer_phone'] ?? null,
            'customer_name' => $orderData['customer_name'] ?? null
        ],
        'order_meta' => [
            'notify_url' => $orderData['notify_url'] ?? null,
            'payment_methods' => $orderData['payment_methods'] ?? 'upi',
            'return_url' => $orderData['return_url'] ?? null
        ]
    ];
    
    if (isset($orderData['order_expiry_time'])) {
        $payload['order_expiry_time'] = $orderData['order_expiry_time'];
    }
    
    if (isset($orderData['order_tags'])) {
        $payload['order_tags'] = $orderData['order_tags'];
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'content-type: application/json',
        'x-api-version: 2022-09-01',
        'x-client-id: ' . $clientId,
        'x-client-secret: ' . $clientSecret
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300 && isset($data['cf_order_id'])) {
        return ['success' => true, 'data' => $data];
    }
    
    return ['success' => false, 'message' => 'Failed to create order', 'data' => $data];
}

/**
 * Get UPI intent URL for payment
 */
function cashfreeGetPaymentSession($paymentSessionId, $upiId = null, $upiExpiryMinutes = 10) {
    $clientId = cashfreeClientId();
    $clientSecret = cashfreeClientSecret();
    
    if (!$clientId || !$clientSecret) {
        return ['success' => false, 'message' => 'Cashfree not configured'];
    }
    
    $url = cashfreeBaseUrl() . '/pg/orders/sessions';
    
    $payload = [
        'payment_session_id' => $paymentSessionId,
        'payment_method' => [
            'upi' => [
                'channel' => 'link',
                'upi_id' => $upiId,
                'upi_expiry_minutes' => $upiExpiryMinutes
            ]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'content-type: application/json',
        'x-api-version: 2022-09-01',
        'x-client-id: ' . $clientId,
        'x-client-secret: ' . $clientSecret
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300 && isset($data['data']['payload']['default'])) {
        return ['success' => true, 'data' => $data];
    }
    
    return ['success' => false, 'message' => 'Failed to get payment session', 'data' => $data];
}

/**
 * Parse UPI URL to extract payment parameters
 */
function cashfreeParseUpiUrl($upiUrl) {
    $parsed = parse_url($upiUrl);
    parse_str($parsed['query'] ?? '', $params);
    
    return [
        'pa' => $params['pa'] ?? null, // Merchant VPA
        'pn' => $params['pn'] ?? null, // Payee name
        'tr' => $params['tr'] ?? null, // Transaction reference
        'am' => $params['am'] ?? null, // Amount
        'cu' => $params['cu'] ?? null, // Currency
        'mode' => $params['mode'] ?? null,
        'purpose' => $params['purpose'] ?? null,
        'mc' => $params['mc'] ?? null, // Merchant category code
        'tn' => $params['tn'] ?? null  // Transaction note
    ];
}

/**
 * Get payment status
 */
function cashfreeGetPaymentStatus($orderId) {
    $clientId = cashfreeClientId();
    $clientSecret = cashfreeClientSecret();
    
    if (!$clientId || !$clientSecret) {
        return ['success' => false, 'message' => 'Cashfree not configured'];
    }
    
    $url = cashfreeBaseUrl() . '/pg/orders/' . urlencode($orderId) . '/payments';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'x-api-version: 2022-09-01',
        'x-client-id: ' . $clientId,
        'x-client-secret: ' . $clientSecret
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $data];
    }
    
    return ['success' => false, 'message' => 'Failed to get payment status', 'data' => $data];
}

/**
 * Process refund
 */
function cashfreeProcessRefund($refundData) {
    $clientId = cashfreeClientId();
    $clientSecret = cashfreeClientSecret();
    
    if (!$clientId || !$clientSecret) {
        return ['success' => false, 'message' => 'Cashfree not configured'];
    }
    
    $url = cashfreeBaseUrl() . '/pg/orders/' . urlencode($refundData['order_id']) . '/refunds';
    
    $payload = [
        'refund_amount' => $refundData['refund_amount'],
        'refund_id' => $refundData['refund_id'],
        'refund_note' => $refundData['refund_note'] ?? null,
        'refund_splits' => $refundData['refund_splits'] ?? []
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'content-type: application/json',
        'x-api-version: 2022-09-01',
        'x-client-id: ' . $clientId,
        'x-client-secret: ' . $clientSecret
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $data];
    }
    
    return ['success' => false, 'message' => 'Failed to process refund', 'data' => $data];
}

/**
 * Store payment order in database
 */
function cashfreeStoreOrder($db, $orderData) {
    $stmt = $db->prepare('INSERT INTO whatsapp_payment_orders (order_id, cf_order_id, customer_id, customer_phone, customer_email, order_amount, order_currency, payment_session_id, cf_payment_id, payment_status, payment_configuration, reference_id, merchant_vpa, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE payment_status = ?, cf_payment_id = ?, updated_at = NOW()');
    
    $stmt->execute([
        $orderData['order_id'],
        $orderData['cf_order_id'] ?? null,
        $orderData['customer_id'] ?? null,
        $orderData['customer_phone'] ?? null,
        $orderData['customer_email'] ?? null,
        $orderData['order_amount'],
        $orderData['order_currency'] ?? 'INR',
        $orderData['payment_session_id'] ?? null,
        $orderData['cf_payment_id'] ?? null,
        $orderData['payment_status'] ?? 'PENDING',
        $orderData['payment_configuration'] ?? null,
        $orderData['reference_id'] ?? null,
        $orderData['merchant_vpa'] ?? null,
        $orderData['payment_status'] ?? 'PENDING',
        $orderData['cf_payment_id'] ?? null
    ]);
    
    return $db->lastInsertId();
}

/**
 * Update payment status from webhook
 */
function cashfreeUpdatePaymentStatus($db, $orderId, $paymentStatus, $cfPaymentId = null, $additionalData = []) {
    $updates = ['payment_status = ?', 'updated_at = NOW()'];
    $params = [$paymentStatus];
    
    if ($cfPaymentId) {
        $updates[] = 'cf_payment_id = ?';
        $params[] = $cfPaymentId;
    }
    
    if (isset($additionalData['payment_method'])) {
        $updates[] = 'payment_method = ?';
        $params[] = $additionalData['payment_method'];
    }
    
    if (isset($additionalData['payment_time'])) {
        $updates[] = 'payment_time = ?';
        $params[] = $additionalData['payment_time'];
    }
    
    $params[] = $orderId;
    
    $sql = 'UPDATE whatsapp_payment_orders SET ' . implode(', ', $updates) . ' WHERE order_id = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->rowCount() > 0;
}

?>

