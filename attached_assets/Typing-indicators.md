Typing indicators
Updated: Oct 21, 2025
When you get a messages webhook indicating a received message, you can use the message.id value to mark the message as read and display a typing indicator so the WhatsApp user knows you are preparing a response. This is good practice if it will take you a few seconds to respond.

The typing indicator will be dismissed once you respond, or after 25 seconds, whichever comes first. To prevent a poor user experience, only display a typing indicator if you are going to respond.
Request syntax
curl -X POST \
'https://graph.facebook.com/<API_VERSION>/<WHATSAPP_BUSINESS_PHONE_NUMBER_ID>/messages'
-H 'Authorization: Bearer <ACCESS_TOKEN>' \
-H 'Content-Type: application/json' \
-d '
{
  "messaging_product": "whatsapp",
  "status": "read",
  "message_id": "<WHATSAPP_MESSAGE_ID>",
  "typing_indicator": {
    "type": "text"
  }
}'

Request parameters
Placeholder	Description	Example value
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
<WHATSAPP_BUSINESS_PHONE_NUMBER_ID>
String
Required.
WhatsApp business phone number ID.
106540352242922
<WHATSAPP_MESSAGE_ID>
String
Required.
WhatsApp message ID. This ID is assigned to the messages.id property in received messagemessages webhooks.
wamid.HBgLMTY1MDM4Nzk0MzkVAgARGBJDQjZCMzlEQUE4OTJBMTE4RTUA
Response
Upon success:
{
  "success": true
}

Example request
curl 'https://graph.facebook.com/v24.0/106540352242922/messages' \
-H 'Content-Type: application/json' \
-H 'Authorization: Bearer EAAJB...' \
-d '
{
  "messaging_product": "whatsapp",
  "status": "read",
  "message_id": "wamid.HBgLMTY1MDM4Nzk0MzkVAgARGBJDQjZCMzlEQUE4OTJBMTE4RTUA",
  "typing_indicator": {
    "type": "text"
  }
}'
Response
Upon success:
{
  "success": true
}
