<?php
/**
 * FeaturesticBox Cloud Control Panel — Server Configuration
 * Supports zero-config SQLite3 out of the box, or MySQL/MariaDB for high-scale production.
 */
declare(strict_types=1);

// Database Driver: 'sqlite' (auto-created in storage/featurestic_panel.sqlite) or 'mysql'
define('FBOX_DB_DRIVER', getenv('FBOX_DB_DRIVER') ?: 'sqlite');
define('FBOX_SQLITE_PATH', __DIR__ . '/storage/featurestic_panel.sqlite');

// MySQL Configuration (only used when FBOX_DB_DRIVER === 'mysql')
define('FBOX_MYSQL_HOST', getenv('FBOX_MYSQL_HOST') ?: '127.0.0.1');
define('FBOX_MYSQL_PORT', getenv('FBOX_MYSQL_PORT') ?: '3306');
define('FBOX_MYSQL_DB',   getenv('FBOX_MYSQL_DB')   ?: 'featurestic_panel');
define('FBOX_MYSQL_USER', getenv('FBOX_MYSQL_USER') ?: 'root');
define('FBOX_MYSQL_PASS', getenv('FBOX_MYSQL_PASS') ?: '');

// Default Shared Secret (can also be rotated live inside the Panel Security tab)
define('FBOX_DEFAULT_API_SECRET', 'FBOX_IOS_PANEL_DEFAULT_SECRET_2026');

// Session Lease TTL in seconds (renewed automatically on every SDK heartbeat)
define('FBOX_SESSION_LEASE_TTL', 3600);

// Default initial Admin credentials (change immediately in Panel -> Security Settings)
define('FBOX_DEFAULT_ADMIN_USER', 'admin');
define('FBOX_DEFAULT_ADMIN_PASS', 'admin123');
