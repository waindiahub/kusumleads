<?php
require_once 'config.php';

function pusherTrigger($channel, $event, $data) {
    $appId = getSetting('pusher_app_id');
    $key = getSetting('pusher_key');
    $secret = getSetting('pusher_secret');
    $cluster = getSetting('pusher_cluster');
    if (!$appId || !$key || !$secret || !$cluster) {
        return false;
    }
    $payload = [
        'name' => $event,
        'channels' => [$channel],
        'data' => json_encode($data)
    ];
    $body = json_encode($payload);
    $auth_timestamp = time();
    $auth_version = '1.0';
    $body_md5 = md5($body);
    $query = http_build_query([
        'auth_key' => $key,
        'auth_timestamp' => $auth_timestamp,
        'auth_version' => $auth_version,
        'body_md5' => $body_md5
    ], '', '&');
    $string_to_sign = "POST\n/apps/$appId/events\n$query";
    $auth_signature = hash_hmac('sha256', $string_to_sign, $secret);
    $url = "https://api-$cluster.pusher.com/apps/$appId/events?$query&auth_signature=$auth_signature";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}

?>
