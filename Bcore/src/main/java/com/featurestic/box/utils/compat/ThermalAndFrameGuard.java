package com.featurestic.box.utils.compat;

import android.app.Activity;
import android.os.Build;
import android.os.Process;
import android.view.Surface;
import android.view.SurfaceView;
import android.view.View;
import android.view.ViewGroup;
import android.view.Window;
import android.view.WindowManager;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.lang.reflect.Field;
import java.lang.reflect.Method;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.List;

import com.featurestic.box.core.RNative;
import com.featurestic.box.core.env.BEnvironment;
import com.featurestic.box.utils.FileUtils;

public final class ThermalAndFrameGuard {
    private static volatile boolean sThermalGuardEnabled = true;
    private static volatile int sTargetFps = 120;
    private static volatile boolean sConfigLoaded = false;

    private ThermalAndFrameGuard() {
    }

    private static File getConfigFile() {
        return new File(BEnvironment.getSystemDir(), "featurestic_fps_target.conf");
    }

    public static void setThermalGuardEnabled(boolean enabled) {
        sThermalGuardEnabled = enabled;
    }

    public static boolean isThermalGuardEnabled() {
        return sThermalGuardEnabled;
    }

    public static void setTargetFps(int fps) {
        int clamped = (fps >= 110) ? 120 : (fps >= 80 ? 90 : 60);
        sTargetFps = clamped;
        sConfigLoaded = true;
        try {
            File f = getConfigFile();
            FileUtils.mkdirs(f.getParentFile());
            try (FileOutputStream fos = new FileOutputStream(f)) {
                fos.write(String.valueOf(clamped).getBytes(StandardCharsets.UTF_8));
            }
        } catch (Throwable ignored) {
        }
        try {
            if (clamped >= 120) {
                DeviceProfileCompat.setPreset(DeviceProfileCompat.PRESET_ROG_PHONE_8);
            } else if (clamped >= 90) {
                DeviceProfileCompat.setPreset(DeviceProfileCompat.PRESET_ONEPLUS_12);
            }
        } catch (Throwable ignored) {
        }
    }

    public static int getTargetFps() {
        if (sConfigLoaded) {
            return sTargetFps;
        }
        sConfigLoaded = true;
        try {
            File f = getConfigFile();
            if (f.isFile() && f.length() > 0 && f.length() <= 16) {
                byte[] buf = new byte[(int) f.length()];
                try (FileInputStream fis = new FileInputStream(f)) {
                    int n = fis.read(buf);
                    if (n > 0) {
                        int parsed = Integer.parseInt(new String(buf, 0, n, StandardCharsets.UTF_8).trim());
                        if (parsed == 60 || parsed == 90 || parsed == 120) {
                            sTargetFps = parsed;
                        }
                    }
                }
            }
        } catch (Throwable ignored) {
        }
        return sTargetFps;
    }

    public static void initForProcess() {
        if (!sThermalGuardEnabled) {
            return;
        }
        try {
            Process.setThreadPriority(Process.THREAD_PRIORITY_URGENT_DISPLAY);
        } catch (Throwable ignored) {
        }
        try {
            int fps = getTargetFps();
            RNative.setNativeProp("debug.sf.frame_rate_multiple_threshold", String.valueOf(fps));
            RNative.setNativeProp("ro.surface_flinger.use_content_detection_for_refresh_rate", "false");
            RNative.setNativeProp("debug.graphics.game_default_frame_rate.disabled", "true");
        } catch (Throwable ignored) {
        }
    }

    private static final java.util.Set<SurfaceView> sHookedSurfaces =
            java.util.Collections.synchronizedSet(java.util.Collections.newSetFromMap(new java.util.WeakHashMap<SurfaceView, Boolean>()));

    public static void applyToActivityWindow(final Activity activity) {
        if (activity == null || activity.isFinishing()) {
            return;
        }
        String actName = activity.getClass().getName();
        if (actName.contains("LauncherActivity")) {
            return;
        }
        try {
            Process.setThreadPriority(Process.THREAD_PRIORITY_URGENT_DISPLAY);
        } catch (Throwable ignored) {
        }
        try {
            final Window window = activity.getWindow();
            if (window == null) {
                return;
            }
            final int targetFps = getTargetFps();
            WindowManager.LayoutParams lp = window.getAttributes();
            if (lp != null) {
                lp.preferredRefreshRate = (float) targetFps;
                lp.preferredDisplayModeId = 0;
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
                    lp.layoutInDisplayCutoutMode = WindowManager.LayoutParams.LAYOUT_IN_DISPLAY_CUTOUT_MODE_SHORT_EDGES;
                }
                setMinMaxRefreshRateFields(lp, (float) targetFps);
                window.setAttributes(lp);
            }

            final View decor = window.getDecorView();
            if (decor != null) {
                decor.post(new Runnable() {
                    @Override
                    public void run() {
                        if (activity.isFinishing()) return;
                        applySurfaceFrameRateRecursive(decor, (float) targetFps);
                    }
                });
            }
        } catch (Throwable ignored) {
        }
    }

    private static void setMinMaxRefreshRateFields(WindowManager.LayoutParams lp, float targetHz) {
        String[] fieldNames = new String[]{
                "preferredMinDisplayRefreshRate",
                "preferredMaxDisplayRefreshRate",
                "minRefreshRate",
                "maxRefreshRate"
        };
        for (String name : fieldNames) {
            try {
                Field f = WindowManager.LayoutParams.class.getDeclaredField(name);
                f.setAccessible(true);
                f.setFloat(lp, targetHz);
            } catch (Throwable ignored) {
            }
        }
    }

    private static void applySurfaceFrameRateRecursive(View root, float targetFps) {
        if (root == null) return;
        try {
            List<SurfaceView> surfaces = new ArrayList<>();
            if (root instanceof ViewGroup) {
                collectSurfaceViews((ViewGroup) root, surfaces);
            }
            for (final SurfaceView sv : surfaces) {
                if (sv == null || sv.getHolder() == null) continue;
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                    if (sHookedSurfaces.add(sv)) {
                        sv.getHolder().addCallback(new android.view.SurfaceHolder.Callback() {
                            @Override
                            public void surfaceCreated(android.view.SurfaceHolder holder) {
                                applySurfaceFrameRateRecursive(sv, (float) getTargetFps());
                            }
                            @Override
                            public void surfaceChanged(android.view.SurfaceHolder holder, int format, int w, int h) {
                                applySurfaceFrameRateRecursive(sv, (float) getTargetFps());
                            }
                            @Override
                            public void surfaceDestroyed(android.view.SurfaceHolder holder) {
                            }
                        });
                    }
                    Surface surface = sv.getHolder().getSurface();
                    if (surface != null && surface.isValid()) {
                        try {
                            Method m3 = Surface.class.getMethod("setFrameRate", float.class, int.class, int.class);
                            m3.invoke(surface, targetFps, 1, 1);
                        } catch (Throwable fallback) {
                            try {
                                Method m2 = Surface.class.getMethod("setFrameRate", float.class, int.class);
                                m2.invoke(surface, targetFps, 1);
                            } catch (Throwable ignored) {
                            }
                        }
                    }
                }
            }
        } catch (Throwable ignored) {
        }
    }

    private static void collectSurfaceViews(ViewGroup group, List<SurfaceView> out) {
        if (group == null) return;
        for (int i = 0; i < group.getChildCount(); i++) {
            View child = group.getChildAt(i);
            if (child instanceof SurfaceView) {
                out.add((SurfaceView) child);
            } else if (child instanceof ViewGroup) {
                collectSurfaceViews((ViewGroup) child, out);
            }
        }
    }

    private static volatile long sLastThermalStabilizeMs = 0L;

    public static void onThermalTelemetryTick(Activity activity, float batteryTempCelsius, int currentFps) {
        if (!sThermalGuardEnabled || activity == null) return;
        long now = android.os.SystemClock.uptimeMillis();
        if (batteryTempCelsius >= 43.0f || (getTargetFps() >= 90 && currentFps > 0 && currentFps < 55)) {
            if (now - sLastThermalStabilizeMs < 15000L) {
                return;
            }
            sLastThermalStabilizeMs = now;
            try {
                Process.setThreadPriority(Process.THREAD_PRIORITY_URGENT_DISPLAY);
            } catch (Throwable ignored) {
            }
            boostRenderAndGameThreads();
        }
    }

    public static void boostRenderAndGameThreads() {
        try {
            ThreadGroup root = Thread.currentThread().getThreadGroup();
            while (root != null && root.getParent() != null) {
                root = root.getParent();
            }
            if (root == null) return;
            Thread[] threads = new Thread[root.activeCount() + 16];
            int count = root.enumerate(threads, true);
            for (int i = 0; i < count; i++) {
                Thread t = threads[i];
                if (t == null) continue;
                String name = t.getName();
                if (name == null) continue;
                if (name.contains("RenderThread")
                        || name.contains("GameThread")
                        || name.contains("RHIThread")
                        || name.contains("UnityMain")
                        || name.contains("UnityGfx")) {
                    try {
                        t.setPriority(Thread.MAX_PRIORITY);
                    } catch (Throwable ignored) {
                    }
                }
            }
        } catch (Throwable ignored) {
        }
    }

    /**
     * Drops background virtual caches and forces low-memory cleanup for budget 4GB/6GB devices.
     */
    public static void applyLowEndMemoryTrimAndDiet(android.content.Context ctx) {
        try {
            System.gc();
            if (ctx != null) {
                // ComponentCallbacks2.TRIM_MEMORY_RUNNING_CRITICAL = 15
                ctx.onTrimMemory(15);
            }
        } catch (Throwable ignored) {
        }
    }

}
