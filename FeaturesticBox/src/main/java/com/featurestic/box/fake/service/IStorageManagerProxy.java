package com.featurestic.box.fake.service;

import android.os.Build;
import android.os.Environment;
import android.os.IInterface;
import android.os.storage.StorageVolume;

import java.io.File;
import java.lang.reflect.InvocationTargetException;
import java.lang.reflect.Method;

import black.android.os.BRServiceManager;
import black.android.os.mount.BRIMountServiceStub;
import black.android.os.storage.BRIStorageManagerStub;
import black.android.os.storage.BRStorageVolume;

import com.featurestic.box.FeaturesticBoxCore;
import com.featurestic.box.core.RCore;
import com.featurestic.box.core.env.BEnvironment;
import com.featurestic.box.fake.hook.BinderInvocationStub;
import com.featurestic.box.fake.hook.MethodHook;
import com.featurestic.box.fake.hook.ProxyMethod;
import com.featurestic.box.utils.FileUtils;
import com.featurestic.box.utils.MethodParameterUtils;
import com.featurestic.box.utils.compat.BuildCompat;

public class IStorageManagerProxy extends BinderInvocationStub {

    public IStorageManagerProxy() {
        super(BRServiceManager.get().getService("mount"));
    }

    @Override
    public Object getWho() {
        IInterface mount;
        if (BuildCompat.isOreo()) {
            mount = BRIStorageManagerStub.get().asInterface(BRServiceManager.get().getService("mount"));
        } else {
            mount = BRIMountServiceStub.get().asInterface(BRServiceManager.get().getService("mount"));
        }
        return mount;
    }

    @Override
    public void inject(Object baseInvocation, Object proxyInvocation) {
        replaceSystemService("mount");
    }

    @Override
    public boolean isBadEnv() {
        return false;
    }

    @Override
    public Object invoke(Object proxy, Method method, Object[] args) {
        if (method != null && !"getVolumeList".equals(method.getName())) {
            MethodParameterUtils.replaceAllAppPkg(args);
            MethodParameterUtils.replaceFirstUid(args);
            MethodParameterUtils.replaceLastUid(args);
        }
        try {
            return super.invoke(proxy, method, args);
        } catch (SecurityException ignored) {
            return null;
        } catch (Throwable e) {
            if (e instanceof InvocationTargetException) {
                Throwable target = ((InvocationTargetException) e).getTargetException();
                if (target instanceof SecurityException) return null;
                if (target instanceof RuntimeException) throw (RuntimeException) target;
            }
            if (e instanceof RuntimeException) throw (RuntimeException) e;
            return null;
        }
    }

    @ProxyMethod("getVolumeList")
    public static class GetVolumeList extends MethodHook {
        @Override
        public Object hook(Object who, Method method, Object[] args) {
            Object[] callArgs = args != null ? args.clone() : null;
            if (callArgs != null) {
                if (callArgs.length >= 2 && callArgs[0] instanceof Integer && callArgs[1] instanceof String) {
                    callArgs[0] = Build.VERSION.SDK_INT >= 33
                            ? FeaturesticBoxCore.getHostUserId()
                            : FeaturesticBoxCore.getHostUid();
                    callArgs[1] = FeaturesticBoxCore.getHostPkg();
                } else {
                    MethodParameterUtils.replaceAllAppPkg(callArgs);
                    MethodParameterUtils.replaceFirstUid(callArgs);
                }
            }
            Object raw;
            try {
                raw = method.invoke(who, callArgs);
            } catch (Throwable e) {
                if (callArgs != null && callArgs.length >= 2 && callArgs[0] instanceof Integer) {
                    callArgs[0] = Build.VERSION.SDK_INT >= 33
                            ? FeaturesticBoxCore.getHostUid()
                            : FeaturesticBoxCore.getHostUserId();
                    try { raw = method.invoke(who, callArgs); } catch (Throwable ignored) { return null; }
                } else {
                    return null;
                }
            }
            // Keep StorageVolume's real path (/storage/emulated/0) intact!
            // Overwriting StorageVolume.mPath with .../SdCard breaks native C++ engines
            // (like Unreal Engine 4 / libUE4.so in BGMI) that build GFilePathBase + "/Android/obb/<pkg>"
            // from Environment.getExternalStorageDirectory() and open the .obb via C++ openat().
            return raw;
        }
    }

    @ProxyMethod("mkdirs")
    public static class mkdirs extends MethodHook {
        @Override
        public Object hook(Object who, Method method, Object[] args) {
            try {
                if (args != null) {
                    for (Object arg : args) {
                        if (arg instanceof String) {
                            String p = (String) arg;
                            if (p.startsWith("/")) {
                                String redirected = RCore.get().redirectPath(p);
                                FileUtils.mkdirs(redirected);
                            }
                        } else if (arg instanceof File) {
                            File redirected = RCore.get().redirectPath((File) arg);
                            FileUtils.mkdirs(redirected);
                        }
                    }
                }
                return 0;
            } catch (Throwable t) {
                return 0;
            }
        }
    }

    @ProxyMethod("getVolumePaths")
    public static class GetVolumePaths extends MethodHook {
        @Override
        public Object hook(Object who, Method method, Object[] args) {
            try {
                return new String[]{Environment.getExternalStorageDirectory().getAbsolutePath()};
            } catch (Throwable t) {
                return new String[]{"/storage/emulated/0"};
            }
        }
    }

    @Override
    public void onBindMethod() {
        super.onBindMethod();
        if (BuildCompat.isS()) {
            addMethodHook(new GetVolumePaths());
        }
    }
}
