import OneSignalService from './OneSignalService';

class OneSignalMonitor {
  constructor() {
    this.monitorInterval = null;
    this.isMonitoring = false;
  }

  // Start monitoring OneSignal registration status
  startMonitoring(userId) {
    if (this.isMonitoring) {
      this.stopMonitoring();
    }

    console.log('🔍 Starting OneSignal registration monitor for user:', userId);
    
    this.isMonitoring = true;
    
    // Check immediately
    this.checkRegistration(userId);
    
    // Then check every 5 minutes
    this.monitorInterval = setInterval(() => {
      this.checkRegistration(userId);
    }, 5 * 60 * 1000); // 5 minutes
  }

  // Stop monitoring
  stopMonitoring() {
    if (this.monitorInterval) {
      clearInterval(this.monitorInterval);
      this.monitorInterval = null;
    }
    this.isMonitoring = false;
    console.log('⏹️ OneSignal registration monitor stopped');
  }

  // Check if registration is needed
  async checkRegistration(userId) {
    try {
      // Check if there's a pending registration
      const pendingUserId = localStorage.getItem('pendingOneSignalRegistration');
      
      if (pendingUserId && pendingUserId === userId.toString()) {
        console.log('🔄 Found pending OneSignal registration, attempting to complete...');
        
        const success = await OneSignalService.retryPendingRegistration();
        
        if (success) {
          console.log('✅ Pending OneSignal registration completed successfully');
        }
      } else {
        // Check if user has player ID but not registered in backend
        const playerId = await OneSignalService.getPlayerId();
        
        if (playerId) {
          // Verify with backend
          try {
            // Use the same API base URL as ApiService
            const apiBaseUrl = 'https://sandybrown-gull-863456.hostingersite.com';
            const response = await fetch(
              `${apiBaseUrl}/check_onesignal.php?user_id=${userId}`,
              { method: 'GET' }
            );
            
            const result = await response.json();
            
            if (result.success && !result.data.registered) {
              console.log('🔧 OneSignal Player ID exists but not registered in backend, fixing...');
              OneSignalService.registerPlayerIdWithBackend(userId);
            }
          } catch (error) {
            console.error('Error checking backend registration:', error);
          }
        }
      }
    } catch (error) {
      console.error('Error in OneSignal registration check:', error);
    }
  }

  // Manual trigger for registration check
  async triggerCheck(userId) {
    console.log('🔄 Manual OneSignal registration check triggered');
    await this.checkRegistration(userId);
  }
}

const oneSignalMonitor = new OneSignalMonitor();
export default oneSignalMonitor;