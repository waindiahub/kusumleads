import React, { useState, useEffect } from "react";
import { BrowserRouter as Router, Routes, Route, Navigate, useNavigate } from "react-router-dom";
import { Box } from "@mui/material";
import LoginScreen from "./screens/LoginScreen";
import DashboardScreen from "./screens/DashboardScreen";
import LeadListScreen from "./screens/LeadListScreen";
import LeadDetailScreen from "./screens/LeadDetailScreen";
import ProfileScreen from "./screens/ProfileScreen";
import LeaderboardScreen from "./screens/LeaderboardScreen";
import ChatListScreen from "./screens/ChatListScreen";
import ChatDetailScreen from "./screens/ChatDetailScreen";
import CallScreen from "./screens/CallScreen";
import BottomNav from "./components/BottomNavigation";
import SplashScreen from "./components/SplashScreen";
import { pusherService } from "./services/PusherService";
import { AuthProvider, useAuth } from "./services/AuthService";
import { initOneSignal } from "./utils/onesignal-init";
import OneSignalService from "./services/OneSignalService";
import AndroidOneSignalHelper from "./utils/android-onesignal";
import "./App.css";

function AppRoutes() {
  const { user, loading } = useAuth();
  const navigate = useNavigate();

  /**
   * REGISTER DEVICE AFTER LOGIN (ANDROID OPTIMIZED)
   */
  useEffect(() => {
    async function registerDevice() {
      if (!user || !user.id) return;

      console.log("🔵 User logged in:", user.id);

      const isAndroid = window.Capacitor && window.Capacitor.isNativePlatform();
      
      if (isAndroid) {
        // Use Android-specific registration
        try {
          await AndroidOneSignalHelper.registerUserWithRetry(user.id);
          
          // Additional check after 10 seconds
          setTimeout(() => {
            AndroidOneSignalHelper.checkAndFixRegistration(user.id);
          }, 10000);
        } catch (error) {
          console.error('❌ Android registration error:', error);
        }
      } else {
        // Web fallback (disabled but kept for compatibility)
        console.log('Web OneSignal disabled');
      }
    }

    registerDevice();
    (async () => {
      const ok = await pusherService.init(user)
      if (ok) {
        pusherService.onMessage((data) => {
          console.log('Realtime WhatsApp event', data)
          // Handle call updates - navigate to call screen for incoming calls
          if (data.type === 'call_update' && data.direction === 'USER_INITIATED' && data.status === 'RINGING') {
            const callId = data.call_id || data.id
            if (callId) {
              navigate(`/calls/${callId}`)
            }
          }
        })
        
        // Also listen for call updates separately
        pusherService.onCallUpdate((data) => {
          console.log('Call update received:', data)
          if (data.direction === 'USER_INITIATED' && data.status === 'RINGING') {
            const callId = data.call_id || data.id
            if (callId) {
              navigate(`/calls/${callId}`)
            }
          }
        })
      }
    })()
  }, [user]);

  if (loading) {
    return (
      <div style={{
        display: "flex",
        justifyContent: "center",
        alignItems: "center",
        height: "100vh"
      }}>
        Loading...
      </div>
    );
  }

  return (
    <Box>
      <Routes>
        {user ? (
          <>
            <Route path="/dashboard" element={<DashboardScreen />} />
            <Route path="/leads" element={<LeadListScreen />} />
            <Route path="/leads/:id" element={<LeadDetailScreen />} />
            <Route path="/chats" element={<ChatListScreen />} />
            <Route path="/chats/:id" element={<ChatDetailScreen />} />
            <Route path="/calls/:callId" element={<CallScreen />} />
            <Route path="/leaderboard" element={<LeaderboardScreen />} />
            <Route path="/profile" element={<ProfileScreen />} />
            <Route path="/" element={<Navigate to="/dashboard" replace />} />
            <Route path="*" element={<Navigate to="/dashboard" replace />} />
          </>
        ) : (
          <>
            <Route path="/login" element={<LoginScreen />} />
            <Route path="/" element={<Navigate to="/login" replace />} />
            <Route path="*" element={<Navigate to="/login" replace />} />
          </>
        )}
      </Routes>

      {user && <BottomNav />}
    </Box>
  );
}

function App() {
  const [showSplash, setShowSplash] = useState(true);

  useEffect(() => {
    const initializeOneSignal = async () => {
      const isAndroid = window.Capacitor && window.Capacitor.isNativePlatform();
      
      if (isAndroid) {
        console.log('🔵 Initializing OneSignal for Android...');
        
        try {
          // Use Android-specific initialization
          const success = await AndroidOneSignalHelper.initializeForAndroid();
          
          if (success) {
            console.log('🟢 Android OneSignal initialization complete');
            
            // Check for existing user and register if needed
            const userData = localStorage.getItem('userData');
            if (userData) {
              try {
                const user = JSON.parse(userData);
                if (user && user.id) {
                  setTimeout(() => {
                    AndroidOneSignalHelper.checkAndFixRegistration(user.id);
                  }, 2000);
                }
              } catch (e) {
                console.error('Error parsing stored user data:', e);
              }
            }
          } else {
            console.error('❌ Android OneSignal initialization failed');
          }
        } catch (error) {
          console.error('❌ OneSignal initialization error:', error);
        }
      } else {
        console.log('Web platform - OneSignal disabled');
      }
    };
    
    // Start initialization after a short delay
    setTimeout(initializeOneSignal, 1000);
  }, []);

  if (showSplash) {
    return <SplashScreen onComplete={() => setShowSplash(false)} />;
  }

  return (
    <AuthProvider>
      <Router>
        <div className="App">
          <AppRoutes />
        </div>
      </Router>
    </AuthProvider>
  );
}

export default App;
