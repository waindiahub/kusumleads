import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Box,
  Card,
  CardContent,
  Typography,
  TextField,
  Button,
  MenuItem,
  AppBar,
  Toolbar,
  IconButton,
  Grid,
  Chip,
  CircularProgress,
  Alert,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions
} from '@mui/material';
import { 
  ArrowBack, 
  Phone, 
  WhatsApp, 
  Save,
  LocationOn,
  Campaign,
  Schedule
} from '@mui/icons-material';
import { apiService } from '../services/ApiService';
import WhatsAppTemplateSelector from '../components/WhatsAppTemplateSelector';

export default function LeadDetailScreen() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [lead, setLead] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [responseStatus, setResponseStatus] = useState('');
  const [notes, setNotes] = useState('');
  const [price, setPrice] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showReminderDialog, setShowReminderDialog] = useState(false);
  const [reminderTime, setReminderTime] = useState('');
  const [reminderNote, setReminderNote] = useState('');
  const [templateSelectorOpen, setTemplateSelectorOpen] = useState(false);

  useEffect(() => {
    fetchLeadDetails();
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  const fetchLeadDetails = async () => {
    try {
      const response = await apiService.get(`/leads/${id}`);
      if (response.success) {
        setLead(response.data);
        setResponseStatus(response.data.response_status || '');
        setNotes(response.data.response_text || '');
        setPrice(response.data.price_offered || '');
      }
    } catch (error) {
      console.error('Error fetching lead details:', error);
      setError('Failed to load lead details');
    } finally {
      setLoading(false);
    }
  };

  const handleSubmitResponse = async (e) => {
    e.preventDefault();
    if (!responseStatus) {
      setError('Please select a response status');
      return;
    }

    setSaving(true);
    setError('');
    try {
      const response = await apiService.post(`/leads/${id}/response`, {
        response_status: responseStatus,
        response_text: notes,
        price_offered: price
      });
      
      if (response.success) {
        setSuccess('Response submitted successfully!');
        setTimeout(() => {
          navigate('/leads');
        }, 2000);
      } else {
        setError('Failed to submit response');
      }
    } catch (error) {
      setError('Error submitting response');
    } finally {
      setSaving(false);
    }
  };

  const handleSetReminder = async () => {
    if (!reminderTime) {
      setError('Please select reminder time');
      return;
    }

    try {
      const response = await apiService.post('/reminders', {
        lead_id: id,
        reminder_time: reminderTime,
        reminder_note: reminderNote
      });
      
      if (response.success) {
        setSuccess('Reminder set successfully!');
        setShowReminderDialog(false);
        setReminderTime('');
        setReminderNote('');
      } else {
        setError('Failed to set reminder');
      }
    } catch (error) {
      setError('Error setting reminder');
    }
  };

  const makePhoneCall = (phoneNumber) => {
    const cleanPhone = phoneNumber.replace(/[^0-9+]/g, '');
    window.open(`tel:${cleanPhone}`);
  };



  const getStatusColor = (status) => {
    switch (status) {
      case 'contacted': return 'info';
      case 'qualified': return 'success';
      case 'not_qualified': return 'error';
      default: return 'default';
    }
  };

  if (loading) {
    return (
      <Box sx={styles.loadingContainer}>
        <CircularProgress />
        <Typography sx={{ mt: 2 }}>Loading lead details...</Typography>
      </Box>
    );
  }

  if (!lead) {
    return (
      <Box sx={styles.errorContainer}>
        <Typography variant="h6" color="error">
          Lead not found
        </Typography>
        <Button onClick={() => navigate('/leads')} sx={{ mt: 2 }}>
          Back to Leads
        </Button>
      </Box>
    );
  }

  return (
    <Box sx={styles.container}>
      <AppBar position="static">
        <Toolbar>
          <IconButton 
            edge="start" 
            color="inherit" 
            onClick={() => navigate('/leads')}
          >
            <ArrowBack />
          </IconButton>
          <Typography variant="h6" sx={{ flexGrow: 1 }}>
            Lead Details
          </Typography>
        </Toolbar>
      </AppBar>

      <Box sx={styles.content}>
        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error}
          </Alert>
        )}
        
        {success && (
          <Alert severity="success" sx={{ mb: 2 }}>
            {success}
          </Alert>
        )}

        <Card sx={styles.leadInfoCard} elevation={2}>
          <CardContent>
            <Box sx={styles.leadHeader}>
              <Typography variant="h4" sx={styles.leadName}>
                {lead.full_name}
              </Typography>
              {lead.response_status && (
                <Chip
                  label={lead.response_status}
                  color={getStatusColor(lead.response_status)}
                />
              )}
            </Box>

            <Grid container spacing={2} sx={styles.infoGrid}>
              <Grid item xs={12} sm={6}>
                <Box sx={styles.infoItem}>
                  <Phone sx={styles.infoIcon} />
                  <Typography>{lead.phone_number}</Typography>
                </Box>
              </Grid>
              
              {lead.city && (
                <Grid item xs={12} sm={6}>
                  <Box sx={styles.infoItem}>
                    <LocationOn sx={styles.infoIcon} />
                    <Typography>{lead.city}</Typography>
                  </Box>
                </Grid>
              )}
              
              {lead.campaign_name && (
                <Grid item xs={12}>
                  <Box sx={styles.infoItem}>
                    <Campaign sx={styles.infoIcon} />
                    <Typography>{lead.campaign_name}</Typography>
                  </Box>
                </Grid>
              )}
              
              <Grid item xs={12}>
                <Typography variant="caption" color="textSecondary">
                  Created: {new Date(lead.created_time).toLocaleDateString()}
                </Typography>
              </Grid>
            </Grid>

            <Box sx={styles.actionButtons}>
              <Button
                variant="outlined"
                startIcon={<Phone />}
                onClick={() => makePhoneCall(lead.phone_number)}
                sx={styles.actionButton}
              >
                Call
              </Button>
              <Button
                variant="contained"
                startIcon={<WhatsApp />}
                onClick={() => setTemplateSelectorOpen(true)}
                sx={[styles.actionButton, styles.whatsappButton]}
              >
                Templates
              </Button>
              <Button
                variant="outlined"
                startIcon={<Schedule />}
                onClick={() => setShowReminderDialog(true)}
                sx={styles.actionButton}
              >
                Remind
              </Button>
            </Box>
          </CardContent>
        </Card>

        <Card sx={styles.responseCard} elevation={2}>
          <CardContent>
            <Typography variant="h6" gutterBottom>
              Agent Response
            </Typography>
            
            <Box component="form" onSubmit={handleSubmitResponse}>
              <TextField
                select
                label="Response Status"
                value={responseStatus}
                onChange={(e) => setResponseStatus(e.target.value)}
                fullWidth
                required
                sx={styles.formField}
              >
                <MenuItem value="">Select Status</MenuItem>
                <MenuItem value="contacted">Contacted</MenuItem>
                <MenuItem value="qualified">Qualified</MenuItem>
                <MenuItem value="not_qualified">Not Qualified</MenuItem>
                <MenuItem value="call_not_picked">Call Not Picked</MenuItem>
                <MenuItem value="payment_completed">Payment Completed</MenuItem>
              </TextField>

              <TextField
                label="Notes"
                multiline
                rows={4}
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                fullWidth
                placeholder="Add your notes here..."
                sx={styles.formField}
              />

              {(responseStatus === 'qualified' || responseStatus === 'payment_completed') && (
                <TextField
                  label={responseStatus === 'payment_completed' ? "Payment Amount" : "Price Offered"}
                  type="number"
                  value={price}
                  onChange={(e) => setPrice(e.target.value)}
                  fullWidth
                  placeholder={responseStatus === 'payment_completed' ? "Enter payment amount" : "Enter price"}
                  sx={styles.formField}
                />
              )}

              <Button
                type="submit"
                variant="contained"
                fullWidth
                disabled={saving}
                startIcon={saving ? <CircularProgress size={20} /> : <Save />}
                sx={styles.submitButton}
              >
                {saving ? 'Submitting...' : 'Submit Response'}
              </Button>
            </Box>
          </CardContent>
        </Card>
      </Box>

      <Dialog open={showReminderDialog} onClose={() => setShowReminderDialog(false)}>
        <DialogTitle>Set Follow-up Reminder</DialogTitle>
        <DialogContent>
          <TextField
            label="Reminder Time"
            type="datetime-local"
            value={reminderTime}
            onChange={(e) => setReminderTime(e.target.value)}
            fullWidth
            sx={{ mt: 1, mb: 2 }}
            InputLabelProps={{ shrink: true }}
          />
          <TextField
            label="Note (Optional)"
            multiline
            rows={2}
            value={reminderNote}
            onChange={(e) => setReminderNote(e.target.value)}
            fullWidth
            placeholder="Reminder note..."
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setShowReminderDialog(false)}>Cancel</Button>
          <Button onClick={handleSetReminder} variant="contained">Set Reminder</Button>
        </DialogActions>
      </Dialog>
      
      <WhatsAppTemplateSelector
        open={templateSelectorOpen}
        onClose={() => setTemplateSelectorOpen(false)}
        lead={lead}
        onSend={(template, message) => {
          console.log('Template sent:', template.name, message);
        }}
      />
    </Box>
  );
}

const styles = {
  container: {
    minHeight: '100vh',
    backgroundColor: '#f5f5f5',
    paddingBottom: 8, // Space for bottom nav
  },
  content: {
    padding: 2,
  },
  loadingContainer: {
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'center',
    alignItems: 'center',
    minHeight: '100vh',
  },
  errorContainer: {
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'center',
    alignItems: 'center',
    minHeight: '100vh',
  },
  leadInfoCard: {
    marginBottom: 2,
  },
  leadHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 2,
  },
  leadName: {
    fontSize: 24,
    fontWeight: 'bold',
    flex: 1,
  },
  infoGrid: {
    marginBottom: 2,
  },
  infoItem: {
    display: 'flex',
    alignItems: 'center',
    gap: 1,
  },
  infoIcon: {
    color: '#666',
    fontSize: 20,
  },
  actionButtons: {
    display: 'flex',
    gap: 1,
    marginTop: 2,
  },
  actionButton: {
    flex: 1,
  },
  whatsappButton: {
    backgroundColor: '#25D366',
    '&:hover': {
      backgroundColor: '#20B858',
    },
  },
  responseCard: {
    marginBottom: 2,
  },
  formField: {
    marginBottom: 2,
  },
  submitButton: {
    marginTop: 1,
    paddingY: 1.5,
    fontSize: 16,
    fontWeight: 'bold',
  },
};