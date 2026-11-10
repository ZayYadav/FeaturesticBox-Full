package com.featurestic.box.core.system.pm.installer;

import android.content.pm.ApplicationInfo;

import java.io.File;
import java.io.IOException;

import com.featurestic.box.FeaturesticBoxCore;
import com.featurestic.box.core.env.BEnvironment;
import com.featurestic.box.core.system.pm.BPackageSettings;
import com.featurestic.box.entity.pm.InstallOption;
import com.featurestic.box.utils.FileUtils;
import com.featurestic.box.utils.NativeUtils;
import com.featurestic.box.utils.compat.BuildCompat;

public class CopyExecutor implements Executor {

    @Override
    public int exec(BPackageSettings ps, InstallOption option, int userId) {
        try {
            File baseApk = new File(ps.pkg.baseCodePath);
            File appLibDir = BEnvironment.getAppLibDir(ps.pkg.packageName);
            FileUtils.mkdirs(appLibDir);
            NativeUtils.copyNativeLib(baseApk, appLibDir);

            // 1. Copy native libs from any split APKs in the same directory
            File parentDir = baseApk.getParentFile();
            if (parentDir != null && parentDir.isDirectory() && !parentDir.equals(BEnvironment.getAppDir(ps.pkg.packageName))) {
                File[] splitApks = parentDir.listFiles((dir, name) ->
                        name != null && name.endsWith(".apk") && !name.equals(baseApk.getName()));
                if (splitApks != null) {
                    for (File splitApk : splitApks) {
                        try {
                            NativeUtils.copyNativeLib(splitApk, appLibDir);
                            if (option.isFlag(InstallOption.FLAG_STORAGE) && !option.isFlag(InstallOption.FLAG_URI_FILE)) {
                                File dstSplit = new File(BEnvironment.getAppDir(ps.pkg.packageName), splitApk.getName());
                                if (!dstSplit.exists() || dstSplit.length() != splitApk.length()) {
                                    FileUtils.copyFile(splitApk, dstSplit);
                                    if (BuildCompat.isUpsideDownCake()) {
                                        try {
                                            dstSplit.setReadOnly();
                                        } catch (Throwable ignored) {
                                        }
                                    }
                                }
                            }
                        } catch (Throwable ignored) {
                        }
                    }
                }
            }

            // 2. Also inspect host PackageManager's splitSourceDirs and nativeLibraryDir
            try {
                ApplicationInfo hostAppInfo = FeaturesticBoxCore.getPackageManager()
                        .getApplicationInfo(ps.pkg.packageName, 0);
                if (hostAppInfo != null) {
                    if (hostAppInfo.splitSourceDirs != null) {
                        for (String splitPath : hostAppInfo.splitSourceDirs) {
                            if (splitPath == null) continue;
                            File splitFile = new File(splitPath);
                            if (splitFile.isFile()) {
                                try {
                                    NativeUtils.copyNativeLib(splitFile, appLibDir);
                                } catch (Throwable ignored) {
                                }
                            }
                        }
                    }
                    if (hostAppInfo.nativeLibraryDir != null) {
                        File hostLibDir = new File(hostAppInfo.nativeLibraryDir);
                        File[] hostSoFiles = hostLibDir.listFiles((d, name) -> name != null && name.endsWith(".so"));
                        if (hostSoFiles != null) {
                            for (File soFile : hostSoFiles) {
                                File dstSo = new File(appLibDir, soFile.getName());
                                if (!dstSo.exists() || dstSo.length() == 0) {
                                    try {
                                        FileUtils.copyFile(soFile, dstSo);
                                        dstSo.setReadable(true, false);
                                        dstSo.setExecutable(true, false);
                                    } catch (Throwable ignored) {
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (Throwable ignored) {
            }
        } catch (Exception e) {
            e.printStackTrace();
            if (!option.isFlag(InstallOption.FLAG_SYSTEM)) {
                return -1;
            }
        }
        if (option.isFlag(InstallOption.FLAG_STORAGE)) {
            File origFile = new File(ps.pkg.baseCodePath);
            File newFile = BEnvironment.getBaseApkDir(ps.pkg.packageName);
            try {
                if (!origFile.getAbsolutePath().equals(newFile.getAbsolutePath()) || !newFile.isFile()) {
                    if (newFile.exists()) {
                        try {
                            newFile.setWritable(true, true);
                        } catch (Throwable ignored) {
                        }
                    }
                    if (option.isFlag(InstallOption.FLAG_URI_FILE)) {
                        boolean b = FileUtils.renameTo(origFile, newFile);
                        if (!b) {
                            FileUtils.copyFile(origFile, newFile);
                        }
                    } else {
                        FileUtils.copyFile(origFile, newFile);
                    }
                    if (BuildCompat.isUpsideDownCake()) {
                        try {
                            newFile.setReadOnly();
                        } catch (Throwable ignored) {
                        }
                    }
                }
                ps.pkg.baseCodePath = newFile.getAbsolutePath();
            } catch (IOException e) {
                e.printStackTrace();
                return -1;
            }
        }
        return 0;
    }
}
