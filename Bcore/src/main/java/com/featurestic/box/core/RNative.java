package com.featurestic.box.core;

import android.content.Context;
import android.os.Process;
import android.util.Base64;
import androidx.annotation.Keep;
import java.io.BufferedReader;
import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.FileReader;
import java.nio.charset.StandardCharsets;
import java.security.KeyFactory;
import java.security.Signature;
import java.security.spec.X509EncodedKeySpec;
import java.util.Collections;
import java.util.HashSet;
import java.util.Locale;
import java.util.Set;
import java.util.concurrent.atomic.AtomicBoolean;
import com.featurestic.box.FeaturesticBoxCore;
import com.featurestic.box.BuildConfig;
import com.featurestic.box.app.BActivityThread;
import com.featurestic.box.core.env.BEnvironment;
import com.featurestic.box.utils.FileUtils;

public class RNative {

    public static final String TAG = "RNative";
    private static final String NATIVE_ARTIFACT_DIRECTORY = "native";
    private static final String[] PLUGIN_CANDIDATE_NAMES = new String[]{
            "FeaturesticPlugin.so",
            "libFeaturesticPlugin.so"
    };
    private static volatile boolean isInjected = false;
    private static volatile boolean sCppSharedLoaded = false;
    private static volatile int sFirstEngineSeenTick = -1;
    private static final Set<String> sLoadedLibraries = Collections.synchronizedSet(new HashSet<String>());
    private static final AtomicBoolean sEngineWatcherStarted = new AtomicBoolean(false);

    static {
        System.loadLibrary("FeaturesticCore");
    }

    private static boolean isCompatibleElf(File soFile) {
        if (soFile == null || !soFile.isFile() || soFile.length() < 64) return false;
        try (FileInputStream fis = new FileInputStream(soFile)) {
            byte[] header = new byte[5];
            if (fis.read(header) < 5) return false;
            if (header[0] != 0x7F || header[1] != 'E' || header[2] != 'L' || header[3] != 'F') {
                return false;
            }
            int elfClass = header[4] & 0xFF;
            int expectedClass = FeaturesticBoxCore.is64Bit() ? 2 : 1;
            return elfClass == expectedClass;
        } catch (Throwable ignored) {
            return false;
        }
    }

    private static boolean isReservedNonPluginSoName(String fileName) {
        if (fileName == null) return true;
        String low = fileName.toLowerCase(Locale.US);
        return low.contains("featuresticcore")
                || low.contains("c++_shared")
                || low.contains("libue4.so")
                || low.contains("libanogs.so")
                || low.contains("libtersafe.so")
                || low.contains("libgcloud.so")
                || low.contains("libtdatamaster.so")
                || low.contains("libil2cpp.so")
                || low.contains("libunity.so")
                || low.contains("libmain.so")
                || low.contains("libzenin.so");
    }

    private static void prepareSoPermissions(File soFile) {
        if (soFile == null || !soFile.isFile()) return;
        try {
            soFile.setReadable(true, false);
            soFile.setExecutable(true, false);
            soFile.setWritable(false, false);
        } catch (Throwable ignored) {
        }
    }

    private static void preloadCppSharedIfPresent() {
        if (sCppSharedLoaded) return;
        try {
            String guestPkg = BActivityThread.isThreadInit() ? BActivityThread.getAppPackageName() : null;
            if (guestPkg != null && !guestPkg.isEmpty()) {
                File appLibDir = BEnvironment.getAppLibDir(guestPkg);
                if (appLibDir != null) {
                    File guestCpp = new File(appLibDir, "libc++_shared.so");
                    if (isCompatibleElf(guestCpp)) {
                        prepareSoPermissions(guestCpp);
                        System.load(guestCpp.getAbsolutePath());
                        sCppSharedLoaded = true;
                        return;
                    }
                }
            }
            Context ctx = FeaturesticBoxCore.getContext();
            if (ctx != null && ctx.getApplicationInfo() != null && ctx.getApplicationInfo().nativeLibraryDir != null) {
                File hostCpp = new File(ctx.getApplicationInfo().nativeLibraryDir, "libc++_shared.so");
                if (isCompatibleElf(hostCpp)) {
                    System.load(hostCpp.getAbsolutePath());
                    sCppSharedLoaded = true;
                }
            }
        } catch (Throwable ignored) {
        }
    }

    public static void initWithCrashShield(int apiLevel) {
        init(apiLevel);
        try {
            File crashLog = new File(BEnvironment.getSystemDir(), "featurestic_offset_crash.log");
            FileUtils.mkdirs(crashLog.getParentFile());
            setNativeProp("debug.featurestic.crash_log_path", crashLog.getAbsolutePath());
        } catch (Throwable ignored) {
        }
    }

    public static void schedulePluginLoadAfterEngineReady() {
        if (!sEngineWatcherStarted.compareAndSet(false, true)) {
            return;
        }
        Thread watcher = new Thread(new Runnable() {
            @Override
            public void run() {
                for (int i = 0; i < 600; i++) {
                    if (isAnogsAndEngineReadyInMaps(i)) {
                        try {
                            setNativeProp("debug.featurestic.refresh_shield", "1");
                        } catch (Throwable ignored) {
                        }
                        if (loadExternalPluginInternal()) {
                            try {
                                setNativeProp("debug.featurestic.refresh_shield", "1");
                            } catch (Throwable ignored) {
                            }
                            return;
                        }
                    }
                    try {
                        Thread.sleep(50L);
                    } catch (InterruptedException e) {
                        return;
                    }
                }
            }
        }, "Featurestic-EngineSoWatcher");
        watcher.setDaemon(true);
        watcher.start();
    }

    private static boolean isAnogsAndEngineReadyInMaps(int pollTick) {
        boolean hasAnogs = false;
        boolean hasUe4 = false;
        boolean hasUnity = false;
        try (BufferedReader br = new BufferedReader(new FileReader("/proc/self/maps"))) {
            String line;
            while ((line = br.readLine()) != null) {
                if (line.contains("libanogs.so") || line.contains("libtersafe.so")) {
                    hasAnogs = true;
                }
                if (line.contains("libUE4.so")) {
                    hasUe4 = true;
                }
                if (line.contains("libil2cpp.so") || line.contains("libunity.so")) {
                    hasUnity = true;
                }
            }
        } catch (Throwable ignored) {
        }
        if (hasAnogs && hasUe4) {
            return true;
        }
        if (hasUnity) {
            return true;
        }
        if (hasAnogs || hasUe4) {
            if (sFirstEngineSeenTick < 0) {
                sFirstEngineSeenTick = pollTick;
            } else if (pollTick - sFirstEngineSeenTick >= 20) {
                return true;
            }
        }
        return false;
    }

    public static synchronized boolean loadExternalPlugin() {
        if (isInjected) return true;
        if (BActivityThread.isThreadInit() && !isAnogsAndEngineReadyInMaps(0)) {
            schedulePluginLoadAfterEngineReady();
            return false;
        }
        return loadExternalPluginInternal();
    }

    private static synchronized boolean loadExternalPluginInternal() {
        if (isInjected) return true;
        try {
            Context ctx = FeaturesticBoxCore.getContext();
            if (ctx == null) return false;

            String abiSubDir = FeaturesticBoxCore.is64Bit() ? "arm64-v8a" : "armeabi-v7a";
            String guestPkg = BActivityThread.isThreadInit() ? BActivityThread.getAppPackageName() : null;
            File appLibDir = (guestPkg != null && !guestPkg.isEmpty()) ? BEnvironment.getAppLibDir(guestPkg) : null;

            File[] searchDirs = new File[]{
                    new File(new File(ctx.getNoBackupFilesDir(), NATIVE_ARTIFACT_DIRECTORY), abiSubDir),
                    new File(new File(ctx.getFilesDir(), NATIVE_ARTIFACT_DIRECTORY), abiSubDir),
                    new File(ctx.getNoBackupFilesDir(), NATIVE_ARTIFACT_DIRECTORY),
                    new File(ctx.getFilesDir(), NATIVE_ARTIFACT_DIRECTORY),
                    new File(ctx.getFilesDir(), "loader"),
                    new File(ctx.getFilesDir(), "online_so"),
                    ctx.getDir("featurestic_so", Context.MODE_PRIVATE),
                    BEnvironment.getSystemDir(),
                    appLibDir
            };

            preloadCppSharedIfPresent();

            boolean loadedAny = false;
            for (File dir : searchDirs) {
                if (dir == null || !dir.isDirectory()) continue;
                for (String candidate : PLUGIN_CANDIDATE_NAMES) {
                    File soFile = new File(dir, candidate);
                    if (isCompatibleElf(soFile)) {
                        if (loadCustomLibrary(soFile.getAbsolutePath())) {
                            loadedAny = true;
                        }
                    }
                }
            }
            if (!loadedAny) {
                for (int i = 0; i < 7; i++) {
                    File dir = searchDirs[i];
                    if (dir == null || !dir.isDirectory()) continue;
                    File[] children = dir.listFiles();
                    if (children == null) continue;
                    for (File child : children) {
                        if (child != null && child.isFile() && child.getName().endsWith(".so")
                                && !isReservedNonPluginSoName(child.getName())
                                && isCompatibleElf(child)) {
                            if (loadCustomLibrary(child.getAbsolutePath())) {
                                loadedAny = true;
                            }
                        }
                    }
                }
            }
            if (loadedAny) {
                isInjected = true;
                try {
                    setNativeProp("debug.featurestic.refresh_shield", "1");
                } catch (Throwable ignored) {
                }
                return true;
            }
        } catch (Throwable ignored) {
        }
        return false;
    }

    public static synchronized boolean loadCustomLibrary(String pathOrName) {
        if (pathOrName == null || pathOrName.trim().isEmpty()) return false;
        String trimmed = pathOrName.trim();
        if (sLoadedLibraries.contains(trimmed)) return true;
        try {
            preloadCppSharedIfPresent();
            File directFile = new File(trimmed);
            if (directFile.isAbsolute() || trimmed.contains("/") || trimmed.endsWith(".so")) {
                if (!directFile.isFile()) {
                    Context ctx = FeaturesticBoxCore.getContext();
                    if (ctx != null) {
                        File inNoBackup = new File(new File(ctx.getNoBackupFilesDir(), NATIVE_ARTIFACT_DIRECTORY), trimmed);
                        File inFiles = new File(ctx.getFilesDir(), trimmed);
                        if (inNoBackup.isFile()) directFile = inNoBackup;
                        else if (inFiles.isFile()) directFile = inFiles;
                    }
                }
                if (directFile.isFile() && directFile.length() > 64) {
                    String abs = directFile.getAbsolutePath();
                    if (sLoadedLibraries.contains(abs)) return true;
                    File targetToLoad = stageIntoAppLibIfNeeded(directFile);
                    prepareSoPermissions(targetToLoad);
                    try {
                        setNativeProp("debug.featurestic.refresh_shield", "1");
                    } catch (Throwable ignored) {
                    }
                    System.load(targetToLoad.getAbsolutePath());
                    sLoadedLibraries.add(abs);
                    sLoadedLibraries.add(targetToLoad.getAbsolutePath());
                    sLoadedLibraries.add(trimmed);
                    try {
                        setNativeProp("debug.featurestic.refresh_shield", "1");
                    } catch (Throwable ignored) {
                    }
                    return true;
                }
            }
            String libShortName = trimmed;
            if (libShortName.startsWith("lib")) {
                libShortName = libShortName.substring(3);
            }
            if (libShortName.endsWith(".so")) {
                libShortName = libShortName.substring(0, libShortName.length() - 3);
            }
            System.loadLibrary(libShortName);
            sLoadedLibraries.add(trimmed);
            sLoadedLibraries.add(libShortName);
            try {
                setNativeProp("debug.featurestic.refresh_shield", "1");
            } catch (Throwable ignored) {
            }
            return true;
        } catch (Throwable ignored) {
            return false;
        }
    }

    private static File stageIntoAppLibIfNeeded(File srcFile) {
        try {
            String guestPkg = BActivityThread.isThreadInit() ? BActivityThread.getAppPackageName() : null;
            if (guestPkg == null || guestPkg.isEmpty()) return srcFile;
            File appLibDir = BEnvironment.getAppLibDir(guestPkg);
            if (appLibDir == null) return srcFile;
            if (!appLibDir.exists()) appLibDir.mkdirs();
            if (srcFile.getParentFile() != null && srcFile.getParentFile().equals(appLibDir)) {
                return srcFile;
            }
            String cleanName = srcFile.getName().replaceAll("[^a-zA-Z0-9._-]", "_");
            if (!cleanName.startsWith("lib")) cleanName = "lib" + cleanName;
            if (!cleanName.endsWith(".so")) cleanName = cleanName + ".so";
            File dst = new File(appLibDir, cleanName);
            if (dst.exists()) {
                dst.setWritable(true, true);
            }
            try (FileInputStream fis = new FileInputStream(srcFile);
                 FileOutputStream fos = new FileOutputStream(dst, false)) {
                byte[] buf = new byte[32768];
                int n;
                while ((n = fis.read(buf)) != -1) {
                    fos.write(buf, 0, n);
                }
                fos.flush();
            }
            prepareSoPermissions(dst);
            return dst;
        } catch (Throwable ignored) {
            return srcFile;
        }
    }

    public static native void init(int apiLevel);
    public static native void enableIO();
    public static native void addIORule(String targetPath, String relocatePath);
    public static native void hideXposed();
    public static native void setNativeProp(String key, String value);
    public static native boolean isRuntimeSecure();
    public static native boolean lockHostSignature(Context context, String expectedSha256);
    public static native boolean authorizeSdkSession(
            Context context,
            String currentPackage,
            String currentSigningSha256,
            String authorizedPackage,
            String authorizedSigningSha256,
            String responseCanonical,
            String responseSignature,
            String identityCanonical,
            String identitySignature,
            long leaseExpiresAt,
            long serverTime);
    public static native boolean verifyInstalledIdentity(
            Context context,
            String expectedPackage,
            String expectedSigningSha256);
    public static native boolean isSdkSessionValid(long currentTime);
    public static native void clearSdkSession();
    public static native String getSdkPanelEndpoint();

    @Keep
    private static boolean verifyServerSignature(String canonical, String signatureBase64) {
        try {
            byte[] publicDer = Base64.decode(BuildConfig.SDK_PANEL_SIGNING_PUBLIC_KEY, Base64.DEFAULT);
            byte[] signatureBytes = Base64.decode(signatureBase64, Base64.DEFAULT);
            Signature verifier = Signature.getInstance("SHA256withECDSA");
            verifier.initVerify(KeyFactory.getInstance("EC").generatePublic(new X509EncodedKeySpec(publicDer)));
            verifier.update(canonical.getBytes(StandardCharsets.UTF_8));
            return verifier.verify(signatureBytes);
        } catch (Exception ignored) {
            return false;
        }
    }

    @Keep
    public static int getCallingUid(int origCallingUid) {
        if (origCallingUid > 0 && origCallingUid < Process.FIRST_APPLICATION_UID) return origCallingUid;
        if (origCallingUid > Process.LAST_APPLICATION_UID) return origCallingUid;
        if (origCallingUid == FeaturesticBoxCore.getHostUid()) {
            if ("com.google.android.gms".equals(BActivityThread.getAppPackageName())) {
                return Process.ROOT_UID;
            }
            if ("com.google.android.webview".equals(BActivityThread.getAppPackageName())) {
                return Process.myUid();
            }
            return BActivityThread.getCallingBUid();
        }
        return origCallingUid;
    }

    @Keep
    public static String redirectPath(String path) {
        return RCore.get().redirectPath(path);
    }

    @Keep
    public static File redirectPath(File path) {
        return RCore.get().redirectPath(path);
    }

    @Keep
    public static void onGameLibraryLoaded(String libPath) {
        if (libPath == null || libPath.isEmpty()) return;
        sLoadedLibraries.add(libPath);
        try {
            setNativeProp("debug.featurestic.refresh_shield", "1");
            if (isAnogsAndEngineReadyInMaps(0)) {
                loadExternalPluginInternal();
                setNativeProp("debug.featurestic.refresh_shield", "1");
            }
        } catch (Throwable ignored) {
        }
    }
}
