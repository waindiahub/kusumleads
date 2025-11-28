Media
Updated: Nov 17, 2025
You use 4 different endpoints to manage your media:
Endpoint	Uses
POST /PHONE_NUMBER_ID/media
Upload media.
GET /MEDIA_ID
Retrieve the URL for a specific media.
DELETE /MEDIA_ID
Delete a specific media.
GET /MEDIA_URL
Download media from a media URL.
See Supported Media Types for supported types and size limits.
Get media ID
Some of the API requests described in this document require a media ID. Media IDs are returned by the API when uploading media, and are included in incoming media messages webhooks (image messages, video messages, etc.)
Media IDs returned by the API expire after 30 days. Media IDs in webhooks expire after 7 days.
Upload media
To upload media, make a POST call to /PHONE_NUMBER_ID/media and include the parameters listed below. All media files sent through this endpoint are encrypted and persist for 30 days, unless they are deleted earlier.
Endpoint	Authentication
/PHONE_NUMBER_ID/media
(See Get Phone Number ID)
Developers can authenticate their API calls with the access token generated in the App Dashboard > WhatsApp > API Setup.

Solution Partners must authenticate themselves with an access token with the whatsapp_business_messaging permission.
Parameters
Name	Description
file
Required.
Path to the file stored in your local directory. For example: “@/local/path/file.jpg”.
type
Required.
Type of media file being uploaded. See Supported Media Types for more information.
messaging_product
Required.
Messaging service used for the request. In this case, use whatsapp.
Request
curl 'https://graph.facebook.com/<API_VERSION>/<PHONE_NUMBER_ID>/media' \
-H 'Authorization: Bearer <ACCESS_TOKEN>' \
-F 'messaging_product=whatsapp' \
-F 'file=@<FILE_PATH_AND_NAME>;type=<MIME_TYPE>'

Response
Upon success:
{
  "id": "<MEDIA_ID>"
}

Example request
curl 'https://graph.facebook.com/v24.0/106540352242922/media' \
-H 'Authorization: Bearer EAAJB...' \
-F 'messaging_product=whatsapp' \
-F 'file=@/media/template_assets/black_friday_2025.mp4;type=video/mp4'
Example response
{
  "id": "1037543291543636"
}

Get media URL
You can query a media ID directly to get a media URL, which you can then query directly with your access token to download the media asset.
Starting November 12, 2025, incoming media messages webhooks (image messages, video messages, etc.) will include the media url automatically, and assign it to a new url property. This property is being released to developers gradually over several weeks, so may not be available to you immediately.
Media URLs expire after 5 minutes, after which you must query the ID again to get a new URL.
Request syntax
curl 'https://graph.facebook.com/<API_VERSION>/<MEDIA_ID>?phone_number_id=<BUSINESS_PHONE_NUMBER_ID>' \
-H 'Authorization: Bearer EAAJB'

Note that phone_number_id is optional. If included, the request will only be processed if the business phone number ID included in the query matches the ID of the business phone number that the media was uploaded on.
Response syntax
A successful response includes an object with a media url. The URL is only valid for 5 minutes. To use this URL, see Download Media.
{
  "messaging_product": "whatsapp",
  "url": "<MEDIA_URL>",
  "mime_type": "<MEDIA_MIME_TYPE>",
  "sha256": "<SHA_256_HASH>",
  "file_size": "<MEDIA_FILE_SIZE>",
  "id": "<MEDIA_ID>"
}

Delete media
Use the DELETE /<MEDIA_ID> endpoint to delete a media asset.
Request syntax
curl -X DELETE 'https://graph.facebook.com/<API_VERSION>/<MEDIA_ID>?phone_number_id=<BUSINESS_PHONE_NUMBER_ID>' \
-H 'Authorization: Bearer EAAJB...'

Note that phone_number_id is optional. If included, the request will only be processed if the business phone number ID included in the query matches the ID of the business phone number that the media was uploaded on.
Example response
{
  "success": true
}

Download media
To download media, make a GET request on the media URL and include your access token. If you omit your token, the request will fail.
Note that when retrieving a media from a media ID received via webhook, the media ID will only be available to download for 7 days.
Request syntax
curl '<MEDIA_URL>' \
-H 'Authorization: Bearer EAAJB...' \
-o '<DESIRED_FILE_NAME>'

Upon success, the API will respond with the binary data of the media asset. Response headers contain a content-type header to indicate the mime type of returned data. Check supported media types for supported media types.
If the download attempt fails, you will receive a 404 Not Found response code. In that case, we recommend you try to get a new media URL and download it again. If doing so doesn’t resolve the issue, renew your access token and attempt to download the media asset again.
Supported media types
Audio
Audio Type	Extension	MIME Type	Max Size
AAC
.aac
audio/aac
16 MB
AMR
.amr
audio/amr
16 MB
MP3
.mp3
audio/mpeg
16 MB
MP4 Audio
.m4a
audio/mp4
16 MB
OGG Audio
.ogg
audio/ogg (OPUS codecs only; base audio/ogg not supported; mono input only)
16 MB
Document
Document Type	Extension	MIME Type	Max Size
Text
.txt
text/plain
100 MB
Microsoft Excel
.xls
application/vnd.ms-excel
100 MB
Microsoft Excel
.xlsx
application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
100 MB
Microsoft Word
.doc
application/msword
100 MB
Microsoft Word
.docx
application/vnd.openxmlformats-officedocument.wordprocessingml.document
100 MB
Microsoft PowerPoint
.ppt
application/vnd.ms-powerpoint
100 MB
Microsoft PowerPoint
.pptx
application/vnd.openxmlformats-officedocument.presentationml.presentation
100 MB
PDF
.pdf
application/pdf
100 MB
Image
Images must be 8-bit, RGB or RGBA.


Image Type	Extension	MIME Type	Max Size
JPEG
.jpeg
image/jpeg
5 MB
PNG
.png
image/png
5 MB
Sticker
WebP images can only be sent in sticker messages.
Sticker Type	Extension	MIME Type	Max Size
Animated sticker
.webp
image/webp
500 KB
Static sticker
.webp
image/webp
100 KB
Video
Only H.264 video codec and AAC audio codec supported. Single audio stream or no audio stream only.


Video Type	Extension	MIME Type	Max Size
3GPP
.3gp
video/3gpp
16 MB
MP4 Video
.mp4
video/mp4
16 MB
Note that mismatched MIME type (131053) is a common error. We recommend that you inspect your media files to verify their MIME type, and make sure that your file name extensions reflect their types. For example, if you are using UNIX, you can inspect a file via the command line to determine its MIME type:
file -I your-image-asset.png
Media message download constraints
The maximum supported file size for media messages on Cloud API is 100MB. In the event the customer sends a file that is greater than 100MB, you will receive a webhook with error code 131052 and title:
“Media file size too big. Max file size we currently support: 100MB. Please communicate with your customer to send a media file that is smaller than 100MB”.
We advise that you send customers a warning message that their media file exceeds the maximum file size when this webhook event is triggered.