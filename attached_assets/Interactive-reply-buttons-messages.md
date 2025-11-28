Interactive reply buttons messages
Updated: Nov 3, 2025
Interactive reply buttons messages allow you to send up to three predefined replies for users to choose from.

Users can respond to a message by selecting one of the predefined buttons, which triggers a messages webhook describing their selection.

Request syntax
Use the POST /<WHATSAPP_BUSINESS_PHONE_NUMBER_ID>/messages endpoint to send an interactive reply buttons message to a WhatsApp user.
curl 'https://graph.facebook.com/<API_VERSION>/<WHATSAPP_BUSINESS_PHONE_NUMBER_ID>/messages' \
-H 'Content-Type: application/json' \
-H 'Authorization: Bearer <ACCESS_TOKEN>' \
-d '
{
  "messaging_product": "whatsapp",
  "recipient_type": "individual",
  "to": "<WHATSAPP_USER_PHONE_NUMBER>",
  "type": "interactive",
  "interactive": {
    "type": "button",
    "header": {<MESSAGE_HEADER>},
    "body": {
      "text": "<BODY_TEXT>"
    },
    "footer": {
      "text": "<FOOTER_TEXT>"
    },
    "action": {
      "buttons": [
        {
          "type": "reply",
          "reply": {
            "id": "<BUTTON_ID>",
            "title": "<BUTTON_LABEL_TEXT>"
          }
        }
        <!-- Additional buttons would go here (maximum 3) -->
      ]
    }
  }
}'

Request parameters
Placeholder	Description	Sample Value
<ACCESS_TOKEN>
String
Required.
System token or business token.
EAAAN6tcBzAUBOZC82CW7iR2LiaZBwUHS4Y7FDtQxRUPy1PHZClDGZBZCgWdrTisgMjpFKiZAi1FBBQNO2IqZBAzdZAA16lmUs0XgRcCf6z1LLxQCgLXDEpg80d41UZBt1FKJZCqJFcTYXJvSMeHLvOdZwFyZBrV9ZPHZASSqxDZBUZASyFdzjiy2A1sippEsF4DVV5W2IlkOSr2LrMLuYoNMYBy8xQczzOKDOMccqHEZD
<API_VERSION>
String
Optional.
Graph API version.
v24.0
<BODY_TEXT>
String
Required.
Body text. URLs are automatically hyperlinked.
Maximum 1024 characters.
Hi Pablo! Your gardening workshop is scheduled for 9am tomorrow. Use the buttons if you need to reschedule. Thank you!
<BUTTON_ID>
String
Required.
A unique identifier for each button. Supports up to 3 buttons.
Maximum 256 characters.
change-button
<BUTTON_LABEL_TEXT>
String
Required.
Button label text. Must be unique if using multiple buttons.
Maximum 20 characters.
Change
<FOOTER_TEXT>
String
Required if using a footer.
Footer text. URLs are automatically hyperlinked.
Maximum 60 characters.
Lucky Shrub: Your gateway to succulents!™
<MESSAGE_HEADER>
JSON Object
Optional.
Header content. Supports the following types:
document
image
text
video
Media assets can be sent using their uploaded mediaid or URL link (not recommended).
Image header example using uploaded media ID (same basic structure for all media types):

{
"type": "image",
"image": {
"id": "2762702990552401"
}
Image header example using hosted media:

{
"type": "image",
"image": {
"link": "https://www.luckyshrub.com/media/workshop-banner.png"
}
Text header example:

{
"type":"text",
"text": "Workshop Details"
}
<WHATSAPP_BUSINESS_PHONE_NUMBER_ID>
String
Required.
WhatsApp business phone number ID.
106540352242922
<WHATSAPP_USER_PHONE_NUMBER>
String
Required.
WhatsApp user phone number.
+16505551234
Example Request
Example request to send an interactive reply buttons message with an image header, body text, footer text, and two quick-reply buttons.
curl 'https://graph.facebook.com/v24.0/106540352242922/messages' \
-H 'Content-Type: application/json' \
-H 'Authorization: Bearer EAAJB...' \
-d '
{
  "messaging_product": "whatsapp",
  "recipient_type": "individual",
  "to": "+16505551234",
  "type": "interactive",
  "interactive": {
    "type": "button",
    "header": {
      "type": "image",
      "image": {
        "id": "2762702990552401"
      }
    },
    "body": {
      "text": "Hi Pablo! Your gardening workshop is scheduled for 9am tomorrow. Use the buttons if you need to reschedule. Thank you!"
    },
    "footer": {
      "text": "Lucky Shrub: Your gateway to succulents!™"
    },
    "action": {
      "buttons": [
        {
          "type": "reply",
          "reply": {
            "id": "change-button",
            "title": "Change"
          }
        },
        {
          "type": "reply",
          "reply": {
            "id": "cancel-button",
            "title": "Cancel"
          }
        }
      ]
    }
  }
}'
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

Webhooks
When a WhatsApp user taps on a reply button, a messages webhook is triggered that describes their selection in a button_reply object:
"button_reply": {
  "id": "<BUTTON_ID>",
  "title": "<BUTTON_LABEL_TEXT>"
}

<BUTTON_ID> — The button ID of the button tapped by the user.
<BUTTON_LABEL_TEXT> — The button label text of the button tapped by the user.
Example Webhook
{
  "object": "whatsapp_business_account",
  "entry": [
    {
      "id": "102290129340398",
      "changes": [
        {
          "value": {
            "messaging_product": "whatsapp",
            "metadata": {
              "display_phone_number": "15550783881",
              "phone_number_id": "106540352242922"
            },
            "contacts": [
              {
                "profile": {
                  "name": "Pablo Morales"
                },
                "wa_id": "16505551234"
              }
            ],
            "messages": [
              {
                "context": {
                  "from": "15550783881",
                  "id": "wamid.HBgLMTY0NjcwNDM1OTUVAgARGBJBM0Y4RUU0RUNFQkFDMjYzQUMA"
                },
                "from": "16505551234",
                "id": "wamid.HBgLMTY0NjcwNDM1OTUVAgASGBQzQThBREYwNzc2RDc2QjA1QTIwMgA=",
                "timestamp": "1714510003",
                "type": "interactive",
                "interactive": {
                  "type": "button_reply",
                  "button_reply": {
                    "id": "change-button",
                    "title": "Change"
                  }
                }
              }
            ]
          },
          "field": "messages"
        }
      ]
    }
  ]
}
