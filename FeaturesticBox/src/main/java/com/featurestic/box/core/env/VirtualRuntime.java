package com.featurestic.box.core.env;

import android.content.pm.ApplicationInfo;

import black.android.ddm.BRDdmHandleAppName;
import black.android.os.BRProcess;

public class VirtualRuntime {

    private static String sInitialPackageName;
    private static String sProcessName;

    public static String getProcessName() {
        return sProcessName;
    }

    public static String getInitialPackageName() {
        return sInitialPackageName;
    }

    public static void setupRuntime(String processName, ApplicationInfo appInfo) {
        if (sProcessName != null) {
            return;
        }
        sInitialPackageName = appInfo != null ? appInfo.packageName : processName;
        sProcessName = processName != null ? processName : sInitialPackageName;
        if (sProcessName == null) return;
        try {
            BRProcess.get().setArgV0(sProcessName);
        } catch (Throwable ignored) {
        }
        try {
            BRDdmHandleAppName.get().setAppName(sProcessName, 0);
        } catch (Throwable ignored) {
        }
    }
}
