# Complete Feature Testing Guide

This guide provides comprehensive testing instructions for all 22 implemented Meta WhatsApp API features.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Analytics API](#1-analytics-api)
3. [Business-initiated Calls](#2-business-initiated-calls)
4. [Cashfree Payment Gateway](#3-cashfree-payment-gateway)
5. [Cloud API Calling Infrastructure](#4-cloud-api-calling-infrastructure)
6. [Configure Call Settings](#5-configure-call-settings)
7. [Contextual Replies](#6-contextual-replies)
8. [Interactive Media Carousel Messages](#7-interactive-media-carousel-messages)
9. [Interactive Reply Buttons Messages](#8-interactive-reply-buttons-messages)
10. [Link Previews](#9-link-previews)
11. [Media Management](#10-media-management)
12. [Obtain User Call Permissions](#11-obtain-user-call-permissions)
13. [Enhanced Sending Messages](#12-enhanced-sending-messages)
14. [Templates Management](#13-templates-management)
15. [Throughput Management](#14-throughput-management)
16. [Typing Indicators](#15-typing-indicators)
17. [User-initiated Calls](#16-user-initiated-calls)
18. [Utility Templates](#17-utility-templates)
19. [Welcome Message Sequences](#18-welcome-message-sequences)
20. [Database Schema](#19-database-schema)
21. [Call Routing to Agents](#20-call-routing-to-agents)
22. [React Native App Integration](#21-react-native-app-integration)

---

## Prerequisites

### Backend Setup
1. **Database Migration**: Run the SQL schema file
   ```bash
   mysql -u username -p database_name < database/calls_schema.sql
   ```

2. **Configuration**: Update `php/admin/settings.php` with:
   - WhatsApp Business Account ID (WABA ID) - Required for Analytics
   - Cashfree Client ID and Secret - For payments
   - All existing WhatsApp credentials

3. **API Endpoints**: Ensure all endpoints are accessible at `/api/whatsapp/*`

### Frontend Setup
1. **React App**: Ensure `npm install` is completed
2. **API Service**: Verify `app/src/services/ApiService.js` has all methods
3. **Pusher Service**: Configure Pusher credentials

---

## 1. Analytics API

### Testing Messaging Analytics

**Admin Panel:**
1. Navigate to: `Admin Panel > Communication > Analytics`
2. Select "Messaging" tab
3. Choose date range (default: last 30 days)
4. Select granularity (Day, Half Hour, Month)
5. Click "Load Analytics"

**API Test:**
```bash
curl -X GET "http://your-domain/api/whatsapp/analytics/messaging?start=1704067200&end=1704153600&granularity=DAY" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "data_points": [
      {
        "start": 1704067200,
        "end": 1704153600,
        "sent": 1500,
        "delivered": 1450
      }
    ]
  }
}
```

### Testing Conversation Analytics

**Admin Panel:**
1. Navigate to Analytics page
2. Click "Conversations" tab
3. Select date range and granularity
4. Optionally filter by:
   - Metric types (COST, CONVERSATION)
   - Conversation categories (AUTHENTICATION, MARKETING, SERVICE, UTILITY)
   - Conversation types (FREE_ENTRY, FREE_TIER, REGULAR)
   - Conversation directions (BUSINESS_INITIATED, USER_INITIATED)
5. Click "Load Analytics"

**API Test:**
```bash
curl -X GET "http://your-domain/api/whatsapp/analytics/conversation?start=1704067200&end=1704153600&granularity=DAILY&metric_types=[\"CONVERSATION\"]" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Testing Pricing Analytics

**Admin Panel:**
1. Navigate to Analytics page
2. Click "Pricing" tab
3. Select date range
4. Click "Load Analytics"

**API Test:**
```bash
curl -X GET "http://your-domain/api/whatsapp/analytics/pricing?start=1704067200&end=1704153600&granularity=DAILY" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Testing Template Analytics

**Admin Panel:**
1. Navigate to Analytics page
2. Click "Templates" tab
3. Select date range (max 90 days)
4. Optionally filter by template IDs
5. Click "Load Analytics"

**API Test:**
```bash
curl -X GET "http://your-domain/api/whatsapp/analytics/template?start=1704067200&end=1704153600&granularity=DAILY" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Verification:**
- ✅ Chart displays data points
- ✅ Table shows analytics data
- ✅ Data is cached (subsequent requests are faster)
- ✅ Date range validation works

---

## 2. Business-initiated Calls

### Prerequisites
- User must have granted call permission
- Call settings must be enabled

### Testing Call Initiation

**Admin Panel:**
1. Navigate to: `Admin Panel > Communication > Calls`
2. View call settings
3. Ensure calling is enabled

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/calls/initiate" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "to": "919876543210",
    "sdp_offer": "v=0\r\no=- 0 0 IN IP4 127.0.0.1\r\ns=-\r\nt=0 0\r\n",
    "biz_opaque_callback_data": "optional_data"
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "calls": [{
      "id": "call_123456",
      "status": "RINGING"
    }]
  }
}
```

### Testing Call Webhook

**Webhook Payload (from Meta):**
```json
{
  "entry": [{
    "changes": [{
      "field": "calls",
      "value": {
        "calls": [{
          "id": "call_123456",
          "event": "connect",
          "direction": "BUSINESS_INITIATED",
          "from": "919876543210",
          "status": "RINGING"
        }]
      }
    }]
  }]
}
```

**Verification:**
- ✅ Call record created in `whatsapp_calls` table
- ✅ Call routed to assigned agent
- ✅ Agent receives Pusher notification
- ✅ Call appears in admin Calls page

---

## 3. Cashfree Payment Gateway

### Setup
1. **Admin Panel Settings:**
   - Navigate to: `Admin Panel > Settings`
   - Scroll to "Cashfree Payment Gateway" section
   - Enter Client ID and Client Secret
   - Select Sandbox/Production mode

### Testing Order Creation

**Admin Panel:**
1. Navigate to: `Admin Panel > Communication > Payments`
2. Click "Create Order"
3. Fill in:
   - Order Amount
   - Customer Phone
   - Customer Email (optional)
   - Order Note (optional)
4. Click "Create Order"

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/payments/orders" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "order_amount": 100.50,
    "order_currency": "INR",
    "customer_phone": "919876543210",
    "customer_email": "customer@example.com",
    "order_note": "Test order",
    "payment_methods": "upi"
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "cf_order_id": 123456,
    "order_id": "order_123",
    "payment_session_id": "session_abc123",
    "order_status": "ACTIVE"
  }
}
```

### Testing Payment Session (UPI Intent URL)

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/payments/sessions" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "payment_session_id": "session_abc123",
    "upi_id": "customer@paytm",
    "upi_expiry_minutes": 10
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "data": {
      "payload": {
        "default": "upi://pay?pa=merchant@bank&pn=Merchant&tr=123&am=100.50&cu=INR"
      }
    }
  }
}
```

### Testing Payment Webhook

**Webhook Endpoint:** `POST /api/whatsapp/payments/webhook`

**Webhook Payload (from Cashfree):**
```json
{
  "data": {
    "order": {
      "order_id": "order_123",
      "order_amount": 100.50
    },
    "payment": {
      "cf_payment_id": 789012,
      "payment_status": "SUCCESS",
      "payment_method": "upi"
    }
  }
}
```

**Verification:**
- ✅ Order created in database
- ✅ Payment status updated
- ✅ Webhook data stored
- ✅ Order visible in Payments page

---

## 4. Cloud API Calling Infrastructure

### Testing Call Settings

**Admin Panel:**
1. Navigate to: `Admin Panel > Communication > Calls`
2. View "Call Settings" card
3. Configure:
   - Calling Status (Enabled/Disabled)
   - Call Icon Visibility
   - Callback Permission
   - Call Hours Status
4. Click "Save Settings"

**API Test - Get Settings:**
```bash
curl -X GET "http://your-domain/api/whatsapp/settings/calls" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**API Test - Update Settings:**
```bash
curl -X POST "http://your-domain/api/whatsapp/settings/calls" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "calling_status": "ENABLED",
    "call_icon_visibility": "DEFAULT",
    "callback_permission_status": "ENABLED",
    "call_hours_status": "ENABLED",
    "call_hours_timezone": "Asia/Kolkata",
    "call_hours_weekly_schedule": [
      {
        "day": "MONDAY",
        "open_time": "09:00",
        "close_time": "18:00"
      }
    ]
  }'
```

**Verification:**
- ✅ Settings saved to database
- ✅ Settings synced to Meta API
- ✅ Settings persist after page reload

---

## 5. Configure Call Settings

### Testing Call Hours Configuration

**Admin Panel:**
1. Navigate to Calls page
2. In Call Settings, enable "Call Hours Status"
3. Configure timezone and weekly schedule
4. Add holiday schedule if needed
5. Save settings

**Verification:**
- ✅ Calls only allowed during configured hours
- ✅ Holiday schedule respected
- ✅ Timezone conversion correct

### Testing SIP Configuration

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/settings/calls" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "sip_status": "ENABLED",
    "sip_servers": [
      {
        "host": "sip.example.com",
        "port": 5060,
        "username": "user",
        "password": "pass"
      }
    ]
  }'
```

---

## 6. Contextual Replies

### Testing Contextual Reply

**React Native App:**
1. Open a conversation
2. Long-press on a message
3. Select "Reply"
4. Type reply message
5. Send

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/contextual_reply" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "to": "919876543210",
    "message_body": "This is a reply to your previous message",
    "context_message_id": "wamid.xxx"
  }'
```

**Verification:**
- ✅ Message sent with context
- ✅ Original message quoted in WhatsApp
- ✅ Context stored in database

---

## 7. Interactive Media Carousel Messages

### Testing Carousel Message

**Admin Panel / React App:**
1. Navigate to conversation
2. Select "Send Carousel" option
3. Add 2-10 cards with:
   - Header (image/video)
   - Body text
   - Action button (URL)
4. Send message

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/send" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "to": "919876543210",
    "type": "interactive",
    "interactive": {
      "type": "carousel",
      "body": {
        "text": "Check out our products"
      },
      "action": {
        "cards": [
          {
            "card_index": 0,
            "type": "product",
            "header": {
              "type": "image",
              "image": {
                "link": "https://example.com/image1.jpg"
              }
            },
            "body": {
              "text": "Product 1"
            },
            "action": {
              "parameters": {
                "url": "https://example.com/product1"
              }
            }
          }
        ]
      }
    }
  }'
```

**Verification:**
- ✅ Carousel displays in WhatsApp
- ✅ User can swipe between cards
- ✅ Buttons work correctly
- ✅ Media loads properly

---

## 8. Interactive Reply Buttons Messages

### Testing Button Message

**React Native App:**
1. Open conversation
2. Select "Send Button Message"
3. Enter body text
4. Add up to 3 buttons with titles
5. Optionally add header and footer
6. Send

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/send" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "to": "919876543210",
    "type": "interactive",
    "interactive": {
      "type": "button",
      "body": {
        "text": "Choose an option:"
      },
      "action": {
        "buttons": [
          {
            "type": "reply",
            "reply": {
              "id": "btn_1",
              "title": "Option 1"
            }
          },
          {
            "type": "reply",
            "reply": {
              "id": "btn_2",
              "title": "Option 2"
            }
          }
        ]
      }
    }
  }'
```

### Testing Button Reply Webhook

**Webhook Payload (from Meta):**
```json
{
  "entry": [{
    "changes": [{
      "value": {
        "messages": [{
          "from": "919876543210",
          "id": "wamid.xxx",
          "type": "interactive",
          "interactive": {
            "type": "button_reply",
            "button_reply": {
              "id": "btn_1",
              "title": "Option 1"
            }
          }
        }]
      }
    }]
  }]
}
```

**Verification:**
- ✅ Button message sent successfully
- ✅ User can tap buttons
- ✅ Button reply received in webhook
- ✅ Reply stored in database with button ID

---

## 9. Link Previews

### Testing Link Preview

**Automatic Detection:**
1. Send a text message containing a URL
2. System automatically extracts link preview
3. Preview data cached for 7 days

**API Test:**
```bash
curl -X GET "http://your-domain/api/whatsapp/link/preview?url=https://example.com/article" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "title": "Article Title",
    "description": "Article description",
    "url": "https://example.com/article",
    "image": "https://example.com/image.jpg"
  }
}
```

**Verification:**
- ✅ Link preview extracted from URL
- ✅ og:title, og:description, og:image, og:url parsed
- ✅ Preview cached in database
- ✅ Preview displayed in message UI

---

## 10. Media Management

### Testing Media Upload

**Admin Panel:**
1. Navigate to: `Admin Panel > Communication > Media`
2. Click "Upload Media"
3. Select file (image, video, audio, document)
4. Click "Upload"

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/media/upload" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "file=@/path/to/image.jpg" \
  -F "mime_type=image/jpeg"
```

**Expected Response:**
```json
{
  "success": true,
  "media_id": "123456789"
}
```

### Testing Get Media URL

**API Test:**
```bash
curl -X GET "http://your-domain/api/whatsapp/media/123456789/url" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Testing Delete Media

**API Test:**
```bash
curl -X DELETE "http://your-domain/api/whatsapp/media/123456789" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Verification:**
- ✅ Media uploaded to WhatsApp
- ✅ Media ID returned
- ✅ Media URL retrievable
- ✅ Media deletable
- ✅ Media type detected correctly

---

## 11. Obtain User Call Permissions

### Testing Free-form Permission Request

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/call_permissions/request" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "to": "919876543210",
    "message_body": "We would like to call you. Do you grant permission?"
  }'
```

### Testing Get Permission Status

**API Test:**
```bash
curl -X GET "http://your-domain/api/whatsapp/call_permissions?user_wa_id=919876543210" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "permission_status": "temporary",
    "expiration_time": 1704153600,
    "is_permanent": false
  }
}
```

### Testing Permission Reply Webhook

**Webhook Payload:**
```json
{
  "entry": [{
    "changes": [{
      "value": {
        "call_permission_reply": [{
          "from": "919876543210",
          "response": "granted",
          "is_permanent": false,
          "expiration_timestamp": 1704153600
        }]
      }
    }]
  }]
}
```

**Verification:**
- ✅ Permission request sent
- ✅ User receives permission message
- ✅ Permission reply stored
- ✅ Permission status checkable
- ✅ Temporary permissions expire correctly

---

## 12. Enhanced Sending Messages

### Testing Address Message

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/message/address" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "to": "919876543210",
    "address": {
      "street": "123 Main St",
      "city": "Mumbai",
      "state": "Maharashtra",
      "zip": "400001",
      "country": "India",
      "country_code": "IN"
    }
  }'
```

### Testing Audio Message

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/message/audio" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "to": "919876543210",
    "audio_url": "https://example.com/audio.mp3"
  }'
```

### Testing Contacts Message

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/message/contacts" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "to": "919876543210",
    "contacts": [
      {
        "name": {
          "formatted_name": "John Doe"
        },
        "phones": [
          {
            "phone": "+919876543210",
            "type": "MOBILE"
          }
        ]
      }
    ]
  }'
```

### Testing Sticker Message

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/message/sticker" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "to": "919876543210",
    "sticker_url": "https://example.com/sticker.webp"
  }'
```

### Testing Reaction Message

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/message/reaction" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "to": "919876543210",
    "message_id": "wamid.xxx",
    "emoji": "👍"
  }'
```

**Verification:**
- ✅ All message types send successfully
- ✅ Messages display correctly in WhatsApp
- ✅ Media messages download properly
- ✅ Reactions appear on target message

---

## 13. Templates Management

### Testing Create Template (Named Parameters)

**Admin Panel:**
1. Navigate to: `Admin Panel > Communication > Templates`
2. Click "Create Template"
3. Fill in:
   - Template Name
   - Category (Authentication, Marketing, Utility)
   - Language
   - Parameter Format (Named/Positional)
   - Components (Header, Body, Footer, Buttons)
4. Submit

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/templates/create" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "name": "order_confirmation",
    "category": "utility",
    "language": "en_US",
    "parameter_format": "named",
    "components": [
      {
        "type": "BODY",
        "text": "Thank you, {{first_name}}! Your order number is {{order_number}}.",
        "example": {
          "body_text_named_params": [
            {
              "param_name": "first_name",
              "example": "Pablo"
            },
            {
              "param_name": "order_number",
              "example": "860198-230332"
            }
          ]
        }
      }
    ]
  }'
```

### Testing Create Template (Positional Parameters)

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/templates/create" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "name": "order_confirmation",
    "category": "utility",
    "language": "en_US",
    "parameter_format": "positional",
    "components": [
      {
        "type": "BODY",
        "text": "Hi {{1}}! Your order number is {{2}}. Thank you.",
        "example": {
          "body_text": [
            ["Pablo", "860198-230332"]
          ]
        }
      }
    ]
  }'
```

### Testing Get Template Status

**API Test:**
```bash
curl -X GET "http://your-domain/api/whatsapp/templates/TEMPLATE_ID/status" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Expected Response:**
```json
{
  "success": true,
  "status": "APPROVED",
  "quality_rating": "GREEN",
  "data": {
    "status": "APPROVED",
    "quality_rating": "GREEN"
  }
}
```

### Testing Send Template (Named Parameters)

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/templates/send/named" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "to": "919876543210",
    "template_name": "order_confirmation",
    "language": "en_US",
    "parameters": [
      {
        "component_type": "body",
        "params": [
          {
            "type": "text",
            "name": "first_name",
            "value": "Jessica"
          },
          {
            "type": "text",
            "name": "order_number",
            "value": "SKBUP2-4CPIG9"
          }
        ]
      }
    ]
  }'
```

### Testing Send Template (Positional Parameters)

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/templates/send/positional" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "to": "919876543210",
    "template_name": "order_confirmation",
    "language": "en_US",
    "parameters": [
      {
        "component_type": "body",
        "params": [
          {
            "type": "text",
            "value": "Jessica"
          },
          {
            "type": "text",
            "value": "SKBUP2-4CPIG9"
          }
        ]
      }
    ]
  }'
```

**Verification:**
- ✅ Template created via API
- ✅ Template status tracked (PENDING, APPROVED, REJECTED)
- ✅ Quality rating displayed
- ✅ Named parameters work correctly
- ✅ Positional parameters work correctly
- ✅ Template stored in database

---

## 14. Throughput Management

### Testing Get Throughput Level

**Admin Panel:**
1. Navigate to: `Admin Panel > Communication > Throughput`
2. View current throughput status
3. Monitor real-time message rate

**API Test:**
```bash
curl -X GET "http://your-domain/api/whatsapp/throughput" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Expected Response:**
```json
{
  "success": true,
  "throughput": {
    "level": "STANDARD",
    "max_throughput": 80
  }
}
```

### Testing Monitor Message Rate

**API Test:**
```bash
curl -X GET "http://your-domain/api/whatsapp/throughput/monitor" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Expected Response:**
```json
{
  "success": true,
  "current_rate": 15,
  "max_throughput": 80,
  "available_capacity": 65,
  "utilization_percent": 18.75,
  "can_send": true
}
```

### Testing Throughput History

**API Test:**
```bash
curl -X GET "http://your-domain/api/whatsapp/throughput/history?hours=24" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Testing Message Queue

**API Test - Process Queue:**
```bash
curl -X POST "http://your-domain/api/whatsapp/throughput/queue/process" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "phone_number_id": "123456",
    "limit": 10
  }'
```

**Verification:**
- ✅ Current throughput level displayed
- ✅ Real-time rate monitoring works
- ✅ Utilization percentage calculated
- ✅ Queue processes when capacity available
- ✅ History chart displays correctly
- ✅ Automatic upgrade webhook handled

---

## 15. Typing Indicators

### Testing Typing Indicator

**React Native App:**
1. Open conversation
2. Start typing a message
3. System automatically sends typing indicator

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/typing_indicator" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "to": "919876543210",
    "message_id": "wamid.xxx"
  }'
```

**Verification:**
- ✅ Typing indicator sent
- ✅ Indicator displays in WhatsApp
- ✅ Indicator dismisses after 25 seconds or response

---

## 16. User-initiated Calls

### Testing Pre-accept Call

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/calls/pre_accept" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "call_id": "call_123456",
    "sdp_answer": "v=0\r\no=- 0 0 IN IP4 127.0.0.1\r\ns=-\r\nt=0 0\r\n"
  }'
```

### Testing Accept Call

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/calls/accept" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "call_id": "call_123456",
    "sdp_answer": "v=0\r\no=- 0 0 IN IP4 127.0.0.1\r\ns=-\r\nt=0 0\r\n"
  }'
```

### Testing Reject Call

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/calls/reject" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "call_id": "call_123456"
  }'
```

### Testing Terminate Call

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/calls/terminate" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "call_id": "call_123456"
  }'
```

**Verification:**
- ✅ Incoming call received via webhook
- ✅ Call routed to assigned agent
- ✅ Agent can pre-accept, accept, reject, or terminate
- ✅ Call status updated in real-time
- ✅ Call duration tracked
- ✅ Call record stored in database

---

## 17. Utility Templates

### Testing Create Utility Template

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/templates/create" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "name": "order_update",
    "category": "utility",
    "language": "en_US",
    "components": [
      {
        "type": "HEADER",
        "format": "TEXT",
        "text": "Order Update"
      },
      {
        "type": "BODY",
        "text": "Your order {{1}} has been {{2}}."
      },
      {
        "type": "FOOTER",
        "text": "Thank you for shopping with us!"
      },
      {
        "type": "BUTTONS",
        "buttons": [
          {
            "type": "QUICK_REPLY",
            "text": "Track Order"
          },
          {
            "type": "URL",
            "text": "View Details",
            "url": "https://example.com/order/123"
          }
        ]
      }
    ]
  }'
```

**Verification:**
- ✅ Utility template created
- ✅ All components supported
- ✅ Template approved by Meta
- ✅ Template sendable

---

## 18. Welcome Message Sequences

### Testing Create Welcome Sequence

**Admin Panel:**
1. Navigate to: `Admin Panel > Communication > Welcome Sequences`
2. Click "Create Sequence"
3. Fill in:
   - Sequence Name
   - Welcome Text
   - Autofill Message (optional)
   - Ice Breakers (up to 3, one per line)
4. Click "Save Sequence"

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/welcome_sequences" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "name": "Driver Sign-up",
    "text": "Welcome! Thanks for clicking our ad.",
    "autofill_message": "Hello! Can I get more info on this!",
    "ice_breakers": [
      "Quick reply 1",
      "Quick reply 2",
      "Quick reply 3"
    ]
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "sequence_id": "186473890"
}
```

### Testing Update Welcome Sequence

**API Test:**
```bash
curl -X POST "http://your-domain/api/whatsapp/welcome_sequences" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "sequence_id": "186473890",
    "name": "Driver Sign-up Updated",
    "text": "Updated welcome message"
  }'
```

### Testing Get Welcome Sequences

**API Test:**
```bash
curl -X GET "http://your-domain/api/whatsapp/welcome_sequences" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Testing Delete Welcome Sequence

**API Test:**
```bash
curl -X DELETE "http://your-domain/api/whatsapp/welcome_sequences/186473890" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Verification:**
- ✅ Sequence created successfully
- ✅ Sequence ID returned
- ✅ Sequence updatable
- ✅ Sequence listable
- ✅ Sequence deletable (if not linked to active ad)
- ✅ Sequence stored in database

---

## 19. Database Schema

### Verification Steps

1. **Check Tables Exist:**
   ```sql
   SHOW TABLES LIKE 'whatsapp_%';
   ```

2. **Verify Call Tables:**
   ```sql
   DESCRIBE whatsapp_calls;
   DESCRIBE whatsapp_call_permissions;
   DESCRIBE whatsapp_call_settings;
   ```

3. **Verify Analytics Table:**
   ```sql
   DESCRIBE whatsapp_analytics;
   ```

4. **Verify Payment Tables:**
   ```sql
   DESCRIBE whatsapp_payment_orders;
   ```

5. **Verify Welcome Sequences:**
   ```sql
   DESCRIBE whatsapp_welcome_sequences;
   ```

6. **Verify Throughput Tables:**
   ```sql
   DESCRIBE whatsapp_throughput_metrics;
   DESCRIBE whatsapp_throughput_upgrades;
   DESCRIBE whatsapp_message_queue;
   ```

7. **Verify Link Preview Cache:**
   ```sql
   DESCRIBE link_preview_cache;
   ```

**Verification:**
- ✅ All tables created
- ✅ Foreign keys configured
- ✅ Indexes created
- ✅ Default values set
- ✅ Constraints applied

---

## 20. Call Routing to Agents

### Testing Call Routing

**Scenario:**
1. User initiates call to business
2. System receives webhook
3. System finds conversation
4. System gets assigned agent
5. System routes call to agent
6. Agent receives Pusher notification
7. Agent's React Native app navigates to CallScreen

### Verification Steps

1. **Create Test Conversation:**
   - Send a message from test number
   - Assign conversation to an agent

2. **Initiate Call:**
   - Call business from test number
   - Or use API to simulate incoming call

3. **Check Database:**
   ```sql
   SELECT * FROM whatsapp_calls WHERE phone_number = 'TEST_NUMBER' ORDER BY created_at DESC LIMIT 1;
   ```
   - Verify `assigned_agent_id` is set
   - Verify `conversation_id` is linked

4. **Check Pusher:**
   - Verify agent receives `whatsapp_call_update` event
   - Verify event contains call details

5. **Check React Native App:**
   - Verify CallScreen opens automatically
   - Verify call details displayed
   - Verify controls work (accept, reject, hangup)

**Verification:**
- ✅ Call routed to correct agent
- ✅ Agent notified via Pusher
- ✅ React Native app navigates to CallScreen
- ✅ Call controls functional
- ✅ Call status updates in real-time

---

## 21. React Native App Integration

### Testing API Methods

**All API methods are available in `ApiService.js`:**

1. **Call Methods:**
   - `initiateCall()`
   - `preAcceptCall()`
   - `acceptCall()`
   - `rejectCall()`
   - `terminateCall()`
   - `getCall()`
   - `getCalls()`

2. **Call Settings:**
   - `getCallSettings()`
   - `updateCallSettings()`

3. **Call Permissions:**
   - `getCallPermissionStatus()`
   - `sendCallPermissionRequest()`

4. **Analytics:**
   - `getMessagingAnalytics()`
   - `getConversationAnalytics()`
   - `getPricingAnalytics()`
   - `getTemplateAnalytics()`

5. **Media:**
   - `uploadMedia()`
   - `getMediaUrl()`
   - `deleteMedia()`

6. **Payments:**
   - `createPaymentOrder()`
   - `getPaymentSession()`
   - `getPaymentStatus()`
   - `processRefund()`

7. **Welcome Sequences:**
   - `createWelcomeSequence()`
   - `updateWelcomeSequence()`
   - `getWelcomeSequences()`
   - `deleteWelcomeSequence()`

8. **Throughput:**
   - `getThroughput()`
   - `monitorThroughput()`
   - `getThroughputHistory()`
   - `processMessageQueue()`

9. **Messages:**
   - `sendAddressMessage()`
   - `sendAudioMessage()`
   - `sendContactsMessage()`
   - `sendStickerMessage()`
   - `sendReactionMessage()`
   - `sendMediaCarouselMessage()`
   - `sendButtonMessage()`
   - `sendTypingIndicator()`
   - `sendContextualReply()`

10. **Templates:**
    - `createTemplate()`
    - `getTemplateStatus()`
    - `deleteTemplate()`
    - `sendTemplateNamed()`
    - `sendTemplatePositional()`

### Testing Pusher Integration

**Verify Pusher Events:**
1. Open React Native app
2. Login as agent
3. Check console for Pusher connection
4. Send test message
5. Verify `whatsapp_message` event received
6. Initiate test call
7. Verify `whatsapp_call_update` event received

**Verification:**
- ✅ All API methods accessible
- ✅ Pusher connection established
- ✅ Real-time events received
- ✅ Call navigation works
- ✅ Message updates work

---

## 22. Code Cleanup Verification

### Check Removed Files

1. Verify `.updates` files removed
2. Verify duplicate functions removed
3. Verify unused imports removed

### Code Quality Checks

1. **Linter:**
   ```bash
   cd app
   npm run lint
   ```

2. **PHP Syntax:**
   ```bash
   php -l php/includes/whatsapp_*.php
   ```

**Verification:**
- ✅ No compilation errors
- ✅ No linter warnings
- ✅ No unused code
- ✅ Clean codebase

---

## Admin Panel Access

### Navigation Structure

All new features are accessible via:

**Admin Panel > Communication:**
- Live Chats
- Templates
- **Calls** (NEW)
- **Analytics** (NEW)
- **Media** (NEW)
- **Payments** (NEW)
- **Welcome Sequences** (NEW)
- **Throughput** (NEW)
- Campaigns
- Campaign Recipients
- Notifications
- Reminders

### Settings Configuration

**Admin Panel > Settings:**
- WhatsApp Cloud API section (existing)
- **Cashfree Payment Gateway** section (NEW)
- All existing settings

---

## Quick Test Checklist

- [ ] Analytics API - All 4 types (messaging, conversation, pricing, template)
- [ ] Business-initiated calls - Initiate, webhook, routing
- [ ] Cashfree Payments - Create order, get session, webhook
- [ ] Call Settings - Get, update, save
- [ ] Contextual replies - Send with message_id
- [ ] Carousel messages - Send with 2-10 cards
- [ ] Button messages - Send, receive reply
- [ ] Link previews - Extract and cache
- [ ] Media management - Upload, get URL, delete
- [ ] Call permissions - Request, check status
- [ ] Enhanced messages - Address, audio, contacts, sticker, reaction
- [ ] Templates - Create (named/positional), send, check status
- [ ] Throughput - Monitor, history, queue
- [ ] Typing indicators - Send
- [ ] User-initiated calls - Accept, reject, terminate
- [ ] Utility templates - Create with all components
- [ ] Welcome sequences - Create, update, delete
- [ ] Database - All tables exist
- [ ] Call routing - Routes to agents
- [ ] React Native - All API methods work
- [ ] Admin Panel - All pages accessible

---

## Troubleshooting

### Common Issues

1. **Analytics not loading:**
   - Check WABA ID is set in settings
   - Verify date range is valid (max 1 year for messaging/conversation, 90 days for templates)

2. **Calls not routing:**
   - Verify conversation exists
   - Check agent assignment
   - Verify Pusher configuration

3. **Payments failing:**
   - Check Cashfree credentials
   - Verify sandbox/production mode
   - Check order amount format

4. **Templates not approved:**
   - Wait for Meta review (can take hours/days)
   - Check template quality rating
   - Review rejected reason if applicable

5. **Media upload failing:**
   - Check file size (max 15MB)
   - Verify MIME type
   - Check WhatsApp media limits

---

## Support

For issues or questions:
1. Check API response errors
2. Review webhook logs
3. Check database records
4. Verify Meta API status
5. Review implementation code

---

**Last Updated:** December 2024
**Version:** 1.0

