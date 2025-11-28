User-initiated calls
Updated: Nov 13, 2025
Overview
The Calling API supports receiving calls made by WhatsApp users to your business.
Your business dictates when calls can be received by configuring business calling hours and holiday unavailability.
Consumer device eligibility
Currently, the WhatsApp Business Calling API can only accept calls that originate from a consumer’s primary device. Calls originating from a consumer’s companion devices will be rejected.
A primary device is the consumer’s main device, typically a mobile phone, which holds the authoritative state for the user’s account. It has full access to messaging history and core functionalities. There is exactly one primary device per user account at any given time.
Companion devices are additional devices registered to the user’s account that can operate alongside the primary device. Examples include web clients, desktop apps, tablets, and smart glasses. Companion devices have access to some or all messaging history and core features but are limited compared to the primary device. They can function independently for a period but currently are not supported for calls.
Prerequisites
Before you get started with user-initiated calling, ensure that:
Subscribe to the calls webhook field
Enable Calling API features on your business phone number
Call sequence diagram
Image
User-initiated calling flow
Part 1: A WhatsApp user calls your business from their client app
When a WhatsApp user calls your business, a Call Connect webhook will be triggered with an SDP Offer:
{
  "object": "whatsapp_business_account",
  "entry": [
    {
      "id": "366634483210360", // WhatsApp Business Account ID associated with the business phone number
      "changes": [
        {
          "value": {
            "messaging_product": "whatsapp",
            "metadata": { // ID and display number for the business phone number placing the call (caller)
              "phone_number_id": "436666719526789",
              "display_phone_number": "13175551399",
            },
            "calls": [
              {
                "id": "wacid.ABGGFjFVU2AfAgo6V-Hc5eCgK5Gh", // The WhatsApp call ID
                "to": "16315553601", // The WhatsApp user's phone number (callee)
                "from": "13175551399",
                "event": "connect",
                "timestamp": "1671644824",
                "session": {
                  "sdp_type": "offer",
                  "sdp": "<<RFC 8866 SDP>>"
                }
              }
            ]
          },
          "field": "calls"
        }
      ]
    }
  ]
}
Part 2: Your business pre-accepts the call (Recommended)
In essence, when you pre-accept an inbound call, you are allowing the calling media connection to be established before attempting to send call media through the connection.
Pre-accepting calls is recommended because it facilitates faster connection times and avoids audio clipping issues.
To pre-accept, You call the POST <PHONE_NUMBER_ID>/calls endpoint with the call_id from the previous webhook, an action of pre-accept, and an SDP Answer:
POST <PHONE_NUMBER_ID>/calls
{
  "messaging_product": "whatsapp",
  "call_id": "wacid.ABGGFjFVU2AfAgo6V-Hc5eCgK5Gh",
  "action": "pre_accept",
  "session": {
     "sdp_type": "answer"
     "sdp": "<<RFC 8866 SDP>>"
  }
}
If there are no errors, you’ll receive a success response:
{
  "success" : true
}
Part 3: Your business accepts the call after the WebRTC connection is made
Once the WebRTC connection is made on your end, you can accept the call.
Once you accept the call, wait until you receive a 200 OK back from the endpoint. Media will begin flowing immediately since the connection was established prior to call connect.
You can now call the POST <PHONE_NUMBER_ID>/calls endpoint with the following request body to accept the call:
POST <PHONE_NUMBER_ID>/calls
{
  "messaging_product": "whatsapp",
  "call_id": "wacid.ABGGFjFVU2AfAgo6V-Hc5eCgK5Gh",
  "action": "accept",
  "session" : {
      "sdp_type" : "answer",
      "sdp" : "<<RFC 8866 SDP>>"
   },
}
Part 4: Your business or the WhatsApp user terminates the call
Both the business or the WhatsApp user can terminate the call at any time.
You call the POST <PHONE_NUMBER_ID>/calls endpoint with the following request body to terminate the call:
POST <PHONE_NUMBER_ID>/calls
{
  "messaging_product": "whatsapp",
  "call_id": "wacid.ABGGFjFVU2AfAgo6V-Hc5eCgK5Gh",
  "action" : "terminate"
}
If there are no errors, you’ll receive a success response:
{
  "success" : true
}
When either the business or the WhatsApp user terminates the call, you receive a Call Terminate webhook:
{
  "object": "whatsapp_business_account",
  "entry": [
    {
      "id": "366634483210360", // WhatsApp Business Account ID associated with the business phone number
      "changes": [
        {
          "value": {
            "messaging_product": "whatsapp",
            "metadata": { // ID and display number for the business phone number placing the call (caller)
              "phone_number_id": "436666719526789"
              "display_phone_number": "13175551399",

            },
            "calls": [
              {
                "id": "wacid.ABGGFjFVU2AfAgo6V-Hc5eCgK5Gh",
                "to": "16315553601", // The WhatsApp user's phone number (callee)
                "from": "13175551399", // The business phone number placing the call (caller)
                "event": "terminate",
                "direction": "USER_INITIATED",
                "timestamp": "1749197480",
                "status": ["Failed", "Completed"],
                "start_time": "1671644824", // Call start UNIX timestamp
                "end_time": "1671644944", // Call end UNIX timestamp
                "duration": 480 // Call duration in seconds
              }
            ]
          },
          "field": "calls"
        }
      ]
    }
  ]
}
Endpoints for user-initiated calling
Pre-accept call
In essence, when you pre-accept an inbound call, you are allowing the calling media connection to be established before attempting to send call media through the connection.
When you then call the accept call endpoint, media begins flowing immediately since the connection has already been established
Pre-accepting calls is recommended because it facilitates faster connection times and avoids audio clipping issues.
There is about 30 to 60 seconds after the Call Connect webhook is sent for the business to accept the phone call. If the business does not respond, the call is terminated on the WhatsApp user side with a “Not Answered” notification and a Terminate Webhook is delivered back to you.
Note: Since the WebRTC connection is established before calling the Accept Call endpoint, make sure to flow the call media only after you receive a 200 OK response back.
If call media flows too early, the caller will miss the first few words of the call. If call media flows too late, callers will hear silence.
Request Syntax
POST <PHONE_NUMBER_ID/calls
Placeholder	Description	Sample Value
<PHONE_NUMBER_ID>
Integer
Required

The business phone number which you are using Calling API features from.
Learn more about formatting phone numbers in Cloud API
+12784358810
Request Body
{
  "messaging_product": "whatsapp",
  "call_id": "wacid.ABGGFjFVU2AfAgo6V-Hc5eCgK5Gh",
  "action": "pre_accept",
  "session" : {
      "sdp_type" : "answer",
      "sdp" : "<<RFC 8866 SDP>>"
   }
}
Body Parameters
Parameter	Description	Sample Value
call_id
String
Required

The ID of the phone call.
For inbound calls, you receive a call ID from the Call Connect webhook when a WhatsApp user initiates the call.
“wacid.ABGGFjFVU2AfAgo6V-Hc5eCgK5Gh”
action
String
Optional

The action being taken on the given call ID.
Values can be connect | pre_accept | accept | reject | terminate
“pre_accept”
session
JSON object
Optional

Contains the session description protocol (SDP) type and description language.
Requires two values:
sdp_type — (String) Required
“offer”, to indicate SDP offer
sdp — (String) Required
The SDP info of the device on the other end of the call. The SDP must be compliant with RFC 8866.
Learn more about Session Description Protocol (SDP)
View example SDP structures
"session" :
{
"sdp_type" : "offer",
"sdp" : "<<RFC 8866 SDP>>"
}
Success Response
{
  "messaging_product": "whatsapp",
  "success" : true
}
Error Response
Possible errors that can occur:
Invalid call-id
Invalid phone-number-id
Error related to your payment method
Invalid Connection info eg sdp, ice
Accept/Reject an already In Progress/Completed/Failed call
Permissions/Authorization errors
View Calling API Error Codes and Troubleshooting for more information
View general Cloud API Error Codes here

Accept call
Use this endpoint to connect to a call by providing a call agent’s SDP.
You have about 30 to 60 seconds after the Call Connect Webhook is sent to accept the phone call. If your business does not respond, the call is terminated on the WhatsApp user side with a “Not Answered” notification and a Terminate Webhook is delivered back to you.
Request Syntax
POST <PHONE_NUMBER_ID/calls
Placeholder	Description	Sample Value
<PHONE_NUMBER_ID>
Integer
Required

The business phone number which you are using Calling API features from.
Learn more about formatting phone numbers in Cloud API
+12784358810
Request Body
{
  "messaging_product": "whatsapp",
  "call_id": "wacid.ABGGFjFVU2AfAgo6V-Hc5eCgK5Gh",
  "action": "accept",
  "session" : {
      "sdp_type" : "answer",
      "sdp" : "<<RFC 8866 SDP>>"
   },
   "biz_opaque_callback_data": "random_string",
  }
}
Body Parameters
Parameter	Description	Sample Value
call_id
String
Required

The ID of the phone call.
For inbound calls, you receive a call ID from the Call Connect webhook when a WhatsApp user initiates the call.
“wacid.ABGGFjFVU2AfAgo6V-Hc5eCgK5Gh”
action
String
Optional

The action being taken on the given call ID.
Values can be connect | pre_accept | accept | reject | terminate
“accept”
session
JSON object
Optional

Contains the session description protocol (SDP) type and description language.
Requires two values:
sdp_type — (String) Required
“offer”, to indicate SDP offer
sdp — (String) Required
The SDP info of the device on the other end of the call. The SDP must be compliant with RFC 8866.
Learn more about Session Description Protocol (SDP)
View example SDP structures
"session" :
{
"sdp_type" : "offer",
"sdp" : "<<RFC 8866 SDP>>"
}
biz_opaque_callback_data
String
Optional

An arbitrary string you can pass in that is useful for tracking and logging purposes.
Any app subscribed to the “calls” webhook field on your WhatsApp Business Account can receive this string, as it is included in the calls object within the subsequent Terminate webhook payload.
Cloud API does not process this field, it just returns it as part of the Terminate webhook.
Maximum 512 characters
“8huas8d80nn”
Success Response
{
  "messaging_product": "whatsapp",
  "success" : true
}
Error Response
Possible errors that can occur:
Invalid call-id
Invalid phone-number-id
Error related to your payment method
Invalid Connection info eg sdp, ice etc
Accept/Reject an already In Progress/Completed/Failed call
Permissions/Authorization errors
SDP answer provided in accept does not match the SDP answer given in the Pre-Accept endpoint for the same call-id
View Calling API Error Codes and Troubleshooting for more information
View general Cloud API Error Codes here

Reject call
Use this endpoint to reject a call.
You have about 30 to 60 seconds after the Call Connect webhook is sent to accept the phone call. If the business does not respond the call is terminated on the WhatsApp user side with a “Not Answered” notification and a Terminate Webhook is delivered back to you.
Request Syntax
POST <PHONE_NUMBER_ID/calls
Placeholder	Description	Sample Value
<PHONE_NUMBER_ID>
Integer
Required

The business phone number which you are using Calling API features from.
Learn more about formatting phone numbers in Cloud API
+12784358810
Request Body
{
  "messaging_product": "whatsapp",
  "call_id": "wacid.ABGGFjFVU2AfAgo6V-Hc5eCgK5Gh",
  "action": "reject"
}
Body Parameters
Parameter	Description	Sample Value
call_id
String
Required

The ID of the phone call.
For inbound calls, you receive a call ID from the Call Connect webhook when a WhatsApp user initiates the call.
“wacid.ABGGFjFVU2AfAgo6V-Hc5eCgK5Gh”
action
String
Optional

The action being taken on the given call ID.
Values can be connect | pre_accept | accept | reject | terminate
“reject”
Success Response
{
  "messaging_product": "whatsapp",
  "success" : true
}
Error Response
Possible errors that can occur:
Invalid call-id
Invalid phone-number-id
Accept/Reject an already In Progress/Completed/Failed call
Permissions/Authorization errors
View Calling API Error Codes and Troubleshooting for more information
View general Cloud API Error Codes here

Terminate call
Use this endpoint to terminate an active call.
This must be done even if there is an RTCP BYE packet in the media path. Ending the call this way also ensures pricing is more accurate.
When the WhatsApp user terminates the call, you do not have to call this endpoint. Once the call is successfully terminated, a Call Terminate Webhook will be sent to you.
Request Syntax
POST <PHONE_NUMBER_ID/calls
Placeholder	Description	Sample Value
<PHONE_NUMBER_ID>
Integer
Required

The business phone number which you are using Calling API features from.
Learn more about formatting phone numbers in Cloud API
+12784358810
Request Body
{
  "messaging_product": "whatsapp",
  "call_id": "wacid.ABGGFjFVU2AfAgo6V-Hc5eCgK5Gh",
  "action": "terminate"
}
Body Parameters
Parameter	Description	Sample Value
call_id
String
Required

The ID of the phone call.
For inbound calls, you receive a call ID from the Call Connect webhook when a WhatsApp user initiates the call.
“wacid.ABGGFjFVU2AfAgo6V-Hc5eCgK5Gh”
action
String
Optional

The action being taken on the given call ID.
Values can be connect | pre_accept | accept | reject | terminate
“terminate”
Success Response
{
  "messaging_product": "whatsapp",
  "success" : true
}
Error Response
Possible errors that can occur:
Invalid call-id
Invalid phone-number-id
Accept/Reject an already In Progress/Completed/Failed call
Reject call is already in progress
Permissions/Authorization errors
View Calling API Error Codes and Troubleshooting for more information
View general Cloud API Error Codes here
Webhooks for user-initiated calling
With all Calling API webhooks, there is a ”calls” object inside the ”value” object of the webhook response. The ”calls” object contains metadata about the call that is used to action on each call received by your business.
To receive Calling API webhooks, subscribe to the calls webhook field.
Learn more about Cloud API webhooks here
Call Connect webhook
A webhook notification is sent in near real-time when a call initiated by your business is ready to be connected to the whatsapp user (an SDP Answer).
Critically, the webhook contains information required to establish a call connection via WebRTC.
Once you receive the Call Connect webhook, you can apply the SDP Answer received in the webhook to your WebRTC stack in order to initiate the media connection.
{
  "object": "whatsapp_business_account",
  "entry": [
    {
      "id": "<WHATSAPP_BUSINESS_ACCOUNT_ID>",
      "changes": [
        {
          "value": {
            "messaging_product": "whatsapp",
            "metadata": {
              "display_phone_number": "16315553601",
              "phone_number_id": "<PHONE_NUMBER_ID>"
            },
            "contacts": [
              {
                "profile": {
                  "name": "<CALLEE_NAME>"
                },
                "wa_id": "16315553602"
              }
            ],
            "calls": [
              {
                "id": "wacid.ABGGFjFVU2AfAgo6V-Hc5eCgK5Gh",
                "to": "16315553601",
                "from": "16315553602",
                "event": "connect",
                "timestamp": "1671644824",
                "direction": "USER_INITIATED",
                "deeplink_payload": "deeplink_payload",
                "cta_payload": "cta_payload",
                "session": {
                  "sdp_type": "offer",
                  "sdp": "<<RFC 8866 SDP>>"
                }
              }
            ]
          },
          "field": "calls"
        }
      ]
    }
  ]
}
Webhook values for "calls"
Placeholder	Description
id
String
A unique ID for the call
to
Integer
The number being called (callee)
from
Integer
The number of the caller
event
Integer
The calling event that this webhook is notifying the subscriber of
timestamp
Integer
The UNIX timestamp of the webhook event
direction
String
The direction of the call being made.
Can contain either:
BUSINESS_INITIATED, for calls initiated by your business.
USER_INITIATED, for calls initiated by a WhatsApp user.
deeplink_payload
String
Arbitrary string specified in biz_payload query param on a call deeplink. Will only be returned if call was initiated from a deeplink with such param.
See Call Button Messages and Deep Links for more details.
cta_payload
String
Arbitrary string specified in payload field on a call button. Will only be returned if call was initiated from a call button with payload.
See Call Button Messages and Deep Links for more details.
session
JSON object
Optional

Contains the session description protocol (SDP) type and description language.
Requires two values:
sdp_type — (String) Required
“offer”, to indicate SDP offer
sdp — (String) Required
The SDP info of the device on the other end of the call. The SDP must be compliant with RFC 8866.
Learn more about Session Description Protocol (SDP)
View example SDP structures
contacts
JSON object
Profile information of the callee.
Contains two values:
name — The WhatsApp profile name of the callee.
wa_id — The WhatsApp ID of the callee.

Call Terminate webhook
A webhook notification is sent whenever the call has been terminated for any reason, such as when the WhatsApp user hangs up, or when the business calls the POST /<PHONE_NUMBER_ID>/calls endpoint with an action of terminate or reject.
{
  "object": "whatsapp_business_account",
  "entry": [
    {
      "id": "<WHATSAPP_BUSINESS_ACCOUNT_ID>",
      "changes": [
        {
          "value": {
              "messaging_product": "whatsapp",
              "metadata": {
                   "display_phone_number": "16505553602",
                   "phone_number_id": "<PHONE_NUMBER_ID>",
              },
               "calls": [
                {
                    "id": "wacid.ABGGFjFVU2AfAgo6V-Hc5eCgK5Gh",
                    "to": "16315553601",
                    "from": "16315553602",
                    "event": "terminate"
                    "direction": "USER_INITIATED",
                    "deeplink_payload": "deeplink_payload",
                    "cta_payload": "cta_payload",
                    "biz_opaque_callback_data": "random_string",
                    "timestamp": "1671644824",
                    "status" : [FAILED | COMPLETED],
                    "start_time" : "1671644824",
                    "end_time" : "1671644944",
                    "duration" : 120
                }
              ],
              "errors": [
                {
                    "code": INT_CODE,
                    "message": "ERROR_TITLE",
                    "href": "ERROR_HREF",
                    "error_data": {
                        "details": "ERROR_DETAILS"
                    }
                }
              ]
          },
          "field": "calls"
        }
      ]
    }
  ]
}
Webhook values for "calls"
Placeholder	Description
id
String
A unique ID for the call
to
Integer
The number being called (callee)
from
Integer
The number of the caller
event
Integer
The calling event that this webhook is notifying the subscriber of
timestamp
Integer
The UNIX timestamp of the webhook event
direction
String
The direction of the call being made.
Can contain either:
BUSINESS_INITIATED, for calls initiated by your business.
USER_INITIATED, for calls initiated by a WhatsApp user.
deeplink_payload
String
Arbitrary string specified in biz_payload query param on a call deeplink. Will only be returned if call was initiated from a deeplink with such param.
See Call Button Messages and Deep Links for more details.
cta_payload
String
Arbitrary string specified in payload field on a call button. Will only be returned if call was initiated from a call button with payload.
See Call Button Messages and Deep Links for more details.
start_time
Integer
The UNIX timestamp of when the call started.
Only present when the call was picked up by the other party.
end_time
Integer
The UNIX timestamp of when the call ended.
Only present when the call was picked up by the other party.
duration
Integer
Duration of the call in seconds.
Only present when the call was picked up by the other party.
biz_opaque_callback_date
String
Arbitrary string your business passes into the call for tracking and logging purposes.
Will only be returned if provided through an Initiate Call request or Accept Call request
errors.code
Integer
The errors object is present only for failed calls when there is error information available. Code is one of the calling error codes
Dual tone multi frequency (DTMF) support
The dialpad provided by the Calling API only supports DTMF use cases.
It does not support consumer-to-consumer calls and does not change any other calling behaviors. For example, the dialpad cannot be used to dial a number and initiate a call or message on WhatsApp.
WhatsApp Business Calling API supports DTMF tones, with the intention to enable BSP applications to support IVR-based systems.
WhatsApp users can press tone buttons on their client app and these DTMF tones are injected into the WebRTC RTP stream established as a part of the VoIP connection.
Our WebRTC stream conforms to RFC 4733 for the transfer of DTMF Digits via RTP Payload.
There is no webhook for conveying DTMF digits.
DTMF clock rate
Only 8000 clock rate is supported in our SDPs. For user-initiated calls, our SDP offer includes only 8000 clock rate. For business-initiated calls, we expect your SDP offer to have 8000 clock rate. Even if it is absent, we’ll still go ahead with 8000 clock rate against payload type 126.
The RTP packets representing DTMF events will use the same timestamp base and sequence number base as the regular audio packets. So you don’t have to worry about differing clock rates between audio packets and DTMF packets. The duration field of the DTMF packet is calculated using 8000 clock units.
We don’t support 48000 clock rate for DTMF
Sending DTMF digits on consumer WhatsApp client
WhatsApp client applications are enhanced to have a dialpad for calls with CloudAPI business phone numbers. The WhatsApp user can press the buttons on the dialpad and send DTMF tones.
Image
SDP Overview and Sample SDP Structures
Session Description Protocol (SDP) is a text-based format used to describe the characteristics of multimedia sessions, such as voice and video calls, in real-time communication applications. SDP provides a standardized way to convey information about the session’s media streams, including the type of media, codecs, protocols, and other parameters necessary for establishing and managing the session.
In the context of WebRTC, SDP is used to negotiate the media parameters between the sender and receiver, enabling them to agree on the specifics of the media exchange.
View SDP sample structures for user-initiated calls