<?php
/**
 * Dedicated HWID & Linked IP Blacklist Check Endpoint (ban_check.php)
 * Confirms the device's HWID and IP address are not blacklisted before allowing connection.
 */
declare(strict_types=1);
$forcedAction = 'ban_check';
require_once __DIR__ . '/api.php';
