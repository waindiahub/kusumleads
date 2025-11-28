<?php
require_once __DIR__ . '/config.php';

function r2Config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $cfg = [
        'access_key' => trim((string)getSetting('r2_access_key')),
        'secret_key' => trim((string)getSetting('r2_secret_key')),
        'account_id' => trim((string)getSetting('r2_account_id')),
        'bucket' => trim((string)getSetting('r2_bucket')),
        'endpoint' => trim((string)getSetting('r2_endpoint')),
        'region' => trim((string)(getSetting('r2_region') ?: 'auto')),
        'custom_domain' => trim((string)getSetting('r2_custom_domain')),
    ];

    if (!$cfg['endpoint'] && $cfg['account_id']) {
        $cfg['endpoint'] = sprintf('https://%s.r2.cloudflarestorage.com', $cfg['account_id']);
    }

    return $cfg;
}

function r2IsConfigured(): bool
{
    $cfg = r2Config();
    return !empty($cfg['access_key']) && !empty($cfg['secret_key']) && !empty($cfg['bucket']) && !empty($cfg['endpoint']);
}

function r2PublicUrl(string $key): ?string
{
    $cfg = r2Config();
    if (!$key) {
        return null;
    }
    $cleanKey = ltrim($key, '/');
    if (!empty($cfg['custom_domain'])) {
        return rtrim($cfg['custom_domain'], '/') . '/' . $cleanKey;
    }
    if (!$cfg['endpoint']) {
        return null;
    }
    $parts = parse_url($cfg['endpoint']);
    if (!$parts || empty($parts['host'])) {
        return null;
    }
    $segments = [];
    if (!empty($parts['path'])) {
        $segments = array_values(array_filter(explode('/', $parts['path']), 'strlen'));
    }
    if (empty($segments) || strtolower(end($segments)) !== strtolower($cfg['bucket'])) {
        $segments[] = $cfg['bucket'];
    }
    $encodedSegments = array_map('rawurlencode', array_merge($segments, array_filter(explode('/', $cleanKey), 'strlen')));
    $scheme = $parts['scheme'] ?? 'https';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $baseUrl = $scheme . '://' . $parts['host'] . $port;
    return $baseUrl . '/' . implode('/', $encodedSegments);
}

function r2Upload(string $key, string $contents, string $contentType = 'application/octet-stream'): array
{
    if (!r2IsConfigured()) {
        throw new Exception('Cloudflare R2 storage is not configured.');
    }

    $cfg = r2Config();
    $bucket = $cfg['bucket'];
    if (!$bucket) {
        throw new Exception('Cloudflare R2 bucket is not configured.');
    }
    $key = ltrim($key, '/');
    $endpoint = $cfg['endpoint'];
    $endpointParts = parse_url($endpoint);
    if (!$endpointParts || empty($endpointParts['host'])) {
        throw new Exception('Invalid R2 endpoint.');
    }

    $pathSegments = [];
    if (!empty($endpointParts['path'])) {
        $pathSegments = array_values(array_filter(explode('/', $endpointParts['path']), 'strlen'));
    }
    if (empty($pathSegments) || strtolower(end($pathSegments)) !== strtolower($bucket)) {
        $pathSegments[] = $bucket;
    }
    $objectSegments = array_filter(explode('/', $key), 'strlen');
    $encodedSegments = array_map('rawurlencode', array_merge($pathSegments, $objectSegments));
    $requestPath = '/' . implode('/', $encodedSegments);

    $scheme = $endpointParts['scheme'] ?? 'https';
    $baseHost = $endpointParts['host'];
    $port = isset($endpointParts['port']) ? ':' . $endpointParts['port'] : '';
    $baseUrl = $scheme . '://' . $baseHost . $port;
    $requestUrl = $baseUrl . $requestPath;

    $host = $baseHost . $port;
    $amzDate = gmdate('Ymd\THis\Z');
    $dateStamp = substr($amzDate, 0, 8);
    $payloadHash = hash('sha256', $contents);

    $headers = [
        'content-type' => $contentType,
        'host' => $host,
        'x-amz-content-sha256' => $payloadHash,
        'x-amz-date' => $amzDate,
    ];

    ksort($headers);
    $canonicalHeaders = '';
    foreach ($headers as $k => $v) {
        $canonicalHeaders .= strtolower($k) . ':' . trim($v) . "\n";
    }
    $signedHeaders = implode(';', array_keys($headers));

    $canonicalRequest = implode("\n", [
        'PUT',
        $requestPath,
        '',
        $canonicalHeaders,
        $signedHeaders,
        $payloadHash
    ]);

    $credentialScope = sprintf('%s/%s/s3/aws4_request', $dateStamp, $cfg['region']);
    $stringToSign = implode("\n", [
        'AWS4-HMAC-SHA256',
        $amzDate,
        $credentialScope,
        hash('sha256', $canonicalRequest)
    ]);

    $signingKey = r2SigningKey($cfg['secret_key'], $dateStamp, $cfg['region']);
    $signature = hash_hmac('sha256', $stringToSign, $signingKey);

    $authorization = sprintf(
        'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
        $cfg['access_key'],
        $credentialScope,
        $signedHeaders,
        $signature
    );

    $curlHeaders = [
        'Content-Type: ' . $contentType,
        'X-Amz-Content-Sha256: ' . $payloadHash,
        'X-Amz-Date: ' . $amzDate,
        'Authorization: ' . $authorization,
        'Host: ' . $host
    ];

    $ch = curl_init($requestUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $contents);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($code < 200 || $code >= 300) {
        throw new Exception('R2 upload failed: ' . ($error ?: $response ?: 'Unknown error'));
    }

    return [
        'success' => true,
        'key' => $key,
        'url' => r2PublicUrl($key),
    ];
}

function r2SigningKey(string $secret, string $dateStamp, string $region)
{
    $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $secret, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', 's3', $kRegion, true);
    return hash_hmac('sha256', 'aws4_request', $kService, true);
}

function r2GuessExtension(string $mime): string
{
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/3gpp' => '3gp',
        'audio/mpeg' => 'mp3',
        'audio/ogg' => 'ogg',
        'application/pdf' => 'pdf',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/msword' => 'doc',
        'application/vnd.ms-excel' => 'xls'
    ];
    return $map[strtolower($mime)] ?? 'bin';
}

function r2DetectMediaType(string $mime): string
{
    $mime = strtolower($mime);
    if (strncmp($mime, 'image/', 6) === 0) {
        return 'image';
    }
    if (strncmp($mime, 'video/', 6) === 0) {
        return 'video';
    }
    if (strncmp($mime, 'audio/', 6) === 0) {
        return 'audio';
    }
    return 'document';
}

function r2BuildRequestUrlAndHost(string $key): array
{
    $cfg = r2Config();
    $bucket = $cfg['bucket'];
    $endpointParts = parse_url($cfg['endpoint']);
    $pathSegments = [];
    if (!empty($endpointParts['path'])) {
        $pathSegments = array_values(array_filter(explode('/', $endpointParts['path']), 'strlen'));
    }
    if (empty($pathSegments) || strtolower(end($pathSegments)) !== strtolower($bucket)) {
        $pathSegments[] = $bucket;
    }
    $objectSegments = array_filter(explode('/', ltrim($key, '/')), 'strlen');
    $encodedSegments = array_map('rawurlencode', array_merge($pathSegments, $objectSegments));
    $requestPath = '/' . implode('/', $encodedSegments);
    $scheme = $endpointParts['scheme'] ?? 'https';
    $baseHost = $endpointParts['host'];
    $port = isset($endpointParts['port']) ? ':' . $endpointParts['port'] : '';
    $baseUrl = $scheme . '://' . $baseHost . $port;
    return [$baseUrl . $requestPath, $baseHost . $port, $requestPath];
}

function r2PresignPut(string $key, string $contentType, int $expires = 900): array
{
    if (!r2IsConfigured()) {
        throw new Exception('Cloudflare R2 storage is not configured.');
    }
    $cfg = r2Config();
    [$requestUrl, $host, $requestPath] = r2BuildRequestUrlAndHost($key);
    $amzDate = gmdate('Ymd\THis\Z');
    $dateStamp = substr($amzDate, 0, 8);
    $payloadHash = 'UNSIGNED-PAYLOAD';
    $headers = [
        'content-type' => $contentType,
        'host' => $host,
        'x-amz-content-sha256' => $payloadHash,
        'x-amz-date' => $amzDate,
    ];
    ksort($headers);
    $canonicalHeaders = '';
    foreach ($headers as $k => $v) { $canonicalHeaders .= strtolower($k) . ':' . trim($v) . "\n"; }
    $signedHeaders = implode(';', array_keys($headers));
    $canonicalRequest = implode("\n", ['PUT', $requestPath, '', $canonicalHeaders, $signedHeaders, $payloadHash]);
    $credentialScope = sprintf('%s/%s/s3/aws4_request', $dateStamp, $cfg['region']);
    $stringToSign = implode("\n", ['AWS4-HMAC-SHA256', $amzDate, $credentialScope, hash('sha256', $canonicalRequest)]);
    $signingKey = r2SigningKey($cfg['secret_key'], $dateStamp, $cfg['region']);
    $signature = hash_hmac('sha256', $stringToSign, $signingKey);
    $query = [
        'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
        'X-Amz-Credential' => rawurlencode($cfg['access_key'] . '/' . $credentialScope),
        'X-Amz-Date' => $amzDate,
        'X-Amz-Expires' => (string)max(1, min($expires, 3600)),
        'X-Amz-SignedHeaders' => $signedHeaders,
        'X-Amz-Signature' => $signature,
    ];
    $url = $requestUrl . '?' . http_build_query($query);
    return ['url' => $url, 'key' => $key, 'headers' => ['Content-Type' => $contentType]];
}

function r2PresignGet(string $key, int $expires = 900): string
{
    if (!r2IsConfigured()) {
        throw new Exception('Cloudflare R2 storage is not configured.');
    }
    $cfg = r2Config();
    [$requestUrl, $host, $requestPath] = r2BuildRequestUrlAndHost($key);
    $amzDate = gmdate('Ymd\THis\Z');
    $dateStamp = substr($amzDate, 0, 8);
    $payloadHash = 'UNSIGNED-PAYLOAD';
    $headers = [
        'host' => $host,
        'x-amz-content-sha256' => $payloadHash,
        'x-amz-date' => $amzDate,
    ];
    ksort($headers);
    $canonicalHeaders = '';
    foreach ($headers as $k => $v) { $canonicalHeaders .= strtolower($k) . ':' . trim($v) . "\n"; }
    $signedHeaders = implode(';', array_keys($headers));
    $canonicalRequest = implode("\n", ['GET', $requestPath, '', $canonicalHeaders, $signedHeaders, $payloadHash]);
    $credentialScope = sprintf('%s/%s/s3/aws4_request', $dateStamp, $cfg['region']);
    $stringToSign = implode("\n", ['AWS4-HMAC-SHA256', $amzDate, $credentialScope, hash('sha256', $canonicalRequest)]);
    $signingKey = r2SigningKey($cfg['secret_key'], $dateStamp, $cfg['region']);
    $signature = hash_hmac('sha256', $stringToSign, $signingKey);
    $query = [
        'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
        'X-Amz-Credential' => rawurlencode($cfg['access_key'] . '/' . $credentialScope),
        'X-Amz-Date' => $amzDate,
        'X-Amz-Expires' => (string)max(1, min($expires, 3600)),
        'X-Amz-SignedHeaders' => $signedHeaders,
        'X-Amz-Signature' => $signature,
    ];
    return $requestUrl . '?' . http_build_query($query);
}


