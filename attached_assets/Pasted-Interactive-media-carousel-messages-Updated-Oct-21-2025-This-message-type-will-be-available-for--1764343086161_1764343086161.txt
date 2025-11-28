Interactive media carousel messages
Updated: Oct 21, 2025
This message type will be available for delivery to WhatsApp users on November 11.
The interactive media carousel message enables businesses to send horizontally scrollable cards with images or videos, each with a call-to-action button, within WhatsApp conversations. This format allows users to browse multiple offers or content in a single message, providing a rich and engaging experience via the WhatsApp Business APIs and mobile clients.
How to build a media carousel message
The media carousel message contains a card object. You must add at least 2 card objects to your message, and can add a maximum of 10. Each card exists in a cards[] array and must be given a card_index value of 0 through 9.
The type of each card must be set to "cta_url", and all cards must have the same header type ("image" or "video"). Each card must include a header, a unique index, and a call-to-action button with display text and a URL.
You must add a message body to the message (max 1024 characters). No header, footer, or buttons are allowed outside the cards.
The card object
...
{
  "card_index": 0,
  "type": "cta_url",
  "header": {
    "type": "image",
    "image": {
      "link": "https://example.com/image1.png"
    }
  },
  "body": {
    "text": "Exclusive deal #1"
  },
  "action": {
    "name": "cta_url",
    "parameters": {
      "display_text": "Shop now",
      "url": "https://shop.example.com/deal1"
    }
  }
}
...

Request Syntax
curl 'https://graph.facebook.com/<API_VERSION>/<WHATSAPP_BUSINESS_PHONE_NUMBER_ID>/messages' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer <ACCESS_TOKEN>' \
  -d '{
    "messaging_product": "whatsapp",
    "recipient_type": "individual",
    "to": "<PHONE_NUMBER>",
    "type": "interactive",
    "interactive": {
      "type": "carousel",
      "body": {
        "text": "Check out our latest offers!"
      },
      "action": {
        "cards": [
          {
            "card_index": 0,
            "type": "cta_url",
            "header": {
              "type": "image",
              "image": {
                "link": "https://example.com/image1.png"
              }
            },
            "body": {
              "text": "Exclusive deal #1"
            },
            "action": {
              "name": "cta_url",
              "parameters": {
                "display_text": "Shop now",
                "url": "https://shop.example.com/deal1"
              }
            }
          },
          {
            "card_index": 1,
            "type": "cta_url",
            "header": {
              "type": "image",
              "image": {
                "link": "https://example.com/image2.png"
              }
            },
            "body": {
              "text": "Exclusive deal #2"
            },
            "action": {
              "name": "cta_url",
              "parameters": {
                "display_text": "Shop now",
                "url": "https://shop.example.com/deal2"
              }
            }
          }
        ]
      }
    }
  }'

Request parameters
Field	Description	Sample Value
<API_VERSION>
String
Required
API version to use
v19.0
<WHATSAPP_BUSINESS_PHONE_NUMBER_ID>
String
Required
WhatsApp Business phone number ID
1234567890
<ACCESS_TOKEN>
String
Required
Access token for authentication
EAAG...
<PHONE_NUMBER>
String
Recipient’s WhatsApp phone number
16505551234
<MESSAGE_BODY_TEXT>
String
Required. Maximum 1024 characters.
Check out our latest offers!
Card Object Parameters
Field	Description	Sample Value
card_index
Integer
Required
Unique index for each card (0-9).
0
type
String
Required
Must be "cta_url".
"cta_url"
header.type
String
Required
"image" or "video" (all cards must match).
"image"
header.image.link
String
Required if header.type is "image".
"https://example.com/image1.png"
header.video.link
String
Required if header.type is "video".
"https://example.com/video1.png"
body.text
String
Optional
Max 160 chars, and up to 2 line breaks.
"Exclusive deal #1"
action.name
String
Required
Must be "cta_url".
"cta_url"
action.parameters.display_text
String
Button display text. Max 20 chars.
"Shop now"
action.parameters.url
String
Button URL
"https://shop.example.com/deal1"
Example Request
{
  "messaging_product": "whatsapp",
  "recipient_type": "individual",
  "to": "1234567890",
  "type": "interactive",
  "interactive": {
    "type": "carousel",
    "body": {
      "text": "Check out our latest offers!"
    },
    "action": {
      "cards": [
        {
          "card_index": 0,
          "type": "cta_url",
          "header": {
            "type": "image",
            "image": {
              "link": "https://example.com/image1.png"
            }
          },
          "body": {
            "text": "Exclusive deal #1"
          },
          "action": {
            "name": "cta_url",
            "parameters": {
              "display_text": "Shop now",
              "url": "https://shop.example.com/deal1"
            }
          }
        },
        {
          "card_index": 1,
          "type": "cta_url",
          "header": {
            "type": "image",
            "image": {
              "link": "https://example.com/image2.png"
            }
          },
          "body": {
            "text": "Exclusive deal #2"
          },
          "action": {
            "name": "cta_url",
            "parameters": {
              "display_text": "Shop now",
              "url": "https://shop.example.com/deal2"
            }
          }
        }
      ]
    }
  }
}

Example Response
{
  "messaging_product": "whatsapp",
  "contacts": [
    {
      "input": "+16505551234",
      "wa_id": "16505551234"
    }
  ],
  "messages": [
    {
      "id": "wamid.HBgLMTY0NjcwNDM1OTUVAgARGBI1RjQyNUE3NEYxMzAzMzQ5MkEA"
    }
  ]
}
