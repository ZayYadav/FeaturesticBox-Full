# FeaturesticBox SDK — Complete Developer Integration Guide

`FeaturesticBox` is a universal Android 10–16 (API 29–36) Virtualization & Game Loader Engine supporting **32-bit (`armeabi-v7a`, `x86`)**, **64-bit (`arm64-v8a`, `x86_64`)**, and **Universal Multi-ABI** operation, **Zero-Copy + Auto-Sync OBB/Data**, **Keyless Testing Mode**, and **Multi-Platform Login (Google, Facebook, Twitter/X, Discord, WhatsApp, VK, LINE, Kakao, Telegram, WeChat, QQ, Garena, Apple, Microsoft/Xbox)**.

## 1. Gradle Setup

In `settings.gradle`:
```groovy
include ':app', ':FeaturesticBox'
```

In your host app's `app/build.gradle`:
```groovy
dependencies {
    implementation project(':FeaturesticBox')
}
```

## 2. Building AARs (Universal / 64-Bit / 32-Bit)

- **Universal AAR (32-bit + 64-bit + Emulator, Default):**
  ```bash
  ./gradlew :FeaturesticBox:assembleRelease
  ```
- **64-Bit Only AAR (`arm64-v8a`):**
  ```bash
  ./gradlew :FeaturesticBox:assembleRelease -PabiMode=64
  ```
- **32-Bit Only AAR (`armeabi-v7a`):**
  ```bash
  ./gradlew :FeaturesticBox:assembleRelease -PabiMode=32
  ```

## 3. Initializing `FeaturesticBoxCore` in Your Host `Application`

```kotlin
import android.app.Application
import android.content.Context
import com.featurestic.box.FeaturesticBoxCore
import com.featurestic.box.app.configuration.ClientConfiguration

class MyLoaderApp : Application() {
    override fun attachBaseContext(base: Context) {
        super.attachBaseContext(base)
        FeaturesticBoxCore.get().doAttachBaseContext(base, object : ClientConfiguration() {
            override fun getHostPackageName(): String = base.packageName
            override fun isHideRoot(): Boolean = true
            override fun isHideXposed(): Boolean = true
        })
    }

    override fun onCreate() {
        super.onCreate()
        FeaturesticBoxCore.get().doCreate()
    }
}
```

## 4. Installing & Launching Games (with Auto OBB/Data Sync)

```kotlin
val core = FeaturesticBoxCore.get()
val userId = 0
val gamePkg = "com.pubg.imobile"

// 1. Install from real phone (or pass File("/sdcard/game.apk"))
if (!core.isInstalled(gamePkg, userId)) {
    core.installPackageAsUser(gamePkg, userId)
}

// 2. Launch inside FeaturesticBox (OBB & Data sync happens automatically on launch)
core.launchApk(gamePkg, userId)
```

## 5. Loader UI Power APIs (Cache Clean, Storage Size, Guest Account Backup & Restore)

```kotlin
val core = FeaturesticBoxCore.get()
val userId = 0
val pkg = "com.pubg.imobile"

// Clear virtual app cache (internal + code_cache + external)
core.clearAppCache(pkg, userId)

// Get total storage used by virtual app (in bytes)
val sizeBytes: Long = core.getAppStorageSize(pkg, userId)

// Backup guest account & settings to a ZIP file
val backupZip = File(filesDir, "backups/${pkg}_u${userId}.zip")
val saved: Boolean = core.backupAppData(pkg, userId, backupZip)

// Restore guest account & settings from a ZIP file
val restored: Boolean = core.restoreAppData(pkg, userId, backupZip)

// Reset virtual device identity (Android ID / Serial / IMEI)
val newDeviceId: String = FeaturesticBoxCore.resetVirtualDeviceIdentity()
```

## 6. Custom Native `.so` Plugin Injection (32-Bit & 64-Bit Aware)

- Place 64-bit plugins in: `<host_no_backup_files>/native/arm64-v8a/libPlugin.so`
- Place 32-bit plugins in: `<host_no_backup_files>/native/armeabi-v7a/libPlugin.so`
- Or load on demand:
  ```kotlin
  FeaturesticBoxCore.loadCustomLibrary("libMyPlugin.so")
  ```
  `RNative` automatically verifies ELF bitness (`EI_CLASS` byte 4) so a 32-bit `.so` never crashes a 64-bit process and vice-versa.
