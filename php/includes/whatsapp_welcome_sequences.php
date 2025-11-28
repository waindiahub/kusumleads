<?php
// WhatsApp Welcome Message Sequences for Click-to-WhatsApp Ads

function whatsappCreateWelcomeSequence($wabaId, $accessToken, $name, $welcomeMessage) {
  $body = [
    'name' => $name,
    'welcome_message_sequence' => $welcomeMessage
  ];

  $headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $accessToken
  ];

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => "https://graph.facebook.com/v21.0/$wabaId/welcome_message_sequences",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($body),
    CURLOPT_HTTPHEADER => $headers
  ]);

  $response = curl_exec($ch);
  curl_close($ch);

  return json_decode($response, true);
}

function whatsappGetWelcomeSequences($wabaId, $accessToken, $sequenceId = null) {
  $headers = ['Authorization: Bearer ' . $accessToken];
  
  $url = "https://graph.facebook.com/v21.0/$wabaId/welcome_message_sequences";
  if ($sequenceId) $url .= "?sequence_id=$sequenceId";

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers
  ]);

  $response = curl_exec($ch);
  curl_close($ch);

  return json_decode($response, true);
}

function whatsappUpdateWelcomeSequence($wabaId, $accessToken, $sequenceId, $updates) {
  $body = array_merge(['sequence_id' => $sequenceId], $updates);

  $headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $accessToken
  ];

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => "https://graph.facebook.com/v21.0/$wabaId/welcome_message_sequences",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($body),
    CURLOPT_HTTPHEADER => $headers
  ]);

  $response = curl_exec($ch);
  curl_close($ch);

  return json_decode($response, true);
}

function whatsappDeleteWelcomeSequence($wabaId, $accessToken, $sequenceId) {
  $body = ['sequence_id' => $sequenceId];

  $headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $accessToken
  ];

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => "https://graph.facebook.com/v21.0/$wabaId/welcome_message_sequences",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_POSTFIELDS => json_encode($body),
    CURLOPT_HTTPHEADER => $headers
  ]);

  $response = curl_exec($ch);
  curl_close($ch);

  return json_decode($response, true);
}
?>
