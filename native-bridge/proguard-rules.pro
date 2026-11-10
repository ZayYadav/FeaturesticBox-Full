# FeaturesticBox Loader ProGuard / R8 Obfuscation Rules
# Add these rules to your Loader's app/proguard-rules.pro

# 1. Keep FeaturesticBox SDK Public API
-keep class com.featurestic.box.FeaturesticBoxCore { *; }
-keep class com.featurestic.box.FeaturesticSdkConfig { *; }
-keep class com.featurestic.box.FeaturesticAntiCrackVault { *; }
-keep class com.featurestic.box.FeaturesticRemoteConfigGuard { *; }
-keep class com.featurestic.box.FeaturesticCloudPluginLoader { *; }
-keep class com.featurestic.box.FeaturesticNativeBridge { *; }
-keep class com.featurestic.box.app.LauncherActivity { *; }

# 2. Keep JNI Native Methods
-keepclasseswithmembernames class * {
    native <methods>;
}

# 3. Obfuscate all internal Loader UI & Helper Classes aggressively
-repackageclasses 'a'
-allowaccessmodification
-dontusemixedcaseclassnames
-skipnonpubliclibraryclasses
-verbose
