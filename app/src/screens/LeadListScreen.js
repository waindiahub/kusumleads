import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Box,
  Card,
  CardContent,
  CardActions,
  Typography,
  Chip,
  TextField,
  Button,
  Grid,
  AppBar,
  Toolbar,
  IconButton,
  Fab,
  CircularProgress,
  InputAdornment,
  MenuItem
} from '@mui/material';
import { 
  Phone, 
  WhatsApp, 
  Visibility, 
  Refresh,
  ArrowBack,
  DateRange,
  Campaign as CampaignIcon,
  LocationOn,
  Star,
  Whatshot,
  TrendingUp
} from '@mui/icons-material';
import { apiService } from '../services/ApiService';
import { useAuth } from '../services/AuthService';

export default function LeadListScreen() {
  const [leads, setLeads] = useState([]);
  const [filteredLeads, setFilteredLeads] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [dateFilter, setDateFilter] = useState('all');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [singleDate, setSingleDate] = useState('');
  const { user } = useAuth();
  const navigate = useNavigate();

  const loadLeads = useCallback(async () => {
    try {
      const response = await apiService.getLeads({ agent_id: user.id });
      if (response.success) {
        setLeads(response.data);
      }
    } catch (error) {
      console.error('Error loading leads:', error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [user.id]);

  const filterLeads = useCallback(() => {
    let filtered = leads;

    // Single date filter
    if (dateFilter === 'single' && singleDate) {
      filtered = filtered.filter(lead => {
        const leadDate = new Date(lead.created_time);
        const selectedDate = new Date(singleDate);
        return leadDate.toDateString() === selectedDate.toDateString();
      });
    }
    // Custom date range filter
    else if (dateFilter === 'custom' && (startDate || endDate)) {
      if (startDate && endDate) {
        filtered = filtered.filter(lead => {
          const leadDate = new Date(lead.assigned_at).toDateString();
          const start = new Date(startDate).toDateString();
          const end = new Date(endDate).toDateString();
          return leadDate >= start && leadDate <= end;
        });
      } else if (startDate) {
        filtered = filtered.filter(lead => {
          const leadDate = new Date(lead.assigned_at).toDateString();
          const start = new Date(startDate).toDateString();
          return leadDate >= start;
        });
      } else if (endDate) {
        filtered = filtered.filter(lead => {
          const leadDate = new Date(lead.assigned_at).toDateString();
          const end = new Date(endDate).toDateString();
          return leadDate <= end;
        });
      }
    } else if (dateFilter !== 'all' && dateFilter !== 'custom') {
      // Quick filter options
      const now = new Date();
      const filterDate = new Date();
      
      switch (dateFilter) {
        case 'today':
          filterDate.setHours(0, 0, 0, 0);
          filtered = filtered.filter(lead => new Date(lead.created_time) >= filterDate);
          break;
        case 'week':
          filterDate.setDate(now.getDate() - 7);
          filtered = filtered.filter(lead => new Date(lead.created_time) >= filterDate);
          break;
        case 'month':
          filterDate.setMonth(now.getMonth() - 1);
          filtered = filtered.filter(lead => new Date(lead.created_time) >= filterDate);
          break;
        default:
          // No additional filtering needed
          break;
      }
    }

    setFilteredLeads(filtered);
  }, [leads, dateFilter, startDate, endDate, singleDate]);

  useEffect(() => {
    loadLeads();
  }, [loadLeads]);

  useEffect(() => {
    filterLeads();
  }, [filterLeads]);

  const handleQuickFilterChange = (value) => {
    setDateFilter(value);
    setStartDate('');
    setEndDate('');
    setSingleDate('');
  };

  const handleStartDateChange = (value) => {
    setStartDate(value);
    setDateFilter('custom');
  };

  const handleEndDateChange = (value) => {
    setEndDate(value);
    setDateFilter('custom');
  };

  const onRefresh = () => {
    setRefreshing(true);
    loadLeads();
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

  const makePhoneCall = (phoneNumber) => {
    const cleanPhone = phoneNumber.replace(/[^0-9+]/g, '');
    window.open(`tel:${cleanPhone}`);
  };

  const openWhatsApp = (phoneNumber) => {
    const cleanPhone = phoneNumber.replace(/[^0-9]/g, '');
    window.open(`https://wa.me/${cleanPhone}`);
  };

  const isUrgent = (assignedAt) => {
    const assignedDate = new Date(assignedAt);
    const now = new Date();
    const hoursDiff = (now - assignedDate) / (1000 * 60 * 60);
    return hoursDiff > 24; // Urgent if assigned more than 24 hours ago
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

  const getCardStyle = (status, priority) => {
    const baseStyle = { 
      marginBottom: 1, 
      cursor: 'pointer',
      position: 'relative'
    };
    
    let borderColor = '#ff9800'; // default
    switch (status) {
      case 'qualified': borderColor = '#4caf50'; break;
      case 'not_qualified': borderColor = '#f44336'; break;
      case 'contacted': borderColor = '#2196f3'; break;
      case 'call_not_picked': borderColor = '#757575'; break;
      case 'payment_completed': borderColor = '#9c27b0'; break;
      default:
        // Use default borderColor
        break;
    }
    
    // Add glow effect for hot leads
    const glowEffect = priority === 'hot' ? {
      boxShadow: '0 0 10px rgba(255, 87, 34, 0.3)',
      border: '1px solid rgba(255, 87, 34, 0.2)'
    } : {};
    
    return { 
      ...baseStyle, 
      borderLeft: `4px solid ${borderColor}`,
      ...glowEffect
    };
  };

  const getPriorityIcon = (priority) => {
    switch (priority) {
      case 'hot':
        return <Whatshot sx={{ color: '#ff5722', fontSize: 18 }} />;
      case 'high':
        return <TrendingUp sx={{ color: '#ff9800', fontSize: 18 }} />;
      case 'medium':
        return <Star sx={{ color: '#2196f3', fontSize: 18 }} />;
      default:
        return null;
    }
  };

  const getScoreChipStyle = (score) => {
    if (score >= 80) return { backgroundColor: '#ff5722', color: 'white' };
    if (score >= 65) return { backgroundColor: '#ff9800', color: 'white' };
    if (score >= 50) return { backgroundColor: '#2196f3', color: 'white' };
    return { backgroundColor: '#757575', color: 'white' };
  };


  if (loading) {
    return (
      <Box sx={styles.loadingContainer}>
        <CircularProgress />
        <Typography sx={{ mt: 2 }}>Loading leads...</Typography>
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
            onClick={() => navigate('/dashboard')}
          >
            <ArrowBack />
          </IconButton>
          <Typography variant="h6" sx={{ flexGrow: 1 }}>
            My Leads ({filteredLeads.length})
          </Typography>
          <IconButton color="inherit" onClick={onRefresh} disabled={refreshing}>
            <Refresh />
          </IconButton>
        </Toolbar>
      </AppBar>

      <Box sx={styles.content}>
        <Box sx={styles.dateFilterContainer}>
          <Grid container spacing={2}>
            <Grid item xs={12} sm={4}>
              <TextField
                select
                label="Quick Filter"
                value={dateFilter}
                onChange={(e) => handleQuickFilterChange(e.target.value)}
                fullWidth
                sx={styles.dateFilter}
                InputProps={{
                  startAdornment: (
                    <InputAdornment position="start">
                      <IconButton 
                        size="small" 
                        onClick={() => handleQuickFilterChange('single')}
                        sx={{ padding: 0.5 }}
                      >
                        <DateRange />
                      </IconButton>
                    </InputAdornment>
                  ),
                }}
              >
                <MenuItem value="all">All Time</MenuItem>
                <MenuItem value="today">Today</MenuItem>
                <MenuItem value="week">Last 7 Days</MenuItem>
                <MenuItem value="month">Last 30 Days</MenuItem>
                <MenuItem value="single">Single Date</MenuItem>
                <MenuItem value="custom">Custom Range</MenuItem>
              </TextField>
            </Grid>
            {dateFilter === 'single' && (
              <Grid item xs={12} sm={4}>
                <TextField
                  label="Select Date"
                  type="date"
                  value={singleDate}
                  onChange={(e) => setSingleDate(e.target.value)}
                  fullWidth
                  sx={styles.dateFilter}
                  InputLabelProps={{
                    shrink: true,
                  }}
                />
              </Grid>
            )}
            {dateFilter === 'custom' && (
              <>
                <Grid item xs={12} sm={4}>
                  <TextField
                    label="Start Date"
                    type="date"
                    value={startDate}
                    onChange={(e) => handleStartDateChange(e.target.value)}
                    fullWidth
                    sx={styles.dateFilter}
                    InputLabelProps={{
                      shrink: true,
                    }}
                  />
                </Grid>
                <Grid item xs={12} sm={4}>
                  <TextField
                    label="End Date"
                    type="date"
                    value={endDate}
                    onChange={(e) => handleEndDateChange(e.target.value)}
                    fullWidth
                    sx={styles.dateFilter}
                    InputLabelProps={{
                      shrink: true,
                    }}
                  />
                </Grid>
              </>
            )}
          </Grid>
        </Box>

        <Grid container spacing={2}>
          {filteredLeads.map((lead) => (
            <Grid item xs={12} key={lead.id}>
              <Card sx={[styles.leadCard, getCardStyle(lead.assignment_status, lead.priority_level)]} elevation={2}>
                <CardContent onClick={() => navigate(`/leads/${lead.id}`)} sx={styles.cardContent}>
                  <Box sx={styles.leadHeader}>
                    <Box sx={styles.leadTitleSection}>
                      <Box sx={styles.leadNameSection}>
                        <Typography variant="h6" sx={styles.leadName}>
                          {lead.full_name}
                        </Typography>
                        {getPriorityIcon(lead.priority_level)}
                      </Box>
                      <Box sx={styles.leadBadges}>
                        {lead.lead_score && (
                          <Chip 
                            label={`${lead.lead_score}%`} 
                            size="small" 
                            sx={getScoreChipStyle(lead.lead_score)}
                          />
                        )}
                        {isUrgent(lead.assigned_at) && (
                          <Chip label="Urgent" color="error" size="small" sx={styles.urgentChip} />
                        )}
                      </Box>
                    </Box>
                    <Chip
                      label={getStatusText(lead.assignment_status)}
                      color={getStatusColor(lead.assignment_status)}
                      size="small"
                      sx={styles.statusChip}
                    />
                  </Box>
                  
                  <Box sx={styles.leadInfo}>
                    <Box sx={styles.infoRow}>
                      <Phone sx={styles.infoIcon} />
                      <Typography sx={styles.infoText}>
                        {lead.phone_number}
                      </Typography>
                    </Box>
                    {lead.city && (
                      <Box sx={styles.infoRow}>
                        <LocationOn sx={styles.infoIcon} />
                        <Typography sx={styles.infoText}>
                          {lead.city}
                        </Typography>
                      </Box>
                    )}
                    {lead.campaign_name && (
                      <Box sx={styles.infoRow}>
                        <CampaignIcon sx={styles.infoIcon} />
                        <Typography sx={styles.infoText}>
                          {lead.campaign_name}
                        </Typography>
                      </Box>
                    )}
                  </Box>
                  
                  <Box sx={styles.leadFooter}>
                    <Typography sx={styles.dateText}>
                      {getTimeAgo(lead.assigned_at)}
                    </Typography>
                    {lead.price_offered && (
                      <Typography sx={styles.priceText}>
                        ₹{Number(lead.price_offered).toLocaleString()}
                      </Typography>
                    )}
                  </Box>
                </CardContent>
                
                <CardActions sx={styles.cardActions}>
                  <Button
                    variant="outlined"
                    startIcon={<Phone />}
                    onClick={(e) => {
                      e.stopPropagation();
                      makePhoneCall(lead.phone_number);
                    }}
                    size="small"
                    sx={styles.actionButton}
                  >
                    Call
                  </Button>
                  <Button
                    variant="contained"
                    startIcon={<WhatsApp />}
                    onClick={(e) => {
                      e.stopPropagation();
                      openWhatsApp(lead.phone_number);
                    }}
                    size="small"
                    sx={[styles.actionButton, styles.whatsappButton]}
                  >
                    WhatsApp
                  </Button>
                  <Button
                    variant="outlined"
                    startIcon={<Visibility />}
                    onClick={(e) => {
                      e.stopPropagation();
                      navigate(`/leads/${lead.id}`);
                    }}
                    size="small"
                    sx={styles.actionButton}
                  >
                    View
                  </Button>
                </CardActions>
              </Card>
            </Grid>
          ))}
        </Grid>

        {filteredLeads.length === 0 && (
          <Box sx={styles.noLeads}>
            <Typography variant="h6" color="textSecondary">
              No leads found
            </Typography>
            <Typography color="textSecondary">
              Try adjusting your search or filter criteria
            </Typography>
          </Box>
        )}
      </Box>

      <Fab
        color="primary"
        sx={styles.fab}
        onClick={onRefresh}
        disabled={refreshing}
      >
        <Refresh />
      </Fab>
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
  dateFilterContainer: {
    marginBottom: 2,
  },
  dateFilter: {
    backgroundColor: 'white',
  },
  leadCard: {
    marginBottom: 1,
    cursor: 'pointer',
    transition: 'all 0.2s ease-in-out',
    '&:hover': {
      boxShadow: 6,
      transform: 'translateY(-2px)',
    },
  },
  cardContent: {
    cursor: 'pointer',
  },
  leadHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 1,
  },
  leadTitleSection: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    flex: 1,
  },
  leadNameSection: {
    display: 'flex',
    alignItems: 'center',
    gap: 1,
  },
  leadBadges: {
    display: 'flex',
    gap: 0.5,
    flexWrap: 'wrap',
  },
  leadName: {
    fontSize: 18,
    fontWeight: 'bold',
    flex: 1,
  },
  leadInfo: {
    marginBottom: 1,
    gap: 0.5,
    display: 'flex',
    flexDirection: 'column',
  },
  infoRow: {
    display: 'flex',
    alignItems: 'center',
    gap: 1,
  },
  infoIcon: {
    fontSize: 16,
    color: '#666',
  },
  infoText: {
    fontSize: 14,
    color: '#666',
  },
  leadFooter: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 1,
  },
  dateText: {
    fontSize: 12,
    color: '#999',
    fontWeight: 500,
  },
  priceText: {
    fontSize: 14,
    color: '#4caf50',
    fontWeight: 'bold',
  },
  urgentChip: {
    fontSize: 10,
    height: 20,
  },
  statusChip: {
    fontWeight: 'bold',
  },
  cardActions: {
    justifyContent: 'space-around',
    paddingX: 2,
    paddingBottom: 2,
  },
  actionButton: {
    flex: 1,
    marginX: 0.5,
  },
  whatsappButton: {
    backgroundColor: '#25D366',
    '&:hover': {
      backgroundColor: '#20B858',
    },
  },
  fab: {
    position: 'fixed',
    bottom: 16,
    right: 16,
  },
  noLeads: {
    textAlign: 'center',
    padding: 4,
    marginTop: 4,
  },
};