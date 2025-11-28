import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.sushma.app',
  appName: 'Kusum CRM',
  webDir: 'build',

  plugins: {
    SplashScreen: {
      launchShowDuration: 2000,
      launchAutoHide: true,
      backgroundColor: '#667eea',
      androidSplashResourceName: 'splash',
      androidScaleType: 'CENTER_CROP',
      showSpinner: false,
      splashFullScreen: true,
      splashImmersive: true
    },

    // ✅ Required for Capacitor Push Notifications
    PushNotifications: {
      presentationOptions: ["badge", "sound", "alert"]
    },

    // ⭐ REQUIRED FOR ONESIGNAL + ANDROID PUSH ⭐
    OneSignalPlugin: {
      androidSupportV4: true,
      smallIcon: "ic_stat_onesignal_default",   // show notification icon
      inFocusDisplaying: 2                      // show notifications in foreground
    }
  },

  // Fix Android WebView secure origin
  server: {
    androidScheme: 'https'
  }
};

export default config;
