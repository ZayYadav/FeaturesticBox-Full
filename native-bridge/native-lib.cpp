#include <jni.h>
#include <string>
#include <vector>

/**
 * FeaturesticBox Native C++ Secret Obfuscator Bridge.
 * Stores the shared HMAC/AES-256 secret key in XOR-obfuscated byte form inside
 * compiled .so binary (unreadable by Java decompilers like Jadx/Bytecode Viewer).
 */

// XOR key mask to prevent plain string extraction via `strings libnative.so`
static const uint8_t SECRET_XOR_MASK = 0x5A;

// Obfuscated bytes for default secret: "FBOX_IOS_PANEL_DEFAULT_SECRET_2026"
// You can replace this with your own XOR-masked bytes
static const uint8_t OBFUSCATED_SECRET[] = {
    0x1C, 0x18, 0x15, 0x02, 0x05, 0x13, 0x15, 0x09, 0x05, 0x0A,
    0x1B, 0x14, 0x1F, 0x16, 0x05, 0x1E, 0x1F, 0x1C, 0x1B, 0x0F,
    0x16, 0x0E, 0x05, 0x09, 0x1F, 0x19, 0x08, 0x1F, 0x0E, 0x05,
    0x68, 0x6A, 0x68, 0x6C
};
static const size_t SECRET_LEN = sizeof(OBFUSCATED_SECRET);

extern "C" JNIEXPORT jstring JNICALL
Java_com_featurestic_box_FeaturesticNativeBridge_getObfuscatedServerSecret(
        JNIEnv* env,
        jclass /* clazz */) {
    std::string deobfuscated;
    deobfuscated.reserve(SECRET_LEN);
    for (size_t i = 0; i < SECRET_LEN; ++i) {
        deobfuscated.push_back(static_cast<char>(OBFUSCATED_SECRET[i] ^ SECRET_XOR_MASK));
    }
    return env->NewStringUTF(deobfuscated.c_str());
}

extern "C" JNIEXPORT jstring JNICALL
Java_com_featurestic_box_FeaturesticNativeBridge_getObfuscatedGatewayUrl(
        JNIEnv* env,
        jclass /* clazz */) {
    // Return empty by default so dynamic gateway/bootstrap URL is preferred
    return env->NewStringUTF("");
}
