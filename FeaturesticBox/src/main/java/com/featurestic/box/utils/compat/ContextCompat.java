package com.featurestic.box.utils.compat;

import android.content.Context;
import android.content.ContextWrapper;

import java.io.File;
import java.lang.reflect.Field;

import black.android.app.BRContextImpl;
import black.android.app.BRContextImplKitkat;
import black.android.content.AttributionSourceStateContext;
import black.android.content.BRAttributionSource;
import black.android.content.BRAttributionSourceState;
import black.android.content.BRContentResolver;
import com.featurestic.box.FeaturesticBoxCore;
import com.featurestic.box.app.BActivityThread;
import com.featurestic.box.core.env.BEnvironment;
import com.featurestic.box.utils.FileUtils;

public class ContextCompat {
    public static final String TAG = "ContextCompat";

    public static void fixAttributionSourceState(Object obj, int uid) {
        fixAttributionSourceState(obj, uid, 0);
    }

    public static void fixAttributionSourceState(Object obj, int uid, int depth) {
        if (depth >= 10) return;
        try {
            if (obj != null && BRAttributionSource.get(obj)._check_mAttributionSourceState() != null) {
                Object mAttributionSourceState = BRAttributionSource.get(obj).mAttributionSourceState();
                AttributionSourceStateContext attributionSourceStateContext =
                        BRAttributionSourceState.get(mAttributionSourceState);
                attributionSourceStateContext._set_packageName(FeaturesticBoxCore.getHostPkg());
                attributionSourceStateContext._set_uid(uid);
                fixAttributionSourceState(BRAttributionSource.get(obj).getNext(), uid, depth + 1);
            }
        } catch (Throwable ignored) {
        }
    }

    /**
     * Repairs the identity Android system services observe for a virtual Context,
     * and pre-populates ContextImpl external files/cache/OBB directories so native
     * C++ engines (like Unreal Engine 4 / libUE4.so in BGMI) get:
     * 1. Real host OBB directory (/storage/emulated/0/Android/obb/<pkg>) for 0ms read-only OBB mounting
     * 2. Writable virtual external files/cache directories inside the Loader's own sandbox
     */
    public static void fix(Context context) {
        Context baseContext = unwrap(context);
        if (baseContext == null) return;

        final String hostPackage = FeaturesticBoxCore.getHostPkg();
        final int hostUid = FeaturesticBoxCore.getHostUid();

        try {
            BRContextImpl.get(baseContext)._set_mPackageManager(null);
            baseContext.getPackageManager();
        } catch (Throwable ignored) {
        }

        try {
            BRContextImpl.get(baseContext)._set_mBasePackageName(hostPackage);
        } catch (Throwable ignored) {
        }

        try {
            BRContextImplKitkat.get(baseContext)._set_mOpPackageName(hostPackage);
        } catch (Throwable ignored) {
        }

        try {
            if (baseContext.getContentResolver() != null) {
                BRContentResolver.get(baseContext.getContentResolver())._set_mPackageName(hostPackage);
            }
        } catch (Throwable ignored) {
        }

        try {
            String virtualPkg = BActivityThread.getAppPackageName();
            if (virtualPkg != null && !virtualPkg.isEmpty() && !virtualPkg.equals(hostPackage)) {
                File extFilesDir = BEnvironment.getExternalDataFilesDir(virtualPkg);
                File extCacheDir = BEnvironment.getExternalDataCacheDir(virtualPkg);
                FileUtils.mkdirs(extFilesDir);
                FileUtils.mkdirs(extCacheDir);
                setContextImplDirArray(baseContext, "mExternalFilesDirs", new File[]{extFilesDir});
                setContextImplDirArray(baseContext, "mExternalCacheDirs", new File[]{extCacheDir});

                File virtualObbDir = BEnvironment.getExternalObbDir(virtualPkg);
                File hostObbDir = BEnvironment.getHostExternalObbDir(virtualPkg);
                File[] vObbs = virtualObbDir.listFiles((d, name) ->
                        name != null && name.endsWith(".obb") && new File(d, name).length() > 10 * 1024 * 1024L);
                File preferredObbDir = (vObbs != null && vObbs.length > 0) ? virtualObbDir : hostObbDir;
                setContextImplDirArray(baseContext, "mObbDirs", new File[]{preferredObbDir});
            }
        } catch (Throwable ignored) {
        }

        try {
            VirtualPackageMetadataCompat.install(baseContext);
        } catch (Throwable ignored) {
        }

        if (BuildCompat.isS()) {
            try {
                fixAttributionSourceState(
                        BRContextImpl.get(baseContext).getAttributionSource(), hostUid);
            } catch (Throwable ignored) {
            }
        }
    }

    private static void setContextImplDirArray(Context baseContext, String fieldName, File[] dirs) {
        if (baseContext == null || fieldName == null || dirs == null) return;
        Class<?> cls = baseContext.getClass();
        while (cls != null && cls != Object.class) {
            try {
                Field f = cls.getDeclaredField(fieldName);
                f.setAccessible(true);
                f.set(baseContext, dirs);
                return;
            } catch (NoSuchFieldException ignored) {
                cls = cls.getSuperclass();
            } catch (Throwable ignored) {
                return;
            }
        }
    }

    private static Context unwrap(Context context) {
        if (context == null) return null;
        Context current = context;
        int depth = 0;
        try {
            while (current instanceof ContextWrapper && depth < 10) {
                Context next = ((ContextWrapper) current).getBaseContext();
                if (next == null || next == current) break;
                current = next;
                depth++;
            }
        } catch (Throwable ignored) {
        }
        return current;
    }
}
