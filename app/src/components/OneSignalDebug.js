import React, { useState, useEffect } from 'react';
import { Box, Button, Typography, Alert, Card, CardContent } from '@mui/material';
import { useAuth } from '../services/AuthService';
import OneSignalService from '../services/OneSignalService';
import AndroidOneSignalHelper from '../utils/android-onesignal';

export default function OneSignalDebug() {
  const { user } = useAuth();
  const [debugInfo, setDebugInfo] = useState({});
  const [loading, setLoading] = useState(false);

  const isAndroid = window.Capacitor && window.Capacitor.isNativePlatform();

  const runDiagnostics = async () => {
    setLoading(true);
    const info = {};

    try {
      // Platform info
      info.platform = isAndroid ? 'Android' : 'Web';
      info.capacitorAvailable = !!window.Capacitor;
      info.oneSignalPluginAvailable = !!window.plugins?.OneSignal;

      if (isAndroid && window.plugins?.OneSignal) {
        const OS = window.plugins.OneSignal;
        
        // Check available methods
        info.initializeAvailable = typeof OS.initialize === 'function';
        info.getPlayerIdAvailable = typeof OS.User?.pushSubscription?.getId === 'function';
        info.loginAvailable = typeof OS.login === 'function';
        info.permissionAvailable = typeof OS.promptForPushNotificationsWithUserResponse === 'function';

        // Try to get player ID
        try {
          const playerId = await OneSignalService.getPlayerId();
          info.playerId = playerId || 'Not available';
        } catch (error) {
          info.playerId = `Error: ${error.message}`;
        }

        // Check backend registration if user is logged in
        if (user && user.id) {
          try {
            const response = await fetch(
              `https://sandybrown-gull-863456.hostingersite.com/check_onesignal.php?user_id=${user.id}`,
              { method: 'GET' }
            );
            const result = await response.json();
            info.backendRegistered = result.success && result.data?.registered;
            info.backendPlayerId = result.data?.player_id || 'None';
          } catch (error) {
            info.backendError = error.message;
          }
        }
      }

      // Check localStorage
      info.pendingRegistration = localStorage.getItem('pendingOneSignalRegistration') || 'None';
      info.userData = localStorage.getItem('userData') ? 'Present' : 'Not found';

    } catch (error) {
      info.error = error.message;
    }

    setDebugInfo(info);
    setLoading(false);
  };

  const forceRegistration = async () => {
    if (!user || !user.id) return;
    
    setLoading(true);
    try {
      if (isAndroid) {
        await AndroidOneSignalHelper.registerUserWithRetry(user.id, 5);
      } else {
        await OneSignalService.ensureRegistration(user.id);
      }
      
      // Refresh diagnostics
      setTimeout(runDiagnostics, 2000);
    } catch (error) {
      console.error('Force registration failed:', error);
    }
    setLoading(false);
  };

  const clearPendingRegistration = () => {
    localStorage.removeItem('pendingOneSignalRegistration');
    runDiagnostics();
  };

  useEffect(() => {
    runDiagnostics();
  }, [user]);

  if (!user) return null;

  return (
    <Card sx={{ m: 2 }}>
      <CardContent>
        <Typography variant="h6" gutterBottom>
          OneSignal Debug Information
        </Typography>
        
        <Box sx={{ mb: 2 }}>
          <Button 
            variant="outlined" 
            onClick={runDiagnostics} 
            disabled={loading}
            sx={{ mr: 1 }}
          >
            Refresh Diagnostics
          </Button>
          
          {isAndroid && (
            <Button 
              variant="contained" 
              onClick={forceRegistration} 
              disabled={loading}
              sx={{ mr: 1 }}
            >
              Force Registration
            </Button>
          )}
          
          {debugInfo.pendingRegistration !== 'None' && (
            <Button 
              variant="outlined" 
              color="warning"
              onClick={clearPendingRegistration}
            >
              Clear Pending
            </Button>
          )}
        </Box>

        {Object.keys(debugInfo).length > 0 && (
          <Box>
            <Typography variant="subtitle2" gutterBottom>
              Diagnostic Results:
            </Typography>
            
            <Box component="pre" sx={{ 
              fontSize: '0.8rem', 
              backgroundColor: '#f5f5f5', 
              p: 1, 
              borderRadius: 1,
              overflow: 'auto'
            }}>
              {JSON.stringify(debugInfo, null, 2)}
            </Box>
            
            {debugInfo.playerId && debugInfo.playerId !== 'Not available' && debugInfo.backendRegistered && (
              <Alert severity="success" sx={{ mt: 1 }}>
                ✅ OneSignal is properly configured and registered
              </Alert>
            )}
            
            {debugInfo.playerId && debugInfo.playerId !== 'Not available' && !debugInfo.backendRegistered && (
              <Alert severity="warning" sx={{ mt: 1 }}>
                ⚠️ Player ID exists but not registered in backend
              </Alert>
            )}
            
            {(!debugInfo.playerId || debugInfo.playerId === 'Not available') && (
              <Alert severity="error" sx={{ mt: 1 }}>
                ❌ No Player ID available - check OneSignal configuration
              </Alert>
            )}
          </Box>
        )}
      </CardContent>
    </Card>
  );
}