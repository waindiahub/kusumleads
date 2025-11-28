import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Box,
  Card,
  CardContent,
  Typography,
  Avatar,
  Chip,
  Button,
  AppBar,
  Toolbar,
  IconButton,
  CircularProgress,
  Tabs,
  Tab
} from '@mui/material';
import { 
  ArrowBack, 
  EmojiEvents,
  Star,
  TrendingUp,
  Phone,
  AttachMoney
} from '@mui/icons-material';
import { apiService } from '../services/ApiService';

export default function LeaderboardScreen() {
  const [leaderboard, setLeaderboard] = useState([]);
  const [loading, setLoading] = useState(true);
  const [period, setPeriod] = useState('week');
  const navigate = useNavigate();

  useEffect(() => {
    loadLeaderboard();
  }, [period]);

  const loadLeaderboard = async () => {
    try {
      setLoading(true);
      const response = await apiService.get('/leaderboard', { period });
      if (response.success) {
        setLeaderboard(response.data);
      }
    } catch (error) {
      console.error('Error loading leaderboard:', error);
    } finally {
      setLoading(false);
    }
  };

  const getBadgeIcon = (badge) => {
    switch (badge) {
      case 'gold': return '🥇';
      case 'silver': return '🥈';
      case 'bronze': return '🥉';
      case 'star': return '⭐';
      case 'active': return '🔥';
      default: return '👤';
    }
  };

  const getBadgeColor = (badge) => {
    switch (badge) {
      case 'gold': return '#ffd700';
      case 'silver': return '#c0c0c0';
      case 'bronze': return '#cd7f32';
      case 'star': return '#ff9800';
      case 'active': return '#f44336';
      default: return '#757575';
    }
  };

  const getRankStyle = (rank) => {
    if (rank <= 3) {
      return {
        background: `linear-gradient(45deg, ${getBadgeColor(rank === 1 ? 'gold' : rank === 2 ? 'silver' : 'bronze')}, #fff)`,
        border: `2px solid ${getBadgeColor(rank === 1 ? 'gold' : rank === 2 ? 'silver' : 'bronze')}`,
        boxShadow: '0 4px 8px rgba(0,0,0,0.2)'
      };
    }
    return {};
  };

  if (loading) {
    return (
      <Box sx={styles.loadingContainer}>
        <CircularProgress />
        <Typography sx={{ mt: 2 }}>Loading leaderboard...</Typography>
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
          <EmojiEvents sx={{ mr: 1 }} />
          <Typography variant="h6" sx={{ flexGrow: 1 }}>
            Leaderboard
          </Typography>
        </Toolbar>
      </AppBar>

      <Box sx={styles.content}>
        <Tabs 
          value={period} 
          onChange={(e, newValue) => setPeriod(newValue)}
          centered
          sx={styles.tabs}
        >
          <Tab label="Today" value="today" />
          <Tab label="This Week" value="week" />
          <Tab label="This Month" value="month" />
          <Tab label="All Time" value="all" />
        </Tabs>

        {leaderboard.length > 0 && (
          <Box sx={styles.podium}>
            {leaderboard.slice(0, 3).map((agent, index) => (
              <Box key={agent.id} sx={[styles.podiumCard, getRankStyle(agent.rank)]}>
                <Typography variant="h2" sx={styles.podiumRank}>
                  {getBadgeIcon(agent.badge)}
                </Typography>
                <Typography variant="h6" sx={styles.podiumName}>
                  {agent.name}
                </Typography>
                <Typography variant="body2" sx={styles.podiumStats}>
                  {agent.qualified_leads} Qualified
                </Typography>
                <Typography variant="body2" sx={styles.podiumRevenue}>
                  ₹{Number(agent.total_revenue).toLocaleString()}
                </Typography>
              </Box>
            ))}
          </Box>
        )}

        <Box sx={styles.leaderboardList}>
          {leaderboard.map((agent) => (
            <Card key={agent.id} sx={[styles.agentCard, getRankStyle(agent.rank)]} elevation={2}>
              <CardContent sx={styles.agentContent}>
                <Box sx={styles.agentHeader}>
                  <Box sx={styles.agentInfo}>
                    <Avatar sx={[styles.avatar, { backgroundColor: getBadgeColor(agent.badge) }]}>
                      {agent.rank}
                    </Avatar>
                    <Box>
                      <Typography variant="h6" sx={styles.agentName}>
                        {agent.name} {getBadgeIcon(agent.badge)}
                      </Typography>
                      <Typography variant="caption" color="textSecondary">
                        {agent.points} points
                      </Typography>
                    </Box>
                  </Box>
                  <Chip 
                    label={`#${agent.rank}`} 
                    color={agent.rank <= 3 ? 'primary' : 'default'}
                    sx={styles.rankChip}
                  />
                </Box>

                <Box sx={styles.statsGrid}>
                  <Box sx={styles.statItem}>
                    <Phone sx={styles.statIcon} />
                    <Typography variant="body2">{agent.contacted_leads}</Typography>
                    <Typography variant="caption">Contacted</Typography>
                  </Box>
                  <Box sx={styles.statItem}>
                    <Star sx={styles.statIcon} />
                    <Typography variant="body2">{agent.qualified_leads}</Typography>
                    <Typography variant="caption">Qualified</Typography>
                  </Box>
                  <Box sx={styles.statItem}>
                    <AttachMoney sx={styles.statIcon} />
                    <Typography variant="body2">{agent.payment_leads}</Typography>
                    <Typography variant="caption">Payments</Typography>
                  </Box>
                  <Box sx={styles.statItem}>
                    <TrendingUp sx={styles.statIcon} />
                    <Typography variant="body2">{agent.conversion_rate}%</Typography>
                    <Typography variant="caption">Conversion</Typography>
                  </Box>
                </Box>

                <Box sx={styles.revenueSection}>
                  <Typography variant="body1" sx={styles.revenue}>
                    Revenue: ₹{Number(agent.total_revenue).toLocaleString()}
                  </Typography>
                </Box>
              </CardContent>
            </Card>
          ))}
        </Box>

        {leaderboard.length === 0 && (
          <Box sx={styles.noData}>
            <Typography variant="h6" color="textSecondary">
              No data available
            </Typography>
            <Typography color="textSecondary">
              Performance data will appear here once agents start working
            </Typography>
          </Box>
        )}
      </Box>
    </Box>
  );
}

const styles = {
  container: {
    minHeight: '100vh',
    backgroundColor: '#f5f5f5',
    paddingBottom: 8,
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
  tabs: {
    backgroundColor: 'white',
    marginBottom: 2,
    borderRadius: 1,
  },
  podium: {
    display: 'flex',
    justifyContent: 'center',
    gap: 1,
    marginBottom: 3,
  },
  podiumCard: {
    padding: 2,
    textAlign: 'center',
    borderRadius: 2,
    minWidth: 100,
    backgroundColor: 'white',
  },
  podiumRank: {
    fontSize: 40,
    marginBottom: 1,
  },
  podiumName: {
    fontWeight: 'bold',
    marginBottom: 0.5,
  },
  podiumStats: {
    color: '#666',
  },
  podiumRevenue: {
    color: '#4caf50',
    fontWeight: 'bold',
  },
  leaderboardList: {
    display: 'flex',
    flexDirection: 'column',
    gap: 1,
  },
  agentCard: {
    marginBottom: 1,
  },
  agentContent: {
    padding: '16px !important',
  },
  agentHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 2,
  },
  agentInfo: {
    display: 'flex',
    alignItems: 'center',
    gap: 2,
  },
  avatar: {
    width: 40,
    height: 40,
    fontWeight: 'bold',
  },
  agentName: {
    fontSize: 16,
    fontWeight: 'bold',
  },
  rankChip: {
    fontWeight: 'bold',
  },
  statsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(4, 1fr)',
    gap: 1,
    marginBottom: 2,
  },
  statItem: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    padding: 1,
    backgroundColor: '#f8f9fa',
    borderRadius: 1,
  },
  statIcon: {
    fontSize: 20,
    color: '#666',
    marginBottom: 0.5,
  },
  revenueSection: {
    textAlign: 'center',
    paddingTop: 1,
    borderTop: '1px solid #eee',
  },
  revenue: {
    fontWeight: 'bold',
    color: '#4caf50',
  },
  noData: {
    textAlign: 'center',
    padding: 4,
    marginTop: 4,
  },
};