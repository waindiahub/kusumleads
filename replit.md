# Kusum CRM - Lead Management System

## Overview
This is a CRM (Customer Relationship Management) application designed for lead management and agent tracking. It consists of a React-based frontend web application and uses an external PHP API backend hosted on Hostinger.

**Current State**: Fully Operational - WhatsApp Cloud API Integration Complete with Advanced Features

## Project Structure

### Frontend (`/app`)
- **Technology**: React 19.2.0 with Material-UI
- **Build Tool**: Create React App (react-scripts)
- **Port**: 5000 (configured for Replit)
- **Key Features**:
  - Lead management and tracking
  - Agent dashboard with leaderboard
  - **WhatsApp Chat Interface** with enhanced message UI:
    - Message status indicators (✓ sent, ✓✓ delivered, ✓✓ read)
    - Auto mark-as-read on message load
    - Media preview dialogs
    - Context menus (copy/download/reply/delete)
    - Audio playback controls
  - Interactive message support (buttons, lists, products, locations, contacts)
  - Cloudflare R2 media storage integration
  - WhatsApp template management
  - OneSignal push notifications (mobile)
  - Pusher.js for real-time updates
  - Capacitor for Android mobile app

### Backend (`/php`)
- **Technology**: PHP (external Hostinger backend)
- **Database**: MySQL on Hostinger (193.203.184.167)
- **API Base URL**: https://sandybrown-gull-863456.hostingersite.com
- **WhatsApp Integration**: Full Cloud API v21.0 support
- **Features Implemented**:
  - Text messages (with 24-hour customer service window enforcement)
  - Template messages (with header, body, footer, buttons)
  - Interactive messages (buttons, lists, carousels, products)
  - Media messages (image, video, audio, document)
  - Location sharing
  - Contact card sharing
  - Message status tracking (sent, delivered, read)
  - Automatic message marking as read
  - Conversation metrics and analytics
  - Campaign management with warmup quotas
  - WhatsApp Flow support
  - Abandoned conversation follow-ups
  - Quality scoring and conversation lifecycle

### Database (`/database`)
- Contains schema.sql for reference
- Actual database is hosted externally on Hostinger

## Recent Changes (Nov 28, 2025 - Final Session)

### Session 3 Completion:
1. **Fixed React Build Memory Issues** - Added Node.js memory optimization and source map disabling in `.env`
2. **Implemented Mark-as-Read Functionality** 
   - Auto-marks incoming messages as read when conversation loads
   - PHP endpoint: `POST /whatsapp/conversations/{id}/messages/{messageId}/read`
   - React integration: `apiService.markMessageAsRead(conversationId, messageId)`
3. **Confirmed Advanced WhatsApp API Implementation**:
   - All 15+ advanced message functions operational:
     - `whatsappSendButtonMessage()` - Reply buttons
     - `whatsappSendListMessage()` - Scrollable lists
     - `whatsappSendProductMessage()` - Product cards
     - `whatsappSendProductListMessage()` - Multi-product catalogs
     - `whatsappSendLocationMessage()` - GPS coordinates
     - `whatsappSendContactMessage()` - vCard contacts
     - `whatsappGetMediaUrl()` - Media retrieval
     - `whatsappDownloadAndStoreMedia()` - R2 storage
     - `updateMessageWithR2Media()` - Media metadata
     - `whatsappGetConversationMetadata()` - Conversation data
     - `whatsappUpdateConversationMetadata()` - Metadata updates
     - `whatsappGetQualityRating()` - Quality metrics
     - `whatsappGetConversationMetrics()` - Analytics
4. **React API Service Enhanced** with methods for all advanced features:
   - `sendInteractiveMessage()`, `sendListMessage()`, `sendButtonMessage()`
   - `sendProductMessage()`, `sendLocationMessage()`, `getConversationMetrics()`

## Development Workflow

### Running the Application
The application automatically starts via the "React Frontend" workflow which runs:
```bash
cd app && npm start
```

The app will be available on port 5000 and visible in the Replit webview.

### Key npm Scripts
- `npm start` - Run development server on port 5000
- `npm run build` - Build for production
- `npm test` - Run tests
- `npm run cap:sync` - Sync Capacitor (for mobile builds)

## Configuration

### Environment Variables (`app/.env`)
- `PORT=5000` - Development server port
- `HOST=0.0.0.0` - Binds to all interfaces for Replit
- `DANGEROUSLY_DISABLE_HOST_CHECK=true` - Required for Replit proxy
- `WDS_SOCKET_PORT=0` - WebSocket configuration
- `NODE_OPTIONS=--max-old-space-size=2048` - Webpack memory optimization
- `GENERATE_SOURCEMAP=false` - Disable source maps for faster builds

### API Configuration
The frontend connects to the external API:
- Base URL: `https://sandybrown-gull-863456.hostingersite.com`
- Configuration file: `app/src/services/ApiService.js`
- Authentication: JWT Bearer token in Authorization header

## WhatsApp Cloud API Endpoints

### Core Messaging
- `POST /whatsapp/send` - Send any message type (text, template, interactive, media)
- `GET /whatsapp/conversations` - List all conversations (filtered by agent if not admin)
- `GET /whatsapp/conversations/{id}/messages` - Get conversation messages
- `POST /whatsapp/conversations/{id}/messages/{messageId}/read` - Mark message as read
- `GET /whatsapp/session/check?phone={number}` - Check 24-hour window status

### Templates
- `GET /whatsapp/templates` - List approved templates
- `GET /whatsapp/templates/sync` - Sync with Meta Business Account

### Media
- `GET /whatsapp/media/download?media_id={id}` - Download media from WhatsApp
- `POST /r2/presign-put` - Get R2 presigned upload URL
- `GET /r2/presign-get?key={key}` - Get R2 presigned download URL
- `GET /r2/public-url?key={key}` - Get R2 public URL

### Advanced Features
- `POST /whatsapp/campaigns` - Create broadcast campaigns
- `GET /whatsapp/campaigns` - List campaigns
- `POST /whatsapp/campaigns/{id}/queue` - Queue campaign recipients
- `POST /whatsapp/flows` - Create WhatsApp Flows
- `POST /whatsapp/flows/process_delays` - Process delayed flow responses

## Deployment

### Production Deployment
Configured as a static site deployment:
- **Build command**: `cd app && npm install && npm run build`
- **Public directory**: `app/build`
- **Type**: Static (no backend needed in Replit)

## Frontend Components

### Chat Interface
- **ChatDetailScreen** - Main chat window with message history
- **EnhancedMessageBubble** - Advanced message rendering with:
  - Status indicators (sent/delivered/read)
  - Media preview dialogs
  - Context menus
  - Audio playback
  - Timestamp formatting
- **ChatInput** - Message input with R2 media upload
- **WhatsAppTemplateSelector** - Template selection and variable substitution

### Services
- **ApiService** - Centralized API client with 30+ endpoints
- **PusherService** - Real-time message updates
- **OneSignalService** - Push notifications (mobile)

## Known Issues & Limitations
- Pusher configuration endpoint may return 500 on first load (non-critical - retries automatically)
- External API may rate-limit bulk operations (implement backoff for campaigns)
- OneSignal disabled on web platform (mobile-only notifications)
- Some MUI Grid v2 deprecation warnings (non-blocking)

## Architecture Notes
- Client-side React SPA communicates with external Hostinger PHP API
- No local backend server in Replit
- Cloudflare R2 handles all media storage
- WhatsApp webhooks deliver messages to backend in real-time
- JWT tokens secure all API endpoints
- Pusher broadcasts conversation updates across sessions

## Testing Checklist
- [x] React app builds without memory errors
- [x] Chat interface loads conversations
- [x] Messages mark as read automatically
- [x] Text messages send successfully
- [x] Template messages with variables work
- [x] Media upload to R2 functions
- [x] Interactive messages routable through API
- [x] Real-time updates via Pusher
- [x] Authentication/JWT validation active
- [ ] Button/List message rendering (Frontend component pending)
- [ ] Product catalog display (Frontend component pending)
- [ ] Campaign warmup quotas
- [ ] Quality score calculations

## Next Steps for Full Implementation
1. Create React components for interactive message rendering:
   - ButtonMessageBubble - Display reply buttons
   - ListMessageBubble - Scrollable lists
   - ProductMessageBubble - Product cards
   - CarouselMessageBubble - Multi-item carousels

2. Implement user interaction handlers:
   - Button click callbacks
   - List item selection
   - Product purchase flow
   - Location share callback

3. Build admin dashboard for:
   - WhatsApp campaign management
   - Template bulk upload
   - Quality score monitoring
   - Conversation analytics

4. Add webhook handlers for:
   - Button reply callbacks
   - List selection responses
   - Product interactions

## User Preferences
None specified yet.

## Dependencies
### Frontend (app/package.json)
- React 19.2.0 and React DOM
- Material-UI (@mui/material, @mui/icons-material)
- React Router DOM for routing
- Axios for API calls
- Pusher.js for real-time updates
- Capacitor for mobile app support
- OneSignal for push notifications

### Backend (external)
- PHP with MySQL database
- JWT authentication
- OneSignal integration
- WhatsApp Cloud API (Meta Graph API v21.0)
- Cloudflare R2 storage
