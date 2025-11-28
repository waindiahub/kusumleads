import React, { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Avatar,
  Box,
  Button,
  CircularProgress,
  IconButton,
  Paper,
  Typography,
  Chip
} from '@mui/material';
import {
  Call,
  CallEnd,
  PhoneDisabled,
  VolumeUp,
  VolumeOff,
  Mic,
  MicOff
} from '@mui/icons-material';
import { apiService } from '../services/ApiService';
import { pusherService } from '../services/PusherService';

export default function CallScreen() {
  const { callId } = useParams();
  const navigate = useNavigate();
  const [call, setCall] = useState(null);
  const [loading, setLoading] = useState(true);
  const [callStatus, setCallStatus] = useState('RINGING');
  const [isMuted, setIsMuted] = useState(false);
  const [isSpeakerOn, setIsSpeakerOn] = useState(false);
  const [duration, setDuration] = useState(0);
  const [sdpOffer, setSdpOffer] = useState(null);
  const [sdpAnswer, setSdpAnswer] = useState(null);
  const callIntervalRef = useRef(null);
  const startTimeRef = useRef(null);

  useEffect(() => {
    if (callId) {
      loadCall();
      setupPusherListener();
    }
    
    return () => {
      if (callIntervalRef.current) {
        clearInterval(callIntervalRef.current);
      }
    };
  }, [callId]);

  const loadCall = async () => {
    try {
      const res = await apiService.getCall(callId);
      if (res.success && res.data) {
        setCall(res.data);
        setCallStatus(res.data.status);
        setSdpOffer(res.data.sdp_offer);
        setSdpAnswer(res.data.sdp_answer);
        
        if (res.data.status === 'ACCEPTED' && res.data.start_time) {
          startTimeRef.current = res.data.start_time;
          startDurationTimer();
        }
      }
    } catch (error) {
      console.error('Failed to load call:', error);
    } finally {
      setLoading(false);
    }
  };

  const setupPusherListener = () => {
    pusherService.onCall((data) => {
      if (data.call_id === callId) {
        setCallStatus(data.status);
        if (data.status === 'ACCEPTED' && data.start_time) {
          startTimeRef.current = data.start_time;
          startDurationTimer();
        } else if (data.status === 'TERMINATED' || data.status === 'REJECTED') {
          handleCallEnd();
        }
      }
    });
  };

  const startDurationTimer = () => {
    if (callIntervalRef.current) {
      clearInterval(callIntervalRef.current);
    }
    
    callIntervalRef.current = setInterval(() => {
      if (startTimeRef.current) {
        const elapsed = Math.floor((Date.now() / 1000) - startTimeRef.current);
        setDuration(elapsed);
      }
    }, 1000);
  };

  const handleAccept = async () => {
    try {
      // Generate SDP answer (this would typically come from WebRTC stack)
      // For now, we'll use a placeholder - in production, integrate with WebRTC
      const sdpAnswer = generateSDPAnswer(sdpOffer);
      
      const res = await apiService.acceptCall(callId, sdpAnswer);
      
      if (res.success) {
        setCallStatus('ACCEPTED');
        startTimeRef.current = Math.floor(Date.now() / 1000);
        startDurationTimer();
        setSdpAnswer(sdpAnswer);
      }
    } catch (error) {
      console.error('Failed to accept call:', error);
    }
  };

  const handlePreAccept = async () => {
    try {
      const sdpAnswer = generateSDPAnswer(sdpOffer);
      
      const res = await apiService.preAcceptCall(callId, sdpAnswer);
      
      if (res.success) {
        // After pre-accept, still need to accept
        setTimeout(() => {
          handleAccept();
        }, 1000);
      }
    } catch (error) {
      console.error('Failed to pre-accept call:', error);
    }
  };

  const handleReject = async () => {
    try {
      const res = await apiService.rejectCall(callId);
      if (res.success) {
        handleCallEnd();
      }
    } catch (error) {
      console.error('Failed to reject call:', error);
    }
  };

  const handleTerminate = async () => {
    try {
      const res = await apiService.terminateCall(callId);
      if (res.success) {
        handleCallEnd();
      }
    } catch (error) {
      console.error('Failed to terminate call:', error);
    }
  };

  const handleCallEnd = () => {
    if (callIntervalRef.current) {
      clearInterval(callIntervalRef.current);
    }
    setCallStatus('TERMINATED');
    setTimeout(() => {
      navigate('/chats');
    }, 2000);
  };

  const formatDuration = (seconds) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  };

  // Placeholder SDP answer generator - in production, use WebRTC library
  const generateSDPAnswer = (offer) => {
    // This is a placeholder - in production, use WebRTC to generate proper SDP answer
    return offer ? offer.replace('offer', 'answer') : 'v=0\r\no=- 0 0 IN IP4 127.0.0.1\r\ns=-\r\nt=0 0\r\n';
  };

  if (loading) {
    return (
      <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: '100vh' }}>
        <CircularProgress />
      </Box>
    );
  }

  if (!call) {
    return (
      <Box sx={{ p: 3, textAlign: 'center' }}>
        <Typography>Call not found</Typography>
        <Button onClick={() => navigate('/chats')} sx={{ mt: 2 }}>Go Back</Button>
      </Box>
    );
  }

  const contactName = call.contact_name || call.phone_number || 'Unknown';
  const isIncoming = call.direction === 'USER_INITIATED';
  const isActive = callStatus === 'ACCEPTED';
  const isRinging = callStatus === 'RINGING';

  return (
    <Box
      sx={{
        minHeight: '100vh',
        bgcolor: '#1a1a1a',
        color: 'white',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        p: 3
      }}
    >
      {/* Contact Info */}
      <Box sx={{ textAlign: 'center', mb: 4 }}>
        <Avatar
          sx={{
            width: 120,
            height: 120,
            mx: 'auto',
            mb: 2,
            bgcolor: '#25D366',
            fontSize: 48
          }}
        >
          {contactName.charAt(0).toUpperCase()}
        </Avatar>
        <Typography variant="h4" sx={{ mb: 1, fontWeight: 600 }}>
          {contactName}
        </Typography>
        <Typography variant="body1" color="text.secondary" sx={{ mb: 2 }}>
          {call.phone_number}
        </Typography>
        
        {isActive && (
          <Typography variant="h6" sx={{ color: '#25D366' }}>
            {formatDuration(duration)}
          </Typography>
        )}
        
        {isRinging && (
          <Chip
            label={isIncoming ? 'Incoming Call' : 'Calling...'}
            sx={{ bgcolor: '#25D366', color: 'white', fontWeight: 600 }}
          />
        )}
        
        {callStatus === 'ACCEPTED' && (
          <Chip
            label="Connected"
            sx={{ bgcolor: '#25D366', color: 'white', fontWeight: 600 }}
          />
        )}
      </Box>

      {/* Call Controls */}
      <Box sx={{ display: 'flex', gap: 2, flexWrap: 'wrap', justifyContent: 'center', mt: 4 }}>
        {isRinging && isIncoming && (
          <>
            <IconButton
              onClick={handlePreAccept}
              sx={{
                bgcolor: '#25D366',
                color: 'white',
                width: 64,
                height: 64,
                '&:hover': { bgcolor: '#20BA5A' }
              }}
            >
              <Call />
            </IconButton>
            <IconButton
              onClick={handleReject}
              sx={{
                bgcolor: '#f44336',
                color: 'white',
                width: 64,
                height: 64,
                '&:hover': { bgcolor: '#d32f2f' }
              }}
            >
              <CallEnd />
            </IconButton>
          </>
        )}
        
        {isActive && (
          <>
            <IconButton
              onClick={() => setIsMuted(!isMuted)}
              sx={{
                bgcolor: isMuted ? '#f44336' : 'rgba(255,255,255,0.1)',
                color: 'white',
                width: 56,
                height: 56,
                '&:hover': { bgcolor: isMuted ? '#d32f2f' : 'rgba(255,255,255,0.2)' }
              }}
            >
              {isMuted ? <MicOff /> : <Mic />}
            </IconButton>
            
            <IconButton
              onClick={() => setIsSpeakerOn(!isSpeakerOn)}
              sx={{
                bgcolor: isSpeakerOn ? '#25D366' : 'rgba(255,255,255,0.1)',
                color: 'white',
                width: 56,
                height: 56,
                '&:hover': { bgcolor: isSpeakerOn ? '#20BA5A' : 'rgba(255,255,255,0.2)' }
              }}
            >
              {isSpeakerOn ? <VolumeUp /> : <VolumeOff />}
            </IconButton>
            
            <IconButton
              onClick={handleTerminate}
              sx={{
                bgcolor: '#f44336',
                color: 'white',
                width: 64,
                height: 64,
                '&:hover': { bgcolor: '#d32f2f' }
              }}
            >
              <CallEnd />
            </IconButton>
          </>
        )}
        
        {(callStatus === 'TERMINATED' || callStatus === 'REJECTED') && (
          <Paper sx={{ p: 3, textAlign: 'center', bgcolor: 'rgba(255,255,255,0.1)' }}>
            <Typography variant="h6" sx={{ mb: 2 }}>
              Call {callStatus === 'TERMINATED' ? 'Ended' : 'Rejected'}
            </Typography>
            <Button
              variant="contained"
              onClick={() => navigate('/chats')}
              sx={{ bgcolor: '#25D366', '&:hover': { bgcolor: '#20BA5A' } }}
            >
              Go Back
            </Button>
          </Paper>
        )}
      </Box>

      {/* Call Info */}
      <Box sx={{ mt: 4, textAlign: 'center' }}>
        <Typography variant="caption" color="text.secondary">
          Direction: {call.direction === 'USER_INITIATED' ? 'Incoming' : 'Outgoing'}
        </Typography>
      </Box>
    </Box>
  );
}

