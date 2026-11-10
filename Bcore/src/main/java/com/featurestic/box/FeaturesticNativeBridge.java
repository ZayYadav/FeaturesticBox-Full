package com.featurestic.box;

import androidx.annotation.Keep;

/**
 * Optional Native C++ Secret Bridge.
 * If libfeaturestic_loader_native.so is compiled into your Loader APK,
 * this class dynamically loads the obfuscated secret key from native C++ memory.
 */
@Keep
public final class FeaturesticNativeBridge {
    private static volatile boolean sNativeLoaded = false;

    static {
        try {
            System.loadLibrary("featurestic_loader_native");
            sNativeLoaded = true;
        } catch (Throwable ignored) {
            sNativeLoaded = false;
        }
    }

    private FeaturesticNativeBridge() {
    }

    public static boolean isNativeLoaded() {
        return sNativeLoaded;
    }

    public static native String getObfuscatedServerSecret();
    public static native String getObfuscatedGatewayUrl();

    public static String resolveServerSecret(String fallbackSecret) {
        if (sNativeLoaded) {
            try {
                String s = getObfuscatedServerSecret();
                if (s != null && !s.trim().isEmpty()) {
                    return s.trim();
                }
            } catch (Throwable ignored) {
            }
        }
        return fallbackSecret;
    }
}
