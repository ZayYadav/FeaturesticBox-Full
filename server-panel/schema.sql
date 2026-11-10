-- FeaturesticBox Cloud Control Center — MySQL / MariaDB Schema (v3.5 Multi-Loader Studio Edition)
-- (Note: If using default SQLite mode, db.php automatically creates and auto-migrates all tables!)

CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    updated_at INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS panel_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS license_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(120) NOT NULL UNIQUE,
    username VARCHAR(64) NOT NULL DEFAULT '',
    password_hash VARCHAR(255) NOT NULL DEFAULT '',
    buyer_username VARCHAR(64) NOT NULL DEFAULT '',
    buyer_password_hash VARCHAR(255) NOT NULL DEFAULT '',
    custom_plugin_url_64 TEXT NOT NULL,
    custom_plugin_url_32 TEXT NOT NULL,
    custom_plugin_sha256 VARCHAR(64) NOT NULL DEFAULT '',
    custom_offsets_json TEXT NOT NULL,
    custom_features_json TEXT NOT NULL,
    custom_target_fps INT NOT NULL DEFAULT 0,
    custom_device_preset VARCHAR(64) NOT NULL DEFAULT '',
    decoy_mode_enabled INT NOT NULL DEFAULT 0,
    decoy_pin_code VARCHAR(32) NOT NULL DEFAULT '1337',
    ban_wave_breaker_enabled INT NOT NULL DEFAULT 1,
    circuit_breaker_tripped INT NOT NULL DEFAULT 0,
    telemetry_silencer_enabled INT NOT NULL DEFAULT 1,
    ram_diet_enabled INT NOT NULL DEFAULT 1,
    max_loader_slots INT NOT NULL DEFAULT 1,
    allowed_games_json TEXT NOT NULL,
    buyer_telegram_bot_token VARCHAR(128) NOT NULL DEFAULT '',
    buyer_telegram_chat_id VARCHAR(64) NOT NULL DEFAULT '',
    is_diagnostic_mode INT NOT NULL DEFAULT 0,
    diagnostic_expires_at INT NOT NULL DEFAULT 0,
    daily_diag_limit_minutes INT NOT NULL DEFAULT 120,
    diag_used_date VARCHAR(10) NOT NULL DEFAULT '',
    diag_used_minutes_today INT NOT NULL DEFAULT 0,
    label VARCHAR(120) NOT NULL DEFAULT '',
    duration_days INT NOT NULL DEFAULT 30,
    max_devices INT NOT NULL DEFAULT 0,
    bound_hwids_json TEXT NOT NULL,
    bound_apks_json TEXT NOT NULL,
    lock_loader_apk_signature INT NOT NULL DEFAULT 1,
    ban_wave_breaker_enabled INT NOT NULL DEFAULT 0,
    ban_wave_threshold INT NOT NULL DEFAULT 5,
    circuit_breaker_tripped INT NOT NULL DEFAULT 0,
    honeypot_on_tamper INT NOT NULL DEFAULT 0,
    allowed_countries VARCHAR(128) NOT NULL DEFAULT 'ALL',
    plan_tier VARCHAR(32) NOT NULL DEFAULT 'enterprise',
    buyer_entitlements_json TEXT NOT NULL,
    buyer_can_roll_key INT NOT NULL DEFAULT 1,
    key_roll_count INT NOT NULL DEFAULT 0,
    last_key_roll_at INT NOT NULL DEFAULT 0,
    ram_only_so_enabled INT NOT NULL DEFAULT 1,
    anogs_maps_cloak_enabled INT NOT NULL DEFAULT 1,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_at INT NOT NULL,
    activated_at INT NOT NULL DEFAULT 0,
    expires_at INT NOT NULL DEFAULT 0,
    last_login_at INT NOT NULL DEFAULT 0,
    last_ip VARCHAR(64) NOT NULL DEFAULT '',
    last_device_model VARCHAR(128) NOT NULL DEFAULT '',
    total_logins INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loader_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key_id INT NOT NULL,
    license_key VARCHAR(120) NOT NULL,
    loader_id VARCHAR(64) NOT NULL,
    loader_name VARCHAR(128) NOT NULL,
    sub_api_key VARCHAR(120) NOT NULL DEFAULT '',
    target_package VARCHAR(128) NOT NULL DEFAULT '',
    plugin_url_64 TEXT NOT NULL,
    plugin_url_32 TEXT NOT NULL,
    plugin_sha256 VARCHAR(64) NOT NULL DEFAULT '',
    offsets_json TEXT NOT NULL,
    features_json TEXT NOT NULL,
    target_fps INT NOT NULL DEFAULT 0,
    device_preset VARCHAR(64) NOT NULL DEFAULT '',
    created_at INT NOT NULL,
    updated_at INT NOT NULL,
    UNIQUE KEY uq_lic_loader (license_key, loader_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS device_bans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hwid VARCHAR(128) NOT NULL UNIQUE,
    device_model VARCHAR(128) NOT NULL DEFAULT '',
    package_name VARCHAR(128) NOT NULL DEFAULT '',
    apk_sha256 VARCHAR(128) NOT NULL DEFAULT '',
    ip_address VARCHAR(64) NOT NULL DEFAULT '',
    ban_ip INT NOT NULL DEFAULT 1,
    license_key VARCHAR(120) NOT NULL DEFAULT '',
    ban_reason VARCHAR(255) NOT NULL DEFAULT 'TAMPER_DETECTED',
    tamper_details TEXT NOT NULL,
    ban_source VARCHAR(64) NOT NULL DEFAULT 'SDK Auto-Shield',
    is_active INT NOT NULL DEFAULT 1,
    banned_at INT NOT NULL,
    unbanned_at INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS active_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_token VARCHAR(128) NOT NULL UNIQUE,
    rolling_token VARCHAR(128) NOT NULL DEFAULT '',
    license_key VARCHAR(120) NOT NULL DEFAULT '',
    hwid VARCHAR(128) NOT NULL,
    loader_id VARCHAR(64) NOT NULL DEFAULT 'default',
    device_model VARCHAR(128) NOT NULL DEFAULT '',
    package_name VARCHAR(128) NOT NULL DEFAULT '',
    active_game VARCHAR(128) NOT NULL DEFAULT '',
    ip_address VARCHAR(64) NOT NULL DEFAULT '',
    started_at INT NOT NULL,
    last_heartbeat_at INT NOT NULL,
    expires_at INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(64) NOT NULL,
    severity VARCHAR(32) NOT NULL DEFAULT 'info',
    hwid VARCHAR(128) NOT NULL DEFAULT '',
    license_key VARCHAR(120) NOT NULL DEFAULT '',
    device_model VARCHAR(128) NOT NULL DEFAULT '',
    ip_address VARCHAR(64) NOT NULL DEFAULT '',
    details TEXT NOT NULL,
    created_at INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS crash_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hwid VARCHAR(128) NOT NULL DEFAULT '',
    device_model VARCHAR(128) NOT NULL DEFAULT '',
    package_name VARCHAR(128) NOT NULL DEFAULT '',
    license_key VARCHAR(120) NOT NULL DEFAULT '',
    loader_id VARCHAR(64) NOT NULL DEFAULT '',
    crash_type VARCHAR(64) NOT NULL DEFAULT 'JAVA_EXCEPTION',
    stack_trace TEXT NOT NULL,
    logcat_snippet TEXT NOT NULL,
    created_at INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS resellers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    credits INT NOT NULL DEFAULT 0,
    allowed_plan_tier VARCHAR(32) NOT NULL DEFAULT 'pro',
    label VARCHAR(120) NOT NULL DEFAULT '',
    created_at INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS managers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    label VARCHAR(120) NOT NULL DEFAULT '',
    can_generate_keys INT NOT NULL DEFAULT 1,
    can_unban_devices INT NOT NULL DEFAULT 1,
    can_extend_keys INT NOT NULL DEFAULT 1,
    created_at INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
