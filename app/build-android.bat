@echo off
echo ========================================
echo Building Android APK with OneSignal Fix
echo ========================================

echo.
echo Step 1: Installing dependencies...
call npm install

echo.
echo Step 2: Building React app...
call npm run build

echo.
echo Step 3: Adding Android platform (if not exists)...
call npx cap add android

echo.
echo Step 4: Syncing Capacitor...
call npx cap sync android

echo.
echo Step 5: Copying OneSignal configuration...
if not exist "android\app\src\main\res\values" mkdir "android\app\src\main\res\values"

echo ^<?xml version="1.0" encoding="utf-8"?^> > "android\app\src\main\res\values\onesignal.xml"
echo ^<resources^> >> "android\app\src\main\res\values\onesignal.xml"
echo     ^<string name="onesignal_app_id"^>ca751a15-6451-457b-aa3c-3b9a52eee8f6^</string^> >> "android\app\src\main\res\values\onesignal.xml"
echo     ^<string name="onesignal_google_project_number"^>XXXXXXX^</string^> >> "android\app\src\main\res\values\onesignal.xml"
echo ^</resources^> >> "android\app\src\main\res\values\onesignal.xml"

echo.
echo Step 6: Building APK...
cd android
call gradlew assembleDebug

echo.
echo ========================================
echo Build Complete!
echo APK Location: android\app\build\outputs\apk\debug\app-debug.apk
echo ========================================

pause