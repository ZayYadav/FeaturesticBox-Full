package com.featurestic.box;

import android.app.Activity;
import android.content.Context;
import android.os.Build;
import android.os.Handler;
import android.os.Looper;
import android.widget.Toast;

import androidx.annotation.Keep;

import com.featurestic.box.app.BActivityThread;
import com.featurestic.box.core.env.BEnvironment;
import com.featurestic.box.utils.FileUtils;
import com.featurestic.box.utils.compat.DeviceProfileCompat;
import com.featurestic.box.utils.compat.FeaturesticGraphicsOptimizer;
import com.featurestic.box.utils.compat.ThermalAndFrameGuard;

import java.io.BufferedReader;
import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.Map;
import java.util.concurrent.atomic.AtomicBoolean;

/**
 * Dynamic Server Endpoint Controller, Rolling-Token Challenge-Response Heartbeat,
 * Continuous Anti-Debug Watchdog & Remote Kill-Switch Guard.
 */
@Keep
public final class FeaturesticRemoteConfigGuard {
    private static volatile String sRemoteConfigUrl = "";
    private static volatile String sBootstrapUrl = "";
    private static volatile boolean sMaintenanceActive = false;
    private static volatile String sMaintenanceNotice = "Server is currently under maintenance. Please try again shortly.";
    private static final AtomicBoolean sHeartbeatRunning = new AtomicBoolean(false);
    private static final AtomicBoolean sWatchdogRunning = new AtomicBoolean(false);
    private static final Handler MAIN_HANDLER = new Handler(Looper.getMainLooper());

    private FeaturesticRemoteConfigGuard() {
    }

    private static File getEndpointCacheFile() {
        return new File(BEnvironment.getSystemDir(), "featurestic_panel_endpoint.conf");
    }

    private static final java.util.List<String> sFallbackUrls = new java.util.concurrent.CopyOnWriteArrayList<String>();

    public static void setFallbackGatewayUrls(String... urls) {
        sFallbackUrls.clear();
        if (urls != null) {
            for (String u : urls) {
                if (u != null && !u.trim().isEmpty()) {
                    sFallbackUrls.add(u.trim());
                }
            }
        }
    }

    public static java.util.List<String> getCandidateGatewayUrls() {
        java.util.List<String> list = new java.util.ArrayList<String>();
        String prim = getRemoteConfigUrl();
        if (prim != null && !prim.isEmpty()) list.add(prim);
        for (String fb : sFallbackUrls) {
            if (!list.contains(fb)) list.add(fb);
        }
        return list;
    }

    public static void setRemoteConfigUrl(String url) {
        sRemoteConfigUrl = url != null ? url.trim() : "";
        if (!sRemoteConfigUrl.isEmpty()) {
            try {
                File f = getEndpointCacheFile();
                FileUtils.mkdirs(f.getParentFile());
                try (FileOutputStream fos = new FileOutputStream(f, false)) {
                    fos.write(sRemoteConfigUrl.getBytes(StandardCharsets.UTF_8));
                }
            } catch (Throwable ignored) {
            }
        }
    }

    public static void setBootstrapEndpointUrl(final String bootstrapJsonUrl) {
        sBootstrapUrl = bootstrapJsonUrl != null ? bootstrapJsonUrl.trim() : "";
        if (sBootstrapUrl.startsWith("http")) {
            new Thread(new Runnable() {
                @Override
                public void run() {
                    resolveDynamicEndpointFromBootstrap(sBootstrapUrl);
                }
            }, "Featurestic-Bootstrap").start();
        }
    }

    public static String getRemoteConfigUrl() {
        if (sRemoteConfigUrl != null && !sRemoteConfigUrl.isEmpty()) {
            return sRemoteConfigUrl;
        }
        try {
            File f = getEndpointCacheFile();
            if (f.isFile() && f.length() > 0 && f.length() < 1024) {
                byte[] buf = new byte[(int) f.length()];
                try (FileInputStream fis = new FileInputStream(f)) {
                    int n = fis.read(buf);
                    if (n > 0) {
                        sRemoteConfigUrl = new String(buf, 0, n, StandardCharsets.UTF_8).trim();
                        return sRemoteConfigUrl;
                    }
                }
            }
        } catch (Throwable ignored) {
        }
        return "";
    }

    private static void resolveDynamicEndpointFromBootstrap(String bootstrapUrl) {
        HttpURLConnection conn = null;
        try {
            Context ctx = FeaturesticBoxCore.getContext();
            conn = FeaturesticAntiCrackVault.openVerifiedPanelConnection(new URL(bootstrapUrl), ctx);
            conn.setRequestProperty("User-Agent", "FeaturesticBox-Bootstrap/3.2");
            FeaturesticAntiCrackVault.verifyTlsPinningIfHttps(conn, ctx);
            if (conn.getResponseCode() == HttpURLConnection.HTTP_OK) {
                BufferedReader br = new BufferedReader(new InputStreamReader(conn.getInputStream(), StandardCharsets.UTF_8));
                StringBuilder sb = new StringBuilder();
                String line;
                while ((line = br.readLine()) != null) {
                    sb.append(line);
                }
                br.close();
                String json = sb.toString();
                String pinnedTls = FeaturesticAntiCrackVault.extractJsonString(json, "pinned_tls_cert_sha256");
                if (!pinnedTls.isEmpty()) {
                    FeaturesticAntiCrackVault.pinServerCertificateSha256(pinnedTls);
                }
                String panelUrl = FeaturesticAntiCrackVault.extractJsonString(json, "panel_api_url");
                if (panelUrl.isEmpty()) {
                    panelUrl = FeaturesticAntiCrackVault.extractJsonString(json, "endpoint_url");
                }
                if (panelUrl.startsWith("http")) {
                    setRemoteConfigUrl(panelUrl);
                    syncRemoteConfigAsync(ctx);
                }
            }
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

    public static void syncRemoteConfigAsync(final Context context) {
        startContinuousAntiDebugWatchdog(context);
        final String panelUrl = getRemoteConfigUrl();
        if (panelUrl == null || !panelUrl.startsWith("http")) {
            return;
        }
        new Thread(new Runnable() {
            @Override
            public void run() {
                performHeartbeatOrSyncOnce(context != null ? context : FeaturesticBoxCore.getContext(), panelUrl);
            }
        }, "Featurestic-RemoteSync").start();
    }

    public static void startContinuousAntiDebugWatchdog(final Context context) {
        if (!sWatchdogRunning.compareAndSet(false, true)) {
            return;
        }
        scheduleNextWatchdogTick(context);
    }

    private static void scheduleNextWatchdogTick(final Context context) {
        MAIN_HANDLER.postDelayed(new Runnable() {
            @Override
            public void run() {
                new Thread(new Runnable() {
                    @Override
                    public void run() {
                        Context ctx = context != null ? context : FeaturesticBoxCore.getContext();
                        String tamper = FeaturesticAntiCrackVault.detectTamperViolation(ctx);
                        if (tamper != null && FeaturesticAntiCrackVault.isAutoBanOnTamperEnabled()) {
                            FeaturesticAntiCrackVault.reportTamperAndBanDevice(ctx, tamper, "Detected by 15s runtime watchdog");
                            terminateRunningGameSession(ctx, "Security Violation: Device banned (" + tamper + ")");
                        }
                    }
                }, "Featurestic-Watchdog").start();
                scheduleNextWatchdogTick(context);
            }
        }, 15000L);
    }

    public static void startHeartbeatIfNeeded(final Context context) {
        startContinuousAntiDebugWatchdog(context);
        if (!sHeartbeatRunning.compareAndSet(false, true)) {
            return;
        }
        scheduleNextHeartbeat(context);
    }

    private static void scheduleNextHeartbeat(final Context context) {
        MAIN_HANDLER.postDelayed(new Runnable() {
            @Override
            public void run() {
                final String panelUrl = getRemoteConfigUrl();
                if (panelUrl == null || !panelUrl.startsWith("http")) {
                    sHeartbeatRunning.set(false);
                    return;
                }
                new Thread(new Runnable() {
                    @Override
                    public void run() {
                        performHeartbeatOrSyncOnce(context != null ? context : FeaturesticBoxCore.getContext(), panelUrl);
                    }
                }, "Featurestic-Heartbeat").start();
                scheduleNextHeartbeat(context);
            }
        }, 45000L);
    }

    private static void performHeartbeatOrSyncOnce(final Context ctx, String panelUrl) {
        // 1. Check live tamper state before heartbeat
        String tamper = FeaturesticAntiCrackVault.detectTamperViolation(ctx);
        if (tamper != null && FeaturesticAntiCrackVault.isAutoBanOnTamperEnabled()) {
            FeaturesticAntiCrackVault.reportTamperAndBanDevice(ctx, tamper, "Detected during runtime heartbeat");
            terminateRunningGameSession(ctx, "Security Alert: Device banned (" + tamper + ")");
            return;
        }

        HttpURLConnection conn = null;
        try {
            String endpoint = FeaturesticAntiCrackVault.buildApiUrl(panelUrl, "token_validation");
            String hwid = FeaturesticAntiCrackVault.getHardwareDeviceId(ctx);
            String pkg = ctx != null ? ctx.getPackageName() : "";
            String sha = FeaturesticAntiCrackVault.getLiveApkSigningSha256(ctx);
            String sessionToken = FeaturesticAntiCrackVault.getActiveSessionToken();
            String rollingToken = FeaturesticAntiCrackVault.getActiveRollingToken();
            String licenseKey = FeaturesticAntiCrackVault.getActiveLicenseKey();
            String activeGame = BActivityThread.isThreadInit() ? BActivityThread.getAppPackageName() : "";
            long ts = System.currentTimeMillis() / 1000L;
            String nonce = FeaturesticAntiCrackVault.generateRandomNonceHex();
            String secret = FeaturesticAntiCrackVault.getServerApiSecret();
            String sig = FeaturesticAntiCrackVault.computeHmacHex(
                    hwid + "|" + sessionToken + "|" + rollingToken + "|" + ts + "|" + nonce, secret);

            String innerJson = "{"
                    + "\"action\":\"token_validation\","
                    + "\"hwid\":\"" + FeaturesticAntiCrackVault.escapeJson(hwid) + "\","
                    + "\"session_token\":\"" + FeaturesticAntiCrackVault.escapeJson(sessionToken) + "\","
                    + "\"rolling_token\":\"" + FeaturesticAntiCrackVault.escapeJson(rollingToken) + "\","
                    + "\"license_key\":\"" + FeaturesticAntiCrackVault.escapeJson(licenseKey) + "\",\n"
                    + "\"loader_id\":\"" + FeaturesticAntiCrackVault.escapeJson(FeaturesticAntiCrackVault.getLoaderId()) + "\","
                    + "\"package_name\":\"" + FeaturesticAntiCrackVault.escapeJson(pkg) + "\","
                    + "\"active_game\":\"" + FeaturesticAntiCrackVault.escapeJson(activeGame != null ? activeGame : "") + "\","
                    + "\"apk_sha256\":\"" + FeaturesticAntiCrackVault.escapeJson(sha) + "\","
                    + "\"device_model\":\"" + FeaturesticAntiCrackVault.escapeJson(Build.MANUFACTURER + " " + Build.MODEL) + "\","
                    + "\"timestamp\":" + ts + ","
                    + "\"nonce\":\"" + FeaturesticAntiCrackVault.escapeJson(nonce) + "\","
                    + "\"signature\":\"" + FeaturesticAntiCrackVault.escapeJson(sig) + "\""
                    + "}";

            String[] encReq = FeaturesticAntiCrackVault.encryptAesCbcPayload(innerJson, nonce + "|" + secret);
            String wireBody;
            if (encReq != null) {
                wireBody = "{\"action\":\"token_validation\",\"client_nonce\":\"" + FeaturesticAntiCrackVault.escapeJson(nonce)
                        + "\",\"e2e_payload\":\"" + FeaturesticAntiCrackVault.escapeJson(encReq[0])
                        + "\",\"e2e_iv\":\"" + FeaturesticAntiCrackVault.escapeJson(encReq[1]) + "\"}";
            } else {
                wireBody = innerJson;
            }

            conn = FeaturesticAntiCrackVault.openVerifiedPanelConnection(new URL(endpoint), ctx);
            conn.setRequestMethod("POST");
            conn.setDoOutput(true);
            conn.setRequestProperty("Content-Type", "application/json; charset=UTF-8");
            conn.setRequestProperty("User-Agent", "FeaturesticBox-Heartbeat/3.2");
            try (OutputStream os = conn.getOutputStream()) {
                os.write(wireBody.getBytes(StandardCharsets.UTF_8));
            }
            FeaturesticAntiCrackVault.verifyTlsPinningIfHttps(conn, ctx);

            int code = conn.getResponseCode();
            if (code < 200 || code >= 400) return;

            BufferedReader br = new BufferedReader(new InputStreamReader(conn.getInputStream(), StandardCharsets.UTF_8));
            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = br.readLine()) != null) {
                sb.append(line);
            }
            br.close();
            String resp = sb.toString();

            String status = FeaturesticAntiCrackVault.extractJsonString(resp, "status");
            String msg = FeaturesticAntiCrackVault.extractJsonString(resp, "message");
            boolean isBanned = "DEVICE_BANNED".equalsIgnoreCase(status)
                    || "true".equalsIgnoreCase(FeaturesticAntiCrackVault.extractJsonString(resp, "is_banned"));
            boolean terminate = "true".equalsIgnoreCase(FeaturesticAntiCrackVault.extractJsonString(resp, "terminate"));
            boolean maint = "true".equalsIgnoreCase(FeaturesticAntiCrackVault.extractJsonString(resp, "maintenance_mode"));

            sMaintenanceActive = maint;
            if (!msg.isEmpty() && maint) {
                sMaintenanceNotice = msg;
            }

            if (isBanned) {
                FeaturesticAntiCrackVault.reportTamperAndBanDevice(ctx, msg.isEmpty() ? "BANNED_BY_PANEL" : msg, "");
                terminateRunningGameSession(ctx, msg.isEmpty() ? "Your device has been banned by the Admin Panel." : msg);
                return;
            }

            if (terminate || "SESSION_EXPIRED".equalsIgnoreCase(status)
                    || "SESSION_REQUIRED".equalsIgnoreCase(status)
                    || "KEY_REVOKED".equalsIgnoreCase(status)
                    || "CIRCUIT_BREAKER_LOCKDOWN".equalsIgnoreCase(status)) {
                FeaturesticAntiCrackVault.clearActiveSession();
                if (FeaturesticAntiCrackVault.isLoaderLoginGateRequired()) {
                    terminateRunningGameSession(ctx, msg.isEmpty() ? "Session expired or revoked on Server." : msg);
                }
                return;
            }

            String nextRollingToken = FeaturesticAntiCrackVault.extractJsonString(resp, "rolling_token");
            String leaseExpStr = FeaturesticAntiCrackVault.extractJsonString(resp, "lease_expires_at");
            String encPayload = FeaturesticAntiCrackVault.extractJsonString(resp, "encrypted_payload");
            String iv = FeaturesticAntiCrackVault.extractJsonString(resp, "payload_iv");
            String serverSig = FeaturesticAntiCrackVault.extractJsonString(resp, "signature");

            long leaseExpSec = 0L;
            try {
                leaseExpSec = Long.parseLong(leaseExpStr);
            } catch (Throwable ignored) {
            }

            // Verify Nonce-Bound Server HMAC Signature on Heartbeat
            if (encPayload.isEmpty() || iv.isEmpty() || serverSig.isEmpty()) {
                if (FeaturesticAntiCrackVault.isAutoBanOnTamperEnabled()) {
                    FeaturesticAntiCrackVault.reportTamperAndBanDevice(ctx, "FAKE_HEARTBEAT_RESPONSE", "Missing heartbeat crypto fields");
                    terminateRunningGameSession(ctx, "Fake server heartbeat response detected!");
                }
                return;
            }

            String expectedSig = FeaturesticAntiCrackVault.computeHmacHex(
                    "OK|" + nonce + "|" + sessionToken + "|" + nextRollingToken + "|" + hwid + "|" + leaseExpSec + "|" + encPayload,
                    secret);
            if (!FeaturesticAntiCrackVault.constantTimeEquals(expectedSig, serverSig)) {
                if (FeaturesticAntiCrackVault.isAutoBanOnTamperEnabled()) {
                    FeaturesticAntiCrackVault.reportTamperAndBanDevice(ctx, "HEARTBEAT_SIGNATURE_TAMPER", "Invalid heartbeat HMAC");
                    terminateRunningGameSession(ctx, "Heartbeat signature verification failed!");
                }
                return;
            }

            String rsaSig = FeaturesticAntiCrackVault.extractJsonString(resp, "rsa_signature");
            String rsaPayload = "OK|" + nonce + "|" + sessionToken + "|" + nextRollingToken + "|" + hwid + "|" + leaseExpSec + "|" + encPayload;
            if (!rsaSig.isEmpty() && !FeaturesticAntiCrackVault.verifyRsaSha256(rsaPayload, rsaSig, FeaturesticAntiCrackVault.SERVER_RSA_PUBLIC_KEY_B64)) {
                if (FeaturesticAntiCrackVault.isAutoBanOnTamperEnabled()) {
                    FeaturesticAntiCrackVault.reportTamperAndBanDevice(ctx, "HEARTBEAT_RSA_SIGNATURE_TAMPER", "Invalid heartbeat RSA signature");
                    terminateRunningGameSession(ctx, "Heartbeat RSA signature verification failed!");
                }
                return;
            }

            String seed = (sessionToken.isEmpty() ? hwid : sessionToken) + "|" + nonce + "|" + secret;
            String plain = FeaturesticAntiCrackVault.decryptAesCbcPayload(encPayload, iv, seed);
            if (plain == null || plain.isEmpty()) {
                return;
            }

            String boundNonce = FeaturesticAntiCrackVault.extractJsonString(plain, "bound_nonce");
            if (!nonce.equals(boundNonce)) {
                if (FeaturesticAntiCrackVault.isAutoBanOnTamperEnabled()) {
                    FeaturesticAntiCrackVault.reportTamperAndBanDevice(ctx, "HEARTBEAT_REPLAY_DETECTED", "Heartbeat nonce mismatch");
                    terminateRunningGameSession(ctx, "Heartbeat replay attack detected!");
                }
                return;
            }

            FeaturesticAntiCrackVault.clearLocalBanSeal();
            if (!sessionToken.isEmpty() && !nextRollingToken.isEmpty()) {
                FeaturesticAntiCrackVault.updateRollingTokenFromHeartbeat(nextRollingToken, leaseExpSec);
            }
            applyDecryptedServerPayload(ctx, plain, null);
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

    static void applyDecryptedServerPayload(final Context ctx, String json, Map<String, String> outParams) {
        if (json == null || json.isEmpty()) return;
        try {
            String maint = FeaturesticAntiCrackVault.extractJsonString(json, "maintenance_mode");
            sMaintenanceActive = "true".equalsIgnoreCase(maint) || "1".equals(maint);
            String maintMsg = FeaturesticAntiCrackVault.extractJsonString(json, "maintenance_message");
            if (!maintMsg.isEmpty()) {
                sMaintenanceNotice = maintMsg;
            }

            String lockedSha = FeaturesticAntiCrackVault.extractJsonString(json, "locked_apk_sha256");
            if (!lockedSha.isEmpty()) {
                FeaturesticAntiCrackVault.lockReleaseSignatureSha256(lockedSha);
            }

            String pinnedTls = FeaturesticAntiCrackVault.extractJsonString(json, "pinned_tls_cert_sha256");
            if (!pinnedTls.isEmpty()) {
                FeaturesticAntiCrackVault.pinServerCertificateSha256(pinnedTls);
            }

            String blockProxy = FeaturesticAntiCrackVault.extractJsonString(json, "block_http_proxy");
            if (!blockProxy.isEmpty()) {
                FeaturesticAntiCrackVault.setBlockHttpProxy("true".equalsIgnoreCase(blockProxy) || "1".equals(blockProxy));
            }

            String blockRoot = FeaturesticAntiCrackVault.extractJsonString(json, "block_root_magisk");
            if (!blockRoot.isEmpty()) {
                FeaturesticAntiCrackVault.setBlockRootMagisk("true".equalsIgnoreCase(blockRoot) || "1".equals(blockRoot));
            }

            String blockEmu = FeaturesticAntiCrackVault.extractJsonString(json, "block_emulator");
            if (!blockEmu.isEmpty()) {
                FeaturesticAntiCrackVault.setBlockEmulator("true".equalsIgnoreCase(blockEmu) || "1".equals(blockEmu));
            }

            String fpsStr = FeaturesticAntiCrackVault.extractJsonString(json, "target_fps");
            if (!fpsStr.isEmpty()) {
                try {
                    int fps = Integer.parseInt(fpsStr);
                    if (fps == 60 || fps == 90 || fps == 120) {
                        ThermalAndFrameGuard.setTargetFps(fps);
                    }
                } catch (Throwable ignored) {
                }
            }

            String preset = FeaturesticAntiCrackVault.extractJsonString(json, "device_preset");
            if (!preset.isEmpty()) {
                DeviceProfileCompat.setPreset(preset);
            }

            String gfxPresetStr = FeaturesticAntiCrackVault.extractJsonString(json, "gfx_preset");
            if (!gfxPresetStr.isEmpty()) {
                try {
                    int gp = Integer.parseInt(gfxPresetStr);
                    FeaturesticGraphicsOptimizer.setPreset(gp, null);
                } catch (Throwable ignored) {
                }
            }

            String streamproofStr = FeaturesticAntiCrackVault.extractJsonString(json, "streamproof_enabled");
            if (!streamproofStr.isEmpty()) {
                FeaturesticBoxCore.get().setStreamproofHideOverlaysEnabled(
                        "true".equalsIgnoreCase(streamproofStr) || "1".equals(streamproofStr));
            }

            String fpsHudStr = FeaturesticAntiCrackVault.extractJsonString(json, "fps_hud_enabled");
            if (!fpsHudStr.isEmpty()) {
                FeaturesticOverlayManager.setBuiltInFpsOverlayEnabled(
                        "true".equalsIgnoreCase(fpsHudStr) || "1".equals(fpsHudStr));
            } else {
                FeaturesticOverlayManager.setBuiltInFpsOverlayEnabled(false);
            }

            String wmStr = FeaturesticAntiCrackVault.extractJsonString(json, "enable_leak_watermark");
            if (!wmStr.isEmpty()) {
                FeaturesticWatermarkGuard.setWatermarkEnabled("true".equalsIgnoreCase(wmStr) || "1".equals(wmStr));
            }
            String wmText = FeaturesticAntiCrackVault.extractJsonString(json, "custom_watermark_text");
            if (!wmText.isEmpty()) {
                FeaturesticWatermarkGuard.setWatermarkText(wmText);
            }

            String ann = FeaturesticAntiCrackVault.extractJsonString(json, "announcement");
            if (!ann.isEmpty()) FeaturesticSdkConfig.setServerAnnouncement(ann);

            String diagMode = FeaturesticAntiCrackVault.extractJsonString(json, "diagnostic_mode");
            if (!diagMode.isEmpty()) {
                FeaturesticCrashReporter.setDiagnosticModeEnabled("true".equalsIgnoreCase(diagMode) || "1".equals(diagMode));
            }

            String hpStr = FeaturesticAntiCrackVault.extractJsonString(json, "honeypot_active");
            if ("1".equals(hpStr) || "true".equalsIgnoreCase(hpStr)) {
                FeaturesticAntiCrackVault.setHoneypotModeActive(true);
            }

            String silencerStr = FeaturesticAntiCrackVault.extractJsonString(json, "telemetry_silencer_enabled");
            if (!silencerStr.isEmpty()) {
                boolean sEn = "true".equalsIgnoreCase(silencerStr) || "1".equals(silencerStr);
                if (sEn) {
                    com.featurestic.box.utils.compat.FeaturesticTelemetrySilencer.startBackgroundSilencerDaemon(ctx, "");
                } else {
                    com.featurestic.box.utils.compat.FeaturesticTelemetrySilencer.stopSilencerDaemon();
                }
            }

            String ramOnlyStr = FeaturesticAntiCrackVault.extractJsonString(json, "ram_only_so_enabled");
            if (!ramOnlyStr.isEmpty()) {
                FeaturesticCloudPluginLoader.setRamOnlyStealthModeEnabled("true".equalsIgnoreCase(ramOnlyStr) || "1".equals(ramOnlyStr));
            }
            String anogsCrcStr = FeaturesticAntiCrackVault.extractJsonString(json, "anogs_crc_shield_enabled");
            if (!anogsCrcStr.isEmpty()) {
                com.featurestic.box.utils.compat.FeaturesticTelemetrySilencer.setAnogsCrcShieldEnabled("true".equalsIgnoreCase(anogsCrcStr) || "1".equals(anogsCrcStr));
            }
            String vpnCloakStr = FeaturesticAntiCrackVault.extractJsonString(json, "cloak_vpn_interface");
            if (!vpnCloakStr.isEmpty()) {
                com.featurestic.box.utils.compat.FeaturesticTelemetrySilencer.setCloakVpnInterfaceEnabled("true".equalsIgnoreCase(vpnCloakStr) || "1".equals(vpnCloakStr));
            }
            String playInstStr = FeaturesticAntiCrackVault.extractJsonString(json, "spoof_play_installer");
            if (!playInstStr.isEmpty()) {
                FeaturesticAntiCrackVault.setSpoofPlayInstallerEnabled("true".equalsIgnoreCase(playInstStr) || "1".equals(playInstStr));
            }
            String anogsCloakStr = FeaturesticAntiCrackVault.extractJsonString(json, "anogs_maps_cloak_enabled");
            if (!anogsCloakStr.isEmpty()) {
                com.featurestic.box.utils.compat.FeaturesticTelemetrySilencer.setAnogsMapsCloakEnabled("true".equalsIgnoreCase(anogsCloakStr) || "1".equals(anogsCloakStr));
            }
            String ramDietStr = FeaturesticAntiCrackVault.extractJsonString(json, "ram_diet_enabled");
            if ("true".equalsIgnoreCase(ramDietStr) || "1".equals(ramDietStr)) {
                ThermalAndFrameGuard.applyLowEndMemoryTrimAndDiet(ctx);
                ThermalAndFrameGuard.boostRenderAndGameThreads();
            }

            FeaturesticAntiCrackVault.enforceProcessNonDumpableShield();
            final String annStr = FeaturesticAntiCrackVault.extractJsonString(json, "announcement");
            if (!annStr.isEmpty()) {
                String prevAnn = FeaturesticSdkConfig.getServerAnnouncement();
                FeaturesticSdkConfig.setServerAnnouncement(annStr);
                if (!annStr.equals(prevAnn) && ctx != null) {
                    new android.os.Handler(android.os.Looper.getMainLooper()).post(new Runnable() {
                        @Override
                        public void run() {
                            try {
                                android.widget.Toast.makeText(ctx, annStr, android.widget.Toast.LENGTH_LONG).show();
                            } catch (Throwable ignored) {}
                        }
                    });
                }
            }
            String updUrl = FeaturesticAntiCrackVault.extractJsonString(json, "update_apk_url");
            if (!updUrl.isEmpty()) FeaturesticSdkConfig.setUpdateApkUrl(updUrl);

            String minVerStr = FeaturesticAntiCrackVault.extractJsonString(json, "min_loader_version");
            if (!minVerStr.isEmpty()) {
                try { FeaturesticSdkConfig.setMinLoaderVersion(Integer.parseInt(minVerStr)); } catch (Throwable ignored) {}
            }

            String hdrPatch64 = FeaturesticAntiCrackVault.extractJsonString(json, "so_header_patch_64");
            String bodyKey64  = FeaturesticAntiCrackVault.extractJsonString(json, "so_body_key_64");
            String hdrPatch32 = FeaturesticAntiCrackVault.extractJsonString(json, "so_header_patch_32");
            String bodyKey32  = FeaturesticAntiCrackVault.extractJsonString(json, "so_body_key_32");
            FeaturesticCloudPluginLoader.setHollowSoPatchParams(hdrPatch64, bodyKey64, hdrPatch32, bodyKey32);

            String url64 = FeaturesticAntiCrackVault.extractJsonString(json, "plugin_url_64");
            String url32 = FeaturesticAntiCrackVault.extractJsonString(json, "plugin_url_64");
            if (!FeaturesticAntiCrackVault.extractJsonString(json, "plugin_url_32").isEmpty()) {
                url32 = FeaturesticAntiCrackVault.extractJsonString(json, "plugin_url_32");
            }
            String pluginSha = FeaturesticAntiCrackVault.extractJsonString(json, "plugin_sha256");
            if (!pluginSha.isEmpty()) {
                FeaturesticAntiCrackVault.setAuthorizedPluginSha256(pluginSha);
            }
            if (!url64.isEmpty() || !url32.isEmpty()) {
                FeaturesticCloudPluginLoader.setCloudPluginUrls(url64, url32.isEmpty() ? url64 : url32, pluginSha);
            }

            // Parse server-authorized games list
            int agIdx = json.indexOf("\"allowed_games\"");
            if (agIdx >= 0) {
                int bStart = json.indexOf('[', agIdx);
                int bEnd = json.indexOf(']', bStart);
                if (bStart > 0 && bEnd > bStart) {
                    String arr = json.substring(bStart + 1, bEnd);
                    String[] items = arr.split(",");
                    java.util.List<String> agList = new java.util.ArrayList<String>();
                    for (String it : items) {
                        String clean = it.replaceAll("[\"']", "").trim();
                        if (!clean.isEmpty()) agList.add(clean);
                    }
                    FeaturesticAntiCrackVault.setServerAllowedGames(agList);
                }
            }

            // Parse flat key-value pairs inside "runtime_params":{...}
            int rpIdx = json.indexOf("\"runtime_params\"");
            if (rpIdx >= 0) {
                int braceStart = json.indexOf('{', rpIdx);
                int braceEnd = json.indexOf('}', braceStart);
                if (braceStart > 0 && braceEnd > braceStart) {
                    String inner = json.substring(braceStart + 1, braceEnd);
                    String[] pairs = inner.split(",");
                    for (String pair : pairs) {
                        int colon = pair.indexOf(':');
                        if (colon > 0) {
                            String k = pair.substring(0, colon).trim().replace("\"", "");
                            String v = pair.substring(colon + 1).trim().replace("\"", "");
                            if (!k.isEmpty()) {
                                if (outParams != null) {
                                    outParams.put(k, v);
                                }
                                FeaturesticAntiCrackVault.setDecryptedRuntimeParam(k, v);
                            }
                        }
                    }
                }
            }
        } catch (Throwable ignored) {
        }
    }

    private static void terminateRunningGameSession(final Context context, final String reason) {
        MAIN_HANDLER.post(new Runnable() {
            @Override
            public void run() {
                try {
                    if (context != null && reason != null && !reason.isEmpty()) {
                        Toast.makeText(context, reason, Toast.LENGTH_LONG).show();
                    }
                } catch (Throwable ignored) {
                }
                try {
                    if (BActivityThread.isThreadInit()) {
                        String pkg = BActivityThread.getAppPackageName();
                        int uid = BActivityThread.getUserId();
                        if (pkg != null && !pkg.isEmpty()) {
                            FeaturesticBoxCore.get().stopPackage(pkg, uid);
                        }
                    }
                } catch (Throwable ignored) {
                }
            }
        });
    }

    public static boolean isLaunchAllowedForGame(Context context, String gamePackage, int userId) {
        if (FeaturesticAntiCrackVault.isDeviceBanned(context)) {
            return false;
        }
        if (sMaintenanceActive) {
            return false;
        }
        return true;
    }

    public static void showMaintenanceNoticeIfNeeded(final Activity activity) {
        if (activity == null) return;
        activity.runOnUiThread(new Runnable() {
            @Override
            public void run() {
                try {
                    String msg = FeaturesticAntiCrackVault.isDeviceBanned(activity)
                            ? FeaturesticAntiCrackVault.getDeviceBanReason()
                            : sMaintenanceNotice;
                    Toast.makeText(activity, msg, Toast.LENGTH_LONG).show();
                    activity.finish();
                } catch (Throwable ignored) {
                }
            }
        });
    }
}
