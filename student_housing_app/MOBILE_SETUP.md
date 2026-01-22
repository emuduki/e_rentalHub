# Mobile WebView Setup Guide

## ✅ Current Setup Status

The Flutter app now supports WebView on Android and iOS! The app is configured to:

### Desktop (Windows/Mac/Linux)
- Shows a message with the localhost URL
- User must open in their browser

### Mobile (Android/iOS)
- **Automatically loads the e_rentalHub website in a WebView**
- Handles loading states with a spinner
- Includes error handling

## 📱 Platform-Specific URLs

The app automatically detects the platform and uses the appropriate URL:

| Platform | URL | Notes |
|----------|-----|-------|
| **Android Emulator** | `http://10.0.2.2/e_rentalHub/` | Special address to reach host machine |
| **Android Physical Device** | `http://192.168.x.x/e_rentalHub/` | Replace with your machine's IP |
| **iOS Simulator** | `http://localhost/e_rentalHub/` | Accesses host machine directly |
| **iOS Physical Device** | `http://192.168.x.x/e_rentalHub/` | Replace with your machine's IP |
| **Windows/Mac/Linux** | Manual browser | User opens `http://localhost/e_rentalHub/` |

## 🚀 Testing on Android

### Option 1: Android Emulator (Easiest)

```bash
# Open Android Studio
# Tools > Device Manager > Create Virtual Device > Select API Level 21+

# Run on emulator
flutter run -d <emulator_id>

# Or list emulators first:
flutter emulators
flutter emulators launch Pixel_5_API_31
```

The app will automatically use `http://10.0.2.2/e_rentalHub/` for the emulator.

### Option 2: Physical Android Device

1. Enable USB Debugging on your phone
2. Connect via USB
3. Run:
   ```bash
   flutter run -d android
   ```
4. **Update the URL in `lib/screens/webview_screen.dart`:**
   - Find your machine's IP: `ipconfig` (look for IPv4 Address)
   - Update the Android section to use that IP:
   ```dart
   if (Platform.isAndroid) {
     return 'http://YOUR_MACHINE_IP/e_rentalHub/';
   }
   ```

## 🍎 Testing on iOS

### Option 1: iOS Simulator

```bash
# Open Xcode
# Xcode > Preferences > Components > Download iOS Simulator

# Run on simulator
open -a Simulator
flutter run -d ios
```

The app will automatically use `http://localhost/e_rentalHub/` for the simulator.

### Option 2: Physical iOS Device

1. Install Xcode Command Line Tools
2. Connect iPhone via USB
3. Trust the developer certificate on the phone
4. Run:
   ```bash
   flutter run -d ios
   ```
5. **Update the URL for physical device:**
   - Find your machine's IP: `ifconfig`
   - Update the iOS section to use that IP:
   ```dart
   if (Platform.isIOS) {
     return 'http://YOUR_MACHINE_IP/e_rentalHub/';
   }
   ```

## 🔧 Building for Release

### Android APK
```bash
flutter build apk --release
# Output: build/app/outputs/flutter-app.apk
```

### Android App Bundle (for Google Play)
```bash
flutter build appbundle --release
# Output: build/app/outputs/bundle/release/app-release.aab
```

### iOS App
```bash
flutter build ios --release
# Use Xcode to submit to App Store
```

## ⚠️ Important Notes

1. **XAMPP Must Be Running** - The PHP website must be running on your local machine
   ```bash
   # Start XAMPP
   # Windows: C:\xampp\xampp-control-panel.exe
   # Mac/Linux: sudo /applications/xampp/xamppfiles/bin/xampp start
   ```

2. **Firewall** - Ensure your firewall allows connections on port 80 (HTTP)

3. **SSL/HTTPS** - Currently uses HTTP. For production, add HTTPS and update URLs

4. **Network Setup** - For physical devices, you may need to:
   - Connect to the same WiFi as your development machine
   - Disable VPN if used
   - Check router port forwarding if accessing from outside network

## 🐛 Troubleshooting

### WebView not loading
- Verify XAMPP is running: `http://localhost/e_rentalHub/` in browser
- Check the correct URL for your platform
- Verify you're on the same network (for physical devices)

### Connection refused on physical device
- Update the IP address to your machine's actual IP
- Run `ipconfig` (Windows) or `ifconfig` (Mac/Linux)
- Example: `http://192.168.1.100/e_rentalHub/`

### Blank WebView
- Check browser console for errors
- Verify internet permissions in Android/iOS settings

## 📝 Next Steps

1. Test on Android/iOS emulator
2. Fix any URL issues for your network
3. Build release versions for distribution
4. Add native features (push notifications, camera, etc.) if needed
5. Configure HTTPS for production

## 💡 Tips

- Use `flutter run -d` to see all available devices
- Hot reload works for UI changes: Press `r` in terminal
- Use `flutter doctor` to check your setup
- Check `flutter logs` for WebView errors
