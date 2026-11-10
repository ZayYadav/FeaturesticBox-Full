package com.featurestic.box.core.env;

import android.app.Activity;
import android.content.Context;
import android.os.Environment;
import android.os.Handler;
import android.os.Looper;
import android.view.View;
import android.view.ViewGroup;
import android.view.Window;
import android.widget.TextView;

import com.featurestic.box.FeaturesticBoxCore;
import com.featurestic.box.app.BActivityThread;
import com.featurestic.box.utils.FileUtils;
import com.featurestic.box.utils.compat.BuildCompat;

import java.io.File;
import java.io.FilenameFilter;
import java.util.ArrayList;
import java.util.Locale;
import java.util.Map;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.TimeUnit;

import black.android.app.BRActivityThread;
import black.android.app.BRActivityThreadActivityClientRecord;

public class BEnvironment {

    private static final File sVBoxRoot = FeaturesticBoxCore.getContext().getFilesDir();
    private static final File sExternalVBoxRoot = resolveRealExternalStorageRoot();

    private static File resolveRealExternalStorageRoot() {
        String[] candidates = new String[]{
                "/storage/emulated/" + FeaturesticBoxCore.getHostUserId(),
                "/storage/emulated/0",
                "/sdcard"
        };
        for (String path : candidates) {
            File f = new File(path);
            if (f.isDirectory()) {
                return f;
            }
        }
        try {
            File envDir = Environment.getExternalStorageDirectory();
            if (envDir != null && !envDir.getAbsolutePath().endsWith("/SdCard")) {
                return envDir;
            }
        } catch (Throwable ignored) {
        }
        return new File("/storage/emulated/0");
    }

    public static void load() {
        FileUtils.mkdirs(sVBoxRoot);
        FileUtils.mkdirs(sExternalVBoxRoot);
        FileUtils.mkdirs(getSystemDir());
        FileUtils.mkdirs(getCacheDir());
        FileUtils.mkdirs(getProcDir());
    }

    public static File getVBoxRoot() {
        return sVBoxRoot;
    }

    public static ArrayList<String> getAllDex(String str) {
        File appDir = getAppDir(str);
        ArrayList<String> arrayList = new ArrayList<>();
        File[] listFiles = appDir.listFiles(new FilenameFilter() {
            @Override
            public boolean accept(File dir, String name) {
                return name != null && name.endsWith(".apk");
            }
        });
        if (listFiles != null) {
            for (File file : listFiles) {
                if (BuildCompat.isUpsideDownCake()) {
                    try {
                        file.setReadOnly();
                    } catch (Throwable th) {
                        th.printStackTrace();
                    }
                }
                arrayList.add(file.getAbsolutePath());
            }
        }
        return arrayList;
    }

    public static File getSystemDir() {
        return new File(sVBoxRoot, "system");
    }

    public static File getProcDir() {
        return new File(sVBoxRoot, "proc");
    }

    public static File getCacheDir() {
        return new File(sVBoxRoot, "cache");
    }

    public static File getUserDir(int userId) {
        return new File(sVBoxRoot, String.format(Locale.CHINA, "data/user/%d", userId));
    }

    public static File getDeDataDir(String packageName, int userId) {
        return new File(sVBoxRoot, String.format(Locale.CHINA, "data/user_de/%d/%s", userId, packageName));
    }

    public static File getDataDir(String packageName, int userId) {
        return new File(sVBoxRoot, String.format(Locale.CHINA, "data/user/%d/%s", userId, packageName));
    }

    public static File getAppDir(String packageName) {
        return new File(sVBoxRoot, "data/app/" + packageName);
    }

    public static File getBaseApkDir(String packageName) {
        return new File(sVBoxRoot, "data/app/" + packageName + "/base.apk");
    }

    public static File getUserInfoConf() {
        return new File(getSystemDir(), "user.conf");
    }

    public static File getAccountsConf() {
        return new File(getSystemDir(), "accounts.conf");
    }

    public static File getUidConf() {
        return new File(getSystemDir(), "uid.conf");
    }

    public static File getSharedUserConf() {
        return new File(getSystemDir(), "shared-user.conf");
    }

    public static File getXPModuleConf() {
        return new File(getSystemDir(), "xposed-module.conf");
    }

    public static File getFakeLocationConf() {
        return new File(getSystemDir(), "fake-location.conf");
    }

    public static File getFakeDeviceConf() {
        return new File(getSystemDir(), "fake-device.conf");
    }

    public static File getPackageConf(String packageName) {
        return new File(getAppDir(packageName), "package.conf");
    }

    public static File getExternalStorageDirectory() {
        File root = sExternalVBoxRoot != null ? sExternalVBoxRoot : resolveRealExternalStorageRoot();
        File legacySdCard = new File(root, "SdCard");
        try {
            if (legacySdCard.isDirectory() && legacySdCard.canWrite()) {
                return legacySdCard;
            }
            if (legacySdCard.mkdirs() && legacySdCard.canWrite()) {
                return legacySdCard;
            }
        } catch (Throwable ignored) {
        }
        try {
            Context ctx = FeaturesticBoxCore.getContext();
            if (ctx != null) {
                File appExt = ctx.getExternalFilesDir("SdCard");
                if (appExt != null) {
                    FileUtils.mkdirs(appExt);
                    return appExt;
                }
            }
        } catch (Throwable ignored) {
        }
        return legacySdCard;
    }

    public static File getHostExternalDataDir(String packageName) {
        File root = sExternalVBoxRoot != null ? sExternalVBoxRoot : resolveRealExternalStorageRoot();
        return new File(root, String.format(Locale.CHINA, "Android/data/%s", packageName));
    }

    public static File getHostExternalObbDir(String packageName) {
        File root = sExternalVBoxRoot != null ? sExternalVBoxRoot : resolveRealExternalStorageRoot();
        return new File(root, String.format(Locale.CHINA, "Android/obb/%s", packageName));
    }

    public static File getExternalDataDir(String packageName) {
        File dir = new File(getExternalStorageDirectory(), String.format(Locale.CHINA, "Android/data/%s", packageName));
        FileUtils.mkdirs(dir);
        return dir;
    }

    public static File getExternalObbDir(String packageName) {
        stabilizeHostActivityTextViewsForBackgroundSetText();
        File dir = new File(getExternalStorageDirectory(), String.format(Locale.CHINA, "Android/obb/%s/", packageName));
        FileUtils.mkdirs(dir);
        return dir;
    }

    public static void stabilizeHostActivityTextViewsForBackgroundSetText() {
        try {
            if (FeaturesticBoxCore.get().isFeaturesticProcess() || FeaturesticBoxCore.get().isServerProcess()) {
                return;
            }
            Runnable task = new Runnable() {
                @Override
                public void run() {
                    try {
                        Object mainThread = FeaturesticBoxCore.mainThread();
                        if (mainThread == null) return;
                        Map<?, ?> activities = BRActivityThread.get(mainThread).mActivities();
                        if (activities == null || activities.isEmpty()) return;
                        for (Object record : activities.values()) {
                            if (record == null) continue;
                            Activity act = BRActivityThreadActivityClientRecord.get(record).activity();
                            if (act == null || act.isFinishing()) continue;
                            Window win = act.getWindow();
                            if (win == null) continue;
                            View decor = win.peekDecorView();
                            if (decor != null) {
                                lockWrapContentTextViews(decor);
                            }
                        }
                    } catch (Throwable ignored) {
                    }
                }
            };
            if (Looper.myLooper() == Looper.getMainLooper()) {
                task.run();
            } else {
                final CountDownLatch latch = new CountDownLatch(1);
                new Handler(Looper.getMainLooper()).post(new Runnable() {
                    @Override
                    public void run() {
                        try {
                            task.run();
                        } finally {
                            latch.countDown();
                        }
                    }
                });
                latch.await(80, TimeUnit.MILLISECONDS);
            }
        } catch (Throwable ignored) {
        }
    }

    private static void lockWrapContentTextViews(View view) {
        if (view == null) return;
        if (view instanceof TextView && !(view instanceof android.widget.Button) && !(view instanceof android.widget.EditText)) {
            ViewGroup.LayoutParams lp = view.getLayoutParams();
            if (lp != null) {
                if (lp.width == ViewGroup.LayoutParams.WRAP_CONTENT) {
                    lp.width = ViewGroup.LayoutParams.MATCH_PARENT;
                }
                int h = view.getHeight();
                if (h > 0 && lp.height == ViewGroup.LayoutParams.WRAP_CONTENT) {
                    lp.height = h;
                }
            }
        } else if (view instanceof ViewGroup) {
            ViewGroup vg = (ViewGroup) view;
            int count = vg.getChildCount();
            for (int i = 0; i < count; i++) {
                lockWrapContentTextViews(vg.getChildAt(i));
            }
        }
    }

    public static File getExternalUserAppDataDir(int userId, String packageName) {
        if (userId <= 0) {
            return getExternalDataDir(packageName);
        }
        File dir = new File(getExternalStorageDirectory(),
                String.format(Locale.CHINA, "users/%d/Android/data/%s", userId, packageName));
        FileUtils.mkdirs(dir);
        return dir;
    }

    public static File getExternalUserAppObbDir(int userId, String packageName) {
        if (userId <= 0) {
            return getExternalObbDir(packageName);
        }
        File dir = new File(getExternalStorageDirectory(),
                String.format(Locale.CHINA, "users/%d/Android/obb/%s", userId, packageName));
        FileUtils.mkdirs(dir);
        return dir;
    }

    public static File getProcDir(int pid) {
        File file = new File(getProcDir(), String.format(Locale.CHINA, "%d", pid));
        FileUtils.mkdirs(file);
        return file;
    }

    public static File getExternalDataFilesDir(String packageName) {
        return new File(getExternalDataDir(packageName), "files");
    }

    public static File getDataFilesDir(String packageName, int userId) {
        return new File(getDataDir(packageName, userId), "files");
    }

    public static File getExternalDataCacheDir(String packageName) {
        return new File(getExternalDataDir(packageName), "cache");
    }

    public static File getDataCacheDir(String packageName, int userId) {
        return new File(getDataDir(packageName, userId), "cache");
    }

    public static File getDataLibDir(String packageName, int userId) {
        return new File(getDataDir(packageName, userId), "lib");
    }

    public static File getDataDatabasesDir(String packageName, int userId) {
        return new File(getDataDir(packageName, userId), "databases");
    }

    public static File getAppRootDir() {
        return getAppDir("");
    }

    public static File getAppLibDir(String packageName) {
        return new File(getAppDir(packageName), "lib");
    }

    public static File getXSharedPreferences(String packageName, String prefFileName) {
        return new File(BEnvironment.getDataDir(packageName, BActivityThread.getUserId()), "shared_prefs/" + prefFileName + ".xml");
    }
}
