import React from 'react';
import {
  Box,
  Card,
  CardContent,
  Typography,
  Button,
  Avatar,
  Divider,
  List,
  ListItem,
  ListItemIcon,
  ListItemText,
  AppBar,
  Toolbar
} from '@mui/material';
import { 
  Person, 
  Email, 
  ExitToApp,
  Settings,
  Info
} from '@mui/icons-material';
import { useAuth } from '../services/AuthService';
import { useNavigate } from 'react-router-dom';
import OneSignalStatus from '../components/OneSignalStatus';
import OneSignalDebug from '../components/OneSignalDebug';

export default function ProfileScreen() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <Box sx={styles.container}>
      <AppBar position="static">
        <Toolbar>
          <Person sx={{ mr: 2 }} />
          <Typography variant="h6">
            Profile
          </Typography>
        </Toolbar>
      </AppBar>

      <Box sx={styles.content}>
        <Card sx={styles.profileCard} elevation={2}>
          <CardContent sx={styles.profileContent}>
            <Avatar sx={styles.avatar}>
              {user?.name?.charAt(0)?.toUpperCase()}
            </Avatar>
            <Typography variant="h5" sx={styles.userName}>
              {user?.name}
            </Typography>
            <Typography variant="body2" color="textSecondary">
              CRM Agent
            </Typography>
          </CardContent>
        </Card>

        <Card sx={styles.infoCard} elevation={2}>
          <CardContent>
            <Typography variant="h6" gutterBottom>
              Account Information
            </Typography>
            <List>
              <ListItem>
                <ListItemIcon>
                  <Email />
                </ListItemIcon>
                <ListItemText 
                  primary="Email" 
                  secondary={user?.email} 
                />
              </ListItem>
              <ListItem>
                <ListItemIcon>
                  <Person />
                </ListItemIcon>
                <ListItemText 
                  primary="Role" 
                  secondary="Agent" 
                />
              </ListItem>
            </List>
          </CardContent>
        </Card>

        <Card sx={styles.notificationCard} elevation={2}>
          <CardContent>
            <Typography variant="h6" gutterBottom>
              Notifications
            </Typography>
            <OneSignalStatus />
          </CardContent>
        </Card>

        <OneSignalDebug />

        <Card sx={styles.actionsCard} elevation={2}>
          <CardContent>
            <Typography variant="h6" gutterBottom>
              Actions
            </Typography>
            <List>
              <ListItem button onClick={() => {}}>
                <ListItemIcon>
                  <Settings />
                </ListItemIcon>
                <ListItemText primary="Settings" />
              </ListItem>
              <ListItem button onClick={() => {}}>
                <ListItemIcon>
                  <Info />
                </ListItemIcon>
                <ListItemText primary="About" />
              </ListItem>
            </List>
            <Divider sx={{ my: 2 }} />
            <Button
              variant="contained"
              color="error"
              fullWidth
              startIcon={<ExitToApp />}
              onClick={handleLogout}
              sx={styles.logoutButton}
            >
              Logout
            </Button>
          </CardContent>
        </Card>
      </Box>
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
  profileCard: {
    marginBottom: 2,
  },
  profileContent: {
    textAlign: 'center',
    padding: 3,
  },
  avatar: {
    width: 80,
    height: 80,
    margin: '0 auto 16px',
    backgroundColor: '#1976d2',
    fontSize: 32,
  },
  userName: {
    fontWeight: 'bold',
    marginBottom: 1,
  },
  infoCard: {
    marginBottom: 2,
  },
  notificationCard: {
    marginBottom: 2,
  },
  actionsCard: {
    marginBottom: 2,
  },
  logoutButton: {
    paddingY: 1.5,
  },
};