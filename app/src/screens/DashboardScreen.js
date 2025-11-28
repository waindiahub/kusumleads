import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Box,
  Card,
  CardContent,
  Typography,
  Grid,
  Paper,
  Chip,
  Button,
  AppBar,
  Toolbar,
  IconButton,
  CircularProgress,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TextField
} from '@mui/material';
import { Refresh, ExitToApp, Assessment, Phone, WhatsApp, Update, Speed, Schedule, CheckCircle, EmojiEvents } from '@mui/icons-material';
import { apiService } from '../services/ApiService';
import { useAuth } from '../services/AuthService';
import WhatsAppTemplateSelector from '../components/WhatsAppTemplateSelector';


export default function DashboardScreen() {
  const [stats, setStats] = useState(null);
  const [recentLeads, setRecentLeads] = useState([]);
  const [weeklyData, setWeeklyData] = useState([]);
  const [topAgents, setTopAgents] = useState([]);
  const [reminders, setReminders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [templateSelectorOpen, setTemplateSelectorOpen] = useState(false);
  const [selectedLead, setSelectedLead] = useState(null);
  const [editReminderOpen, setEditReminderOpen] = useState(false);
  const [selectedReminder, setSelectedReminder] = useState(null);
  const [editReminderTime, setEditReminderTime] = useState('');
  const [editReminderNote, setEditReminderNote] = useState('');
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  useEffect(() => {
    loadDashboardData();
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const loadDashboardData = async () => {
    try {
      const [statsResponse, leadsResponse, weeklyResponse, leaderboardResponse, remindersResponse] = await Promise.all([
        apiService.getDashboardStats(),
        apiService.getLeads({ agent_id: user.id, limit: 5 }),
        apiService.get('/reports/weekly', { agent_id: user.id }),
        apiService.get('/leaderboard', { period: 'week' }),
        apiService.get('/reminders')
      ]);
      
      if (statsResponse.success) {
        setStats(statsResponse.data);
      }
      if (leadsResponse.success) {
        setRecentLeads(leadsResponse.data);
      }
      if (weeklyResponse.success) {
        setWeeklyData(weeklyResponse.data);
      }
      if (leaderboardResponse.success) {
        setTopAgents(leaderboardResponse.data.slice(0, 3));
      }
      if (remindersResponse.success) {
        setReminders(remindersResponse.data);
      }
    } catch (error) {
      console.error('Error loading dashboard:', error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const onRefresh = () => {
    setRefreshing(true);
    loadDashboardData();
  };

  const handleEditReminder = (reminder) => {
    setSelectedReminder(reminder);
    setEditReminderTime(reminder.reminder_time.slice(0, 16));
    setEditReminderNote(reminder.reminder_note || '');
    setEditReminderOpen(true);
  };

  const handleUpdateReminder = async () => {
    try {
      const response = await apiService.post(`/reminders/${selectedReminder.id}/update`, {
        reminder_time: editReminderTime,
        reminder_note: editReminderNote
      });
      if (response.success) {
        setReminders(reminders.map(r => 
          r.id === selectedReminder.id 
            ? { ...r, reminder_time: editReminderTime, reminder_note: editReminderNote }
            : r
        ));
        setEditReminderOpen(false);
      }
    } catch (error) {
      console.error('Error updating reminder:', error);
    }
  };

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'assigned': return 'warning';
      case 'contacted': return 'info';
      case 'qualified': return 'success';
      case 'not_qualified': return 'error';
      case 'call_not_picked': return 'secondary';
      case 'payment_completed': return 'primary';
      default: return 'default';
    }
  };

  const getStatusText = (status) => {
    switch (status) {
      case 'assigned': return 'New';
      case 'contacted': return 'Contacted';
      case 'qualified': return 'Qualified';
      case 'not_qualified': return 'Not Qualified';
      case 'call_not_picked': return 'No Answer';
      case 'payment_completed': return 'Payment Done';
      default: return status;
    }
  };

  const getTimeAgo = (dateString) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInHours = Math.floor((now - date) / (1000 * 60 * 60));
    
    if (diffInHours < 1) return 'Just now';
    if (diffInHours < 24) return `${diffInHours}h ago`;
    const diffInDays = Math.floor(diffInHours / 24);
    if (diffInDays < 7) return `${diffInDays}d ago`;
    return date.toLocaleDateString();
  };

  const WeeklyChart = ({ data }) => {
    if (!data || data.length === 0) {
      return (
        <Box sx={styles.chartContainer}>
          <Typography color="textSecondary">No data available</Typography>
        </Box>
      );
    }

    const maxValue = Math.max(...data.map(d => Math.max(d.contacted, d.qualified, d.payment_completed || 0)));
    
    return (
      <Box sx={styles.chartContainer}>
        <Box sx={styles.chartDays}>
          {data.map((day, index) => (
            <Box key={`day-${index}`} sx={styles.chartDay}>
              <Box sx={styles.chartBars}>
                <Box 
                  key={`contacted-${index}`}
                  sx={[styles.chartBar, styles.contactedBar, { height: `${(day.contacted / maxValue) * 60}px` }]}
                  title={`Contacted: ${day.contacted}`}
                />
                <Box 
                  key={`qualified-${index}`}
                  sx={[styles.chartBar, styles.qualifiedBar, { height: `${(day.qualified / maxValue) * 60}px` }]}
                  title={`Qualified: ${day.qualified}`}
                />
                <Box 
                  key={`payment-${index}`}
                  sx={[styles.chartBar, styles.paymentBar, { height: `${((day.payment_completed || 0) / maxValue) * 60}px` }]}
                  title={`Payments: ${day.payment_completed || 0}`}
                />
              </Box>
              <Typography variant="caption" sx={styles.chartLabel}>
                {day.day_name}
              </Typography>
            </Box>
          ))}
        </Box>
        <Box sx={styles.chartLegend}>
          <Box sx={styles.legendItem}>
            <Box sx={[styles.legendColor, styles.contactedBar]} />
            <Typography variant="caption">Contacted</Typography>
          </Box>
          <Box sx={styles.legendItem}>
            <Box sx={[styles.legendColor, styles.qualifiedBar]} />
            <Typography variant="caption">Qualified</Typography>
          </Box>
          <Box sx={styles.legendItem}>
            <Box sx={[styles.legendColor, styles.paymentBar]} />
            <Typography variant="caption">Payments</Typography>
          </Box>
        </Box>
      </Box>
    );
  };

  const QuickActionsWidget = () => {
    const pendingLeads = recentLeads.filter(lead => lead.assignment_status === 'assigned').slice(0, 3);
    
    const callNextLead = () => {
      if (pendingLeads.length > 0) {
        const lead = pendingLeads[0];
        const cleanPhone = lead.phone_number.replace(/[^0-9+]/g, '');
        window.open(`tel:${cleanPhone}`);
      }
    };

    const whatsappTemplate = (lead, template) => {
      const cleanPhone = lead.phone_number.replace(/[^0-9]/g, '');
      const messages = {
        intro: `Hi ${lead.full_name}, I'm calling regarding your inquiry. When would be a good time to discuss?`,
        followup: `Hi ${lead.full_name}, following up on our previous conversation. Are you still interested?`,
        pricing: `Hi ${lead.full_name}, I have some great pricing options for you. Can we schedule a quick call?`
      };
      const message = encodeURIComponent(messages[template]);
      window.open(`https://wa.me/${cleanPhone}?text=${message}`);
    };

    const quickStatusUpdate = async (leadId, status) => {
      try {
        await apiService.post(`/leads/${leadId}/response`, {
          response_status: status,
          response_text: `Quick update via dashboard`
        });
        loadDashboardData(); // Refresh data
      } catch (error) {
        console.error('Quick update failed:', error);
      }
    };

    return (
      <Box>
        {pendingLeads.length > 0 ? (
          <>
            <Button
              variant="contained"
              fullWidth
              startIcon={<Phone />}
              onClick={callNextLead}
              sx={[styles.quickButton, styles.callButton]}
            >
              Call Next Lead
            </Button>
            
            <Typography variant="subtitle2" sx={{ mt: 2, mb: 1 }}>
              Pending Leads ({pendingLeads.length})
            </Typography>
            
            {pendingLeads.map((lead) => (
              <Box key={lead.id} sx={styles.quickLead}>
                <Box sx={styles.quickLeadInfo}>
                  <Typography variant="body2" sx={styles.quickLeadName}>
                    {lead.full_name}
                  </Typography>
                  <Typography variant="caption" color="textSecondary">
                    {lead.phone_number}
                  </Typography>
                </Box>
                <Box sx={styles.quickActions}>
                  <IconButton 
                    size="small" 
                    onClick={() => {
                      const cleanPhone = lead.phone_number.replace(/[^0-9+]/g, '');
                      window.open(`tel:${cleanPhone}`);
                    }}
                    sx={styles.quickActionBtn}
                  >
                    <Phone fontSize="small" />
                  </IconButton>
                  <IconButton 
                    size="small" 
                    onClick={() => whatsappTemplate(lead, 'intro')}
                    sx={[styles.quickActionBtn, styles.whatsappBtn]}
                  >
                    <WhatsApp fontSize="small" />
                  </IconButton>
                  <IconButton 
                    size="small" 
                    onClick={() => quickStatusUpdate(lead.id, 'contacted')}
                    sx={styles.quickActionBtn}
                  >
                    <Update fontSize="small" />
                  </IconButton>
                </Box>
              </Box>
            ))}
            
            <Typography variant="caption" sx={{ mt: 2, display: 'block' }}>
              WhatsApp Templates:
            </Typography>
            <Box sx={styles.templateButtons}>
              <Button 
                size="small" 
                variant="outlined"
                sx={{ fontSize: 10, padding: '2px 6px', minWidth: 'auto' }}
                onClick={() => pendingLeads[0] && whatsappTemplate(pendingLeads[0], 'intro')}
              >
                Intro
              </Button>
              <Button 
                size="small" 
                variant="outlined"
                sx={{ fontSize: 10, padding: '2px 6px', minWidth: 'auto' }}
                onClick={() => pendingLeads[0] && whatsappTemplate(pendingLeads[0], 'followup')}
              >
                Follow-up
              </Button>
              <Button 
                size="small" 
                variant="outlined"
                sx={{ fontSize: 10, padding: '2px 6px', minWidth: 'auto' }}
                onClick={() => pendingLeads[0] && whatsappTemplate(pendingLeads[0], 'pricing')}
              >
                Pricing
              </Button>
            </Box>
          </>
        ) : (
          <Box sx={styles.noLeadsMessage}>
            <Typography color="textSecondary" align="center">
              No pending leads
            </Typography>
            <Button 
              variant="outlined" 
              onClick={() => navigate('/leads')}
              sx={{ mt: 1 }}
              fullWidth
            >
              View All Leads
            </Button>
          </Box>
        )}
      </Box>
    );
  };

  const FollowupReminders = () => {
    const formatReminderTime = (dateString) => {
      const date = new Date(dateString);
      const now = new Date();
      const diffInMinutes = Math.floor((date - now) / (1000 * 60));
      
      if (diffInMinutes < 0) {
        return 'Overdue';
      }
      if (diffInMinutes < 60) {
        return `In ${diffInMinutes} minutes`;
      }
      if (diffInMinutes < 1440) {
        const hours = Math.floor(diffInMinutes / 60);
        return `In ${hours} hour${hours > 1 ? 's' : ''}`;
      }
      const days = Math.floor(diffInMinutes / 1440);
      return `In ${days} day${days > 1 ? 's' : ''}`;
    };

    const formatDateTime = (dateString) => {
      const date = new Date(dateString);
      return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
      });
    };

    const handleCompleteReminder = async (reminderId) => {
      try {
        const response = await apiService.post(`/reminders/${reminderId}/complete`);
        if (response.success) {
          // Remove completed reminder from list
          setReminders(reminders.filter(r => r.id !== reminderId));
        }
      } catch (error) {
        console.error('Error completing reminder:', error);
      }
    };

    const handleStatusUpdate = async (reminderId, status) => {
      try {
        const response = await apiService.post(`/reminders/${reminderId}/status`, { status });
        if (response.success) {
          setReminders(reminders.map(r => 
            r.id === reminderId ? { ...r, status } : r
          ));
        }
      } catch (error) {
        console.error('Error updating status:', error);
      }
    };

    const handleEditReminder = (reminder) => {
      setSelectedReminder(reminder);
      setEditReminderTime(reminder.reminder_time.slice(0, 16)); // Format for datetime-local
      setEditReminderNote(reminder.reminder_note || '');
      setEditReminderOpen(true);
    };



    const handleReminderClick = (reminder) => {
      navigate(`/leads/${reminder.lead_id}`);
    };

    if (reminders.length === 0) {
      return (
        <Box sx={styles.noRemindersMessage}>
          <Typography color="textSecondary" align="center">
            No follow-up reminders set
          </Typography>
        </Box>
      );
    }

    return (
      <Box>
        {reminders.map((reminder) => {
          const isOverdue = new Date(reminder.reminder_time) < new Date();
          return (
            <Box 
              key={reminder.id} 
              sx={[styles.reminderItem, isOverdue && styles.reminderOverdue]}
              onClick={() => handleReminderClick(reminder)}
            >
              <Box sx={styles.reminderContent}>
                <Box sx={styles.reminderHeader}>
                  <Typography variant="body1" sx={styles.reminderLeadName}>
                    {reminder.lead_name}
                  </Typography>
                  <Chip 
                    label={formatReminderTime(reminder.reminder_time)}
                    size="small"
                    color={isOverdue ? 'error' : 'primary'}
                    sx={styles.reminderTimeChip}
                  />
                </Box>
                <Typography variant="caption" color="textSecondary" sx={styles.reminderPhone}>
                  {reminder.lead_phone}
                </Typography>
                <Typography variant="body2" sx={styles.reminderDateTime}>
                  {formatDateTime(reminder.reminder_time)}
                </Typography>
                {reminder.reminder_note && (
                  <Typography variant="body2" sx={styles.reminderNote}>
                    📝 {reminder.reminder_note}
                  </Typography>
                )}
              </Box>
              <Box sx={styles.reminderActions}>
                <IconButton 
                  size="small" 
                  onClick={(e) => {
                    e.stopPropagation();
                    handleEditReminder(reminder);
                  }}
                  sx={styles.editButton}
                  title="Edit Reminder"
                >
                  <Update sx={{ fontSize: 14 }} />
                </IconButton>
                <IconButton 
                  size="small" 
                  onClick={(e) => {
                    e.stopPropagation();
                    handleStatusUpdate(reminder.id, 'contacted');
                  }}
                  sx={styles.actionButton}
                  title="Mark as Contacted"
                >
                  <Phone sx={{ fontSize: 14 }} />
                </IconButton>
                <IconButton 
                  size="small" 
                  onClick={(e) => {
                    e.stopPropagation();
                    handleCompleteReminder(reminder.id);
                  }}
                  sx={styles.completeButton}
                  title="Mark as Completed"
                >
                  <CheckCircle fontSize="small" />
                </IconButton>
              </Box>
            </Box>
          );
        })}
      </Box>
    );
  };

  const TopPerformers = () => {
    const getBadgeIcon = (rank) => {
      if (rank === 1) return '🥇';
      if (rank === 2) return '🥈';
      if (rank === 3) return '🥉';
      return '👤';
    };

    return (
      <Box>
        {topAgents.length > 0 ? (
          <>
            {topAgents.map((agent) => (
              <Box key={agent.id} sx={styles.topAgent}>
                <Box sx={styles.topAgentInfo}>
                  <Typography variant="h6" sx={styles.topAgentRank}>
                    {getBadgeIcon(agent.rank)}
                  </Typography>
                  <Box>
                    <Typography variant="body2" sx={styles.topAgentName}>
                      {agent.name}
                    </Typography>
                    <Typography variant="caption" color="textSecondary">
                      {agent.qualified_leads} qualified • ₹{Number(agent.total_revenue).toLocaleString()}
                    </Typography>
                  </Box>
                </Box>
                <Chip 
                  label={`${agent.conversion_rate}%`} 
                  size="small" 
                  color={agent.rank <= 3 ? 'primary' : 'default'}
                />
              </Box>
            ))}
            <Button 
              variant="outlined" 
              onClick={() => navigate('/leaderboard')}
              sx={{ mt: 2 }}
              fullWidth
              size="small"
            >
              View Full Leaderboard
            </Button>
          </>
        ) : (
          <Typography color="textSecondary" align="center">
            No performance data available
          </Typography>
        )}
      </Box>
    );
  };

  if (loading) {
    return (
      <Box sx={styles.loadingContainer}>
        <CircularProgress />
        <Typography sx={{ mt: 2 }}>Loading dashboard...</Typography>
      </Box>
    );
  }

  return (
    <Box sx={styles.container}>
      <AppBar position="static" sx={styles.appBar}>
        <Toolbar>
          <Assessment sx={{ mr: 2 }} />
          <Typography variant="h6" sx={{ flexGrow: 1 }}>
            CRM Dashboard
          </Typography>
          <IconButton color="inherit" onClick={onRefresh} disabled={refreshing}>
            <Refresh />
          </IconButton>
          <IconButton color="inherit" onClick={handleLogout}>
            <ExitToApp />
          </IconButton>
        </Toolbar>
      </AppBar>

      <Box sx={styles.content}>
        <Paper sx={styles.header} elevation={2}>
          <Typography variant="h4" sx={styles.welcomeText}>
            Welcome, {user?.name}
          </Typography>
          <Typography variant="body1" sx={styles.subtitle}>
            Your lead performance overview
          </Typography>
        </Paper>

        <Grid container spacing={2} sx={{ marginBottom: 2 }}>
          <Grid item xs={12} md={8}>
            <Card sx={styles.card} elevation={2}>
              <CardContent>
                <Typography variant="h6" gutterBottom>
                  Weekly Performance
                </Typography>
                <WeeklyChart data={weeklyData} />
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} md={4}>
            <Card sx={styles.card} elevation={2}>
              <CardContent>
                <Typography variant="h6" gutterBottom>
                  <Speed sx={{ mr: 1, verticalAlign: 'middle' }} />
                  Quick Actions
                </Typography>
                <QuickActionsWidget />
              </CardContent>
            </Card>
          </Grid>
        </Grid>

        <Card sx={styles.card} elevation={2}>
          <CardContent>
            <Typography variant="h6" gutterBottom>
              <Schedule sx={{ mr: 1, verticalAlign: 'middle' }} />
              Follow-up Reminders
            </Typography>
            <FollowupReminders />
          </CardContent>
        </Card>

        <Grid container spacing={2} sx={styles.statsGrid}>
          <Grid item xs={6}>
            <Paper sx={[styles.statCard, styles.primaryCard]} elevation={2}>
              <Typography variant="h3" sx={styles.statNumber}>
                {stats?.total_leads || 0}
              </Typography>
              <Typography variant="caption" sx={styles.statLabel}>
                Total Leads
              </Typography>
            </Paper>
          </Grid>

          <Grid item xs={6}>
            <Paper sx={[styles.statCard, styles.warningCard]} elevation={2}>
              <Typography variant="h3" sx={styles.statNumber}>
                {stats?.pending_leads || 0}
              </Typography>
              <Typography variant="caption" sx={styles.statLabel}>
                Pending
              </Typography>
            </Paper>
          </Grid>

          <Grid item xs={6}>
            <Paper sx={[styles.statCard, styles.successCard]} elevation={2}>
              <Typography variant="h3" sx={styles.statNumber}>
                {stats?.qualified_leads || 0}
              </Typography>
              <Typography variant="caption" sx={styles.statLabel}>
                Qualified
              </Typography>
            </Paper>
          </Grid>

          <Grid item xs={6}>
            <Paper sx={[styles.statCard, styles.infoCard]} elevation={2}>
              <Typography variant="h3" sx={styles.statNumber}>
                {stats?.conversion_rate || 0}%
              </Typography>
              <Typography variant="caption" sx={styles.statLabel}>
                Conversion
              </Typography>
            </Paper>
          </Grid>
        </Grid>

        <Card sx={styles.card} elevation={2}>
          <CardContent>
            <Typography variant="h6" gutterBottom>
              Performance Summary
            </Typography>
            <Box sx={styles.performanceRow}>
              <Typography>Total Leads:</Typography>
              <Chip label={stats?.total_leads || 0} variant="outlined" />
            </Box>
            <Box sx={styles.performanceRow}>
              <Typography>Qualified:</Typography>
              <Chip label={stats?.qualified_leads || 0} variant="outlined" />
            </Box>
          </CardContent>
        </Card>

        <Card sx={styles.card} elevation={2}>
          <CardContent>
            <Typography variant="h6" gutterBottom>
              Recent Activity
            </Typography>
            {recentLeads.length > 0 ? (
              <Box>
                {recentLeads.slice(0, 3).map((lead) => (
                  <Box key={lead.id} sx={styles.recentLead} onClick={() => navigate(`/leads/${lead.id}`)}>
                    <Box>
                      <Typography variant="body2" sx={styles.recentLeadName}>
                        {lead.full_name}
                      </Typography>
                      <Typography variant="caption" color="textSecondary">
                        {lead.phone_number} • {getTimeAgo(lead.assigned_at)}
                      </Typography>
                    </Box>
                    <Chip 
                      label={getStatusText(lead.assignment_status)} 
                      color={getStatusColor(lead.assignment_status)}
                      size="small"
                    />
                  </Box>
                ))}
                <Button 
                  variant="outlined" 
                  onClick={() => navigate('/leads')}
                  sx={{ mt: 2 }}
                  fullWidth
                >
                  View All Leads
                </Button>
              </Box>
            ) : (
              <Typography color="textSecondary">
                No recent leads assigned
              </Typography>
            )}
          </CardContent>
        </Card>

        <Card sx={styles.card} elevation={2}>
          <CardContent>
            <Typography variant="h6" gutterBottom>
              Today's Goals
            </Typography>
            <Box sx={styles.goalItem}>
              <Typography variant="body2">Calls Target</Typography>
              <Box sx={styles.progressBar}>
                <Box sx={[styles.progressFill, { width: `${Math.min((stats?.contacted_today || 0) / 10 * 100, 100)}%` }]} />
              </Box>
              <Typography variant="caption">{stats?.contacted_today || 0}/10</Typography>
            </Box>
            <Box sx={styles.goalItem}>
              <Typography variant="body2">Qualification Target</Typography>
              <Box sx={styles.progressBar}>
                <Box sx={[styles.progressFill, { width: `${Math.min((stats?.qualified_today || 0) / 3 * 100, 100)}%` }]} />
              </Box>
              <Typography variant="caption">{stats?.qualified_today || 0}/3</Typography>
            </Box>
          </CardContent>
        </Card>

        <Card sx={styles.card} elevation={2}>
          <CardContent>
            <Typography variant="h6" gutterBottom>
              <EmojiEvents sx={{ mr: 1, verticalAlign: 'middle', color: '#ffd700' }} />
              Top Performers
            </Typography>
            <TopPerformers />
          </CardContent>
        </Card>

      </Box>
      
      <WhatsAppTemplateSelector
        open={templateSelectorOpen}
        onClose={() => {
          setTemplateSelectorOpen(false);
          setSelectedLead(null);
        }}
        lead={selectedLead}
        onSend={(template, message) => {
          console.log('Template sent:', template.name, message);
        }}
      />
      
      <Dialog open={editReminderOpen} onClose={() => setEditReminderOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Edit Reminder</DialogTitle>
        <DialogContent>
          {selectedReminder && (
            <Box sx={{ mt: 1 }}>
              <Typography variant="subtitle2" sx={{ mb: 2 }}>
                Lead: {selectedReminder.lead_name} ({selectedReminder.lead_phone})
              </Typography>
              <TextField
                label="Reminder Time"
                type="datetime-local"
                value={editReminderTime}
                onChange={(e) => setEditReminderTime(e.target.value)}
                fullWidth
                sx={{ mb: 2 }}
                InputLabelProps={{ shrink: true }}
              />
              <TextField
                label="Note"
                multiline
                rows={3}
                value={editReminderNote}
                onChange={(e) => setEditReminderNote(e.target.value)}
                fullWidth
                placeholder="Reminder note..."
              />
            </Box>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setEditReminderOpen(false)}>Cancel</Button>
          <Button onClick={handleUpdateReminder} variant="contained">Update</Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}

const styles = {
  container: {
    minHeight: '100vh',
    backgroundColor: '#f5f5f5',
    paddingBottom: 8, // Space for bottom nav
  },
  appBar: {
    backgroundColor: '#1976d2',
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
  header: {
    padding: 3,
    marginBottom: 2,
    backgroundColor: '#fff',
  },
  welcomeText: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#333',
  },
  subtitle: {
    color: '#666',
    marginTop: 0.5,
  },
  statsGrid: {
    marginBottom: 2,
  },
  statCard: {
    padding: 3,
    textAlign: 'center',
    height: 120,
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'center',
  },
  primaryCard: {
    backgroundColor: '#e3f2fd',
  },
  warningCard: {
    backgroundColor: '#fff3e0',
  },
  infoCard: {
    backgroundColor: '#e8f5e8',
  },
  successCard: {
    backgroundColor: '#f3e5f5',
  },
  statNumber: {
    fontSize: 28,
    fontWeight: 'bold',
    color: '#333',
  },
  statLabel: {
    fontSize: 12,
    color: '#666',
    textAlign: 'center',
  },
  card: {
    marginBottom: 2,
  },
  performanceRow: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginY: 1,
  },
  recentLead: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 1,
    marginY: 1,
    backgroundColor: '#f8f9fa',
    borderRadius: 1,
    cursor: 'pointer',
    '&:hover': {
      backgroundColor: '#e9ecef',
    },
  },
  recentLeadName: {
    fontWeight: 'bold',
  },
  goalItem: {
    marginY: 2,
  },
  progressBar: {
    height: 8,
    backgroundColor: '#e0e0e0',
    borderRadius: 4,
    marginY: 1,
    overflow: 'hidden',
  },
  progressFill: {
    height: '100%',
    backgroundColor: '#4caf50',
    transition: 'width 0.3s ease',
  },
  chartContainer: {
    display: 'flex',
    flexDirection: 'column',
    minHeight: 120,
  },
  chartDays: {
    display: 'flex',
    justifyContent: 'space-around',
    alignItems: 'flex-end',
    flex: 1,
  },
  chartDay: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    flex: 1,
    minWidth: 40,
  },
  chartBars: {
    display: 'flex',
    alignItems: 'flex-end',
    height: 80,
    gap: 2,
    marginBottom: 1,
  },
  chartBar: {
    width: 8,
    minHeight: 2,
    borderRadius: 1,
    transition: 'height 0.3s ease',
  },
  contactedBar: {
    backgroundColor: '#2196f3',
  },
  qualifiedBar: {
    backgroundColor: '#4caf50',
  },
  paymentBar: {
    backgroundColor: '#9c27b0',
  },
  chartLabel: {
    fontSize: 10,
    textAlign: 'center',
  },
  chartLegend: {
    display: 'flex',
    justifyContent: 'center',
    gap: 2,
    marginTop: 2,
  },
  legendItem: {
    display: 'flex',
    alignItems: 'center',
    gap: 0.5,
  },
  legendColor: {
    width: 12,
    height: 12,
    borderRadius: 1,
  },
  quickButton: {
    marginBottom: 1,
    fontWeight: 'bold',
    fontSize: 12,
    padding: '6px 12px',
  },
  callButton: {
    backgroundColor: '#4caf50',
    '&:hover': {
      backgroundColor: '#45a049',
    },
  },
  quickLead: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 0.75,
    marginY: 0.25,
    backgroundColor: '#f8f9fa',
    borderRadius: 1,
  },
  quickLeadInfo: {
    flex: 1,
    minWidth: 0,
  },
  quickLeadName: {
    fontWeight: 'bold',
    fontSize: 12,
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap',
  },
  quickActions: {
    display: 'flex',
    gap: 0.25,
  },
  quickActionBtn: {
    padding: 0.25,
    minWidth: 28,
    height: 28,
    backgroundColor: '#fff',
    '&:hover': {
      backgroundColor: '#e0e0e0',
    },
  },
  whatsappBtn: {
    color: '#25D366',
  },
  templateButtons: {
    display: 'flex',
    gap: 0.25,
    marginTop: 0.5,
    flexWrap: 'wrap',
  },
  noLeadsMessage: {
    textAlign: 'center',
    padding: 2,
  },
  topAgent: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 1,
    marginY: 0.5,
    backgroundColor: '#f8f9fa',
    borderRadius: 1,
  },
  topAgentInfo: {
    display: 'flex',
    alignItems: 'center',
    gap: 1,
  },
  topAgentRank: {
    fontSize: 20,
  },
  topAgentName: {
    fontWeight: 'bold',
    fontSize: 13,
  },
  noRemindersMessage: {
    textAlign: 'center',
    padding: 2,
  },
  reminderItem: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    padding: 1.5,
    marginY: 1,
    backgroundColor: '#f8f9fa',
    borderRadius: 1,
    cursor: 'pointer',
    borderLeft: '4px solid #2196f3',
    '&:hover': {
      backgroundColor: '#e9ecef',
    },
  },
  reminderOverdue: {
    borderLeft: '4px solid #f44336',
    backgroundColor: '#ffebee',
  },
  reminderContent: {
    flex: 1,
  },
  reminderHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 0.5,
  },
  reminderLeadName: {
    fontWeight: 'bold',
    fontSize: 15,
  },
  reminderTimeChip: {
    fontSize: 10,
  },
  reminderPhone: {
    display: 'block',
    marginBottom: 0.5,
  },
  reminderDateTime: {
    color: '#666',
    fontSize: 12,
    marginTop: 0.5,
  },
  reminderNote: {
    marginTop: 0.5,
    color: '#555',
    fontSize: 13,
  },
  reminderActions: {
    display: 'flex',
    alignItems: 'center',
    marginLeft: 1,
  },
  completeButton: {
    color: '#4caf50',
    '&:hover': {
      backgroundColor: '#e8f5e9',
    },
  },
  actionButton: {
    color: '#2196f3',
    '&:hover': {
      backgroundColor: '#e3f2fd',
    },
  },
  editButton: {
    color: '#ff9800',
    '&:hover': {
      backgroundColor: '#fff3e0',
    },
  },
};