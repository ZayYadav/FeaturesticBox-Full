package com.featurestic.box.core;

import android.annotation.SuppressLint;
import android.content.Context;
import android.content.pm.ApplicationInfo;
import android.content.pm.PackageManager;
import android.os.Process;
import android.system.Os;
import android.text.TextUtils;

import java.io.File;
import java.io.FileOutputStream;
import java.nio.charset.StandardCharsets;
import java.util.HashSet;
import java.util.LinkedHashMap;
import java.util.Map;
import java.util.Objects;
import java.util.Set;

import com.featurestic.box.FeaturesticBoxCore;
import com.featurestic.box.app.BActivityThread;
import com.featurestic.box.core.env.BEnvironment;
import com.featurestic.box.utils.FileUtils;
import com.featurestic.box.utils.TrieTree;

@SuppressLint("SdCardPath")
public class RCore {
    public static final String TAG = "RCore";

    private static final RCore sRCore = new RCore();
    private static final TrieTree mTrieTree = new TrieTree();
    private static final TrieTree sBlackTree = new TrieTree();
    private final Map<String, String> mRedirectMap = new LinkedHashMap<>();

    public static RCore get() {
        return sRCore;
    }

    public void addRedirect(String origPath, String redirectPath) {
        if (TextUtils.isEmpty(origPath) || TextUtils.isEmpty(redirectPath) || mRedirectMap.get(origPath) != null)
            return;
        mTrieTree.add(origPath);
        mRedirectMap.put(origPath, redirectPath);
        File redirectFile = new File(redirectPath);
        if (!redirectFile.exists()) {
            if (origPath.endsWith("/cmdline") || redirectPath.endsWith("/cmdline") || redirectPath.endsWith("-fake")) {
                if (redirectFile.getParentFile() != null) {
                    FileUtils.mkdirs(redirectFile.getParentFile());
                }
            } else {
                FileUtils.mkdirs(redirectPath);
            }
        }
        RNative.addIORule(origPath, redirectPath);
    }

    public void addBlackRedirect(String path) {
        if (TextUtils.isEmpty(path))
            return;
        sBlackTree.add(path);
    }

    public String redirectPath(String path) {
        if (TextUtils.isEmpty(path))
            return path;
        if ((path.contains("/SdCard/") && !path.contains("/Android/obb/"))
                || path.contains("/files/data/")
                || path.contains("/files/user_de/")
                || path.contains("/Download/")
                || path.endsWith("/Download")) {
            return path;
        }
        String search = sBlackTree.search(path);
        if (!TextUtils.isEmpty(search))
            return path;

        String key = mTrieTree.search(path);
        if (!TextUtils.isEmpty(key)) {
            String target = mRedirectMap.get(key);
            if (target != null) {
                if (path.startsWith(target)) {
                    return path;
                }
                if (path.startsWith(key)) {
                    path = target + path.substring(key.length());
                } else {
                    path = path.replaceFirst(java.util.regex.Pattern.quote(key), java.util.regex.Matcher.quoteReplacement(target));
                }
            }
        }
        return path;
    }

    public File redirectPath(File path) {
        if (path == null)
            return null;
        String pathStr = path.getAbsolutePath();
        return new File(redirectPath(pathStr));
    }

    public String redirectPath(String path, Map<String, String> rule) {
        if (TextUtils.isEmpty(path))
            return path;
        String key = mTrieTree.search(path);
        if (!TextUtils.isEmpty(key))
            path = path.replace(key, Objects.requireNonNull(rule.get(key)));
        return path;
    }

    public File redirectPath(File path, Map<String, String> rule) {
        if (path == null)
            return null;
        String pathStr = path.getAbsolutePath();
        return new File(redirectPath(pathStr, rule));
    }

    private static boolean hasValidObbFiles(File dir) {
        if (dir == null || !dir.isDirectory()) return false;
        File[] files = dir.listFiles((d, name) -> name != null && name.endsWith(".obb"));
        if (files == null || files.length == 0) return false;
        for (File f : files) {
            if (f != null && f.isFile() && f.length() > 10L * 1024L * 1024L) {
                return true;
            }
        }
        return false;
    }

    public void enableRedirect(Context context) {
        Map<String, String> rule = new LinkedHashMap<>();
        Set<String> blackRule = new HashSet<>();
        String packageName = context.getPackageName();

        try {
            ApplicationInfo packageInfo = FeaturesticBoxCore.getBPackageManager().getApplicationInfo(packageName, PackageManager.GET_META_DATA, BActivityThread.getUserId());
            int systemUserId = FeaturesticBoxCore.getHostUserId();
            rule.put(String.format("/data/data/%s/lib", packageName), packageInfo.nativeLibraryDir);
            rule.put(String.format("/data/user/%d/%s/lib", systemUserId, packageName), packageInfo.nativeLibraryDir);
            rule.put(String.format("/data/user/0/%s/lib", packageName), packageInfo.nativeLibraryDir);

            rule.put(String.format("/data/data/%s", packageName), packageInfo.dataDir);
            rule.put(String.format("/data/user/%d/%s", systemUserId, packageName), packageInfo.dataDir);
            rule.put(String.format("/data/user/0/%s", packageName), packageInfo.dataDir);
            String deDataDir = BEnvironment.getDeDataDir(packageName, BActivityThread.getUserId()).getAbsolutePath();
            rule.put(String.format("/data/user_de/%d/%s", systemUserId, packageName), deDataDir);
            rule.put(String.format("/data/user_de/0/%s", packageName), deDataDir);
            for (String subDir : new String[]{"files", "cache", "code_cache", "databases", "shared_prefs", "app_webview", "files/ano_tmp", "files/tss_tmp"}) {
                FileUtils.mkdirs(new File(packageInfo.dataDir, subDir));
                FileUtils.mkdirs(new File(deDataDir, subDir));
            }
            try {
                File dataLibLink = new File(packageInfo.dataDir, "lib");
                if (!dataLibLink.exists() && packageInfo.nativeLibraryDir != null) {
                    Os.symlink(packageInfo.nativeLibraryDir, dataLibLink.getAbsolutePath());
                }
            } catch (Throwable ignored) {
            }
            FileUtils.mkdirs(BEnvironment.getExternalDataFilesDir(packageName));
            FileUtils.mkdirs(BEnvironment.getExternalDataCacheDir(packageName));
            FileUtils.mkdirs(BEnvironment.getExternalObbDir(packageName));

            File profilesRoot = new File(BEnvironment.getVBoxRoot(), "profiles");
            FileUtils.mkdirs(profilesRoot.getAbsolutePath());
            rule.put("/data/misc/profiles", profilesRoot.getAbsolutePath());

            File profilesCurDir = new File(profilesRoot, String.format("cur/%d/%s", BActivityThread.getUserId(), packageName));
            File profilesRefDir = new File(profilesRoot, String.format("ref/%d/%s", BActivityThread.getUserId(), packageName));
            FileUtils.mkdirs(profilesCurDir.getAbsolutePath());
            FileUtils.mkdirs(profilesRefDir.getAbsolutePath());
            rule.put(String.format("/data/misc/profiles/cur/%d/%s", BActivityThread.getUserId(), packageName), profilesCurDir.getAbsolutePath());
            rule.put(String.format("/data/misc/profiles/ref/%d/%s", BActivityThread.getUserId(), packageName), profilesRefDir.getAbsolutePath());

            File external = BEnvironment.getExternalStorageDirectory();
            FileUtils.mkdirs(external);
            File virtualObbDir = new File(external, "Android/obb/" + packageName);
            FileUtils.mkdirs(virtualObbDir);
            // Always redirect guest OBB requests to the verified virtual game's
            // expansion directory. Never silently redirect to another app's
            // host OBB folder: access can be denied by Android scoped storage,
            // and that fallback previously masked missing virtual copies.
            String virtualObbPath = virtualObbDir.getAbsolutePath();
            rule.put("/sdcard/Android/obb/" + packageName, virtualObbPath);
            rule.put("/mnt/sdcard/Android/obb/" + packageName, virtualObbPath);
            rule.put("/storage/self/primary/Android/obb/" + packageName, virtualObbPath);
            rule.put("/storage/emulated/0/Android/obb/" + packageName, virtualObbPath);
            rule.put(String.format("/storage/emulated/%d/Android/obb/%s",
                    systemUserId, packageName), virtualObbPath);
            rule.put("/sdcard", external.getAbsolutePath());
            rule.put("/mnt/sdcard", external.getAbsolutePath());
            rule.put("/storage/self/primary", external.getAbsolutePath());
            rule.put("/storage/emulated/0", external.getAbsolutePath());
            rule.put(String.format("/storage/emulated/%d", systemUserId), external.getAbsolutePath());
            blackRule.add("/sdcard/Pictures");
            blackRule.add("/storage/emulated/0/Pictures");
            blackRule.add(String.format("/storage/emulated/%d/Pictures", systemUserId));
            blackRule.add("/sdcard/Download");
            blackRule.add("/storage/emulated/0/Download");
            blackRule.add(String.format("/storage/emulated/%d/Download", systemUserId));
            if (FeaturesticBoxCore.get().setHideRoot()) {
                hideRoot(rule);
            }
            proc(rule);
        } catch (Exception e) {
            e.printStackTrace();
        }
        for (String key : rule.keySet()) {
            get().addRedirect(key, rule.get(key));
        }
        for (String s : blackRule) {
            get().addBlackRedirect(s);
        }
        RNative.enableIO();
    }

    private void hideRoot(Map<String, String> rule) {
        rule.put("/system/app/Superuser.apk", "/system/app/Superuser.apk-fake");
        rule.put("/sbin/su", "/sbin/su-fake");
        rule.put("/system/bin/su", "/system/bin/su-fake");
        rule.put("/system/xbin/su", "/system/xbin/su-fake");
        rule.put("/data/local/xbin/su", "/data/local/xbin/su-fake");
        rule.put("/data/local/bin/su", "/data/local/bin/su-fake");
        rule.put("/system/sd/xbin/su", "/system/sd/xbin/su-fake");
        rule.put("/system/bin/failsafe/su", "/system/bin/failsafe/su-fake");
        rule.put("/data/local/su", "/data/local/su-fake");
        rule.put("/su/bin/su", "/su/bin/su-fake");
        rule.put("/sbin/magisk", "/sbin/magisk-fake");
        rule.put("/system/bin/magisk", "/system/bin/magisk-fake");
        rule.put("/data/adb/magisk", "/data/adb/magisk-fake");
        rule.put("/data/adb/ksud", "/data/adb/ksud-fake");
        rule.put("/data/adb/ap", "/data/adb/ap-fake");
        rule.put("/data/adb/modules", "/data/adb/modules-fake");
    }

    private void proc(Map<String, String> rule) {
        int appPid = BActivityThread.getAppPid();
        int pid = Process.myPid();
        String selfProc = "/proc/self/";
        String proc = "/proc/" + pid + "/";

        String procName = BActivityThread.getAppProcessName();
        if (TextUtils.isEmpty(procName)) {
            procName = BActivityThread.getAppPackageName();
        }

        File cmdlineFile = new File(BEnvironment.getProcDir(appPid), "cmdline");
        writeCmdlineFileSafely(cmdlineFile, procName);
        if (pid != appPid) {
            File myPidCmdline = new File(BEnvironment.getProcDir(pid), "cmdline");
            writeCmdlineFileSafely(myPidCmdline, procName);
        }

        String cmdline = cmdlineFile.getAbsolutePath();
        rule.put(proc + "cmdline", cmdline);
        rule.put(selfProc + "cmdline", cmdline);
        File procRoot = BEnvironment.getProcDir();
        File[] procDirs = procRoot != null ? procRoot.listFiles() : null;
        if (procDirs != null) {
            for (File dir : procDirs) {
                if (dir != null && dir.isDirectory()) {
                    File subCmd = new File(dir, "cmdline");
                    if (subCmd.isFile()) {
                        rule.put("/proc/" + dir.getName() + "/cmdline", subCmd.getAbsolutePath());
                    }
                }
            }
        }
    }

    private static void writeCmdlineFileSafely(File cmdlineFile, String procName) {
        if (cmdlineFile == null || TextUtils.isEmpty(procName)) return;
        try {
            if (cmdlineFile.isDirectory()) {
                FileUtils.deleteDir(cmdlineFile);
            }
            if (cmdlineFile.getParentFile() != null) {
                FileUtils.mkdirs(cmdlineFile.getParentFile());
            }
            try (FileOutputStream fos = new FileOutputStream(cmdlineFile, false)) {
                fos.write((procName + "\0").getBytes(StandardCharsets.UTF_8));
            }
        } catch (Throwable ignored) {
        }
    }
}
