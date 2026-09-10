# eTraining Chat Android APK

Single Android app for agents: WhatsApp inbox (`/back/chat`) plus an **in-app Maqsam dialer** (no browser popup). Screenshots and screen recording of the app are blocked via `FLAG_SECURE`.

## Requirements

- Node.js 18+
- JDK 17
- Android SDK (`ANDROID_HOME`, build-tools 33+)

## Configure server URL

Default Capacitor server URL opens chat directly (chat-only shell):

`https://prod.jasarah-ksa.com/back/chat?source=chat-app`

Login still appears when needed; after OTP the APK lands on chat (not the platform dashboard). Navigation outside chat/login/caller is redirected back to chat.
## Build

```bash
cd mobile
npm install
npx cap sync android
```

### Debug APK (sideload)

```bash
npm run build:debug
cp android/app/build/outputs/apk/debug/app-debug.apk ../backend/public/eTraining-Chat-debug.apk
```

### Release APK (signed)

1. Copy signing template and fill in values (or generate a new keystore):

```bash
cp signing/key.properties.example signing/key.properties
keytool -genkeypair -v \
  -keystore signing/etraining-chat.jks \
  -alias etraining-chat \
  -keyalg RSA -keysize 2048 -validity 10000
```

2. Put the keystore path, alias, and passwords in `signing/key.properties`.

3. Build and publish to the web `public/` folder:

```bash
npm run build:release
cp android/app/build/outputs/apk/release/app-release.apk ../backend/public/eTraining-Chat-release.apk
```

### Download URLs (after deploy)

- Release: `https://prod.jasarah-ksa.com/eTraining-Chat-release.apk`
- Debug: `https://prod.jasarah-ksa.com/eTraining-Chat-debug.apk`

Locally (with `php artisan serve` / your usual host):

- `/eTraining-Chat-release.apk`
- `/eTraining-Chat-debug.apk`

## Agent usage

1. Open the app → it opens **Chat** (or login, then Chat). You will not see the platform dashboard.
2. Tap **Call** on a conversation:
   - First tap opens the **in-app Maqsam dialer** (autologin). Keep it online; use **Minimize** to return to chat without killing the dialer session.
   - Later taps place the call via Maqsam API while the dialer session stays alive.
3. Your eTraining user **email must match** your Maqsam agent email.

## Deploy note

The APK loads the **live site**. The Capacitor/native dialer JS path lives in the Laravel frontend (`MaqsamDialerPanel.vue`). Deploy those backend frontend changes before relying on in-app calling; until then the shell still opens login/chat but call may fall back incorrectly.

After frontend deploy:

1. Install the APK
2. Log in
3. Open Chat → Call → grant microphone → Maqsam dialer opens **inside the app**
4. Minimize dialer → chat remains usable; dialer session stays alive

## Security notes

- `FLAG_SECURE` blocks normal screenshots, Recents previews, and most screen recorders. Rooted devices / ADB can still bypass this.
- App backup is disabled (`allowBackup=false`).
- Do not commit `signing/*.jks` or `signing/key.properties`.
