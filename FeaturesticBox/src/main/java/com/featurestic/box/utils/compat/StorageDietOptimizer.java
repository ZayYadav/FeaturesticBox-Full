package com.featurestic.box.utils.compat;

import java.io.File;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.ThreadFactory;
import java.util.concurrent.atomic.AtomicBoolean;

import com.featurestic.box.core.env.BEnvironment;

public final class StorageDietOptimizer {
    private static final ExecutorService DIET_POOL = Executors.newSingleThreadExecutor(new ThreadFactory() {
        @Override
        public Thread newThread(Runnable r) {
            Thread t = new Thread(r, "Featurestic-StorageDiet");
            t.setDaemon(true);
            t.setPriority(Thread.MIN_PRIORITY);
            return t;
        }
    });
    private static final AtomicBoolean sFirstLaunchDelayed = new AtomicBoolean(false);

    private static final String[] JUNK_DIR_NAMES = new String[]{
            "Crashes",
            "anr",
            "tombstones",
            "bugly",
            "CrashSight"
    };

    private StorageDietOptimizer() {
    }

    public static void trimJunkAsync(final String packageName, final int userId) {
        if (packageName == null || packageName.isEmpty()) return;
        final boolean shouldDelay = sFirstLaunchDelayed.compareAndSet(false, true);
        DIET_POOL.execute(new Runnable() {
            @Override
            public void run() {
                if (shouldDelay) {
                    try {
                        Thread.sleep(60000L);
                    } catch (InterruptedException ignored) {
                        return;
                    }
                }
                trimJunkNow(packageName, userId);
            }
        });
    }

    public static long trimJunkNow(String packageName, int userId) {
        if (packageName == null || packageName.isEmpty()) return 0L;
        long freedBytes = 0L;
        try {
            File internalData = BEnvironment.getDataDir(packageName, userId);
            freedBytes += trimDirectoryTree(internalData, 0);
        } catch (Throwable ignored) {
        }
        try {
            File extData = BEnvironment.getExternalUserAppDataDir(userId, packageName);
            freedBytes += trimDirectoryTree(extData, 0);
        } catch (Throwable ignored) {
        }
        return freedBytes;
    }

    private static long trimDirectoryTree(File dir, int depth) {
        if (dir == null || !dir.isDirectory() || depth > 5) {
            return 0L;
        }
        File[] children = dir.listFiles();
        if (children == null) return 0L;
        long freed = 0L;
        long now = System.currentTimeMillis();
        for (File child : children) {
            if (child == null) continue;
            String name = child.getName();
            if ("Paks".equalsIgnoreCase(name)
                    || "Saved".equalsIgnoreCase(name)
                    || "Config".equalsIgnoreCase(name)
                    || "ano_tmp".equalsIgnoreCase(name)
                    || "tss_tmp".equalsIgnoreCase(name)
                    || "shared_prefs".equalsIgnoreCase(name)
                    || "databases".equalsIgnoreCase(name)
                    || "app_webview".equalsIgnoreCase(name)) {
                continue;
            }
            if (child.isDirectory()) {
                if (isJunkDirectoryName(name)) {
                    freed += deleteOldFilesInside(child, now);
                } else {
                    freed += trimDirectoryTree(child, depth + 1);
                }
            } else {
                String lower = name.toLowerCase(java.util.Locale.US);
                if ((lower.endsWith(".dmp") || lower.endsWith(".tlog"))
                        && (now - child.lastModified() >= 300_000L)) {
                    long len = child.length();
                    if (child.delete()) {
                        freed += len;
                    }
                }
            }
        }
        return freed;
    }

    private static boolean isJunkDirectoryName(String name) {
        if (name == null) return false;
        for (String j : JUNK_DIR_NAMES) {
            if (j.equalsIgnoreCase(name)) {
                return true;
            }
        }
        return false;
    }

    private static long deleteOldFilesInside(File dir, long now) {
        if (dir == null || !dir.isDirectory()) return 0L;
        long bytes = 0L;
        File[] files = dir.listFiles();
        if (files == null) return 0L;
        for (File f : files) {
            if (f == null) continue;
            if (f.isDirectory()) {
                bytes += deleteOldFilesInside(f, now);
            } else if (now - f.lastModified() >= 300_000L) {
                long len = f.length();
                if (f.delete()) {
                    bytes += len;
                }
            }
        }
        return bytes;
    }
}
