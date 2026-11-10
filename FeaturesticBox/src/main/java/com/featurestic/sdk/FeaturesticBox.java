package com.featurestic.sdk;

import android.content.Context;
import android.content.pm.PackageInfo;
import android.content.pm.PackageManager;
import android.os.Handler;
import android.os.Looper;
import android.util.Log;

import com.featurestic.box.FeaturesticBoxCore;
import com.featurestic.box.FeaturesticCloudPluginLoader;
import com.featurestic.box.app.configuration.ClientConfiguration;
import com.featurestic.box.core.env.BEnvironment;
import com.featurestic.box.entity.pm.InstallResult;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.IOException;
import java.util.ArrayList;
import java.util.List;

/**
 * Compatibility API used by the separate FeaturesticLoader application.
 * OBB sync is copy-and-verify, not a claim to bypass Android's storage sandbox.
 */
public final class FeaturesticBox {
    private static final String TAG = "FeaturesticBoxBridge";
    private static final FeaturesticBox INSTANCE = new FeaturesticBox();
    private static final Handler MAIN = new Handler(Looper.getMainLooper());

    private FeaturesticBox() {}

    public interface SetupCallback {
        void onProgress(int percent, String statusTitle, String statusDetail);
        void onComplete(boolean success, String message);
    }

    public static FeaturesticBox get() {
        return INSTANCE;
    }

    public static void doAttachBaseContext(final Context context) {
        if (context == null) throw new IllegalArgumentException("Context required");
        FeaturesticBoxCore.get().doAttachBaseContext(context, new ClientConfiguration() {
            @Override public String getHostPackageName() {
                return context.getPackageName();
            }
        });
    }

    public static void doCreate(Context context) {
        FeaturesticBoxCore.get().doCreate();
    }

    public static void setFeature(String name, boolean enabled) {
        if ("hide_root".equals(name)) {
            FeaturesticBoxCore.setHideRoot(enabled);
        } else if ("hide_xposed".equals(name)) {
            FeaturesticBoxCore.setHideXposed(enabled);
        } else {
            Log.i(TAG, "No core mapping for optional feature: " + name);
        }
    }

    public void setTargetGameFps(int fps) {
        FeaturesticBoxCore.get().setTargetGameFps(fps);
    }

    public static PackageInfo findHostGameInfo(Context context, String packageName) {
        if (context == null || !validPackage(packageName)) return null;
        try {
            return context.getPackageManager().getPackageInfo(packageName, 0);
        } catch (PackageManager.NameNotFoundException e) {
            return null;
        }
    }

    public static boolean isGameInstalledOnHost(Context context, String packageName) {
        return findHostGameInfo(context, packageName) != null;
    }

    private static boolean validPackage(String packageName) {
        return packageName != null
            && packageName.matches("[A-Za-z0-9_]+(\\.[A-Za-z0-9_]+)+");
    }

    private static List<File> obbFiles(File dir) {
        List<File> found = new ArrayList<>();
        if (dir == null || !dir.isDirectory()) return found;
        File[] files = dir.listFiles();
        if (files == null) return found;
        for (File file : files) {
            if (file.isFile() && file.getName().toLowerCase(java.util.Locale.ROOT).endsWith(".obb")
                    && file.length() > 0) {
                found.add(file);
            }
        }
        return found;
    }

    public static boolean hasHostObb(Context context, String packageName) {
        return validPackage(packageName)
            && !obbFiles(BEnvironment.getHostExternalObbDir(packageName)).isEmpty();
    }

    public static boolean hasVirtualObb(String packageName) {
        return validPackage(packageName)
            && !obbFiles(BEnvironment.getExternalObbDir(packageName)).isEmpty();
    }

    public static boolean isInstalled(String packageName, int userId) {
        return validPackage(packageName) && FeaturesticBoxCore.get().isInstalled(packageName, userId);
    }

    public static boolean installGame(String packageName, int userId) {
        if (!validPackage(packageName)) return false;
        if (isInstalled(packageName, userId)) return true;
        InstallResult result = FeaturesticBoxCore.get().installPackageAsUser(packageName, userId);
        return result != null && result.success && isInstalled(packageName, userId);
    }

    /**
     * Sets the existing cloud plugin URL. Local .so paths are not supported by this bridge.
     */
    public static void loadCustomLibrary(String location) {
        if (location == null || location.trim().isEmpty()) return;
        String url = location.trim();
        if (url.startsWith("https://")) {
            FeaturesticCloudPluginLoader.setCloudPluginUrl(url);
        } else {
            Log.w(TAG, "Ignoring unsupported custom library location");
        }
    }

    private static void progress(final SetupCallback cb, final int pct,
                                 final String title, final String detail) {
        if (cb != null) MAIN.post(() -> cb.onProgress(pct, title, detail));
    }

    private static void complete(final SetupCallback cb, final boolean ok, final String message) {
        if (cb != null) MAIN.post(() -> cb.onComplete(ok, message));
    }

    /**
     * A complete copied OBB is verified by byte length, then moved into place.
     * A failed/inaccessible source must not report setup success.
     */
    private static void copyVerified(File source, File dest) throws IOException {
        if (!source.isFile() || source.length() <= 0) {
            throw new IOException("OBB source missing or empty: " + source.getName());
        }
        if (source.getCanonicalFile().equals(dest.getCanonicalFile())) return;
        if (dest.isFile() && dest.length() == source.length()) return;
        File parent = dest.getParentFile();
        if (parent == null || (!parent.isDirectory() && !parent.mkdirs())) {
            throw new IOException("Cannot create virtual OBB directory");
        }
        File pending = new File(parent, dest.getName() + ".partial");
        if (pending.exists() && !pending.delete()) {
            throw new IOException("Cannot clear incomplete OBB: " + pending.getName());
        }
        try {
            try (FileInputStream input = new FileInputStream(source);
                 FileOutputStream output = new FileOutputStream(pending)) {
                byte[] block = new byte[1024 * 1024];
                int n;
                while ((n = input.read(block)) != -1) {
                    output.write(block, 0, n);
                }
                output.getFD().sync();
            }
            if (pending.length() != source.length()) {
                throw new IOException("OBB size verification failed: " + dest.getName());
            }
            if (dest.exists() && !dest.delete()) {
                throw new IOException("Could not replace OBB: " + dest.getName());
            }
            if (!pending.renameTo(dest) || dest.length() != source.length()) {
                throw new IOException("Could not finalize OBB: " + dest.getName());
            }
        } finally {
            if (pending.exists()) pending.delete();
        }
    }

    public static void setupGameAsync(final Context context, final String packageName,
                                      final int userId, final SetupCallback callback) {
        new Thread(() -> {
            try {
                if (context == null || !validPackage(packageName)) {
                    throw new IOException("Invalid context or game package");
                }
                progress(callback, 5, "Checking installed game", packageName);
                if (!isGameInstalledOnHost(context, packageName)) {
                    throw new IOException("The game is not installed on this device");
                }
                if (!installGame(packageName, userId)) {
                    throw new IOException("Virtual game installation failed");
                }
                progress(callback, 35, "Checking OBB files", "Locating readable source files");
                synchronized (FeaturesticBox.class) {
                    File host = BEnvironment.getHostExternalObbDir(packageName);
                    File target = BEnvironment.getExternalObbDir(packageName);
                    List<File> sources = obbFiles(host);
                    if (sources.isEmpty()) {
                        if (hasVirtualObb(packageName)) {
                            complete(callback, true, "Virtual OBB is already present");
                            return;
                        }
                        throw new IOException("No readable host OBB found in " + host
                            + ". Android 11+ may restrict other apps' Android/obb folders.");
                    }
                    for (int i = 0; i < sources.size(); i++) {
                        File src = sources.get(i);
                        progress(callback, 40 + (int)(45.0 * i / sources.size()),
                            "Copying OBB", src.getName());
                        copyVerified(src, new File(target, src.getName()));
                    }
                    for (File src : sources) {
                        File copied = new File(target, src.getName());
                        if (!copied.isFile() || copied.length() != src.length()) {
                            throw new IOException("OBB verification failed: " + src.getName());
                        }
                    }
                }
                if (!isInstalled(packageName, userId) || !hasVirtualObb(packageName)) {
                    throw new IOException("Game installed but OBB sync was not verified");
                }
                progress(callback, 100, "Ready", "Game and OBB verified");
                complete(callback, true, "Game and OBB setup verified");
            } catch (Throwable failure) {
                Log.e(TAG, "Setup failed for " + packageName, failure);
                complete(callback, false, failure.getMessage() != null
                    ? failure.getMessage() : failure.toString());
            }
        }, "Featurestic-OBB-Setup").start();
    }

    public static boolean startGame(Context context, String packageName, int userId,
                                    String pluginLocation) {
        if (!isInstalled(packageName, userId) || !hasVirtualObb(packageName)) {
            Log.e(TAG, "Refusing launch: virtual game/OBB not ready");
            return false;
        }
        loadCustomLibrary(pluginLocation);
        return FeaturesticBoxCore.get().launchApk(packageName, userId);
    }
}
