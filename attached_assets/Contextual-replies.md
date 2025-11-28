Contextual replies
Updated: Oct 21, 2025
Contextual replies are a special way of responding to a WhatsApp user message. Sending a message as a contextual reply makes it clearer to the user which message you are replying to by quoting the previous message in a contextual bubble:

Limitations
You cannot send a reaction message as a contextual reply.
The contextual bubble will not appear at the top of the delivered message if:
The previous message has been deleted or moved to long term storage (messages are typically moved to long term storage after 30 days, unless you have enabled local storage).
You reply with an audio, image, or video message and the WhatsApp user is running KaiOS.
You use the WhatsApp client to reply with a push-to-talk message and the WhatsApp user is running KaiOS.
You reply with a template message.
Request Syntax
POST /<WHATSAPP_BUSINESS_PHONE_NUMBER_ID>/messages
Post Body
{
  "messaging_product": "whatsapp",
  "recipient_type": "individual",
  "to": "<WHATSAPP_USER_PHONE_NUMBER>",
  "context": {
    "message_id": "WAMID_TO_REPLY_TO"
  },

  /* Message type and type contents goes here */

}

Post Body Parameters
Placeholder	Description	Example Value
<WAMID_TO_REPLY_TO>
String
Required.
WhatsApp message ID (wamid) of the previous message you want to reply to.
wamid.HBgLMTY0NjcwNDM1OTUVAgASGBQzQTdCNTg5RjY1MEMyRjlGMjRGNgA=
<WHATSAPP_USER_PHONE_NUMBER>
String
Required.
WhatsApp user phone number.
+16505551234
Example Request
Example of a text message sent as a reply to a previous message.
curl 'https://graph.facebook.com/v19.0/106540352242922/messages' \
-H 'Content-Type: application/json' \
-H 'Authorization: Bearer EAAJB...' \
-d '
{
  "messaging_product": "whatsapp",
  "recipient_type": "individual",
  "to": "+16505551234",
  "context": {
    "message_id": "wamid.HBgLMTY0NjcwNDM1OTUVAgASGBQzQTdCNTg5RjY1MEMyRjlGMjRGNgA="
  },
  "type": "text",
  "text": {
    "body": "You'\''re welcome, Pablo!"
  }
}'
