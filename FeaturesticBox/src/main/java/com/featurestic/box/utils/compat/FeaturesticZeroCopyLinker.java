package com.featurestic.box.utils.compat;

import android.system.Os;

import com.featurestic.box.core.env.BEnvironment;
import com.featurestic.box.utils.FileUtils;

import java.io.File;

/**
 * Fast OBB & Resource Linker that cleans partial/truncated OBB copies and never blocks startup.
 */
public final class FeaturesticZeroCopyLinker {

    private FeaturesticZeroCopyLinker() {
    }

    public static int linkGameObbAndMapPaks(String packageName, int userId) {
        if (packageName == null || packageName.trim().isEmpty()) {
            return 0;
        }
        int linkedCount = 0;
        try {
            File targetObbDir = BEnvironment.getExternalUserAppObbDir(userId, packageName);
            FileUtils.mkdirs(targetObbDir);

            File[] sourceObbDirs = new File[]{
                    BEnvironment.getHostExternalObbDir(packageName),
                    new File("/sdcard/Android/obb/" + packageName),
                    BEnvironment.getExternalUserAppObbDir(0, packageName)
            };
            for (File srcDir : sourceObbDirs) {
                if (srcDir == null || !srcDir.isDirectory() || srcDir.equals(targetObbDir)) {
                    continue;
                }
                File[] files = srcDir.listFiles();
                if (files == null) continue;
                for (File f : files) {
                    if (f != null && f.isFile() && f.getName().endsWith(".obb") && f.length() > 10L * 1024L * 1024L) {
                        File dst = new File(targetObbDir, f.getName());
                        if (dst.exists() && dst.length() != f.length()) {
                            try {
                                dst.delete();
                            } catch (Throwable ignored) {
                            }
                        }
                        if (!dst.exists()) {
                            try {
                                Os.symlink(f.getAbsolutePath(), dst.getAbsolutePath());
                                linkedCount++;
                            } catch (Throwable ignored) {
                            }
                        }
                    }
                }
            }
        } catch (Throwable ignored) {
        }
        return linkedCount;
    }
}
