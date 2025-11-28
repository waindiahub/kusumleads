import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { 
  Card, 
  CardContent, 
  TextField, 
  Button, 
  Typography, 
  Box,
  Alert,
  CircularProgress
} from '@mui/material';
import { useAuth } from '../services/AuthService';

export default function LoginScreen() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const { login } = useAuth();
  const navigate = useNavigate();

  const handleLogin = async (e) => {
    e.preventDefault();

    if (!email || !password) {
      setError('Please enter both email and password');
      return;
    }

    setLoading(true);
    setError('');

    try {
      const result = await login(email, password);

      if (result.success) {
        // Request OneSignal permission on mobile after login
        if (window.Capacitor && window.Capacitor.isNativePlatform()) {
          if (window.plugins && window.plugins.OneSignal) {
            window.plugins.OneSignal.promptForPushNotificationsWithUserResponse((accepted) => {
              console.log('Push notification permission:', accepted);
            });
          }
        }
        
        navigate('/dashboard');
      } else {
        setError(result.message);
      }
    } catch (error) {
      setError(error.message || 'An unexpected error occurred');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Box sx={styles.container}>
      <Card sx={styles.card} elevation={4}>
        <CardContent sx={styles.cardContent}>
          <Typography variant="h4" sx={styles.title}>
            CRM Agent Login
          </Typography>
          <Typography variant="body1" sx={styles.subtitle}>
            Sign in to access your leads
          </Typography>

          <Box component="form" onSubmit={handleLogin} sx={styles.form}>
            <TextField
              label="Email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              variant="outlined"
              fullWidth
              sx={styles.input}
              autoComplete="email"
              required
            />

            <TextField
              label="Password"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              variant="outlined"
              fullWidth
              sx={styles.input}
              autoComplete="current-password"
              required
            />

            {error && (
              <Alert severity="error" sx={styles.error}>
                {error}
              </Alert>
            )}

            <Button
              type="submit"
              variant="contained"
              fullWidth
              disabled={loading}
              sx={styles.button}
              startIcon={loading && <CircularProgress size={20} color="inherit" />}
            >
              {loading ? 'Signing In...' : 'Sign In'}
            </Button>
          </Box>
        </CardContent>
      </Card>
    </Box>
  );
}

const styles = {
  container: {
    display: 'flex',
    justifyContent: 'center',
    alignItems: 'center',
    minHeight: '100vh',
    padding: 2.5,
    backgroundColor: '#f5f5f5',
  },
  card: {
    padding: 2.5,
    width: '100%',
    maxWidth: 400,
  },
  cardContent: {
    padding: '20px !important',
  },
  title: {
    textAlign: 'center',
    marginBottom: 1,
    fontSize: 24,
    fontWeight: 'bold',
  },
  subtitle: {
    textAlign: 'center',
    marginBottom: 4,
    color: '#666',
  },
  form: {
    width: '100%',
  },
  input: {
    marginBottom: 2,
  },
  button: {
    marginTop: 2,
    paddingY: 1.5,
    fontSize: 16,
    fontWeight: 'bold',
  },
  error: {
    marginBottom: 2,
  },
};
