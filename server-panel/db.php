<?php
/**
 * FeaturesticBox Database Layer, Auto-Migration Engine & Autonomous HWID/IP Ban Manager
 */
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function fbox_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    if (FBOX_DB_DRIVER === 'mysql') {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            FBOX_MYSQL_HOST, FBOX_MYSQL_PORT, FBOX_MYSQL_DB);
        $pdo = new PDO($dsn, FBOX_MYSQL_USER, FBOX_MYSQL_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } else {
        $dir = dirname(FBOX_SQLITE_PATH);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $pdo = new PDO('sqlite:' . FBOX_SQLITE_PATH, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA foreign_keys = ON;');
    }

    fbox_init_schema($pdo);
    return $pdo;
}

function fbox_ensure_column(PDO $pdo, string $table, string $column, string $definition): void {
    try {
        if (FBOX_DB_DRIVER === 'mysql') {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$column]);
            if ($stmt->fetch() === false) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            }
        } else {
            $cols = $pdo->query("PRAGMA table_info(`$table`)")->fetchAll();
            $found = false;
            foreach ($cols as $c) {
                if (($c['name'] ?? '') === $column) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            }
        }
    } catch (Throwable $ignored) {
    }
}

function fbox_normalize_github_url(string $url): string {
    $url = trim($url);
    if ($url === '') return '';
    // e.g. https://github.com/user/repo/blob/main/lib.so -> https://raw.githubusercontent.com/user/repo/main/lib.so
    if (preg_match('#^https?://github\.com/([^/]+)/([^/]+)/blob/(.+)$#i', $url, $m)) {
        return 'https://raw.githubusercontent.com/' . $m[1] . '/' . $m[2] . '/' . $m[3];
    }
    // e.g. https://github.com/user/repo/raw/main/lib.so -> https://raw.githubusercontent.com/user/repo/main/lib.so
    if (preg_match('#^https?://github\.com/([^/]+)/([^/]+)/raw/(.+)$#i', $url, $m)) {
        return 'https://raw.githubusercontent.com/' . $m[1] . '/' . $m[2] . '/' . $m[3];
    }
    return $url;
}

function fbox_init_schema(PDO $pdo): void {
    $isMysql = (FBOX_DB_DRIVER === 'mysql');
    $autoInc = $isMysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        $pdo->exec("CREATE TABLE IF NOT EXISTS resellers (
        id $autoInc,
        username VARCHAR(64) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        credits INTEGER NOT NULL DEFAULT 0,
        label VARCHAR(120) NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL
    )");

    fbox_ensure_column($pdo, 'license_keys', 'created_by_reseller', "VARCHAR(64) NOT NULL DEFAULT ''");
    fbox_ensure_column($pdo, 'license_keys', 'buyer_can_unban', "INTEGER NOT NULL DEFAULT 1");
    fbox_ensure_column($pdo, 'license_keys', 'buyer_can_toggle_autoban', "INTEGER NOT NULL DEFAULT 1");
    fbox_ensure_column($pdo, 'license_keys', 'buyer_autoban_enabled', "INTEGER NOT NULL DEFAULT 1");
    fbox_ensure_column($pdo, 'license_keys', 'hwid_reset_cooldown_hours', "INTEGER NOT NULL DEFAULT 24");
    fbox_ensure_column($pdo, 'license_keys', 'last_hwid_reset_at', "INTEGER NOT NULL DEFAULT 0");
    fbox_ensure_column($pdo, 'license_keys', 'watermark_mode', "VARCHAR(32) NOT NULL DEFAULT 'inherit'");
    fbox_ensure_column($pdo, 'license_keys', 'custom_watermark_text', "VARCHAR(128) NOT NULL DEFAULT ''");
    fbox_ensure_column($pdo, 'license_keys', 'ban_wave_breaker_enabled', "INTEGER NOT NULL DEFAULT 0");
    fbox_ensure_column($pdo, 'license_keys', 'ban_wave_threshold', "INTEGER NOT NULL DEFAULT 5");
    fbox_ensure_column($pdo, 'license_keys', 'circuit_breaker_tripped', "INTEGER NOT NULL DEFAULT 0");
    fbox_ensure_column($pdo, 'license_keys', 'telemetry_silencer_enabled', "INTEGER NOT NULL DEFAULT 1");
    fbox_ensure_column($pdo, 'license_keys', 'ram_diet_enabled', "INTEGER NOT NULL DEFAULT 1");
    fbox_ensure_column($pdo, 'license_keys', 'bound_apks_json', "TEXT NOT NULL DEFAULT '[]'");
    fbox_ensure_column($pdo, 'license_keys', 'lock_loader_apk_signature', "INTEGER NOT NULL DEFAULT 1");
    fbox_ensure_column($pdo, 'license_keys', 'honeypot_on_tamper', "INTEGER NOT NULL DEFAULT 0");
    fbox_ensure_column($pdo, 'license_keys', 'allowed_countries', "VARCHAR(128) NOT NULL DEFAULT 'ALL'");
    fbox_ensure_column($pdo, 'license_keys', 'plan_tier', "VARCHAR(32) NOT NULL DEFAULT 'enterprise'");
    fbox_ensure_column($pdo, 'license_keys', 'buyer_entitlements_json', "TEXT NOT NULL DEFAULT '{}'");
    fbox_ensure_column($pdo, 'license_keys', 'buyer_can_roll_key', "INTEGER NOT NULL DEFAULT 1");
    fbox_ensure_column($pdo, 'license_keys', 'key_roll_count', "INTEGER NOT NULL DEFAULT 0");
    fbox_ensure_column($pdo, 'license_keys', 'last_key_roll_at', "INTEGER NOT NULL DEFAULT 0");
    fbox_ensure_column($pdo, 'license_keys', 'ram_only_so_enabled', "INTEGER NOT NULL DEFAULT 1");
    fbox_ensure_column($pdo, 'license_keys', 'anogs_maps_cloak_enabled', "INTEGER NOT NULL DEFAULT 1");
    fbox_ensure_column($pdo, 'license_keys', 'anogs_crc_shield_enabled', "INTEGER NOT NULL DEFAULT 1");
    fbox_ensure_column($pdo, 'license_keys', 'cloak_vpn_interface', "INTEGER NOT NULL DEFAULT 1");
    fbox_ensure_column($pdo, 'license_keys', 'daily_reset_date', "VARCHAR(12) NOT NULL DEFAULT ''");
    fbox_ensure_column($pdo, 'license_keys', 'daily_reset_count', "INTEGER NOT NULL DEFAULT 0");
    fbox_ensure_column($pdo, 'resellers', 'allowed_plan_tier', "VARCHAR(32) NOT NULL DEFAULT 'pro'");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id $autoInc,
        username VARCHAR(64) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        updated_at INTEGER NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS panel_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS license_keys (
        id $autoInc,
        license_key VARCHAR(120) NOT NULL UNIQUE,
        username VARCHAR(64) NOT NULL DEFAULT '',
        password_hash VARCHAR(255) NOT NULL DEFAULT '',
        buyer_username VARCHAR(64) NOT NULL DEFAULT '',
        buyer_password_hash VARCHAR(255) NOT NULL DEFAULT '',
        custom_plugin_url_64 TEXT NOT NULL DEFAULT '',
        custom_plugin_url_32 TEXT NOT NULL DEFAULT '',
        custom_plugin_sha256 VARCHAR(64) NOT NULL DEFAULT '',
        custom_offsets_json TEXT NOT NULL DEFAULT '{}',
        custom_target_fps INTEGER NOT NULL DEFAULT 0,
        custom_device_preset VARCHAR(64) NOT NULL DEFAULT '',
        max_loader_slots INTEGER NOT NULL DEFAULT 1,
        label VARCHAR(120) NOT NULL DEFAULT '',
        duration_days INTEGER NOT NULL DEFAULT 30,
        max_devices INTEGER NOT NULL DEFAULT 0,
        bound_hwids_json TEXT NOT NULL DEFAULT '[]',
        status VARCHAR(32) NOT NULL DEFAULT 'active',
        created_at INTEGER NOT NULL,
        activated_at INTEGER NOT NULL DEFAULT 0,
        expires_at INTEGER NOT NULL DEFAULT 0,
        last_login_at INTEGER NOT NULL DEFAULT 0,
        last_ip VARCHAR(64) NOT NULL DEFAULT '',
        last_device_model VARCHAR(128) NOT NULL DEFAULT '',
        total_logins INTEGER NOT NULL DEFAULT 0
    )");

    // Ensure seamless auto-migration for existing databases
    fbox_ensure_column($pdo, 'license_keys', 'username', "VARCHAR(64) NOT NULL DEFAULT ''");
    fbox_ensure_column($pdo, 'license_keys', 'password_hash', "VARCHAR(255) NOT NULL DEFAULT ''");
    fbox_ensure_column($pdo, 'license_keys', 'buyer_username', "VARCHAR(64) NOT NULL DEFAULT ''");
    fbox_ensure_column($pdo, 'license_keys', 'buyer_password_hash', "VARCHAR(255) NOT NULL DEFAULT ''");
    fbox_ensure_column($pdo, 'license_keys', 'custom_plugin_url_64', "TEXT NOT NULL DEFAULT ''");
    fbox_ensure_column($pdo, 'license_keys', 'custom_plugin_url_32', "TEXT NOT NULL DEFAULT ''");
    fbox_ensure_column($pdo, 'license_keys', 'custom_plugin_sha256', "VARCHAR(64) NOT NULL DEFAULT ''");
    fbox_ensure_column($pdo, 'license_keys', 'custom_offsets_json', "TEXT NOT NULL DEFAULT '{}'");
    fbox_ensure_column($pdo, 'license_keys', 'custom_target_fps', "INTEGER NOT NULL DEFAULT 0");
    fbox_ensure_column($pdo, 'license_keys', 'custom_device_preset', "VARCHAR(64) NOT NULL DEFAULT ''");
    fbox_ensure_column($pdo, 'license_keys', 'custom_features_json', "TEXT NOT NULL DEFAULT '{}'");
    fbox_ensure_column($pdo, 'license_keys', 'max_loader_slots', "INTEGER NOT NULL DEFAULT 1");
    fbox_ensure_column($pdo, 'license_keys', 'allowed_games_json', "TEXT NOT NULL DEFAULT '["*"]'");
    fbox_ensure_column($pdo, 'license_keys', 'buyer_telegram_bot_token', "VARCHAR(128) NOT NULL DEFAULT ''");
    fbox_ensure_column($pdo, 'license_keys', 'buyer_telegram_chat_id', "VARCHAR(64) NOT NULL DEFAULT ''");
    fbox_ensure_column($pdo, 'license_keys', 'is_diagnostic_mode', "INTEGER NOT NULL DEFAULT 0");
    fbox_ensure_column($pdo, 'license_keys', 'diagnostic_expires_at', "INTEGER NOT NULL DEFAULT 0");
    fbox_ensure_column($pdo, 'license_keys', 'daily_diag_limit_minutes', "INTEGER NOT NULL DEFAULT 120");
    fbox_ensure_column($pdo, 'license_keys', 'diag_used_date', "VARCHAR(10) NOT NULL DEFAULT ''");
    fbox_ensure_column($pdo, 'license_keys', 'diag_used_minutes_today', "INTEGER NOT NULL DEFAULT 0");

    $pdo->exec("CREATE TABLE IF NOT EXISTS device_bans (
        id $autoInc,
        hwid VARCHAR(128) NOT NULL UNIQUE,
        device_model VARCHAR(128) NOT NULL DEFAULT '',
        package_name VARCHAR(128) NOT NULL DEFAULT '',
        apk_sha256 VARCHAR(128) NOT NULL DEFAULT '',
        ip_address VARCHAR(64) NOT NULL DEFAULT '',
        ban_ip INTEGER NOT NULL DEFAULT 1,
        license_key VARCHAR(120) NOT NULL DEFAULT '',
        ban_reason VARCHAR(255) NOT NULL DEFAULT 'TAMPER_DETECTED',
        tamper_details TEXT NOT NULL DEFAULT '',
        ban_source VARCHAR(64) NOT NULL DEFAULT 'SDK Auto-Shield',
        is_active INTEGER NOT NULL DEFAULT 1,
        banned_at INTEGER NOT NULL,
        unbanned_at INTEGER NOT NULL DEFAULT 0
    )");

    fbox_ensure_column($pdo, 'device_bans', 'ban_ip', "INTEGER NOT NULL DEFAULT 1");

    $pdo->exec("CREATE TABLE IF NOT EXISTS active_sessions (
        id $autoInc,
        session_token VARCHAR(128) NOT NULL UNIQUE,
        rolling_token VARCHAR(128) NOT NULL DEFAULT '',
        license_key VARCHAR(120) NOT NULL DEFAULT '',
        hwid VARCHAR(128) NOT NULL,
        device_model VARCHAR(128) NOT NULL DEFAULT '',
        package_name VARCHAR(128) NOT NULL DEFAULT '',
        active_game VARCHAR(128) NOT NULL DEFAULT '',
        ip_address VARCHAR(64) NOT NULL DEFAULT '',
        started_at INTEGER NOT NULL,
        last_heartbeat_at INTEGER NOT NULL,
        expires_at INTEGER NOT NULL
    )");

    fbox_ensure_column($pdo, 'active_sessions', 'rolling_token', "VARCHAR(128) NOT NULL DEFAULT ''");
    fbox_ensure_column($pdo, 'active_sessions', 'loader_id', "VARCHAR(64) NOT NULL DEFAULT 'default'");

    $pdo->exec("CREATE TABLE IF NOT EXISTS loader_profiles (
        id $autoInc,
        license_key_id INTEGER NOT NULL,
        license_key VARCHAR(120) NOT NULL,
        loader_id VARCHAR(64) NOT NULL,
        loader_name VARCHAR(128) NOT NULL,
        target_package VARCHAR(128) NOT NULL DEFAULT '',
        plugin_url_64 TEXT NOT NULL DEFAULT '',
        plugin_url_32 TEXT NOT NULL DEFAULT '',
        plugin_sha256 VARCHAR(64) NOT NULL DEFAULT '',
        offsets_json TEXT NOT NULL DEFAULT '{}',
        features_json TEXT NOT NULL DEFAULT '{}',
        target_fps INTEGER NOT NULL DEFAULT 0,
        device_preset VARCHAR(64) NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL,
        updated_at INTEGER NOT NULL
    )");
    fbox_ensure_column($pdo, 'loader_profiles', 'sub_api_key', "VARCHAR(120) NOT NULL DEFAULT ''");

    $pdo->exec("CREATE TABLE IF NOT EXISTS security_logs (
        id $autoInc,
        event_type VARCHAR(64) NOT NULL,
        severity VARCHAR(32) NOT NULL DEFAULT 'info',
        hwid VARCHAR(128) NOT NULL DEFAULT '',
        license_key VARCHAR(120) NOT NULL DEFAULT '',
        device_model VARCHAR(128) NOT NULL DEFAULT '',
        ip_address VARCHAR(64) NOT NULL DEFAULT '',
        details TEXT NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS crash_reports (
        id $autoInc,
        hwid VARCHAR(128) NOT NULL DEFAULT '',
        device_model VARCHAR(128) NOT NULL DEFAULT '',
        package_name VARCHAR(128) NOT NULL DEFAULT '',
        license_key VARCHAR(120) NOT NULL DEFAULT '',
        loader_id VARCHAR(64) NOT NULL DEFAULT '',
        crash_type VARCHAR(64) NOT NULL DEFAULT 'JAVA_EXCEPTION',
        stack_trace TEXT NOT NULL DEFAULT '',
        logcat_snippet TEXT NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL
    )");

    // Seed default manager if empty
     = ->query("SELECT COUNT(*) AS cnt FROM managers");
     =  ? ->fetch() : ['cnt' => 0];
    if ((int)($mRow['cnt'] ?? 0) === 0) {
        $insM = $pdo->prepare("INSERT INTO managers (username, password_hash, label, can_generate_keys, can_unban_devices, can_extend_keys, created_at) VALUES (?, ?, ?, 1, 1, 1, ?)");
        $insM->execute(['manager', password_hash('manager123', PASSWORD_BCRYPT), 'Default Support Manager', time()]);
    }

    // Seed default admin if empty
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM admins");
    $row = $stmt ? $stmt->fetch() : ['cnt' => 0];
    if ((int)($row['cnt'] ?? 0) === 0) {
        $ins = $pdo->prepare("INSERT INTO admins (username, password_hash, updated_at) VALUES (?, ?, ?)");
        $ins->execute([
            FBOX_DEFAULT_ADMIN_USER,
            password_hash(FBOX_DEFAULT_ADMIN_PASS, PASSWORD_BCRYPT),
            time()
        ]);
    }

    // Seed default SDK settings
    $defaults = [
        'maintenance_mode'          => '0',
        'maintenance_message'       => 'Server is undergoing scheduled maintenance. Please wait 15 minutes.',
        'announcement'              => 'FeaturesticBox Cloud Security Shield Active',
        'target_fps'                => '120',
        'device_preset'             => 'ROG_PHONE_8',
        'gfx_preset'                => '0',
        'streamproof_enabled'       => '1',
        'fps_hud_enabled'           => '0',
        'feature_floating_menu'     => '0',
        'feature_esp'               => '1',
        'feature_aimbot'            => '1',
        'feature_bullet_track'      => '1',
        'feature_streamproof'       => '1',
                'kill_switch_active'        => '0',
        'force_update_message'      => 'A mandatory security update is required to continue.',
        'enable_leak_watermark'     => '0',
        'reseller_price_1d'         => '1',
        'reseller_price_7d'         => '5',
        'reseller_price_15d'        => '10',
        'reseller_price_30d'        => '18',
        'reseller_price_60d'        => '32',
        'enforce_arm64_only'        => '0',
        'min_android_version'       => '9',
        'min_loader_version'        => '1',
        'update_apk_url'            => '',
        'telegram_bot_token'        => '',
        'telegram_chat_id'          => '',
        'discord_webhook_url'       => '',
        'hide_xposed'               => '1',
        'auto_ban_on_tamper'        => '1',
        'ban_ip_with_hwid'          => '1',
        'require_login_gate'        => '1',
        'enforce_apk_signature'     => '0',
        'enforce_request_hmac'      => '1',
        'block_http_proxy'          => '1',
        'block_root_magisk'         => '0',
        'block_emulator'            => '0',
        'locked_apk_sha256'         => 'E545B00E31AF8FC008566DE0B87C0B17FC467A90AEED71B00755B98698AE58BC',
        'pinned_tls_cert_sha256'    => '',
        'plugin_url_64'             => '',
        'plugin_url_32'             => '',
        'plugin_sha256'             => '',
        'dynamic_primary_endpoint'  => '',
        'dynamic_backup_endpoint'   => '',
        'shared_api_secret'         => FBOX_DEFAULT_API_SECRET,
        'runtime_params_json'       => json_encode([
            'anogs_shield_mode'    => 'rwx_cow_remap',
            'engine_load_delay_ms' => '50',
            'offset_guard_hook'    => '0x0',
            'offset_ace_worker'    => '0x0',
            'fov_default'          => '90'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    ];

    $checkStmt = $pdo->prepare("SELECT setting_value FROM panel_settings WHERE setting_key = ?");
    $insertStmt = $pdo->prepare("INSERT INTO panel_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaults as $k => $v) {
        $checkStmt->execute([$k]);
        if ($checkStmt->fetch() === false) {
            $insertStmt->execute([$k, $v]);
        }
    }

    // Seed sample test license keys with sample buyer credentials if table is empty
    $kCount = (int)($pdo->query("SELECT COUNT(*) AS cnt FROM license_keys")->fetch()['cnt'] ?? 0);
    if ($kCount === 0) {
        $now = time();
        $stmtKey = $pdo->prepare("INSERT INTO license_keys
            (license_key, username, password_hash, buyer_username, buyer_password_hash, label, duration_days, max_devices, bound_hwids_json, status, created_at, activated_at, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, '[]', 'active', ?, 0, 0)");
        $stmtKey->execute([
            'FBOX-PRO-2026-VIP01',
            'vip_user',
            password_hash('vip12345', PASSWORD_BCRYPT),
            'customer1',
            password_hash('customer123', PASSWORD_BCRYPT),
            'Default Admin Test Key (30 Days)',
            30,
            2,
            $now
        ]);
        $stmtKey->execute([
            'FBOX-TEST-1DAY-0001',
            'trial_user',
            password_hash('trial123', PASSWORD_BCRYPT),
            'buyer_trial',
            password_hash('trial123', PASSWORD_BCRYPT),
            '1-Day Trial Key',
            1,
            1,
            $now
        ]);
    }
}

function fbox_get_setting(string $key, string $default = ''): string {
    $stmt = fbox_db()->prepare("SELECT setting_value FROM panel_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row !== false ? (string)$row['setting_value'] : $default;
}

function fbox_set_setting(string $key, string $value): void {
    $pdo = fbox_db();
    $stmt = $pdo->prepare("SELECT setting_key FROM panel_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    if ($stmt->fetch() !== false) {
        $upd = $pdo->prepare("UPDATE panel_settings SET setting_value = ? WHERE setting_key = ?");
        $upd->execute([$value, $key]);
    } else {
        $ins = $pdo->prepare("INSERT INTO panel_settings (setting_key, setting_value) VALUES (?, ?)");
        $ins->execute([$key, $value]);
    }
}

function fbox_get_all_settings(): array {
    $rows = fbox_db()->query("SELECT setting_key, setting_value FROM panel_settings")->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[$r['setting_key']] = $r['setting_value'];
    }
    return $out;
}

function fbox_client_ip(): string {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }
    return $ip;
}

function fbox_log_security_event(
    string $eventType,
    string $severity,
    string $hwid,
    string $licenseKey,
    string $deviceModel,
    string $details
): void {
    try {
        $ip = fbox_client_ip();
        $stmt = fbox_db()->prepare("INSERT INTO security_logs
            (event_type, severity, hwid, license_key, device_model, ip_address, details, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$eventType, $severity, $hwid, $licenseKey, $deviceModel, $ip, $details, time()]);
    } catch (Throwable $ignored) {
    }
}

function fbox_find_active_ban(string $hwid, string $ipAddress): ?array {
    $pdo = fbox_db();
    if ($hwid !== '') {
        $stmt = $pdo->prepare("SELECT * FROM device_bans WHERE hwid = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$hwid]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return $row;
        }
    }
    if ($ipAddress !== '' && $ipAddress !== '0.0.0.0' && $ipAddress !== '127.0.0.1' && $ipAddress !== '::1') {
        $banIpSetting = fbox_get_setting('ban_ip_with_hwid', '1') === '1';
        if ($banIpSetting) {
            $stmt = $pdo->prepare("SELECT * FROM device_bans WHERE ip_address = ? AND is_active = 1 AND ban_ip = 1 LIMIT 1");
            $stmt->execute([$ipAddress]);
            $row = $stmt->fetch();
            if ($row !== false) {
                return $row;
            }
        }
    }
    return null;
}

function fbox_ban_device_hwid(
    string $hwid,
    string $deviceModel,
    string $packageName,
    string $apkSha256,
    string $licenseKey,
    string $banReason,
    string $tamperDetails,
    string $banSource = 'SDK Auto-Shield'
): void {
    if ($hwid === '') {
        return;
    }
    $pdo   = fbox_db();
    $ip    = fbox_client_ip();
    $banIp = fbox_get_setting('ban_ip_with_hwid', '1') === '1' ? 1 : 0;
    $now   = time();

    $stmt = $pdo->prepare("SELECT id FROM device_bans WHERE hwid = ?");
    $stmt->execute([$hwid]);
    if ($stmt->fetch() !== false) {
        $upd = $pdo->prepare("UPDATE device_bans SET
            device_model = ?, package_name = ?, apk_sha256 = ?, ip_address = ?, ban_ip = ?,
            license_key = ?, ban_reason = ?, tamper_details = ?, ban_source = ?,
            is_active = 1, banned_at = ?
            WHERE hwid = ?");
        $upd->execute([$deviceModel, $packageName, $apkSha256, $ip, $banIp, $licenseKey, $banReason, $tamperDetails, $banSource, $now, $hwid]);
    } else {
        $ins = $pdo->prepare("INSERT INTO device_bans
            (hwid, device_model, package_name, apk_sha256, ip_address, ban_ip, license_key, ban_reason, tamper_details, ban_source, is_active, banned_at, unbanned_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 0)");
        $ins->execute([$hwid, $deviceModel, $packageName, $apkSha256, $ip, $banIp, $licenseKey, $banReason, $tamperDetails, $banSource, $now]);
    }

    $del = $pdo->prepare("DELETE FROM active_sessions WHERE hwid = ? OR (ip_address = ? AND ip_address NOT IN ('0.0.0.0', '127.0.0.1', '::1'))");
    $del->execute([$hwid, $ip]);

    fbox_log_security_event('DEVICE_AUTO_BANNED', 'critical', $hwid, $licenseKey, $deviceModel,
        "Reason: $banReason | IP: $ip | Source: $banSource | Details: $tamperDetails");
    fbox_send_webhook_alert($hwid, $deviceModel, $ip, $licenseKey, $banReason, $tamperDetails);
}

function fbox_send_webhook_alert(string $hwid, string $deviceModel, string $ip, string $licenseKey, string $reason, string $details): void {
    try {
        $tgToken = fbox_get_setting('telegram_bot_token', '');
        $tgChat  = fbox_get_setting('telegram_chat_id', '');
        if ($tgToken !== '' && $tgChat !== '') {
            $msg = "[ALERT] <b>FeaturesticBox Tamper Alert</b>\n"
                 . "<b>Device:</b> " . htmlspecialchars($deviceModel) . "\n"
                 . "<b>HWID:</b> <code>" . htmlspecialchars(substr($hwid, 0, 24)) . "...</code>\n"
                 . "<b>IP:</b> " . htmlspecialchars($ip) . "\n"
                 . "<b>Reason:</b> <b>" . htmlspecialchars($reason) . "</b>\n"
                 . "<b>License:</b> " . htmlspecialchars($licenseKey) . "\n"
                 . "<b>Details:</b> " . htmlspecialchars($details) . "\n"
                 . "<i>Device blacklisted automatically.</i>";

            $url = "https://api.telegram.org/bot{$tgToken}/sendMessage";
            $data = json_encode(['chat_id' => $tgChat, 'text' => $msg, 'parse_mode' => 'HTML']);
            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\n",
                    'content' => $data,
                    'timeout' => 3,
                ],
            ]);
            @file_get_contents($url, false, $ctx);
        }

        $discordUrl = fbox_get_setting('discord_webhook_url', '');
        if ($discordUrl !== '' && filter_var($discordUrl, FILTER_VALIDATE_URL)) {
            $embed = [
                'title'       => '[ALERT] FeaturesticBox Anti-Crack Alert',
                'description' => "**Device:** $deviceModel\n**HWID:** `".substr($hwid, 0, 24)."...`\n**IP:** $ip\n**Reason:** **$reason**\n**License:** $licenseKey\n**Details:** $details",
                'color'       => 0xFF3B30,
                'footer'      => ['text' => 'FeaturesticBox Autonomous Shield']
            ];
            $dData = json_encode(['embeds' => [$embed]]);
            $dCtx = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\n",
                    'content' => $dData,
                    'timeout' => 3,
                ],
            ]);
            @file_get_contents($discordUrl, false, $dCtx);
        }
    } catch (Throwable $ignored) {
    }
}

function fbox_get_loader_profile(string $licenseKey, string $loaderId): ?array {
    if ($licenseKey === '' || $loaderId === '') return null;
    $pdo = fbox_db();
    $stmt = $pdo->prepare("SELECT * FROM loader_profiles WHERE license_key = ? AND loader_id = ? LIMIT 1");
    $stmt->execute([$licenseKey, $loaderId]);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

function fbox_get_buyer_loaders(int $licenseKeyId): array {
    $pdo = fbox_db();
    $stmt = $pdo->prepare("SELECT * FROM loader_profiles WHERE license_key_id = ? ORDER BY id ASC");
    $stmt->execute([$licenseKeyId]);
    return $stmt->fetchAll();
}

function fbox_send_crash_alert(
    string $hwid,
    string $deviceModel,
    string $pkg,
    string $licenseKey,
    string $loaderId,
    string $crashType,
    string $stackTrace,
    string $faultAddr = '',
    string $crashingLib = '',
    string $crashingOffset = ''
): void {
    // 1. Check if the key belongs to a Buyer who configured their own Telegram Bot
    if ($licenseKey !== '') {
        try {
            $pdo = fbox_db();
            $kStmt = $pdo->prepare("SELECT buyer_telegram_bot_token, buyer_telegram_chat_id, buyer_username, is_diagnostic_mode FROM license_keys WHERE license_key = ?");
            $kStmt->execute([$licenseKey]);
            $kRow = $kStmt->fetch();
            if ($kRow && !empty($kRow['buyer_telegram_bot_token']) && !empty($kRow['buyer_telegram_chat_id'])) {
                $bToken = (string)$kRow['buyer_telegram_bot_token'];
                $bChat  = (string)$kRow['buyer_telegram_chat_id'];
                $shortStack = substr($stackTrace, 0, 750);

                $bMsg = "<b>Featurestic Box — Buyer .so Diagnostic Report</b>\n"
                      . "----------------------------------------\n"
                      . "<b>Game:</b> <code>" . htmlspecialchars($pkg ?: 'Unknown') . "</code>\n"
                      . "<b>Loader ID:</b> <code>" . htmlspecialchars($loaderId ?: 'default') . "</code>\n"
                      . "<b>Crash Signal:</b> <b>" . htmlspecialchars($crashType) . "</b>\n";

                if ($crashingLib !== '') {
                    $bMsg .= "<b>Library:</b> <code>" . htmlspecialchars($crashingLib) . "</code>\n";
                }
                if ($crashingOffset !== '') {
                    $bMsg .= "<b>Crashing Offset:</b> <code>" . htmlspecialchars($crashingOffset) . "</code>\n";
                }
                if ($faultAddr !== '') {
                    $bMsg .= "<b>Fault Address:</b> <code>" . htmlspecialchars($faultAddr) . "</code>\n";
                }

                $bMsg .= "<b>Device:</b> " . htmlspecialchars($deviceModel) . "\n"
                      . "----------------------------------------\n"
                      . "<b>Stacktrace:</b>\n<pre>" . htmlspecialchars($shortStack) . "</pre>";

                $url = "https://api.telegram.org/bot{$bToken}/sendMessage";
                $data = json_encode(['chat_id' => $bChat, 'text' => $bMsg, 'parse_mode' => 'HTML']);
                $ctx = stream_context_create([
                    'http' => [
                        'method'  => 'POST',
                        'header'  => "Content-Type: application/json\r\n",
                        'content' => $data,
                        'timeout' => 4,
                    ],
                ]);
                @file_get_contents($url, false, $ctx);
            }
        } catch (Throwable $ignored) {
        }
    }

    try {
        $tgToken = fbox_get_setting('telegram_bot_token', '');
        $tgChat  = fbox_get_setting('telegram_chat_id', '');
        if ($tgToken !== '' && $tgChat !== '') {
            $shortStack = substr($stackTrace, 0, 800);
            $msg = "[DIAGNOSTIC] <b>FeaturesticBox Crash Diagnostic Report</b>\n"
                 . "<b>Device:</b> " . htmlspecialchars($deviceModel) . "\n"
                 . "<b>Game:</b> <code>" . htmlspecialchars($pkg ?: 'Unknown') . "</code>\n"
                 . "<b>Crash Type:</b> <b>" . htmlspecialchars($crashType) . "</b>\n"
                 . "<b>License Key:</b> " . htmlspecialchars($licenseKey ?: 'Unlicensed') . "\n"
                 . "<b>Loader ID:</b> " . htmlspecialchars($loaderId ?: 'default') . "\n"
                 . "<b>Stacktrace:</b>\n<pre>" . htmlspecialchars($shortStack) . "</pre>\n"
                 . "<i>Check Panel for full diagnostic log.</i>";

            $url = "https://api.telegram.org/bot{$tgToken}/sendMessage";
            $data = json_encode(['chat_id' => $tgChat, 'text' => $msg, 'parse_mode' => 'HTML']);
            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\n",
                    'content' => $data,
                    'timeout' => 4,
                ],
            ]);
            @file_get_contents($url, false, $ctx);
        }

        $discordUrl = fbox_get_setting('discord_webhook_url', '');
        if ($discordUrl !== '' && filter_var($discordUrl, FILTER_VALIDATE_URL)) {
            $shortStack = substr($stackTrace, 0, 1000);
            $embed = [
                'title'       => '[DIAGNOSTIC] FeaturesticBox Crash Diagnostic Report',
                'description' => "**Device:** $deviceModel
**Game:** `$pkg`
**Type:** **$crashType**
**Key:** $licenseKey
**Loader:** $loaderId
```
$shortStack
```",
                'color'       => 0xFF9500,
                'footer'      => ['text' => 'FeaturesticBox Diagnostic Telemetry']
            ];
            $dData = json_encode(['embeds' => [$embed]]);
            $dCtx = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\n",
                    'content' => $dData,
                    'timeout' => 4,
                ],
            ]);
            @file_get_contents($discordUrl, false, $dCtx);
        }
    } catch (Throwable $ignored) {
    }
}


/**
 * Automated Database Backup Engine (Zero-Loss Cloud / Telegram Snapshot)
 */
function fbox_backup_database(?string $tgBotToken = null, ?string $tgChatId = null): array {
    $backupDir = __DIR__ . '/storage/backups';
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0755, true);
    }

    $filename = 'featurestic_backup_' . date('Y-m-d_His') . (FBOX_DB_DRIVER === 'sqlite' ? '.sqlite' : '.sql');
    $destPath = $backupDir . '/' . $filename;

    if (FBOX_DB_DRIVER === 'sqlite') {
        $srcPath = __DIR__ . '/storage/' . FBOX_DB_NAME;
        if (!file_exists($srcPath)) {
            return ['status' => 'ERROR', 'message' => 'Source database file not found.'];
        }
        if (!@copy($srcPath, $destPath)) {
            return ['status' => 'ERROR', 'message' => 'Failed to copy SQLite database snapshot.'];
        }
    } else {
        $pdo = fbox_db();
        $tables = ['admins', 'panel_settings', 'license_keys', 'loader_profiles', 'resellers', 'device_bans', 'security_logs'];
        $sql = "-- Featurestic Box Database Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        foreach ($tables as $t) {
            try {
                $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    $cols = array_map(fn($c) => "`$c`", array_keys($r));
                    $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($r));
                    $sql .= "INSERT INTO `$t` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
                }
            } catch (Throwable $ignored) {}
        }
        file_put_contents($destPath, $sql);
    }

    $tgSent = false;
    $settings = fbox_get_all_settings();
    $token = $tgBotToken ?: ($settings['telegram_bot_token'] ?? '');
    $chatId = $tgChatId ?: ($settings['telegram_chat_id'] ?? '');

    if ($token !== '' && $chatId !== '' && file_exists($destPath)) {
        $cfile = new CURLFile($destPath, 'application/octet-stream', $filename);
        $ch = curl_init("https://api.telegram.org/bot{$token}/sendDocument");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id'  => $chatId,
            'document' => $cfile,
            'caption'  => 'FEATURESTIC BOX Database Snapshot • ' . date('d M Y H:i:s')
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $res = curl_exec($ch);
        curl_close($ch);
        if ($res !== false) {
            $dec = json_decode($res, true);
            $tgSent = ($dec['ok'] ?? false) === true;
        }
    }

    fbox_log_security_event('DATABASE_BACKUP', 'info', '', '', '', 'Snapshot ' . $filename . ' created' . ($tgSent ? ' and sent to Telegram' : ''));
    return [
        'status'        => 'OK',
        'file'          => $filename,
        'path'          => $destPath,
        'telegram_sent' => $tgSent,
        'message'       => 'Backup created: ' . $filename . ($tgSent ? ' (Sent to Telegram)' : '')
    ];
}


/**
 * Returns Admin-Configured Tiered Pricing for Resellers
 */
function fbox_get_reseller_price_tiers(): array {
    $s = fbox_get_all_settings();
    return [
        1  => ['label' => '1 Day Pass',           'days' => 1,  'credits' => max(1, (int)($s['reseller_price_1d'] ?? 1))],
        7  => ['label' => '7 Days Pass',          'days' => 7,  'credits' => max(1, (int)($s['reseller_price_7d'] ?? 5))],
        15 => ['label' => '15 Days Pass',         'days' => 15, 'credits' => max(1, (int)($s['reseller_price_15d'] ?? 10))],
        30 => ['label' => '1 Month Pass (30 Days)', 'days' => 30, 'credits' => max(1, (int)($s['reseller_price_30d'] ?? 18))],
        60 => ['label' => '2 Months Pass (60 Days)', 'days' => 60, 'credits' => max(1, (int)($s['reseller_price_60d'] ?? 32))],
    ];
}

/**
 * 7-Day Automatic Log Cleanup (Auto-Purge)
 * Deletes panel database logs older than 7 days to preserve high database performance,
 * while historical records remain archived permanently in Telegram.
 */
function fbox_purge_old_logs(PDO $pdo): int {
    try {
        $cutoff = time() - (7 * 86400); // 7 days retention
        $del1 = $pdo->exec("DELETE FROM security_logs WHERE created_at < $cutoff");
        $del2 = $pdo->exec("DELETE FROM crash_reports WHERE created_at < $cutoff");
        return (int)(($del1 !== false ? $del1 : 0) + ($del2 !== false ? $del2 : 0));
    } catch (Throwable $e) {
        return 0;
    }
}


/**
 * 1-Click Unban All Devices associated with a specific License Key
 */
function fbox_unban_all_for_key(string $licenseKey): int {
    if ($licenseKey === '') return 0;
    $pdo = fbox_db();
    $stmt = $pdo->prepare("UPDATE device_bans SET is_active = 0, unbanned_at = ? WHERE license_key = ? AND is_active = 1");
    $stmt->execute([time(), $licenseKey]);
    $affected = $stmt->rowCount();
    if ($affected > 0) {
        fbox_log_security_event('ALL_DEVICES_UNBANNED_FOR_KEY', 'info', '', $licenseKey, '', "$affected device(s) unbanned for key $licenseKey");
    }
    return $affected;
}


/**
 * 24/7 Automated Ban-Wave Circuit Breaker Watchman.
 * Trips automatically if >= 3 crashes or tamper bans occur on a key within 10 minutes (600s).
 */
function fbox_check_and_trip_circuit_breaker(PDO $pdo, string $licenseKey, string $triggerReason): bool {
    if ($licenseKey === '') return false;
    try {
        $stmt = $pdo->prepare("SELECT id, ban_wave_breaker_enabled, ban_wave_threshold, circuit_breaker_tripped, buyer_telegram_bot_token, buyer_telegram_chat_id FROM license_keys WHERE license_key = ? LIMIT 1");
        $stmt->execute([$licenseKey]);
        $kRow = $stmt->fetch();
        if (!$kRow || (int)($kRow['ban_wave_breaker_enabled'] ?? 0) === 0) {
            return false;
        }
        if ((int)($kRow['circuit_breaker_tripped'] ?? 0) === 1) {
            return true;
        }
        $threshold = max(2, (int)($kRow['ban_wave_threshold'] ?? 5));
        $windowStart = time() - 600;
        $cStmt = $pdo->prepare("SELECT COUNT(*) FROM crash_reports WHERE license_key = ? AND created_at >= ?");
        $cStmt->execute([$licenseKey, $windowStart]);
        $crashCount = (int)$cStmt->fetchColumn();

        $bStmt = $pdo->prepare("SELECT COUNT(*) FROM device_bans WHERE license_key = ? AND banned_at >= ?");
        $bStmt->execute([$licenseKey, $windowStart]);
        $banCount = (int)$bStmt->fetchColumn();

        $totalEvents = $crashCount + $banCount;
        if ($totalEvents >= $threshold) {
            $pdo->prepare("UPDATE license_keys SET circuit_breaker_tripped = 1 WHERE id = ?")->execute([(int)$kRow['id']]);
            $pdo->prepare("DELETE FROM active_sessions WHERE license_key = ?")->execute([$licenseKey]);
            fbox_log_security_event(
                'CIRCUIT_BREAKER_TRIPPED',
                'critical',
                '',
                $licenseKey,
                '',
                "Auto Circuit Breaker tripped ($totalEvents events in 10m: $crashCount crashes, $banCount bans). Last trigger: $triggerReason"
            );
            fbox_send_webhook_alert('CIRCUIT_BREAKER_TRIPPED', [
                'license_key' => $licenseKey,
                'reason'      => "Auto-Shield paused key after $totalEvents abnormal events in 10 mins ($triggerReason)",
                'ip'          => fbox_client_ip(),
            ]);
            return true;
        }
    } catch (Throwable $ignored) {
    }
    return false;
}


/**
 * Returns the default feature entitlements array for a given plan tier ('starter', 'pro', 'enterprise').
 */
function fbox_get_plan_preset_entitlements(string $planTier): array {
    $tier = strtolower(trim($planTier));
    if ($tier === 'starter' || $tier === 'basic') {
        return [
            'ent_cloud_so'           => 0,
            'ent_ram_only_so'        => 0,
            'ent_device_spoofer'     => 0,
            'ent_streamproof'        => 1,
            'ent_telemetry_silencer' => 1,
            'ent_ram_diet'           => 1,
            'ent_ban_wave_breaker'   => 0,
            'ent_honeypot'           => 0,
            'ent_geo_lock'           => 0,
            'ent_watermark_off'      => 0,
            'ent_crash_bot'          => 0,
        ];
    }
    if ($tier === 'pro') {
        return [
            'ent_cloud_so'           => 1,
            'ent_ram_only_so'        => 0,
            'ent_device_spoofer'     => 1,
            'ent_streamproof'        => 1,
            'ent_telemetry_silencer' => 1,
            'ent_ram_diet'           => 1,
            'ent_ban_wave_breaker'   => 1,
            'ent_honeypot'           => 0,
            'ent_geo_lock'           => 1,
            'ent_watermark_off'      => 1,
            'ent_crash_bot'          => 1,
        ];
    }
    // Default: 'enterprise' / 'vip' -> all features unlocked
    return [
        'ent_cloud_so'           => 1,
        'ent_ram_only_so'        => 1,
        'ent_device_spoofer'     => 1,
        'ent_streamproof'        => 1,
        'ent_telemetry_silencer' => 1,
        'ent_ram_diet'           => 1,
        'ent_ban_wave_breaker'   => 1,
        'ent_honeypot'           => 1,
        'ent_geo_lock'           => 1,
        'ent_watermark_off'      => 1,
        'ent_crash_bot'          => 1,
    ];
}

/**
 * Resolves the effective entitlements for a license_keys row.
 */
function fbox_get_key_entitlements(array $keyRow): array {
    $tier = (string)($keyRow['plan_tier'] ?? 'enterprise');
    $base = fbox_get_plan_preset_entitlements($tier);
    if (!empty($keyRow['buyer_entitlements_json'])) {
        $custom = json_decode((string)$keyRow['buyer_entitlements_json'], true);
        if (is_array($custom) && !empty($custom)) {
            foreach ($base as $k => $v) {
                if (isset($custom[$k])) {
                    $base[$k] = (int)$custom[$k] ? 1 : 0;
                }
            }
        }
    }
    return $base;
}


/**
 * Dispatches an instant security notice to the Buyer's private Telegram Bot (if configured on their key)
 * when their SDK API Key is rolled or a new Loader APK slot is bound.
 */
function fbox_send_buyer_bot_notice(array $keyRow, string $title, array $lines): void {
    $botToken = trim((string)($keyRow['buyer_telegram_bot_token'] ?? ''));
    $chatId   = trim((string)($keyRow['buyer_telegram_chat_id'] ?? ''));
    if ($botToken === '' || $chatId === '') {
        return;
    }
    $text = "<b>[FEATURESTIC BOX • SDK SECURITY NOTICE]</b>\n"
          . "<b>Event:</b> " . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
    foreach ($lines as $k => $v) {
        $text .= "<b>" . htmlspecialchars((string)$k, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ":</b> <code>"
               . htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>\n";
    }
    $text .= "<b>Time:</b> " . gmdate('Y-m-d H:i:s') . " UTC";
    $tgUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query([
                'chat_id'                  => $chatId,
                'text'                     => $text,
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => 'true',
            ]),
            'timeout' => 3.0,
            'ignore_errors' => true,
        ],
    ]);
    @file_get_contents($tgUrl, false, $ctx);
}
