<?php
/**
 * FeaturesticBox Cloud SDK Security Gateway (api.php)
 * Supports direct calls and dedicated endpoint wrappers:
 *   - login.php            (?action=auth / ?action=login)
 *   - ban_check.php        (?action=ban_check)
 *   - token_validation.php (?action=token_validation / ?action=heartbeat)
 *   - api.php?action=report_tamper
 *   - api.php?action=bootstrap
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crypto.php';

$pdo      = fbox_db();
$settings = fbox_get_all_settings();
$secret   = $settings['shared_api_secret'] ?? FBOX_DEFAULT_API_SECRET;
$clientIp = fbox_client_ip();

$rawInput = file_get_contents('php://input');
$body     = is_string($rawInput) && $rawInput !== '' ? (json_decode($rawInput, true) ?? []) : [];

// Unwrap optional End-to-End AES-256-CBC encrypted request envelope if present
if (!empty($body['e2e_payload']) && !empty($body['e2e_iv']) && !empty($body['client_nonce'])) {
    $envNonce = trim((string)$body['client_nonce']);
    $decryptedReq = fbox_decrypt_sdk_payload((string)$body['e2e_payload'], (string)$body['e2e_iv'], $envNonce . '|' . $secret);
    if ($decryptedReq !== null) {
        $inner = json_decode($decryptedReq, true);
        if (is_array($inner)) {
            $body = array_merge($body, $inner);
        }
    }
}

$action = $forcedAction ?? ($_GET['action'] ?? ($body['action'] ?? 'bootstrap'));

function fbox_build_encrypted_runtime_json(
    array $settings,
    string $boundNonce = '',
    string $boundHwid = '',
    string $sessionToken = '',
    string $rollingToken = '',
    ?array $keyRow = null,
    ?array $loaderProfile = null,
    bool $isHoneypot = false
): string {
    $runtimeParams = json_decode($settings['runtime_params_json'] ?? '{}', true);
    if (!is_array($runtimeParams)) {
        $runtimeParams = [];
    }

    // Merge key-specific custom offsets from buyer if set
    if ($keyRow !== null && !empty($keyRow['custom_offsets_json'])) {
        $customOffsets = json_decode((string)$keyRow['custom_offsets_json'], true);
        if (is_array($customOffsets)) {
            $runtimeParams = array_merge($runtimeParams, $customOffsets);
        }
    }
    // Specific loader profile offsets override key-level defaults
    if ($loaderProfile !== null && !empty($loaderProfile['offsets_json'])) {
        $profOffsets = json_decode((string)$loaderProfile['offsets_json'], true);
        if (is_array($profOffsets)) {
            $runtimeParams = array_merge($runtimeParams, $profOffsets);
        }
    }

    $flatParams = [];
    foreach ($runtimeParams as $k => $v) {
        $flatParams[(string)$k] = (string)$v;
    }

    // Determine 64-bit and 32-bit plugin URLs (Loader profile first, then buyer custom, else global admin setting)
    $url64 = (!empty($loaderProfile['plugin_url_64']))
        ? (string)$loaderProfile['plugin_url_64']
        : ((!empty($keyRow['custom_plugin_url_64'])) ? (string)$keyRow['custom_plugin_url_64'] : ($settings['plugin_url_64'] ?? ''));
    $url32 = (!empty($loaderProfile['plugin_url_32']))
        ? (string)$loaderProfile['plugin_url_32']
        : ((!empty($keyRow['custom_plugin_url_32'])) ? (string)$keyRow['custom_plugin_url_32'] : ($settings['plugin_url_32'] ?? ''));
    $pluginSha = (!empty($loaderProfile['plugin_sha256']))
        ? (string)$loaderProfile['plugin_sha256']
        : ((!empty($keyRow['custom_plugin_sha256'])) ? (string)$keyRow['custom_plugin_sha256'] : ($settings['plugin_sha256'] ?? ''));

    // Seamless GitHub raw URL normalization
    $url64 = fbox_normalize_github_url($url64);
    $url32 = fbox_normalize_github_url($url32);

    if ($isHoneypot) {
        $url64 = '';
        $url32 = '';
        $pluginSha = '';
        foreach ($flatParams as $hk => $hv) {
            if (stripos($hk, 'offset') !== false || strpos($hv, '0x') === 0) {
                $flatParams[$hk] = '0xDEAD0000';
            }
        }
        $flatParams['offset_guard_hook'] = '0xDEAD0000';
        $flatParams['offset_ace_worker'] = '0xDEAD0000';
        $flatParams['honeypot_active']   = '1';
    }

    $targetFps = ($keyRow !== null && (int)($keyRow['custom_target_fps'] ?? 0) > 0)
        ? (int)$keyRow['custom_target_fps']
        : (int)($settings['target_fps'] ?? 120);

    $devicePreset = ($keyRow !== null && !empty($keyRow['custom_device_preset']))
        ? (string)$keyRow['custom_device_preset']
        : ($settings['device_preset'] ?? 'ROG_PHONE_8');

    // Enforce Admin Plan Entitlements Matrix (Starter / Pro / Enterprise / Custom Deal)
    $ent = $keyRow !== null ? fbox_get_key_entitlements($keyRow) : fbox_get_plan_preset_entitlements('enterprise');
    if (empty($ent['ent_cloud_so'])) {
        $url64 = '';
        $url32 = '';
        $pluginSha = '';
    }
    if (empty($ent['ent_device_spoofer'])) {
        $devicePreset = 'NONE';
        $targetFps = 60;
    }

    $wmMode = $keyRow['watermark_mode'] ?? 'inherit';
    $enableWatermark = ($wmMode === 'on') || ($wmMode === 'inherit' && ($settings['enable_leak_watermark'] ?? '0') === '1');
    if ($wmMode === 'off' && !empty($ent['ent_watermark_off'])) {
        $enableWatermark = false;
    } elseif (empty($ent['ent_watermark_off'])) {
        // Basic plan without watermark removal entitlement keeps watermark active if enabled by Admin
        $enableWatermark = true;
    }
    $wmCustomText = ($keyRow !== null && !empty($ent['ent_watermark_off']) && !empty($keyRow['custom_watermark_text']))
        ? (string)$keyRow['custom_watermark_text'] : '';

    $allowedGames = ['*'];
    if ($keyRow !== null && !empty($keyRow['allowed_games_json'])) {
        $decAg = json_decode((string)$keyRow['allowed_games_json'], true);
        if (is_array($decAg) && !empty($decAg)) {
            $allowedGames = array_values($decAg);
        }
    }

    // In-game features map: default floating_menu is OFF (0) so game has no built-in menu unless buyer enables it
    $features = [
        'floating_menu' => ($settings['feature_floating_menu'] ?? '0') === '1',
        'esp'           => ($settings['feature_esp'] ?? '1') === '1',
        'aimbot'        => ($settings['feature_aimbot'] ?? '1') === '1',
        'bullet_track'  => ($settings['feature_bullet_track'] ?? '1') === '1',
        'streamproof'   => ($settings['feature_streamproof'] ?? '1') === '1',
        'fps_unlock'    => true,
    ];
    if ($keyRow !== null && !empty($keyRow['custom_features_json'])) {
        $customFeats = json_decode((string)$keyRow['custom_features_json'], true);
        if (is_array($customFeats)) {
            foreach ($customFeats as $fk => $fv) {
                $features[(string)$fk] = (bool)$fv;
            }
        }
    }

    return json_encode([
        'bound_nonce'            => $boundNonce,
        'bound_hwid'             => $boundHwid,
        'session_token'          => $sessionToken,
        'rolling_token'          => $rollingToken,
        'server_time'            => time(),
        'maintenance_mode'       => ($settings['maintenance_mode'] ?? '0') === '1',
        'maintenance_message'    => $settings['maintenance_message'] ?? '',
        'announcement'           => $settings['announcement'] ?? '',
        'target_fps'             => $targetFps,
        'device_preset'          => $devicePreset,
        'gfx_preset'             => (int)($settings['gfx_preset'] ?? 0),
        'features'               => $features,
        'streamproof_enabled'        => (!empty($ent['ent_streamproof']) && !empty($features['streamproof'])),
        'diagnostic_mode'            => (!empty($ent['ent_crash_bot']) && $keyRow !== null && (int)($keyRow['is_diagnostic_mode'] ?? 0) === 1 && ((int)($keyRow['diagnostic_expires_at'] ?? 0) === 0 || time() < (int)$keyRow['diagnostic_expires_at'])),
        'fps_hud_enabled'            => !empty($features['floating_menu']),
        'enable_leak_watermark'      => $enableWatermark,
        'custom_watermark_text'      => $wmCustomText,
        'telemetry_silencer_enabled' => (!empty($ent['ent_telemetry_silencer']) && ($keyRow === null || (int)($keyRow['telemetry_silencer_enabled'] ?? 1) === 1)),
        'ram_diet_enabled'           => (!empty($ent['ent_ram_diet']) && ($keyRow === null || (int)($keyRow['ram_diet_enabled'] ?? 1) === 1)),
        'ram_only_so_enabled'        => (!empty($ent['ent_ram_only_so']) && ($keyRow === null || (int)($keyRow['ram_only_so_enabled'] ?? 1) === 1)),
        'anogs_maps_cloak_enabled'   => (!empty($ent['ent_ram_only_so']) && ($keyRow === null || (int)($keyRow['anogs_maps_cloak_enabled'] ?? 1) === 1)),
        'anogs_crc_shield_enabled'   => (!empty($ent['ent_ram_only_so']) && ($keyRow === null || (int)($keyRow['anogs_crc_shield_enabled'] ?? 1) === 1)),
        'cloak_vpn_interface'        => (!empty($ent['ent_telemetry_silencer']) && ($keyRow === null || (int)($keyRow['cloak_vpn_interface'] ?? 1) === 1)),
        'enforce_arm64_only'     => ($settings['enforce_arm64_only'] ?? '0') === '1',
        'min_android_version'    => (int)($settings['min_android_version'] ?? 9),
        'min_loader_version'     => (int)($settings['min_loader_version'] ?? 1),
        'update_apk_url'         => $settings['update_apk_url'] ?? '',
        'hide_xposed'            => ($settings['hide_xposed'] ?? '1') === '1',
        'block_http_proxy'       => ($settings['block_http_proxy'] ?? '1') === '1',
        'block_root_magisk'      => ($settings['block_root_magisk'] ?? '0') === '1',
        'block_emulator'         => ($settings['block_emulator'] ?? '0') === '1',
        'locked_apk_sha256'      => ($settings['enforce_apk_signature'] ?? '0') === '1'
            ? ($settings['locked_apk_sha256'] ?? '') : '',
        'pinned_tls_cert_sha256' => $settings['pinned_tls_cert_sha256'] ?? '',
        'plugin_url_64'          => $url64,
        'plugin_url_32'          => $url32,
        'plugin_sha256'          => $pluginSha,
        'allowed_games'          => $allowedGames,
        'runtime_params'         => $flatParams,
    ], JSON_UNESCAPED_SLASHES);
}

// ---------------------------------------------------------------------------
// 1. Dynamic Endpoint Discovery (GET/POST ?action=bootstrap)
// ---------------------------------------------------------------------------
if ($action === 'bootstrap') {
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseDir = rtrim(dirname(strtok($_SERVER['REQUEST_URI'] ?? '/api.php', '?')), '/\\');
    $selfUrl = $scheme . '://' . $host . $baseDir . '/api.php';
    $primary = !empty($settings['dynamic_primary_endpoint']) ? $settings['dynamic_primary_endpoint'] : $selfUrl;

    echo json_encode([
        'status'                 => 'OK',
        'panel_api_url'          => $primary,
        'login_endpoint'         => $scheme . '://' . $host . $baseDir . '/login.php',
        'ban_check_endpoint'     => $scheme . '://' . $host . $baseDir . '/ban_check.php',
        'token_val_endpoint'     => $scheme . '://' . $host . $baseDir . '/token_validation.php',
        'backup_api_url'         => $settings['dynamic_backup_endpoint'] ?? '',
        'pinned_tls_cert_sha256' => $settings['pinned_tls_cert_sha256'] ?? '',
        'maintenance_mode'       => ($settings['maintenance_mode'] ?? '0') === '1',
        'announcement'           => $settings['announcement'] ?? '',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------------------------------------------------------------------------
// 2. Pre-Connection HWID & IP Blacklist Check (ban_check.php / ?action=ban_check)
// ---------------------------------------------------------------------------
if ($action === 'ban_check') {
    $hwid        = trim((string)($body['hwid'] ?? ($_GET['hwid'] ?? '')));
    $nonce       = trim((string)($body['nonce'] ?? ($_GET['nonce'] ?? '')));
    $deviceModel = trim((string)($body['device_model'] ?? 'Android Device'));

    $banRow = fbox_find_active_ban($hwid, $clientIp);
    if ($banRow !== null) {
        $reason = (string)($banRow['ban_reason'] ?? 'TAMPER_DETECTED');
        $sigStr = 'DEVICE_BANNED|' . $nonce . '|' . $hwid . '|1';
        $sig    = fbox_compute_hmac_hex($sigStr, $secret);
        $rsaSig = fbox_sign_rsa_sha256($sigStr);
        echo json_encode([
            'status'        => 'DEVICE_BANNED',
            'is_banned'     => true,
            'terminate'     => true,
            'ban_reason'    => $reason,
            'banned_ip'     => $banRow['ip_address'] ?? $clientIp,
            'nonce'         => $nonce,
            'message'       => "Access Denied: Your HWID or IP is blacklisted ($reason). Unban only from Admin Panel.",
            'signature'     => $sig,
            'rsa_signature' => $rsaSig,
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $sigStr = 'CLEAN|' . $nonce . '|' . $hwid . '|0';
    $sig    = fbox_compute_hmac_hex($sigStr, $secret);
    $rsaSig = fbox_sign_rsa_sha256($sigStr);
    echo json_encode([
        'status'        => 'CLEAN',
        'is_banned'     => false,
        'terminate'     => false,
        'nonce'         => $nonce,
        'message'       => 'Device HWID and IP are clean.',
        'signature'     => $sig,
        'rsa_signature' => $rsaSig,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------------------------------------------------------------------------
// 2.8 Database Backup (GET/POST ?action=backup_db)
// ---------------------------------------------------------------------------
if ($action === 'backup_db') {
    $adminKey = trim((string)($body['admin_secret'] ?? ($_GET['admin_secret'] ?? '')));
    if ($adminKey !== FBOX_DEFAULT_API_SECRET && $adminKey !== ($settings['shared_api_secret'] ?? '')) {
        echo json_encode(['status' => 'UNAUTHORIZED', 'message' => 'Invalid admin secret']);
        exit;
    }
    $res = fbox_backup_database();
    echo json_encode($res, JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------------------------------------------------------------------------
// 2.5 Automated Crash & Diagnostic Log Telemetry (POST ?action=report_crash)
// ---------------------------------------------------------------------------
if ($action === 'report_crash') {
    $hwid        = trim((string)($body['hwid'] ?? ''));
    $deviceModel = trim((string)($body['device_model'] ?? 'Unknown Device'));
    $pkg         = trim((string)($body['package_name'] ?? ''));
    $licenseKey  = trim((string)($body['license_key'] ?? ''));
    $loaderId    = trim((string)($body['loader_id'] ?? 'default'));
    $crashType   = trim((string)($body['crash_type'] ?? 'UNCAUGHT_EXCEPTION'));
    $stackTrace  = trim((string)($body['stack_trace'] ?? ''));
    $logcat      = trim((string)($body['logcat_snippet'] ?? ''));
    $now         = time();

    $faultAddr      = trim((string)($body['fault_address'] ?? ''));
    $crashingLib    = trim((string)($body['crashing_library'] ?? ''));
    $crashingOffset = trim((string)($body['crashing_offset'] ?? ''));
    $isTestMode     = (int)($body['is_test_mode'] ?? 0);

    if ($stackTrace !== '' || $logcat !== '' || $faultAddr !== '') {
        $insCrash = $pdo->prepare("INSERT INTO crash_reports
            (hwid, device_model, package_name, license_key, loader_id, crash_type, fault_address, crashing_library, crashing_offset, is_test_mode, stack_trace, logcat_snippet, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insCrash->execute([$hwid, $deviceModel, $pkg, $licenseKey, $loaderId, $crashType, $faultAddr, $crashingLib, $crashingOffset, $isTestMode, $stackTrace, $logcat, $now]);

        fbox_send_crash_alert($hwid, $deviceModel, $pkg, $licenseKey, $loaderId, $crashType, $stackTrace, $faultAddr, $crashingLib, $crashingOffset);
        fbox_check_and_trip_circuit_breaker($pdo, $licenseKey, 'CRASH:' . ($crashingLib ?: $crashType));
    }

    echo json_encode(['status' => 'OK', 'message' => 'Telemetry received']);
    exit;
}

// ---------------------------------------------------------------------------
// 3. Autonomous Anti-Crack Device + IP Ban Endpoint (POST ?action=report_tamper)
// ---------------------------------------------------------------------------
if ($action === 'report_tamper') {
    $hwid        = trim((string)($body['hwid'] ?? ''));
    $pkg         = trim((string)($body['package_name'] ?? ''));
    $apkSha      = trim((string)($body['apk_sha256'] ?? ''));
    $deviceModel = trim((string)($body['device_model'] ?? 'Unknown Device'));
    $reason      = trim((string)($body['tamper_reason'] ?? 'TAMPER_DETECTED'));
    $details     = trim((string)($body['tamper_details'] ?? ''));
    $licenseKey  = trim((string)($body['license_key'] ?? ''));

    if ($hwid !== '') {
        $shouldBan = true;
        if ($licenseKey !== '') {
            $kStmt = $pdo->prepare("SELECT buyer_autoban_enabled FROM license_keys WHERE license_key = ?");
            $kStmt->execute([$licenseKey]);
            $kRow = $kStmt->fetch();
            if ($kRow && (int)($kRow['buyer_autoban_enabled'] ?? 1) === 0) {
                $shouldBan = false;
            }
        }
        if ($shouldBan) {
            fbox_ban_device_hwid(
                $hwid,
                $deviceModel,
                $pkg,
                $apkSha,
                $licenseKey,
                $reason,
                $details,
                'SDK Anti-Crack Auto-Ban'
            );
        } else {
            fbox_log_security_event('TAMPER_DETECTED_NO_BAN', 'warning', $hwid, $licenseKey, $deviceModel, "Tamper detected ($reason) but auto-ban disabled by buyer preference");
        }
        fbox_check_and_trip_circuit_breaker($pdo, $licenseKey, 'TAMPER:' . $reason);
    }

    echo json_encode([
        'status'    => 'DEVICE_BANNED',
        'is_banned' => true,
        'terminate' => true,
        'message'   => "Device & IP blacklisted by FeaturesticBox Anti-Crack Shield ($reason). Unban only via Admin Panel.",
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------------------------------------------------------------------------
// 4. License / User Authentication & Nonce-Bound Encrypted Payload (login.php / ?action=auth)
// ---------------------------------------------------------------------------
if ($action === 'auth' || $action === 'login') {
    $licenseKey  = trim((string)($body['license_key'] ?? ''));
    $username    = trim((string)($body['username'] ?? ''));
    $password    = (string)($body['password'] ?? '');
    $hwid        = trim((string)($body['hwid'] ?? ''));
    $loaderId    = trim((string)($body['loader_id'] ?? 'default'));
    if ($loaderId === '') $loaderId = 'default';
    $pkg         = trim((string)($body['package_name'] ?? ''));
    $apkSha      = strtoupper(str_replace(':', '', trim((string)($body['apk_sha256'] ?? ''))));
    $deviceModel = trim((string)($body['device_model'] ?? 'Android Device'));
    $ts          = (int)($body['timestamp'] ?? 0);
    $nonce       = trim((string)($body['nonce'] ?? ''));
    $reqSig      = trim((string)($body['signature'] ?? ''));
    $now         = time();

    if ($hwid === '' || strlen($hwid) < 16) {
        echo json_encode(['status' => 'INVALID_REQUEST', 'message' => 'Missing or invalid device HWID']);
        exit;
    }

    // Step A: Check if HWID or linked IP is blacklisted
    $banRow = fbox_find_active_ban($hwid, $clientIp);
    if ($banRow !== null) {
        // Check if Buyer/Admin enabled Silent Honeypot Mode (Fake 0xDEAD0000 Offsets) for this key
        $hpCheckRow = false;
        if ($licenseKey !== '') {
            $hpStmt = $pdo->prepare("SELECT * FROM license_keys WHERE license_key = ? LIMIT 1");
            $hpStmt->execute([$licenseKey]);
            $hpCheckRow = $hpStmt->fetch();
        }
        if ($hpCheckRow !== false && (int)($hpCheckRow['honeypot_on_tamper'] ?? 0) === 1) {
            fbox_log_security_event('HONEYPOT_DECOY_SERVED', 'info', $hwid, $licenseKey, $deviceModel,
                'Served fake 0xDEAD0000 honeypot offsets to banned cracker HWID (' . $banRow['ban_reason'] . ')');
            $fakeSess = bin2hex(random_bytes(24));
            $fakeRoll = bin2hex(random_bytes(24));
            $fakeExp  = $now + 3600;
            $hpPlain  = fbox_build_encrypted_runtime_json($settings, $nonce, $hwid, $fakeSess, $fakeRoll, $hpCheckRow, null, true);
            $hpSeed   = $fakeSess . '|' . ($nonce !== '' ? ($nonce . '|') : '') . $secret;
            $hpEnc    = fbox_encrypt_sdk_payload($hpPlain, $hpSeed);
            $hpSigStr = 'OK|' . ($nonce !== '' ? ($nonce . '|') : '') . $fakeSess . '|' . $fakeRoll . '|' . $hwid . '|' . $fakeExp . '|' . $hpEnc['encrypted_payload'];
            echo json_encode([
                'status'            => 'OK',
                'is_banned'         => false,
                'honeypot_mode'     => true,
                'message'           => 'Authenticated with FeaturesticBox Panel',
                'nonce'             => $nonce,
                'session_token'     => $fakeSess,
                'rolling_token'     => $fakeRoll,
                'lease_expires_at'  => $fakeExp,
                'encrypted_payload' => $hpEnc['encrypted_payload'],
                'payload_iv'        => $hpEnc['payload_iv'],
                'signature'         => fbox_compute_hmac_hex($hpSigStr, $secret),
                'rsa_signature'     => fbox_sign_rsa_sha256($hpSigStr),
            ], JSON_UNESCAPED_SLASHES);
            exit;
        }

        fbox_log_security_event('BANNED_DEVICE_BLOCKED', 'warning', $hwid, $licenseKey, $deviceModel,
            'Blocked login on blacklisted HWID/IP (' . $banRow['ban_reason'] . ')');
        $banSig = fbox_compute_hmac_hex('DEVICE_BANNED|' . $nonce . '|' . $hwid, $secret);
        echo json_encode([
            'status'    => 'DEVICE_BANNED',
            'is_banned' => true,
            'terminate' => true,
            'nonce'     => $nonce,
            'signature' => $banSig,
            'message'   => 'This device or IP is blacklisted (' . $banRow['ban_reason'] . '). Contact Admin to unban from Panel.',
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Step B: Verify Client Request HMAC & Timestamp Replay Window (if enforce_request_hmac enabled)
    if (($settings['enforce_request_hmac'] ?? '1') === '1' && $reqSig !== '') {
        if ($ts > 0 && abs($now - $ts) > 300) {
            fbox_log_security_event('REPLAY_TIMESTAMP_REJECTED', 'warning', $hwid, $licenseKey, $deviceModel,
                "Request timestamp skew too large (ts=$ts, now=$now)");
            echo json_encode([
                'status'  => 'REPLAY_DETECTED',
                'message' => 'Request timestamp expired. Check device clock.',
            ]);
            exit;
        }
        $authIdentity = $licenseKey !== '' ? $licenseKey : $username;
        $expectedReqSig = fbox_compute_hmac_hex(
            $authIdentity . '|' . $hwid . '|' . $pkg . '|' . $apkSha . '|' . $ts . '|' . $nonce,
            $secret
        );
        // Also accept legacy key-only signature format
        $legacyReqSig = fbox_compute_hmac_hex(
            $licenseKey . '|' . $hwid . '|' . $pkg . '|' . $apkSha . '|' . $ts . '|' . $nonce,
            $secret
        );
        if (!hash_equals($expectedReqSig, strtoupper($reqSig)) && !hash_equals($legacyReqSig, strtoupper($reqSig))) {
            fbox_log_security_event('REQUEST_HMAC_MISMATCH', 'critical', $hwid, $licenseKey, $deviceModel,
                'Client request HMAC signature verification failed');
            if (($settings['auto_ban_on_tamper'] ?? '1') === '1') {
                fbox_ban_device_hwid($hwid, $deviceModel, $pkg, $apkSha, $licenseKey,
                    'CLIENT_REQUEST_SIGNATURE_TAMPER', 'Invalid request HMAC during login', 'Server Request Guard');
            }
            echo json_encode([
                'status'    => 'DEVICE_BANNED',
                'is_banned' => true,
                'terminate' => true,
                'message'   => 'Tampered login request detected! Device blacklisted.',
            ]);
            exit;
        }
    }

    // Step C: Check APK Signature Lock if enabled in Panel
        // Emergency Kill Switch
    if (($settings['kill_switch_active'] ?? '0') === '1') {
        echo json_encode([
            'status'    => 'SERVER_LOCKDOWN',
            'is_banned' => false,
            'terminate' => true,
            'nonce'     => $nonce,
            'message'   => $settings['maintenance_message'] ?: 'Emergency maintenance in progress. System temporarily locked.',
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Mandatory Force Update Gate
    $clientVer = (int)($body['loader_version'] ?? 1);
    $minVer    = (int)($settings['min_loader_version'] ?? 1);
    if ($minVer > 1 && $clientVer < $minVer && !empty($settings['update_apk_url'])) {
        echo json_encode([
            'status'       => 'UPDATE_REQUIRED',
            'is_banned'    => false,
            'terminate'    => true,
            'nonce'        => $nonce,
            'download_url' => $settings['update_apk_url'],
            'message'      => $settings['force_update_message'] ?? 'A mandatory security update is required to continue.',
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (($settings['enforce_apk_signature'] ?? '0') === '1') {
        $expectedSha = strtoupper(str_replace(':', '', trim((string)($settings['locked_apk_sha256'] ?? ''))));
        if ($expectedSha !== '' && $apkSha !== '' && $apkSha !== 'UNSIGNED_DEV' && $apkSha !== $expectedSha) {
            if (($settings['auto_ban_on_tamper'] ?? '1') === '1') {
                fbox_ban_device_hwid(
                    $hwid,
                    $deviceModel,
                    $pkg,
                    $apkSha,
                    $licenseKey,
                    'APK_SIGNATURE_MISMATCH',
                    "Expected $expectedSha, got $apkSha",
                    'Server Signature Guard'
                );
                echo json_encode([
                    'status'    => 'DEVICE_BANNED',
                    'is_banned' => true,
                    'terminate' => true,
                    'message'   => 'Repackaged/Modified APK detected! Your device and IP have been banned.',
                ], JSON_UNESCAPED_SLASHES);
                exit;
            }
            echo json_encode([
                'status'  => 'SIGNATURE_MISMATCH',
                'message' => 'Unauthorized APK signature.',
            ]);
            exit;
        }
    }

    // Step D: Check Maintenance Mode
    if (($settings['maintenance_mode'] ?? '0') === '1') {
        echo json_encode([
            'status'           => 'MAINTENANCE',
            'maintenance_mode' => true,
            'message'          => $settings['maintenance_message'] ?? 'Server is under maintenance.',
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Step E: Lookup License Row by Master SDK Key, Per-Loader Sub-API Key (sub_api_key), OR Username+Password
    $keyRow = false;
    if ($licenseKey !== '') {
        $keyStmt = $pdo->prepare("SELECT * FROM license_keys WHERE license_key = ?");
        $keyStmt->execute([$licenseKey]);
        $keyRow = $keyStmt->fetch();

        // Check if client supplied a Per-Loader Sub-API Key (Cloud API Key style)
        if ($keyRow === false) {
            try {
                $subStmt = $pdo->prepare("SELECT * FROM loader_profiles WHERE sub_api_key = ? AND sub_api_key != '' LIMIT 1");
                $subStmt->execute([$licenseKey]);
                $subProf = $subStmt->fetch();
                if ($subProf !== false) {
                    $pStmt = $pdo->prepare("SELECT * FROM license_keys WHERE id = ? LIMIT 1");
                    $pStmt->execute([(int)$subProf['license_key_id']]);
                    $keyRow = $pStmt->fetch();
                    if ($keyRow !== false) {
                        $loaderId = (string)$subProf['loader_id'];
                    }
                }
            } catch (Throwable $ignored) {}
        }
    } elseif ($username !== '') {
        $uStmt = $pdo->prepare("SELECT * FROM license_keys WHERE username = ?");
        $uStmt->execute([$username]);
        $keyRow = $uStmt->fetch();
    }

    if ($keyRow === false) {
        fbox_log_security_event('INVALID_CREDENTIALS', 'warning', $hwid, $licenseKey !== '' ? $licenseKey : $username,
            $deviceModel, 'Invalid license key or username');
        echo json_encode([
            'status'    => 'INVALID_KEY',
            'is_banned' => false,
            'message'   => 'Invalid license credentials. Please check your key or username.',
        ]);
        exit;
    }

    // Verify password if the license entry has a username/password configured and user supplied username/password
    if (!empty($keyRow['password_hash']) && ($username !== '' || $password !== '')) {
        if (!password_verify($password, (string)$keyRow['password_hash'])) {
            fbox_log_security_event('INVALID_PASSWORD', 'warning', $hwid, (string)$keyRow['license_key'],
                $deviceModel, "Wrong password for user '$username'");
            echo json_encode([
                'status'    => 'INVALID_CREDENTIALS',
                'is_banned' => false,
                'message'   => 'Incorrect username or password.',
            ]);
            exit;
        }
    }

    $resolvedKey = (string)$keyRow['license_key'];

    if ($keyRow['status'] !== 'active') {
        echo json_encode([
            'status'    => 'KEY_PAUSED',
            'is_banned' => false,
            'message'   => 'This license key has been paused or revoked by Admin.',
        ]);
        exit;
    }

    // Check Automated Ban-Wave Circuit Breaker
    if ((int)($keyRow['circuit_breaker_tripped'] ?? 0) === 1) {
        echo json_encode([
            'status'    => 'CIRCUIT_BREAKER_LOCKDOWN',
            'is_banned' => false,
            'terminate' => true,
            'nonce'     => $nonce,
            'message'   => 'Safety Shield Active: Game security update detected. Loader is temporarily paused to keep your game account 100% safe.',
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Strict Status Gate: Blocked, Paused, or Deactivated keys cannot log in
    $currentStatus = strtolower(trim((string)($keyRow['status'] ?? 'active')));
    if ($currentStatus !== 'active') {
        $stUpper = strtoupper($currentStatus);
        fbox_log_security_event('BLOCKED_KEY_LOGIN_DENIED', 'warning', $hwid, $resolvedKey, $deviceModel, "Login attempt with {$stUpper} key");
        echo json_encode([
            'status'    => 'KEY_BLOCKED',
            'is_banned' => false,
            'terminate' => true,
            'message'   => "License key is currently {$stUpper}. Please contact support or your reseller to activate.",
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $activated = (int)$keyRow['activated_at'];
    $expiresAt = (int)$keyRow['expires_at'];
    $durDays   = (int)$keyRow['duration_days'];

    if ($activated === 0) {
        $activated = $now;
        $expiresAt = $durDays > 0 ? ($now + ($durDays * 86400)) : ($now + (3650 * 86400));
    } elseif ($expiresAt > 0 && $now >= $expiresAt) {
        $pdo->prepare("UPDATE license_keys SET status = 'expired' WHERE id = ?")->execute([$keyRow['id']]);
        echo json_encode([
            'status'    => 'KEY_EXPIRED',
            'is_banned' => false,
            'message'   => 'Your license key expired on ' . date('d M Y, H:i', $expiresAt) . '.',
        ]);
        exit;
    }

    // Step F1: Panel-Controlled Geo-IP / Country Lock (if configured by Buyer or Admin)
    $allowedCountries = strtoupper(trim((string)($keyRow['allowed_countries'] ?? 'ALL')));
    if ($allowedCountries !== '' && $allowedCountries !== 'ALL' && $allowedCountries !== '*') {
        $clientCountry = strtoupper(trim((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? $_SERVER['HTTP_X_COUNTRY_CODE'] ?? $_SERVER['GEOIP_COUNTRY_CODE'] ?? '')));
        if ($clientCountry !== '' && $clientCountry !== 'XX') {
            $countryList = array_map('trim', explode(',', $allowedCountries));
            if (!in_array($clientCountry, $countryList, true)) {
                fbox_log_security_event('GEO_COUNTRY_BLOCKED', 'warning', $hwid, $resolvedKey, $deviceModel,
                    "Blocked SDK access from unauthorized country '$clientCountry' (Allowed: $allowedCountries)");
                echo json_encode([
                    'status'    => 'GEO_REGION_BLOCKED',
                    'is_banned' => false,
                    'terminate' => true,
                    'message'   => "Region Restricted: This SDK license is only authorized in [$allowedCountries].",
                ]);
                exit;
            }
        }
    }

    // Step F2: B2B Loader APK Slot & APK Signature Binding (Prevents Buyer from sharing .aar across unauthorized Loader APKs)
    $maxSlots = (int)($keyRow['max_loader_slots'] ?? 1);
    $boundApks = json_decode((string)($keyRow['bound_apks_json'] ?? '[]'), true);
    if (!is_array($boundApks)) {
        $boundApks = [];
    }
    $apkSlotKey = ($pkg !== '' ? $pkg : 'default_loader') . '|' . ($loaderId !== '' ? $loaderId : 'default');
    $lockLoaderSig = (int)($keyRow['lock_loader_apk_signature'] ?? 1) === 1;

    if (!isset($boundApks[$apkSlotKey])) {
        if ($maxSlots > 0 && count($boundApks) >= $maxSlots) {
            fbox_log_security_event('LOADER_SLOTS_EXCEEDED', 'warning', $hwid, $resolvedKey, $deviceModel,
                "Buyer tried to bind extra Loader APK '$apkSlotKey' ($maxSlots Loader Slots allowed)");
            echo json_encode([
                'status'    => 'LOADER_SLOTS_EXCEEDED',
                'is_banned' => false,
                'terminate' => true,
                'message'   => "SDK Loader APK Slot limit reached ($maxSlots Loader APK allowed). Ask Admin to upgrade your Loader Slots or reset bound APKs.",
            ]);
            exit;
        }
        $boundApks[$apkSlotKey] = [
            'package'    => $pkg,
            'loader_id'  => $loaderId,
            'apk_sha256' => $apkSha,
            'bound_at'   => $now,
        ];
        fbox_send_buyer_bot_notice($keyRow, 'New Loader APK Bound to SDK Slot', [
            'SDK Key'      => $resolvedKey,
            'Loader Pkg'   => $pkg ?: 'default',
            'Loader ID'    => $loaderId,
            'APK SHA-256'  => substr($apkSha, 0, 16) . '...',
            'Slots Used'   => count($boundApks) . ' / ' . ($maxSlots === 0 ? 'Unlimited' : $maxSlots),
        ]);
    } else {
        $savedSha = strtoupper(trim((string)($boundApks[$apkSlotKey]['apk_sha256'] ?? '')));
        if ($lockLoaderSig && $savedSha !== '' && $savedSha !== 'HOST_UNAVAILABLE' && $apkSha !== '' && $apkSha !== 'HOST_UNAVAILABLE' && $savedSha !== $apkSha) {
            fbox_log_security_event('LOADER_APK_SIGNATURE_TAMPER', 'critical', $hwid, $resolvedKey, $deviceModel,
                "Loader APK '$pkg' signature mismatch! Bound: $savedSha, Got: $apkSha");
            echo json_encode([
                'status'    => 'SIGNATURE_MISMATCH',
                'is_banned' => false,
                'terminate' => true,
                'message'   => 'Unauthorized or modified Loader APK signature detected! If you rebuilt your Loader APK, click Reset Bound APK Signature in your Buyer Console.',
            ]);
            exit;
        }
    }

    // Step F3: End-User Device Binding (max_devices = 0 means Unlimited End-User Players for B2B SDK Mode!)
    $boundHwids = json_decode((string)$keyRow['bound_hwids_json'], true);
    if (!is_array($boundHwids)) {
        $boundHwids = [];
    }
    $maxDevices = max(0, (int)($keyRow['max_devices'] ?? 0));

    if (!in_array($hwid, $boundHwids, true)) {
        if ($maxDevices > 0 && count($boundHwids) >= $maxDevices) {
            fbox_log_security_event('HWID_MISMATCH', 'warning', $hwid, $resolvedKey, $deviceModel,
                "Key already locked to " . count($boundHwids) . "/$maxDevices HWID(s)");
            echo json_encode([
                'status'    => 'HWID_LIMIT_REACHED',
                'is_banned' => false,
                'message'   => "License is locked to another device HWID ($maxDevices max). Request HWID Reset from Admin.",
            ]);
            exit;
        }
        $boundHwids[] = $hwid;
        if ($maxDevices === 0 && count($boundHwids) > 100) {
            $boundHwids = array_slice($boundHwids, -100);
        }
    }

    // Update license key stats & bound Loader APKs
    $updKey = $pdo->prepare("UPDATE license_keys SET
        bound_hwids_json = ?, bound_apks_json = ?, activated_at = ?, expires_at = ?,
        last_login_at = ?, last_ip = ?, last_device_model = ?,
        total_logins = total_logins + 1
        WHERE id = ?");
    $updKey->execute([
        json_encode(array_values($boundHwids)),
        json_encode($boundApks),
        $activated,
        $expiresAt,
        $now,
        $clientIp,
        $deviceModel,
        $keyRow['id']
    ]);

    // Step G: Issue Session Token + Rolling Token & Nonce-Bound AES-256-CBC Payload
    $sessionToken = bin2hex(random_bytes(24));
    $rollingToken = bin2hex(random_bytes(24));
    $leaseExpires = min($expiresAt > 0 ? $expiresAt : ($now + FBOX_SESSION_LEASE_TTL), $now + FBOX_SESSION_LEASE_TTL);

    // Lookup specific Loader Profile (for multi-loader support)
    $loaderProfile = fbox_get_loader_profile($resolvedKey, $loaderId);
    if ($loaderProfile === null && $pkg !== '') {
        $stmtPkg = $pdo->prepare("SELECT * FROM loader_profiles WHERE license_key = ? AND (target_package = ? OR loader_id = ?) LIMIT 1");
        $stmtPkg->execute([$resolvedKey, $pkg, $pkg]);
        $loaderProfile = $stmtPkg->fetch() ?: null;
    }

    // Check allowed games authorization (only if an active virtualized game package is specified)
    $allowedGames = json_decode((string)($keyRow['allowed_games_json'] ?? '["*"]'), true);
    if (!is_array($allowedGames) || empty($allowedGames)) {
        $allowedGames = ['*'];
    }
    $activeGamePkg = trim((string)($body['active_game'] ?? ''));
    if (!in_array('*', $allowedGames, true) && $activeGamePkg !== '' && $activeGamePkg !== '*' && !in_array($activeGamePkg, $allowedGames, true)) {
        echo json_encode([
            'status'    => 'GAME_NOT_AUTHORIZED',
            'is_banned' => false,
            'message'   => "Access Denied: Your SDK license is not authorized for game '$activeGamePkg'. Contact Admin to unlock this game.",
        ]);
        exit;
    }

    // Auto-register loader profile if within max_loader_slots
    if ($loaderProfile === null && $loaderId !== 'default') {
        $countStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM loader_profiles WHERE license_key_id = ?");
        $countStmt->execute([$keyRow['id']]);
        $currentLoaderCount = (int)($countStmt->fetchColumn() ?? 0);
        if ($maxSlots === 0 || $currentLoaderCount < $maxSlots) {
            $insProf = $pdo->prepare("INSERT INTO loader_profiles
                (license_key_id, license_key, loader_id, loader_name, target_package, plugin_url_64, plugin_url_32, plugin_sha256, offsets_json, features_json, target_fps, device_preset, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, '', '', '', '{}', '{}', 0, '', ?, ?)");
            $insProf->execute([$keyRow['id'], $resolvedKey, $loaderId, ($deviceModel ?: 'New') . ' Loader (' . $loaderId . ')', $pkg, $now, $now]);
            $loaderProfile = fbox_get_loader_profile($resolvedKey, $loaderId);
        }
    }

    // Prevent simultaneous multi-device usage per (license_key, loader_id, hwid)
    if ($maxDevices === 1) {
        $pdo->prepare("DELETE FROM active_sessions WHERE (license_key = ? AND loader_id = ?) OR (hwid = ? AND loader_id = ?)")
            ->execute([$resolvedKey, $loaderId, $hwid, $loaderId]);
    } else {
        $pdo->prepare("DELETE FROM active_sessions WHERE hwid = ? AND loader_id = ?")->execute([$hwid, $loaderId]);
    }

    $insSess = $pdo->prepare("INSERT INTO active_sessions
        (session_token, rolling_token, license_key, hwid, loader_id, device_model, package_name, active_game, ip_address, started_at, last_heartbeat_at, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, '', ?, ?, ?, ?)");
    $insSess->execute([$sessionToken, $rollingToken, $resolvedKey, $hwid, $loaderId, $deviceModel, $pkg, $clientIp, $now, $now, $leaseExpires]);

    $plainPayload = fbox_build_encrypted_runtime_json($settings, $nonce, $hwid, $sessionToken, $rollingToken, $keyRow, $loaderProfile);
    $keySeed      = $sessionToken . '|' . ($nonce !== '' ? ($nonce . '|') : '') . $secret;
    $enc          = fbox_encrypt_sdk_payload($plainPayload, $keySeed);

    // Nonce-bound server HMAC signature prevents replay attacks and fake server responses
    $sigString = 'OK|' . ($nonce !== '' ? ($nonce . '|') : '') . $sessionToken . '|' . $rollingToken . '|' . $hwid . '|' . $leaseExpires . '|' . $enc['encrypted_payload'];
    $serverSig = fbox_compute_hmac_hex($sigString, $secret);
    $rsaSig    = fbox_sign_rsa_sha256($sigString);

    fbox_log_security_event('AUTH_SUCCESS', 'info', $hwid, $resolvedKey, $deviceModel, 'Session + rolling token issued');

    echo json_encode([
        'status'            => 'OK',
        'is_banned'         => false,
        'message'           => 'Authenticated • Expires ' . date('d M Y H:i', $expiresAt),
        'nonce'             => $nonce,
        'session_token'     => $sessionToken,
        'rolling_token'     => $rollingToken,
        'lease_expires_at'  => $leaseExpires,
        'key_expires_at'    => $expiresAt,
        'encrypted_payload' => $enc['encrypted_payload'],
        'payload_iv'        => $enc['payload_iv'],
        'signature'         => $serverSig,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------------------------------------------------------------------------
// 5. Rolling Token Validation & Heartbeat (token_validation.php / ?action=heartbeat)
// ---------------------------------------------------------------------------
if ($action === 'heartbeat' || $action === 'token_validation') {
    $hwid         = trim((string)($body['hwid'] ?? ''));
    $sessionToken = trim((string)($body['session_token'] ?? ''));
    $rollingToken = trim((string)($body['rolling_token'] ?? ''));
    $loaderId     = trim((string)($body['loader_id'] ?? 'default'));
    if ($loaderId === '') $loaderId = 'default';
    $licenseKey   = trim((string)($body['license_key'] ?? ''));
    $pkg          = trim((string)($body['package_name'] ?? ''));
    $activeGame   = trim((string)($body['active_game'] ?? ''));
    $apkSha       = strtoupper(str_replace(':', '', trim((string)($body['apk_sha256'] ?? ''))));
    $deviceModel  = trim((string)($body['device_model'] ?? 'Android Device'));
    $nonce        = trim((string)($body['nonce'] ?? ''));
    $ts           = (int)($body['timestamp'] ?? 0);
    $reqSig       = trim((string)($body['signature'] ?? ''));
    $now          = time();

    // Step A: Check if HWID or IP was banned while in-game
    $banRow = fbox_find_active_ban($hwid, $clientIp);
    if ($banRow !== null) {
        $sig = fbox_compute_hmac_hex('DEVICE_BANNED|' . $nonce . '|' . $hwid, $secret);
        echo json_encode([
            'status'    => 'DEVICE_BANNED',
            'is_banned' => true,
            'terminate' => true,
            'nonce'     => $nonce,
            'signature' => $sig,
            'message'   => 'Device/IP banned by Admin (' . $banRow['ban_reason'] . '). Session terminated.',
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Step B: Continuous APK Integrity Check during Heartbeat
    if (($settings['enforce_apk_signature'] ?? '0') === '1') {
        $expectedSha = strtoupper(str_replace(':', '', trim((string)($settings['locked_apk_sha256'] ?? ''))));
        if ($expectedSha !== '' && $apkSha !== '' && $apkSha !== 'UNSIGNED_DEV' && $apkSha !== $expectedSha) {
            if (($settings['auto_ban_on_tamper'] ?? '1') === '1') {
                fbox_ban_device_hwid($hwid, $deviceModel, $pkg, $apkSha, $licenseKey,
                    'HEARTBEAT_APK_SIGNATURE_TAMPER', "Expected $expectedSha, got $apkSha", 'Token Validation Guard');
            }
            echo json_encode([
                'status'    => 'DEVICE_BANNED',
                'is_banned' => true,
                'terminate' => true,
                'nonce'     => $nonce,
                'message'   => 'Runtime APK signature mismatch! Device blacklisted.',
            ], JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    // Step C: Check Maintenance Mode
    if (($settings['maintenance_mode'] ?? '0') === '1') {
        echo json_encode([
            'status'           => 'MAINTENANCE',
            'is_banned'        => false,
            'maintenance_mode' => true,
            'terminate'        => true,
            'nonce'            => $nonce,
            'message'          => $settings['maintenance_message'] ?? 'Server entered maintenance mode.',
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Step D: Validate Active Session & Rolling Token Rotation
    $nextRollingToken = bin2hex(random_bytes(24));
    $leaseExpires     = $now + FBOX_SESSION_LEASE_TTL;

    if ($sessionToken !== '') {
        $sessStmt = $pdo->prepare("SELECT * FROM active_sessions WHERE session_token = ?");
        $sessStmt->execute([$sessionToken]);
        $sessRow = $sessStmt->fetch();

        if ($sessRow === false) {
            if (($settings['require_login_gate'] ?? '1') === '1') {
                echo json_encode([
                    'status'    => 'SESSION_EXPIRED',
                    'is_banned' => false,
                    'terminate' => true,
                    'nonce'     => $nonce,
                    'message'   => 'Session terminated or logged in from another device.',
                ], JSON_UNESCAPED_SLASHES);
                exit;
            }
        } else {
            // Verify HWID matches the session's bound HWID
            if ($sessRow['hwid'] !== '' && $hwid !== '' && $sessRow['hwid'] !== $hwid) {
                fbox_log_security_event('SESSION_HIJACK_BLOCKED', 'critical', $hwid, $licenseKey, $deviceModel,
                    'Session token used from different HWID');
                $pdo->prepare("DELETE FROM active_sessions WHERE id = ?")->execute([$sessRow['id']]);
                echo json_encode([
                    'status'    => 'SESSION_EXPIRED',
                    'is_banned' => false,
                    'terminate' => true,
                    'nonce'     => $nonce,
                    'message'   => 'Session HWID mismatch detected! Session terminated.',
                ], JSON_UNESCAPED_SLASHES);
                exit;
            }

            // Verify Rolling Token if client presented one and DB has one
            if ($rollingToken !== '' && !empty($sessRow['rolling_token']) && !hash_equals((string)$sessRow['rolling_token'], $rollingToken)) {
                fbox_log_security_event('ROLLING_TOKEN_REPLAY', 'warning', $hwid, (string)$sessRow['license_key'], $deviceModel,
                    'Rolling token mismatch (possible session clone or replay)');
                $pdo->prepare("DELETE FROM active_sessions WHERE id = ?")->execute([$sessRow['id']]);
                echo json_encode([
                    'status'    => 'SESSION_EXPIRED',
                    'is_banned' => false,
                    'terminate' => true,
                    'nonce'     => $nonce,
                    'message'   => 'Rolling security token invalidated. Please re-authenticate.',
                ], JSON_UNESCAPED_SLASHES);
                exit;
            }

            $pdo->prepare("UPDATE active_sessions SET rolling_token = ?, last_heartbeat_at = ?, active_game = ?, ip_address = ?, expires_at = ? WHERE id = ?")
                ->execute([$nextRollingToken, $now, $activeGame, $clientIp, $leaseExpires, $sessRow['id']]);
        }
    } elseif (($settings['require_login_gate'] ?? '1') === '1' && $action === 'token_validation') {
        echo json_encode([
            'status'    => 'SESSION_REQUIRED',
            'is_banned' => false,
            'terminate' => true,
            'nonce'     => $nonce,
            'message'   => 'Active session token required.',
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Step E: Validate License Key status & expiry
    if ($licenseKey !== '') {
        $kStmt = $pdo->prepare("SELECT * FROM license_keys WHERE license_key = ?");
        $kStmt->execute([$licenseKey]);
        $kRow = $kStmt->fetch();
        if ($kRow !== false && (int)($kRow['circuit_breaker_tripped'] ?? 0) === 1) {
            echo json_encode([
                'status'    => 'CIRCUIT_BREAKER_LOCKDOWN',
                'is_banned' => false,
                'terminate' => true,
                'nonce'     => $nonce,
                'message'   => 'Safety Shield Active: Loader paused automatically to protect your game account.',
            ], JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($kRow === false || $kRow['status'] !== 'active' || ((int)$kRow['expires_at'] > 0 && $now >= (int)$kRow['expires_at'])) {
            echo json_encode([
                'status'    => 'SESSION_EXPIRED',
                'is_banned' => false,
                'terminate' => true,
                'nonce'     => $nonce,
                'message'   => 'License key expired or revoked by Admin.',
            ], JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    $seedKey      = ($sessionToken !== '' ? $sessionToken : $hwid) . '|' . ($nonce !== '' ? ($nonce . '|') : '') . $secret;
    $loaderProfile = fbox_get_loader_profile($licenseKey, $loaderId);
    $plainPayload = fbox_build_encrypted_runtime_json($settings, $nonce, $hwid, $sessionToken, $nextRollingToken, $kRow ?? null, $loaderProfile);
    $enc          = fbox_encrypt_sdk_payload($plainPayload, $seedKey);

    $sigString = 'OK|' . ($nonce !== '' ? ($nonce . '|') : '') . $sessionToken . '|' . $nextRollingToken . '|' . $hwid . '|' . $leaseExpires . '|' . $enc['encrypted_payload'];
    $serverSig = fbox_compute_hmac_hex($sigString, $secret);
    $rsaSig    = fbox_sign_rsa_sha256($sigString);

    echo json_encode([
        'status'            => 'OK',
        'is_banned'         => false,
        'terminate'         => false,
        'maintenance_mode'  => false,
        'nonce'             => $nonce,
        'rolling_token'     => $nextRollingToken,
        'lease_expires_at'  => $leaseExpires,
        'encrypted_payload' => $enc['encrypted_payload'],
        'payload_iv'        => $enc['payload_iv'],
        'signature'         => $serverSig,
        'rsa_signature'     => $rsaSig,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode(['status' => 'UNKNOWN_ACTION', 'message' => 'Unsupported action']);
