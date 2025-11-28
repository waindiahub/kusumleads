Configure Call Settings
Updated: Nov 7, 2025
Calling is not enabled by default on a business phone number
Use the POST /<PHONE_NUMBER_ID>/settings endpoint to enable Calling API features on a business phone number.
Calling Eligibility
To qualify for Calling API features, your business must have a messaging limit of at least 2000 business-initiated conversations in a rolling 24-hour period.
Learn more about Quality Ratings and Messaging Limits
When you test your WhatsApp Calling integration using public test numbers (PTNs) and sandbox accounts, Calling API restrictions are relaxed.
Learn more about testing your WhatsApp Calling API integration
Overview
Use these endpoints to view and configure call settings for the WhatsApp Business Calling API.
You can also configure session initiation protocol (SIP) for call signaling instead of using Graph API endpoint calls and webhooks.
Configure/Update business phone number calling settings
Use this endpoint to update call settings configuration for an individual business phone number.
WhatsApp clients reflecting latest calling config
After call configuration is updated, WhatsApp users may take up to 7 days to reflect that configuration although most users refresh much sooner. You can force an immediate refresh in WhatsApp by entering the chat window with business and open the chat info page. Regardless of WhatsApp client behavior, the semantics of settings are still honored on the server side.
Request syntax
POST /<PHONE_NUMBER_ID>/settings
Endpoint parameters

Placeholder	Description	Sample Value
<PHONE_NUMBER_ID>
Integer
Required

The business phone number for which you are updating Calling API settings.
Learn more about formatting phone numbers in Cloud API
+12784358810
Request body
{
  "calling": {
    "status": "ENABLED",
    "call_icon_visibility": "DEFAULT",
    "call_hours": {
      "status": "ENABLED",
      "timezone_id": "America/Manaus",
      "weekly_operating_hours": [
        {
          "day_of_week": "MONDAY",
          "open_time": "0400",
          "close_time": "1020"
        },
        {
          "day_of_week": "TUESDAY",
          "open_time": "0108",
          "close_time": "1020"
        }
      ],
      "holiday_schedule": [
        {
          "date": "2026-01-01",
          "start_time": "0000",
          "end_time": "2359"
        }
      ]
    },
    "callback_permission_status": "ENABLED",
    "sip": {
      "status": "ENABLED | DISABLED (default)",
      "servers": [
        {
          "hostname": SIP_SERVER_HOSTNAME,
          "port": SIP_SERVER_PORT,
          "request_uri_user_params": {
            "KEY1": "VALUE1",
            "KEY2": "VALUE2"
          }
        }
      ]
    }
  }
}

Body parameters
Parameter	Description	Sample Value
status
String
Optional

Enable or disable the Calling API for the given business phone number.
“ENABLED”
“DISABLED”
call_icon_visibility
String
Optional

Configure whether the WhatsApp call button icon displays for users when chatting with the business.
View call icon visibility behavior details below
View call icon visibility behavior details below
call_hours
JSON object
Optional

Allows you specify and trigger call settings for incoming calls based on your timezone, business operating hours, and holiday schedules.
Any previously configured values in call_hours will be replaced with the values passed in the request body of this API call.
View call hours behavior details below
View call hours behavior details below
callback_permission_status
String
Optional

Configure whether a WhatsApp user is prompted with a call permission request after calling your business.
Note: The call permission request is triggered from either a missed or connected call.
View callback permission status behavior details below
“ENABLED”
“DISABLED”
sip
JSON object
Optional

Configure call signaling via signal initiation protocol (SIP).
Note: When SIP is enabled, you cannot use calling related endpoints and will not receive calling related webhooks.
Learn how to configure and use SIP call signaling
"sip": {
  "status": "ENABLED | DISABLED (default)",
  "servers": [// one server per app]
    {
      "hostname": SIP_SERVER_HOSTNAME
      "port": SIP_SERVER_PORT,
      "request_uri_user_params": {
        "KEY1": "VALUE1", // for cases like TGRP
        "KEY2": "VALUE2",
      }
    }
  ]
}

Parameter details
Calling status
When the status parameter is set to “ENABLED”, calling features are enabled for the business phone number. WhatsApp client applications will render the call button icon in both the business chat and business chat profile.
When the status parameter is set to “DISABLED”, calling features are disabled, and both the business chat and business chat profile do not display the call button icon.
Updates to status will update the call button icon in existing business chats in near real-time when the business phone number is in the WhatsApp user’s contacts.
Otherwise, updates are real-time for a limited number of users in conversation with the business, and are eventual for the rest of conversations.
Call button icon visibility
When Calling API features are enabled for a business number, you can still choose whether to show the call button icon or not by using the call_icon_visibility parameter. Note: Disabling call button icon visibility does not disable a WhatsApp user’s ability to make unsolicited calls to your business.
The behavior for supported options is as follows:
DEFAULT
The Call button icon will be displayed in the chat menu bar and the business info page, allowing for unsolicited calls to the business by WhatsApp users.
Image


DISABLE_ALL
The call button icon is hidden in the chat menu bar and the business info page, and all other entry points external to the chat are also disabled. Consumers cannot make unsolicited calls to the business.
Your business can still send interactive messages or template messages with a Calling API CTA button.
Image


Callback permissions
Calling a WhatsApp user requires explicit permission from the user. One way to obtain calling permissions is to request permission when a WhatsApp user calls your business.
You can configure the call permission UI to automatically show in the WhatsApp user’s client app when they call your business number. The user may change their permission selection at any time.
Image


Call hours
With the call_hours setting, you can specify the timezone, business operating hours, and holiday schedules that will be enforced for all user-initiated calls.
Configuring this setting restricts calls only to available weekly hours you configure. User-initiated calls are unavailable outside of the weekly hours and holiday schedules you set.
The WhatsApp client app will show users an option to chat with the business, or request a callback, if callback_permission_status is ENABLED. The user will also be shown the next available calling slot on the option screen.
Image


"call_hours": {
  "status": "ENABLED",
  "timezone_id": "America/Manaus",
  "weekly_operating_hours": [
    {
      "day_of_week": "MONDAY",
      "open_time": "0400",
      "close_time": "1020"
    },
    {
      "day_of_week": "TUESDAY",
      "open_time": "0108",
      "close_time": "1020"
    }
  ],
  "holiday_schedule": [
    {
      "date": "2026-01-01",
      "start_time": "0000",
      "end_time": "2359"
    }
  ]
}

Parameter	Description	Sample Values
status
String
Required

Enable or disable the call hours for the business.
If call hours are disabled, the business is considered open all 24 hours of the day, 7 days a week.
“ENABLED”
“DISABLED”
timezone_id
String
Required

The timezone that the business is operating within.
Learn more about supported values for
timezone_id
“America/Menominee”
“Asia/Singapore”
weekly_operating_hours
List of JSON object
Required

The operating hours schedule for each day of the week.
Each entry is an JSON object with 3 key/value pairs:
day_of_week — (Enum) [Required]
The day of the week.
Can take one of seven values: "MONDAY", “TUESDAY”, “WEDNESDAY”, “THURSDAY”, “FRIDAY”, “SATURDAY”, “SUNDAY”
open_time
close_time — (Integer) [Required]
Opening and closing times represented in 24 hour format, e.g. ”1130” = 11:30AM
Maximum of 2 entries allowed per day of week
open_time must be before close_time
Overlapping entries not allowed
{
"day_of_week": "MONDAY",
"open_time": "0400",
"close_time": "1020"
},
{
"day_of_week":"TUESDAY",
"open_time": "0108",
"close_time": "1020"
}
...

holiday_schedule
String
Optional

An optional override to the weekly schedule.
Up to 20 overrides can be specified.
Note: If holiday_schedule is not passed in the request, then the existing holiday_schedule will be deleted and replaced with an empty schedule.
date — (String) [Required]
Date for which you want to specify the override.
YYYY-MM-DD format.
open_time
close_time — (Integer) [Required]
Opening and closing times represented in 24 hour format, e.g. ”1130” = 11:30AM
Maximum of 2 entries allowed per day of week
open_time must be before close_time
Overlapping entries not allowed
{
"date": "2026-01-01",
"start_time": "0000",
"end_time": "2359",
}
...

Success response
{
  "success": true
}

Error response
Possible errors that can occur:
Permissions/Authorization errors
Invalid status
Invalid schedule for call_hours
Holiday given in call_hours is a past date
Timezone is invalid in call_hours
weekly_operating_hours in call_hours cannot be empty
Date format in holiday_schedule for call_hours is invalid
More than 2 entries not allowed in weekly_operating_hours schedule in call_hours
Overlapping schedule in call_hours is not allowed
Calling restriction errors
View Calling API Error Codes and Troubleshooting for more information
View general Cloud API Error Codes here
Get phone number calling settings
Use this endpoint to check the configuration of your Calling API feature settings.
This endpoint can return information for other Cloud API feature settings.
Request syntax
GET /<PHONE_NUMBER_ID>/settings
Endpoint parameters

Parameter	Description	Sample Value
<PHONE_NUMBER_ID>
Integer
Required

The business phone number for which you are getting Calling API settings.
Learn more about formatting phone numbers in Cloud API
124545784358810
App permission required
whatsapp_business_management: Advanced access is required to update use the API for end business clients
Response body
{
  "calling": {
    "status": "ENABLED",
    "call_icon_visibility": "DEFAULT",
    "callback_permission_status": "ENABLED",
    "call_hours": {
      "status": "ENABLED",
      "timezone_id": "[REDACTED]",
      "weekly_operating_hours": [
        {
          "day_of_week": "MONDAY",
          "open_time": "0400",
          "close_time": "1020"
        },
        {
          "day_of_week": "TUESDAY",
          "open_time": "0108",
          "close_time": "1020"
        }
      ],
      "holiday_schedule": [
        {
          "date": "2026-01-01",
          "start_time": "0000",
          "end_time": "2359"
        }
      ]
    },
    "sip": {
      "status": "ENABLED",
      "servers": [
        {
          "hostname": "[REDACTED]",
          "sip_user_password": "[REDACTED]"
        }
      ]
    }
  },
  <Other non-calling feature configuration...>
}

Include SIP user password
Optionally, you can include SIP user credentials in your response body by adding the SIP credentials query parameter in the POST request:
GET /<PHONE_NUMBER_ID>/settings?include_sip_credentials=true
Where the response will look like this:
{
  "calling": {
    ... // other calling api settings
    "sip": {
      "status": "ENABLED",
      "servers": [
        {
          "hostname": "sip.example.com",
          "sip_user_password": "{SIP_USER_PASSWORD}"
        }
      ]
    }
  }
}

Response details
The GET /<PHONE_NUMBER_ID>/settings endpoint returns Calling API settings, along with other configuration information for your WhatsApp business phone number.
Learn more about Calling API settings and their values
Response with calling restrictions
If your business is enforced upon, the response body will contain information about the restriction as follows along with other calling api settings.
 {
   "calling": {
     ... // other calling api settings
     "restrictions": {
       "restrictions_list": [
         {
           "type": "RESTRICTED_BIZ_INITIATED_AND_USER_INITIATED_CALLING",
           "reason": "Calling capability has been temporarily disabled for this phone number due to high negative feedback from users.",
           "expiration": 1754072386
         }
       ]
     }
   }
}

Parameter	Description
<restrictions>
JSON Object
The restrictions object contains the following values: restriction_list(JSON Object): list of currently imposed restrictions with the following values
type(string) - for calling restriction, this would have the value of RESTRICTED_BIZ_INITIATED_AND_USER_INITIATED_CALLING
reason(string) - description of restriction
expiration(Integer) - The UNIX time at which the restriction will expire in UTC timezone
Error response
Possible errors that can occur:
Permissions/Authorization errors
View Calling API Error Codes and Troubleshooting for more information
View general Cloud API Error Codes here
Call settings in WhatsApp Manager
You can also control your call settings via WhatsApp Manager.
To access calling controls in WhatsApp Manager:
Click on Account tools > Phone numbers panel
Click the gear icon next to the phone number you are using for calling
Click the Calls tab
Image
Configure and use call signaling via session initiation protocol (SIP)
Session Initiation Protocol (SIP) is a signaling protocol used for initiating, maintaining, modifying, and terminating real-time communication sessions between two or more endpoints. You can send and receive call signals using SIP instead of Graph API endpoints.
Learn more about how to use and configure SIP
Settings update webhooks
You can subscribe to a new webhook subscription field account_settings_update to get notified on updates to phone number settings.
You’ll be notified even for your own updates
Currently only changes to calling settings are supported. Underneath the calling object, only changes to subset of fields are observed - status, call_icon_visibility, callback_permission_status, sip.status, srtp_key_exchange_protocol
Steps to get started
Set up your webhook subscription and subscribe to the account_settings_update field.
The same app should also be subscribed to the WhatsApp Business Account of your business phone number.
Your app should have whatsapp_business_management permission to receive the webhooks. Using access token for the same app, if you’re able to get settings successfully, your app is good to receive the webhooks too.
Webhook payload
{
    "object": "whatsapp_business_account",
    "entry": [
        {
            "id": "whatsapp-business-account-id",
            "changes": [
                {
                    "value": {
                        "messaging_product": "whatsapp",
                        "timestamp": "1671644824",
                        "type": "[phone_number_settings]",
                        "phone_number_settings": {
                            "phone_number_id": "phone-number-id",
                            "calling": {
                                "status": "ENABLED",
                                "call_icon_visibility": "DEFAULT",
                                "callback_permission_status": "ENABLED",
                                "call_hours": {
                                    "status": "ENABLED",
                                    "timezone_id": "[REDACTED]",
                                    "weekly_operating_hours": [
                                        {
                                            "day_of_week": "MONDAY",
                                            "open_time": "0400",
                                            "close_time": "1020"
                                        },
                                        {
                                            "day_of_week": "TUESDAY",
                                            "open_time": "0108",
                                            "close_time": "1020"
                                        }
                                    ],
                                    "holiday_schedule": [
                                        {
                                            "date": "2026-01-01",
                                            "start_time": "0000",
                                            "end_time": "2359"
                                        }
                                    ]
                                },
                                "sip": {
                                    "status": "ENABLED",
                                    "servers": [
                                        {
                                            "hostname": "[REDACTED]",
                                            "port": SIP_SERVER_PORT
                                        }
                                    ]
                                }
                            }
                        }
                    },
                    "field": "account_settings_update"
                }
            ]
        }
    ]
}
Webhook values
Placeholder	Description
messaging_product
String
Always whatsapp for now
timestamp
Integer
Time when the settings got updated
type
String
Type of the change. Currently only PHONE_NUMBER_SETTINGS
phone_number_settings
Object
This field is present if the type is PHONE_NUMBER_SETTINGS. Currently only calling sub-field under this is supported.
phone_number_settings.phone_number_id
String
The phone number id, whose settings got updated
phone_number_settings.calling
Object
This is present only if fields related to calling are updated. It’s null otherwise. When present, the payload is same as Get settings API
Calling Restrictions for User Feedback
If your calls receive a high negative user feedback, such as blocks and reports, calling functionality on your phone number can be restricted.
Early Warning
You will be notified when the business phone number is close to being paused as an early warning. The early warning notifications will be communicated via below channels
Email
Enforcement emails are sent to the email addresses of all users and admins associated with the business. If you did not receive an email, confirm which email you have designated as the contact email for your app and make sure that it is active, can receive new email, and does not flag the email as junk or spam mail.
Webhook
A webhook will be sent on the account_update field:
{
  "object": "whatsapp_business_account",
  "entry": [
    {
      "id": "0",
      "time": 1623862418,
      "changes": [
        {
          "field": "account_update",
          "value": {
            "phone_number": "PN",
            "event": "ACCOUNT_VIOLATION",
            "violation_info": {
               "violation_type": "LOW_CALLING_QUALITY",
            }
          }
        }
      ]
    }
  ]
}

Refer account_update for information about the webhook.
Pause in Calling functionality
Once the negative user feedback reaches a threshold, Cloud API will automatically restrict calling functionality on your phone number for a period of 7 days. While paused the calling phone number will be unable to
Make business initiated calls to users
Receive calls from users
Have call icon visible
Send call permissions requests
Alter calling settings for this account number
Once your phone number has been paused, notifications will be communicated via below channels.
Email
Enforcement emails are sent to the email addresses of all users and admins associated with the business. If you did not receive an email, confirm which email you have designated as the contact email for your app and make sure that it is active, can receive new email, and does not flag the email as junk or spam mail.
Webhook
A webhook will be sent on the account_update field:
{
  "object": "whatsapp_business_account",
  "entry": [
    {
      "id": "0",
      "time": 1641848059,
      "changes": [
        {
          "field": "account_update",
          "value": {
            "phone_number": "PN",
            "event": "ACCOUNT_RESTRICTION",
            "violation_info": {
               "violation_type": "LOW_CALLING_QUALITY",
            },
            "restriction_info": [
              {
                "restriction_type": "RESTRICTED_BIZ_INITIATED_AND_USER_INITIATED_CALLING",
                "expiration": 1641848057
              }
            ]
          }
        }
      ]
    }
  ]
}

Refer account_update for information about the webhook.
Calling Restrictions for Low Call Pickup Rates
When calling is enabled on your business phone number, you are expected to pick up calls that users place to you.
If a significant number of calls placed to your calling-enabled business phone number are not picked up, we will notify you and expect you to make a change.
What happens if you do not pick up calls
Warning via Email: We will first notify you via email and provide options for you to change how you handle incoming calls.
Calling becomes restricted on the business phone number: The calling button will be hidden from users.
How to mitigate the situation
If you receive a warning
Continue allowing users to call:
Please identify and address the cause of calls not being picked up and make sure you are properly resourced to handle expected call volumes.
Hide call buttons for user-initiated calls:
You can do so either by working with your partner or going to WhatsApp Manager > Account tools > Phone numbers > select Phone number [WA phone number] > Calls > toggle off Display call buttons.
Turn off calling altogether:
You can do so either by working with your partner or going to WhatsApp Manager > Account tools > Phone numbers > select Phone number [WA phone number] > Calls > toggle off Allow voice calls.
If the call button is hidden for the business phone number
Re-display calling buttons:
Please identify and address the cause of calls not being picked up and make sure you are properly resourced to handle expected call volumes.
Next, display the calling buttons by either working with your partner or going to WhatsApp Manager > Account tools > Phone numbers > select Phone number [WA phone number] > Calls > toggle on Display call buttons.
Turn off calling altogether:
You can do so either by working with your partner or going to WhatsApp Manager > Account tools > Phone numbers > select Phone number [WA phone number] > Calls > toggle off Allow voice calls.
Webhooks
Warning webhook
[
  {
    "object": "whatsapp_business_account",
    "entry": [
      {
        "id": "0",
        "time": 1641848059,
        "changes": [
          {
            "field": "account_update",
            "value": {
              "phone_number": "16505552771",
              "event": "ACCOUNT_VIOLATION",
              "violation_info": {
                "violation_type": "USER_INITIATED_CALLS_LOW_PICKUP_RATE",
                "remediation": "Please identify and address the cause of user-initiated calls not being picked up and make sure the business is properly resourced to handle expected call volumes."
              }
            }
          }
        ]
      }
    ]
  }
]

Enforcement webhook
[
  {
    "object": "whatsapp_business_account",
    "entry": [
      {
        "id": "0",
        "time": 1641848059,
        "changes": [
          {
            "field": "account_update",
            "value": {
              "phone_number": "16505552771",
              "event": "ACCOUNT_RESTRICTION",
              "restriction_info": [
                {
                  "restriction_type": "RESTRICTED_USER_INITIATED_CALLING_CALL_BUTTON_HIDDEN",
                  "remediation": "The call button has been hidden due to low pickup rates. Please identify and address the cause of user-initiated calls not being picked up.  Next, display the calling buttons by either working with your partner or going to WhatsApp Manager > Account tools > Phone numbers > select Phone number > Calls > toggle on Display call buttons"
                }
              ]
            }
          }
        ]
      }
    ]
  }
]
