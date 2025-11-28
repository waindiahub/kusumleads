class OneSignalService {
  constructor() {
    this.initialized = false;
    this.appId = "ca751a15-6451-457b-aa3c-3b9a52eee8f6";
  }

  // ============================================================
  // INITIALIZE (ANDROID ONLY - FIXED)
  // ============================================================
  async initialize() {
    if (this.initialized) return;

    try {
      const isMobile = window.Capacitor && window.Capacitor.isNativePlatform();

      // -------------------- ANDROID / MOBILE --------------------
      if (isMobile) {
        return new Promise((resolve) => {
          let retries = 0;
          const maxRetries = 20;
          
          const tryInitialize = () => {
            const OS = window.plugins?.OneSignal;

            if (OS) {
              try {
                console.log("🔵 Initializing OneSignal (Android - v5 API)");

                // Initialize with app ID
                if (typeof OS.initialize === 'function') {
                  OS.initialize(this.appId);
                  console.log("✅ OneSignal initialized with app ID:", this.appId);
                } else {
                  console.warn("OneSignal initialize method not available");
                }

                // Set up notification handlers
                if (typeof OS.setNotificationOpenedHandler === 'function') {
                  OS.setNotificationOpenedHandler((result) => {
                    console.log("📱 Notification opened (Android):", result);
                    try {
                      this.handleNotificationClick(result?.notification?.additionalData);
                    } catch (err) {
                      console.error("Error handling notification click:", err);
                    }
                  });
                }

                if (typeof OS.setNotificationWillShowInForegroundHandler === 'function') {
                  OS.setNotificationWillShowInForegroundHandler((notificationReceivedEvent) => {
                    console.log("📱 Notification received in foreground:", notificationReceivedEvent);
                    notificationReceivedEvent.complete(notificationReceivedEvent.getNotification());
                  });
                }

                this.initialized = true;
                console.log("🟢 OneSignal initialization complete");
                resolve();
              } catch (initError) {
                console.error("❌ OneSignal initialization error:", initError);
                this.initialized = true;
                resolve();
              }
            } else if (retries < maxRetries) {
              retries++;
              console.log(`⏳ OneSignal plugin not ready, retry ${retries}/${maxRetries}`);
              setTimeout(tryInitialize, 1000);
            } else {
              console.warn("❌ OneSignal plugin not available after all retries");
              this.initialized = true;
              resolve();
            }
          };
          
          tryInitialize();
        });
      }

      // -------------------------- WEB --------------------------
      else {
        console.log("OneSignal web SDK disabled - mobile app only");
        this.initialized = true;
      }
    } catch (e) {
      console.error("OneSignal initialization error:", e);
      this.initialized = true;
    }
  }

  // ============================================================
  // LOGIN / LOGOUT (replaces externalUserId)
  // ============================================================
  async loginUser(userId) {
    if (!userId) {
      console.warn("OneSignal loginUser called without userId");
      return;
    }

    const isMobile = window.Capacitor && window.Capacitor.isNativePlatform();

    try {
      if (isMobile) {
        const OS = window.plugins?.OneSignal;
        if (OS && typeof OS.login === 'function') {
          OS.login(String(userId)); // NEW API
          console.log("OneSignal user login:", userId);
        } else {
          console.warn("OneSignal login method not available yet");
        }
      } else if (window.OneSignal && typeof window.OneSignal.login === 'function') {
        await window.OneSignal.login(String(userId));
        console.log("OneSignal user login:", userId);
      }
    } catch (err) {
      console.error("OneSignal login failed:", err);
      // Don't throw - allow app to continue
    }
  }

  async logoutUser() {
    const isMobile = window.Capacitor && window.Capacitor.isNativePlatform();

    try {
      if (isMobile) {
        const OS = window.plugins?.OneSignal;
        if (OS && typeof OS.logout === 'function') {
          OS.logout();
          console.log("OneSignal user logged out");
        } else {
          console.warn("OneSignal logout method not available");
        }
      } else if (window.OneSignal && typeof window.OneSignal.logout === 'function') {
        await window.OneSignal.logout();
        console.log("OneSignal user logged out");
      }
    } catch (err) {
      console.error("OneSignal logout failed:", err);
      // Don't throw - allow app to continue
    }
  }

  // ============================================================
  // REQUEST PERMISSION (FIXED FOR ANDROID)
  // ============================================================
  async requestPermission() {
    try {
      const isMobile = window.Capacitor && window.Capacitor.isNativePlatform();

      if (isMobile) {
        return new Promise((resolve) => {
          const OS = window.plugins?.OneSignal;
          if (OS) {
            // Try multiple permission methods for v5 compatibility
            if (typeof OS.promptForPushNotificationsWithUserResponse === 'function') {
              OS.promptForPushNotificationsWithUserResponse((accepted) => {
                console.log("🔔 Android permission (v5):", accepted);
                resolve(accepted);
              });
            } else if (typeof OS.Notifications && typeof OS.Notifications.requestPermission === 'function') {
              OS.Notifications.requestPermission((accepted) => {
                console.log("🔔 Android permission (v5 alt):", accepted);
                resolve(accepted);
              });
            } else {
              console.warn("OneSignal permission methods not available");
              resolve(false);
            }
          } else {
            console.warn("OneSignal plugin not available for permission");
            resolve(false);
          }
        });
      } else {
        console.log("Web OneSignal disabled - mobile only");
        return false;
      }
    } catch (e) {
      console.error("Permission request failed:", e);
      return false;
    }
  }

  // ============================================================
  // GET PLAYER ID (FIXED FOR v5 ANDROID)
  // ============================================================
  async getPlayerId() {
    try {
      const isMobile = window.Capacitor && window.Capacitor.isNativePlatform();

      // ------------- ANDROID (v5 API) --------------
      if (isMobile) {
        return new Promise((resolve) => {
          const checkForPlayerId = () => {
            const OS = window.plugins?.OneSignal;
            
            if (OS && OS.User && OS.User.pushSubscription) {
              try {
                // Try multiple methods for v5 compatibility
                if (typeof OS.User.pushSubscription.getId === 'function') {
                  OS.User.pushSubscription.getId((id) => {
                    console.log("Android Player ID (method 1):", id);
                    resolve(id || null);
                  });
                } else if (typeof OS.User.pushSubscription.id !== 'undefined') {
                  const id = OS.User.pushSubscription.id;
                  console.log("Android Player ID (method 2):", id);
                  resolve(id || null);
                } else if (typeof OS.getDeviceState === 'function') {
                  OS.getDeviceState((state) => {
                    const id = state?.userId || state?.playerId;
                    console.log("Android Player ID (method 3):", id);
                    resolve(id || null);
                  });
                } else {
                  console.warn("No available method to get Player ID");
                  resolve(null);
                }
              } catch (err) {
                console.error("Error getting Player ID:", err);
                resolve(null);
              }
            } else {
              console.warn("OneSignal User API not available yet");
              resolve(null);
            }
          };
          
          // Check immediately
          checkForPlayerId();
        });
      }

      // ---------------- WEB ----------------
      console.log("Web OneSignal disabled - mobile only");
    } catch (e) {
      console.error("getPlayerId error:", e);
    }

    return null;
  }

  // ============================================================
  // REGISTER PLAYER ID WITH BACKEND
  // ============================================================
  async registerPlayerIdWithBackend(userId) {
    // Prevent duplicate registration attempts
    const registrationKey = `onesignal_registration_${userId}`;
    const isRegistering = sessionStorage.getItem(registrationKey);
    
    if (isRegistering === 'true') {
      console.log("⏸️ OneSignal registration already in progress for user:", userId);
      return;
    }
    
    sessionStorage.setItem(registrationKey, 'true');
    
    const maxRetries = 20; // Increased retries for better reliability
    const retryDelay = 2000; // 2 seconds between retries
    
    const attemptRegistration = async (attempt) => {
      console.log(`🔍 Attempting OneSignal registration (${attempt}/${maxRetries})`);
      
      const playerId = await this.getPlayerId();
      
      if (playerId) {
        console.log("✅ OneSignal Player ID found:", playerId);
        
        try {
          // Use the same API base URL as ApiService
          const apiBaseUrl = 'https://sandybrown-gull-863456.hostingersite.com';
          const response = await fetch(
            `${apiBaseUrl}/register_onesignal.php`,
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                user_id: userId,
                player_id: playerId,
              }),
            }
          );
          
          const result = await response.json();
          console.log("📡 Backend registration result:", result);
          
          if (result.success) {
            console.log("🎉 OneSignal Player ID successfully registered!");
            sessionStorage.removeItem(registrationKey);
            localStorage.removeItem('pendingOneSignalRegistration');
            return true;
          } else {
            console.error("❌ Backend registration failed:", result.message);
          }
        } catch (error) {
          console.error("❌ Network error during registration:", error);
        }
      } else {
        console.log(`⏳ Player ID not ready yet (attempt ${attempt})`);
      }
      
      // Retry if not successful and attempts remaining
      if (attempt < maxRetries) {
        setTimeout(() => attemptRegistration(attempt + 1), retryDelay);
      } else {
        console.error("❌ Failed to register OneSignal Player ID after all retries");
        sessionStorage.removeItem(registrationKey);
        // Store failed registration for later retry
        localStorage.setItem('pendingOneSignalRegistration', userId.toString());
      }
    };
    
    // Start registration attempts immediately
    attemptRegistration(1);
  }

  // ============================================================
  // RETRY PENDING REGISTRATION
  // ============================================================
  async retryPendingRegistration() {
    const pendingUserId = localStorage.getItem('pendingOneSignalRegistration');
    
    if (pendingUserId) {
      console.log("🔄 Retrying pending OneSignal registration for user:", pendingUserId);
      
      const playerId = await this.getPlayerId();
      
      if (playerId) {
        try {
          // Use the same API base URL as ApiService
          const apiBaseUrl = 'https://sandybrown-gull-863456.hostingersite.com';
          const response = await fetch(
            `${apiBaseUrl}/register_onesignal.php`,
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                user_id: pendingUserId,
                player_id: playerId,
              }),
            }
          );
          
          const result = await response.json();
          
          if (result.success) {
            console.log("🎉 Pending OneSignal registration completed!");
            localStorage.removeItem('pendingOneSignalRegistration');
            return true;
          }
        } catch (error) {
          console.error("❌ Retry registration failed:", error);
        }
      }
    }
    
    return false;
  }

  // ============================================================
  // HANDLE NOTIFICATION CLICK
  // ============================================================
  handleNotificationClick(data) {
    if (data?.lead_id) {
      window.location.href = `/leads/${data.lead_id}`;
    }
  }

  // ============================================================
  // CHECK AND ENSURE REGISTRATION
  // ============================================================
  async ensureRegistration(userId) {
    if (!userId) {
      console.warn("ensureRegistration called without userId");
      return;
    }

    try {
      // First try to retry any pending registration
      const retrySuccess = await this.retryPendingRegistration();
      
      if (!retrySuccess) {
        // If no pending registration or retry failed, start new registration
        this.registerPlayerIdWithBackend(userId);
      }
    } catch (error) {
      console.error("Error in ensureRegistration:", error);
      // Don't throw - allow app to continue
    }
  }
}

const oneSignalService = new OneSignalService();

// Auto-retry pending registrations when app becomes active
if (typeof document !== 'undefined') {
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      oneSignalService.retryPendingRegistration();
    }
  });
}

export default oneSignalService;
