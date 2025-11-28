/**
 * Android-specific OneSignal initialization and fixes
 * Handles timing issues and proper v5 API usage
 */

import OneSignalService from '../services/OneSignalService';

class AndroidOneSignalHelper {
  constructor() {
    this.initializationAttempts = 0;
    this.maxAttempts = 30;
    this.isAndroid = window.Capacitor && window.Capacitor.isNativePlatform();
  }

  /**
   * Wait for OneSignal plugin to be available
   */
  async waitForOneSignalPlugin() {
    if (!this.isAndroid) return false;

    return new Promise((resolve) => {
      const checkPlugin = () => {
        this.initializationAttempts++;
        
        if (window.plugins?.OneSignal) {
          console.log(`✅ OneSignal plugin found after ${this.initializationAttempts} attempts`);
          resolve(true);
        } else if (this.initializationAttempts < this.maxAttempts) {
          console.log(`⏳ Waiting for OneSignal plugin... (${this.initializationAttempts}/${this.maxAttempts})`);
          setTimeout(checkPlugin, 1000);
        } else {
          console.error('❌ OneSignal plugin not found after maximum attempts');
          resolve(false);
        }
      };
      
      checkPlugin();
    });
  }

  /**
   * Initialize OneSignal with proper Android handling
   */
  async initializeForAndroid() {
    if (!this.isAndroid) {
      console.log('Not Android platform, skipping Android initialization');
      return false;
    }

    console.log('🔵 Starting Android OneSignal initialization...');

    // Wait for plugin to be available
    const pluginAvailable = await this.waitForOneSignalPlugin();
    if (!pluginAvailable) {
      console.error('❌ OneSignal plugin not available, cannot initialize');
      return false;
    }

    try {
      // Initialize OneSignal service
      await OneSignalService.initialize();
      
      // Request permission
      const permissionGranted = await OneSignalService.requestPermission();
      console.log('🔔 Permission granted:', permissionGranted);

      return true;
    } catch (error) {
      console.error('❌ Android OneSignal initialization failed:', error);
      return false;
    }
  }

  /**
   * Register user after login with retry mechanism
   */
  async registerUserWithRetry(userId, maxRetries = 10) {
    if (!this.isAndroid || !userId) return false;

    console.log(`🔄 Starting user registration for Android: ${userId}`);

    for (let attempt = 1; attempt <= maxRetries; attempt++) {
      try {
        // Login user in OneSignal
        await OneSignalService.loginUser(userId);
        
        // Wait a bit for login to process
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        // Try to get player ID
        const playerId = await OneSignalService.getPlayerId();
        
        if (playerId) {
          console.log(`✅ Player ID found on attempt ${attempt}:`, playerId);
          
          // Register with backend
          await OneSignalService.registerPlayerIdWithBackend(userId);
          return true;
        } else {
          console.log(`⏳ No player ID yet, attempt ${attempt}/${maxRetries}`);
          
          if (attempt < maxRetries) {
            // Wait before retry
            await new Promise(resolve => setTimeout(resolve, 3000));
          }
        }
      } catch (error) {
        console.error(`❌ Registration attempt ${attempt} failed:`, error);
        
        if (attempt < maxRetries) {
          await new Promise(resolve => setTimeout(resolve, 2000));
        }
      }
    }

    console.error('❌ Failed to register user after all attempts');
    return false;
  }

  /**
   * Check and fix registration status
   */
  async checkAndFixRegistration(userId) {
    if (!this.isAndroid || !userId) return;

    try {
      // Check if player ID exists
      const playerId = await OneSignalService.getPlayerId();
      
      if (playerId) {
        console.log('🔍 Player ID exists, checking backend registration...');
        
        // Check backend registration
        const response = await fetch(
          `https://sandybrown-gull-863456.hostingersite.com/check_onesignal.php?user_id=${userId}`,
          { method: 'GET' }
        );
        
        const result = await response.json();
        
        if (!result.success || !result.data?.registered) {
          console.log('🔧 Player ID not registered in backend, fixing...');
          await OneSignalService.registerPlayerIdWithBackend(userId);
        } else {
          console.log('✅ OneSignal registration is complete');
        }
      } else {
        console.log('⚠️ No player ID found, attempting to register...');
        await this.registerUserWithRetry(userId, 5);
      }
    } catch (error) {
      console.error('❌ Error checking registration:', error);
    }
  }
}

const androidOneSignalHelper = new AndroidOneSignalHelper();
export default androidOneSignalHelper;