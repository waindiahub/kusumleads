import OneSignalService from "../services/OneSignalService";

let initializedOnce = false;

/**
 * Initialize OneSignal on app startup
 * - Prevents double initialization
 * - Initializes SDK (web + Android)
 * - Requests notification permission
 */
export const initOneSignal = async () => {
  if (initializedOnce) {
    console.log("OneSignal already initialized — skipping.");
    return;
  }

  try {
    console.log("🔵 Initializing OneSignal…");

    // Initialize SDK with error handling
    try {
      await OneSignalService.initialize();
    } catch (initError) {
      console.error("OneSignal SDK initialization error:", initError);
      // Continue even if initialization fails
    }

    // Ask permission (mobile + web) with error handling
    try {
      await OneSignalService.requestPermission();
    } catch (permissionError) {
      console.error("OneSignal permission request error:", permissionError);
      // Continue even if permission request fails
    }

    initializedOnce = true;
    console.log("🟢 OneSignal Initialization Complete");
  } catch (error) {
    console.error("❌ OneSignal Initialization Failed:", error);
    // Mark as initialized to prevent retry loops
    initializedOnce = true;
  }
};

const oneSignalInit = { initOneSignal };
export default oneSignalInit;
