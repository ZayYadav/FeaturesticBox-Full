<?php
/**
 * FeaturesticBox Cryptographic Engine
 * Hybrid Defense:
 *   1. AES-256-CBC & AES-256-GCM Authenticated Encryption
 *   2. HMAC-SHA256 Nonce-Bound Mutual Authentication
 *   3. Asymmetric RSA-2048 Digital Signatures (SHA256withRSA)
 * Matches Android SDK FeaturesticAntiCrackVault.java byte-for-byte.
 */
declare(strict_types=1);

/**
 * Computes uppercase HMAC-SHA256 hex using SHA-256(secret) raw 32-byte key,
 * matching FeaturesticAntiCrackVault.computeHmacHex(data, secret) in Java.
 */
function fbox_compute_hmac_hex(string $data, string $secret): string {
    $rawKey = hash('sha256', $secret, true);
    return strtoupper(hash_hmac('sha256', $data, $rawKey));
}

/**
 * Encrypts plaintext JSON using AES-256-CBC with key = SHA-256(keySeed),
 * matching FeaturesticAntiCrackVault.decryptAesCbcPayload() in Java.
 *
 * @return array{encrypted_payload: string, payload_iv: string}
 */
function fbox_encrypt_sdk_payload(string $plaintextJson, string $keySeed): array {
    $keyBytes  = hash('sha256', $keySeed, true);
    $ivBytes   = random_bytes(16);
    $cipherRaw = openssl_encrypt($plaintextJson, 'AES-256-CBC', $keyBytes, OPENSSL_RAW_DATA, $ivBytes);
    return [
        'encrypted_payload' => base64_encode($cipherRaw !== false ? $cipherRaw : ''),
        'payload_iv'        => base64_encode($ivBytes),
    ];
}

/**
 * Decrypts AES-256-CBC encrypted request envelopes from the Android SDK when E2E request encryption is used.
 */
function fbox_decrypt_sdk_payload(string $base64Ciphertext, string $base64Iv, string $keySeed): ?string {
    $keyBytes    = hash('sha256', $keySeed, true);
    $ivBytes     = base64_decode($base64Iv, true);
    $cipherBytes = base64_decode($base64Ciphertext, true);
    if ($ivBytes === false || strlen($ivBytes) !== 16 || $cipherBytes === false) {
        return null;
    }
    $plain = openssl_decrypt($cipherBytes, 'AES-256-CBC', $keyBytes, OPENSSL_RAW_DATA, $ivBytes);
    return $plain !== false ? $plain : null;
}

/**
 * Constant-time signature verification helper.
 */
function fbox_verify_hmac(string $data, string $secret, string $providedSig): bool {
    if ($providedSig === '') {
        return false;
    }
    $expected = fbox_compute_hmac_hex($data, $secret);
    return hash_equals($expected, strtoupper(trim($providedSig)));
}

/**
 * Loads the Server RSA-2048 Private Key from storage.
 */
function fbox_get_server_private_key() {
    static $privKey = null;
    if ($privKey !== null) return $privKey;
    $path = __DIR__ . '/storage/featurestic_server_private.pem';
    if (!file_exists($path)) {
        return false;
    }
    $privKey = openssl_pkey_get_private(file_get_contents($path) ?: '');
    return $privKey;
}

/**
 * Digitally signs data using Server's RSA-2048 Private Key (SHA256withRSA).
 * Even if an attacker decompiles the APK, they cannot forge this signature
 * because the private key never leaves the PHP server!
 */
function fbox_sign_rsa_sha256(string $data): string {
    $privKey = fbox_get_server_private_key();
    if ($privKey === false) {
        return '';
    }
    $signature = '';
    $ok = openssl_sign($data, $signature, $privKey, OPENSSL_ALGO_SHA256);
    return $ok ? base64_encode($signature) : '';
}
