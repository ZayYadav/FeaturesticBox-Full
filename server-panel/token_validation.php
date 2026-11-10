<?php
/**
 * Dedicated Rolling Token & Heartbeat Validation Endpoint (token_validation.php)
 * Authenticates rolling tokens sent via heartbeat, rotates one-time rolling tokens,
 * and continuously verifies APK integrity signatures.
 */
declare(strict_types=1);
$forcedAction = 'token_validation';
require_once __DIR__ . '/api.php';
