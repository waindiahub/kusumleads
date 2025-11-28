# Kusum CRM - Lead Management System

## Overview
This is a CRM (Customer Relationship Management) application designed for lead management and agent tracking. It consists of a React-based frontend web application and uses an external PHP API backend hosted on Hostinger.

**Current State**: Successfully configured to run in Replit environment

## Project Structure

### Frontend (`/app`)
- **Technology**: React 19.2.0 with Material-UI
- **Build Tool**: Create React App (react-scripts)
- **Port**: 5000 (configured for Replit)
- **Key Features**:
  - Lead management and tracking
  - Agent dashboard with leaderboard
  - WhatsApp integration for messaging
  - OneSignal push notifications (mobile)
  - Pusher.js for real-time updates
  - Capacitor for Android mobile app

### Backend (`/php`)
- **Technology**: PHP (external Hostinger backend)
- **Database**: MySQL on Hostinger (193.203.184.167)
- **API Base URL**: https://sandybrown-gull-863456.hostingersite.com
- **Note**: Backend is NOT running in Replit - it's an external service

### Database (`/database`)
- Contains schema.sql for reference
- Actual database is hosted externally on Hostinger

## Recent Changes (Nov 28, 2025)
1. Configured React app to run on port 5000 with host 0.0.0.0
2. Added `.env` file with Replit-compatible settings (DANGEROUSLY_DISABLE_HOST_CHECK=true)
3. Set up workflow "React Frontend" to run the development server
4. Configured static deployment with build directory `app/build`
5. Installed Node.js and PHP dependencies

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

### Environment Variables
Located in `app/.env`:
- `PORT=5000` - Development server port
- `HOST=0.0.0.0` - Binds to all interfaces for Replit
- `DANGEROUSLY_DISABLE_HOST_CHECK=true` - Required for Replit proxy
- `WDS_SOCKET_PORT=0` - WebSocket configuration

### API Configuration
The frontend connects to the external API:
- Base URL: `https://sandybrown-gull-863456.hostingersite.com`
- Configuration file: `app/src/services/ApiService.js`

## Deployment

### Production Deployment
Configured as a static site deployment:
- **Build command**: `cd app && npm install && npm run build`
- **Public directory**: `app/build`
- **Type**: Static (no backend needed in Replit)

## Known Issues
- Some ESLint warnings in the codebase (unused variables, hook dependencies)
- External API may return 500 errors for certain endpoints (pusher_config) - this is a backend issue, not a Replit setup issue
- The application requires valid credentials to access the external API

## Architecture Notes
- This is a client-side React SPA that communicates with an external API
- No local backend server needed in Replit
- Mobile features (OneSignal, Capacitor) are for Android builds only
- Web version has OneSignal disabled

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
- WhatsApp Cloud API integration
