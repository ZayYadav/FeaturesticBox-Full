# FeaturesticBox Cloud Control Center (iOS 18 Cupertino PHP Edition)

## 1. Quick Upload & Setup (cPanel / Hostinger / VPS / XAMPP)
1. Extract `FeaturesticBox-PHP-Panel.zip` and upload the contents of `FeaturesticBox-Panel/` (`index.php`, `api.php`, `config.php`, `db.php`, `crypto.php`, `.htaccess`, `storage/`) to your web server (e.g., `https://your-domain.com/panel/`).
2. Ensure the `storage/` folder is writable (`chmod 755 storage`). By default, `config.php` uses **zero-config SQLite3** (`storage/featurestic_panel.sqlite` is created and seeded automatically on first load).
   - If you prefer MySQL/MariaDB, change `FBOX_DB_DRIVER` to `'mysql'` in `config.php` and import `schema.sql`.
3. Open `https://your-domain.com/panel/index.php` in your browser:
   - **Default Username:** `admin`
   - **Default Password:** `admin123`
   *(Change your password immediately under the **SDK & Endpoint Security** tab).*

## 2. Connecting Your Android Loader (`BoxApplication.java` & `LogAct.java`)
In `BoxApplication.onCreate()`:
```java
com.featurestic.box.FeaturesticSdkConfig.create()
        .setServerGatewayUrl("https://your-domain.com/panel/api.php")
        .setServerApiSecret("FBOX_IOS_PANEL_DEFAULT_SECRET_2026")
        .setAutoBanOnTamper(true)
        .requireLoginGate(true)
        .apply();
```

In your Login Activity (`LogAct.java`) when the user taps Login:
```java
com.featurestic.box.FeaturesticSdkConfig.authenticateWithPanel(
        this,
        enteredLicenseKey,
        (success, status, message, runtimeParams) -> {
            if (success) {
                // Session lease unlocked & AES-256 payload decrypted in RAM!
                startActivity(new Intent(this, MAct.class));
                finish();
            } else {
                Toast.makeText(this, message, Toast.LENGTH_LONG).show();
            }
        }
);
```
