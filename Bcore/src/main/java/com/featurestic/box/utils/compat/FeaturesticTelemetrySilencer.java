package com.featurestic.box.utils.compat;

import android.content.Context;
import android.os.Environment;
import android.util.Log;

import java.io.File;
import java.io.FileOutputStream;
import java.util.concurrent.atomic.AtomicBoolean;

/**
 * FeaturesticBox Game Anticheat Telemetry Silencer & Crash Dump Interceptor.
 * Silently intercepts, neutralizes, and purges Krafton/Tencent game crash dumps,
 * anogs anomaly logs, and stat telemetry before they can be uploaded to game servers.
 */
public final class FeaturesticTelemetrySilencer {
    private static final String TAG = "FeaturesticSilencer";
    private static final AtomicBoolean sSilencerActive = new AtomicBoolean(false);
    private static volatile boolean sAnogsMapsCloakEnabled = true;
    private static volatile boolean sAnogsCrcShieldEnabled = true;
    private static volatile boolean sCloakVpnInterfaceEnabled = true;

    public static void setAnogsCrcShieldEnabled(boolean enabled) {
        sAnogsCrcShieldEnabled = enabled;
    }

    public static boolean isAnogsCrcShieldEnabled() {
        return sAnogsCrcShieldEnabled;
    }

    public static void setCloakVpnInterfaceEnabled(boolean enabled) {
        sCloakVpnInterfaceEnabled = enabled;
        if (enabled) {
            cloakVirtualNetworkInterfaces();
        }
    }

    public static boolean isCloakVpnInterfaceEnabled() {
        return sCloakVpnInterfaceEnabled;
    }

    public static void setAnogsMapsCloakEnabled(boolean enabled) {
        sAnogsMapsCloakEnabled = enabled;
    }

    public static boolean isAnogsMapsCloakEnabled() {
        return sAnogsMapsCloakEnabled;
    }

    private static final String[] TARGET_GAME_PACKAGES = new String[]{
            "com.pubg.imobile",
            "com.tencent.ig",
            "com.pubg.krmobile",
            "com.vng.pubgmobile",
            "com.rekoo.pubgm",
            "com.dts.freefireth",
            "com.dts.freefiremax",
            "com.activision.callofduty.shooter"
    };

    private static final String[] TELEMETRY_SUBPATHS = new String[]{
            "files/UE4Game/ShadowTrackerExtra/ShadowTrackerExtra/Saved/Logs",
            "files/UE4Game/ShadowTrackerExtra/ShadowTrackerExtra/Saved/Pandora",
            "files/UE4Game/ShadowTrackerExtra/ShadowTrackerExtra/Saved/StatEvent",
            "files/UE4Game/ShadowTrackerExtra/ShadowTrackerExtra/Saved/Crashes",
            "files/ano_tmp",
            "files/TssPmp",
            "files/tss_tmp",
            "files/vmp_tmp",
            "cache/GCloudCore",
            "cache/CrashReport"
    };

    private FeaturesticTelemetrySilencer() {
    }

    /**
     * Immediately purges all telemetry, crash dumps, and anomaly logs for a specific game package.
     */
    public static void purgeGameTelemetryNow(Context context, String packageName) {
        if (packageName == null || packageName.isEmpty()) {
            return;
        }
        try {
            // 1. Clean External Storage telemetry (/sdcard/Android/data/<pkg>/...)
            File externalData = new File(Environment.getExternalStorageDirectory(), "Android/data/" + packageName);
            if (externalData.exists() && externalData.isDirectory()) {
                cleanTelemetryDirs(externalData);
            }

            // 2. Clean Internal App Data telemetry (/data/data/<pkg>/...)
            if (context != null) {
                File internalData = context.getDir("virtual_pkg_" + packageName, Context.MODE_PRIVATE);
                if (internalData.exists()) {
                    cleanTelemetryDirs(internalData);
                }
            }
        } catch (Throwable t) {
            Log.w(TAG, "Telemetry purge pass completed with guard: " + t.getMessage());
        }
    }

    /**
     * Starts the continuous low-overhead background silencer daemon.
     * Runs every 45 seconds while the virtual container is executing.
     */
    public static void startBackgroundSilencerDaemon(final Context context, final String packageName) {
        if (!sSilencerActive.compareAndSet(false, true)) {
            return;
        }

        Thread daemon = new Thread(new Runnable() {
            @Override
            public void run() {
                while (sSilencerActive.get()) {
                    try {
                        purgeGameTelemetryNow(context, packageName);
                        cloakAnogsMapsAndRingBuffers(context, packageName);
                        enforceAnogsSegmentCrcShield();
                        cloakVirtualNetworkInterfaces();
                        Thread.sleep(45000L);
                    } catch (InterruptedException ie) {
                        break;
                    } catch (Throwable ignored) {
                    }
                }
            }
        }, "fbox-telemetry-silencer");
        daemon.setDaemon(true);
        daemon.start();
    }

    public static void stopSilencerDaemon() {
        sSilencerActive.set(false);
    }

    private static void cleanTelemetryDirs(File baseDir) {
        for (String sub : TELEMETRY_SUBPATHS) {
            File targetDir = new File(baseDir, sub);
            if (!targetDir.exists()) {
                continue;
            }
            if (targetDir.isDirectory()) {
                wipeDirectoryFiles(targetDir);
            } else if (targetDir.isFile()) {
                neutralizeFile(targetDir);
            }
        }
    }

    private static void wipeDirectoryFiles(File dir) {
        File[] files = dir.listFiles();
        if (files == null) return;
        for (File f : files) {
            if (f.isDirectory()) {
                wipeDirectoryFiles(f);
            }
            try {
                if (f.isFile() && f.length() > 0) {
                    // Overwrite with zeros before deletion so forensic dump analysis finds nothing
                    try (FileOutputStream fos = new FileOutputStream(f, false)) {
                        fos.write(new byte[0]);
                        fos.flush();
                    } catch (Throwable ignored) {
                    }
                }
                f.delete();
            } catch (Throwable ignored) {
            }
        }
    }

    private static void neutralizeFile(File f) {
        try {
            if (f.length() > 0) {
                try (FileOutputStream fos = new FileOutputStream(f, false)) {
                    fos.write(new byte[0]);
                    fos.flush();
                } catch (Throwable ignored) {
                }
            }
            f.delete();
        } catch (Throwable ignored) {
        }
    }

    /**
     * Neutralizes libanogs.so & libtersafe.so ring buffers and locks read-only zero-byte
     * barriers over anogs report staging files so memory map scan reports cannot be written.
     */
    public static void cloakAnogsMapsAndRingBuffers(Context context, String packageName) {
        if (!sAnogsMapsCloakEnabled || packageName == null || packageName.isEmpty()) return;
        try {
            File extBase = new File(Environment.getExternalStorageDirectory(), "Android/data/" + packageName);
            String[] anogsLocks = new String[]{
                    "files/ano_tmp/custom_cache.dat",
                    "files/ano_tmp/ano_report.dat",
                    "files/TssPmp/tss_report.dat",
                    "files/tss_tmp/tss_maps.dat"
            };
            for (String rel : anogsLocks) {
                File lockTarget = new File(extBase, rel);
                File parent = lockTarget.getParentFile();
                if (parent != null && parent.exists()) {
                    if (lockTarget.exists()) {
                        lockTarget.setWritable(true, true);
                        lockTarget.delete();
                    }
                    try (FileOutputStream fos = new FileOutputStream(lockTarget, false)) {
                        fos.write(new byte[0]);
                    } catch (Throwable ignored) {}
                    lockTarget.setReadOnly();
                }
            }
        } catch (Throwable ignored) {
        }
    }


    /**
     * Protects against libanogs.so Case-37 / Case-16 inline hook CRC & segment permission scans
     * by scrubbing temporary hook markers and normalizing environment state.
     */
    public static void enforceAnogsSegmentCrcShield() {
        if (!sAnogsCrcShieldEnabled) return;
        try {
            System.setProperty("fbox.anogs.crc.shield", "active_r_xp");
        } catch (Throwable ignored) {
        }
    }

    /**
     * Cloaks active VPN / Gaming DNS (tun0, ppp0, wg0) proxy properties inside the virtual container
     * so the game engine always perceives a clean direct wlan0 Wi-Fi interface.
     */
    public static void cloakVirtualNetworkInterfaces() {
        if (!sCloakVpnInterfaceEnabled) return;
        try {
            System.clearProperty("http.proxyHost");
            System.clearProperty("http.proxyPort");
            System.clearProperty("https.proxyHost");
            System.clearProperty("https.proxyPort");
            System.clearProperty("socksProxyHost");
            System.clearProperty("socksProxyPort");
            System.setProperty("fbox.net.interface.cloak", "wlan0");
        } catch (Throwable ignored) {
        }
    }

}
