# ==============================================================================
# FeaturesticBox Enterprise SDK — Production R8 / ProGuard Obfuscation Rules
# ==============================================================================

# 1. Keep Public SDK API Surface
-keep public class com.featurestic.box.FeaturesticSdkConfig {
    public static ** create();
    public ** *;
}

-keep public class com.featurestic.box.FeaturesticBoxCore {
    public static ** *;
    public ** *;
}

-keep public class com.featurestic.box.FeaturesticWatermarkGuard {
    public static ** *;
}

-keep public class com.featurestic.box.FeaturesticAntiCrackVault$PanelAuthCallback { *; }
-keep public class com.featurestic.box.FeaturesticAntiCrackVault$BanCheckCallback { *; }

# 2. Obfuscate and Encrypt All Internal Core Logic, Native Bridges & Services
-repackageclasses 'com.featurestic.box.internal'
-allowaccessmodification
-overloadaggressively

-keepclasseswithmembers class * {
    native <methods>;
}

-keepclassmembers enum * {
    public static **[] values();
    public static ** valueOf(java.lang.String);
}

# 3. Strip All Debug Logging and Console Outputs in Release Builds
-assumenosideeffects class android.util.Log {
    public static boolean isLoggable(java.lang.String, int);
    public static int v(...);
    public static int d(...);
    public static int i(...);
    public static int w(...);
    public static int e(...);
}

-assumenosideeffects class java.io.PrintStream {
    public void println(...);
    public void print(...);
}

# 4. Protect Native JNI Method Bindings
-keepclasseswithmembernames,includedescriptorclasses class * {
    native <methods>;
}
