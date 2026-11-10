package com.featurestic.box.utils.compat;

import android.app.Activity;
import android.content.Context;
import android.content.Intent;
import android.net.Uri;
import android.os.Build;
import android.os.Handler;
import android.os.Looper;
import android.os.PowerManager;
import android.provider.Settings;

import com.featurestic.box.FeaturesticBoxCore;

public final class FeaturesticBatteryAndOemGuard {
    private static final long MINIMIZE_KEEP_ALIVE_MS = 180_000L;
    private static PowerManager.WakeLock sMinimizeWakeLock;
    private static final Handler MAIN_HANDLER = new Handler(Looper.getMainLooper());
    private static final Runnable RELEASE_TASK = new Runnable() {
        @Override
        public void run() {
            releaseMinimizeKeepAlive();
        }
    };

    private FeaturesticBatteryAndOemGuard() {
    }

    public static void onGameResumed(Activity activity) {
        MAIN_HANDLER.removeCallbacks(RELEASE_TASK);
    }

    public static synchronized void onGameMinimized(Context context) {
        Context ctx = context != null ? context.getApplicationContext() : FeaturesticBoxCore.getContext();
        if (ctx == null) return;
        try {
            if (sMinimizeWakeLock == null) {
                PowerManager pm = (PowerManager) ctx.getSystemService(Context.POWER_SERVICE);
                if (pm != null) {
                    sMinimizeWakeLock = pm.newWakeLock(
                            PowerManager.PARTIAL_WAKE_LOCK,
                            "Featurestic:MinimizeKeepAlive");
                    sMinimizeWakeLock.setReferenceCounted(false);
                }
            }
            if (sMinimizeWakeLock != null && !sMinimizeWakeLock.isHeld()) {
                sMinimizeWakeLock.acquire(MINIMIZE_KEEP_ALIVE_MS);
            }
            MAIN_HANDLER.removeCallbacks(RELEASE_TASK);
            MAIN_HANDLER.postDelayed(RELEASE_TASK, MINIMIZE_KEEP_ALIVE_MS);
        } catch (Throwable ignored) {
        }
    }

    public static synchronized void releaseMinimizeKeepAlive() {
        try {
            if (sMinimizeWakeLock != null && sMinimizeWakeLock.isHeld()) {
                sMinimizeWakeLock.release();
            }
        } catch (Throwable ignored) {
        }
    }

    public static boolean isIgnoringBatteryOptimizations(Context context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) return true;
        try {
            Context ctx = context != null ? context : FeaturesticBoxCore.getContext();
            if (ctx == null) return true;
            PowerManager pm = (PowerManager) ctx.getSystemService(Context.POWER_SERVICE);
            return pm == null || pm.isIgnoringBatteryOptimizations(ctx.getPackageName());
        } catch (Throwable ignored) {
            return true;
        }
    }

    public static void requestIgnoreBatteryOptimizationsIfNeeded(Activity hostActivity) {
        if (hostActivity == null || Build.VERSION.SDK_INT < Build.VERSION_CODES.M) return;
        try {
            if (!isIgnoringBatteryOptimizations(hostActivity)) {
                Intent intent = new Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS);
                intent.setData(Uri.parse("package:" + hostActivity.getPackageName()));
                hostActivity.startActivity(intent);
            }
        } catch (Throwable ignored) {
        }
    }
}
