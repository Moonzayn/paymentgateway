# Payment App - Android

Aplikasi Android untuk sistem pembayaran digital berbasis PHP.

## Persiapan

### 1. Install Java JDK 17
```bash
# Windows (dengan Chocolatey)
choco install openjdk17

# Atau download dari:
# https://adoptium.net/
```

### 2. Install Gradle 8.2
```bash
# Windows (dengan Chocolatey)
choco install gradle

# Atau download dari:
# https://gradle.org/install/
```

### 3. Setup Android SDK
```bash
# Install Android SDK Command Line Tools
# https://developer.android.com/studio#command-line-tools

# Set environment variable
export ANDROID_HOME=$HOME/Android/Sdk
export PATH=$PATH:$ANDROID_HOME/cmdline-tools/latest/bin:$ANDROID_HOME/platform-tools
```

## Build APK

```bash
cd android

# Build Debug APK
gradle assembleDebug

# Build Release APK
gradle assembleRelease
```

APK akan berada di: `app/build/outputs/apk/debug/app-debug.apk`

## Konfigurasi

### Mengubah Base URL
Edit file `gradle.properties` atau ubah di `app/build.gradle`:

```gradle
buildConfigField "String", "BASE_URL", '"http://192.168.1.x/payment/"'
```

Untuk emulator Android, gunakan:
```gradle
buildConfigField "String", "BASE_URL", '"http://10.0.2.2/payment/"'
```

## Fitur

- Login dengan username/password
- Verifikasi 2FA (TOTP)
- Tampilkan saldo
- POS Kasir dengan QRIS
- Pembayaran Tunai
- Chat dengan admin
- Deposit saldo
- Notifikasi

## Struktur Project

```
app/src/main/java/com/payment/app/
├── data/
│   ├── api/          # Retrofit API services
│   ├── model/        # Data models (DTO)
│   └── repository/   # Repository implementations
├── di/               # Hilt dependency injection
├── ui/
│   ├── auth/         # Login, 2FA
│   ├── main/         # Main dashboard
│   ├── pos/          # POS Kasir
│   ├── chat/         # Chat
│   └── deposit/      # Deposit
└── util/             # Helpers
```

## Lisensi

MIT License
