package com.featurestic.box;

import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageInfo;
import android.content.pm.PackageManager;
import android.content.pm.Signature;
import android.content.pm.SigningInfo;
import android.os.Build;
import android.os.Debug;
import android.os.Handler;
import android.os.Looper;
import android.os.Process;
import android.util.Base64;
import android.widget.Toast;

import com.featurestic.box.core.RNative;
import com.featurestic.box.core.env.BEnvironment;
import com.featurestic.box.utils.FileUtils;

import java.io.BufferedReader;
import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.FileReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.InetAddress;
import java.net.InetSocketAddress;
import java.net.Socket;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.SecureRandom;
import java.security.cert.Certificate;
import java.util.Collections;
import java.util.HashMap;
import java.util.Locale;
import java.util.Map;

import javax.crypto.Cipher;
import javax.crypto.Mac;
import javax.crypto.spec.IvParameterSpec;
import javax.crypto.spec.SecretKeySpec;
import javax.net.ssl.HttpsURLConnection;

/**
 * Server-Driven Anti-Crack Vault, Challenge-Response Nonce & Rolling Token Enforcer,
 * Continuous Anti-Debug / Anti-Hook / Anti-Proxy Shield, TLS Certificate Pinning,
 * HWID + IP Device Ban Enforcer & AES-256-CBC / HMAC-SHA256 Encrypted Payload Receiver.
 */
public final class FeaturesticAntiCrackVault {
    public static final String EXTRA_LAUNCH_HMAC_PROOF = "_fbox_launch_hmac_proof_";
    public static final String EXTRA_LAUNCH_NONCE = "_fbox_launch_nonce_";

    public interface PanelAuthCallback {
        void onResult(boolean success, String status, String message, Map<String, String> runtimeParams);
    }

    public interface BanCheckCallback {
        void onResult(boolean isBanned, String status, String message);
    }

    private static final SecureRandom SECURE_RANDOM = new SecureRandom();
    private static final byte[] BOOT_SECRET = new byte[32];
    private static volatile boolean sRequireLoaderLoginGate = false;
    private static volatile boolean sAutoBanOnTamper = true;
    private static volatile boolean sBlockHttpProxy = true;
    private static volatile boolean sBlockRootMagisk = false;
    private static volatile boolean sBlockEmulator = false;
    private static volatile String sLockedReleaseCertSha256 = "";
    private static volatile String sPinnedTlsCertSha256 = "";
    private static final java.util.List<String> sServerAllowedGames = new java.util.concurrent.CopyOnWriteArrayList<String>();
    private static volatile String sAuthorizedPluginSha256 = "";

    public static void setServerAllowedGames(java.util.List<String> games) {
        sServerAllowedGames.clear();
        if (games != null) {
            for (String g : games) {
                if (g != null && !g.trim().isEmpty()) {
                    sServerAllowedGames.add(g.trim().toLowerCase(Locale.US));
                }
            }
        }
    }

    public static void setAuthorizedPluginSha256(String sha256) {
        sAuthorizedPluginSha256 = sha256 != null ? sha256.trim().toUpperCase(Locale.US) : "";
    }

    public static boolean isGamePackageAuthorized(String packageName) {
        if (packageName == null || packageName.trim().isEmpty()) return false;
        if (sServerAllowedGames.isEmpty()) {
            return !sRequireLoaderLoginGate;
        }
        if (sServerAllowedGames.contains("*")) {
            return true;
        }
        String cleanPkg = packageName.trim().toLowerCase(Locale.US);
        return sServerAllowedGames.contains(cleanPkg);
    }

    public static boolean isPluginBytesAuthorized(byte[] elfBytes) {
        if (elfBytes == null || elfBytes.length < 20) return false;
        if (sAuthorizedPluginSha256 == null || sAuthorizedPluginSha256.isEmpty()) {
            return false;
        }
        try {
            MessageDigest md = MessageDigest.getInstance("SHA-256");
            byte[] hash = md.digest(elfBytes);
            StringBuilder sb = new StringBuilder();
            for (byte b : hash) {
                sb.append(String.format(Locale.US, "%02X", b));
            }
            return constantTimeEquals(sAuthorizedPluginSha256, sb.toString());
        } catch (Throwable t) {
            return false;
        }
    }

    public static boolean verifyAppPluginsIntegrity(String packageName) {
        try {
            File appLibDir = BEnvironment.getAppLibDir(packageName);
            if (appLibDir != null && appLibDir.isDirectory()) {
                File[] libs = appLibDir.listFiles();
                if (libs != null) {
                    for (File f : libs) {
                        if (f != null && f.isFile() && f.getName().contains("Plugin") && f.getName().endsWith(".so")) {
                            if (sAuthorizedPluginSha256 == null || sAuthorizedPluginSha256.isEmpty()) {
                                f.delete();
                                return false;
                            }
                            String sha = computeFileSha256Hex(f);
                            if (!constantTimeEquals(sAuthorizedPluginSha256, sha)) {
                                f.delete();
                                return false;
                            }
                        }
                    }
                }
            }
            File sysDir = BEnvironment.getSystemDir();
            if (sysDir != null && sysDir.isDirectory()) {
                File sysPlugin = new File(sysDir, "libFeaturesticPlugin.so");
                if (sysPlugin.isFile()) {
                    if (sAuthorizedPluginSha256 == null || sAuthorizedPluginSha256.isEmpty()) {
                        sysPlugin.delete();
                        return false;
                    }
                    String sha = computeFileSha256Hex(sysPlugin);
                    if (!constantTimeEquals(sAuthorizedPluginSha256, sha)) {
                        sysPlugin.delete();
                        return false;
                    }
                }
            }
        } catch (Throwable ignored) {
        }
        return true;
    }

    public static String computeFileSha256Hex(File file) {
        if (file == null || !file.isFile()) return "";
        try (FileInputStream fis = new FileInputStream(file)) {
            MessageDigest md = MessageDigest.getInstance("SHA-256");
            byte[] buf = new byte[8192];
            int n;
            while ((n = fis.read(buf)) > 0) {
                md.update(buf, 0, n);
            }
            byte[] hash = md.digest();
            StringBuilder sb = new StringBuilder();
            for (byte b : hash) {
                sb.append(String.format(Locale.US, "%02X", b));
            }
            return sb.toString();
        } catch (Throwable ignored) {
            return "";
        }
    }

    private static volatile String sServerApiSecret = "FBOX_IOS_PANEL_DEFAULT_SECRET_2026";
    public static final String SERVER_RSA_PUBLIC_KEY_B64 =
            "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAq71MvH0w4mgAEzduObi3YO4fSuDQpIO8ury6lK6Ld/05PwWFS2/X4y/8/pqQm0m9QsdcXdh7gcUjbdHXDqFSgrQ13QOanR+U6fh5hwZd3T4EJ1qZDrqrZgYZHUhCAPJWuFCWuXfRWz9j/1f1/0Y1aONsJCldiz5/GvTgcVU5KfJlroTJf0S1OUOsNn7WI7TUQbwzzhvHT344pJqXf8nCF5ycGJ2oZ+7aSx5826qT5SGNZVnkmmfP2t9cdq0QHd6dt/VKtOofepLVJ2MPVqfXJI/68udirJ/Fd8dXNudDWSkmYBBPplFEfNomJuo3c1Y3/6P3jgA+2V10/EOkbfuocwIDAQAB";

    public static boolean verifyRsaSha256(String data, String base64Sig, String base64PubKey) {
        if (data == null || base64Sig == null || base64Sig.isEmpty() || base64PubKey == null || base64PubKey.isEmpty()) {
            return false;
        }
        try {
            byte[] pubBytes = Base64.decode(base64PubKey, Base64.DEFAULT);
            java.security.spec.X509EncodedKeySpec spec = new java.security.spec.X509EncodedKeySpec(pubBytes);
            java.security.KeyFactory kf = java.security.KeyFactory.getInstance("RSA");
            java.security.PublicKey pubKey = kf.generatePublic(spec);
            java.security.Signature sig = java.security.Signature.getInstance("SHA256withRSA");
            sig.initVerify(pubKey);
            sig.update(data.getBytes(StandardCharsets.UTF_8));
            byte[] sigBytes = Base64.decode(base64Sig, Base64.DEFAULT);
            return sig.verify(sigBytes);
        } catch (Throwable t) {
            return false;
        }
    }

    private static volatile String sActiveSessionProof = "";
    private static volatile String sActiveUserTokenHash = "DEFAULT_DEV_SESSION";
    private static volatile String sActiveSessionToken = "";
    private static volatile String sActiveRollingToken = "";
    private static volatile String sServerVerifiedSeal = "";
    private static volatile String sActiveLicenseKey = "";
    private static volatile String sLoaderId = "default";
    private static volatile String sEmbeddedSdkLicenseKey = "";
    private static volatile boolean sHoneypotModeActive = false;
    private static volatile boolean sSpoofPlayInstaller = true;

    public static void setSpoofPlayInstallerEnabled(boolean enabled) {
        sSpoofPlayInstaller = enabled;
        try {
            RNative.setNativeProp("debug.featurestic.play_installer", enabled ? "com.android.vending" : "");
        } catch (Throwable ignored) {}
    }

    public static boolean isSpoofPlayInstallerEnabled() {
        return sSpoofPlayInstaller;
    }

    public static void setEmbeddedSdkLicenseKey(String sdkKey) {
        if (sdkKey != null && !sdkKey.trim().isEmpty()) {
            sEmbeddedSdkLicenseKey = sdkKey.trim();
        }
    }

    public static String getEmbeddedSdkLicenseKey() {
        return sEmbeddedSdkLicenseKey;
    }

    public static void setHoneypotModeActive(boolean active) {
        sHoneypotModeActive = active;
    }

    public static boolean isHoneypotModeActive() {
        return sHoneypotModeActive;
    }

    public static void setLoaderId(String loaderId) {
        if (loaderId != null && !loaderId.trim().isEmpty()) {
            sLoaderId = loaderId.trim();
        }
    }

    public static String getLoaderId() {
        return sLoaderId;
    }
    private static volatile long sSessionLeaseExpiresAtMs = 0L;
    private static volatile long sLastSuccessfulHeartbeatMs = 0L;
    private static volatile long sMatchGraceToleranceMs = 300_000L;
    private static volatile boolean sDeviceBanned = false;
    private static volatile String sDeviceBanReason = "";
    private static final Map<String, String> sDecryptedRuntimeParams =
            Collections.synchronizedMap(new HashMap<String, String>());

    static {
        SECURE_RANDOM.nextBytes(BOOT_SECRET);
        sActiveSessionProof = computeHmacHex("BOOT_INIT|" + Process.myPid(), sActiveUserTokenHash);
        enforceProcessNonDumpableShield();
    }

    /**
     * Prevents external root/non-root memory dumpers (ue4dumper, memdumper, GameGuardian)
     * from reading /proc/self/mem or attaching ptrace by setting PR_SET_DUMPABLE = 0.
     */
    public static void enforceProcessNonDumpableShield() {
        try {
            Class<?> osCls = Class.forName("android.system.Os");
            java.lang.reflect.Method prctl = osCls.getMethod("prctl", int.class, long.class, long.class, long.class, long.class);
            prctl.invoke(null, 1 /* PR_SET_DUMPABLE */, 0L, 0L, 0L, 0L);
        } catch (Throwable ignored) {
        }
    }

    private FeaturesticAntiCrackVault() {
    }

    private static File getBanSealFile() {
        return new File(BEnvironment.getSystemDir(), "featurestic_device_ban.seal");
    }

    private static File getSessionCacheFile() {
        return new File(BEnvironment.getSystemDir(), "featurestic_session_lease.dat");
    }

    public static void setServerApiSecret(String secret) {
        if (secret != null && !secret.trim().isEmpty()) {
            sServerApiSecret = secret.trim();
        }
    }

    public static String getServerApiSecret() {
        return sServerApiSecret;
    }

    public static void setAutoBanOnTamper(boolean enabled) {
        sAutoBanOnTamper = enabled;
    }

    public static boolean isAutoBanOnTamperEnabled() {
        return sAutoBanOnTamper;
    }

    public static void setBlockHttpProxy(boolean block) {
        sBlockHttpProxy = block;
    }

    public static void setBlockRootMagisk(boolean block) {
        sBlockRootMagisk = block;
    }

    public static void setBlockEmulator(boolean block) {
        sBlockEmulator = block;
    }

    public static void pinServerCertificateSha256(String certSha256Hex) {
        if (certSha256Hex == null || certSha256Hex.trim().isEmpty()) {
            sPinnedTlsCertSha256 = "";
            return;
        }
        sPinnedTlsCertSha256 = certSha256Hex.trim().replace(":", "").toUpperCase(Locale.US);
    }

    public static String getPinnedServerCertificateSha256() {
        return sPinnedTlsCertSha256;
    }

    public static void lockReleaseSignatureSha256(String expectedSha256Hex) {
        if (expectedSha256Hex == null || expectedSha256Hex.trim().isEmpty()) {
            sLockedReleaseCertSha256 = "";
            return;
        }
        sLockedReleaseCertSha256 = expectedSha256Hex.trim().replace(":", "").toUpperCase(Locale.US);
    }

    public static String getLockedReleaseSignatureSha256() {
        return sLockedReleaseCertSha256;
    }

    public static void requireLoaderLoginGate(boolean required) {
        sRequireLoaderLoginGate = required;
        try {
            RNative.setNativeProp("debug.featurestic.login_gate", "0");
        } catch (Throwable ignored) {
        }
    }

    public static boolean isLoaderLoginGateRequired() {
        return sRequireLoaderLoginGate;
    }

    /**
     * Generates a fresh 128-bit cryptographic random hex nonce for challenge-response signing.
     */
    public static String generateRandomNonceHex() {
        byte[] rnd = new byte[16];
        SECURE_RANDOM.nextBytes(rnd);
        return bytesToHex(rnd);
    }

    /**
     * Generates a deterministic 64-character SHA-256 Hardware ID (HWID) for this device.
     */
    public static String getHardwareDeviceId(Context context) {
        Context ctx = context != null ? context : FeaturesticBoxCore.getContext();
        String pkg = ctx != null ? ctx.getPackageName() : "";
        String androidId = "";
        try {
            if (ctx != null && ctx.getContentResolver() != null) {
                Class<?> secCls = Class.forName("android.provider.Settings$Secure");
                java.lang.reflect.Method getStr = secCls.getMethod("getString", android.content.ContentResolver.class, String.class);
                Object val = getStr.invoke(null, ctx.getContentResolver(), "android_id");
                if (val instanceof String) {
                    androidId = (String) val;
                }
            }
        } catch (Throwable ignored) {
        }

        // Salted composite hardware fingerprint resistant to ID changer spoofing
        String raw = Build.BOARD + "|" + Build.BRAND + "|" + Build.DEVICE + "|"
                + Build.HARDWARE + "|" + Build.MANUFACTURER + "|" + Build.MODEL + "|"
                + Build.PRODUCT + "|" + Build.FINGERPRINT + "|"
                + (androidId != null ? androidId : "") + "|"
                + (sServerApiSecret != null ? sServerApiSecret : "SALT_FBOX_CORE") + "|" + pkg + "|" + getTeeHardwareKeyThumbprint();

        try {
            File hwidFile = new File(BEnvironment.getSystemDir(), "featurestic_hwid.id");
            if (hwidFile.isFile() && hwidFile.length() == 64) {
                byte[] buf = new byte[64];
                try (FileInputStream fis = new FileInputStream(hwidFile)) {
                    if (fis.read(buf) == 64) {
                        return new String(buf, StandardCharsets.UTF_8);
                    }
                }
            }
            String computed = sha256Hex("FBOX_HWID_SALT_V3|" + raw);
            FileUtils.mkdirs(hwidFile.getParentFile());
            try (FileOutputStream fos = new FileOutputStream(hwidFile, false)) {
                fos.write(computed.getBytes(StandardCharsets.UTF_8));
            }
            return computed;
        } catch (Throwable ignored) {
            return sha256Hex("FBOX_HWID_SALT_V3|" + raw);
        }
    }

    public static void zeroizeOffsetsInMemory() {
        try {
            for (Map.Entry<String, String> entry : sDecryptedRuntimeParams.entrySet()) {
                if (entry.getKey().startsWith("offset_") || entry.getKey().contains("hook")) {
                    entry.setValue("0x0");
                }
            }
        } catch (Throwable ignored) {
        }
    }

    public static boolean isDeviceBanned(Context context) {
        if (sDeviceBanned) return true;
        try {
            File seal = getBanSealFile();
            if (seal.isFile() && seal.length() > 0) {
                byte[] buf = new byte[(int) Math.min(seal.length(), 512L)];
                try (FileInputStream fis = new FileInputStream(seal)) {
                    int n = fis.read(buf);
                    if (n > 0) {
                        sDeviceBanReason = new String(buf, 0, n, StandardCharsets.UTF_8).trim();
                    }
                }
                sDeviceBanned = true;
                return true;
            }
        } catch (Throwable ignored) {
        }
        return false;
    }

    public static String getDeviceBanReason() {
        return sDeviceBanReason != null && !sDeviceBanReason.isEmpty()
                ? sDeviceBanReason
                : "Device banned by FeaturesticBox Anti-Crack Shield. Contact Admin Panel to unban.";
    }

    public static void clearLocalBanSeal() {
        sDeviceBanned = false;
        sDeviceBanReason = "";
        try {
            File seal = getBanSealFile();
            if (seal.exists()) {
                seal.delete();
            }
        } catch (Throwable ignored) {
        }
    }

    private static void writeLocalBanSeal(String reason) {
        sDeviceBanned = true;
        sDeviceBanReason = reason != null ? reason : "TAMPER_DETECTED";
        try {
            File seal = getBanSealFile();
            FileUtils.mkdirs(seal.getParentFile());
            try (FileOutputStream fos = new FileOutputStream(seal, false)) {
                fos.write(sDeviceBanReason.getBytes(StandardCharsets.UTF_8));
            }
        } catch (Throwable ignored) {
        }
    }

    /**
     * Continuous Anti-Debugging, Anti-Hooking (Frida/Xposed/LSPosed/Substrate),
     * APK Signature Integrity, HTTP Proxy Sniffer & Root/Emulator Detection.
     */
    public static String detectTamperViolation(Context context) {
        Context ctx = context != null ? context : FeaturesticBoxCore.getContext();

        // 1. Verify locked APK release signature SHA-256 if configured
        String expectedSha = sLockedReleaseCertSha256;
        if (expectedSha != null && !expectedSha.isEmpty()) {
            String liveSha = getLiveApkSigningSha256(ctx);
            if (!"HOST_UNAVAILABLE".equals(liveSha)
                    && !"UNSIGNED_DEV".equals(liveSha)
                    && !expectedSha.equalsIgnoreCase(liveSha)) {
                return "APK_SIGNATURE_MISMATCH (Expected " + expectedSha.substring(0, 8) + "..., Got " + liveSha.substring(0, 8) + "...)";
            }
        }

        // 2. Check Java & Native Debugger attachment (Debug.isDebuggerConnected / TracerPid)
        try {
            if (Debug.isDebuggerConnected() || Debug.waitingForDebugger()) {
                return "JAVA_JDWP_DEBUGGER_ATTACHED";
            }
        } catch (Throwable ignored) {
        }

        if (!FeaturesticBoxCore.get().isFeaturesticProcess()) {
            try (BufferedReader br = new BufferedReader(new FileReader("/proc/self/status"))) {
                String line;
                while ((line = br.readLine()) != null) {
                    if (line.startsWith("TracerPid:")) {
                        String val = line.substring("TracerPid:".length()).trim();
                        int tracerPid = Integer.parseInt(val);
                        if (tracerPid > 0) {
                            return "DEBUGGER_TRACERPID_ATTACHED (PID=" + tracerPid + ")";
                        }
                        break;
                    }
                }
            } catch (Throwable ignored) {
            }
        }

        // 3. Check /proc/self/maps for Frida gadget/agent, Substrate, Dobby, or Xposed hooks on host loader
        try (BufferedReader br = new BufferedReader(new FileReader("/proc/self/maps"))) {
            String line;
            while ((line = br.readLine()) != null) {
                String low = line.toLowerCase(Locale.US);
                if (low.contains("frida-agent")
                        || low.contains("frida-gadget")
                        || low.contains("libgum.so")
                        || low.contains("linjector")) {
                    return "FRIDA_MEMORY_INJECTION_DETECTED";
                }
                if (low.contains("gameguardian")
                        || low.contains("ue4dumper")
                        || low.contains("libdumper")
                        || low.contains("memdumper")
                        || low.contains("cheatengine")) {
                    return "MEMORY_DUMPER_OR_GG_SCANNER_DETECTED";
                }
                // W^X memory protection check: executable code segments must NEVER be writable
                if ((low.contains("libfeaturestic") || low.contains(".so")) && line.contains("rwxp")) {
                    return "MEMORY_HOOK_INLINE_TAMPER (W^X violation: writable code page in " + line.trim() + ")";
                }
            }
        } catch (Throwable ignored) {
        }

        // 4. Scan /proc/self/task/*/comm for Frida internal threads (gum-js-loop, pool-frida)
        try {
            File taskDir = new File("/proc/self/task");
            File[] tasks = taskDir.listFiles();
            if (tasks != null) {
                for (File t : tasks) {
                    File comm = new File(t, "comm");
                    if (comm.isFile()) {
                        try (BufferedReader br = new BufferedReader(new FileReader(comm))) {
                            String name = br.readLine();
                            if (name != null) {
                                String low = name.trim().toLowerCase(Locale.US);
                                if (low.contains("gum-js-loop") || low.contains("pool-frida") || low.contains("frida")) {
                                    return "FRIDA_RUNTIME_THREAD_DETECTED (" + low + ")";
                                }
                            }
                        } catch (Throwable ignored) {
                        }
                    }
                }
            }
        } catch (Throwable ignored) {
        }

        // 5. Check HTTP Proxy / MITM Packet Sniffer (Reqable, HttpCanary, Charles, Burp)
        if (sBlockHttpProxy) {
            try {
                String proxyHost = System.getProperty("http.proxyHost");
                String httpsProxy = System.getProperty("https.proxyHost");
                if ((proxyHost != null && !proxyHost.trim().isEmpty())
                        || (httpsProxy != null && !httpsProxy.trim().isEmpty())) {
                    return "HTTP_PROXY_MITM_SNIFFER_DETECTED (" + (proxyHost != null ? proxyHost : httpsProxy) + ")";
                }
            } catch (Throwable ignored) {
            }
        }

        // 6. Optional Root / Magisk Check (controlled by Panel switch block_root_magisk)
        if (sBlockRootMagisk) {
            String[] rootPaths = new String[]{
                    "/system/bin/su", "/system/xbin/su", "/sbin/su",
                    "/data/adb/magisk", "/sbin/.magisk", "/system/app/Superuser.apk"
            };
            for (String rp : rootPaths) {
                if (new File(rp).exists()) {
                    return "ROOT_OR_MAGISK_ENVIRONMENT_DETECTED (" + rp + ")";
                }
            }
        }

        // 7. Optional Emulator Check (controlled by Panel switch block_emulator)
        if (sBlockEmulator) {
            String hw = (Build.HARDWARE != null ? Build.HARDWARE : "").toLowerCase(Locale.US);
            String fp = (Build.FINGERPRINT != null ? Build.FINGERPRINT : "").toLowerCase(Locale.US);
            String prod = (Build.PRODUCT != null ? Build.PRODUCT : "").toLowerCase(Locale.US);
            if (hw.contains("goldfish") || hw.contains("ranchu") || hw.contains("vbox86")
                    || hw.contains("nox") || fp.contains("generic") || prod.contains("sdk_gphone")) {
                return "EMULATOR_ENVIRONMENT_BLOCKED (" + hw + ")";
            }
        }

        // 8. Anti-Frida Memory Trampoline Cloaking Check
        String trampoline = detectInlineHookTrampolines();
        if (trampoline != null) {
            return trampoline;
        }

        // 9. Thread Timing Jitter Anomaly Check (Debugger / PTrace Single-Stepping)
        String jitter = checkTimingJitterAnomaly();
        if (jitter != null) {
            return jitter;
        }

        // 10. Kernel ASLR (Address Space Layout Randomization) Verification Guard
        try {
            File aslrFile = new File("/proc/sys/kernel/randomize_va_space");
            if (aslrFile.isFile() && aslrFile.canRead()) {
                try (BufferedReader abr = new BufferedReader(new FileReader(aslrFile))) {
                    String aslrVal = abr.readLine();
                    if (aslrVal != null && "0".equals(aslrVal.trim())) {
                        return "KERNEL_ASLR_DISABLED_MEMORY_DUMPER";
                    }
                }
            }
        } catch (Throwable ignored) {
        }

        return null;
    }

    /**
     * Checks if local Frida default server port (27042) is listening on loopback (runs only on worker thread).
     */
    /**
     * Anti-Frida Dynamic Memory Trampoline Cloaking Check:
     * Scans memory mappings for anonymous hook allocations and dynamic detours.
     */
    private static String detectInlineHookTrampolines() {
        try {
            File maps = new File("/proc/self/maps");
            if (!maps.isFile()) return null;
            try (BufferedReader br = new BufferedReader(new FileReader(maps))) {
                String line;
                while ((line = br.readLine()) != null) {
                    String low = line.toLowerCase(Locale.US);
                    if (line.contains("r-xp") && (line.contains("[anon:") || line.trim().endsWith("]"))) {
                        if (low.contains("gum") || low.contains("hook") || low.contains("trampoline") || low.contains("dobby")) {
                            return "DYNAMIC_TRAMPOLINE_ANON_HOOK (" + line.trim() + ")";
                        }
                    }
                }
            }
        } catch (Throwable ignored) {
        }
        return null;
    }

    /**
     * Thread Timing Jitter Anomaly Check:
     * Traps debugger single-stepping / ptrace latency spikes across cryptographic execution.
     */
    private static String checkTimingJitterAnomaly() {
        try {
            long t0 = System.nanoTime();
            MessageDigest md = MessageDigest.getInstance("SHA-256");
            md.update(BOOT_SECRET);
            md.digest();
            long dtMs = (System.nanoTime() - t0) / 1000000L;
            if (dtMs > 450L) {
                return "DEBUGGER_PTRACE_STEPPING_ANOMALY (Latency: " + dtMs + "ms)";
            }
        } catch (Throwable ignored) {
        }
        return null;
    }

    private static String checkBackgroundFridaServerPort() {
        for (int port : new int[]{27042, 27043}) {
            Socket sock = null;
            try {
                sock = new Socket();
                sock.connect(new InetSocketAddress("127.0.0.1", port), 120);
                return "FRIDA_SERVER_PORT_OPEN (" + port + ")";
            } catch (Throwable ignored) {
            } finally {
                if (sock != null) {
                    try {
                        sock.close();
                    } catch (Throwable ignored) {
                    }
                }
            }
        }
        return null;
    }

    /**
     * Opens an HTTP/HTTPS connection to the Panel while verifying:
     * 1. DNS is not spoofed via /etc/hosts to 127.0.0.1 / ::1 when target is a remote domain.
     * 2. TLS X.509 Certificate SHA-256 matches sPinnedTlsCertSha256 (if configured).
     */
    /**
     * Scans /system/etc/hosts and /etc/hosts to ensure the Panel domain has not been hijacked on a rooted device.
     */
    public static boolean isDomainHijackedInHostsFile(String targetHost) {
        if (targetHost == null || targetHost.trim().isEmpty()) return false;
        String cleanHost = targetHost.trim().toLowerCase(Locale.US);
        if ("localhost".equals(cleanHost) || "127.0.0.1".equals(cleanHost) || "10.0.2.2".equals(cleanHost)) {
            return false;
        }
        String[] hostPaths = new String[]{"/system/etc/hosts", "/etc/hosts", "/system/etc/hosts.bak"};
        for (String p : hostPaths) {
            File f = new File(p);
            if (f.isFile() && f.canRead()) {
                try (BufferedReader br = new BufferedReader(new FileReader(f))) {
                    String line;
                    while ((line = br.readLine()) != null) {
                        int hash = line.indexOf('#');
                        if (hash >= 0) line = line.substring(0, hash);
                        String low = line.trim().toLowerCase(Locale.US);
                        if (!low.isEmpty() && low.contains(cleanHost)) {
                            return true;
                        }
                    }
                } catch (Throwable ignored) {
                }
            }
        }
        return false;
    }

    /**
     * Resolves a domain name using Cloudflare (1.1.1.1) or Google (8.8.8.8) DNS-over-HTTPS (DoH)
     * so ISP DNS blocking or local router DNS poisoning cannot block or spoof the Panel URL.
     */
    public static String resolveDomainViaDoH(String host) {
        if (host == null || host.trim().isEmpty()) return "";
        String[] dohEndpoints = new String[]{
                "https://1.1.1.1/dns-query?name=" + host.trim() + "&type=A",
                "https://8.8.8.8/resolve?name=" + host.trim() + "&type=A"
        };
        for (String ep : dohEndpoints) {
            HttpURLConnection c = null;
            try {
                c = (HttpURLConnection) new URL(ep).openConnection();
                c.setConnectTimeout(4000);
                c.setReadTimeout(4000);
                c.setRequestProperty("Accept", "application/dns-json");
                if (c.getResponseCode() == 200) {
                    BufferedReader br = new BufferedReader(new InputStreamReader(c.getInputStream(), StandardCharsets.UTF_8));
                    StringBuilder sb = new StringBuilder();
                    String line;
                    while ((line = br.readLine()) != null) sb.append(line);
                    br.close();
                    String json = sb.toString();
                    int idx = json.lastIndexOf("\"data\":\"");
                    if (idx >= 0) {
                        int start = idx + 8;
                        int end = json.indexOf('"', start);
                        if (end > start) {
                            String ip = json.substring(start, end).trim();
                            if (ip.matches("^[0-9]+\\.[0-9]+\\.[0-9]+\\.[0-9]+$")) {
                                return ip;
                            }
                        }
                    }
                }
            } catch (Throwable ignored) {
            } finally {
                if (c != null) {
                    try { c.disconnect(); } catch (Throwable ignored) {}
                }
            }
        }
        return "";
    }

    static HttpURLConnection openVerifiedPanelConnection(URL url, Context ctx) throws Exception {
        String host = url.getHost();
        if (host != null && !host.equalsIgnoreCase("localhost") && !host.equals("127.0.0.1") && !host.equals("10.0.2.2")) {
            if (isDomainHijackedInHostsFile(host)) {
                if (sAutoBanOnTamper) {
                    reportTamperAndBanDevice(ctx, "HOSTS_FILE_DOMAIN_HIJACK", "Domain " + host + " found in /etc/hosts");
                }
                throw new SecurityException("Hosts file tamper detected for " + host);
            }
            InetAddress resolved = null;
            try {
                resolved = InetAddress.getByName(host);
            } catch (Throwable dnsErr) {
                // Fallback to DNS-over-HTTPS if ISP DNS fails
                String dohIp = resolveDomainViaDoH(host);
                if (dohIp == null || dohIp.isEmpty()) {
                    throw dnsErr;
                }
                resolved = InetAddress.getByName(dohIp);
            }
            if (resolved != null && (resolved.isLoopbackAddress() || "0.0.0.0".equals(resolved.getHostAddress()))) {
                if (sAutoBanOnTamper) {
                    reportTamperAndBanDevice(ctx, "HOSTS_FILE_MOCK_SERVER_SPOOFING", "Domain " + host + " redirected to loopback");
                }
                throw new SecurityException("Mock server redirection detected for " + host);
            }
        }

        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setConnectTimeout(10000);
        conn.setReadTimeout(12000);
        return conn;
    }

    static void verifyTlsPinningIfHttps(HttpURLConnection conn, Context ctx) throws Exception {
        if (conn instanceof HttpsURLConnection && sPinnedTlsCertSha256 != null && !sPinnedTlsCertSha256.isEmpty()) {
            HttpsURLConnection https = (HttpsURLConnection) conn;
            Certificate[] certs = https.getServerCertificates();
            if (certs == null || certs.length == 0 || certs[0] == null) {
                throw new SecurityException("Missing server TLS certificate");
            }
            MessageDigest md = MessageDigest.getInstance("SHA-256");
            String certSha = bytesToHex(md.digest(certs[0].getEncoded()));
            if (!sPinnedTlsCertSha256.equalsIgnoreCase(certSha)) {
                if (sAutoBanOnTamper) {
                    reportTamperAndBanDevice(ctx, "TLS_MITM_CERTIFICATE_SPOOFING",
                            "Expected TLS SHA-256 " + sPinnedTlsCertSha256.substring(0, 12) + ", got " + certSha.substring(0, 12));
                }
                throw new SecurityException("TLS Certificate Pinning mismatch! Possible SSL MITM proxy.");
            }
        }
    }

    /**
     * Immediately bans the device locally and reports the tamper incident + HWID + IP to the PHP Admin Panel.
     */
    public static void reportTamperAndBanDevice(final Context context, final String tamperReason, final String extraDetails) {
        final Context ctx = context != null ? context : FeaturesticBoxCore.getContext();
        final String reason = (tamperReason != null && !tamperReason.isEmpty()) ? tamperReason : "UNAUTHORIZED_CRACK_ATTEMPT";
        writeLocalBanSeal(reason);
        clearActiveSession();

        final String panelUrl = FeaturesticRemoteConfigGuard.getRemoteConfigUrl();
        if (panelUrl == null || !panelUrl.startsWith("http")) {
            return;
        }
        new Thread(new Runnable() {
            @Override
            public void run() {
                HttpURLConnection conn = null;
                try {
                    String endpoint = buildApiUrl(panelUrl, "report_tamper");
                    String hwid = getHardwareDeviceId(ctx);
                    String pkg = ctx != null ? ctx.getPackageName() : "unknown";
                    String sha = getLiveApkSigningSha256(ctx);
                    long ts = System.currentTimeMillis() / 1000L;
                    String sig = computeHmacHex(hwid + "|" + pkg + "|" + reason + "|" + ts, sServerApiSecret);

                    String jsonBody = "{"
                            + "\"action\":\"report_tamper\","
                            + "\"hwid\":\"" + escapeJson(hwid) + "\","
                            + "\"package_name\":\"" + escapeJson(pkg) + "\","
                            + "\"apk_sha256\":\"" + escapeJson(sha) + "\","
                            + "\"device_model\":\"" + escapeJson(Build.MANUFACTURER + " " + Build.MODEL) + "\","
                            + "\"android_version\":\"" + escapeJson(Build.VERSION.RELEASE + " (API " + Build.VERSION.SDK_INT + ")") + "\","
                            + "\"tamper_reason\":\"" + escapeJson(reason) + "\","
                            + "\"tamper_details\":\"" + escapeJson(extraDetails != null ? extraDetails : "") + "\","
                            + "\"license_key\":\"" + escapeJson(sActiveLicenseKey) + "\","
                            + "\"timestamp\":" + ts + ","
                            + "\"signature\":\"" + escapeJson(sig) + "\""
                            + "}";

                    conn = (HttpURLConnection) new URL(endpoint).openConnection();
                    conn.setRequestMethod("POST");
                    conn.setConnectTimeout(8000);
                    conn.setReadTimeout(8000);
                    conn.setDoOutput(true);
                    conn.setRequestProperty("Content-Type", "application/json; charset=UTF-8");
                    conn.setRequestProperty("User-Agent", "FeaturesticBox-SecurityShield/3.2");
                    try (OutputStream os = conn.getOutputStream()) {
                        os.write(jsonBody.getBytes(StandardCharsets.UTF_8));
                    }
                    conn.getResponseCode();
                } catch (Throwable ignored) {
                } finally {
                    if (conn != null) {
                        try {
                            conn.disconnect();
                        } catch (Throwable ignored) {
                        }
                    }
                }
            }
        }, "Featurestic-TamperReporter").start();
    }

    /**
     * Pre-connection check against ban_check.php (?action=ban_check) with Challenge-Response Nonce verification.
     */
    public static void checkBanStatusWithPanel(final Context context, final BanCheckCallback callback) {
        new Thread(new Runnable() {
            @Override
            public void run() {
                final Context ctx = context != null ? context : FeaturesticBoxCore.getContext();
                String panelUrl = FeaturesticRemoteConfigGuard.getRemoteConfigUrl();
                if (panelUrl == null || !panelUrl.startsWith("http")) {
                    boolean banned = isDeviceBanned(ctx);
                    if (callback != null) {
                        callback.onResult(banned, banned ? "DEVICE_BANNED" : "NO_SERVER", getDeviceBanReason());
                    }
                    return;
                }
                HttpURLConnection conn = null;
                try {
                    String endpoint = buildApiUrl(panelUrl, "ban_check");
                    String hwid = getHardwareDeviceId(ctx);
                    String nonce = generateRandomNonceHex();
                    String body = "{\"action\":\"ban_check\",\"hwid\":\"" + escapeJson(hwid)
                            + "\",\"nonce\":\"" + escapeJson(nonce)
                            + "\",\"device_model\":\"" + escapeJson(Build.MANUFACTURER + " " + Build.MODEL) + "\"}";

                    conn = openVerifiedPanelConnection(new URL(endpoint), ctx);
                    conn.setRequestMethod("POST");
                    conn.setDoOutput(true);
                    conn.setRequestProperty("Content-Type", "application/json; charset=UTF-8");
                    try (OutputStream os = conn.getOutputStream()) {
                        os.write(body.getBytes(StandardCharsets.UTF_8));
                    }
                    verifyTlsPinningIfHttps(conn, ctx);

                    int code = conn.getResponseCode();
                    BufferedReader br = new BufferedReader(new InputStreamReader(
                            (code >= 200 && code < 400) ? conn.getInputStream() : conn.getErrorStream(),
                            StandardCharsets.UTF_8));
                    StringBuilder sb = new StringBuilder();
                    String line;
                    while ((line = br.readLine()) != null) sb.append(line);
                    br.close();

                    String resp = sb.toString();
                    String status = extractJsonString(resp, "status");
                    String msg = extractJsonString(resp, "message");
                    String sig = extractJsonString(resp, "signature");
                    boolean isBanned = "DEVICE_BANNED".equalsIgnoreCase(status)
                            || "true".equalsIgnoreCase(extractJsonString(resp, "is_banned"));

                    String expectedSig = computeHmacHex(
                            (isBanned ? "DEVICE_BANNED|" : "CLEAN|") + nonce + "|" + hwid + "|" + (isBanned ? "1" : "0"),
                            sServerApiSecret);
                    if (sig.isEmpty() || !constantTimeEquals(expectedSig, sig)) {
                        if (sAutoBanOnTamper) {
                            reportTamperAndBanDevice(ctx, "FAKE_BAN_CHECK_RESPONSE", "Invalid ban_check signature");
                        }
                        if (callback != null) callback.onResult(true, "MITM_DETECTED", "Invalid server signature");
                        return;
                    }

                    String rsaSig = extractJsonString(resp, "rsa_signature");
                    String sigPayload = (isBanned ? "DEVICE_BANNED|" : "CLEAN|") + nonce + "|" + hwid + "|" + (isBanned ? "1" : "0");
                    if (!rsaSig.isEmpty() && !verifyRsaSha256(sigPayload, rsaSig, SERVER_RSA_PUBLIC_KEY_B64)) {
                        if (sAutoBanOnTamper) {
                            reportTamperAndBanDevice(ctx, "FAKE_BAN_CHECK_RSA_SIGNATURE", "Invalid ban_check RSA signature");
                        }
                        if (callback != null) callback.onResult(true, "MITM_DETECTED", "Invalid server RSA signature");
                        return;
                    }

                    if (isBanned) {
                        writeLocalBanSeal(msg);
                    } else {
                        clearLocalBanSeal();
                    }
                    if (callback != null) {
                        callback.onResult(isBanned, status, msg);
                    }
                } catch (Throwable t) {
                    if (callback != null) {
                        callback.onResult(isDeviceBanned(ctx), "ERROR", t.getMessage());
                    }
                } finally {
                    if (conn != null) {
                        try {
                            conn.disconnect();
                        } catch (Throwable ignored) {
                        }
                    }
                }
            }
        }, "Featurestic-BanCheck").start();
    }

    public static void authenticateLicenseWithPanel(
            final Context context, final String licenseKey, final PanelAuthCallback callback) {
        authenticateCredentialsWithPanel(context, "", "", licenseKey, callback);
    }

    /**
     * Authenticates user credentials (Username + Password and/or License Key) with login.php (?action=auth).
     * Strictly enforces Challenge-Response Nonce HMAC + AES-256-CBC bound_nonce decryption so fake responses
     * or replay attacks are impossible.
     */
    public static void authenticateCredentialsWithPanel(
            final Context context,
            final String username,
            final String password,
            final String licenseKey,
            final PanelAuthCallback callback) {
        new Thread(new Runnable() {
            @Override
            public void run() {
                final Context ctx = context != null ? context : FeaturesticBoxCore.getContext();

                // 1. Run full local + port anti-crack tamper checks
                String tamper = detectTamperViolation(ctx);
                if (tamper == null) {
                    tamper = checkBackgroundFridaServerPort();
                }
                if (tamper != null && sAutoBanOnTamper) {
                    reportTamperAndBanDevice(ctx, tamper, "Detected during login authentication");
                    dispatchCallback(callback, false, "DEVICE_BANNED",
                            "Security Violation: Device has been banned (" + tamper + "). Unban from Admin Panel.",
                            sDecryptedRuntimeParams);
                    return;
                }

                // 2. Strictly require a configured server URL — never allow offline unlock bypass!
                String panelUrl = FeaturesticRemoteConfigGuard.getRemoteConfigUrl();
                if (panelUrl == null || !panelUrl.startsWith("http")) {
                    clearActiveSession();
                    dispatchCallback(callback, false, "MISSING_SERVER_ENDPOINT",
                            "Server Panel URL is not configured or unreachable. Login denied.",
                            sDecryptedRuntimeParams);
                    return;
                }

                HttpURLConnection conn = null;
                try {
                    String endpoint = buildApiUrl(panelUrl, "auth");
                    String hwid = getHardwareDeviceId(ctx);
                    String pkg = ctx != null ? ctx.getPackageName() : "";
                    String sha = getLiveApkSigningSha256(ctx);
                    String abi = FeaturesticBoxCore.is64Bit() ? "arm64-v8a" : "armeabi-v7a";
                    String cleanKey = licenseKey != null ? licenseKey.trim() : "";
                    String cleanUser = username != null ? username.trim() : "";
                    String cleanPass = password != null ? password : "";
                    long ts = System.currentTimeMillis() / 1000L;
                    String nonce = generateRandomNonceHex();
                    String authIdentity = !cleanKey.isEmpty() ? cleanKey : cleanUser;
                    String reqSig = computeHmacHex(
                            authIdentity + "|" + hwid + "|" + pkg + "|" + sha + "|" + ts + "|" + nonce,
                            sServerApiSecret);

                    String innerJson = "{"
                            + "\"action\":\"auth\","
                            + "\"license_key\":\"" + escapeJson(cleanKey) + "\",\n"
                            + "\"loader_id\":\"" + escapeJson(sLoaderId) + "\","
                            + "\"username\":\"" + escapeJson(cleanUser) + "\","
                            + "\"password\":\"" + escapeJson(cleanPass) + "\","
                            + "\"hwid\":\"" + escapeJson(hwid) + "\","
                            + "\"package_name\":\"" + escapeJson(pkg) + "\","
                            + "\"apk_sha256\":\"" + escapeJson(sha) + "\","
                            + "\"device_model\":\"" + escapeJson(Build.MANUFACTURER + " " + Build.MODEL) + "\","
                            + "\"android_version\":\"" + escapeJson(Build.VERSION.RELEASE) + "\","
                            + "\"abi\":\"" + escapeJson(abi) + "\","
                            + "\"timestamp\":" + ts + ","
                            + "\"nonce\":\"" + escapeJson(nonce) + "\","
                            + "\"signature\":\"" + escapeJson(reqSig) + "\""
                            + "}";

                    // Encrypt request body with AES-256-CBC (End-to-End Request Encryption)
                    String[] encReq = encryptAesCbcPayload(innerJson, nonce + "|" + sServerApiSecret);
                    String wireBody;
                    if (encReq != null) {
                        wireBody = "{\"action\":\"auth\",\"client_nonce\":\"" + escapeJson(nonce)
                                + "\",\"e2e_payload\":\"" + escapeJson(encReq[0])
                                + "\",\"e2e_iv\":\"" + escapeJson(encReq[1]) + "\"}";
                    } else {
                        wireBody = innerJson;
                    }

                    java.util.List<String> candidates = FeaturesticRemoteConfigGuard.getCandidateGatewayUrls();
                    if (candidates.isEmpty()) candidates.add(panelUrl);
                    String rawResp = null;
                    Throwable lastErr = null;

                    for (String candidateUrl : candidates) {
                        try {
                            String endpointCandidate = buildApiUrl(candidateUrl, "auth");
                            conn = openVerifiedPanelConnection(new URL(endpointCandidate), ctx);
                            conn.setRequestMethod("POST");
                            conn.setDoOutput(true);
                            conn.setRequestProperty("Content-Type", "application/json; charset=UTF-8");
                            conn.setRequestProperty("User-Agent", "FeaturesticBox-SDK/3.2");
                            try (OutputStream os = conn.getOutputStream()) {
                                os.write(wireBody.getBytes(StandardCharsets.UTF_8));
                            }
                            verifyTlsPinningIfHttps(conn, ctx);

                            int code = conn.getResponseCode();
                            BufferedReader br = new BufferedReader(new InputStreamReader(
                                    (code >= 200 && code < 400) ? conn.getInputStream() : conn.getErrorStream(),
                                    StandardCharsets.UTF_8));
                            StringBuilder sb = new StringBuilder();
                            String line;
                            while ((line = br.readLine()) != null) {
                                sb.append(line);
                            }
                            br.close();
                            String candidateResp = sb.toString();
                            if (code >= 200 && code < 500 && candidateResp != null && !candidateResp.isEmpty()) {
                                rawResp = candidateResp;
                                FeaturesticRemoteConfigGuard.setRemoteConfigUrl(candidateUrl);
                                break;
                            }
                        } catch (Throwable t) {
                            lastErr = t;
                        } finally {
                            if (conn != null) {
                                try { conn.disconnect(); } catch (Throwable ignored) {}
                            }
                        }
                    }
                    if (rawResp == null) {
                        throw (lastErr != null) ? lastErr : new RuntimeException("All panel gateways unreachable");
                    }
                    String status = extractJsonString(rawResp, "status");
                    String msg = extractJsonString(rawResp, "message");
                    boolean isBanned = "DEVICE_BANNED".equalsIgnoreCase(status)
                            || "true".equalsIgnoreCase(extractJsonString(rawResp, "is_banned"));

                    if (isBanned) {
                        writeLocalBanSeal(msg != null && !msg.isEmpty() ? msg : "Banned by Admin Panel");
                        clearActiveSession();
                        dispatchCallback(callback, false, "DEVICE_BANNED", getDeviceBanReason(), sDecryptedRuntimeParams);
                        return;
                    }

                    if ("UPDATE_REQUIRED".equalsIgnoreCase(status)) {
                        clearActiveSession();
                        dispatchCallback(callback, false, "UPDATE_REQUIRED",
                                msg != null && !msg.isEmpty() ? msg : "A mandatory loader update is required to continue.",
                                sDecryptedRuntimeParams);
                        return;
                    }

                    if ("SERVER_LOCKDOWN".equalsIgnoreCase(status)) {
                        clearActiveSession();
                        dispatchCallback(callback, false, "SERVER_LOCKDOWN",
                                msg != null && !msg.isEmpty() ? msg : "System is undergoing emergency maintenance. Please try again later.",
                                sDecryptedRuntimeParams);
                        return;
                    }

                    if (!"OK".equalsIgnoreCase(status)) {
                        clearActiveSession();
                        dispatchCallback(callback, false, status.isEmpty() ? "AUTH_FAILED" : status,
                                msg.isEmpty() ? "License validation failed" : msg, sDecryptedRuntimeParams);
                        return;
                    }

                    String sessionToken = extractJsonString(rawResp, "session_token");
                    String rollingToken = extractJsonString(rawResp, "rolling_token");
                    String leaseExpStr = extractJsonString(rawResp, "lease_expires_at");
                    String encryptedPayload = extractJsonString(rawResp, "encrypted_payload");
                    String payloadIv = extractJsonString(rawResp, "payload_iv");
                    String serverSig = extractJsonString(rawResp, "signature");

                    // STRICT ANTI-SPOOFING CHECK #1: All cryptographic fields MUST be present
                    if (sessionToken.isEmpty() || rollingToken.isEmpty()
                            || encryptedPayload.isEmpty() || payloadIv.isEmpty() || serverSig.isEmpty()) {
                        reportTamperAndBanDevice(ctx, "FAKE_SERVER_RESPONSE_MISSING_CRYPTO",
                                "Server response missing session_token, rolling_token, payload, or signature");
                        dispatchCallback(callback, false, "MITM_DETECTED",
                                "Fake server response detected! Device has been banned.", sDecryptedRuntimeParams);
                        return;
                    }

                    long leaseExpSec = 0L;
                    try {
                        leaseExpSec = Long.parseLong(leaseExpStr);
                    } catch (Throwable ignored) {
                    }
                    if (leaseExpSec <= 0L) {
                        reportTamperAndBanDevice(ctx, "FAKE_SERVER_LEASE_TIMESTAMP", "Invalid lease_expires_at");
                        dispatchCallback(callback, false, "MITM_DETECTED", "Invalid server lease timestamp!", sDecryptedRuntimeParams);
                        return;
                    }

                    // STRICT ANTI-SPOOFING CHECK #2: Verify Nonce-Bound Server HMAC Signature
                    String expectedServerSig = computeHmacHex(
                            "OK|" + nonce + "|" + sessionToken + "|" + rollingToken + "|" + hwid + "|" + leaseExpSec + "|" + encryptedPayload,
                            sServerApiSecret);
                    if (!constantTimeEquals(expectedServerSig, serverSig)) {
                        reportTamperAndBanDevice(ctx, "MITM_RESPONSE_SIGNATURE_TAMPER",
                                "Server HMAC mismatch over challenge nonce");
                        dispatchCallback(callback, false, "MITM_DETECTED",
                                "Server challenge-response signature failed! Fake response blocked.", sDecryptedRuntimeParams);
                        return;
                    }

                    // STRICT ANTI-SPOOFING CHECK #2B: Verify Asymmetric Server RSA-2048 Digital Signature
                    String rsaSig = extractJsonString(rawResp, "rsa_signature");
                    String rsaPayload = "OK|" + nonce + "|" + sessionToken + "|" + rollingToken + "|" + hwid + "|" + leaseExpSec + "|" + encryptedPayload;
                    if (rsaSig.isEmpty() || !verifyRsaSha256(rsaPayload, rsaSig, SERVER_RSA_PUBLIC_KEY_B64)) {
                        reportTamperAndBanDevice(ctx, "RSA_SIGNATURE_FORGERY_DETECTED",
                                "Server RSA-2048 digital signature verification failed");
                        dispatchCallback(callback, false, "FORGERY_DETECTED",
                                "Cryptographic signature forgery! Unsigned response rejected.", sDecryptedRuntimeParams);
                        return;
                    }

                    // STRICT ANTI-SPOOFING CHECK #3: Decrypt AES-256-CBC Payload with per-request nonce key & verify bound_nonce + bound_hwid
                    String decryptedJson = decryptAesCbcPayload(
                            encryptedPayload, payloadIv, sessionToken + "|" + nonce + "|" + sServerApiSecret);
                    if (decryptedJson == null || decryptedJson.isEmpty()) {
                        reportTamperAndBanDevice(ctx, "AES256_PAYLOAD_DECRYPTION_FAILURE",
                                "Failed to decrypt server payload with challenge nonce");
                        dispatchCallback(callback, false, "MITM_DETECTED",
                                "Encrypted server payload verification failed!", sDecryptedRuntimeParams);
                        return;
                    }

                    String boundNonce = extractJsonString(decryptedJson, "bound_nonce");
                    String boundHwid = extractJsonString(decryptedJson, "bound_hwid");
                    if (!nonce.equals(boundNonce) || !hwid.equalsIgnoreCase(boundHwid)) {
                        reportTamperAndBanDevice(ctx, "REPLAY_ATTACK_NONCE_MISMATCH",
                                "Decrypted payload nonce/HWID mismatch");
                        dispatchCallback(callback, false, "REPLAY_DETECTED",
                                "Replay attack detected! Response nonce does not match live request.", sDecryptedRuntimeParams);
                        return;
                    }

                    // All 3 cryptographic layers passed! Clear any old local ban seal and activate session lease
                    clearLocalBanSeal();
                    sActiveLicenseKey = !cleanKey.isEmpty() ? cleanKey : cleanUser;
                    sActiveSessionToken = sessionToken;
                    sActiveRollingToken = rollingToken;
                    sSessionLeaseExpiresAtMs = leaseExpSec * 1000L;
                    sLastSuccessfulHeartbeatMs = System.currentTimeMillis();
                    sServerVerifiedSeal = computeHmacHex(
                            sessionToken + "|" + rollingToken + "|" + hwid + "|" + sSessionLeaseExpiresAtMs,
                            new String(BOOT_SECRET, StandardCharsets.ISO_8859_1));

                    unlockVerifiedServerSession(ctx, sessionToken, rollingToken);
                    FeaturesticRemoteConfigGuard.applyDecryptedServerPayload(ctx, decryptedJson, sDecryptedRuntimeParams);
                    FeaturesticRemoteConfigGuard.startHeartbeatIfNeeded(ctx);

                    dispatchCallback(callback, true, "OK",
                            msg.isEmpty() ? "Authenticated with FeaturesticBox Panel" : msg,
                            sDecryptedRuntimeParams);
                } catch (SecurityException se) {
                    clearActiveSession();
                    dispatchCallback(callback, false, "SECURITY_ALERT", se.getMessage(), sDecryptedRuntimeParams);
                } catch (Throwable t) {
                    dispatchCallback(callback, false, "NETWORK_ERROR",
                            "Panel connection error: " + t.getMessage(), sDecryptedRuntimeParams);
                } finally {
                    if (conn != null) {
                        try {
                            conn.disconnect();
                        } catch (Throwable ignored) {
                        }
                    }
                }
            }
        }, "Featurestic-PanelAuth").start();
    }

    private static void dispatchCallback(
            final PanelAuthCallback cb,
            final boolean ok,
            final String status,
            final String msg,
            final Map<String, String> params) {
        if (cb == null) return;
        new Handler(Looper.getMainLooper()).post(new Runnable() {
            @Override
            public void run() {
                cb.onResult(ok, status, msg, params);
            }
        });
    }

    private static void unlockVerifiedServerSession(Context context, String sessionToken, String rollingToken) {
        if (context == null) {
            context = FeaturesticBoxCore.getContext();
        }
        String liveSha = getLiveApkSigningSha256(context);
        sActiveUserTokenHash = sha256Hex(sessionToken + "|" + rollingToken + "|" + liveSha);
        sActiveSessionProof = computeHmacHex("UNLOCKED|" + Process.myPid() + "|" + liveSha, sActiveUserTokenHash);
        persistSessionState();
        try {
            RNative.setNativeProp("debug.featurestic.login_gate", "0");
        } catch (Throwable ignored) {
        }
    }

    private static void persistSessionState() {
        try {
            File leaseFile = getSessionCacheFile();
            FileUtils.mkdirs(leaseFile.getParentFile());
            String hwid = getHardwareDeviceId(FeaturesticBoxCore.getContext());
            String diskMac = computeHmacHex(
                    sActiveSessionToken + "|" + sActiveRollingToken + "|" + sSessionLeaseExpiresAtMs + "|" + hwid,
                    sServerApiSecret);
            String data = sActiveSessionToken + "|" + sSessionLeaseExpiresAtMs + "|" + sActiveLicenseKey
                    + "|" + sActiveRollingToken + "|" + diskMac;
            try (FileOutputStream fos = new FileOutputStream(leaseFile, false)) {
                fos.write(data.getBytes(StandardCharsets.UTF_8));
            }
        } catch (Throwable ignored) {
        }
    }

    /**
     * Legacy method retained for ABI compatibility; blocked when server login gate is enforced.
     */
    public static boolean unlockLoaderLoginGate(Context context, String userKeyOrSessionToken) {
        if (sRequireLoaderLoginGate) {
            // Cannot unlock locally without a cryptographic server session!
            return isSessionAuthorizedAndIntact(context);
        }
        if (context == null) {
            context = FeaturesticBoxCore.getContext();
        }
        if (isDeviceBanned(context)) {
            return false;
        }
        String tamper = detectTamperViolation(context);
        if (tamper != null && sAutoBanOnTamper) {
            reportTamperAndBanDevice(context, tamper, "Detected in unlockLoaderLoginGate");
            return false;
        }
        String token = (userKeyOrSessionToken != null && !userKeyOrSessionToken.trim().isEmpty())
                ? userKeyOrSessionToken.trim()
                : "AUTHORIZED_USER";
        String liveSha = getLiveApkSigningSha256(context);
        sActiveUserTokenHash = sha256Hex(token + "|" + liveSha);
        sActiveSessionProof = computeHmacHex("UNLOCKED|" + Process.myPid() + "|" + liveSha, sActiveUserTokenHash);
        if (sActiveSessionToken.isEmpty()) {
            sActiveSessionToken = sActiveSessionProof;
        }
        sSessionLeaseExpiresAtMs = System.currentTimeMillis() + 3600_000L;
        return true;
    }

    public static void clearActiveSession() {
        sActiveSessionToken = "";
        sActiveRollingToken = "";
        sServerVerifiedSeal = "";
        sSessionLeaseExpiresAtMs = 0L;
        sDecryptedRuntimeParams.clear();
        try {
            File f = getSessionCacheFile();
            if (f.exists()) f.delete();
        } catch (Throwable ignored) {
        }
    }

    static void updateRollingTokenFromHeartbeat(String nextRollingToken, long newLeaseExpiresSec) {
        if (nextRollingToken != null && !nextRollingToken.isEmpty()) {
            sActiveRollingToken = nextRollingToken;
        }
        if (newLeaseExpiresSec > 0L) {
            sSessionLeaseExpiresAtMs = newLeaseExpiresSec * 1000L;
        }
        sLastSuccessfulHeartbeatMs = System.currentTimeMillis();
        String hwid = getHardwareDeviceId(FeaturesticBoxCore.getContext());
        sServerVerifiedSeal = computeHmacHex(
                sActiveSessionToken + "|" + sActiveRollingToken + "|" + hwid + "|" + sSessionLeaseExpiresAtMs,
                new String(BOOT_SECRET, StandardCharsets.ISO_8859_1));
        persistSessionState();
    }

    public static String getActiveSessionToken() {
        loadSessionFromDiskIfNeeded();
        return sActiveSessionToken;
    }

    public static String getActiveRollingToken() {
        loadSessionFromDiskIfNeeded();
        return sActiveRollingToken;
    }

    private static void loadSessionFromDiskIfNeeded() {
        if (sActiveSessionToken != null && !sActiveSessionToken.isEmpty()) {
            return;
        }
        try {
            File f = getSessionCacheFile();
            if (f.isFile() && f.length() > 0) {
                byte[] buf = new byte[(int) Math.min(f.length(), 1024L)];
                try (FileInputStream fis = new FileInputStream(f)) {
                    int n = fis.read(buf);
                    if (n > 0) {
                        String[] parts = new String(buf, 0, n, StandardCharsets.UTF_8).split("\\|");
                        if (parts.length >= 5) {
                            String tok = parts[0];
                            long expMs = Long.parseLong(parts[1]);
                            String key = parts[2];
                            String roll = parts[3];
                            String mac = parts[4].trim();
                            String hwid = getHardwareDeviceId(FeaturesticBoxCore.getContext());
                            String expectedMac = computeHmacHex(tok + "|" + roll + "|" + expMs + "|" + hwid, sServerApiSecret);
                            if (constantTimeEquals(expectedMac, mac) && System.currentTimeMillis() < expMs) {
                                sActiveSessionToken = tok;
                                sSessionLeaseExpiresAtMs = expMs;
                                sActiveLicenseKey = key;
                                sActiveRollingToken = roll;
                                sServerVerifiedSeal = computeHmacHex(
                                        sActiveSessionToken + "|" + sActiveRollingToken + "|" + hwid + "|" + sSessionLeaseExpiresAtMs,
                                        new String(BOOT_SECRET, StandardCharsets.ISO_8859_1));
                            }
                        }
                    }
                }
            }
        } catch (Throwable ignored) {
        }
    }

    public static String getActiveLicenseKey() {
        return sActiveLicenseKey;
    }

        public static void setDecryptedRuntimeParam(String key, String value) {
        if (key != null && !key.trim().isEmpty() && value != null) {
            sDecryptedRuntimeParams.put(key.trim(), value.trim());
        }
    }

    public static String getDecryptedRuntimeParam(String key, String defaultValue) {
        if (key == null) return defaultValue;
        String val = sDecryptedRuntimeParams.get(key);
        return val != null ? val : defaultValue;
    }

    public static long getDecryptedOffsetLong(String key, long defaultOffset) {
        return getDecryptedOffsetLong(key, null, defaultOffset);
    }

    public static long getDecryptedOffsetLong(String key, String gameVersion, long defaultOffset) {
        if (key == null) return defaultOffset;
        String val = null;
        if (gameVersion != null && !gameVersion.trim().isEmpty()) {
            String cleanVer = gameVersion.trim().replaceAll("[^a-zA-Z0-9._-]", "_");
            val = getDecryptedRuntimeParam("ver_" + cleanVer + "_" + key, null);
            if (val == null) {
                String underscored = cleanVer.replace('.', '_');
                val = getDecryptedRuntimeParam("ver_" + underscored + "_" + key, null);
            }
        }
        if (val == null || val.trim().isEmpty()) {
            val = getDecryptedRuntimeParam(key, null);
        }
        if (val == null || val.trim().isEmpty()) return defaultOffset;
        try {
            String clean = val.trim().toLowerCase(Locale.US);
            if (clean.startsWith("0x")) {
                return Long.parseUnsignedLong(clean.substring(2), 16);
            }
            return Long.parseLong(clean);
        } catch (Throwable ignored) {
            return defaultOffset;
        }
    }

    public static boolean stampLaunchIntentProof(Context context, Intent splashIntent, String packageName, int userId) {
        if (splashIntent == null) return false;
        if (context == null) {
            context = FeaturesticBoxCore.getContext();
        }
        if (isDeviceBanned(context)) {
            showMainThreadToast(context, getDeviceBanReason());
            return false;
        }
        String tamper = detectTamperViolation(context);
        if (tamper != null && sAutoBanOnTamper) {
            reportTamperAndBanDevice(context, tamper, "Detected during game launch");
            showMainThreadToast(context, "Device banned due to tamper detection: " + tamper);
            return false;
        }
        if (sRequireLoaderLoginGate && !isSessionAuthorizedAndIntact(context)) {
            if (sEmbeddedSdkLicenseKey == null || sEmbeddedSdkLicenseKey.isEmpty()) {
                if (sAutoBanOnTamper) {
                    reportTamperAndBanDevice(context, "LOGIN_GATE_BYPASS_ATTEMPT", "Launch attempted without valid server session lease");
                }
                showMainThreadToast(context, "Unauthorized launch blocked! SDK License Key not authenticated.");
                return false;
            }
        }

        if (!isGamePackageAuthorized(packageName)) {
            if (sAutoBanOnTamper) {
                reportTamperAndBanDevice(context, "UNAUTHORIZED_GAME_LAUNCH", "Package '" + packageName + "' is not permitted under this license");
            }
            showMainThreadToast(context, "Access Denied: Game package '" + packageName + "' is not authorized for your license.");
            return false;
        }

        if (!verifyAppPluginsIntegrity(packageName)) {
            if (sAutoBanOnTamper) {
                reportTamperAndBanDevice(context, "UNAUTHORIZED_OFFLINE_SO_INJECTION", "Tampered or unauthorized offline .so found in " + packageName);
            }
            showMainThreadToast(context, "Security Violation: Unauthorized offline plugin injection detected!");
            return false;
        }
        String nonce = generateRandomNonceHex() + "_" + userId;
        String liveSha = getLiveApkSigningSha256(context);
        String pkg = packageName != null ? packageName : "";
        String proofKey = sRequireLoaderLoginGate
                ? (sActiveSessionToken + "|" + sActiveRollingToken + "|" + sServerApiSecret)
                : sActiveSessionProof;
        String proof = computeHmacHex(nonce + "|" + pkg + "|" + userId + "|" + liveSha, proofKey);
        splashIntent.putExtra(EXTRA_LAUNCH_NONCE, nonce);
        splashIntent.putExtra(EXTRA_LAUNCH_HMAC_PROOF, proof);
        return true;
    }

    public static boolean verifyLaunchIntentProof(Context context, Intent splashIntent, String packageName, int userId) {
        if (isDeviceBanned(context)) {
            showMainThreadToast(context, getDeviceBanReason());
            return false;
        }
        if (splashIntent == null) return false;
        if (sRequireLoaderLoginGate) {
            if (!isSessionAuthorizedAndIntact(context) && (sEmbeddedSdkLicenseKey == null || sEmbeddedSdkLicenseKey.isEmpty())) {
                showMainThreadToast(context, "Session expired or unauthenticated! Launch denied.");
                return false;
            }
            String nonce = splashIntent.getStringExtra(EXTRA_LAUNCH_NONCE);
            String proof = splashIntent.getStringExtra(EXTRA_LAUNCH_HMAC_PROOF);
            if (nonce == null || proof == null || nonce.isEmpty() || proof.isEmpty()) {
                if (sAutoBanOnTamper) {
                    reportTamperAndBanDevice(context, "DIRECT_ACTIVITY_LAUNCH_BYPASS", "Missing launch intent HMAC proof");
                }
                return false;
            }
            String liveSha = getLiveApkSigningSha256(context != null ? context : FeaturesticBoxCore.getContext());
            String pkg = packageName != null ? packageName : "";
            String proofKey = sActiveSessionToken + "|" + sActiveRollingToken + "|" + sServerApiSecret;
            String expected = computeHmacHex(nonce + "|" + pkg + "|" + userId + "|" + liveSha, proofKey);
            if (!constantTimeEquals(expected, proof)) {
                if (sAutoBanOnTamper) {
                    reportTamperAndBanDevice(context, "FORGED_LAUNCH_INTENT_PROOF", "Invalid launch intent HMAC");
                }
                return false;
            }
        }

        if (!isGamePackageAuthorized(packageName)) {
            if (sAutoBanOnTamper) {
                reportTamperAndBanDevice(context, "UNAUTHORIZED_GAME_LAUNCH", "Direct launch of unauthorized package: " + packageName);
            }
            return false;
        }

        if (!verifyAppPluginsIntegrity(packageName)) {
            if (sAutoBanOnTamper) {
                reportTamperAndBanDevice(context, "UNAUTHORIZED_OFFLINE_SO_INJECTION", "Direct launch with tampered/offline .so in " + packageName);
            }
            return false;
        }

        return true;
    }

    public static boolean isSessionAuthorizedAndIntact(Context context) {
        if (isDeviceBanned(context)) return false;
        if (!sRequireLoaderLoginGate) return true;
        String tok = getActiveSessionToken();
        String roll = getActiveRollingToken();
        if (tok == null || tok.isEmpty() || roll == null || roll.isEmpty()) return false;
        if (System.currentTimeMillis() >= sSessionLeaseExpiresAtMs) {
            long now = System.currentTimeMillis();
            boolean isGameRunning = com.featurestic.box.app.BActivityThread.isThreadInit();
            if (isGameRunning && sLastSuccessfulHeartbeatMs > 0L && (now - sLastSuccessfulHeartbeatMs) < sMatchGraceToleranceMs) {
                String hwid = getHardwareDeviceId(context);
                String expectedSeal = computeHmacHex(
                        tok + "|" + roll + "|" + hwid + "|" + sSessionLeaseExpiresAtMs,
                        new String(BOOT_SECRET, StandardCharsets.ISO_8859_1));
                return constantTimeEquals(expectedSeal, sServerVerifiedSeal);
            }
            return false;
        }
        String hwid = getHardwareDeviceId(context);
        String expectedSeal = computeHmacHex(
                tok + "|" + roll + "|" + hwid + "|" + sSessionLeaseExpiresAtMs,
                new String(BOOT_SECRET, StandardCharsets.ISO_8859_1));
        return constantTimeEquals(expectedSeal, sServerVerifiedSeal);
    }

    public static boolean verifyEnvironmentAndApkIntegrity(Context context) {
        if (isDeviceBanned(context)) return false;
        String tamper = detectTamperViolation(context);
        if (tamper != null && sAutoBanOnTamper) {
            reportTamperAndBanDevice(context, tamper, "Integrity check failed");
            return false;
        }
        return true;
    }

    public static String getLiveApkSigningSha256(Context context) {
        if (context == null) return "HOST_UNAVAILABLE";
        try {
            PackageManager pm = context.getPackageManager();
            String pkg = context.getPackageName();
            int flags = Build.VERSION.SDK_INT >= 28
                    ? PackageManager.GET_SIGNING_CERTIFICATES
                    : PackageManager.GET_SIGNATURES;
            PackageInfo pi = pm.getPackageInfo(pkg, flags);
            Signature[] sigs = null;
            if (Build.VERSION.SDK_INT >= 28 && pi != null) {
                SigningInfo si = pi.signingInfo;
                if (si != null) {
                    sigs = si.getApkContentsSigners();
                }
            } else if (pi != null) {
                sigs = pi.signatures;
            }
            if (sigs != null && sigs.length > 0 && sigs[0] != null) {
                MessageDigest md = MessageDigest.getInstance("SHA-256");
                return bytesToHex(md.digest(sigs[0].toByteArray()));
            }
        } catch (Throwable ignored) {
        }
        return "UNSIGNED_DEV";
    }

    public static String[] encryptAesCbcPayload(String plaintextJson, String keySeed) {
        try {
            byte[] keyBytes = sha256Bytes(keySeed);
            byte[] ivBytes = new byte[16];
            SECURE_RANDOM.nextBytes(ivBytes);
            Cipher cipher = Cipher.getInstance("AES/CBC/PKCS5Padding");
            cipher.init(Cipher.ENCRYPT_MODE, new SecretKeySpec(keyBytes, "AES"), new IvParameterSpec(ivBytes));
            byte[] cipherBytes = cipher.doFinal(plaintextJson.getBytes(StandardCharsets.UTF_8));
            return new String[]{
                    Base64.encodeToString(cipherBytes, Base64.NO_WRAP),
                    Base64.encodeToString(ivBytes, Base64.NO_WRAP)
            };
        } catch (Throwable ignored) {
            return null;
        }
    }

    public static String decryptAesCbcPayload(String base64Ciphertext, String base64Iv, String keySeed) {
        try {
            byte[] keyBytes = sha256Bytes(keySeed);
            byte[] ivBytes = Base64.decode(base64Iv, Base64.DEFAULT);
            byte[] cipherBytes = Base64.decode(base64Ciphertext, Base64.DEFAULT);
            Cipher cipher = Cipher.getInstance("AES/CBC/PKCS5Padding");
            cipher.init(Cipher.DECRYPT_MODE, new SecretKeySpec(keyBytes, "AES"), new IvParameterSpec(ivBytes));
            byte[] plain = cipher.doFinal(cipherBytes);
            return new String(plain, StandardCharsets.UTF_8);
        } catch (Throwable ignored) {
            return null;
        }
    }

    static boolean constantTimeEquals(String a, String b) {
        if (a == null || b == null) return false;
        byte[] ba = a.toUpperCase(Locale.US).getBytes(StandardCharsets.UTF_8);
        byte[] bb = b.toUpperCase(Locale.US).getBytes(StandardCharsets.UTF_8);
        return MessageDigest.isEqual(ba, bb);
    }

    static String buildApiUrl(String baseUrl, String action) {
        String trimmed = baseUrl.trim();
        if (trimmed.contains("?")) {
            return trimmed + "&action=" + action;
        }
        if (trimmed.endsWith("/login.php") || trimmed.endsWith("/ban_check.php") || trimmed.endsWith("/token_validation.php")) {
            int slash = trimmed.lastIndexOf('/');
            trimmed = trimmed.substring(0, slash + 1) + "api.php";
        }
        if (!trimmed.endsWith(".php") && !trimmed.endsWith("/")) {
            return trimmed + "/api.php?action=" + action;
        }
        if (trimmed.endsWith("/")) {
            return trimmed + "api.php?action=" + action;
        }
        return trimmed + "?action=" + action;
    }

    static String extractJsonString(String json, String key) {
        if (json == null || key == null) return "";
        String pattern = "\"" + key + "\"";
        int idx = json.indexOf(pattern);
        if (idx < 0) return "";
        int colon = json.indexOf(':', idx + pattern.length());
        if (colon < 0) return "";
        int i = colon + 1;
        while (i < json.length() && Character.isWhitespace(json.charAt(i))) i++;
        if (i >= json.length()) return "";
        char c = json.charAt(i);
        if (c == '"') {
            StringBuilder sb = new StringBuilder();
            i++;
            while (i < json.length()) {
                char ch = json.charAt(i++);
                if (ch == '\\' && i < json.length()) {
                    char esc = json.charAt(i++);
                    if (esc == 'n') sb.append('\n');
                    else if (esc == 'r') sb.append('\r');
                    else if (esc == 't') sb.append('\t');
                    else sb.append(esc);
                } else if (ch == '"') {
                    break;
                } else {
                    sb.append(ch);
                }
            }
            return sb.toString();
        } else {
            int end = i;
            while (end < json.length() && ",}".indexOf(json.charAt(end)) < 0 && !Character.isWhitespace(json.charAt(end))) {
                end++;
            }
            return json.substring(i, end).trim();
        }
    }

    static String escapeJson(String s) {
        if (s == null) return "";
        return s.replace("\\", "\\\\").replace("\"", "\\\"").replace("\n", "\\n").replace("\r", "");
    }

    private static void showMainThreadToast(final Context context, final String message) {
        if (context == null || message == null || message.isEmpty()) return;
        new Handler(Looper.getMainLooper()).post(new Runnable() {
            @Override
            public void run() {
                try {
                    Toast.makeText(context, message, Toast.LENGTH_LONG).show();
                } catch (Throwable ignored) {
                }
            }
        });
    }

    static String computeHmacHex(String data, String key) {
        try {
            Mac mac = Mac.getInstance("HmacSHA256");
            SecretKeySpec spec = new SecretKeySpec(sha256Bytes(key != null ? key : ""), "HmacSHA256");
            mac.init(spec);
            return bytesToHex(mac.doFinal(data.getBytes(StandardCharsets.UTF_8)));
        } catch (Throwable ignored) {
            return sha256Hex(data + "|" + key);
        }
    }

    private static byte[] sha256Bytes(String input) {
        try {
            return MessageDigest.getInstance("SHA-256").digest(input.getBytes(StandardCharsets.UTF_8));
        } catch (Throwable ignored) {
            return new byte[32];
        }
    }

    private static String sha256Hex(String input) {
        return bytesToHex(sha256Bytes(input));
    }

    private static String bytesToHex(byte[] bytes) {
        if (bytes == null) return "";
        StringBuilder sb = new StringBuilder(bytes.length * 2);
        for (byte b : bytes) {
            sb.append(String.format("%02X", b));
        }
        return sb.toString();
    }

    public static void setMatchGraceToleranceMs(long ms) {
        if (ms > 0L) sMatchGraceToleranceMs = ms;
    }

    public static long getMatchGraceToleranceMs() {
        return sMatchGraceToleranceMs;
    }

    public static boolean isSessionInGracePeriod() {
        if (sSessionLeaseExpiresAtMs <= 0L) return false;
        long now = System.currentTimeMillis();
        return now >= sSessionLeaseExpiresAtMs && sLastSuccessfulHeartbeatMs > 0L && (now - sLastSuccessfulHeartbeatMs) < sMatchGraceToleranceMs;
    }


    private static String getTeeHardwareKeyThumbprint() {
        try {
            java.security.KeyStore ks = java.security.KeyStore.getInstance("AndroidKeyStore");
            ks.load(null);
            String alias = "fbox_tee_hw_anchor_v1";
            if (ks.containsAlias(alias)) {
                java.security.cert.Certificate cert = ks.getCertificate(alias);
                if (cert != null && cert.getPublicKey() != null) {
                    return sha256Hex(bytesToHex(cert.getPublicKey().getEncoded()));
                }
            }
        } catch (Throwable ignored) {
        }
        return "TEE_STD";
    }

    public static boolean ensureEmbeddedSdkKeyAuthenticatedSync(final Context context, long timeoutMs) {
        if (isSessionAuthorizedAndIntact(context)) {
            return true;
        }
        final String sdkKey = sEmbeddedSdkLicenseKey;
        if (sdkKey == null || sdkKey.isEmpty()) {
            return false;
        }
        final java.util.concurrent.CountDownLatch latch = new java.util.concurrent.CountDownLatch(1);
        final boolean[] ok = new boolean[]{false};
        authenticateLicenseWithPanel(context, sdkKey, new PanelAuthCallback() {
            @Override
            public void onResult(boolean success, String status, String message, Map<String, String> decryptedRuntimeParams) {
                ok[0] = success;
                latch.countDown();
            }
        });
        try {
            latch.await(timeoutMs, java.util.concurrent.TimeUnit.MILLISECONDS);
        } catch (InterruptedException ignored) {
        }
        return ok[0] && isSessionAuthorizedAndIntact(context);
    }

}
