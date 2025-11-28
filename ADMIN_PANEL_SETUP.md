# Admin Panel Setup and Configuration

## Overview

All 22 Meta WhatsApp API features are now integrated into the admin panel with dedicated pages and navigation.

## New Admin Pages Created

1. **`php/admin/whatsapp_calls.php`** - Call management and settings
2. **`php/admin/whatsapp_analytics.php`** - Analytics dashboard
3. **`php/admin/whatsapp_media.php`** - Media upload and management
4. **`php/admin/whatsapp_payments.php`** - Payment order management
5. **`php/admin/whatsapp_welcome_sequences.php`** - Welcome sequence management
6. **`php/admin/whatsapp_throughput.php`** - Throughput monitoring

## Sidebar Navigation Updated

The sidebar (`php/admin/sidebar.php`) now includes:

**Communication Section:**
- Live Chats (existing)
- Templates (existing)
- **Calls** (NEW)
- **Analytics** (NEW)
- **Media** (NEW)
- **Payments** (NEW)
- **Welcome Sequences** (NEW)
- **Throughput** (NEW)
- Campaigns (existing)
- Campaign Recipients (existing)
- Notifications (existing)
- Reminders (existing)

## Settings Configuration

### New Settings Fields

**File:** `php/admin/settings.php`

**WhatsApp Cloud API Section:**
- Business Account ID (WABA ID) - Required for Analytics and Welcome Sequences

**New Section: Cashfree Payment Gateway**
- Client ID
- Client Secret
- Sandbox Mode (Enabled/Disabled)

### How to Configure

1. Navigate to: `Admin Panel > Settings`
2. Scroll to "WhatsApp Cloud API" section
3. Enter **Business Account ID (WABA ID)** - Get this from Meta Business Manager
4. Scroll to "Cashfree Payment Gateway" section
5. Enter Cashfree credentials:
   - Client ID
   - Client Secret
   - Enable/Disable Sandbox mode
6. Click "Save Settings"

## API Endpoints

All admin pages use REST API endpoints at `/api/whatsapp/*`

### Authentication

Admin pages use JWT tokens from:
1. `sessionStorage.getItem('jwt')` (primary)
2. `localStorage.getItem('admin_token')` (fallback)

### API Base URL

All admin pages use: `../api` (relative to admin directory)

This resolves to: `/api/whatsapp/*` endpoints

## Testing the Admin Pages

### 1. Calls Page

**URL:** `/admin/whatsapp_calls.php`

**Features to Test:**
- View call settings card
- Update call settings (enable/disable, call hours, etc.)
- View recent calls table
- Refresh calls list
- View call details

**Expected Behavior:**
- Settings load from database
- Settings can be saved
- Calls list displays recent calls
- Auto-refresh every 30 seconds

### 2. Analytics Page

**URL:** `/admin/whatsapp_analytics.php`

**Features to Test:**
- Switch between analytics types (Messaging, Conversations, Pricing, Templates)
- Select date range (default: last 30 days)
- Select granularity (Day, Half Hour, Month)
- View charts (Chart.js)
- View data tables
- Load analytics data

**Prerequisites:**
- WABA ID must be configured in Settings
- Analytics data available from Meta API

**Expected Behavior:**
- Charts render correctly
- Data tables populate
- Date range validation works
- Analytics are cached (subsequent loads faster)

### 3. Media Page

**URL:** `/admin/whatsapp_media.php`

**Features to Test:**
- Upload media files
- View media library (basic implementation)
- Get media URLs
- Delete media

**Expected Behavior:**
- File upload works
- Media ID returned
- Media stored in WhatsApp
- Media accessible via URL

### 4. Payments Page

**URL:** `/admin/whatsapp_payments.php`

**Features to Test:**
- Create payment orders
- View order history
- Check payment status
- Process refunds

**Prerequisites:**
- Cashfree credentials configured in Settings

**Expected Behavior:**
- Orders created successfully
- Order ID returned
- Payment session ID generated
- Orders visible in table

### 5. Welcome Sequences Page

**URL:** `/admin/whatsapp_welcome_sequences.php`

**Features to Test:**
- Create welcome sequences
- Edit sequences
- Delete sequences
- View sequence list

**Prerequisites:**
- WABA ID must be configured in Settings

**Expected Behavior:**
- Sequences created successfully
- Sequence ID returned
- Sequences listable
- Sequences deletable (if not linked to active ad)

### 6. Throughput Page

**URL:** `/admin/whatsapp_throughput.php`

**Features to Test:**
- View current message rate
- View max throughput level
- Monitor utilization percentage
- View throughput history chart
- Process message queue

**Expected Behavior:**
- Current rate updates every 5 seconds
- Utilization percentage calculated
- History chart displays
- Queue processing works

## API Integration

### How Admin Pages Call APIs

All admin pages use `fetch()` API with JWT authentication:

```javascript
const res = await fetch(`${API_BASE}/whatsapp/endpoint`, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + getToken()
    },
    body: JSON.stringify(data)
});
```

### Token Retrieval

```javascript
function getToken() {
    return sessionStorage.getItem('jwt') || localStorage.getItem('admin_token') || '';
}
```

## Database Requirements

Ensure all tables are created by running:

```bash
mysql -u username -p database_name < database/calls_schema.sql
```

**Required Tables:**
- `whatsapp_calls`
- `whatsapp_call_permissions`
- `whatsapp_call_settings`
- `whatsapp_analytics`
- `whatsapp_payment_orders`
- `whatsapp_welcome_sequences`
- `whatsapp_throughput_metrics`
- `whatsapp_throughput_upgrades`
- `whatsapp_message_queue`
- `link_preview_cache`

## Troubleshooting

### Admin Pages Not Loading

1. **Check PHP Errors:**
   ```bash
   tail -f /var/log/php_errors.log
   ```

2. **Check File Permissions:**
   ```bash
   chmod 644 php/admin/whatsapp_*.php
   ```

3. **Check Session:**
   - Ensure logged in as admin
   - Check session is active

### API Calls Failing

1. **Check JWT Token:**
   - Open browser console
   - Check `sessionStorage.getItem('jwt')`
   - Verify token is valid

2. **Check API Endpoints:**
   - Verify `/api/whatsapp/*` endpoints exist
   - Check API routing in `php/includes/whatsapp.php`

3. **Check CORS:**
   - Verify CORS headers in `php/cors.php`
   - Check API allows admin origin

### Settings Not Saving

1. **Check Database:**
   - Verify `settings` table exists
   - Check write permissions

2. **Check Form Submission:**
   - Verify form action is correct
   - Check POST data is sent

### Analytics Not Loading

1. **Check WABA ID:**
   - Verify WABA ID is set in Settings
   - Check WABA ID is valid

2. **Check Date Range:**
   - Messaging/Conversation: Max 1 year
   - Templates: Max 90 days
   - Pricing: Max 1 year

3. **Check Meta API:**
   - Verify access token is valid
   - Check API rate limits

### Payments Not Working

1. **Check Cashfree Credentials:**
   - Verify Client ID and Secret
   - Check Sandbox/Production mode

2. **Check Order Creation:**
   - Verify order amount format
   - Check customer phone format

## Next Steps

1. **Test Each Admin Page:**
   - Access each page from sidebar
   - Test all features
   - Verify data loads correctly

2. **Configure Settings:**
   - Add WABA ID
   - Add Cashfree credentials
   - Configure call settings

3. **Test API Endpoints:**
   - Use Postman or curl
   - Test with JWT token
   - Verify responses

4. **Test React Native Integration:**
   - Verify all API methods work
   - Test Pusher notifications
   - Test call routing

## Documentation

- **Complete Testing Guide:** `COMPLETE_FEATURE_TESTING_GUIDE.md`
- **Admin Panel Features:** `ADMIN_PANEL_FEATURES.md`
- **Implementation Summary:** `IMPLEMENTATION_SUMMARY.md`

---

**Last Updated:** December 2024
**Version:** 1.0

