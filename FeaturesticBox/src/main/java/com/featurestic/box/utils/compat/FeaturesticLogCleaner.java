package com.featurestic.box.utils.compat;

import android.content.Context;
import android.os.Handler;
import android.os.Looper;

import java.io.File;
import java.util.concurrent.atomic.AtomicBoolean;

import com.featurestic.box.core.env.BEnvironment;
import com.featurestic.box.utils.FileUtils;

public final class FeaturesticLogCleaner {
    private static volatile Boolean sEnabled;
    private static final AtomicBoolean sPeriodicRunning = new AtomicBoolean(false);
    private static final Handler sHandler = new Handler(Looper.getMainLooper());
    private static volatile int sLastCleanedCount = 0;

    // Never include files/ano_tmp, files/tss_tmp, files/logs, or Saved/Logs because UE4 and
    // libanogs.so create and mmap active runtime files there during startup and gameplay.
    private static final String[] LOG_REL_DIRS = new String[]{
            "app_bugly",
            "app_crashrecord",
            "app_crashSight",
            "cache/crash",
            "files/crash",
            "files/UE4Game/ShadowTrackerExtra/ShadowTrackerExtra/Saved/Crashes"
    };

    private FeaturesticLogCleaner() {
    }

    private static File getDisabledFlagFile() {
        return new File(BEnvironment.getSystemDir(), "featurestic_log_cleaner_disabled.flag");
    }

    public static boolean isAutoLogCleanerEnabled() {
        if (sEnabled != null) return sEnabled;
        try {
            File f = getDisabledFlagFile();
            sEnabled = !(f != null && f.exists());
            return sEnabled;
        } catch (Throwable ignored) {
            return true;
        }
    }

    public static void setAutoLogCleanerEnabled(boolean enabled) {
        sEnabled = enabled;
        try {
            File f = getDisabledFlagFile();
            FileUtils.mkdirs(f.getParentFile());
            if (enabled) {
                if (f.exists()) f.delete();
            } else {
                f.createNewFile();
            }
        } catch (Throwable ignored) {
        }
    }

    public static int getLastCleanedCount() {
        return sLastCleanedCount;
    }

    public static void startAutoCleanerForGame(final Context context, final String pkg, final int userId) {
        if (!isAutoLogCleanerEnabled()) return;
        if (sPeriodicRunning.compareAndSet(false, true)) {
            scheduleNextSweep(context, pkg, userId);
        }
    }

    private static void scheduleNextSweep(final Context context, final String pkg, final int userId) {
        sHandler.postDelayed(new Runnable() {
            @Override
            public void run() {
                if (!isAutoLogCleanerEnabled()) {
                    sPeriodicRunning.set(false);
                    return;
                }
                cleanContainerLogsAsync(context, pkg, userId);
                scheduleNextSweep(context, pkg, userId);
            }
        }, 60000L);
    }

    public static void cleanContainerLogsAsync(final Context context, final String pkg, final int userId) {
        Thread t = new Thread(new Runnable() {
            @Override
            public void run() {
                try {
                    int count = cleanContainerLogsNow(context, pkg, userId);
                    if (count > 0) {
                        sLastCleanedCount += count;
                    }
                } catch (Throwable ignored) {
                }
            }
        }, "FeaturesticLogCleaner");
        t.setPriority(Thread.MIN_PRIORITY);
        t.setDaemon(true);
        t.start();
    }

    public static int cleanContainerLogsNow(Context context, String pkg, int userId) {
        int cleaned = 0;
        try {
            if (pkg != null && !pkg.isEmpty()) {
                File dataDir = BEnvironment.getDataDir(pkg, userId);
                if (dataDir != null && dataDir.exists()) {
                    cleaned += sweepAppDirectory(dataDir);
                }
                File extDir = BEnvironment.getExternalDataDir(pkg);
                if (extDir != null && extDir.exists()) {
                    cleaned += sweepAppDirectory(extDir);
                }
            }
        } catch (Throwable ignored) {
        }
        return cleaned;
    }

    private static int sweepAppDirectory(File baseDir) {
        int count = 0;
        for (String rel : LOG_REL_DIRS) {
            File target = new File(baseDir, rel);
            if (target.exists()) {
                count += wipeLogFilesRecursive(target, 0);
            }
        }
        return count;
    }

    private static int wipeLogFilesRecursive(File file, int depth) {
        if (file == null || !file.exists() || depth > 3) return 0;
        int wiped = 0;
        if (file.isDirectory()) {
            File[] children = file.listFiles();
            if (children != null) {
                for (File c : children) {
                    wiped += wipeLogFilesRecursive(c, depth + 1);
                }
            }
        } else if (file.isFile()) {
            try {
                // Never truncate files with FileOutputStream (causes SIGBUS on mmap) and
                // only delete inactive crash files older than 5 minutes.
                long ageMs = System.currentTimeMillis() - file.lastModified();
                if (ageMs >= 300_000L && file.delete()) {
                    wiped++;
                }
            } catch (Throwable ignored) {
            }
        }
        return wiped;
    }
}
