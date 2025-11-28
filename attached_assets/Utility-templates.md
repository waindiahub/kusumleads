Utility templates
Updated: Nov 14, 2025
This document describes how to create and send utility templates.
Utility templates are typically sent in response to a user action or request, such as an order confirmation or update.

Utility templates have strict content requirements, particularly around marketing material. If you attempt to create or update a utility template with marketing material, the template will automatically be re-categorized as a marketing template.
See our template categorization documentation for content guidelines.
Supported components
Utility templates support the following components:
1 header (optional; all types supported)
1 body
1 footer (optional)
Up to 10 buttons (optional). Supported types:
Call request
Copy code
Phone number
Quick-reply
URL
Create a utility template
Request syntax
Use the POST/<WHATSAPP_BUSINESS_ACCOUNT_ID>/message_templates endpoint to create a utility template.
curl 'https://graph.facebook.com/<API_VERSION>/<WHATSAPP_BUSINESS_ACCOUNT_ID>/message_templates' \
-H 'Content-Type: application/json' \
-H 'Authorization: Bearer <ACCESS_TOKEN>' \
-d '
{
  "name": "<TEMPLATE_NAME>",
  "language": "<TEMPLATE_LANGUAGE>",
  "category": "utility",
  "parameter_format": "<PARAMETER_FORMAT>",
  "components": [

    <!-- header component optional -->
    {
      "type": "header",
      "format": "<HEADER_TYPE>",
      "example": {
        "header_handle": [
          "<HEADER_HANDLE>"
        ]
      }
    },

    <!-- body component required -->
    {
      "type": "body",
      "text": "<BODY_TEXT>",

      <!-- example required if <BODY_TEXT> contains one or more parameters -->
      "example": {
        "body_text_named_params": [
          {
            "param_name": "<PARAMETER_NAME>",
            "example": "<PARAMETER_EXAMPLE_VALUE>"
          },

          <!-- additional parameters would follow, if using multiple parameters -->
        ]
      }
    },

    <!-- footer component optional -->
    {
      "type": "footer",
      "text": "<FOOTER_TEXT>"
    },

    <!-- button components optional -->
    {
      "type": "buttons",
      "buttons": [
        {
          "type": "url",
          "text": "<URL_BUTTON_LABEL_TEXT>",
          "url": "<URL>"
        },
        {
          "type": "phone_number",
          "text": "<PHONE_BUTTON_LABEL_TEXT>",
          "phone_number": "<PHONE_NUMBER>"
        },
        {
          "type": "quick_reply",
          "text": "<QUICK_REPLY_BUTTON_LABEL_TEXT>"
        }
      ]
    }
  ]
}'

Request parameters
Placeholder	Description	Example Value
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

Template body text. Variables are supported.

Maximum 1024 characters.
You're all set! Your reservation for {{number_of_guests}} at Lucky Shrub Eatery on {{day}}, {{date}}, at {{time}}, is confirmed. See you then!
<FOOTER_TEXT>
String
Optional.
Template footer text. Variables are supported
Maximum 60 characters.
Lucky Shrub Eatery: The Luckiest Eatery in Town!
<HEADER_ASSET_HANDLE>
String
Required if using a header with a media asset.
Asset handle of example media asset uploaded on your WhatsApp Business Account.
Maximum 60 characters.
4::aW1hZ2UvcG5n:ARYpf5zqqUjggwGfsZOJ2_o26Zs8ntcO2mss2vKpFb8P_IvskL043YXKpehYTD7IxqEB4t-uZcIzOTxOFRavEcN_tZLhk1WXFb3IOr4S8UKJcQ:e:1759093121:634974688087057:100089620928913:ARYyOAh63uQLhDpqOdk\n4::aW1hZ2UvcG5n:ARZW8t9-cBNjpdmxV5Z9wcRAMhfmw4ATpJcJiHT0nY62hXq4ppOeBaTWaGI0IwX-twF2IkeKo-_MyW2pEDuBAE5vyw2oHTNgPZQkntclrgWMGg:e:1759093121:634974688087057:100089620928913:ARZE4NC5MrxnZUe5GRw
<HEADER_TYPE>
String
Required if using a header.
Header format. Values can be:
documentation
image
location
text
video
image
<PARAMETER_EXAMPLE_VALUE>
String
Required if using a body component string that includes one or more parameters.
Example parameter value. You must supply an example for each parameter defined in your body component string.
Saturday
<PARAMETER_NAME>
String
Required if using named parameters.
Must be a unique string, composed of lowercase characters and underscores, wrapped in double curly brackets.
{{day}}
<PHONE_BUTTON_LABEL_TEXT>
String
Required if using a phone number button.
Button label text.
Maximum 25 characters. Alphanumeric characters only.
Change reservation
<PHONE_NUMBER>
String
Required if using a phone number button component.
Business phone number to be called in the WhatsApp user’s default phone app when tapped by the user.
Note that some countries have special phone numbers that have leading zeros after the country calling code (e.g., +55-0-955-585-95436). If you assign one of these numbers to the button, the leading zero will be stripped from the number. If your number will not work without the leading zero, assign an alternate number to the button, or add the number as message
Maximum 20 characters. Alphanumeric characters only.
15550051310
<QUICK_REPLY_BUTTON_LABEL_TEXT>
Required if using a quick-reply button.
Button label text.
Maximum 25 characters. Alphanumeric characters only.
Cancel reservation
<TEMPLATE_LANGUAGE>
String
Required.
Template language code.
en_US
<TEMPLATE_NAME>
String
Required.
Template name. Must be unique, unless existing templates with the same name have a different template language.
Maximum 512 characters. Lowercase, alphanumeric characters and underscores only.
reservation_confirmation
<URL>
String
Required if including a URL button.
URL to be loaded in WhatsApp user’s default web browser when tapped.
https://www.luckyshrubeater.com/reservations
<URL_BUTTON_LABEL_TEXT>
String
Required if using a URL button.
Button label text.
Maximum 25 characters. Alphanumeric characters only.
Change reservation
<WHATSAPP_BUSINESS_ACCOUNT_ID>
Required.
WhatsApp Business Account ID.
546151681022936
Response syntax
Upon success:
{
  "id": "<TEMPLATE_ID>",
  "status": "<TEMPLATE_STATUS>",
  "category": "<TEMPLATE_CATEGORY>"
}

Response parameters
Placeholder	Description	Example value
<TEMPLATE_CATEGORY>
Template category.
UTILITY
<TEMPLATE_ID>
Template ID.
546151681022936
<TEMPLATE_STATUS>
Template status.
PENDING
Example request
This example request creates a utility template with:
an image header component
a body component with a string that has 4 named parameters
a footer component
a URL button component
a phone number button component
a quick-reply button component
curl 'https://graph.facebook.com/v23.0/102290129340398/message_templates' \
-H 'Content-Type: application/json' \
-H 'Authorization: Bearer EAAJB...' \
-d '
{
  "name": "reservation_confirmation",
  "language": "en_US",
  "category": "utility",
  "parameter_format": "named",
  "components": [
    {
      "type": "header",
      "format": "image",
      "example": {
        "header_handle": [
          "4::aW..."
        ]
      }
    },
    {
      "type": "body",
      "text": "*You're all set!*\n\nYour reservation for {{number_of_guests}} at Lucky Shrub Eatery on {{day}}, {{date}}, at {{time}}, is confirmed. See you then!",
      "example": {
        "body_text_named_params": [
          {
            "param_name": "number_of_guests",
            "example": "4"
          },
          {
            "param_name": "day",
            "example": "Saturday"
          },
          {
            "param_name": "date",
            "example": "August 30th, 2025"
          },
          {
            "param_name": "time",
            "example": "7:30 pm"
          }
        ]
      }
    },
    {
      "type": "footer",
      "text": "Lucky Shrub Eatery: The Luckiest Eatery in Town!"
    },
    {
      "type": "buttons",
      "buttons": [
        {
          "type": "url",
          "text": "Change reservation",
          "url": "https://www.luckyshrubeater.com/reservations"
        },
        {
          "type": "phone_number",
          "text": "Call us",
          "phone_number": "+15550051310"
        },
        {
          "type": "quick_reply",
          "text": "Cancel reservation"
        }
      ]
    }
  ]
}'

Example response
{
  "id": "546151681022936",
  "status": "PENDING",
  "category": "UTILITY"
}

Send a utility template
Request syntax
Use the 
POST/<WHATSAPP_BUSINESS_PHONE_NUMBER_ID>/message
 endpoint to send an approved utility template in template message.
curl 'https://graph.facebook.com/<API_VERSION>/<WHATSAPP_BUSINESSS_PHONE_NUMBER_ID>/messages' \
-H 'Content-Type: application/json' \
-H 'Authorization: Bearer <ACCESS_TOKEN>' \
-d '
{
  "messaging_product": "whatsapp",
  "recipient_type": "individual",
  "to": "<WHATSAPP_USER_PHONE_NUMBER>",
  "type": "template",
  "template": {
    "name": "<TEMPLATE_NAME>",
    "language": {
      "code": "<TEMPLATE_LANGUAGE>"
    },
    "components": [

      <!-- Only required if the template uses a media header component -->
      {
        "type": "header",
        "parameters": [
          {
            "type": "<MEDIA_HEADER_TYPE>",
            "<MEDIA_HEADER_TYPE>": {
              "id": "<MEDIA_HEADER_ASSET_ID>"
            }
          }
        ]
      },

      <!-- Only required if the template uses body component parameters -->
      {
        "type": "body",
        "parameters": [
          {
            "type": "<NAMED_PARAM_TYPE>",
            "parameter_name": "<NAMED_PARAM_NAME>",
            "text": "<NAMED_PARAM_VALUE>"
          },

          <!-- Additional parameters values would follow, if needed -->

        ]
      }
    ]
  }
}'

Request parameters
Placeholder	Description	Example Value
<ACCESS_TOKEN>
String
Required.
System token or business token.
EAAAN6tcBzAUBOZC82CW7iR2LiaZBwUHS4Y7FDtQxRUPy1PHZClDGZBZCgWdrTisgMjpFKiZAi1FBBQNO2IqZBAzdZAA16lmUs0XgRcCf6z1LLxQCgLXDEpg80d41UZBt1FKJZCqJFcTYXJvSMeHLvOdZwFyZBrV9ZPHZASSqxDZBUZASyFdzjiy2A1sippEsF4DVV5W2IlkOSr2LrMLuYoNMYBy8xQczzOKDOMccqHEZD
<API_VERSION>
String
Optional
API version. If omitted, defaults to the newest API version available to your app.
v24.0
<MEDIA_HEADER_ASSET_ID>
String
Required if template uses a media header component.
2871834006348767
<MEDIA_HEADER_TYPE>
String
Required if template uses a media header component.
Media header type. Values can be:
document
image
video
Note that this placeholder appears twice in the request syntax above.
image
<NAMED_PARAM_NAME>
Required if template uses body component parameters.
Name of parameter as defined in the template body component text string.
number_of_guests
<NAMED_PARAM_TYPE>
Required if template uses body component parameters.
Parameter type. Set to text.
text
<NAMED_PARAM_VALUE>
Required if template uses body component parameters.
Parameter value.
4
<TEMPLATE_LANGUAGE>
String
Required.
Template language code.
en_US
<TEMPLATE_NAME>
String
Required.
Template name. Must be unique, unless existing templates with the same name have a different template language.
Maximum 512 characters. Lowercase, alphanumeric characters and underscores only.
reservation_confirmation
<WHATSAPP_BUSINESS_ACCOUNT_ID>
Required.
WhatsApp Business Account ID.
546151681022936
<WHATSAPP_USER_PHONE_NUMBER>
Required.
WhatsApp user phone number.
16505551234
Response syntax
Upon success:
{
  "messaging_product": "whatsapp",
  "contacts": [
    {
      "input": "<WHATSAPP_USER_PHONE_NUMBER>",
      "wa_id": "<WHATSAPP_USER_ID>"
    }
  ],
  "messages": [
    {
      "id": "<WHATSAPP_MESSAGE_ID>",
      "message_status": "<PACING_STATUS>"
    }
  ]
}

Response parameters
Placeholder	Description	Example Value
<PACING_STATUS>
Template pacing status.
accepted
<WHATSAPP_MESSAGE_ID>
WhatsApp Message ID.
This ID is included in status messages webhooks for delivery status purposes.
wamid.HBgLMTY1MDM4Nzk0MzkVAgARGBJBRkJENzExMTRFRjk2NTI1OTEA
<WHATSAPP_USER_ID>
WhatsApp user’s WhatsApp ID. May not match input value.
16505551234
<WHATSAPP_USER_PHONE_NUMBER>
WhatsApp user’s WhatsApp phone number. May not match wa_id value.
16505551234
Example request
This is an example request that sends the template created in the example template creation request above.
curl 'https://graph.facebook.com/v23.0/106540352242922/messages' \
-H 'Content-Type: application/json' \
-H 'Authorization: Bearer EAAJB...' \
-d '
{
  "messaging_product": "whatsapp",
  "recipient_type": "individual",
  "to": "16505551234",
  "type": "template",
  "template": {
    "name": "reservation_confirmation",
    "language": {
      "code": "en_US"
    },
    "components": [
      {
        "type": "header",
        "parameters": [
          {
            "type": "image",
            "image": {
              "id": "2871834006348767"
            }
          }
        ]
      },
      {
        "type": "body",
        "parameters": [
          {
            "type": "text",
            "parameter_name": "number_of_guests",
            "text": "4"
          },
          {
            "type": "text",
            "parameter_name": "day",
            "text": "Saturday"
          },
          {
            "type": "text",
            "parameter_name": "date",
            "text": "August 30th, 2025"
          },
          {
            "type": "text",
            "parameter_name": "time",
            "text": "7:30 pm"
          }
        ]
      }
    ]
  }
}'

Example response
{
  "messaging_product": "whatsapp",
  "contacts": [
    {
      "input": "16505551234",
      "wa_id": "16505551234"
    }
  ],
  "messages": [
    {
      "id": "wamid.HBgLMTY1MDM4Nzk0MzkVAgARGBJBRkJENzExMTRFRjk2NTI1OTEA",
      "message_status": "accepted"
    }
  ]
}
