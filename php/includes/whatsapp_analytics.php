<?php
// WhatsApp Analytics API

function whatsappGetAnalytics($wabaId, $accessToken, $startTime, $endTime, $granularity = 'DAY', $phoneNumbers = [], $productTypes = []) {
  $fields = "analytics.start($startTime).end($endTime).granularity($granularity)";
  
  if (!empty($phoneNumbers)) {
    $phones_json = json_encode($phoneNumbers);
    $fields .= ".phone_numbers($phones_json)";
  }
  if (!empty($productTypes)) {
    $types_json = json_encode($productTypes);
    $fields .= ".product_types($types_json)";
  }

  $headers = ['Authorization: Bearer ' . $accessToken];

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => "https://graph.facebook.com/v21.0/$wabaId?fields=$fields",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers
  ]);

  $response = curl_exec($ch);
  curl_close($ch);

  return json_decode($response, true);
}

function whatsappGetConversationAnalytics($wabaId, $accessToken, $startTime, $endTime, $granularity = 'DAILY', $phoneNumbers = [], $dimensions = []) {
  $dimensions_default = ['CONVERSATION_CATEGORY', 'CONVERSATION_TYPE', 'COUNTRY'];
  $dimensions = !empty($dimensions) ? $dimensions : $dimensions_default;

  $fields = "conversation_analytics.start($startTime).end($endTime).granularity($granularity)";
  
  $phones_json = json_encode($phoneNumbers);
  $dims_json = json_encode($dimensions);
  
  $fields .= ".phone_numbers($phones_json).dimensions($dims_json)";

  $headers = ['Authorization: Bearer ' . $accessToken];

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => "https://graph.facebook.com/v21.0/$wabaId?fields=$fields",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers
  ]);

  $response = curl_exec($ch);
  curl_close($ch);

  return json_decode($response, true);
}

function whatsappGetPricingAnalytics($wabaId, $accessToken, $startTime, $endTime, $granularity = 'DAILY') {
  $fields = "pricing_analytics.start($startTime).end($endTime).granularity($granularity).dimensions([\"PRICING_CATEGORY\",\"PRICING_TYPE\",\"COUNTRY\"])";

  $headers = ['Authorization: Bearer ' . $accessToken];

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => "https://graph.facebook.com/v21.0/$wabaId?fields=$fields",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers
  ]);

  $response = curl_exec($ch);
  curl_close($ch);

  return json_decode($response, true);
}
?>
