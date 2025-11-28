import React from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { 
  BottomNavigation, 
  BottomNavigationAction, 
  Paper 
} from '@mui/material';
import { 
  Dashboard, 
  Assignment, 
  Person,
  EmojiEvents,
  Chat
} from '@mui/icons-material';

export default function BottomNav() {
  const navigate = useNavigate();
  const location = useLocation();

  const getActiveTab = () => {
    if (location.pathname === '/dashboard') return 0;
    if (location.pathname === '/leads' || location.pathname.startsWith('/leads/')) return 1;
    if (location.pathname === '/chats' || location.pathname.startsWith('/chats/')) return 2;
    if (location.pathname === '/leaderboard') return 3;
    if (location.pathname === '/profile') return 4;
    return 0;
  };

  const handleChange = (event, newValue) => {
    switch (newValue) {
      case 0:
        navigate('/dashboard');
        break;
      case 1:
        navigate('/leads');
        break;
      case 2:
        navigate('/chats');
        break;
      case 3:
        navigate('/leaderboard');
        break;
      case 4:
        navigate('/profile');
        break;
      default:
        break;
    }
  };

  return (
    <Paper 
      sx={{ 
        position: 'fixed', 
        bottom: 0, 
        left: 0, 
        right: 0, 
        zIndex: 1000,
        elevation: 8
      }}
    >
      <BottomNavigation
        value={getActiveTab()}
        onChange={handleChange}
        sx={{
          height: 60,
          '& .MuiBottomNavigationAction-root': {
            minWidth: 'auto',
            paddingTop: 1,
          },
          '& .MuiBottomNavigationAction-label': {
            fontSize: 12,
            fontWeight: 500,
          },
        }}
      >
        <BottomNavigationAction 
          label="Dashboard" 
          icon={<Dashboard />}
          showLabel={true}
          sx={{
            '& .MuiBottomNavigationAction-label': {
              color: '#000000 !important',
              opacity: '1 !important',
              display: 'block !important',
            },
            '&.Mui-selected .MuiBottomNavigationAction-label': {
              color: '#000000 !important',
              opacity: '1 !important',
            },
            '&:not(.Mui-selected) .MuiBottomNavigationAction-label': {
              color: '#000000 !important',
              opacity: '1 !important',
            },
          }}
        />
        <BottomNavigationAction 
          label="My Leads" 
          icon={<Assignment />}
          showLabel={true}
          sx={{
            '& .MuiBottomNavigationAction-label': {
              color: '#000000 !important',
              opacity: '1 !important',
              display: 'block !important',
            },
            '&.Mui-selected .MuiBottomNavigationAction-label': {
              color: '#000000 !important',
              opacity: '1 !important',
            },
            '&:not(.Mui-selected) .MuiBottomNavigationAction-label': {
              color: '#000000 !important',
              opacity: '1 !important',
            },
          }}
        />
        <BottomNavigationAction 
          label="Chats" 
          icon={<Chat />}
          showLabel={true}
          sx={{
            '& .MuiBottomNavigationAction-label': {
              color: '#000000 !important',
              opacity: '1 !important',
              display: 'block !important',
            },
            '&.Mui-selected .MuiBottomNavigationAction-label': {
              color: '#000000 !important',
              opacity: '1 !important',
            },
            '&:not(.Mui-selected) .MuiBottomNavigationAction-label': {
              color: '#000000 !important',
              opacity: '1 !important',
            },
          }}
        />
        <BottomNavigationAction 
          label="Leaderboard" 
          icon={<EmojiEvents />}
          showLabel={true}
          sx={{
            '& .MuiBottomNavigationAction-label': {
              color: '#000000 !important',
              opacity: '1 !important',
              display: 'block !important',
            },
            '&.Mui-selected .MuiBottomNavigationAction-label': {
              color: '#000000 !important',
              opacity: '1 !important',
            },
            '&:not(.Mui-selected) .MuiBottomNavigationAction-label': {
              color: '#000000 !important',
              opacity: '1 !important',
            },
          }}
        />
        <BottomNavigationAction 
          label="Profile" 
          icon={<Person />}
          showLabel={true}
          sx={{
            '& .MuiBottomNavigationAction-label': {
              color: '#000000 !important',
              opacity: '1 !important',
              display: 'block !important',
            },
            '&.Mui-selected .MuiBottomNavigationAction-label': {
              color: '#000000 !important',
              opacity: '1 !important',
            },
            '&:not(.Mui-selected) .MuiBottomNavigationAction-label': {
              color: '#000000 !important',
              opacity: '1 !important',
            },
          }}
        />
      </BottomNavigation>
    </Paper>
  );
}
