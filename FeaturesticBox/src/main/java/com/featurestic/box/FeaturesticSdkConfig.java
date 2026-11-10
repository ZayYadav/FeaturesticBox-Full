package com.featurestic.box;

import android.content.Context;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.nio.charset.StandardCharsets;
import java.util.Arrays;

import com.featurestic.box.core.RNative;
import com.featurestic.box.core.env.BEnvironment;
import com.featurestic.box.utils.compat.DeviceProfileCompat;
import com.featurestic.box.utils.compat.ThermalAndFrameGuard;

/**
 * Unified Server-Driven SDK Configuration, Challenge-Response Authentication Bridge,
 * TLS Certificate Pinning, Anti-Crack Ban Enforcer & Engine-Ready .so Plugin Stager.
 */
public final class FeaturesticSdkConfig {
    private Integer mTargetFps;
    private Integer mIpadViewMode;
    private Boolean mStreamproof;
    private Boolean mBuiltInFpsHud;
    private Boolean mHideXposed;
    private String mDevicePreset;
    private String mLockedCertSha256;
    private String mPinnedTlsCertSha256;
    private Boolean mRequireLoginGate;
    private Boolean mAutoBanOnTamper;
    private Boolean mBlockHttpProxy;
    private Boolean mBlockRootMagisk;
    private Boolean mBlockEmulator;
    private String mServerGatewayUrl;
    private String mBootstrapUrl;
    private String mServerApiSecret;
    private Integer mIsolatedUserSlot;
    private String mLoaderId;
    private String mSdkLicenseKey;
    private String[] mFallbackGatewayUrls;
    private Boolean mEnableWatermark;
    private String mWatermarkText;
    private Integer mLoaderVersion;
    private Boolean mEnableTelemetrySilencer = true;
    private Integer mOfflineGraceSeconds = 300;

    private FeaturesticSdkConfig() {
    }

    public static FeaturesticSdkConfig create() {
        return new FeaturesticSdkConfig();
    }

    public FeaturesticSdkConfig setSdkLicenseKey(String sdkKey) {
        this.mSdkLicenseKey = sdkKey;
        return this;
    }

    public FeaturesticSdkConfig setLicenseKey(String sdkKey) {
        this.mSdkLicenseKey = sdkKey;
        return this;
    }

    public FeaturesticSdkConfig setLoaderId(String loaderId) {
        this.mLoaderId = loaderId;
        return this;
    }

    public FeaturesticSdkConfig setFallbackGatewayUrls(String... fallbackUrls) {
        this.mFallbackGatewayUrls = fallbackUrls;
        return this;
    }

    public FeaturesticSdkConfig setWatermarkEnabled(boolean enabled) {
        this.mEnableWatermark = enabled;
        return this;
    }

    public FeaturesticSdkConfig setWatermarkText(String text) {
        this.mWatermarkText = text;
        return this;
    }

    public FeaturesticSdkConfig setLoaderVersion(int version) {
        this.mLoaderVersion = version;
        return this;
    }

    public FeaturesticSdkConfig setAnticheatTelemetrySilencerEnabled(boolean enabled) {
        this.mEnableTelemetrySilencer = enabled;
        return this;
    }

    public FeaturesticSdkConfig setOfflineGraceToleranceSeconds(int seconds) {
        this.mOfflineGraceSeconds = seconds;
        return this;
    }

    public FeaturesticSdkConfig setServerGatewayUrl(String panelApiUrl) {
        this.mServerGatewayUrl = panelApiUrl;
        return this;
    }

    public FeaturesticSdkConfig setBootstrapConfigUrl(String bootstrapJsonUrl) {
        this.mBootstrapUrl = bootstrapJsonUrl;
        return this;
    }

    public FeaturesticSdkConfig setServerApiSecret(String sharedSecret) {
        this.mServerApiSecret = sharedSecret;
        return this;
    }

    public FeaturesticSdkConfig pinServerCertSha256(String tlsCertSha256Hex) {
        this.mPinnedTlsCertSha256 = tlsCertSha256Hex;
        return this;
    }

    public FeaturesticSdkConfig setAutoBanOnTamper(boolean enabled) {
        this.mAutoBanOnTamper = enabled;
        return this;
    }

    public FeaturesticSdkConfig setBlockHttpProxy(boolean block) {
        this.mBlockHttpProxy = block;
        return this;
    }

    public FeaturesticSdkConfig setBlockRootMagisk(boolean block) {
        this.mBlockRootMagisk = block;
        return this;
    }

    public FeaturesticSdkConfig setBlockEmulator(boolean block) {
        this.mBlockEmulator = block;
        return this;
    }

    public FeaturesticSdkConfig setTargetFps(int fps) {
        this.mTargetFps = fps;
        return this;
    }

    public FeaturesticSdkConfig setIpadViewMode(int mode) {
        this.mIpadViewMode = mode;
        return this;
    }

    public FeaturesticSdkConfig setStreamproof(boolean enabled) {
        this.mStreamproof = enabled;
        return this;
    }

    public FeaturesticSdkConfig setBuiltInFpsHud(boolean enabled) {
        this.mBuiltInFpsHud = enabled;
        return this;
    }

    public FeaturesticSdkConfig setHideXposed(boolean hide) {
        this.mHideXposed = hide;
        return this;
    }

    public FeaturesticSdkConfig setDevicePreset(String preset) {
        this.mDevicePreset = preset;
        return this;
    }

    public FeaturesticSdkConfig setIsolatedUserSlot(int userId) {
        this.mIsolatedUserSlot = userId;
        return this;
    }

    public FeaturesticSdkConfig lockHostSignatureSha256(String sha256Hex) {
        this.mLockedCertSha256 = sha256Hex;
        return this;
    }

    public FeaturesticSdkConfig requireLoginGate(boolean required) {
        this.mRequireLoginGate = required;
        return this;
    }

    public void apply() {
        try {
            if (mLoaderId != null && !mLoaderId.trim().isEmpty()) {
                FeaturesticAntiCrackVault.setLoaderId(mLoaderId.trim());
            }
            if (mSdkLicenseKey != null && !mSdkLicenseKey.trim().isEmpty()) {
                FeaturesticAntiCrackVault.setEmbeddedSdkLicenseKey(mSdkLicenseKey.trim());
            }
            if (mServerApiSecret != null && !mServerApiSecret.trim().isEmpty()) {
                FeaturesticAntiCrackVault.setServerApiSecret(mServerApiSecret.trim());
            }
            if (mPinnedTlsCertSha256 != null) {
                FeaturesticAntiCrackVault.pinServerCertificateSha256(mPinnedTlsCertSha256);
            }
            if (mAutoBanOnTamper != null) {
                FeaturesticAntiCrackVault.setAutoBanOnTamper(mAutoBanOnTamper);
            }
            if (mBlockHttpProxy != null) {
                FeaturesticAntiCrackVault.setBlockHttpProxy(mBlockHttpProxy);
            }
            if (mBlockRootMagisk != null) {
                FeaturesticAntiCrackVault.setBlockRootMagisk(mBlockRootMagisk);
            }
            if (mBlockEmulator != null) {
                FeaturesticAntiCrackVault.setBlockEmulator(mBlockEmulator);
            }
            if (mFallbackGatewayUrls != null && mFallbackGatewayUrls.length > 0) {
                FeaturesticRemoteConfigGuard.setFallbackGatewayUrls(mFallbackGatewayUrls);
            }
            if (mEnableWatermark != null) {
                FeaturesticWatermarkGuard.setWatermarkEnabled(mEnableWatermark);
            }
            if (mWatermarkText != null && !mWatermarkText.trim().isEmpty()) {
                FeaturesticWatermarkGuard.setWatermarkText(mWatermarkText.trim());
            }
            if (mLoaderVersion != null && mLoaderVersion > 0) {
                setMinLoaderVersion(mLoaderVersion);
            }
            if (mServerGatewayUrl != null && !mServerGatewayUrl.trim().isEmpty()) {
                FeaturesticRemoteConfigGuard.setRemoteConfigUrl(mServerGatewayUrl.trim());
                if (mRequireLoginGate == null) {
                    FeaturesticAntiCrackVault.requireLoaderLoginGate(true);
                }
            }
            if (mBootstrapUrl != null && !mBootstrapUrl.trim().isEmpty()) {
                FeaturesticRemoteConfigGuard.setBootstrapEndpointUrl(mBootstrapUrl.trim());
                if (mRequireLoginGate == null) {
                    FeaturesticAntiCrackVault.requireLoaderLoginGate(true);
                }
            }
            if (mLockedCertSha256 != null && !mLockedCertSha256.trim().isEmpty()) {
                FeaturesticAntiCrackVault.lockReleaseSignatureSha256(mLockedCertSha256);
            }
            if (mRequireLoginGate != null) {
                FeaturesticAntiCrackVault.requireLoaderLoginGate(mRequireLoginGate);
            }
            if (mHideXposed != null) {
                FeaturesticBoxCore.setHideXposed(mHideXposed);
            }
            if (mBuiltInFpsHud != null) {
                FeaturesticOverlayManager.setBuiltInFpsOverlayEnabled(mBuiltInFpsHud);
            } else {
                FeaturesticOverlayManager.setBuiltInFpsOverlayEnabled(false);
            }
            if (mStreamproof != null) {
                FeaturesticBoxCore.get().setStreamproofHideOverlaysEnabled(mStreamproof);
            }
            if (mIpadViewMode != null) {
                FeaturesticBoxCore.get().setIpadViewMode(mIpadViewMode);
            }
            if (mTargetFps != null) {
                ThermalAndFrameGuard.setTargetFps(mTargetFps);
            }
            if (mDevicePreset != null && !mDevicePreset.trim().isEmpty()) {
                DeviceProfileCompat.setPreset(mDevicePreset.trim());
            }
            if (mIsolatedUserSlot != null && mIsolatedUserSlot > 0) {
                applyIsolatedProfileForUserSlot(mIsolatedUserSlot);
            }
            FeaturesticCrashReporter.install(FeaturesticBoxCore.getContext());
            if (mEnableTelemetrySilencer == null || mEnableTelemetrySilencer) {
                com.featurestic.box.utils.compat.FeaturesticTelemetrySilencer.startBackgroundSilencerDaemon(FeaturesticBoxCore.getContext(), "");
            }
            if (mOfflineGraceSeconds != null && mOfflineGraceSeconds > 0) {
                FeaturesticAntiCrackVault.setMatchGraceToleranceMs(mOfflineGraceSeconds * 1000L);
            }
            ThermalAndFrameGuard.applyLowEndMemoryTrimAndDiet(FeaturesticBoxCore.getContext());
            ThermalAndFrameGuard.boostRenderAndGameThreads();
            if (mSdkLicenseKey != null && !mSdkLicenseKey.trim().isEmpty()) {
                FeaturesticAntiCrackVault.authenticateLicenseWithPanel(FeaturesticBoxCore.getContext(), mSdkLicenseKey.trim(), null);
            } else {
                FeaturesticRemoteConfigGuard.syncRemoteConfigAsync(FeaturesticBoxCore.getContext());
            }
        } catch (Throwable ignored) {
        }
    }

    public static void authenticateWithPanel(
            Context context, String licenseKey, FeaturesticAntiCrackVault.PanelAuthCallback callback) {
        FeaturesticAntiCrackVault.authenticateLicenseWithPanel(context, licenseKey, callback);
    }

    public static void authenticateUserWithPanel(
            Context context,
            String username,
            String password,
            String licenseKey,
            FeaturesticAntiCrackVault.PanelAuthCallback callback) {
        FeaturesticAntiCrackVault.authenticateCredentialsWithPanel(context, username, password, licenseKey, callback);
    }

    public static void checkBanStatusWithPanel(
            Context context, FeaturesticAntiCrackVault.BanCheckCallback callback) {
        FeaturesticAntiCrackVault.checkBanStatusWithPanel(context, callback);
    }

    public static String getHardwareId(Context context) {
        return FeaturesticAntiCrackVault.getHardwareDeviceId(context);
    }

    public static boolean isDeviceBanned(Context context) {
        return FeaturesticAntiCrackVault.isDeviceBanned(context);
    }

    public static String getServerRuntimeParam(String key, String defaultValue) {
        return FeaturesticAntiCrackVault.getDecryptedRuntimeParam(key, defaultValue);
    }

    public static long getServerOffset(String key, long defaultOffset) {
        return FeaturesticAntiCrackVault.getDecryptedOffsetLong(key, defaultOffset);
    }

    public static long getServerOffset(String key, String gameVersion, long defaultOffset) {
        return FeaturesticAntiCrackVault.getDecryptedOffsetLong(key, gameVersion, defaultOffset);
    }

    public static String resolveDomainViaDoH(String domain) {
        return FeaturesticAntiCrackVault.resolveDomainViaDoH(domain);
    }

    public static void applyIsolatedProfileForUserSlot(int userId) {
        try {
            String suffix = String.valueOf(Math.max(0, userId));
            DeviceProfileCompat.setCustomProfile(
                    "ASUS",
                    "ASUS",
                    "ASUS_AI2401_A",
                    "AI2401_" + suffix,
                    "qcom",
                    "ASUS/WW_AI2401/AI2401:14/UKQ1.231003.002/34.1420.1420.40" + suffix + ":user/release-keys"
            );
        } catch (Throwable ignored) {
        }
    }

    public static boolean isCompatibleElfBytes(byte[] elfBytes) {
        if (elfBytes == null || elfBytes.length < 20) return false;
        if (elfBytes[0] != 0x7F || elfBytes[1] != 'E' || elfBytes[2] != 'L' || elfBytes[3] != 'F') {
            return false;
        }
        int eiClass = elfBytes[4] & 0xFF;
        boolean is64 = FeaturesticBoxCore.is64Bit();
        return is64 ? (eiClass == 2) : (eiClass == 1);
    }

    public static boolean isCompatibleElfFile(File soFile) {
        if (soFile == null || !soFile.isFile() || soFile.length() < 64) return false;
        byte[] header = new byte[20];
        try (FileInputStream fis = new FileInputStream(soFile)) {
            if (fis.read(header) < 20) return false;
            return isCompatibleElfBytes(header);
        } catch (Throwable ignored) {
            return false;
        }
    }

    private static void enforceWxReadExecPermissions(File f) {
        if (f == null || !f.exists()) return;
        f.setReadable(true, false);
        f.setExecutable(true, false);
        f.setWritable(false, false);
    }

    public static File stagePluginBytes(String pluginName, byte[] elfBytes, String targetGamePkg) {
        if (!isCompatibleElfBytes(elfBytes)) {
            return null;
        }
        // Strict Server Cryptographic Authorization Gate:
        // Offline injection is strictly blocked unless the .so matches the server's signed hash!
        if (!FeaturesticAntiCrackVault.isPluginBytesAuthorized(elfBytes)) {
            FeaturesticAntiCrackVault.reportTamperAndBanDevice(
                    FeaturesticBoxCore.getContext(),
                    "UNAUTHORIZED_OFFLINE_SO_INJECTION",
                    "Attempted to stage unauthorized offline .so: " + pluginName);
            return null;
        }
        String cleanName = (pluginName != null && !pluginName.trim().isEmpty())
                ? pluginName.trim().replaceAll("[^a-zA-Z0-9._-]", "_")
                : "libFeaturesticPlugin.so";
        if (!cleanName.startsWith("lib")) cleanName = "lib" + cleanName;
        if (!cleanName.endsWith(".so")) cleanName = cleanName + ".so";

        try {
            File sysDir = BEnvironment.getSystemDir();
            if (sysDir != null) {
                if (!sysDir.exists()) sysDir.mkdirs();
                File autoPlugin = new File(sysDir, "libFeaturesticPlugin.so");
                if (autoPlugin.exists()) autoPlugin.setWritable(true, true);
                try (FileOutputStream fos = new FileOutputStream(autoPlugin, false)) {
                    fos.write(elfBytes);
                    fos.flush();
                }
                enforceWxReadExecPermissions(autoPlugin);
            }

            File targetDir = (targetGamePkg != null && !targetGamePkg.trim().isEmpty())
                    ? BEnvironment.getAppLibDir(targetGamePkg.trim())
                    : sysDir;
            if (targetDir == null) return null;
            if (!targetDir.exists()) targetDir.mkdirs();

            File outSo = new File(targetDir, cleanName);
            if (outSo.exists()) {
                outSo.setWritable(true, true);
            }
            try (FileOutputStream fos = new FileOutputStream(outSo, false)) {
                fos.write(elfBytes);
                fos.flush();
            }
            enforceWxReadExecPermissions(outSo);
            try {
                RNative.setNativeProp("debug.featurestic.refresh_shield", "1");
            } catch (Throwable ignored) {
            }
            return outSo;
        } catch (Throwable ignored) {
            return null;
        } finally {
            Arrays.fill(elfBytes, (byte) 0);
        }
    }

    public static File stagePluginFile(File srcSoFile, String targetGamePkg) {
        if (!isCompatibleElfFile(srcSoFile)) return null;
        try {
            byte[] buf = new byte[(int) srcSoFile.length()];
            try (FileInputStream fis = new FileInputStream(srcSoFile)) {
                int off = 0;
                while (off < buf.length) {
                    int n = fis.read(buf, off, buf.length - off);
                    if (n <= 0) break;
                    off += n;
                }
            }
            return stagePluginBytes(srcSoFile.getName(), buf, targetGamePkg);
        } catch (Throwable ignored) {
            return null;
        }
    }

    public static File stageXorEncryptedPlugin(String pluginName, byte[] encryptedBytes, byte[] xorKey, String targetGamePkg) {
        if (encryptedBytes == null || encryptedBytes.length < 64 || xorKey == null || xorKey.length == 0) {
            return null;
        }
        byte[] plain = new byte[encryptedBytes.length];
        try {
            for (int i = 0; i < encryptedBytes.length; i++) {
                plain[i] = (byte) ((encryptedBytes[i] & 0xFF) ^ (xorKey[i % xorKey.length] & 0xFF));
            }
            return stagePluginBytes(pluginName, plain, targetGamePkg);
        } finally {
            Arrays.fill(plain, (byte) 0);
        }
    }

    public static int clearStagedPlugins(String targetGamePkg) {
        int removed = 0;
        try {
            File sysDir = BEnvironment.getSystemDir();
            if (sysDir != null && sysDir.isDirectory()) {
                for (String name : new String[]{"FeaturesticPlugin.so", "libFeaturesticPlugin.so"}) {
                    File f = new File(sysDir, name);
                    if (f.exists()) {
                        f.setWritable(true, true);
                        if (f.delete()) removed++;
                    }
                }
            }
            if (targetGamePkg != null && !targetGamePkg.trim().isEmpty()) {
                File appLibDir = BEnvironment.getAppLibDir(targetGamePkg.trim());
                if (appLibDir != null && appLibDir.isDirectory()) {
                    for (String name : new String[]{"FeaturesticPlugin.so", "libFeaturesticPlugin.so"}) {
                        File f = new File(appLibDir, name);
                        if (f.exists()) {
                            f.setWritable(true, true);
                            if (f.delete()) removed++;
                        }
                    }
                }
            }
        } catch (Throwable ignored) {
        }
        return removed;
    }

    public static String getLastNativeCrashReport() {
        try {
            File f = new File(BEnvironment.getSystemDir(), "featurestic_offset_crash.log");
            if (!f.isFile() || f.length() == 0) return "";
            byte[] buf = new byte[(int) Math.min(f.length(), 8192L)];
            try (FileInputStream fis = new FileInputStream(f)) {
                int n = fis.read(buf);
                return (n > 0) ? new String(buf, 0, n, StandardCharsets.UTF_8) : "";
            }
        } catch (Throwable ignored) {
            return "";
        }
    }

    public static void clearLastNativeCrashReport() {
        try {
            File f = new File(BEnvironment.getSystemDir(), "featurestic_offset_crash.log");
            if (f.exists()) f.delete();
        } catch (Throwable ignored) {
        }
    }

    public static int healCorruptedVirtualObb(String packageName) {
        if (packageName == null || packageName.trim().isEmpty()) return 0;
        int removed = 0;
        try {
            File virtualObbDir = BEnvironment.getExternalObbDir(packageName);
            File hostObbDir = BEnvironment.getHostExternalObbDir(packageName);
            if (virtualObbDir == null || !virtualObbDir.isDirectory()) return 0;
            File[] vFiles = virtualObbDir.listFiles();
            if (vFiles == null) return 0;
            for (File vf : vFiles) {
                if (vf == null || !vf.isFile() || !vf.getName().endsWith(".obb")) continue;
                File hf = (hostObbDir != null) ? new File(hostObbDir, vf.getName()) : null;
                boolean corrupted = (vf.length() < 50L * 1024L * 1024L)
                        || (hf != null && hf.isFile() && hf.length() > vf.length());
                if (corrupted && vf.delete()) {
                    removed++;
                }
            }
        } catch (Throwable ignored) {
        }
        return removed;
    }
    private static volatile String sAnnouncement = "";
    private static volatile String sUpdateApkUrl = "";
    private static volatile int sMinLoaderVersion = 1;

    public static void setServerAnnouncement(String announcement) {
        sAnnouncement = announcement != null ? announcement.trim() : "";
    }

    public static String getServerAnnouncement() {
        return sAnnouncement;
    }

    public static void setUpdateApkUrl(String url) {
        sUpdateApkUrl = url != null ? url.trim() : "";
    }

    public static String getUpdateApkUrl() {
        return sUpdateApkUrl;
    }

    public static void setMinLoaderVersion(int version) {
        sMinLoaderVersion = Math.max(1, version);
    }

    public static int getMinLoaderVersion() {
        return sMinLoaderVersion;
    }

    public static boolean isUpdateRequired(int currentAppVersionCode) {
        return sMinLoaderVersion > 1 && currentAppVersionCode < sMinLoaderVersion && !sUpdateApkUrl.isEmpty();
    }

    public static boolean isFeatureEnabled(String featureName, boolean defaultValue) {
        if (featureName == null) return defaultValue;
        String val = getServerRuntimeParam("feature_" + featureName, null);
        if (val == null) {
            val = getServerRuntimeParam(featureName, null);
        }
        if (val == null) return defaultValue;
        return "1".equals(val) || "true".equalsIgnoreCase(val) || "on".equalsIgnoreCase(val);
    }

    public static String getLoaderId() {
        return FeaturesticAntiCrackVault.getLoaderId();
    }

    /**
     * Attaches the 100% Panel-Controlled Calculator Decoy Disguise screen if enabled by the Buyer/Admin in the Panel.
     * End-users see zero toggles; if Decoy Mode is OFF on the panel, onUnlockedCallback runs immediately.
     */
    

}
