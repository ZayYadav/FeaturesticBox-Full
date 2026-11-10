package com.featurestic.box.utils.compat;

import android.content.Context;
import android.content.pm.ApplicationInfo;
import android.content.pm.PackageInfo;
import android.content.pm.PackageManager;
import android.os.Build;
import android.util.Log;

import java.io.File;

import com.featurestic.box.FeaturesticBoxCore;
import com.featurestic.box.core.env.BEnvironment;
import com.featurestic.box.entity.pm.InstallOption;
import com.featurestic.box.entity.pm.InstallResult;
import com.featurestic.box.utils.FileUtils;
import com.featurestic.box.utils.NativeUtils;

public final class FeaturesticHotUpdateSync {
    private static final String TAG = "FeaturesticHotSync";
    private static volatile boolean sAutoSyncEnabled = true;

    private FeaturesticHotUpdateSync() {
    }

    public static void setAutoSyncEnabled(boolean enabled) {
        sAutoSyncEnabled = enabled;
    }

    public static boolean isAutoSyncEnabled() {
        return sAutoSyncEnabled;
    }

    /**
     * Called during launchApk(): runs a fast timestamp check without blocking the caller thread
     * or copying multi-GB OBB files on the UI thread.
     */
    public static boolean syncIfHostUpdated(String packageName, int userId) {
        if (!sAutoSyncEnabled || packageName == null || packageName.trim().isEmpty()) {
            return false;
        }
        try {
            File virtBaseApk = new File(BEnvironment.getAppDir(packageName), "base.apk");
            if (virtBaseApk.isFile() && virtBaseApk.length() > 0) {
                Context hostCtx = FeaturesticBoxCore.getContext();
                if (hostCtx == null) return false;
                ApplicationInfo hostAppInfo;
                try {
                    hostAppInfo = hostCtx.getPackageManager().getApplicationInfo(packageName, 0);
                } catch (Throwable notOnHost) {
                    return false;
                }
                if (hostAppInfo == null || hostAppInfo.sourceDir == null) {
                    return false;
                }
                File hostBaseApk = new File(hostAppInfo.sourceDir);
                if (!hostBaseApk.isFile() || hostBaseApk.length() == virtBaseApk.length()) {
                    // 99.9% fast-path on every launch: zero Binder IPC to BPackageManager, zero OBB copy!
                    return false;
                }
            } else {
                return false;
            }
        } catch (Throwable ignored) {
            return false;
        }
        return syncInPlaceNow(packageName, userId);
    }

    public static boolean syncInPlaceNow(String packageName, int userId) {
        try {
            Context hostCtx = FeaturesticBoxCore.getContext();
            if (hostCtx == null) return false;

            PackageManager hostPm = hostCtx.getPackageManager();
            PackageInfo hostInfo = hostPm.getPackageInfo(packageName, 0);
            if (hostInfo == null || hostInfo.applicationInfo == null || hostInfo.applicationInfo.sourceDir == null) {
                return false;
            }

            PackageInfo virtualInfo = FeaturesticBoxCore.getBPackageManager().getPackageInfo(packageName, 0, userId);
            if (virtualInfo == null) {
                return false;
            }

            long hostVer = getLongVersionCodeCompat(hostInfo);
            long virtVer = getLongVersionCodeCompat(virtualInfo);
            if (hostVer <= virtVer) {
                return false;
            }

            Log.i(TAG, "Detected host game update for " + packageName
                    + " (virtual=" + virtVer + " -> host=" + hostVer + "). Syncing in-place...");

            InstallResult res = FeaturesticBoxCore.getBPackageManager().installPackageAsUser(
                    hostInfo.applicationInfo.sourceDir,
                    InstallOption.installBySystem(),
                    userId);

            ApplicationInfo hostAppInfo = hostInfo.applicationInfo;
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP
                    && hostAppInfo.splitSourceDirs != null
                    && hostAppInfo.splitSourceDirs.length > 0) {
                File appDir = BEnvironment.getAppDir(packageName);
                File appLibDir = BEnvironment.getAppLibDir(packageName);
                FileUtils.mkdirs(appDir);
                FileUtils.mkdirs(appLibDir);
                for (String splitPath : hostAppInfo.splitSourceDirs) {
                    if (splitPath == null) continue;
                    File splitFile = new File(splitPath);
                    if (splitFile.isFile() && splitFile.length() > 0) {
                        File dstSplit = new File(appDir, splitFile.getName());
                        FileUtils.copyFile(splitFile, dstSplit);
                        try {
                            NativeUtils.copyNativeLib(dstSplit, appLibDir);
                        } catch (Throwable ignored) {
                        }
                    }
                }
            }
            return res != null && res.success;
        } catch (Throwable t) {
            return false;
        }
    }

    @SuppressWarnings("deprecation")
    private static long getLongVersionCodeCompat(PackageInfo info) {
        if (info == null) return 0L;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            return info.getLongVersionCode();
        }
        return info.versionCode;
    }
}
