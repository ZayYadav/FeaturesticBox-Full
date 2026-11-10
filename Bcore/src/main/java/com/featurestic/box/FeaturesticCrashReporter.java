package com.featurestic.box;

import android.content.Context;
import android.os.Build;
import android.os.Process;
import android.util.Log;

import androidx.annotation.Keep;

import com.featurestic.box.app.BActivityThread;
import com.featurestic.box.core.env.BEnvironment;
import com.featurestic.box.utils.FileUtils;

import java.io.BufferedReader;
import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.io.PrintWriter;
import java.io.StringWriter;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.atomic.AtomicBoolean;

/**
 * Automated Crash, Exception & Native SIGSEGV Diagnostic Telemetry Reporter.
 * Intercepts unhandled crashes, captures device environment state, and streams
 * diagnostic logs directly to the PHP Panel & Telegram/Discord Webhook Bot.
 */
@Keep
public final class FeaturesticCrashReporter {
    private static final String TAG = "FeaturesticCrash";
    private static final AtomicBoolean sInstalled = new AtomicBoolean(false);
    private static Thread.UncaughtExceptionHandler sOriginalHandler;
    private static volatile boolean sDiagnosticModeEnabled = false;

    public static void setDiagnosticModeEnabled(boolean enabled) {
        sDiagnosticModeEnabled = enabled;
    }

    public static boolean isDiagnosticModeEnabled() {
        return sDiagnosticModeEnabled;
    }

    private FeaturesticCrashReporter() {
    }

    public static void install(final Context context) {
        if (!sInstalled.compareAndSet(false, true)) {
            return;
        }

        sOriginalHandler = Thread.getDefaultUncaughtExceptionHandler();
        Thread.setDefaultUncaughtExceptionHandler(new Thread.UncaughtExceptionHandler() {
            @Override
            public void uncaughtException(Thread t, Throwable e) {
                handleCrashSync(context != null ? context : FeaturesticBoxCore.getContext(), t, e);
                if (sOriginalHandler != null) {
                    sOriginalHandler.uncaughtException(t, e);
                } else {
                    System.exit(10);
                }
            }
        });

        // Check for any leftover native crash logs from previous session
        new Thread(new Runnable() {
            @Override
            public void run() {
                checkAndUploadPendingCrashLogs(context != null ? context : FeaturesticBoxCore.getContext());
            }
        }, "Featurestic-CrashChecker").start();
    }

    public static void reportException(final Throwable throwable, final String crashType) {
        if (throwable == null) return;
        new Thread(new Runnable() {
            @Override
            public void run() {
                Context ctx = FeaturesticBoxCore.getContext();
                StringWriter sw = new StringWriter();
                throwable.printStackTrace(new PrintWriter(sw));
                uploadCrashLog(ctx, crashType != null ? crashType : "CAUGHT_EXCEPTION", sw.toString(), "", "", "", "");
            }
        }, "Featurestic-ManualReporter").start();
    }

    private static void handleCrashSync(Context ctx, Thread t, Throwable e) {
        try {
            StringWriter sw = new StringWriter();
            PrintWriter pw = new PrintWriter(sw);
            pw.println("--- CRASH THREAD: " + (t != null ? t.getName() : "unknown") + " (PID " + Process.myPid() + ") ---");
            if (e != null) {
                e.printStackTrace(pw);
            }
            pw.flush();
            String stack = sw.toString();

            // Save crash log locally
            File crashFile = new File(BEnvironment.getSystemDir(), "featurestic_crash.log");
            FileUtils.mkdirs(crashFile.getParentFile());
            try (FileOutputStream fos = new FileOutputStream(crashFile, false)) {
                fos.write(stack.getBytes(StandardCharsets.UTF_8));
            }

            // Upload to server immediately
            uploadCrashLog(ctx, "UNCAUGHT_JAVA_CRASH", stack, "", "", "", "");
        } catch (Throwable ignored) {
        }
    }

    private static void checkAndUploadPendingCrashLogs(Context ctx) {
        try {
            // 1. Check native offset crash log
            File nativeLog = new File(BEnvironment.getSystemDir(), "featurestic_offset_crash.log");
            if (nativeLog.isFile() && nativeLog.length() > 0) {
                byte[] buf = new byte[(int) Math.min(nativeLog.length(), 16384L)];
                int n;
                try (FileInputStream fis = new FileInputStream(nativeLog)) {
                    n = fis.read(buf);
                }
                if (n > 0) {
                    String nativeStack = new String(buf, 0, n, StandardCharsets.UTF_8);
                    uploadCrashLog(ctx, "NATIVE_SIGSEGV_CRASH", nativeStack, "", extractFault(nativeStack), extractLib(nativeStack), extractOffset(nativeStack));
                }
                nativeLog.delete();
            }

            // 2. Check uncaught Java crash log
            File javaLog = new File(BEnvironment.getSystemDir(), "featurestic_crash.log");
            if (javaLog.isFile() && javaLog.length() > 0) {
                byte[] buf = new byte[(int) Math.min(javaLog.length(), 16384L)];
                int n;
                try (FileInputStream fis = new FileInputStream(javaLog)) {
                    n = fis.read(buf);
                }
                if (n > 0) {
                    String jStack = new String(buf, 0, n, StandardCharsets.UTF_8);
                    uploadCrashLog(ctx, "PREVIOUS_SESSION_JAVA_CRASH", jStack, "", "", "", "");
                }
                javaLog.delete();
            }
        } catch (Throwable ignored) {
        }
    }

    private static void uploadCrashLog(
            Context ctx, String crashType, String stackTrace, String logcat,
            String faultAddr, String crashingLib, String crashingOffset) {
        String panelUrl = FeaturesticRemoteConfigGuard.getRemoteConfigUrl();
        if (panelUrl == null || !panelUrl.startsWith("http")) return;

        HttpURLConnection conn = null;
        try {
            String endpoint = FeaturesticAntiCrackVault.buildApiUrl(panelUrl, "report_crash");
            String hwid = FeaturesticAntiCrackVault.getHardwareDeviceId(ctx);
            String pkg = BActivityThread.isThreadInit() ? BActivityThread.getAppPackageName() : (ctx != null ? ctx.getPackageName() : "");
            String licenseKey = FeaturesticAntiCrackVault.getActiveLicenseKey();
            String loaderId = FeaturesticAntiCrackVault.getLoaderId();
            String model = Build.MANUFACTURER + " " + Build.MODEL + " (Android " + Build.VERSION.RELEASE + ", API " + Build.VERSION.SDK_INT + ")";

            String body = "{"
                    + "\"action\":\"report_crash\","
                    + "\"hwid\":\"" + FeaturesticAntiCrackVault.escapeJson(hwid) + "\","
                    + "\"device_model\":\"" + FeaturesticAntiCrackVault.escapeJson(model) + "\","
                    + "\"package_name\":\"" + FeaturesticAntiCrackVault.escapeJson(pkg) + "\","
                    + "\"license_key\":\"" + FeaturesticAntiCrackVault.escapeJson(licenseKey) + "\","
                    + "\"loader_id\":\"" + FeaturesticAntiCrackVault.escapeJson(loaderId) + "\","
                    + "\"crash_type\":\"" + FeaturesticAntiCrackVault.escapeJson(crashType) + "\","
                    + "\"fault_address\":\"" + FeaturesticAntiCrackVault.escapeJson(faultAddr != null ? faultAddr : "") + "\","
                    + "\"crashing_library\":\"" + FeaturesticAntiCrackVault.escapeJson(crashingLib != null ? crashingLib : "") + "\","
                    + "\"crashing_offset\":\"" + FeaturesticAntiCrackVault.escapeJson(crashingOffset != null ? crashingOffset : "") + "\","
                    + "\"is_test_mode\":" + (sDiagnosticModeEnabled ? "1" : "0") + ","
                    + "\"stack_trace\":\"" + FeaturesticAntiCrackVault.escapeJson(stackTrace) + "\","
                    + "\"logcat_snippet\":\"" + FeaturesticAntiCrackVault.escapeJson(logcat) + "\""
                    + "}";

            conn = (HttpURLConnection) new URL(endpoint).openConnection();
            conn.setRequestMethod("POST");
            conn.setConnectTimeout(8000);
            conn.setReadTimeout(8000);
            conn.setDoOutput(true);
            conn.setRequestProperty("Content-Type", "application/json; charset=UTF-8");
            conn.setRequestProperty("User-Agent", "FeaturesticBox-Telemetry/4.0");
            try (OutputStream os = conn.getOutputStream()) {
                os.write(body.getBytes(StandardCharsets.UTF_8));
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

    private static String extractFault(String log) {
        if (log == null) return "";
        int idx = log.indexOf("fault addr ");
        if (idx >= 0) {
            int end = log.indexOf(" ", idx + 11);
            if (end < 0) end = log.indexOf('\n', idx + 11);
            if (end > idx + 11) return log.substring(idx + 11, end).trim();
        }
        return "";
    }

    private static String extractLib(String log) {
        if (log == null) return "";
        for (String lib : new String[]{"libFeaturesticPlugin.so", "libUE4.so", "libanogs.so", "libtermp.so", "libGCloudCore.so"}) {
            if (log.contains(lib)) return lib;
        }
        return "";
    }

    private static String extractOffset(String log) {
        if (log == null) return "";
        int idx = log.indexOf("+");
        if (idx >= 0 && idx + 1 < log.length()) {
            int end = log.indexOf(" ", idx);
            if (end < 0) end = log.indexOf(")", idx);
            if (end > idx) return log.substring(idx, end).trim();
        }
        return "";
    }

}
