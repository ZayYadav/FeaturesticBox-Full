package com.featurestic.box.utils.compat;

import android.content.Context;
import android.net.wifi.WifiManager;
import android.os.Build;

import java.io.FileDescriptor;
import java.security.Security;
import java.util.concurrent.atomic.AtomicBoolean;

import com.featurestic.box.FeaturesticBoxCore;

/**
 * Clean, Non-Intrusive Wi-Fi Low-Latency Lock & JVM DNS Cache Optimizer.
 *
 * Keeps Linux kernel TCP window auto-tuning (`tcp_moderate_rcvbuf`, up to 4MB-8MB)
 * 100% intact by never clamping SO_RCVBUF/SO_SNDBUF to 256KB, never injecting custom
 * IP_TOS DSCP bits that Indian mobile carriers drop, and never calling
 * ConnectivityManager.requestNetwork(TRANSPORT_CELLULAR) which previously caused
 * GCloud in-game network interface switch reconnects.
 */
public final class NetworkLowLatencyOptimizer {
    private static volatile boolean sEnabled = true;
    private static final AtomicBoolean sProcessInitialized = new AtomicBoolean(false);
    private static WifiManager.WifiLock sLowLatencyWifiLock;

    private static final int WIFI_MODE_FULL_HIGH_PERF = 3;
    private static final int WIFI_MODE_FULL_LOW_LATENCY = 4;

    private NetworkLowLatencyOptimizer() {
    }

    public static void setEnabled(boolean enabled) {
        sEnabled = enabled;
        if (!enabled) {
            releaseWifiLowLatencyLock();
        }
    }

    public static boolean isEnabled() {
        return sEnabled;
    }

    public static void initForProcess(Context context) {
        if (!sEnabled || !sProcessInitialized.compareAndSet(false, true)) {
            return;
        }
        try {
            Security.setProperty("networkaddress.cache.ttl", "300");
            Security.setProperty("networkaddress.cache.negative.ttl", "0");
        } catch (Throwable ignored) {
        }
        try {
            System.setProperty("http.keepAlive", "true");
            System.setProperty("http.maxConnections", "32");
            System.setProperty("sun.net.inetaddr.ttl", "300");
            System.setProperty("sun.net.inetaddr.negative.ttl", "0");
        } catch (Throwable ignored) {
        }
    }

    public static synchronized void acquireWifiLowLatencyLock(Context context) {
        if (!sEnabled) {
            return;
        }
        try {
            if (sLowLatencyWifiLock != null && sLowLatencyWifiLock.isHeld()) {
                return;
            }
            Context appCtx = context != null ? context.getApplicationContext() : FeaturesticBoxCore.getContext();
            if (appCtx == null) {
                return;
            }
            WifiManager wm = (WifiManager) appCtx.getSystemService(Context.WIFI_SERVICE);
            if (wm == null) {
                return;
            }
            int lockMode = Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q
                    ? WIFI_MODE_FULL_LOW_LATENCY
                    : WIFI_MODE_FULL_HIGH_PERF;
            sLowLatencyWifiLock = wm.createWifiLock(lockMode, "Featurestic:LowLatencyLock");
            sLowLatencyWifiLock.setReferenceCounted(false);
            sLowLatencyWifiLock.acquire();
        } catch (Throwable ignored) {
        }
    }

    public static synchronized void releaseWifiLowLatencyLock() {
        try {
            if (sLowLatencyWifiLock != null && sLowLatencyWifiLock.isHeld()) {
                sLowLatencyWifiLock.release();
            }
        } catch (Throwable ignored) {
        }
    }

    public static void optimizeSocketOnCreation(FileDescriptor fd, Object[] socketArgs) {
        // Intentionally left as a safe no-op to preserve Android Linux kernel TCP window
        // auto-tuning (tcp_moderate_rcvbuf) and carrier-compatible DSCP headers.
    }

    public static void optimizeSocketOnConnect(FileDescriptor fd) {
        // Intentionally left as a safe no-op.
    }

    public static boolean shouldBlockLowBandwidthTracker(String url) {
        if (!sEnabled || url == null) {
            return false;
        }
        String lower = url.toLowerCase(java.util.Locale.US);
        return lower.contains("google-analytics.com/")
                || lower.contains("googletagmanager.com/")
                || lower.contains("doubleclick.net/")
                || lower.contains("browser.sentry-cdn.com/")
                || lower.contains("static.hotjar.com/")
                || lower.contains("clarity.ms/");
    }

    public static synchronized void enableDualNetworkStandby(Context context) {
        // Safe no-op: avoids triggering ConnectivityManager network-change callbacks inside GCloud.
    }

    public static android.net.Network getStandbyCellularNetwork() {
        return null;
    }

    public static void boostAndWarmNow(Context context) {
        sEnabled = true;
        acquireWifiLowLatencyLock(context);
    }
}
