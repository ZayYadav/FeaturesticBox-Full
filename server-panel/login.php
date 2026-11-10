<?php
/**
 * Dedicated Login & HWID Authentication Endpoint (login.php)
 * Validates user credentials / license key, HWID binding, IP & HWID ban state,
 * APK signature hash, and returns a Challenge-Response Nonce-Bound AES-256-CBC payload.
 */
declare(strict_types=1);
$forcedAction = 'auth';
require_once __DIR__ . '/api.php';
