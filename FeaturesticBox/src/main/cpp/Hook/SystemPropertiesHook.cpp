#include "SystemPropertiesHook.h"
#include "IO.h"
#include "BoxCore.h"
#import "JniHook/JniHook.h"
#include "Log.h"
#include "xdl.h"
#include "Substrate/CydiaSubstrate.h"
#include <sys/system_properties.h>
#include <mutex>
#include <cstring>

static std::map<std::string, std::string> prop_map;
static std::mutex prop_mutex;

HOOK_JNI(jstring, native_get, JNIEnv *env, jobject obj, jstring key, jstring def) {
    if (env == nullptr) {
        return nullptr;
    }
    if (key == nullptr) {
        return def;
    }

    const char *key_str = env->GetStringUTFChars(key, nullptr);
    if (key_str == nullptr) {
        return def;
    }

    std::string mappedValue;
    bool found = false;
    {
        std::lock_guard<std::mutex> lock(prop_mutex);
        auto ret = prop_map.find(key_str);
        if (ret != prop_map.end()) {
            mappedValue = ret->second;
            found = true;
        }
    }

    env->ReleaseStringUTFChars(key, key_str);

    if (found) {
        return env->NewStringUTF(mappedValue.c_str());
    }

    return orig_native_get(env, obj, key, def);
}

HOOK_JNI(int, __system_property_get, const char *name, char *value) {
    if (!name || !value) {
        return orig___system_property_get(name, value);
    }

    {
        std::lock_guard<std::mutex> lock(prop_mutex);
        auto ret = prop_map.find(name);
        if (ret != prop_map.end()) {
            const char *ret_value = ret->second.c_str();
            strncpy(value, ret_value, PROP_VALUE_MAX - 1);
            value[PROP_VALUE_MAX - 1] = '\0';
            return static_cast<int>(strlen(value));
        }
    }

    return orig___system_property_get(name, value);
}

void SystemPropertiesHook::setProp(const char *key, const char *value) {
    if (key != nullptr && value != nullptr) {
        std::lock_guard<std::mutex> lock(prop_mutex);
        prop_map[std::string(key)] = std::string(value);
    }
}

void SystemPropertiesHook::init(JNIEnv *env) {
    {
        std::lock_guard<std::mutex> lock(prop_mutex);
        // Only set verified bootloader/security props by default; do NOT hardcode Xiaomi "Mi 10" / "umi"
        // which causes a Build.MODEL vs ro.product.model mismatch detected by libanogs.so!
        prop_map.emplace("ro.debuggable", "0");
        prop_map.emplace("ro.secure", "1");
        prop_map.emplace("ro.boot.verifiedbootstate", "green");
        prop_map.emplace("ro.boot.flash.locked", "1");
        prop_map.emplace("ro.boot.vbmeta.device_state", "locked");
        prop_map.emplace("ro.boot.warranty_bit", "0");
        prop_map.emplace("sys.oem_unlock_allowed", "0");
        prop_map.emplace("ro.build.tags", "release-keys");
        prop_map.emplace("ro.build.type", "user");
    }

    JniHook::HookJniFun(
        env,
        "android/os/SystemProperties",
        "native_get",
        "(Ljava/lang/String;Ljava/lang/String;)Ljava/lang/String;",
        (void *) new_native_get,
        (void **) (&orig_native_get),
        true
    );
    void *libcHandle = xdl_open("libc.so", XDL_DEFAULT);
    if (libcHandle != nullptr) {
        void *sym = xdl_sym(libcHandle, "__system_property_get", nullptr);
        if (sym != nullptr) {
            MSHookFunction(sym, (void *) new___system_property_get, (void **) &orig___system_property_get);
        }
        xdl_close(libcHandle);
    }
}