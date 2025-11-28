<?php
// WhatsApp Interactive Messages API - Button, List, Product, Carousel

function whatsappSendButtonMessage($phoneId, $accessToken, $to, $bodyText, $buttons, $headerText = null, $footerText = null) {
  $buttons_array = array_map(function($btn) {
    return [
      'type' => 'reply',
      'reply' => [
        'id' => $btn['id'],
        'title' => $btn['title']
      ]
    ];
  }, $buttons);

  $interactive = [
    'type' => 'button',
    'body' => ['text' => $bodyText],
    'action' => ['buttons' => $buttons_array]
  ];

  if ($headerText) $interactive['header'] = ['type' => 'text', 'text' => $headerText];
  if ($footerText) $interactive['footer'] = ['text' => $footerText];

  return whatsappSendMessage($phoneId, $accessToken, $to, 'interactive', ['interactive' => $interactive]);
}

function whatsappSendListMessage($phoneId, $accessToken, $to, $bodyText, $sections, $buttonText = 'Options') {
  $interactive = [
    'type' => 'list',
    'body' => ['text' => $bodyText],
    'action' => [
      'button' => $buttonText,
      'sections' => $sections
    ]
  ];

  return whatsappSendMessage($phoneId, $accessToken, $to, 'interactive', ['interactive' => $interactive]);
}

function whatsappSendProductMessage($phoneId, $accessToken, $to, $products, $bodyText = null) {
  $interactive = [
    'type' => 'product',
    'action' => ['products' => $products]
  ];

  if ($bodyText) $interactive['body'] = ['text' => $bodyText];

  return whatsappSendMessage($phoneId, $accessToken, $to, 'interactive', ['interactive' => $interactive]);
}

function whatsappSendMediaCarousel($to, $cards, $bodyText, $phoneNumberIdOverride = null) {
  require_once 'whatsapp_cloud.php';
  
  $token = whatsappToken();
  $pnId = $phoneNumberIdOverride ?: whatsappPhoneNumberId();
  
  if (!$token || !$pnId) {
    return ['success' => false, 'message' => 'WhatsApp not configured'];
  }
  
  // Validate cards (2-10 cards required)
  if (count($cards) < 2 || count($cards) > 10) {
    return ['success' => false, 'message' => 'Carousel must have 2-10 cards'];
  }
  
  // All cards must have same header type
  $headerType = null;
  foreach ($cards as $card) {
    $cardHeaderType = $card['header']['type'] ?? null;
    if (!$cardHeaderType || !in_array($cardHeaderType, ['image', 'video'])) {
      return ['success' => false, 'message' => 'All cards must have image or video header'];
    }
    if ($headerType === null) {
      $headerType = $cardHeaderType;
    } elseif ($headerType !== $cardHeaderType) {
      return ['success' => false, 'message' => 'All cards must have the same header type'];
    }
  }
  
  $formatted_cards = array_map(function($card, $idx) {
    return [
      'card_index' => $idx,
      'type' => 'cta_url',
      'header' => $card['header'],
      'body' => $card['body'] ?? ['text' => ''],
      'action' => [
        'name' => 'cta_url',
        'parameters' => [
          'display_text' => substr($card['action']['display_text'] ?? 'View', 0, 20),
          'url' => $card['action']['url'] ?? ''
        ]
      ]
    ];
  }, $cards, array_keys($cards));

  $url = 'https://graph.facebook.com/' . whatsappGraphVersion() . '/' . $pnId . '/messages';
  $payload = [
    'messaging_product' => 'whatsapp',
    'recipient_type' => 'individual',
    'to' => $to,
    'type' => 'interactive',
    'interactive' => [
      'type' => 'carousel',
      'body' => ['text' => substr($bodyText, 0, 1024)],
      'action' => ['cards' => $formatted_cards]
    ]
  ];

  return whatsappPost($url, $payload);
}

function whatsappSendTypingIndicator($phoneId, $accessToken, $to) {
  $body = [
    'messaging_product' => 'whatsapp',
    'status' => 'typing'
  ];

  $headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $accessToken
  ];

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => "https://graph.facebook.com/v21.0/$phoneId/messages",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($body),
    CURLOPT_HTTPHEADER => $headers
  ]);

  $response = curl_exec($ch);
  curl_close($ch);

  return json_decode($response, true);
}

function whatsappSendContextualReply($phoneId, $accessToken, $to, $message, $contextMessageId) {
  $body = [
    'messaging_product' => 'whatsapp',
    'recipient_type' => 'individual',
    'to' => $to,
    'context' => ['message_id' => $contextMessageId],
    'type' => 'text',
    'text' => ['body' => $message]
  ];

  $headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $accessToken
  ];

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => "https://graph.facebook.com/v21.0/$phoneId/messages",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($body),
    CURLOPT_HTTPHEADER => $headers
  ]);

  $response = curl_exec($ch);
  curl_close($ch);

  return json_decode($response, true);
}
?>
