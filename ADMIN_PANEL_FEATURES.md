# Admin Panel Features Integration Guide

This document explains how to access and use all new Meta WhatsApp API features through the admin panel.

## Admin Panel Navigation

### New Menu Items Added

All new features are accessible from the **Communication** section in the sidebar:

1. **Calls** - `/admin/whatsapp_calls.php`
   - View all calls
   - Manage call settings
   - Monitor call status

2. **Analytics** - `/admin/whatsapp_analytics.php`
   - Messaging analytics
   - Conversation analytics
   - Pricing analytics
   - Template analytics

3. **Media** - `/admin/whatsapp_media.php`
   - Upload media
   - View media library
   - Delete media

4. **Payments** - `/admin/whatsapp_payments.php`
   - Create payment orders
   - View payment history
   - Process refunds

5. **Welcome Sequences** - `/admin/whatsapp_welcome_sequences.php`
   - Create welcome sequences
   - Edit sequences
   - Delete sequences

6. **Throughput** - `/admin/whatsapp_throughput.php`
   - Monitor message rate
   - View throughput history
   - Manage message queue

## Settings Configuration

### New Settings Sections

Navigate to **Admin Panel > Settings** to configure:

#### 1. WhatsApp Business Account ID
- **Location:** WhatsApp Cloud API section
- **Field:** Business Account ID (WABA ID)
- **Required for:** Analytics, Welcome Sequences
- **How to get:** From Meta Business Manager

#### 2. Cashfree Payment Gateway
- **Location:** New section in Settings
- **Fields:**
  - Client ID
  - Client Secret
  - Sandbox Mode (Enabled/Disabled)
- **Required for:** Payment processing

## Feature Access Guide

### 1. Calls Management

**Access:** `Admin Panel > Communication > Calls`

**Features:**
- View all calls (incoming/outgoing)
- Filter by status, direction, agent
- Configure call settings:
  - Enable/disable calling
  - Set call hours
  - Configure callback permissions
  - Set SIP configuration
- View call details (duration, SDP, timestamps)

**Usage:**
1. Click "Calls" in sidebar
2. View call settings card at top
3. Adjust settings and click "Save Settings"
4. View recent calls in table below
5. Click eye icon to view call details

### 2. Analytics Dashboard

**Access:** `Admin Panel > Communication > Analytics`

**Features:**
- Switch between analytics types (Messaging, Conversations, Pricing, Templates)
- Select date range
- Choose granularity (Day, Half Hour, Month)
- View charts and data tables
- Export analytics data

**Usage:**
1. Click "Analytics" in sidebar
2. Select analytics type from tabs
3. Choose date range (default: last 30 days)
4. Select granularity
5. Click "Load Analytics"
6. View chart and table data

**Note:** Analytics are cached for 1 hour to improve performance.

### 3. Media Management

**Access:** `Admin Panel > Communication > Media`

**Features:**
- Upload media files (images, videos, audio, documents)
- View media library
- Get media URLs
- Delete media

**Usage:**
1. Click "Media" in sidebar
2. Click "Upload Media" button
3. Select file (max 15MB)
4. Click "Upload"
5. Media ID will be returned
6. Use media ID in messages

**Supported Formats:**
- Images: JPEG, PNG, GIF, WebP
- Videos: MP4, 3GP
- Audio: MP3, OGG, AMR
- Documents: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX

### 4. Payment Orders

**Access:** `Admin Panel > Communication > Payments`

**Features:**
- Create payment orders
- View order history
- Check payment status
- Process refunds
- View payment details

**Usage:**
1. Click "Payments" in sidebar
2. Click "Create Order"
3. Fill in:
   - Order Amount
   - Customer Phone
   - Customer Email (optional)
   - Order Note (optional)
4. Click "Create Order"
5. Get payment session ID
6. Use session ID to get UPI intent URL
7. Send UPI URL to customer via WhatsApp

**Payment Flow:**
1. Create order → Get `cf_order_id` and `payment_session_id`
2. Get payment session → Get UPI intent URL
3. Send UPI URL to customer
4. Customer pays via UPI
5. Receive webhook with payment status
6. Update order status

### 5. Welcome Message Sequences

**Access:** `Admin Panel > Communication > Welcome Sequences`

**Features:**
- Create welcome sequences for Click-to-WhatsApp ads
- Add welcome text
- Add autofill message
- Add up to 3 ice breakers
- Edit existing sequences
- Delete sequences

**Usage:**
1. Click "Welcome Sequences" in sidebar
2. Click "Create Sequence"
3. Fill in:
   - Sequence Name
   - Welcome Text (required)
   - Autofill Message (optional)
   - Ice Breakers (one per line, max 3)
4. Click "Save Sequence"
5. Get sequence ID
6. Link sequence to your Meta ad

**Example:**
```
Name: Driver Sign-up
Text: Welcome! Thanks for clicking our ad. How can we help you today?
Autofill: Hello! Can I get more info on this!
Ice Breakers:
- I want to sign up
- Tell me more
- Contact me
```

### 6. Throughput Management

**Access:** `Admin Panel > Communication > Throughput`

**Features:**
- View current message rate (messages/second)
- View max throughput level (80/1000 mps)
- Monitor utilization percentage
- View throughput history chart
- Manage message queue
- Process queued messages

**Usage:**
1. Click "Throughput" in sidebar
2. View current status cards:
   - Current Rate
   - Max Throughput
   - Utilization
3. View throughput history chart
4. View message queue table
5. Click "Process Queue" to send queued messages

**Throughput Levels:**
- **STANDARD:** 80 messages/second (default)
- **HIGH:** 1,000 messages/second (auto-upgrade when eligible)
- **COEXISTENCE:** 20 messages/second (WhatsApp Business app + Cloud API)

**Auto-upgrade Requirements:**
- Unlimited messaging limit
- 100K+ unique users messaged in 24 hours
- Quality score: YELLOW or higher

## API Endpoints Reference

All features are accessible via REST API. Base URL: `/api/whatsapp/`

### Calls
- `GET /whatsapp/calls` - List calls
- `GET /whatsapp/calls/{callId}` - Get call details
- `POST /whatsapp/calls/initiate` - Initiate call
- `POST /whatsapp/calls/accept` - Accept call
- `POST /whatsapp/calls/reject` - Reject call
- `POST /whatsapp/calls/terminate` - Terminate call
- `GET /whatsapp/settings/calls` - Get call settings
- `POST /whatsapp/settings/calls` - Update call settings

### Analytics
- `GET /whatsapp/analytics/messaging` - Messaging analytics
- `GET /whatsapp/analytics/conversation` - Conversation analytics
- `GET /whatsapp/analytics/pricing` - Pricing analytics
- `GET /whatsapp/analytics/template` - Template analytics
- `GET /whatsapp/analytics/template_group` - Template group analytics

### Media
- `POST /whatsapp/media/upload` - Upload media
- `GET /whatsapp/media/{mediaId}/url` - Get media URL
- `DELETE /whatsapp/media/{mediaId}` - Delete media
- `GET /whatsapp/media/download` - Download and store media

### Payments
- `POST /whatsapp/payments/orders` - Create order
- `POST /whatsapp/payments/sessions` - Get payment session
- `GET /whatsapp/payments/status/{orderId}` - Get payment status
- `POST /whatsapp/payments/refund` - Process refund
- `POST /whatsapp/payments/webhook` - Cashfree webhook

### Welcome Sequences
- `POST /whatsapp/welcome_sequences` - Create/update sequence
- `GET /whatsapp/welcome_sequences` - List sequences
- `DELETE /whatsapp/welcome_sequences/{sequenceId}` - Delete sequence

### Throughput
- `GET /whatsapp/throughput` - Get throughput level
- `GET /whatsapp/throughput/monitor` - Monitor message rate
- `GET /whatsapp/throughput/history` - Get throughput history
- `POST /whatsapp/throughput/queue/process` - Process message queue

### Call Permissions
- `GET /whatsapp/call_permissions` - Get permission status
- `POST /whatsapp/call_permissions/request` - Request permission

### Templates
- `POST /whatsapp/templates/create` - Create template
- `GET /whatsapp/templates/{templateId}/status` - Get template status
- `DELETE /whatsapp/templates/{templateId}` - Delete template
- `POST /whatsapp/templates/send/named` - Send template (named params)
- `POST /whatsapp/templates/send/positional` - Send template (positional params)

### Messages
- `POST /whatsapp/message/address` - Send address
- `POST /whatsapp/message/audio` - Send audio
- `POST /whatsapp/message/contacts` - Send contacts
- `POST /whatsapp/message/sticker` - Send sticker
- `POST /whatsapp/message/reaction` - Send reaction
- `POST /whatsapp/typing_indicator` - Send typing indicator
- `POST /whatsapp/contextual_reply` - Send contextual reply
- `GET /whatsapp/link/preview` - Get link preview

## Authentication

All API endpoints require JWT authentication:

```javascript
headers: {
  'Authorization': 'Bearer YOUR_JWT_TOKEN'
}
```

Get token from login endpoint or use session-based auth in admin panel.

## Webhooks

### Meta Webhooks

**Endpoint:** `POST /api/whatsapp/webhook`

**Subscribed Events:**
- `messages` - Incoming messages
- `calls` - Call events
- `call_permission_reply` - Permission responses
- `phone_number_quality_update` - Throughput upgrades

### Cashfree Webhooks

**Endpoint:** `POST /api/whatsapp/payments/webhook`

**Events:**
- Payment success
- Payment failure
- Payment cancellation

## Database Tables

All features use these database tables:

- `whatsapp_calls` - Call records
- `whatsapp_call_permissions` - User permissions
- `whatsapp_call_settings` - Call configuration
- `whatsapp_analytics` - Cached analytics
- `whatsapp_payment_orders` - Payment orders
- `whatsapp_welcome_sequences` - Welcome sequences
- `whatsapp_throughput_metrics` - Throughput metrics
- `whatsapp_throughput_upgrades` - Upgrade history
- `whatsapp_message_queue` - Queued messages
- `link_preview_cache` - Link preview cache
- `whatsapp_templates` - Enhanced template storage

## React Native Integration

All features are accessible via `ApiService` methods in React Native app:

```javascript
import { apiService } from './services/ApiService';

// Calls
await apiService.initiateCall(to, sdpOffer);
await apiService.acceptCall(callId, sdpAnswer);
await apiService.terminateCall(callId);

// Analytics
await apiService.getMessagingAnalytics(start, end);
await apiService.getConversationAnalytics(start, end);

// Payments
await apiService.createPaymentOrder(orderData);
await apiService.getPaymentSession(sessionId);

// Media
await apiService.uploadMedia(filePath, mimeType);
await apiService.getMediaUrl(mediaId);

// Welcome Sequences
await apiService.createWelcomeSequence(sequenceData);
await apiService.getWelcomeSequences();

// Throughput
await apiService.getThroughput();
await apiService.monitorThroughput();

// And many more...
```

## Quick Start Checklist

1. ✅ Run database migration: `database/calls_schema.sql`
2. ✅ Configure settings in Admin Panel:
   - WhatsApp Business Account ID (WABA ID)
   - Cashfree credentials
   - All existing WhatsApp settings
3. ✅ Access new features from sidebar
4. ✅ Test each feature using admin panels
5. ✅ Verify API endpoints work
6. ✅ Test React Native app integration
7. ✅ Monitor webhooks
8. ✅ Check database records

## Support

For detailed testing instructions, see: `COMPLETE_FEATURE_TESTING_GUIDE.md`

For implementation details, see: `IMPLEMENTATION_SUMMARY.md`

---

**Last Updated:** December 2024
**Version:** 1.0

