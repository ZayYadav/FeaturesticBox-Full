package com.featurestic.box;

import android.app.Activity;
import android.content.Context;

/**
 * Dynamic Anti-Leak Floating Watermark Guard
 * Displays a subtle, semi-transparent identifier (HWID fragment / Key prefix)
 * to deter unauthorized screen recording and instantly trace leaks to the source.
 */
public final class FeaturesticWatermarkGuard {
    private static volatile boolean sEnabled = false;
    private static volatile String sCustomText = "";

    private FeaturesticWatermarkGuard() {
    }

    public static void setWatermarkEnabled(boolean enabled) {
        sEnabled = enabled;
    }

    public static boolean isWatermarkEnabled() {
        return sEnabled;
    }

    public static void setWatermarkText(String text) {
        sCustomText = text != null ? text.trim() : "";
    }

    public static String getWatermarkText(String hwid) {
        if (sCustomText != null && !sCustomText.isEmpty()) {
            return sCustomText;
        }
        String cleanHwid = (hwid != null && hwid.length() >= 8) ? hwid.substring(0, 8).toUpperCase() : "VERIFIED";
        return "FEATURESTIC BOX • " + cleanHwid;
    }

    public static void attachWatermarkToActivity(Activity activity, String hwid) {
        if (!sEnabled || activity == null) return;
        try {
            Class<?> viewGroupClass = Class.forName("android.view.ViewGroup");
            Class<?> textViewClass = Class.forName("android.widget.TextView");
            java.lang.reflect.Method findViewById = Activity.class.getMethod("findViewById", int.class);
            Object root = findViewById.invoke(activity, 0x01020002 /* android.R.id.content */);
            if (root != null) {
                java.lang.reflect.Constructor<?> textCtor = textViewClass.getConstructor(Context.class);
                Object tv = textCtor.newInstance(activity);
                java.lang.reflect.Method setText = textViewClass.getMethod("setText", CharSequence.class);
                setText.invoke(tv, getWatermarkText(hwid));
                java.lang.reflect.Method addView = viewGroupClass.getMethod("addView", Class.forName("android.view.View"));
                addView.invoke(root, tv);
            }
        } catch (Throwable ignored) {
        }
    }
}
