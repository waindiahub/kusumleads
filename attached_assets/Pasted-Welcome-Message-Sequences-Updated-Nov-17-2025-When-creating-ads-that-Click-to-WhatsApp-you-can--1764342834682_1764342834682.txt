Welcome Message Sequences
Updated: Nov 17, 2025
When creating ads that Click-to-WhatsApp, you can connect a Welcome Message Sequence from a messaging partner app. A sequence can include text, prefilled message, and FAQs.
This guide explains how to manage Welcome Message Sequences via the API endpoint.
Requirements
Your app must be granted the whatsapp_business_management permission.
Endpoints
// Create a new sequence / Change an existing sequence
POST /<WHATSAPP_BUSINESS_ACCOUNT_ID>/welcome_message_sequences

// Get a list of sequences / Get a specific sequence
GET /<WHATSAPP_BUSINESS_ACCOUNT_ID>/welcome_message_sequences

// Delete a sequence
DELETE /<WHATSAPP_BUSINESS_ACCOUNT_ID>/welcome_message_sequences

Create a Sequence
To upload a new welcome message sequence, send a POST request to the WHATSAPP_BUSINESS_ACCOUNT_ID/welcome_message_sequences endpoint.
Endpoint
// Create a new sequence
POST /<WHATSAPP_BUSINESS_ACCOUNT_ID>/welcome_message_sequences

Sample Request
curl -X POST\
-F 'welcome_message_sequence=
      {
       "text":"This is a welcome message authored in a 3P tool",
"autofill_message": {"content": "Hello! Can I get more info on this!"},
"ice_breakers":[
    {"title":"Quick reply 1"},
           {"title":"Quick reply 2"},
           {"title":"Quick reply 3"}
        ]
      }' \
-F 'name="Driver sign-up"' \
"https://graph.facebook.com/v14.0/WhatsappBusinessAccount/welcome_message_sequences"
-H 'Authorization: Bearer <ACCESS_TOKEN>'

Sample Response
In response, a welcome message sequence ID is returned.
{"sequence_id":"186473890"}

Parameters
Parameter	Description	Sample Value
sequence_id
String
Required

Identifier of the sequence.
186473890
name
String
Required

Name of the sequence.
Driver sign-up
welcome_message_sequence
JSON Object
Required

The welcome message JSON that will be sent upon clicking the ad.
{
  "text":"This is a welcome message authored in a 3P tool",
  "autofill_message": {"content": "Hello! Can I get more info on this!"},
  "ice_breakers":[
    {"title":"Quick reply 1"},
    {"title":"Quick reply 2"},
    {"title":"Quick reply 3"}
  ]
}

Change an Existing Sequence
A sequence linked to an active ad cannot be deleted.
To update an existing sequence, send a POST request to the WHATSAPP_BUSINESS_ACCOUNT_ID/welcome_message_sequences endpoint with:
The sequence_id parameter set to the ID of the sequence being updated
Other parameters, like name or welcome_message_sequence, that need to be updated.
Endpoint
// Change an existing sequence
POST /<WHATSAPP_BUSINESS_ACCOUNT_ID>/welcome_message_sequences

Sample Request
curl -X POST\
-F 'sequence_id="186473890"'\
-F 'name="Driver sign-up updated name"' \
"https://graph.facebook.com/v14.0/395394933592466/welcome_message_sequences"
-H 'Authorization: Bearer BEAiil...'

Sample Response
In response, a success message or an error message is returned.
{"success": true}

Parameters
Placeholder	Description	Sample Value
sequence_id
String
Required

Identifier of the sequence.
186473890
name
String
Optional

Name of the sequence.
Driver sign-up
welcome_message_sequence
JSON Object
Optional

The welcome message JSON that will be sent upon clicking the ad.
{
  "text":"This is a welcome message authored in a 3P tool",
  "autofill_message": {"content": "Hello! Can I get more info on this!"},
  "ice_breakers":[
    {"title":"Quick reply 1"},
    {"title":"Quick reply 2"},
    {"title":"Quick reply 3"}
  ]
}

Get a List of Sequences
To get an existing sequence, send a GET request to the WHATSAPP_BUSINESS_ACCOUNT_ID/welcome_message_sequences endpoint with:
Endpoint
// Update an existing sequence
GET /<WHATSAPP_BUSINESS_ACCOUNT_ID>/welcome_message_sequences

Sample Request
curl -X GET "https://graph.facebook.com/v14.0/395394933592466/welcome_message_sequences"
     -H 'Authorization: Bearer BEAiil...'

Sample Response
On success, a list of sequences is returned for that particular app.
[
  {
    "sequence_id":"8716291",
    "name":"Driver Sign up",
    "welcome_message_sequence":"<JSON_OBJECT>",
    "is_used_in_ad": true,
  },
  {
    "sequence_id":"4362",
    "name":"Basic Triage",
    "welcome_message_sequence":"<JSON_OBJECT>",
    "is_used_in_ad": false
  },
  {
    "sequence_id":"0139138",
    "name":"Appointment Schedule",
    "welcome_message_sequence":"<JSON_OBJECT>",
    "is_used_in_ad": true
  }
  ...
  ...
  ...,
  {
    "sequence_id":"6987565",
    "name":"Car Leads",
    "welcome_message_sequence":"<JSON_OBJECT>",
    "is_used_in_ad": false
  },
]

Get a Specific Sequence
To get a specific sequence, send a GET request to WHATSAPP_BUSINESS_ACCOUNT_ID/welcome_message_sequences with the sequence_id parameter set to the id of the sequence you want to query.
Endpoint
// Update an existing sequence
GET /<WHATSAPP_BUSINESS_ACCOUNT_ID>/welcome_message_sequences

Sample Request
curl -X GET \
-F 'sequence_id="6987565"'
"https://graph.facebook.com/v14.0/395394933592466/welcome_message_sequences"
-H 'Authorization: Bearer BEAiil...'

Sample Response
On success, a list of sequences is returned for that particular app.
[
  {
    "sequence_id":"6987565",
    "name":"Driver Sign up",
    "welcome_message_sequence":"<JSON_OBJECT>",
    "is_used_in_ad": false
  },
]

Placeholder	Description	Sample Value
sequence_id
String
Optional

Identifier of the sequence.
186473890
limit
int
Optional

Number of sequences to fetch.
5
Delete a Sequence
A sequence linked to an active ad cannot be deleted.
To delete a sequence, send a DELETE request to WHATSAPP_BUSINESS_ACCOUNT_ID/welcome_message_sequences with the sequence_id parameter set to the id of the sequence you want to delete.
Endpoint
// Update an existing sequence
GET /<WHATSAPP_BUSINESS_ACCOUNT_ID>/welcome_message_sequences

Sample Request
curl -X DELETE \
-F 'sequence_id="1234567890"'
"https://graph.facebook.com/v14.0/395394933592466/welcome_message_sequences"
-H 'Authorization: Bearer BEAiil...'

Sample Response
On success, a list of sequences is returned for that particular app.
{"success":true}

Placeholder	Description	Sample Value
sequence_id
String
Optional

Identifier of the sequence.
186473890
Webhook
The following webhook is triggered when a conversation is started after a user clicks an ad with a Click to WhatsApp’s call-to-action.
{
  "object": "whatsapp_business_account",
  "entry": [
    {
      "id": "ID",
      "changes": [
        {
          "value": {
            "messaging_product": "whatsapp",
            "metadata": {
              "display_phone_number": "PHONE_NUMBER",
              "phone_number_id": "PHONE_NUMBER_ID"
            },
            "contacts": [
              {
                "profile": {
                  "name": "NAME"
                },
                "wa_id": "ID"
              }
            ],
            "messages": [
              {
                "referral": {
                  "source_url": "AD_OR_POST_FB_URL",
                  "source_id": "ADID",
                  "source_type": "ad or post",
                  "headline": "AD_TITLE",
                  "body": "AD_DESCRIPTION",
                  "media_type": "image or video",
                  "image_url": "RAW_IMAGE_URL",
                  "video_url": "RAW_VIDEO_URL",
                  "thumbnail_url": "RAW_THUMBNAIL_URL",
                  "ctwa_clid": "CTWA_CLID",
                  "ref": "REF_ID",  // New field in referral

                },
                "from": "SENDER_PHONE_NUMBERID",
                "id": "wamid.ID",
                "timestamp": "TIMESTAMP",
                "type": "text",
                "text": {
                  "body": "BODY"
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

Marketing API Experience
Once welcome message sequences have been successfully submitted over the API, the sequence ID can be used to configure ads through the marketing API.
In the ad creative, the sequence ID can be set as follows:
{
  "name": "creative",
  "object_story_spec": {...},
  "asset_feed_spec": {
    "additional_data": {
      "partner_app_welcome_message_flow_id": "<SEQUENCE_ID_RETURNED_FROM_POST_REQUEST>"
    }
  }
}

For more information about messaging ads, please refer to Messaging Ads in the Marketing API documentation.
Ads Manager Experience Walkthrough
1: In the Message Template section of the Ad Creative, select Partner App
Image
2: Under Partner app, click the dropdown and select the appropriate messaging partner app.
Image
3: Under Message sequence, select the Welcome Message Sequence that you submitted via the API.
Image
4: Preview your message sequence and click the Save button
Image
Error Codes
Code	Description	Possible Solutions
4027001
Invalid input data
Some or all of the input data is not of the required format.
Check all the fields and parameters passed into the request are of the correct type and format, and that all required parameters are present.
4027005
Unable to create a welcome message sequence
An error occurred while trying to create a new welcome message sequence.
Check that the access token has all the required permissions for the WhatsApp business account.
4027006
Unable to update a welcome message sequence
Unable to update the welcome message sequence.
Check all fields and the sequence ID for correctness. Check that the access token has the necessary permissions for the WhatsApp business account.
4027007
API unavailable
The API being accessed is not available for use yet.
Wait a day or two for the API to become available and try again.
4027010
Missing parameter
One or more required parameters is missing.
Check all the documentation and ensure the required parameters are present.
4027012
Sequence used in an ad
The welcome message sequence is linked to an active ad and cannot be updated or deleted.
Disconnect the sequence from the ad and try again.
4027017
Could not load the sequence
Could not load the sequence being updated or deleted.
The welcome message sequence either does not exist, or you do not have permission to access it. Please check the access token and make sure you have the required permissions.