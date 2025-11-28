import React, { createContext, useContext, useState, useEffect } from 'react';
import { apiService } from './ApiService';
import OneSignalService from './OneSignalService';
import OneSignalMonitor from './OneSignalMonitor';
import AndroidOneSignalHelper from '../utils/android-onesignal';

const AuthContext = createContext();

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  // ============================================================
  // RESTORE LOGIN SESSION
  // ============================================================
  useEffect(() => {
    checkAuthState();
  }, []);

  const checkAuthState = async () => {
    try {
      const token = localStorage.getItem('authToken');
      const userData = localStorage.getItem('userData');

      if (token && userData) {
        try {
          const parsedUser = JSON.parse(userData);
          setUser(parsedUser);
          apiService.setAuthToken(token);

          // Auto login on OneSignal and ensure registration
          try {
            const isAndroid = window.Capacitor && window.Capacitor.isNativePlatform();
            
            if (isAndroid) {
              // Use Android-specific registration on app restart
              setTimeout(() => {
                AndroidOneSignalHelper.checkAndFixRegistration(parsedUser.id);
              }, 3000);
            } else {
              console.log('Web OneSignal disabled');
            }
            
            OneSignalMonitor.startMonitoring(parsedUser.id);
          } catch (error) {
            console.error('OneSignal initialization error:', error);
          }
        } catch (error) {
          console.error('Error parsing user data:', error);
          localStorage.removeItem('userData');
          localStorage.removeItem('authToken');
        }
      }
    } catch (error) {
      console.error('Error checking auth state:', error);
    } finally {
      setLoading(false);
    }
  };

  // ============================================================
  // LOGIN
  // ============================================================
  const login = async (email, password) => {
    try {
      const response = await apiService.post('/auth/login', { email, password });
      console.log('Login response:', response);

      if (response.success) {
        const { token, user: userData } = response.data;

        // Save session
        localStorage.setItem('authToken', token);
        localStorage.setItem('userData', JSON.stringify(userData));
        setUser(userData);
        apiService.setAuthToken(token);

        // OneSignal registration with Android optimization
        try {
          const isAndroid = window.Capacitor && window.Capacitor.isNativePlatform();
          
          if (isAndroid) {
            // Use Android-specific registration
            AndroidOneSignalHelper.registerUserWithRetry(userData.id).catch(console.error);
            
            setTimeout(() => {
              AndroidOneSignalHelper.checkAndFixRegistration(userData.id);
            }, 5000);
          } else {
            // Web fallback (disabled)
            console.log('Web OneSignal disabled');
          }
          
          OneSignalMonitor.startMonitoring(userData.id);
        } catch (error) {
          console.error('OneSignal login error:', error);
        }

        return { success: true };
      } else {
        return { success: false, message: response.message };
      }
    } catch (error) {
      console.error('Login error:', error);
      return { success: false, message: error.message || 'Login failed. Please try again.' };
    }
  };

  // ============================================================
  // LOGOUT
  // ============================================================
  const logout = async () => {
    try {
      // Stop OneSignal monitoring
      OneSignalMonitor.stopMonitoring();
      
      // Clear OneSignal ID from device
      OneSignalService.logoutUser();

      // Clear session
      localStorage.removeItem('authToken');
      localStorage.removeItem('userData');
      apiService.setAuthToken(null);
      setUser(null);
    } catch (error) {
      console.error('Error during logout:', error);
    }
  };

  // ============================================================
  // FINAL EXPORT
  // ============================================================
  const value = {
    user,
    login,
    logout,
    loading,
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};
