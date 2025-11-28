Business-initiated calls
Updated: Nov 13, 2025
Overview
The Calling API supports making calls to WhatsApp users from your business.
The user dictates when calls can be received by granting call permissions to the business phone number.
Call sequence diagram
Image (Right click image and choose “Open in new tab” for enlarged image)
Note: The ACCEPTED call status webhook will typically always arrive after the call has been established. It is primarily sent for call event auditing.
Prerequisites
Before you get started with business-initiated calling, ensure that:
Subscribe to the “calls” webhook field
Calling APIs are enabled on your business phone number
Lastly, before you can call a WhatsApp user, you must obtain their permission to do so.
Learn how to obtain WhatsApp user calling permissions here
Business-initiated calling flow
Part 1: Obtain permission to call the WhatsApp user
Obtaining call permissions from the WhatsApp user can be done in one of the following ways:
Send a call permission request message
You can request call permissions by sending the WhatsApp user a permission request, either as a free form message during an open customer service window, or by using a template message that contains the request.
Learn how to send a
free form
call permission request
Learn how to send a
template
call permission request
Enable callback_permission_status in call settings
When callback_permission_status is enabled, the user automatically provides call permission to your business when they place a call to you.
Learn how to enable
callback_permission_status
WhatsApp user grants permanent permissions
The user can also grant permanent permissions to the business at any time through their business profile.
Part 2: Your business initiates a new call to the WhatsApp user
Now that you have user permission, you can initiate a new call to the WhatsApp user in question.
You can now call the POST <PHONE_NUMBER_ID>/calls endpoint with the following request body to initiate a new call:
POST <PHONE_NUMBER_ID>/calls
{
  "messaging_product": "whatsapp",
  "to":"12185552828", // The WhatsApp user's phone number (callee)
  "action":"connect",
  "session" : {
      "sdp_type" : "offer",
      "sdp" : "<<RFC 8866 SDP>>"
  }
}
If there are no errors, you will receive a successful response:
{
  "messaging_product": "whatsapp",
  "calls" : [
     "id" : "wacid.HBgLMTIxODU1NTI4MjgVAgARGCAyODRQIAFRoA", // The WhatsApp call ID
   ]
}
Note: Response with error code 138006 indicates a lack of a call request permission for this business number from the WhatsApp user.
Part 3: You establish the call connection using webhook signaling
After successful initiation of a new call, you will receive a Call Connect webhook response that contains an SDP Answer from Cloud API. Your business will then apply the SDP Answer from this webhook to your WebRTC stack in order to initiate the media connection.
{
    "entry": [
        {
            "changes": [
                {
                    "field": "calls",
                    "value": {
                        "calls": [
                            {
                                "biz_opaque_callback_data": "TRx334DUDFTI4Mj", // Arbitrary string passed by business for tracking purposes
                                "session": {
                                    "sdp_type": "answer",
                                    "sdp": "<RFC 8866 SDP>"
                                },
                                "from": "13175551399", // The business phone number placing the call (caller)
                                "connection": {
                                    "webrtc": {
                                        "sdp": "<RFC 8866 SDP>"
                                    }
                                },
                                "id": "wacid.HBgLMTIxODU1NTI4MjgVAgARGCAyODRQIAFRoA", // The WhatsApp call ID
                                "to": "12185552828", // The WhatsApp user's phone number (callee)
                                "event": "connect",
                                "timestamp": "1749196895",
                                "direction": "BUSINESS_INITIATED"
                            }
                        ],
                        "metadata": { // ID and display number for the business phone number placing the call (caller)
                            "phone_number_id": "436666719526789",
                            "display_phone_number": "13175551399"
                        },
                        "messaging_product": "whatsapp"
                    }
                }
            ],
            "id": "366634483210360" // WhatsApp Business Account ID associated with the business phone number
        }
    ],
    "object": "whatsapp_business_account"
},
You then receive an appropriate status webhook, indicating that the call is RINGING, ACCEPTED, or REJECTED:
{
  "entry": [
    {
      "changes": [
        {
          "field": "calls",
          "value": {
            "statuses": [
              {
                "id": "wacid.HBgLMTIxODU1NTI4MjgVAgARGCAyODRQIAFRoA", // The WhatsApp call ID
                "type": "call",
                "status": "[RINGING|ACCEPTED|REJECTED]", // The current call status
                "timestamp": "1749197000",
                "recipient_id": "12185552828" // The WhatsApp user's phone number (callee)
              }
            ],
            "metadata": { // ID and display number for the business phone number placing the call (caller)
              "phone_number_id": "436666719526789",
              "display_phone_number": "13175551399"
            },
            "messaging_product": "whatsapp"
          }
        }
      ],
      "id": "366634483210360" // WhatsApp Business Account ID associated with the business phone number
    }
  ],
  "object": "whatsapp_business_account"
}
Part 4: Your business or the WhatsApp user terminates the call
Both you or the WhatsApp user can terminate the call at any time.
You call the POST <PHONE_NUMBER_ID>/calls endpoint with the following request body to terminate the call:
POST <PHONE_NUMBER_ID>/calls
{
  "messaging_product": "whatsapp",
  "call_id": "wacid.HBgLMTIxODU1NTI4MjgVAgARGCAyODRQIAFRoA", // The WhatsApp call ID
  "action" : "terminate"
}

If there are no errors, you will receive a success response:
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
                "id": "wacid.HBgLMTIxODU1NTI4MjgVAgARGCAyODRQIAFRoA",
                "to": "12185552828", // The WhatsApp user's phone number (callee)
                "from": "13175551399", // The business phone number placing the call (caller)
                "event": "terminate",
                "direction": "BUSINESS_INITIATED",
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
Endpoints for business-initiated calling
Initiate call
Use this endpoint to initiate a call to a WhatsApp user by providing a phone number and a WebRTC call offer.
Request Syntax
POST <PHONE_NUMBER_ID>/calls
Placeholder	Description	Sample Value
<PHONE_NUMBER_ID>
Integer
Required

The business phone number from which you are initiating a new call from.
Learn more about formatting phone numbers in Cloud API
+12784358810
Request body
{
  "messaging_product": "whatsapp",
  "to": "14085551234",
  "action": "connect",
  "session": {
    "sdp_type": "offer",
    "sdp": "<<RFC 8866 SDP>>"
  },
  "biz_opaque_callback_data": "0fS5cePMok"
}
Body parameters
Parameter	Description	Sample Value
to
Integer
Required

The number being called (callee)
“17863476655”
action
String
Required

The action being taken on the given call ID.
Values can be connect | pre_accept | accept | reject | terminate
“connect”
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
Any app subscribed to the “calls” webhook field on your WhatsApp Business Account can receive this string, as it is included in the calls object within the subsequent Call Terminate Webhook payload.
Cloud API does not process this field.
Maximum 512 characters
“0fS5cePMok”
Success response
{
  "messaging_product": "whatsapp",
  "calls" : [{
     "id" : "wacid.ABGGFjFVU2AfAgo6V",
   }]
}
Error response
Possible errors that can occur:
Invalid phone-number-id
Permissions/Authorization errors
Request format validation errors, e.g. connection info, sdp, ice
SDP validation errors
Calling restriction errors
View Calling API Error Codes and Troubleshooting for more information
View general Cloud API Error Codes here

Terminate call
Use this endpoint to terminate an active call.
This must be done even if there is an RTCP BYE packet in the media path. Ending the call this way also ensures pricing is more accurate.
When the WhatsApp user terminates the call, you do not have to call this endpoint. Once the call is successfully terminated, a Call Terminate Webhook will be sent to you.
Request syntax
POST <PHONE_NUMBER_ID/calls
Parameter	Description	Sample Value
<PHONE_NUMBER_ID>
Integer
Required

The business phone number which you are terminating a call from.
Learn more about formatting phone numbers in Cloud API
18274459827
Request body
{
  "messaging_product": "whatsapp",
  "call_id": "wacid.ABGGFjFVU2AfAgo6V-Hc5eCgK5Gh",
  "action": "terminate"
}
Body parameters
Parameter	Description	Sample Value
call_id
String
Required

The ID of the phone call.
For inbound calls, you receive a call ID from the Call Connect webhook when a WhatsApp user initiates the call.
“wacid.ABGGFjFVU2AfAgo6V-Hc5eCgK5Gh”
action
String
Required

The action being taken on the given call ID.
Values can be connect | pre_accept | accept | reject | terminate
“terminate”
Success response
{
  "messaging_product": "whatsapp",
  "success" : true
}
Error response
Possible errors that can occur:
Invalid call id
Invalid phone-number-id
The WhatsApp user has already terminated the call
Reject call is already in progress
Permissions/Authorization errors
View Calling API Error Codes and Troubleshooting for more information
View general Cloud API Error Codes here
Webhooks for business-initiated calling
With all Calling API webhooks, there is a ”calls” object inside the ”value” object of the webhook response. The ”calls” object contains metadata about the call that is used to action on each call placed or received by your business.
To receive Calling API webhooks, subscribe to the “calls” webhook field.
Learn more about Cloud API webhooks here
Call connect webhook
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
                "direction": "BUSINESS_INITIATED",
                "session": {
                  "sdp_type": "answer",
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

Call status webhook
This webhook is sent during the following calling events:
Ringing: When the WhatsApp user’s client device begins ringing
Accepted: When the WhatsApp user accepts the call
Rejected: When the call is rejected by the WhatsApp user. You’ll also receive the call terminate webhook in this case
The Webhook structure here is similar to the Status webhooks used for the Cloud API messages.
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
                   "phone_number_id": "<PHONE_NUMBER_ID>",
              },
              "statuses": [{
                    "id": "wacid.ABGGFjFVU2AfAgo6V",
                    "timestamp": "1671644824",
                    "type": "call"
                    "status": "[RINGING|ACCEPTED|REJECTED]",
                    "recipient_id": "163155536021",
                    "biz_opaque_callback_data": "random_string",
               }]
          },
          "field": "calls"
        }
      ]
    }
  ]
}
Learn more about Cloud API status webhooks
Webhook values for "statuses"
Placeholder	Description
id
String
A unique ID for the call
timestamp
Integer
The UNIX timestamp of the webhook event
recipient_id
Integer
The phone number of the WhatsApp user receiving the call
status
Integer
The current call status.
Possible values:
RINGING: Business initiated call is ringing the user
ACCEPTED: Business initiated call is accepted by the user
REJECTED: Business initiated call is rejected by the user
biz_opaque_callback_date
String
Arbitrary string your business passes into the call for tracking and logging purposes.
Will only be returned if provided through Initiate New Call API requests

Call terminate webhook
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
                    "direction": "BUSINESS_INITIATED",
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
Will only be returned if provided through an Initiate Call API request or Accept Call request
errors.code
Integer
The errors object is present only for failed calls when there is error information available. Code is one of the calling error codes
SDP overview and sample SDP structures
Session Description Protocol (SDP) is a text-based format used to describe the characteristics of multimedia sessions, such as voice and video calls, in real-time communication applications. SDP provides a standardized way to convey information about the session’s media streams, including the type of media, codecs, protocols, and other parameters necessary for establishing and managing the session.
In the context of WebRTC, SDP is used to negotiate the media parameters between the sender and receiver, enabling them to agree on the specifics of the media exchange.
View SDP sample structures for business-initiated calls