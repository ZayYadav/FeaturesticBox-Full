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

    public static boolean hasHostObb(Context context, String packageName) {
        return validPackage(packageName) && ObbSyncManager.hasReadableHostObb(packageName);
    }

    public static boolean hasVirtualObb(String packageName) {
        if (!validPackage(packageName)) return false;
        Context context = FeaturesticBoxCore.getContext();
        return context != null && ObbSyncManager.isReadyQuick(context, packageName);
    }

    public static boolean hasObbDocuments(Context context, String packageName) {
        return validPackage(packageName)
                && ObbSyncManager.hasRegisteredDocuments(context, packageName);
    }

    public static int registerObbDocuments(Context context, String packageName,
                                           java.util.List<android.net.Uri> documents)
            throws java.io.IOException {
        return ObbSyncManager.registerDocuments(context, packageName, documents);
    }

    public static String getVirtualObbPath(String packageName) {
        return validPackage(packageName) ? ObbSyncManager.getVirtualPath(packageName) : "";
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
     * Background pre-flight: installs the game, discovers expansion files,
     * restores missing or modified files to the virtual path, and verifies all
     * copied content before reporting success. Never performs OBB IO on UI.
     */
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
                progress(callback, 20, "Checking virtual installation", packageName);
                if (!installGame(packageName, userId)) {
                    throw new IOException("Virtual game installation failed");
                }
                String outcome = ObbSyncManager.sync(context, packageName,
                        (percent, title, detail) -> progress(callback, percent, title, detail));
                if (!isInstalled(packageName, userId) || !hasVirtualObb(packageName)) {
                    throw new IOException("Virtual game or verified OBB is missing");
                }
                progress(callback, 100, "Game resources ready", outcome);
                complete(callback, true, outcome);
            } catch (Throwable failure) {
                Log.e(TAG, "Prelaunch OBB sync failed for " + packageName, failure);
                complete(callback, false, failure.getMessage() != null
                        ? failure.getMessage() : failure.toString());
            }
        }, "Featurestic-OBB-Preflight").start();
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
