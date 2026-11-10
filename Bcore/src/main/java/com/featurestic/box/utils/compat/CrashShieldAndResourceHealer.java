package com.featurestic.box.utils.compat;

import android.content.Context;
import android.os.Looper;
import android.os.RemoteException;
import android.util.Log;

import java.io.File;

import com.featurestic.box.app.BActivityThread;
import com.featurestic.box.core.env.BEnvironment;

public final class CrashShieldAndResourceHealer {
    private static final String TAG = "FeaturesticCrashShield";
    private static volatile boolean sInstalled = false;

    private CrashShieldAndResourceHealer() {
    }

    private static File getCrashMarkerFile(String pkg, int userId) {
        String safePkg = (pkg != null && !pkg.isEmpty()) ? pkg.replace('.', '_') : "app";
        return new File(BEnvironment.getSystemDir(), "crash_heal_" + safePkg + "_u" + Math.max(0, userId) + ".flag");
    }

    public static void installForVirtualProcess(final Context appContext) {
        if (sInstalled) {
            return;
        }
        sInstalled = true;
        final String pkg = BActivityThread.getAppPackageName();
        final int userId = BActivityThread.getUserId();

        try {
            File marker = getCrashMarkerFile(pkg, userId);
            if (marker.exists()) {
                marker.delete();
            }
        } catch (Throwable ignored) {
        }

        final Thread.UncaughtExceptionHandler previousHandler = Thread.getDefaultUncaughtExceptionHandler();
        Thread.setDefaultUncaughtExceptionHandler(new Thread.UncaughtExceptionHandler() {
            @Override
            public void uncaughtException(Thread thread, Throwable throwable) {
                if (shouldAbsorbNonFatalException(thread, throwable)) {
                    Log.w(TAG, "Absorbed non-fatal background exception on thread="
                            + (thread != null ? thread.getName() : "null") + ": " + throwable);
                    return;
                }
                if (previousHandler != null) {
                    previousHandler.uncaughtException(thread, throwable);
                }
            }
        });
    }

    private static boolean shouldAbsorbNonFatalException(Thread thread, Throwable t) {
        if (t == null) {
            return true;
        }
        boolean isMainThread = false;
        try {
            Looper mainLooper = Looper.getMainLooper();
            isMainThread = mainLooper != null && mainLooper.getThread() == thread;
        } catch (Throwable ignored) {
        }
        if (isMainThread) {
            return false;
        }
        String msg = t.getMessage() != null ? t.getMessage() : "";
        String cls = t.getClass().getName();
        String tName = thread != null && thread.getName() != null ? thread.getName() : "";

        if (t instanceof RemoteException
                || cls.contains("DeadSystemException")
                || cls.contains("DeadObjectException")
                || (t instanceof SecurityException && !isMainThread)
                || (t instanceof IllegalStateException && (msg.contains("Service") || msg.contains("Broadcast") || msg.contains("SharedPreferences")))
                || tName.contains("Firebase")
                || tName.contains("Measurement")
                || tName.contains("Gms")
                || tName.contains("OkHttp")
                || tName.contains("Crashlytics")
                || tName.contains("Sentry")
                || tName.contains("Bugly")) {
            return true;
        }
        return false;
    }

    public static int healGameResources(Context context, String packageName, int userId) {
        // Never delete ShaderCache, GPUCache, Code Cache, or .ushaderprecache during startup or gameplay.
        return 0;
    }
}
