import React, { useState, useEffect } from 'react';
import { Box, Button, Typography, Alert, CircularProgress } from '@mui/material';
import { useAuth } from '../services/AuthService';
import OneSignalService from '../services/OneSignalService';

export default function OneSignalStatus() {
  const { user } = useAuth();
  const [playerId, setPlayerId] = useState(null);
  const [loading, setLoading] = useState(false);
  const [registrationStatus, setRegistrationStatus] = useState('unknown');

  useEffect(() => {
    checkPlayerIdStatus();
  }, []);

  const checkPlayerIdStatus = async () => {
    if (!user) return;
    
    try {
      const id = await OneSignalService.getPlayerId();
      setPlayerId(id);
      
      if (id) {
        // Check if it's registered in backend
        const response = await fetch(
          `https://sandybrown-gull-863456.hostingersite.com/check_onesignal.php?user_id=${user.id}`,
          { method: 'GET' }
        );
        const result = await response.json();
        setRegistrationStatus(result.registered ? 'registered' : 'not_registered');
      } else {
        setRegistrationStatus('no_player_id');
      }
    } catch (error) {
      console.error('Error checking OneSignal status:', error);
      setRegistrationStatus('error');
    }
  };

  const handleManualRegistration = async () => {
    if (!user) return;
    
    setLoading(true);
    try {
      await OneSignalService.ensureRegistration(user.id);
      
      // Wait a bit and check status again
      setTimeout(() => {
        checkPlayerIdStatus();
        setLoading(false);
      }, 3000);
    } catch (error) {
      console.error('Manual registration failed:', error);
      setLoading(false);
    }
  };

  const getStatusColor = () => {
    switch (registrationStatus) {
      case 'registered': return 'success';
      case 'not_registered': return 'warning';
      case 'no_player_id': return 'error';
      default: return 'info';
    }
  };

  const getStatusMessage = () => {
    switch (registrationStatus) {
      case 'registered': return 'OneSignal is properly configured and registered';
      case 'not_registered': return 'OneSignal Player ID found but not registered in backend';
      case 'no_player_id': return 'OneSignal Player ID not available';
      case 'error': return 'Error checking OneSignal status';
      default: return 'Checking OneSignal status...';
    }
  };

  if (!user) return null;

  return (
    <Box sx={{ p: 2, border: '1px solid #ddd', borderRadius: 2, mb: 2 }}>
      <Typography variant="h6" gutterBottom>
        OneSignal Notification Status
      </Typography>
      
      <Alert severity={getStatusColor()} sx={{ mb: 2 }}>
        {getStatusMessage()}
      </Alert>
      
      {playerId && (
        <Typography variant="body2" sx={{ mb: 1, fontFamily: 'monospace' }}>
          Player ID: {playerId}
        </Typography>
      )}
      
      <Box sx={{ display: 'flex', gap: 1 }}>
        <Button 
          variant="outlined" 
          size="small" 
          onClick={checkPlayerIdStatus}
          disabled={loading}
        >
          Check Status
        </Button>
        
        {registrationStatus !== 'registered' && (
          <Button 
            variant="contained" 
            size="small" 
            onClick={handleManualRegistration}
            disabled={loading}
            startIcon={loading && <CircularProgress size={16} />}
          >
            {loading ? 'Registering...' : 'Register Now'}
          </Button>
        )}
      </Box>
    </Box>
  );
}