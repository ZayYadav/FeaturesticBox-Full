<?php
/**
 * FEATURESTIC BOX — Cloud Security Center & SDK Control Hub (v4.0 Professional Edition)
 * Apple iOS 18 & macOS Sequoia Cupertino Typography & SVG Vector Architecture.
 * Strictly 0% Emojis — 100% Crisp Vector Geometry.
 *
 * Capabilities:
 *   1. Administrator Console: Master Key Generator, Allowed Games Rights, Multi-Loader Slots, HWID Blacklist & Audit Logs.
 *   2. Buyer Delivery Pack: 1-Click Client Credentials & Integration Pack Generator.
 *   3. Customer / Buyer Portal: Per-Key Tenant Isolation, 1-Click HWID Reset, Multi-Loader Profiles, GitHub Raw .so & Offsets.
 */
declare(strict_types=1);
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crypto.php';

$pdo   = fbox_db();
fbox_purge_old_logs($pdo);
$flash = '';
$flashType = 'success';

// Vector SVG Icon Renderer (Replaces all emojis with crisp Apple-style geometry)
function fbox_svg(string $name, int $size = 16, string $color = 'currentColor'): string {
    $s = $size;
    switch ($name) {
        case 'key':
            return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon"><path d="M21 2l-2 2m-1.5 1.5L16 7l-1.5-1.5L13 7l-1.5-1.5L10 7l-1.5-1.5L7 7l-1.5-1.5L4 7a5 5 0 1 0 7 7l2-2 1.5 1.5L16 12l1.5 1.5L19 12l2-2-3-3z"/></svg>';
        case 'shield':
            return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>';
        case 'device':
            return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon"><rect x="5" y="2" width="14" height="20" rx="3" ry="3"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>';
        case 'game':
            return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon"><line x1="6" y1="12" x2="10" y2="12"/><line x1="8" y1="10" x2="8" y2="14"/><line x1="15" y1="13" x2="15.01" y2="13"/><line x1="18" y1="11" x2="18.01" y2="11"/><rect x="2" y="6" width="20" height="12" rx="4"/></svg>';
        case 'bolt':
            return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';
        case 'copy':
            return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
        case 'check':
            return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="svg-icon"><polyline points="20 6 9 17 4 12"/></svg>';
        case 'ban':
            return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>';
        case 'globe':
            return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>';
        case 'user':
            return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
        case 'gear':
            return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';
        case 'clock':
            return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
        case 'refresh':
            return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>';
        case 'plus':
            return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="svg-icon"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
        case 'trash':
            return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>';
        case 'share':
            return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>';
        case 'edit':
            return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
        case 'eye':
            return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
        case 'terminal':
            return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>';
        default:
            return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" class="svg-icon"><circle cx="12" cy="12" r="9"/></svg>';
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'your-domain.com';
$baseDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$autoApiUrl = $scheme . '://' . $host . $baseDir . '/api.php';
$panelUrl   = $scheme . '://' . $host . $baseDir . '/index.php';

// Handle Login (Unified for Administrator and Customer / Buyer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'login') {
    $user = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');

    // 1. Check Administrator
    $adminStmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $adminStmt->execute([$user]);
    $admin = $adminStmt->fetch();
    if ($admin !== false && password_verify($pass, (string)$admin['password_hash'])) {
        $_SESSION['fbox_role']         = 'admin';
        $_SESSION['fbox_admin_id']     = (int)$admin['id'];
        $_SESSION['fbox_user_display'] = (string)$admin['username'];
        $_SESSION['fbox_csrf']         = bin2hex(random_bytes(16));
        header('Location: index.php');
        exit;
    }

        // 1.2 Check Sub-Manager Staff
    try {
         = ->prepare("SELECT * FROM managers WHERE username = ? LIMIT 1");
        ->execute([]);
         = ->fetch();
        if ( !== false && password_verify(, (string)['password_hash'])) {
            ['fbox_role']         = 'manager';
            ['fbox_manager_id']   = (int)['id'];
            ['fbox_user_display'] = (string)['username'];
            ['fbox_csrf']         = bin2hex(random_bytes(16));
            header('Location: index.php');
            exit;
        }
    } catch (Throwable ) {}

    // 1.5 Check Reseller / Agent
    try {
        $resellerStmt = $pdo->prepare("SELECT * FROM resellers WHERE username = ? LIMIT 1");
        $resellerStmt->execute([$user]);
        $reseller = $resellerStmt->fetch();
        if ($reseller !== false && password_verify($pass, (string)$reseller['password_hash'])) {
            $_SESSION['fbox_role']         = 'reseller';
            $_SESSION['fbox_reseller_id'] = (int)$reseller['id'];
            $_SESSION['fbox_user_display'] = (string)$reseller['username'];
            $_SESSION['fbox_csrf']         = bin2hex(random_bytes(16));
            header('Location: index.php');
            exit;
        }
    } catch (Throwable $ignored) {}

    // 2. Check Customer / Buyer
    $buyerStmt = $pdo->prepare("SELECT * FROM license_keys WHERE buyer_username = ? OR license_key = ? OR username = ? LIMIT 1");
    $buyerStmt->execute([$user, $user, $user]);
    $buyer = $buyerStmt->fetch();
    if ($buyer !== false) {
        $buyerPassHash = (string)($buyer['buyer_password_hash'] ?: $buyer['password_hash']);
        $passValid = false;
        if ($buyerPassHash !== '') {
            $passValid = password_verify($pass, $buyerPassHash);
        } else {
            $passValid = ($pass === '123456' || $pass === $buyer['license_key'] || $pass === 'customer123');
        }

        if ($passValid) {
            $_SESSION['fbox_role']         = 'buyer';
            $_SESSION['fbox_buyer_key_id'] = (int)$buyer['id'];
            $_SESSION['fbox_user_display'] = (string)($buyer['buyer_username'] ?: ($buyer['label'] ?: $buyer['license_key']));
            $_SESSION['fbox_csrf']         = bin2hex(random_bytes(16));
            header('Location: index.php');
            exit;
        }
    }

    $flash = 'Invalid credentials. Please verify your username, license key, or password.';
    $flashType = 'error';
}

$role       = $_SESSION['fbox_role'] ?? '';
$isLoggedIn = ($role === 'admin' || $role === 'buyer' || $role === 'reseller');
if ($isLoggedIn && empty($_SESSION['fbox_csrf'])) {
    $_SESSION['fbox_csrf'] = bin2hex(random_bytes(16));
}

// ---------------------------------------------------------------------------
// 1. Handle Customer / Buyer Portal Actions
// ---------------------------------------------------------------------------
if ($role === 'buyer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals((string)$_SESSION['fbox_csrf'], $csrf)) {
        $flash = 'Session expired. Please refresh the page.';
        $flashType = 'error';
    } else {
        $keyId = (int)$_SESSION['fbox_buyer_key_id'];
        $act   = (string)($_POST['form_action'] ?? '');

        // Buyer: 1-Click Roll / Regenerate Master SDK API Key (Cloud API Key Style — Preserves Exact Expiry Date!)
        if ($act === 'buyer_roll_sdk_key') {
            $bkStmt = $pdo->prepare("SELECT * FROM license_keys WHERE id = ?");
            $bkStmt->execute([$keyId]);
            $bKey = $bkStmt->fetch();

            if ((int)($bKey['buyer_can_roll_key'] ?? 1) !== 1) {
                $flash = 'Self-service SDK API Key regeneration is not enabled on your plan. Contact Admin to roll your key.';
                $flashType = 'error';
            } else {
                $oldKey = (string)$bKey['license_key'];
                $newKey = 'FBOX-VIP-' . strtoupper(bin2hex(random_bytes(3))) . '-' . strtoupper(bin2hex(random_bytes(3)));
                $now    = time();

                $pdo->prepare("UPDATE license_keys SET license_key = ?, bound_apks_json = '[]', bound_hwids_json = '[]', key_roll_count = key_roll_count + 1, last_key_roll_at = ? WHERE id = ?")
                    ->execute([$newKey, $now, $keyId]);
                $pdo->prepare("UPDATE loader_profiles SET license_key = ? WHERE license_key_id = ?")
                    ->execute([$newKey, $keyId]);
                $pdo->prepare("DELETE FROM active_sessions WHERE license_key = ?")
                    ->execute([$oldKey]);

                fbox_log_security_event('BUYER_SDK_KEY_ROLLED', 'warning', '', $newKey, '', "Buyer rolled Master SDK API Key from $oldKey to $newKey (Expiry preserved)");
                fbox_send_buyer_bot_notice($bKey, 'Master SDK API Key Rolled (Old Key Revoked)', [
                    'Old Revoked Key' => $oldKey,
                    'New Active Key'  => $newKey,
                    'Expiry Preserved'=> (int)$bKey['expires_at'] > 0 ? date('d M Y, H:i', (int)$bKey['expires_at']) : ((int)$bKey['duration_days'] . ' Days'),
                ]);
                $flash = "SDK API Key rolled successfully! Old key ($oldKey) is now permanently deactivated. Your new SDK Key is $newKey (Remaining subscription expiry unchanged).";
            }
        }

        // Buyer: 1-Click Roll Per-Loader Sub-API Key (Revokes Old Loader Sub-Key Immediately)
        elseif ($act === 'buyer_roll_loader_subkey') {
            $bkStmt = $pdo->prepare("SELECT * FROM license_keys WHERE id = ?");
            $bkStmt->execute([$keyId]);
            $bKey = $bkStmt->fetch();

            if ((int)($bKey['buyer_can_roll_key'] ?? 1) !== 1) {
                $flash = 'API Key rolling is not enabled on your plan. Contact Admin.';
                $flashType = 'error';
            } else {
                $profId = (int)($_POST['profile_id'] ?? 0);
                $newSubKey = 'FBOX-SUB-' . strtoupper(bin2hex(random_bytes(3))) . '-' . strtoupper(bin2hex(random_bytes(3)));
                $pdo->prepare("UPDATE loader_profiles SET sub_api_key = ?, updated_at = ? WHERE id = ? AND license_key_id = ?")
                    ->execute([$newSubKey, time(), $profId, $keyId]);
                $pdo->prepare("DELETE FROM active_sessions WHERE license_key = ?")
                    ->execute([(string)$bKey['license_key']]);
                fbox_send_buyer_bot_notice($bKey, 'Loader Slot Sub-API Key Rotated', [
                    'Profile ID'  => (string)$profId,
                    'New Sub-Key' => $newSubKey,
                ]);
                $flash = "Loader Sub-API Key rotated to $newSubKey! Any old Loader build using the previous sub-key is now disconnected.";
            }
        }

        // Buyer: Reset Bound Loader APK & HWID (Upgraded: 5 Developer Self-Resets / Day Allowance)
        elseif ($act === 'buyer_reset_hwid') {
            $bkStmt = $pdo->prepare("SELECT * FROM license_keys WHERE id = ?");
            $bkStmt->execute([$keyId]);
            $bKey = $bkStmt->fetch();

            $todayStr   = gmdate('Y-m-d');
            $usedToday  = (($bKey['daily_reset_date'] ?? '') === $todayStr) ? (int)($bKey['daily_reset_count'] ?? 0) : 0;
            $maxPerDay  = 5;
            $now        = time();

            if ($usedToday >= $maxPerDay) {
                $flash = "Daily self-reset limit reached ($maxPerDay/day used during testing). Resets automatically at 00:00 UTC, or ask Admin for an instant reset.";
                $flashType = 'error';
            } else {
                $newUsed = $usedToday + 1;
                $pdo->prepare("UPDATE license_keys SET bound_hwids_json = '[]', bound_apks_json = '[]', last_hwid_reset_at = ?, daily_reset_date = ?, daily_reset_count = ? WHERE id = ?")
                    ->execute([$now, $todayStr, $newUsed, $keyId]);
                fbox_log_security_event('BUYER_HWID_RESET', 'info', '', (string)$bKey['license_key'], '', "Buyer reset Bound APK & HWID ($newUsed/$maxPerDay used today)");
                fbox_send_buyer_bot_notice($bKey, 'Bound Loader APK & HWID Slots Reset', [
                    'SDK Key'          => (string)$bKey['license_key'],
                    'Daily Resets Used'=> "$newUsed / $maxPerDay Today",
                ]);
                $flash = "Bound Loader APK Signature & HWID slots reset! ($newUsed/$maxPerDay daily developer resets used today).";
            }
        }

        // Buyer: Unban Device (If permitted by Admin)
        elseif ($act === 'buyer_unban_device') {
            $bkStmt = $pdo->prepare("SELECT * FROM license_keys WHERE id = ?");
            $bkStmt->execute([$keyId]);
            $bKey = $bkStmt->fetch();

            if ((int)($bKey['buyer_can_unban'] ?? 1) !== 1) {
                $flash = 'Device unban permission is not granted for your account. Please contact Admin.';
                $flashType = 'error';
            } else {
                $banId = (int)($_POST['ban_id'] ?? 0);
                $bStmt = $pdo->prepare("SELECT * FROM device_bans WHERE id = ? AND license_key = ?");
                $bStmt->execute([$banId, (string)$bKey['license_key']]);
                $bRow = $bStmt->fetch();

                if ($bRow) {
                    $pdo->prepare("UPDATE device_bans SET is_active = 0, unbanned_at = ? WHERE id = ?")->execute([time(), $banId]);
                    fbox_log_security_event('DEVICE_UNBANNED_BY_BUYER', 'info', (string)$bRow['hwid'], (string)$bKey['license_key'], (string)$bRow['device_model'], 'Buyer unbanned device from Console');
                    $flash = 'Device has been unbanned. The customer can launch the loader again.';
                } else {
                    $flash = 'Banned device record not found or does not belong to your license.';
                    $flashType = 'error';
                }
            }
        }

        // Buyer: 1-Click Unban All Devices for this Key
        elseif ($act === 'buyer_unban_all') {
            $bkStmt = $pdo->prepare("SELECT * FROM license_keys WHERE id = ?");
            $bkStmt->execute([$keyId]);
            $bKey = $bkStmt->fetch();

            if ((int)($bKey['buyer_can_unban'] ?? 1) !== 1) {
                $flash = 'Device unban permission is not granted for your account.';
                $flashType = 'error';
            } else {
                $aff = fbox_unban_all_for_key((string)$bKey['license_key']);
                $flash = "Unbanned $aff device(s) associated with your license key. All devices restored!";
            }
        }

        // Buyer: Toggle Auto-Ban Policy (If permitted by Admin)
        elseif ($act === 'buyer_toggle_autoban') {
            $bkStmt = $pdo->prepare("SELECT * FROM license_keys WHERE id = ?");
            $bkStmt->execute([$keyId]);
            $bKey = $bkStmt->fetch();

            if ((int)($bKey['buyer_can_toggle_autoban'] ?? 1) !== 1) {
                $flash = 'Auto-ban policy control is not granted for your account.';
                $flashType = 'error';
            } else {
                $current = (int)($bKey['buyer_autoban_enabled'] ?? 1);
                $newVal  = ($current === 1) ? 0 : 1;
                $pdo->prepare("UPDATE license_keys SET buyer_autoban_enabled = ? WHERE id = ?")->execute([$newVal, $keyId]);
                $flash = 'Auto-ban policy updated: Violating devices will ' . ($newVal === 1 ? 'now be automatically banned.' : 'NOT be automatically banned (Security logs only).');
            }
        }

        // Buyer: Create Dedicated Loader Profile
        elseif ($act === 'buyer_create_loader') {
            $bkStmt = $pdo->prepare("SELECT * FROM license_keys WHERE id = ?");
            $bkStmt->execute([$keyId]);
            $bKey = $bkStmt->fetch();

            $lId   = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', trim((string)($_POST['loader_id'] ?? ''))));
            $lName = trim((string)($_POST['loader_name'] ?? ''));
            $tPkg  = trim((string)($_POST['target_package'] ?? ''));
            $u64   = fbox_normalize_github_url(trim((string)($_POST['loader_plugin_url_64'] ?? '')));
            $u32   = fbox_normalize_github_url(trim((string)($_POST['loader_plugin_url_32'] ?? '')));
            $rawOff = trim((string)($_POST['loader_offsets_json'] ?? '{}'));
            $now   = time();

            if ($lId === '' || $lName === '') {
                $flash = 'Please provide a valid Loader ID and Name.';
                $flashType = 'error';
            } else {
                // Verify target package is allowed by Admin rights
                $allowedGames = json_decode((string)($bKey['allowed_games_json'] ?? '["*"]'), true) ?: ['*'];
                if (!in_array('*', $allowedGames, true) && $tPkg !== '' && !in_array($tPkg, $allowedGames, true)) {
                    $flash = "Target package '$tPkg' is not authorized by Admin for your license.";
                    $flashType = 'error';
                } else {
                    $maxSlots = (int)($bKey['max_loader_slots'] ?? 1);
                    $cntStmt  = $pdo->prepare("SELECT COUNT(*) FROM loader_profiles WHERE license_key_id = ?");
                    $cntStmt->execute([$keyId]);
                    $cnt = (int)$cntStmt->fetchColumn();

                    if ($maxSlots > 0 && $cnt >= $maxSlots) {
                        $flash = "Loader slot limit reached ($maxSlots max). Contact Admin to upgrade your slots.";
                        $flashType = 'error';
                    } else {
                        $subApiKey = 'FBOX-SUB-' . strtoupper(bin2hex(random_bytes(3))) . '-' . strtoupper(bin2hex(random_bytes(3)));
                        $ins = $pdo->prepare("INSERT INTO loader_profiles
                            (license_key_id, license_key, loader_id, loader_name, sub_api_key, target_package, plugin_url_64, plugin_url_32, plugin_sha256, offsets_json, features_json, target_fps, device_preset, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', ?, '{}', 0, '', ?, ?)");
                        try {
                            $ins->execute([$keyId, (string)$bKey['license_key'], $lId, $lName, $subApiKey, $tPkg, $u64, $u32, $rawOff, $now, $now]);
                            $flash = "Loader Profile '$lName' (ID: $lId) created! Use .setLoaderId(\"$lId\") in your Loader.";
                        } catch (Throwable $e) {
                            $flash = 'A loader with this ID already exists under your account.';
                            $flashType = 'error';
                        }
                    }
                }
            }
        }

        // Buyer: Delete Loader Profile
        elseif ($act === 'buyer_delete_loader') {
            $profId = (int)($_POST['profile_id'] ?? 0);
            $pdo->prepare("DELETE FROM loader_profiles WHERE id = ? AND license_key_id = ?")->execute([$profId, $keyId]);
            $flash = 'Loader profile deleted successfully.';
        }

        // Buyer: Save Custom OTA .so Links (GitHub Raw or Direct)
        elseif ($act === 'buyer_save_plugin') {
            $url64 = fbox_normalize_github_url(trim((string)($_POST['custom_plugin_url_64'] ?? '')));
            $url32 = fbox_normalize_github_url(trim((string)($_POST['custom_plugin_url_32'] ?? '')));
            $sha   = strtolower(trim((string)($_POST['custom_plugin_sha256'] ?? '')));

            $upd = $pdo->prepare("UPDATE license_keys SET custom_plugin_url_64 = ?, custom_plugin_url_32 = ?, custom_plugin_sha256 = ? WHERE id = ?");
            $upd->execute([$url64, $url32, $sha, $keyId]);
            $flash = 'Custom OTA .so plugin URLs updated.';
        }

        // Buyer: Save 100% Panel-Controlled SDK Features, Anti-Crack & Hardware Spoofer (Enforces Admin Plan Entitlements)
        elseif ($act === 'buyer_save_features') {
            $bkStmt = $pdo->prepare("SELECT * FROM license_keys WHERE id = ?");
            $bkStmt->execute([$keyId]);
            $bKey = $bkStmt->fetch();
            $ent  = $bKey ? fbox_get_key_entitlements($bKey) : fbox_get_plan_preset_entitlements('enterprise');

            $feats = [
                'floating_menu' => isset($_POST['feat_floating_menu']) ? 1 : 0,
                'esp'           => isset($_POST['feat_esp']) ? 1 : 0,
                'aimbot'        => isset($_POST['feat_aimbot']) ? 1 : 0,
                'bullet_track'  => isset($_POST['feat_bullet_track']) ? 1 : 0,
                'streamproof'   => (!empty($ent['ent_streamproof']) && isset($_POST['feat_streamproof'])) ? 1 : 0,
            ];
            $cleanFeats  = json_encode($feats);
            $silencer    = (!empty($ent['ent_telemetry_silencer']) && isset($_POST['telemetry_silencer_enabled'])) ? 1 : 0;
            $ramDiet     = (!empty($ent['ent_ram_diet']) && isset($_POST['ram_diet_enabled'])) ? 1 : 0;
            $ramOnlySo   = (!empty($ent['ent_ram_only_so']) && isset($_POST['ram_only_so_enabled'])) ? 1 : 0;
            $anogsCloak  = (!empty($ent['ent_ram_only_so']) && isset($_POST['anogs_maps_cloak_enabled'])) ? 1 : 0;
            $anogsCrc    = (!empty($ent['ent_ram_only_so']) && isset($_POST['anogs_crc_shield_enabled'])) ? 1 : 0;
            $vpnCloak    = (!empty($ent['ent_telemetry_silencer']) && isset($_POST['cloak_vpn_interface'])) ? 1 : 0;
            $breaker     = (!empty($ent['ent_ban_wave_breaker']) && isset($_POST['ban_wave_breaker_enabled'])) ? 1 : 0;
            $breakerThr  = max(2, min(100, (int)($_POST['ban_wave_threshold'] ?? 5)));
            $honeypot    = (!empty($ent['ent_honeypot']) && isset($_POST['honeypot_on_tamper'])) ? 1 : 0;
            $lockApkSig  = isset($_POST['lock_loader_apk_signature']) ? 1 : 0;
            $countries   = !empty($ent['ent_geo_lock']) ? strtoupper(trim((string)($_POST['allowed_countries'] ?? 'ALL'))) : 'ALL';
            if ($countries === '') $countries = 'ALL';
            $devPreset   = !empty($ent['ent_device_spoofer']) ? trim((string)($_POST['custom_device_preset'] ?? 'ROG_PHONE_8')) : 'NONE';
            $targetFps   = !empty($ent['ent_device_spoofer']) ? (int)($_POST['custom_target_fps'] ?? 120) : 60;
            if (!in_array($targetFps, [60, 90, 120], true)) $targetFps = 120;

            $pdo->prepare("UPDATE license_keys SET
                custom_features_json = ?,
                telemetry_silencer_enabled = ?,
                ram_diet_enabled = ?,
                ram_only_so_enabled = ?,
                anogs_maps_cloak_enabled = ?,
                anogs_crc_shield_enabled = ?,
                cloak_vpn_interface = ?,
                ban_wave_breaker_enabled = ?,
                ban_wave_threshold = ?,
                honeypot_on_tamper = ?,
                lock_loader_apk_signature = ?,
                allowed_countries = ?,
                custom_device_preset = ?,
                custom_target_fps = ?
                WHERE id = ?")->execute([
                $cleanFeats, $silencer, $ramDiet, $ramOnlySo, $anogsCloak, $anogsCrc, $vpnCloak, $breaker, $breakerThr, $honeypot, $lockApkSig, $countries, $devPreset, $targetFps, $keyId
            ]);
            $flash = 'SDK Controls saved! Flagship Spoofer (' . $devPreset . ' @ ' . $targetFps . ' FPS), Anogs Memory Cloak (' . ($anogsCloak ? 'ON' : 'OFF') . '), Ban-Wave Breaker (' . ($breaker ? 'ON' : 'OFF') . '), and Honeypot Shield (' . ($honeypot ? 'ON' : 'OFF') . ') updated.';
        }
        // Buyer: Reset Tripped Ban-Wave Circuit Breaker
        elseif ($act === 'buyer_reset_circuit_breaker') {
            $pdo->prepare("UPDATE license_keys SET circuit_breaker_tripped = 0 WHERE id = ?")->execute([$keyId]);
            $flash = 'Auto-Shield Circuit Breaker reset! Your users can now log in normally.';
        }

        // Buyer: Save Developer Telegram Bot for Crash Alerts
        elseif ($act === 'buyer_save_bot') {
            $bToken = trim((string)($_POST['buyer_telegram_bot_token'] ?? ''));
            $bChat  = trim((string)($_POST['buyer_telegram_chat_id'] ?? ''));
            $pdo->prepare("UPDATE license_keys SET buyer_telegram_bot_token = ?, buyer_telegram_chat_id = ? WHERE id = ?")
                ->execute([$bToken, $bChat, $keyId]);
            $flash = 'Your developer Telegram Bot has been configured for live crash alerts.';
        }

        // Buyer: Toggle Developer .so Crash Diagnostic Mode (Gated by Plan Entitlement ent_crash_bot — No Annoying Minute Timer!)
        elseif ($act === 'buyer_toggle_diagnostic_mode') {
            $bkStmt = $pdo->prepare("SELECT * FROM license_keys WHERE id = ?");
            $bkStmt->execute([$keyId]);
            $bKey = $bkStmt->fetch();
            $ent  = $bKey ? fbox_get_key_entitlements($bKey) : fbox_get_plan_preset_entitlements('enterprise');

            if (empty($ent['ent_crash_bot'])) {
                $flash = 'Developer .so Crash Diagnostic Mode is locked on your current Plan. Contact Admin to upgrade.';
                $flashType = 'error';
            } else {
                $hours = (int)($_POST['diag_hours'] ?? 0);
                $now   = time();
                if ($hours > 0) {
                    $exp = $now + ($hours * 3600);
                    $pdo->prepare("UPDATE license_keys SET is_diagnostic_mode = 1, diagnostic_expires_at = ? WHERE id = ?")
                        ->execute([$exp, $keyId]);
                    $flash = "Developer .so Crash Telemetry ACTIVATED for $hours hours! Crashing offsets will be pushed directly to your Telegram Bot.";
                } else {
                    $pdo->prepare("UPDATE license_keys SET is_diagnostic_mode = 0, diagnostic_expires_at = 0 WHERE id = ?")
                        ->execute([$keyId]);
                    $flash = 'Developer .so Crash Telemetry turned OFF (Silent Production Mode).';
                }
            }
        }

        // Buyer: Save Custom Offsets
        elseif ($act === 'buyer_save_offsets') {
            $rawJson = trim((string)($_POST['custom_offsets_json'] ?? '{}'));
            $decoded = json_decode($rawJson, true);
            if (!is_array($decoded)) {
                $flash = 'Invalid JSON syntax in custom offsets.';
                $flashType = 'error';
            } else {
                $cleanJson = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $pdo->prepare("UPDATE license_keys SET custom_offsets_json = ? WHERE id = ?")->execute([$cleanJson, $keyId]);
                $flash = 'Custom offsets and parameters saved.';
            }
        }

        // Buyer: Save Watermark Preference (ON / OFF / Inherit)
        elseif ($act === 'buyer_save_watermark') {
            $mode = trim((string)($_POST['watermark_mode'] ?? 'off'));
            if (!in_array($mode, ['on', 'off', 'inherit'], true)) $mode = 'off';
            $wText = trim((string)($_POST['custom_watermark_text'] ?? ''));
            $pdo->prepare("UPDATE license_keys SET watermark_mode = ?, custom_watermark_text = ? WHERE id = ?")
                ->execute([$mode, $wText, $keyId]);
            $flash = 'Watermark preferences updated. Mode is set to ' . strtoupper($mode) . '.';
        }

        // Buyer: Save Container Preferences
        elseif ($act === 'buyer_save_container') {
            $fps    = (int)($_POST['custom_target_fps'] ?? 0);
            $preset = trim((string)($_POST['custom_device_preset'] ?? ''));
            $pdo->prepare("UPDATE license_keys SET custom_target_fps = ?, custom_device_preset = ? WHERE id = ?")
                ->execute([$fps, $preset, $keyId]);
            $flash = 'Display FPS and Device Model preferences updated.';
        }

        // Buyer: Kick Session
        elseif ($act === 'buyer_kick_session') {
            $kRow = $pdo->prepare("SELECT license_key FROM license_keys WHERE id = ?");
            $kRow->execute([$keyId]);
            $licKey = $kRow->fetchColumn();
            if ($licKey) {
                $pdo->prepare("DELETE FROM active_sessions WHERE license_key = ?")->execute([$licKey]);
                $flash = 'Active game session terminated.';
            }
        }

        // Buyer: Change Password
        elseif ($act === 'buyer_change_password') {
            $newPass = trim((string)($_POST['new_password'] ?? ''));
            if (strlen($newPass) < 6) {
                $flash = 'Password must be at least 6 characters.';
                $flashType = 'error';
            } else {
                $pdo->prepare("UPDATE license_keys SET buyer_password_hash = ? WHERE id = ?")
                    ->execute([password_hash($newPass, PASSWORD_BCRYPT), $keyId]);
                $flash = 'Your portal password has been updated.';
            }
        }
    }
}

// ---------------------------------------------------------------------------
// 2. Handle Administrator Actions
// ---------------------------------------------------------------------------
// ---------------------------------------------------------------------------
// 1.5 Handle Reseller / Agent Actions
// ---------------------------------------------------------------------------
if ($role === 'reseller' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals((string)$_SESSION['fbox_csrf'], $csrf)) {
        $flash = 'Session expired. Please refresh the page.';
        $flashType = 'error';
    } else {
        $act = (string)($_POST['form_action'] ?? '');
        $rId = (int)$_SESSION['fbox_reseller_id'];
        if ($act === 'reseller_generate_key') {
            $rStmt = $pdo->prepare("SELECT * FROM resellers WHERE id = ?");
            $rStmt->execute([$rId]);
            $resellerData = $rStmt->fetch();

            $tiers = fbox_get_reseller_price_tiers();
            $reqDays = (int)($_POST['duration_days'] ?? 30);
            if (!isset($tiers[$reqDays])) $reqDays = 30;
            $costTier = $tiers[$reqDays];
            $costCredits = (int)$costTier['credits'];
            $currentCredits = (int)($resellerData['credits'] ?? 0);

            if ($resellerData && $currentCredits >= $costCredits) {
                $label = trim((string)($_POST['label'] ?? ''));
                $prefix = 'FBOX';
                $newKey = $prefix . '-' . strtoupper(bin2hex(random_bytes(3))) . '-' . strtoupper(bin2hex(random_bytes(3)));
                $now = time();
                $rPlanTier = (string)($resellerData['allowed_plan_tier'] ?? 'pro');
                $rEntJson  = json_encode(fbox_get_plan_preset_entitlements($rPlanTier));

                $ins = $pdo->prepare("INSERT INTO license_keys (license_key, label, duration_days, max_devices, max_loader_slots, allowed_games_json, plan_tier, buyer_entitlements_json, created_by_reseller, bound_hwids_json, status, created_at, activated_at, expires_at) VALUES (?, ?, ?, 0, 1, '["*\"]', ?, ?, ?, '[]', 'active', ?, 0, 0)");
                $ins->execute([$newKey, $label, $reqDays, $rPlanTier, $rEntJson, (string)$resellerData['username'], $now]);

                $pdo->prepare("UPDATE resellers SET credits = credits - ? WHERE id = ?")->execute([$costCredits, $rId]);
                fbox_log_security_event('RESELLER_KEY_GENERATED', 'info', '', $newKey, '', "Reseller {$resellerData['username']} generated {$costTier['label']} for {$costCredits} credits");

                $flash = "License key '$newKey' ({$costTier['label']}) generated successfully! $costCredits credit(s) deducted from your balance.";
            } else {
                $flash = "Insufficient credits! Generating a {$costTier['label']} requires $costCredits credits, but your current balance is $currentCredits credits.";
                $flashType = 'error';
            }
        }

        // Reseller: Extend Existing Client Key Duration
        elseif ($act === 'reseller_extend_key') {
            $keyId   = (int)($_POST['key_id'] ?? 0);
            $extDays = (int)($_POST['extend_days'] ?? 30);
            $tiers   = fbox_get_reseller_price_tiers();
            if (!isset($tiers[$extDays])) $extDays = 30;
            $costTier = $tiers[$extDays];
            $costCredits = (int)$costTier['credits'];

            $rStmt = $pdo->prepare("SELECT * FROM resellers WHERE id = ?");
            $rStmt->execute([$rId]);
            $resellerData = $rStmt->fetch();
            $currentCredits = (int)($resellerData['credits'] ?? 0);

            // Verify key belongs to this reseller
            $kStmt = $pdo->prepare("SELECT * FROM license_keys WHERE id = ? AND created_by_reseller = ?");
            $kStmt->execute([$keyId, (string)$resellerData['username']]);
            $kRow = $kStmt->fetch();

            if (!$kRow) {
                $flash = 'License key not found or does not belong to your account.';
                $flashType = 'error';
            } elseif ($currentCredits < $costCredits) {
                $flash = "Insufficient credits! Extending for {$costTier['label']} requires $costCredits credits (Available: $currentCredits).";
                $flashType = 'error';
            } else {
                $base = ((int)$kRow['expires_at'] > time()) ? (int)$kRow['expires_at'] : time();
                $newExp = $base + ($extDays * 86400);

                $pdo->prepare("UPDATE license_keys SET expires_at = ?, duration_days = duration_days + ?, status = 'active' WHERE id = ?")
                    ->execute([$newExp, $extDays, $keyId]);
                $pdo->prepare("UPDATE resellers SET credits = credits - ? WHERE id = ?")->execute([$costCredits, $rId]);

                fbox_log_security_event('RESELLER_KEY_EXTENDED', 'info', '', (string)$kRow['license_key'], '', "Reseller {$resellerData['username']} extended key by {$extDays}d for {$costCredits} credits");
                $flash = "License key '{$kRow['license_key']}' extended by {$extDays} days! $costCredits credit(s) deducted. New expiry: " . date('d M Y', $newExp);
            }
        }

        // Reseller: Block / Activate Client Key
        elseif ($act === 'reseller_toggle_key_status') {
            $keyId = (int)($_POST['key_id'] ?? 0);
            $rStmt = $pdo->prepare("SELECT username FROM resellers WHERE id = ?");
            $rStmt->execute([$rId]);
            $rUser = (string)$rStmt->fetchColumn();

            $kStmt = $pdo->prepare("SELECT * FROM license_keys WHERE id = ? AND created_by_reseller = ?");
            $kStmt->execute([$keyId, $rUser]);
            $kRow = $kStmt->fetch();

            if ($kRow) {
                $next = ($kRow['status'] === 'active') ? 'blocked' : 'active';
                $pdo->prepare("UPDATE license_keys SET status = ? WHERE id = ?")->execute([$next, $keyId]);
                if ($next === 'blocked') {
                    $pdo->prepare("DELETE FROM active_sessions WHERE license_key = ?")->execute([(string)$kRow['license_key']]);
                }
                $flash = "Key status updated to " . strtoupper($next) . ".";
            }
        }

        // Reseller: Reset HWID for Client Key
        elseif ($act === 'reseller_reset_hwid') {
            $keyId = (int)($_POST['key_id'] ?? 0);
            $rStmt = $pdo->prepare("SELECT username FROM resellers WHERE id = ?");
            $rStmt->execute([$rId]);
            $rUser = (string)$rStmt->fetchColumn();

            $kStmt = $pdo->prepare("SELECT license_key FROM license_keys WHERE id = ? AND created_by_reseller = ?");
            $kStmt->execute([$keyId, $rUser]);
            $lic = $kStmt->fetchColumn();
            if ($lic) {
                $pdo->prepare("UPDATE license_keys SET bound_hwids_json = '[]', bound_apks_json = '[]' WHERE id = ?")->execute([$keyId]);
                $flash = "HWID binding reset for $lic. Client can now bind their new device.";
            }
        }
    }
}

if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals((string)$_SESSION['fbox_csrf'], $csrf)) {
        $flash = 'Session expired. Please refresh the page.';
        $flashType = 'error';
    } else {
        $act = (string)($_POST['form_action'] ?? '');

        // Admin: Quick Security & Anti-Bypass Switches
        if ($act === 'save_quick_switches') {
            fbox_set_setting('maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0');
            fbox_set_setting('auto_ban_on_tamper', isset($_POST['auto_ban_on_tamper']) ? '1' : '0');
            fbox_set_setting('ban_ip_with_hwid', isset($_POST['ban_ip_with_hwid']) ? '1' : '0');
            fbox_set_setting('require_login_gate', isset($_POST['require_login_gate']) ? '1' : '0');
            fbox_set_setting('enforce_request_hmac', isset($_POST['enforce_request_hmac']) ? '1' : '0');
            fbox_set_setting('enforce_apk_signature', isset($_POST['enforce_apk_signature']) ? '1' : '0');
            fbox_set_setting('block_http_proxy', isset($_POST['block_http_proxy']) ? '1' : '0');
            fbox_set_setting('block_root_magisk', isset($_POST['block_root_magisk']) ? '1' : '0');
            fbox_set_setting('block_emulator', isset($_POST['block_emulator']) ? '1' : '0');
            fbox_set_setting('kill_switch_active', isset($_POST['kill_switch_active']) ? '1' : '0');
            fbox_set_setting('enable_leak_watermark', isset($_POST['enable_leak_watermark']) ? '1' : '0');
            if (isset($_POST['min_loader_version'])) fbox_set_setting('min_loader_version', (string)(int)$_POST['min_loader_version']);
            if (isset($_POST['update_apk_url'])) fbox_set_setting('update_apk_url', trim((string)$_POST['update_apk_url']));
            if (isset($_POST['force_update_message'])) fbox_set_setting('force_update_message', trim((string)$_POST['force_update_message']));
            $flash = 'Master switches and anti-bypass policies saved.';
        }
        // Admin: Save Reseller Duration Tier Credit Pricing
        elseif ($act === 'save_reseller_pricing') {
            fbox_set_setting('reseller_price_1d', (string)max(1, (int)($_POST['price_1d'] ?? 1)));
            fbox_set_setting('reseller_price_7d', (string)max(1, (int)($_POST['price_7d'] ?? 5)));
            fbox_set_setting('reseller_price_15d', (string)max(1, (int)($_POST['price_15d'] ?? 10)));
            fbox_set_setting('reseller_price_30d', (string)max(1, (int)($_POST['price_30d'] ?? 18)));
            fbox_set_setting('reseller_price_60d', (string)max(1, (int)($_POST['price_60d'] ?? 32)));
            $flash = 'Reseller wholesale pricing tiers updated successfully.';
        }
        if (false) {
        }

                        // Admin: Create Staff Manager
        elseif ($act === 'create_manager') {
            if ($role !== 'admin') {
                $flash = 'Permission Denied: Only Master Admin can create staff managers.';
                $flashType = 'error';
            } else {
                $mUser = trim((string)($_POST['manager_username'] ?? ''));
                $mPass = (string)($_POST['manager_password'] ?? '');
                $mLbl  = trim((string)($_POST['manager_label'] ?? 'Staff Manager'));
                if ($mUser !== '' && $mPass !== '') {
                    $mHash = password_hash($mPass, PASSWORD_BCRYPT);
                    try {
                        $pdo->prepare("INSERT INTO managers (username, password_hash, label, can_generate_keys, can_unban_devices, can_extend_keys, created_at) VALUES (?, ?, ?, 1, 1, 1, ?)")
                            ->execute([$mUser, $mHash, $mLbl, time()]);
                        $flash = "Staff Manager '$mUser' created successfully!";
                    } catch (Throwable $e) {
                        $flash = "Failed to create manager: " . $e->getMessage();
                        $flashType = 'error';
                    }
                }
            }
        }
        // Admin: Delete Staff Manager
        elseif ($act === 'delete_manager') {
            if ($role !== 'admin') {
                $flash = 'Permission Denied.';
                $flashType = 'error';
            } else {
                $mId = (int)($_POST['manager_id'] ?? 0);
                $pdo->prepare("DELETE FROM managers WHERE id = ?")->execute([$mId]);
                $flash = "Staff Manager deleted.";
            }
        }

        // Admin: Trigger Immediate Database Backup
        elseif ($act === 'backup_database_now') {
            $res = fbox_backup_database();
            $flash = $res['message'];
            if ($res['status'] !== 'OK') $flashType = 'error';
        }

        // Admin: Create New Reseller
        elseif ($act === 'create_reseller') {
            $rUser = trim((string)($_POST['reseller_username'] ?? ''));
            $rPass = (string)($_POST['reseller_password'] ?? '');
            $rCred = max(0, (int)($_POST['reseller_credits'] ?? 10));
            $rPlan = trim((string)($_POST['allowed_plan_tier'] ?? 'pro'));
            if (!in_array($rPlan, ['starter', 'pro', 'enterprise'], true)) $rPlan = 'pro';
            $rLbl  = trim((string)($_POST['reseller_label'] ?? ''));
            if ($rUser !== '' && $rPass !== '') {
                $rHash = password_hash($rPass, PASSWORD_BCRYPT);
                try {
                    $pdo->prepare("INSERT INTO resellers (username, password_hash, credits, allowed_plan_tier, label, created_at) VALUES (?, ?, ?, ?, ?, ?)")
                        ->execute([$rUser, $rHash, $rCred, $rPlan, $rLbl, time()]);
                    $flash = "Reseller '$rUser' created with $rCred credits (" . strtoupper($rPlan) . " Plan access)!";
                } catch (Throwable $e) {
                    $flash = "Failed to create reseller: " . $e->getMessage();
                    $flashType = 'error';
                }
            }
        }

        // Admin: Add Credits to Reseller
        elseif ($act === 'add_reseller_credits') {
            $rId  = (int)($_POST['reseller_id'] ?? 0);
            $cAdd = (int)($_POST['add_credits'] ?? 0);
            $pdo->prepare("UPDATE resellers SET credits = credits + ? WHERE id = ?")->execute([$cAdd, $rId]);
            $flash = "Credits updated for reseller.";
        }

        // Admin: Generate License Keys with Allowed Games & 1-Click Delivery Pack
        elseif ($act === 'generate_keys') {
            $prefix     = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', trim((string)($_POST['prefix'] ?? 'FBOX-VIP'))));
            if ($prefix === '') $prefix = 'FBOX';
            $count      = max(1, min(100, (int)($_POST['count'] ?? 1)));
            $days       = max(1, min(3650, (int)($_POST['duration_days'] ?? 30)));
            $maxDevices = max(0, min(10000, (int)($_POST['max_devices'] ?? 0)));
            $maxSlots   = max(0, min(100, (int)($_POST['max_loader_slots'] ?? 1)));
            $label      = trim((string)($_POST['label'] ?? ''));
            $bUser      = trim((string)($_POST['buyer_username'] ?? ''));
            $bPass      = (string)($_POST['buyer_password'] ?? '');
            $bHash      = $bPass !== '' ? password_hash($bPass, PASSWORD_BCRYPT) : '';
            $gamesArray = $_POST['allowed_games'] ?? ['*'];
            if (!is_array($gamesArray) || empty($gamesArray)) $gamesArray = ['*'];
            $gamesJson  = json_encode(array_values($gamesArray));
            $now        = time();

            $dailyDiagLimit = (int)($_POST['daily_diag_limit_minutes'] ?? 120);
            $buyerCanUnban  = isset($_POST['buyer_can_unban']) ? 1 : 0;
            $buyerCanToggle = isset($_POST['buyer_can_toggle_autoban']) ? 1 : 0;
            $buyerCanRoll   = isset($_POST['buyer_can_roll_key']) ? 1 : 0;
            $cooldownH      = max(0, min(720, (int)($_POST['hwid_reset_cooldown_hours'] ?? 24)));
            $planTier       = trim((string)($_POST['plan_tier'] ?? 'enterprise'));
            if (!in_array($planTier, ['starter', 'pro', 'enterprise', 'custom'], true)) $planTier = 'enterprise';
            $entMap = [
                'ent_cloud_so'           => isset($_POST['ent_cloud_so']) ? 1 : 0,
                'ent_ram_only_so'        => isset($_POST['ent_ram_only_so']) ? 1 : 0,
                'ent_device_spoofer'     => isset($_POST['ent_device_spoofer']) ? 1 : 0,
                'ent_streamproof'        => isset($_POST['ent_streamproof']) ? 1 : 0,
                'ent_telemetry_silencer' => isset($_POST['ent_telemetry_silencer']) ? 1 : 0,
                'ent_ram_diet'           => isset($_POST['ent_ram_diet']) ? 1 : 0,
                'ent_ban_wave_breaker'   => isset($_POST['ent_ban_wave_breaker']) ? 1 : 0,
                'ent_honeypot'           => isset($_POST['ent_honeypot']) ? 1 : 0,
                'ent_geo_lock'           => isset($_POST['ent_geo_lock']) ? 1 : 0,
                'ent_watermark_off'      => isset($_POST['ent_watermark_off']) ? 1 : 0,
                'ent_crash_bot'          => isset($_POST['ent_crash_bot']) ? 1 : 0,
            ];
            $entJson = json_encode($entMap);

            $ins = $pdo->prepare("INSERT INTO license_keys
                (license_key, buyer_username, buyer_password_hash, label, duration_days, max_devices, max_loader_slots, allowed_games_json, plan_tier, buyer_entitlements_json, buyer_can_roll_key, daily_diag_limit_minutes, buyer_can_unban, buyer_can_toggle_autoban, hwid_reset_cooldown_hours, bound_hwids_json, status, created_at, activated_at, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '[]', 'active', ?, 0, 0)");
            $created = 0;
            $firstKey = '';
            $batchKeysList = [];
            for ($i = 0; $i < $count; $i++) {
                $randPart = strtoupper(bin2hex(random_bytes(3))) . '-' . strtoupper(bin2hex(random_bytes(3)));
                $newKey   = $prefix . '-' . $randPart;
                if ($firstKey === '') $firstKey = $newKey;
                $assignedUser = ($bUser !== '' && $count > 1) ? ($bUser . '_' . ($i + 1)) : $bUser;
                try {
                    $ins->execute([$newKey, $assignedUser, $bHash, $label, $days, $maxDevices, $maxSlots, $gamesJson, $planTier, $entJson, $buyerCanRoll, $dailyDiagLimit, $buyerCanUnban, $buyerCanToggle, $cooldownH, $now]);
                    $created++;
                    $batchKeysList[] = $newKey;
                } catch (Throwable $ignored) {
                }
            }
            if ($created > 1) {
                $_SESSION['fbox_last_batch'] = [
                    'keys' => $batchKeysList,
                    'count' => $created,
                    'days' => $days,
                    'games' => $gamesListStr,
                    'time' => date('d M Y, H:i')
                ];
            }

            // Create pre-formatted 1-Click Delivery Pack for the client
            $gamesListStr = in_array('*', $gamesArray, true) ? 'Universal (All Games)' : implode(', ', $gamesArray);
            $_SESSION['fbox_delivery_pack'] = [
                'license_key' => $firstKey,
                'portal_url'  => $panelUrl,
                'gateway_url' => $autoApiUrl,
                'username'    => $bUser ?: 'Use License Key to log in',
                'password'    => $bPass ?: '123456',
                'games'       => $gamesListStr,
                'slots'       => $maxSlots === 0 ? 'Unlimited Loaders' : "$maxSlots Loaders Max",
                'duration'    => "$days Days",
                'label'       => $label ?: 'VIP Client',
            ];

            $flash = "Generated $created key(s)! 1-Click Delivery Pack ready below to send to your client.";
        }

        // Admin: Edit Key, Rights, Allowed Games & Slots
        elseif ($act === 'admin_edit_key_profile') {
            $keyId      = (int)($_POST['key_id'] ?? 0);
            $bUser      = trim((string)($_POST['buyer_username'] ?? ''));
            $bPass      = (string)($_POST['buyer_password'] ?? '');
            $maxSlots   = max(0, min(100, (int)($_POST['max_loader_slots'] ?? 1)));
            $maxDevices = max(0, min(10000, (int)($_POST['max_devices'] ?? 0)));
            $status     = trim((string)($_POST['status'] ?? 'active'));
            $label      = trim((string)($_POST['label'] ?? ''));
            $gamesArray = $_POST['allowed_games'] ?? ['*'];
            if (!is_array($gamesArray) || empty($gamesArray)) $gamesArray = ['*'];
            $gamesJson  = json_encode(array_values($gamesArray));

            $dailyDiagLimit = (int)($_POST['daily_diag_limit_minutes'] ?? 120);
            $devPreset      = trim((string)($_POST['custom_device_preset'] ?? 'ROG_PHONE_8'));
            $targetFps      = (int)($_POST['custom_target_fps'] ?? 120);
            $honeypotEn     = (int)($_POST['honeypot_on_tamper'] ?? 0);
            $countries      = strtoupper(trim((string)($_POST['allowed_countries'] ?? 'ALL')));
            if ($countries === '') $countries = 'ALL';
            $breakerEn      = (int)($_POST['ban_wave_breaker_enabled'] ?? 0);
            $resetBreaker   = isset($_POST['reset_circuit_breaker']) ? 1 : 0;
            $planTier       = trim((string)($_POST['plan_tier'] ?? 'enterprise'));
            $buyerCanRoll   = isset($_POST['buyer_can_roll_key']) ? 1 : 0;
            $entMap = [
                'ent_cloud_so'           => isset($_POST['edit_ent_cloud_so']) ? 1 : 0,
                'ent_ram_only_so'        => isset($_POST['edit_ent_ram_only_so']) ? 1 : 0,
                'ent_device_spoofer'     => isset($_POST['edit_ent_device_spoofer']) ? 1 : 0,
                'ent_streamproof'        => isset($_POST['edit_ent_streamproof']) ? 1 : 0,
                'ent_telemetry_silencer' => isset($_POST['edit_ent_telemetry_silencer']) ? 1 : 0,
                'ent_ram_diet'           => isset($_POST['edit_ent_ram_diet']) ? 1 : 0,
                'ent_ban_wave_breaker'   => isset($_POST['edit_ent_ban_wave_breaker']) ? 1 : 0,
                'ent_honeypot'           => isset($_POST['edit_ent_honeypot']) ? 1 : 0,
                'ent_geo_lock'           => isset($_POST['edit_ent_geo_lock']) ? 1 : 0,
                'ent_watermark_off'      => isset($_POST['edit_ent_watermark_off']) ? 1 : 0,
                'ent_crash_bot'          => isset($_POST['edit_ent_crash_bot']) ? 1 : 0,
            ];
            $entJson = json_encode($entMap);

            if ($bPass !== '') {
                $bHash = password_hash($bPass, PASSWORD_BCRYPT);
                $upd = $pdo->prepare("UPDATE license_keys SET
                    buyer_username = ?, buyer_password_hash = ?, max_loader_slots = ?, max_devices = ?,
                    allowed_games_json = ?, plan_tier = ?, buyer_entitlements_json = ?, buyer_can_roll_key = ?,
                    daily_diag_limit_minutes = ?, custom_device_preset = ?, custom_target_fps = ?,
                    honeypot_on_tamper = ?, allowed_countries = ?, ban_wave_breaker_enabled = ?,
                    circuit_breaker_tripped = CASE WHEN ? = 1 THEN 0 ELSE circuit_breaker_tripped END,
                    status = ?, label = ? WHERE id = ?");
                $upd->execute([$bUser, $bHash, $maxSlots, $maxDevices, $gamesJson, $planTier, $entJson, $buyerCanRoll, $dailyDiagLimit, $devPreset, $targetFps, $honeypotEn, $countries, $breakerEn, $resetBreaker, $status, $label, $keyId]);
            } else {
                $upd = $pdo->prepare("UPDATE license_keys SET
                    buyer_username = ?, max_loader_slots = ?, max_devices = ?,
                    allowed_games_json = ?, plan_tier = ?, buyer_entitlements_json = ?, buyer_can_roll_key = ?,
                    daily_diag_limit_minutes = ?, custom_device_preset = ?, custom_target_fps = ?,
                    honeypot_on_tamper = ?, allowed_countries = ?, ban_wave_breaker_enabled = ?,
                    circuit_breaker_tripped = CASE WHEN ? = 1 THEN 0 ELSE circuit_breaker_tripped END,
                    status = ?, label = ? WHERE id = ?");
                $upd->execute([$bUser, $maxSlots, $maxDevices, $gamesJson, $planTier, $entJson, $buyerCanRoll, $dailyDiagLimit, $devPreset, $targetFps, $honeypotEn, $countries, $breakerEn, $resetBreaker, $status, $label, $keyId]);
            }
            $flash = "SDK Key ID $keyId Plan ($planTier), Feature Entitlements, and Security Policies updated!";
        }

        // Admin: Key Row Actions (Reset HWID, Extend +7d, Toggle Pause/Active, Delete)
        elseif ($act === 'key_action') {
            $keyId = (int)($_POST['key_id'] ?? 0);
            $sub   = (string)($_POST['sub_action'] ?? '');
            if ($sub === 'reset_hwid') {
                $pdo->prepare("UPDATE license_keys SET bound_hwids_json = '[]', bound_apks_json = '[]' WHERE id = ?")->execute([$keyId]);
                $flash = 'HWID binding reset. Key can now bind to a new device.';
            } elseif ($sub === 'reset_breaker') {
                $pdo->prepare("UPDATE license_keys SET circuit_breaker_tripped = 0 WHERE id = ?")->execute([$keyId]);
                $flash = 'Circuit Breaker reset for key. Logins resumed.';
            } elseif ($sub === 'extend_custom' || $sub === 'extend_7d' || $sub === 'extend_30d') {
                $addDays = ($sub === 'extend_7d') ? 7 : (($sub === 'extend_30d') ? 30 : max(1, (int)($_POST['custom_extend_days'] ?? 30)));
                $stmt = $pdo->prepare("SELECT expires_at, duration_days, license_key FROM license_keys WHERE id = ?");
                $stmt->execute([$keyId]);
                $r = $stmt->fetch();
                if ($r) {
                    $base = ((int)$r['expires_at'] > time()) ? (int)$r['expires_at'] : time();
                    $newExp = $base + ($addDays * 86400);
                    $pdo->prepare("UPDATE license_keys SET expires_at = ?, duration_days = duration_days + ?, status = 'active' WHERE id = ?")
                        ->execute([$newExp, $addDays, $keyId]);
                    fbox_log_security_event('ADMIN_KEY_EXTENDED', 'info', '', (string)$r['license_key'], '', "Admin extended key by +{$addDays} days");
                    $flash = "License key extended by +{$addDays} days! New expiry: " . date('d M Y', $newExp);
                }
            } elseif ($sub === 'toggle_status' || $sub === 'block_key' || $sub === 'activate_key') {
                $stmt = $pdo->prepare("SELECT status, license_key FROM license_keys WHERE id = ?");
                $stmt->execute([$keyId]);
                $r = $stmt->fetch();
                if ($r) {
                    if ($sub === 'block_key') {
                        $next = 'blocked';
                    } elseif ($sub === 'activate_key') {
                        $next = 'active';
                    } else {
                        $next = ($r['status'] === 'active') ? 'blocked' : 'active';
                    }
                    $pdo->prepare("UPDATE license_keys SET status = ? WHERE id = ?")->execute([$next, $keyId]);
                    if ($next === 'blocked' || $next === 'paused') {
                        $pdo->prepare("DELETE FROM active_sessions WHERE license_key = ?")->execute([(string)$r['license_key']]);
                    }
                    $flash = "Updated key status to " . strtoupper($next) . ". (Active sessions terminated if blocked)";
                }
            } elseif ($sub === 'unban_all_key') {
                $stmt = $pdo->prepare("SELECT license_key FROM license_keys WHERE id = ?");
                $stmt->execute([$keyId]);
                $kLic = $stmt->fetchColumn();
                if ($kLic) {
                    $aff = fbox_unban_all_for_key((string)$kLic);
                    $flash = "Unbanned $aff device(s) for license key $kLic.";
                }
            } elseif ($sub === 'delete_key') {
                $pdo->prepare("DELETE FROM license_keys WHERE id = ?")->execute([$keyId]);
                $flash = 'License key deleted.';
            }
        }

        // Admin: Unban Device HWID
        elseif ($act === 'unban_device') {
            $banId = (int)($_POST['ban_id'] ?? 0);
            $stmt  = $pdo->prepare("SELECT hwid, device_model FROM device_bans WHERE id = ?");
            $stmt->execute([$banId]);
            $bRow = $stmt->fetch();
            if ($bRow) {
                $pdo->prepare("UPDATE device_bans SET is_active = 0, unbanned_at = ? WHERE id = ?")->execute([time(), $banId]);
                fbox_log_security_event('DEVICE_UNBANNED_BY_ADMIN', 'info', (string)$bRow['hwid'], '', (string)$bRow['device_model'],
                    'Admin unbanned device from Control Panel');
                $flash = 'Device HWID has been unbanned. Access restored on next connection.';
            }
        }

        // Admin: Manual Ban Device HWID
        elseif ($act === 'manual_ban_device') {
            $hwid   = trim((string)($_POST['hwid'] ?? ''));
            $model  = trim((string)($_POST['device_model'] ?? 'Manual Entry'));
            $reason = trim((string)($_POST['ban_reason'] ?? 'MANUAL_ADMIN_BAN'));
            if ($hwid !== '') {
                fbox_ban_device_hwid($hwid, $model, '', '', '', $reason, 'Banned manually by Admin', 'Admin Panel');
                $flash = 'Device HWID banned immediately and active sessions terminated.';
            }
        }

        // Admin: Terminate Session
        elseif ($act === 'session_action') {
            $sessId = (int)($_POST['session_id'] ?? 0);
            $sub    = (string)($_POST['sub_action'] ?? '');
            $stmt   = $pdo->prepare("SELECT * FROM active_sessions WHERE id = ?");
            $stmt->execute([$sessId]);
            $sRow = $stmt->fetch();
            if ($sRow) {
                if ($sub === 'ban_hwid') {
                    fbox_ban_device_hwid((string)$sRow['hwid'], (string)$sRow['device_model'], (string)$sRow['package_name'],
                        '', (string)$sRow['license_key'], 'BANNED_FROM_LIVE_SESSION', 'Admin banned active player', 'Admin Panel');
                    $flash = 'Device banned and live game session terminated.';
                } else {
                    $pdo->prepare("DELETE FROM active_sessions WHERE id = ?")->execute([$sessId]);
                    $flash = 'Active session terminated.';
                }
            }
        }

        // Admin: Save Master Encrypted Payload Vault
        elseif ($act === 'save_payload_vault') {
            $rawJson = trim((string)($_POST['runtime_params_json'] ?? '{}'));
            $decoded = json_decode($rawJson, true);
            if (!is_array($decoded)) {
                $flash = 'Invalid JSON in Encrypted Runtime Parameters.';
                $flashType = 'error';
            } else {
                fbox_set_setting('runtime_params_json', json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $u64 = fbox_normalize_github_url(trim((string)($_POST['plugin_url_64'] ?? '')));
                $u32 = fbox_normalize_github_url(trim((string)($_POST['plugin_url_32'] ?? '')));
                fbox_set_setting('plugin_url_64', $u64);
                fbox_set_setting('plugin_url_32', $u32);
                fbox_set_setting('plugin_sha256', strtolower(trim((string)($_POST['plugin_sha256'] ?? ''))));
                $flash = 'Master Encrypted Payload & Cloud OTA Plugin configuration saved.';
            }
        }

        // Admin: Save SDK Settings & Security Config
        elseif ($act === 'save_sdk_settings') {
            fbox_set_setting('target_fps', (string)((int)($_POST['target_fps'] ?? 120)));
            fbox_set_setting('device_preset', trim((string)($_POST['device_preset'] ?? 'ROG_PHONE_8')));
            fbox_set_setting('gfx_preset', (string)((int)($_POST['gfx_preset'] ?? 0)));
            fbox_set_setting('streamproof_enabled', isset($_POST['streamproof_enabled']) ? '1' : '0');
            fbox_set_setting('fps_hud_enabled', isset($_POST['fps_hud_enabled']) ? '1' : '0');
            fbox_set_setting('hide_xposed', isset($_POST['hide_xposed']) ? '1' : '0');
            fbox_set_setting('maintenance_message', trim((string)($_POST['maintenance_message'] ?? '')));
            fbox_set_setting('announcement', trim((string)($_POST['announcement'] ?? '')));
            fbox_set_setting('locked_apk_sha256', strtoupper(str_replace(':', '', trim((string)($_POST['locked_apk_sha256'] ?? '')))));
            fbox_set_setting('pinned_tls_cert_sha256', strtoupper(str_replace(':', '', trim((string)($_POST['pinned_tls_cert_sha256'] ?? '')))));
            fbox_set_setting('telegram_bot_token', trim((string)($_POST['telegram_bot_token'] ?? '')));
            fbox_set_setting('telegram_chat_id', trim((string)($_POST['telegram_chat_id'] ?? '')));
            fbox_set_setting('discord_webhook_url', trim((string)($_POST['discord_webhook_url'] ?? '')));
            fbox_set_setting('min_loader_version', (string)max(1, (int)($_POST['min_loader_version'] ?? 1)));
            fbox_set_setting('update_apk_url', trim((string)($_POST['update_apk_url'] ?? '')));
            fbox_set_setting('dynamic_primary_endpoint', trim((string)($_POST['dynamic_primary_endpoint'] ?? '')));
            fbox_set_setting('dynamic_backup_endpoint', trim((string)($_POST['dynamic_backup_endpoint'] ?? '')));
            $sec = trim((string)($_POST['shared_api_secret'] ?? ''));
            if ($sec !== '') {
                fbox_set_setting('shared_api_secret', $sec);
            }
            $newPass = trim((string)($_POST['new_admin_password'] ?? ''));
            if ($newPass !== '') {
                $pdo->prepare("UPDATE admins SET password_hash = ?, updated_at = ? WHERE id = ?")
                    ->execute([password_hash($newPass, PASSWORD_BCRYPT), time(), $_SESSION['fbox_admin_id']]);
            }
            $flash = 'SDK Runtime, Signature Lock & Endpoint Settings saved.';
        }

        // Admin: Clear Crash Reports
        elseif ($act === 'clear_crash_reports') {
            $pdo->exec("DELETE FROM crash_reports");
            $flash = 'Crash diagnostic reports cleared.';
        }

        // Admin: Clear Audit Logs
        elseif ($act === 'clear_logs') {
            $pdo->exec("DELETE FROM security_logs");
            $flash = 'Security audit logs cleared.';
        }
    }
}

// ---------------------------------------------------------------------------
// 3. Fetch View Data
// ---------------------------------------------------------------------------
$settings = fbox_get_all_settings();

// Staff (Admin / Manager) Data
$isStaff = ($role === 'admin' || $role === 'manager');
$licenseKeys  = $isStaff ? $pdo->query("SELECT * FROM license_keys ORDER BY id DESC")->fetchAll() : [];
$deviceBans   = $isStaff ? $pdo->query("SELECT * FROM device_bans ORDER BY is_active DESC, banned_at DESC")->fetchAll() : [];
$sessions     = $isStaff ? $pdo->query("SELECT * FROM active_sessions WHERE expires_at > " . time() . " ORDER BY last_heartbeat_at DESC")->fetchAll() : [];
$securityLogs = $isStaff ? $pdo->query("SELECT * FROM security_logs ORDER BY id DESC LIMIT 80")->fetchAll() : [];
$crashReports = $isStaff ? $pdo->query("SELECT * FROM crash_reports ORDER BY id DESC LIMIT 60")->fetchAll() : [];
$managers     = ($role === 'admin') ? $pdo->query("SELECT * FROM managers ORDER BY id DESC")->fetchAll() : [];

$activeKeysCount = 0;
foreach ($licenseKeys as $k) {
    if ($k['status'] === 'active') $activeKeysCount++;
}
$activeBansCount = 0;
foreach ($deviceBans as $b) {
    if ((int)$b['is_active'] === 1) $activeBansCount++;
}
$onlineSessCount = count($sessions);

// Reseller Specific Data
$myReseller = null;
$myResellerKeys = [];
if ($role === 'reseller') {
    $rStmt = $pdo->prepare("SELECT * FROM resellers WHERE id = ?");
    $rStmt->execute([(int)$_SESSION['fbox_reseller_id']]);
    $myReseller = $rStmt->fetch();
    if ($myReseller) {
        $rkStmt = $pdo->prepare("SELECT * FROM license_keys WHERE created_by_reseller = ? ORDER BY id DESC");
        $rkStmt->execute([(string)$myReseller['username']]);
        $myResellerKeys = $rkStmt->fetchAll();
    }
}
$resellers = ($role === 'admin') ? $pdo->query("SELECT * FROM resellers ORDER BY id DESC")->fetchAll() : [];

// Customer / Buyer Specific Data (Zero cross-tenant leakage)
$myKey     = null;
$mySession = null;
$myLoaders = [];
if ($role === 'buyer') {
    $bkStmt = $pdo->prepare("SELECT * FROM license_keys WHERE id = ?");
    $bkStmt->execute([(int)$_SESSION['fbox_buyer_key_id']]);
    $myKey = $bkStmt->fetch();

    if ($myKey) {
        $bsStmt = $pdo->prepare("SELECT * FROM active_sessions WHERE license_key = ? ORDER BY id DESC LIMIT 1");
        $bsStmt->execute([(string)$myKey['license_key']]);
        $mySession = $bsStmt->fetch();
        $myLoaders = fbox_get_buyer_loaders((int)$myKey['id']);
        $bCrashStmt = $pdo->prepare("SELECT * FROM crash_reports WHERE license_key = ? ORDER BY id DESC LIMIT 20");
        $bCrashStmt->execute([(string)$myKey['license_key']]);
        $myCrashReports = $bCrashStmt->fetchAll();

        $bBanStmt = $pdo->prepare("SELECT * FROM device_bans WHERE license_key = ? AND is_active = 1 ORDER BY banned_at DESC LIMIT 30");
        $bBanStmt->execute([(string)$myKey['license_key']]);
        $myBannedDevices = $bBanStmt->fetchAll();
    }
}

$deliveryPack = $_SESSION['fbox_delivery_pack'] ?? null;
unset($_SESSION['fbox_delivery_pack']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Featurestic Box — <?= $role === 'buyer' ? 'Customer Console' : 'Security & SDK Hub' ?></title>
<style>
:root {
    --bg-primary: #F2F2F7;
    --bg-card: rgba(255, 255, 255, 0.90);
    --bg-card-solid: #FFFFFF;
    --text-primary: #1C1C1E;
    --text-secondary: #636366;
    --text-tertiary: #8E8E93;
    --border-subtle: rgba(60, 60, 67, 0.12);
    --border-strong: rgba(60, 60, 67, 0.22);
    --ios-blue: #007AFF;
    --ios-blue-hover: #0062CC;
    --ios-green: #34C759;
    --ios-orange: #FF9500;
    --ios-red: #FF3B30;
    --ios-indigo: #5856D6;
    --shadow-card: 0 4px 20px rgba(0, 0, 0, 0.05);
    --shadow-elevated: 0 8px 30px rgba(0, 0, 0, 0.09);
    --radius-card: 16px;
    --radius-btn: 10px;
    --font-stack: -apple-system, BlinkMacSystemFont, "SF Pro Text", "SF Pro Display", "Helvetica Neue", Helvetica, Arial, sans-serif;
}

[data-theme="dark"] {
    --bg-primary: #000000;
    --bg-card: rgba(28, 28, 30, 0.88);
    --bg-card-solid: #1C1C1E;
    --text-primary: #FFFFFF;
    --text-secondary: #8E8E93;
    --text-tertiary: #636366;
    --border-subtle: rgba(255, 255, 255, 0.12);
    --border-strong: rgba(255, 255, 255, 0.22);
    --shadow-card: 0 4px 24px rgba(0, 0, 0, 0.4);
    --shadow-elevated: 0 8px 36px rgba(0, 0, 0, 0.6);
}

* { box-sizing: border-box; margin: 0; padding: 0; font-family: var(--font-stack); -webkit-font-smoothing: antialiased; }
body { background-color: var(--bg-primary); color: var(--text-primary); min-height: 100vh; padding-bottom: 60px; }

.ios-nav {
    position: sticky; top: 0; z-index: 100;
    background: var(--bg-card); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--border-subtle); padding: 14px 24px;
    display: flex; align-items: center; justify-content: space-between;
}
.nav-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text-primary); font-weight: 700; font-size: 17px; letter-spacing: -0.3px; }
.nav-logo-icon { width: 32px; height: 32px; border-radius: 9px; background: linear-gradient(135deg, var(--ios-blue) 0%, var(--ios-indigo) 100%); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 15px; }
.nav-actions { display: flex; align-items: center; gap: 10px; }

.container { max-width: 1200px; margin: 24px auto; padding: 0 20px; }
.ios-card {
    background: var(--bg-card); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--border-subtle); border-radius: var(--radius-card);
    box-shadow: var(--shadow-card); padding: 22px; margin-bottom: 20px;
}
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px; }
.section-title { font-size: 18px; font-weight: 700; letter-spacing: -0.3px; display: flex; align-items: center; gap: 8px; }
.section-desc { font-size: 13px; color: var(--text-secondary); margin-top: 3px; }

.flash-banner {
    padding: 12px 18px; border-radius: 12px; margin-bottom: 20px;
    font-size: 13.5px; font-weight: 500; display: flex; align-items: center; justify-content: space-between;
}
.flash-success { background: rgba(52, 199, 89, 0.15); color: #288C41; border: 1px solid rgba(52, 199, 89, 0.3); }
.flash-error { background: rgba(255, 59, 48, 0.15); color: #D7261E; border: 1px solid rgba(255, 59, 48, 0.3); }

/* Buyer Hero */
.buyer-hero {
    background: linear-gradient(135deg, rgba(0,122,255,0.08) 0%, rgba(88,86,214,0.10) 100%);
    border: 1px solid rgba(0,122,255,0.18); border-radius: var(--radius-card);
    padding: 22px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;
}
.buyer-hero-left h1 { font-size: 22px; font-weight: 700; margin-bottom: 4px; letter-spacing: -0.4px; }
.buyer-hero-left p { font-size: 13.5px; color: var(--text-secondary); }

/* Delivery Pack Card */
.delivery-pack-card {
    background: linear-gradient(135deg, rgba(52,199,89,0.08) 0%, rgba(0,122,255,0.08) 100%);
    border: 1px solid rgba(52,199,89,0.28); border-radius: var(--radius-card);
    padding: 20px; margin-bottom: 20px;
}
.pack-code-block {
    background: var(--bg-card-solid); border: 1px solid var(--border-subtle);
    border-radius: 10px; padding: 14px; font-family: ui-monospace, Menlo, Monaco, Consolas, monospace;
    font-size: 12.5px; line-height: 1.5; color: var(--text-primary); margin-top: 10px; white-space: pre-wrap;
}

/* Form Controls */
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
.field-group { display: flex; flex-direction: column; gap: 6px; }
.field-label { font-size: 12px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
.ios-input, .ios-select, .ios-textarea {
    width: 100%; padding: 10px 14px; font-size: 14px; color: var(--text-primary);
    background: var(--bg-card-solid); border: 1px solid var(--border-strong);
    border-radius: 10px; outline: none; transition: border-color 0.2s;
}
.ios-input:focus, .ios-select:focus, .ios-textarea:focus { border-color: var(--ios-blue); box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.15); }
.ios-textarea { min-height: 100px; font-family: ui-monospace, Menlo, Monaco, Consolas, monospace; font-size: 12.5px; line-height: 1.45; resize: vertical; }

.ios-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 8px 16px; font-size: 13.5px; font-weight: 600; border-radius: var(--radius-btn);
    border: none; cursor: pointer; text-decoration: none; transition: all 0.18s;
}
.btn-primary { background: var(--ios-blue); color: #fff; }
.btn-primary:hover { background: var(--ios-blue-hover); }
.btn-secondary { background: rgba(120, 120, 128, 0.16); color: var(--text-primary); }
.btn-secondary:hover { background: rgba(120, 120, 128, 0.24); }
.btn-success { background: var(--ios-green); color: #fff; }
.btn-danger { background: var(--ios-red); color: #fff; }
.btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 8px; }

/* Segmented Navigation */
.segmented-bar {
    display: flex; gap: 4px; padding: 4px; background: rgba(120, 120, 128, 0.12);
    border-radius: 12px; margin-bottom: 22px; overflow-x: auto;
}
.seg-btn {
    flex: 1; min-width: 130px; padding: 9px 14px; font-size: 13px; font-weight: 600;
    color: var(--text-secondary); background: transparent; border: none; border-radius: 9px;
    cursor: pointer; transition: all 0.2s; white-space: nowrap; text-align: center;
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
}
.seg-btn.active { background: var(--bg-card-solid); color: var(--text-primary); box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
.tab-pane { display: none; }
.tab-pane.active { display: block; }

/* KPI Grid */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px; }
.kpi-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.kpi-value { font-size: 28px; font-weight: 800; letter-spacing: -0.6px; margin-bottom: 4px; }
.kpi-label { font-size: 12.5px; color: var(--text-secondary); font-weight: 500; }
.squircle-icon { width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; color: #fff; }

/* Table */
.table-wrap { overflow-x: auto; }
.ios-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; }
.ios-table th { padding: 12px 14px; font-size: 11.5px; font-weight: 700; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-subtle); }
.ios-table td { padding: 13px 14px; border-bottom: 1px solid var(--border-subtle); vertical-align: middle; }
.ios-table tr:last-child td { border-bottom: none; }

.badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 6px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
.badge-green { background: rgba(52, 199, 89, 0.14); color: var(--ios-green); }
.badge-orange { background: rgba(255, 149, 0, 0.16); color: var(--ios-orange); }
.badge-red { background: rgba(255, 59, 48, 0.14); color: var(--ios-red); }
.badge-blue { background: rgba(0, 122, 255, 0.14); color: var(--ios-blue); }
.mono-code { font-family: ui-monospace, Menlo, Monaco, Consolas, monospace; font-size: 12px; background: rgba(120, 120, 128, 0.1); padding: 2px 6px; border-radius: 5px; }

/* iOS Switch */
.ios-switch-row {
    display: flex; justify-content: space-between; align-items: center; padding: 14px 0;
    border-bottom: 1px solid var(--border-subtle); gap: 16px;
}
.ios-switch-row:last-child { border-bottom: none; }
.switch-meta { display: flex; align-items: center; gap: 14px; }
.switch-title { font-size: 14px; font-weight: 600; margin-bottom: 2px; }
.switch-sub { font-size: 12px; color: var(--text-secondary); line-height: 1.35; max-width: 680px; }
.ui-switch { position: relative; width: 50px; height: 30px; display: inline-block; flex-shrink: 0; }
.ui-switch input { opacity: 0; width: 0; height: 0; }
.ui-slider {
    position: absolute; cursor: pointer; inset: 0; background-color: rgba(120, 120, 128, 0.28);
    transition: 0.3s; border-radius: 30px;
}
.ui-slider:before {
    position: absolute; content: ""; height: 26px; width: 26px; left: 2px; bottom: 2px;
    background-color: white; transition: 0.3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.25);
}
.ui-switch input:checked + .ui-slider { background-color: var(--ios-green); }
.ui-switch input:checked + .ui-slider:before { transform: translateX(20px); }

/* SVG Alignment */
.svg-icon { vertical-align: middle; display: inline-block; flex-shrink: 0; }

/* Responsive Mobile Optimization */
@media (max-width: 768px) {
    .ios-nav { padding: 10px 14px; flex-wrap: wrap; gap: 8px; }
    .container { margin: 12px auto; padding: 0 10px; }
    .ios-card { padding: 16px; border-radius: 14px; }
    .buyer-hero { padding: 16px 14px; }
    .buyer-hero-left h1 { font-size: 19px; }
    .form-grid { grid-template-columns: 1fr !important; gap: 12px; }
    .kpi-grid { grid-template-columns: 1fr !important; gap: 12px; }
    .segmented-bar { padding: 3px; gap: 2px; }
    .seg-btn { min-width: 105px; padding: 8px 10px; font-size: 12px; }
    .ios-btn { padding: 9px 14px; font-size: 13px; min-height: 40px; }
    .ios-switch-row { flex-direction: row; justify-content: space-between; align-items: center; }
    .table-wrap { font-size: 12px; }
    pre { font-size: 11px !important; }
}
</style>
</head>
<body>

<header class="ios-nav">
    <a href="index.php" class="nav-brand">
        <div class="nav-logo-icon"><?= fbox_svg('shield', 18, '#fff') ?></div>
        <span>FEATURESTIC BOX <?= $role === 'buyer' ? '• Customer Console' : ($role === 'manager' ? '• Staff Manager Console' : '• Control Hub') ?></span>
    </a>
    <div class="nav-actions">
        <button type="button" class="ios-btn btn-secondary btn-sm" id="themeBtn" onclick="toggleTheme()">
            <?= fbox_svg('eye', 13) ?> <span>Appearance</span>
        </button>
        <?php if ($isLoggedIn): ?>
            <span class="badge badge-blue"><?= fbox_svg('user', 12) ?> <?= htmlspecialchars($_SESSION['fbox_user_display'] ?? '') ?></span>
            <a href="index.php?logout=1" class="ios-btn btn-secondary btn-sm">Sign Out</a>
        <?php endif; ?>
    </div>
</header>

<?php if (!$isLoggedIn): ?>
<!-- UNIFIED LOGIN FORM -->
<div style="min-height:75vh;display:flex;align-items:center;justify-content:center;padding:20px;">
    <div class="ios-card" style="width:100%;max-width:400px;box-shadow:var(--shadow-elevated);">
        <div style="text-align:center;margin-bottom:22px;">
            <div class="nav-logo-icon" style="width:48px;height:48px;margin:0 auto 12px;border-radius:13px;">
                <?= fbox_svg('shield', 26, '#fff') ?>
            </div>
            <h2 style="font-size:21px;font-weight:700;letter-spacing:-0.4px;">Featurestic Box</h2>
            <p style="font-size:13px;color:var(--text-secondary);margin-top:4px;">Sign in with Admin credentials or your Buyer account</p>
        </div>
        <?php if ($flash !== ''): ?>
            <div class="flash-banner flash-error" style="margin-bottom:16px;">
                <span><?= htmlspecialchars($flash) ?></span>
            </div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="form_action" value="login">
            <div class="field-group" style="margin-bottom:14px;">
                <label class="field-label">Username, License Key or Buyer ID</label>
                <input type="text" name="username" class="ios-input" placeholder="e.g. admin or client_name or FBOX-VIP-..." required>
            </div>
            <div class="field-group" style="margin-bottom:20px;">
                <label class="field-label">Password</label>
                <input type="password" name="password" class="ios-input" placeholder="Enter password" required>
            </div>
            <button type="submit" class="ios-btn btn-primary" style="width:100%;padding:12px;">Sign In to Console</button>
        </form>
    </div>
</div>

<?php elseif ($role === 'buyer' && $myKey): ?>
<!-- CUSTOMER / BUYER PORTAL (ISOLATED TENANT VIEW) -->
<main class="container">
    <?php if ($flash !== ''): ?>
        <div class="flash-banner <?= $flashType === 'error' ? 'flash-error' : 'flash-success' ?>">
            <span><?= htmlspecialchars($flash) ?></span>
            <span style="font-size:12px;opacity:0.75;"><?= fbox_svg('check', 13) ?> Synced with Gateway</span>
        </div>
    <?php endif; ?>

    <!-- Auto-Ban Policy & Banned Devices Management (Buyer Control) -->
    <?php if ((int)($myKey['buyer_can_toggle_autoban'] ?? 1) === 1 || (int)($myKey['buyer_can_unban'] ?? 1) === 1): ?>
    <div class="ios-card">
        <div class="section-header">
            <div>
                <div class="section-title"><?= fbox_svg('shield', 18, 'var(--ios-red)') ?> Anti-Crack Auto-Ban &amp; Unban Controls</div>
                <div class="section-desc">Manage whether device tampering auto-bans customer phones, and unban flagged devices with 1 click</div>
            </div>
            <?php if ((int)($myKey['buyer_can_unban'] ?? 1) === 1 && !empty($myBannedDevices)): ?>
            <form method="post" style="display:inline;margin-right:8px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                <input type="hidden" name="form_action" value="buyer_unban_all">
                <button type="submit" class="ios-btn btn-success btn-sm" onclick="return confirm('Unban ALL devices flagged under your license key?');">
                    <?= fbox_svg('check', 12, '#fff') ?> 1-Click Unban All Devices (<?= count($myBannedDevices) ?>)
                </button>
            </form>
            <?php endif; ?>
            <?php if ((int)($myKey['buyer_can_toggle_autoban'] ?? 1) === 1): ?>
            <form method="post" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                <input type="hidden" name="form_action" value="buyer_toggle_autoban">
                <button type="submit" class="ios-btn <?= (int)($myKey['buyer_autoban_enabled'] ?? 1) === 1 ? 'btn-danger' : 'btn-ghost' ?>">
                    <?= fbox_svg('ban', 14, '#fff') ?> Auto-Ban: <?= (int)($myKey['buyer_autoban_enabled'] ?? 1) === 1 ? 'ENABLED' : 'DISABLED (Logs Only)' ?>
                </button>
            </form>
            <?php endif; ?>
        </div>

        <?php if ((int)($myKey['buyer_can_unban'] ?? 1) === 1): ?>
        <div style="font-weight:600;font-size:13px;margin-bottom:8px;color:var(--text-secondary);">Devices Banned on Your License (Click Unban to Restore Access):</div>
        <div class="table-wrap">
            <table class="ios-table">
                <thead>
                    <tr>
                        <th>Device Model</th>
                        <th>Hardware HWID</th>
                        <th>Ban Reason</th>
                        <th>Banned Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($myBannedDevices)): ?>
                    <tr><td colspan="5" style="color:var(--text-tertiary);text-align:center;padding:16px;">No devices currently banned on your license.</td></tr>
                <?php else: foreach ($myBannedDevices as $bd): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($bd['device_model'] ?: 'Android Device') ?></strong></td>
                        <td><span class="mono-code"><?= htmlspecialchars(substr($bd['hwid'], 0, 16)) ?>...</span></td>
                        <td><span class="badge badge-red"><?= htmlspecialchars($bd['ban_reason']) ?></span></td>
                        <td><?= date('d M H:i', (int)$bd['banned_at']) ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                                <input type="hidden" name="form_action" value="buyer_unban_device">
                                <input type="hidden" name="ban_id" value="<?= (int)$bd['id'] ?>">
                                <button type="submit" class="ios-btn btn-success btn-sm"><?= fbox_svg('check', 12, '#fff') ?> 1-Click Unban</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Buyer Hero Card -->
    <?php
    $myPlanTier = strtoupper((string)($myKey['plan_tier'] ?? 'enterprise'));
    $myEnt = fbox_get_key_entitlements($myKey);
    ?>
    <div class="buyer-hero">
        <div class="buyer-hero-left">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                <h1 style="margin:0;">Welcome, <?= htmlspecialchars($myKey['buyer_username'] ?: ($myKey['label'] ?: 'Developer')) ?></h1>
                <span class="badge badge-purple" style="font-size:11.5px;padding:4px 10px;"><?= htmlspecialchars($myPlanTier) ?> PLAN</span>
            </div>
            <p>B2B SDK Developer Hub • Active SDK API Key: <code style="color:#fff;font-weight:700;"><?= htmlspecialchars($myKey['license_key']) ?></code></p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php if ((int)($myKey['buyer_can_roll_key'] ?? 1) === 1): ?>
            <form method="post" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                <input type="hidden" name="form_action" value="buyer_roll_sdk_key">
                <button type="submit" class="ios-btn btn-secondary" style="background:rgba(255,255,255,0.16);color:#fff;border:1px solid rgba(255,255,255,0.3);" onclick="return confirm('Roll / Regenerate your Master SDK API Key? Your old SDK key will immediately stop working in all old Loaders, and a new SDK key will be issued with your exact remaining subscription days!');">
                    <?= fbox_svg('key', 14, '#fff') ?> 1-Click Roll / Regenerate SDK Key
                </button>
            </form>
            <?php endif; ?>
            <form method="post" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                <input type="hidden" name="form_action" value="buyer_reset_hwid">
                <button type="submit" class="ios-btn btn-primary" onclick="return confirm('Reset Bound Loader APK Signature & HWID slots?');">
                    <?= fbox_svg('refresh', 14, '#fff') ?> Reset Bound APK &amp; HWID
                </button>
            </form>
        </div>
    </div>

    <!-- 1-Click Java Integration Code Box -->
    <div class="ios-card" style="border-left:4px solid var(--ios-blue);">
        <div class="section-header">
            <div>
                <div class="section-title"><?= fbox_svg('terminal', 18, 'var(--ios-blue)') ?> Connect Your Loader App (1-Click Integration)</div>
                <div class="section-desc">Copy and paste this code directly into your Android Studio Loader project. Pre-filled with your gateway URL, loader ID, and key!</div>
            </div>
            <button type="button" class="ios-btn btn-primary" onclick="copyIntegrationSnippet()">
                <?= fbox_svg('copy', 14, '#fff') ?> Copy All Java Code
            </button>
        </div>

        <div style="background:var(--bg-primary);border:1px solid var(--border-subtle);border-radius:12px;padding:14px;position:relative;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <span style="font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;">BoxApplication.java • 1-Step B2B SDK Setup (Keep Your Own Loader Login Panel!)</span>
                <button type="button" class="ios-btn btn-secondary btn-sm" onclick="copyText('boxAppCode', this)">
                    <?= fbox_svg('copy', 12) ?> Copy BoxApplication Code
                </button>
            </div>
            <pre id="boxAppCode" style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;line-height:1.45;color:var(--text-primary);overflow-x:auto;white-space:pre-wrap;">// Put inside BoxApplication.onCreate() — Connects FeaturesticBox.aar to your SDK License!
// Your own Loader Login (LogAct) and your own Customer Key Panel work independently for your players!
com.featurestic.box.FeaturesticSdkConfig.create()
        .setServerGatewayUrl("<?= htmlspecialchars($autoApiUrl) ?>")
        .setServerApiSecret("<?= htmlspecialchars($settings['shared_api_secret'] ?? FBOX_DEFAULT_API_SECRET) ?>")
        .setSdkLicenseKey("<?= htmlspecialchars($myKey['license_key']) ?>") // <--- Your B2B SDK Developer Key
        .setLoaderId("<?= htmlspecialchars(!empty($myLoaders) ? $myLoaders[0]['loader_id'] : 'loader_1') ?>")
        .setAutoBanOnTamper(true)
        .setBlockHttpProxy(true)
        .apply();</pre>

            <div style="display:flex;justify-content:space-between;align-items:center;margin:14px 0 8px;">
                <span style="font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;">Optional: Reading Dynamic Server Offsets Inside Your Loader (If Needed)</span>
                <button type="button" class="ios-btn btn-secondary btn-sm" onclick="copyText('logActCode', this)">
                    <?= fbox_svg('copy', 12) ?> Copy Offset Helper
                </button>
            </div>
            <pre id="logActCode" style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;line-height:1.45;color:var(--text-primary);overflow-x:auto;white-space:pre-wrap;">// Your Loader launches games normally via FeaturesticBoxCore.get().launchApk("com.pubg.imobile", 0);
// The SDK automatically verifies your SDK License Key, binds your Loader APK slot, applies Flagship Spoofing,
// Streamproof FLAG_SECURE, Telemetry Silencer, and injects your Cloud .so!
long guardOffset = com.featurestic.box.FeaturesticSdkConfig.getServerOffset("offset_guard_hook", 0L);</pre>
        </div>
    </div>

    <!-- License Key KPI Grid -->
    <div class="kpi-grid">
        <div class="ios-card" style="margin-bottom:0;">
            <div class="kpi-top">
                <span class="badge <?= $myKey['status'] === 'active' ? 'badge-green' : 'badge-orange' ?>">
                    <?= htmlspecialchars(strtoupper($myKey['status'])) ?>
                </span>
                <div class="squircle-icon" style="background:var(--ios-blue);"><?= fbox_svg('key', 18, '#fff') ?></div>
            </div>
            <div class="kpi-value" style="font-size:18px;font-family:monospace;"><?= htmlspecialchars($myKey['license_key']) ?></div>
            <div class="kpi-label">Your License Key</div>
        </div>

        <div class="ios-card" style="margin-bottom:0;">
            <div class="kpi-top">
                <span class="badge badge-blue">HWID Binding</span>
                <div class="squircle-icon" style="background:var(--ios-indigo);"><?= fbox_svg('device', 18, '#fff') ?></div>
            </div>
            <?php $hwids = json_decode((string)$myKey['bound_hwids_json'], true) ?: []; ?>
            <div class="kpi-value"><?= count($hwids) ?> / <?= (int)$myKey['max_devices'] ?></div>
            <div class="kpi-label">Bound Devices</div>
        </div>

        <div class="ios-card" style="margin-bottom:0;">
            <div class="kpi-top">
                <span class="badge badge-orange"><?= (int)$myKey['duration_days'] ?> Days</span>
                <div class="squircle-icon" style="background:var(--ios-green);"><?= fbox_svg('clock', 18, '#fff') ?></div>
            </div>
            <div class="kpi-value" style="font-size:17px;">
                <?= (int)$myKey['expires_at'] > 0 ? date('d M Y, H:i', (int)$myKey['expires_at']) : 'Not Activated Yet' ?>
            </div>
            <div class="kpi-label">License Expiry Date</div>
        </div>

        <div class="ios-card" style="margin-bottom:0;">
            <div class="kpi-top">
                <span class="badge badge-green"><?= $mySession ? 'Online' : 'Idle' ?></span>
                <div class="squircle-icon" style="background:var(--ios-orange);"><?= fbox_svg('bolt', 18, '#fff') ?></div>
            </div>
            <div class="kpi-value" style="font-size:17px;"><?= htmlspecialchars($myKey['last_device_model'] ?: 'No device yet') ?></div>
            <div class="kpi-label">Active Device Model</div>
        </div>
    </div>

    <!-- Authorized Games Bar -->
    <div class="ios-card">
        <div class="section-title" style="margin-bottom:10px;">
            <?= fbox_svg('game', 18, 'var(--ios-indigo)') ?> Authorized Games for Your License
        </div>
        <div>
            <?php
            $myGames = json_decode((string)($myKey['allowed_games_json'] ?? '["*"]'), true) ?: ['*'];
            if (in_array('*', $myGames, true)):
            ?>
                <span class="badge badge-green" style="font-size:12px;padding:6px 12px;"><?= fbox_svg('check', 12) ?> Universal Access (Any Android Game Supported)</span>
            <?php else:
                $gameLabels = [
                    'com.pubg.imobile' => 'BGMI',
                    'com.tencent.ig' => 'PUBG Global',
                    'com.pubg.krmobile' => 'PUBG KR',
                    'com.vng.pubgmobile' => 'PUBG VN',
                    'com.dts.freefireth' => 'Free Fire',
                    'com.dts.freefiremax' => 'Free Fire MAX',
                    'com.activision.callofduty.shooter' => 'COD Mobile'
                ];
                foreach ($myGames as $mg):
                    $lbl = $gameLabels[$mg] ?? $mg;
            ?>
                <span class="badge badge-blue" style="font-size:12px;padding:6px 12px;margin-right:6px;"><?= fbox_svg('check', 12) ?> <?= htmlspecialchars($lbl) ?> (<?= htmlspecialchars($mg) ?>)</span>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Multi-Loader Profiles Manager -->
    <div class="ios-card">
        <div class="section-header">
            <div>
                <div class="section-title"><?= fbox_svg('device', 18, 'var(--ios-blue)') ?> My Loaders &amp; App Profiles</div>
                <div class="section-desc">Run multiple different loader APKs under your single license. Each loader has its own ID, game, .so link, and offsets.</div>
            </div>
            <div>
                <?php
                $mySlotsAllowed = (int)($myKey['max_loader_slots'] ?? 1);
                $mySlotsUsed    = count($myLoaders ?? []);
                $canAddLoader   = ($mySlotsAllowed === 0 || $mySlotsUsed < $mySlotsAllowed);
                ?>
                <span class="badge <?= $canAddLoader ? 'badge-green' : 'badge-orange' ?>" style="margin-right:8px;font-size:12px;padding:6px 12px;">
                    Slots: <?= $mySlotsUsed ?> / <?= $mySlotsAllowed === 0 ? 'Unlimited' : $mySlotsAllowed ?>
                </span>
                <?php if ($canAddLoader): ?>
                    <button type="button" class="ios-btn btn-primary btn-sm" onclick="toggleAddLoaderForm()">
                        <?= fbox_svg('plus', 12, '#fff') ?> Create New Loader
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add New Loader Form -->
        <div id="addLoaderBox" style="display:none;background:var(--bg-primary);border:1px solid var(--border-subtle);border-radius:12px;padding:16px;margin-bottom:18px;">
            <div style="font-weight:700;font-size:14px;margin-bottom:12px;color:var(--ios-blue);">Configure New Loader Profile</div>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                <input type="hidden" name="form_action" value="buyer_create_loader">
                <div class="field-group">
                    <label class="field-label">Loader Name</label>
                    <input type="text" name="loader_name" class="ios-input" placeholder="e.g. BGMI VIP 64-Bit" required>
                </div>
                <div class="field-group">
                    <label class="field-label">Loader ID (.setLoaderId in Java)</label>
                    <input type="text" name="loader_id" class="ios-input" placeholder="e.g. bgmi_vip" required>
                </div>
                <div class="field-group">
                    <label class="field-label">Target Game Package</label>
                    <input type="text" name="target_package" id="targetPkgInput" class="ios-input" placeholder="e.g. com.pubg.imobile or *">
                    <div style="display:flex;gap:4px;flex-wrap:wrap;margin-top:4px;">
                        <button type="button" class="ios-btn btn-secondary btn-sm" onclick="setPkg('com.pubg.imobile')">BGMI</button>
                        <button type="button" class="ios-btn btn-secondary btn-sm" onclick="setPkg('com.tencent.ig')">PUBG GL</button>
                        <button type="button" class="ios-btn btn-secondary btn-sm" onclick="setPkg('com.pubg.krmobile')">PUBG KR</button>
                        <button type="button" class="ios-btn btn-secondary btn-sm" onclick="setPkg('com.dts.freefireth')">Free Fire</button>
                        <button type="button" class="ios-btn btn-secondary btn-sm" onclick="setPkg('*')">Universal (*)</button>
                    </div>
                </div>
                <div class="field-group">
                    <label class="field-label">Custom 64-Bit .so URL (GitHub Raw)</label>
                    <input type="text" name="loader_plugin_url_64" class="ios-input" placeholder="https://raw.githubusercontent.com/.../lib64.so">
                </div>
                <div class="field-group">
                    <label class="field-label">Custom 32-Bit .so URL (GitHub Raw)</label>
                    <input type="text" name="loader_plugin_url_32" class="ios-input" placeholder="https://raw.githubusercontent.com/.../lib32.so">
                </div>
                <div class="field-group" style="grid-column:1/-1;">
                    <label class="field-label">Dedicated Offsets JSON for this Loader</label>
                    <textarea name="loader_offsets_json" class="ios-textarea" style="min-height:70px;">{\n  \"offset_guard_hook\": \"0x0\",\n  \"offset_ace_worker\": \"0x0\"\n}</textarea>
                </div>
                <div class="field-group" style="grid-column:1/-1;justify-content:flex-end;flex-direction:row;gap:8px;">
                    <button type="button" class="ios-btn btn-secondary btn-sm" onclick="toggleAddLoaderForm()">Cancel</button>
                    <button type="submit" class="ios-btn btn-primary btn-sm">Save Profile</button>
                </div>
            </form>
        </div>

        <!-- Registered Loaders List -->
        <?php if (empty($myLoaders)): ?>
            <div style="color:var(--text-tertiary);text-align:center;padding:16px;font-size:13px;">
                No dedicated loader profiles configured yet. Click <b>Create New Loader</b> above to set up your apps.
            </div>
        <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:14px;margin-bottom:14px;">
                <?php foreach ($myLoaders as $lp): ?>
                    <div style="background:var(--bg-card-solid);border:1px solid var(--border-subtle);border-radius:12px;padding:16px;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
                            <div>
                                <div style="font-weight:700;font-size:15px;"><?= htmlspecialchars($lp['loader_name']) ?></div>
                                <div style="font-size:12px;color:var(--text-tertiary);font-family:monospace;margin-top:2px;">
                                    ID: <span class="badge badge-blue"><?= htmlspecialchars($lp['loader_id']) ?></span>
                                    <?php if ($lp['target_package'] !== ''): ?>
                                        | Pkg: <?= htmlspecialchars($lp['target_package']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="display:flex;gap:6px;">
                                <?php if ((int)($myKey['buyer_can_roll_key'] ?? 1) === 1): ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Roll Sub-API Key for this Loader? Any old APK using the previous sub-key will stop working immediately.');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                                    <input type="hidden" name="form_action" value="buyer_roll_loader_subkey">
                                    <input type="hidden" name="profile_id" value="<?= (int)$lp['id'] ?>">
                                    <button type="submit" class="ios-btn btn-secondary btn-sm" title="Rotate Sub-API Key"><?= fbox_svg('refresh', 12) ?> Roll Key</button>
                                </form>
                                <?php endif; ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this loader profile and revoke its Sub-API Key?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                                    <input type="hidden" name="form_action" value="buyer_delete_loader">
                                    <input type="hidden" name="profile_id" value="<?= (int)$lp['id'] ?>">
                                    <button type="submit" class="ios-btn btn-danger btn-sm"><?= fbox_svg('trash', 12, '#fff') ?> Delete</button>
                                </form>
                            </div>
                        </div>
                        <?php $dispSubKey = !empty($lp['sub_api_key']) ? (string)$lp['sub_api_key'] : (string)$myKey['license_key']; ?>
                        <div style="font-size:11.5px;background:rgba(0,122,255,0.07);border:1px solid rgba(0,122,255,0.18);border-radius:8px;padding:8px 10px;margin-bottom:6px;font-family:monospace;">
                            <div style="color:var(--text-secondary);font-size:10.5px;margin-bottom:2px;">Dedicated Loader Sub-API Key (Shares Master Expiry):</div>
                            <strong><?= htmlspecialchars($dispSubKey) ?></strong>
                        </div>
                        <div style="font-size:12px;background:rgba(120,120,128,0.08);border-radius:8px;padding:8px 10px;margin-bottom:8px;font-family:monospace;">
                            .setSdkLicenseKey("<?= htmlspecialchars($dispSubKey) ?>").setLoaderId("<?= htmlspecialchars($lp['loader_id']) ?>")
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Default OTA .so Plugins (Direct or GitHub Raw) -->
    <div class="ios-card">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
            <input type="hidden" name="form_action" value="buyer_save_plugin">
            <div class="section-header">
                <div>
                    <div class="section-title"><?= fbox_svg('globe', 18, 'var(--ios-blue)') ?> Default Cloud OTA .so Plugins</div>
                    <div class="section-desc">Pushed automatically to your licensed apps. Supports GitHub blob/raw links automatically!</div>
                </div>
                <button type="submit" class="ios-btn btn-primary">Save Plugins</button>
            </div>
            <div class="form-grid">
                <div class="field-group">
                    <label class="field-label">64-Bit .so Download URL (arm64-v8a)</label>
                    <input type="text" name="custom_plugin_url_64" class="ios-input"
                           value="<?= htmlspecialchars($myKey['custom_plugin_url_64'] ?? '') ?>"
                           placeholder="https://raw.githubusercontent.com/user/repo/main/libPlugin.so">
                </div>
                <div class="field-group">
                    <label class="field-label">32-Bit .so Download URL (armeabi-v7a)</label>
                    <input type="text" name="custom_plugin_url_32" class="ios-input"
                           value="<?= htmlspecialchars($myKey['custom_plugin_url_32'] ?? '') ?>"
                           placeholder="https://raw.githubusercontent.com/user/repo/main/libPlugin_32.so">
                </div>
                <div class="field-group">
                    <label class="field-label">Optional SHA-256 Checksum</label>
                    <input type="text" name="custom_plugin_sha256" class="ios-input"
                           value="<?= htmlspecialchars($myKey['custom_plugin_sha256'] ?? '') ?>"
                           placeholder="64-char SHA-256 hex">
                </div>
            </div>
        </form>
    </div>

    <!-- Circuit Breaker Tripped Alert (If Active) -->
    <?php if ((int)($myKey['circuit_breaker_tripped'] ?? 0) === 1): ?>
    <div class="ios-card" style="border:2px solid var(--ios-red);background:rgba(255,59,48,0.06);">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
                <div style="font-weight:800;font-size:15px;color:var(--ios-red);display:flex;align-items:center;gap:6px;">
                    <?= fbox_svg('alert', 18, 'var(--ios-red)') ?> Auto-Shield Circuit Breaker Tripped (Logins Paused for Safety)
                </div>
                <div style="font-size:12.5px;color:var(--text-secondary);margin-top:4px;">
                    3 or more abnormal crashes/bans occurred within 10 minutes. Logins were automatically paused to protect your users' main game accounts. Verify your .so offsets, then click Reset below to resume.
                </div>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                <input type="hidden" name="form_action" value="buyer_reset_circuit_breaker">
                <button type="submit" class="ios-btn btn-primary"><?= fbox_svg('check', 14, '#fff') ?> Reset Auto-Shield &amp; Resume Loader</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- 100% Panel-Controlled Features, Stealth, Decoy & Hardware Spoofer Controller -->
    <div class="ios-card">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
            <input type="hidden" name="form_action" value="buyer_save_features">
            <div class="section-header">
                <div>
                    <div class="section-title"><?= fbox_svg('gear', 18, 'var(--ios-indigo)') ?> Panel-Side Features, Stealth &amp; Flagship Device Spoofer</div>
                    <div class="section-desc">100% controlled from this panel — end-users see zero cheap switches in the app. Everything you enable here applies automatically to your users!</div>
                </div>
                <button type="submit" class="ios-btn btn-primary">Save All Panel Controls</button>
            </div>

            <?php
            $myFeats = json_decode((string)($myKey['custom_features_json'] ?? '{}'), true) ?: [];
            $featFloatingMenu = !empty($myFeats['floating_menu']);
            $featEsp = !isset($myFeats['esp']) || !empty($myFeats['esp']);
            $featAimbot = !isset($myFeats['aimbot']) || !empty($myFeats['aimbot']);
            $featBullet = !isset($myFeats['bullet_track']) || !empty($myFeats['bullet_track']);
            $featStream = !isset($myFeats['streamproof']) || !empty($myFeats['streamproof']);
            $featSilencer = !isset($myKey['telemetry_silencer_enabled']) || (int)$myKey['telemetry_silencer_enabled'] === 1;
            $featRamDiet = !isset($myKey['ram_diet_enabled']) || (int)$myKey['ram_diet_enabled'] === 1;
            $featBreaker = (int)($myKey['ban_wave_breaker_enabled'] ?? 0) === 1;
            $breakerThr  = (int)($myKey['ban_wave_threshold'] ?? 5);
            $featHoneypot = (int)($myKey['honeypot_on_tamper'] ?? 0) === 1;
            $featLockApkSig = !isset($myKey['lock_loader_apk_signature']) || (int)$myKey['lock_loader_apk_signature'] === 1;
            $curCountries = (string)($myKey['allowed_countries'] ?? 'ALL');
            $curPreset = (string)($myKey['custom_device_preset'] ?: 'ROG_PHONE_8');
            $curFps = (int)($myKey['custom_target_fps'] ?: 120);
            ?>

            <div class="ios-switch-row">
                <div class="switch-meta">
                    <div class="squircle-icon" style="background:var(--ios-indigo);"><?= fbox_svg('shield', 18, '#fff') ?></div>
                    <div>
                        <div class="switch-title">Streamer Mode / Screen Recording Shield (FLAG_SECURE)</div>
                        <div class="switch-sub">Hides loader and overlays from OBS, Discord screen share, and screen recording apps automatically (Panel-Controlled).</div>
                    </div>
                </div>
                <label class="ui-switch">
                    <input type="checkbox" name="feat_streamproof" value="1" <?= $featStream ? 'checked' : '' ?>>
                    <span class="ui-slider"></span>
                </label>
            </div>

            <div class="ios-switch-row">
                <div class="switch-meta">
                    <div class="squircle-icon" style="background:var(--ios-red);"><?= fbox_svg('terminal', 18, '#fff') ?></div>
                    <div>
                        <div class="switch-title">Game Anticheat Telemetry &amp; Crash Log Silencer</div>
                        <div class="switch-sub">Automatically wipes Krafton/Tencent crash logs (<code>UE4Game/Saved/Logs</code>, <code>TssPmp</code>, <code>ano_tmp</code>) every 45s in background.</div>
                    </div>
                </div>
                <label class="ui-switch">
                    <input type="checkbox" name="telemetry_silencer_enabled" value="1" <?= $featSilencer ? 'checked' : '' ?>>
                    <span class="ui-slider"></span>
                </label>
            </div>

            <div class="ios-switch-row">
                <div class="switch-meta">
                    <div class="squircle-icon" style="background:var(--ios-green);"><?= fbox_svg('bolt', 18, '#fff') ?></div>
                    <div>
                        <div class="switch-title">Low-End RAM Diet &amp; 120 FPS Thread Priority Booster</div>
                        <div class="switch-sub">Frees 150MB–350MB RAM on 4GB/6GB phones and boosts <code>RenderThread</code> / <code>RHIThread</code> priority for zero lag.</div>
                    </div>
                </div>
                <label class="ui-switch">
                    <input type="checkbox" name="ram_diet_enabled" value="1" <?= $featRamDiet ? 'checked' : '' ?>>
                    <span class="ui-slider"></span>
                </label>
            </div>

            <?php
            $featRamOnlySo  = !isset($myKey['ram_only_so_enabled']) || (int)$myKey['ram_only_so_enabled'] === 1;
            $featAnogsCloak = !isset($myKey['anogs_maps_cloak_enabled']) || (int)$myKey['anogs_maps_cloak_enabled'] === 1;
            ?>
            <div class="ios-switch-row">
                <div class="switch-meta">
                    <div class="squircle-icon" style="background:var(--ios-purple);"><?= fbox_svg('shield', 18, '#fff') ?></div>
                    <div>
                        <div class="switch-title">
                            RAM-Only Anonymous <code>.so</code> Loader (<code>memfd_create</code>) &amp; <code>libanogs.so</code> <code>/proc/self/maps</code> Cloak
                            <?php if (empty($myEnt['ent_ram_only_so'])): ?>
                                <span class="badge badge-orange" style="margin-left:6px;">Locked by Plan</span>
                            <?php endif; ?>
                        </div>
                        <div class="switch-sub">Loads your Cloud <code>.so</code> directly in RAM with zero disk footprint, scrubs ELF headers, and cloaks <code>/proc/self/maps</code> from <code>libanogs.so</code> scans.</div>
                    </div>
                </div>
                <div style="display:flex;gap:12px;align-items:center;">
                    <label style="font-size:12px;display:flex;align-items:center;gap:4px;">
                        <input type="checkbox" name="ram_only_so_enabled" value="1" <?= (!empty($myEnt['ent_ram_only_so']) && $featRamOnlySo) ? 'checked' : '' ?> <?= empty($myEnt['ent_ram_only_so']) ? 'disabled' : '' ?>> RAM-Only .so
                    </label>
                    <label style="font-size:12px;display:flex;align-items:center;gap:4px;">
                        <input type="checkbox" name="anogs_maps_cloak_enabled" value="1" <?= (!empty($myEnt['ent_ram_only_so']) && $featAnogsCloak) ? 'checked' : '' ?> <?= empty($myEnt['ent_ram_only_so']) ? 'disabled' : '' ?>> Anogs Maps Cloak
                    </label>
                </div>
            </div>

            <div class="ios-switch-row">
                <div class="switch-meta">
                    <div class="squircle-icon" style="background:var(--ios-orange);"><?= fbox_svg('alert', 18, '#fff') ?></div>
                    <div>
                        <div class="switch-title">24/7 Automated Ban-Wave Circuit Breaker (Optional Auto Panic Shield)</div>
                        <div class="switch-sub">100% Optional: Turn ON if you want the SDK to automatically pause game launches when abnormal crashes/bans spike within 10 minutes.</div>
                    </div>
                </div>
                <label class="ui-switch">
                    <input type="checkbox" name="ban_wave_breaker_enabled" value="1" <?= $featBreaker ? 'checked' : '' ?>>
                    <span class="ui-slider"></span>
                </label>
            </div>

            <div class="ios-switch-row">
                <div class="switch-meta">
                    <div class="squircle-icon" style="background:var(--ios-purple);"><?= fbox_svg('shield', 18, '#fff') ?></div>
                    <div>
                        <div class="switch-title">Silent Honeypot Fake Offsets Mode (Anti-Cracker Decoy Trap)</div>
                        <div class="switch-sub">When ON, if a cracker device is flagged/banned, the SDK silently feeds fake <code>0xDEAD0000</code> offsets instead of alerting the cracker!</div>
                    </div>
                </div>
                <label class="ui-switch">
                    <input type="checkbox" name="honeypot_on_tamper" value="1" <?= $featHoneypot ? 'checked' : '' ?>>
                    <span class="ui-slider"></span>
                </label>
            </div>

            <div class="ios-switch-row">
                <div class="switch-meta">
                    <div class="squircle-icon" style="background:var(--ios-blue);"><?= fbox_svg('lock', 18, '#fff') ?></div>
                    <div>
                        <div class="switch-title">Strict Loader APK Signature Slot Lock (Anti-Repackage &amp; Anti-Steal)</div>
                        <div class="switch-sub">Locks your SDK Slot to your Loader APK's exact signing certificate SHA-256 so nobody can mod your Loader APK or steal your SDK Key.</div>
                    </div>
                </div>
                <label class="ui-switch">
                    <input type="checkbox" name="lock_loader_apk_signature" value="1" <?= $featLockApkSig ? 'checked' : '' ?>>
                    <span class="ui-slider"></span>
                </label>
            </div>

            <?php
            $featAnogsCrc = !isset($myKey['anogs_crc_shield_enabled']) || (int)$myKey['anogs_crc_shield_enabled'] === 1;
            $featVpnCloak = !isset($myKey['cloak_vpn_interface']) || (int)$myKey['cloak_vpn_interface'] === 1;
            ?>
            <div class="ios-switch-row">
                <div class="switch-meta">
                    <div class="squircle-icon" style="background:var(--ios-indigo);"><?= fbox_svg('shield', 18, '#fff') ?></div>
                    <div>
                        <div class="switch-title">
                            <code>libanogs.so</code> Case-37 / Case-16 Memory Segment CRC Shield
                            <?php if (empty($myEnt['ent_ram_only_so'])): ?>
                                <span class="badge badge-orange" style="margin-left:6px;">Locked by Plan</span>
                            <?php endif; ?>
                        </div>
                        <div class="switch-sub">Normalizes <code>.text</code> code segment permissions (<code>r-xp</code>) and protects against <code>libanogs.so</code> Case-37 / Case-16 inline hook CRC scans.</div>
                    </div>
                </div>
                <label class="ui-switch">
                    <input type="checkbox" name="anogs_crc_shield_enabled" value="1" <?= (!empty($myEnt['ent_ram_only_so']) && $featAnogsCrc) ? 'checked' : '' ?> <?= empty($myEnt['ent_ram_only_so']) ? 'disabled' : '' ?>>
                    <span class="ui-slider"></span>
                </label>
            </div>

            <div class="ios-switch-row">
                <div class="switch-meta">
                    <div class="squircle-icon" style="background:var(--ios-blue);"><?= fbox_svg('globe', 18, '#fff') ?></div>
                    <div>
                        <div class="switch-title">Virtual Network Interface Cloak (Hide <code>tun0</code> / VPN / Gaming DNS from Game)</div>
                        <div class="switch-sub">Cloaks active <code>tun0</code> / <code>ppp0</code> / <code>wg0</code> interfaces inside the virtual container so the game always sees clean <code>wlan0</code> Wi-Fi.</div>
                    </div>
                </div>
                <label class="ui-switch">
                    <input type="checkbox" name="cloak_vpn_interface" value="1" <?= $featVpnCloak ? 'checked' : '' ?>>
                    <span class="ui-slider"></span>
                </label>
            </div>

            <!-- Panel-Side Hardware Spoofer & Calculator PIN Grid -->
            <div class="form-grid" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border-subtle);">
                <div class="field-group">
                    <label class="field-label">Fake Flagship Device Spoofer (Panel-Controlled)</label>
                    <select name="custom_device_preset" class="ios-select">
                        <option value="ROG_PHONE_8" <?= $curPreset === 'ROG_PHONE_8' ? 'selected' : '' ?>>ASUS ROG Phone 8 (Adreno 750 • Auto 120 FPS)</option>
                        <option value="IPAD_PRO_M4" <?= ($curPreset === 'IPAD_PRO_M4' || $curPreset === 'TAB_S9_ULTRA') ? 'selected' : '' ?>>iPad Pro M4 / Tab S9 Ultra (Wide View • 120 FPS)</option>
                        <option value="S24_ULTRA" <?= $curPreset === 'S24_ULTRA' ? 'selected' : '' ?>>Samsung Galaxy S24 Ultra (SD 8 Gen 3 • 120 FPS)</option>
                        <option value="REDMAGIC_9_PRO" <?= $curPreset === 'REDMAGIC_9_PRO' ? 'selected' : '' ?>>Nubia RedMagic 9 Pro (Gaming • 120 FPS)</option>
                        <option value="ONEPLUS_12" <?= $curPreset === 'ONEPLUS_12' ? 'selected' : '' ?>>OnePlus 12 Flagship (90 / 120 FPS)</option>
                        <option value="IQOO_12" <?= $curPreset === 'IQOO_12' ? 'selected' : '' ?>>iQOO 12 5G Esports Edition (120 FPS)</option>
                        <option value="XIAOMI_14_PRO" <?= $curPreset === 'XIAOMI_14_PRO' ? 'selected' : '' ?>>Xiaomi 14 Pro (120 FPS)</option>
                        <option value="NONE" <?= $curPreset === 'NONE' ? 'selected' : '' ?>>OFF — Use User's Real Phone Hardware</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label">Target Frame Rate Unlock</label>
                    <select name="custom_target_fps" class="ios-select">
                        <option value="120" <?= $curFps === 120 ? 'selected' : '' ?>>120 FPS (Extreme+ Flagship)</option>
                        <option value="90" <?= $curFps === 90 ? 'selected' : '' ?>>90 FPS (Smooth Competitive)</option>
                        <option value="60" <?= $curFps === 60 ? 'selected' : '' ?>>60 FPS (Battery Safe)</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label">Ban-Wave Circuit Breaker Threshold</label>
                    <select name="ban_wave_threshold" class="ios-select">
                        <option value="3" <?= $breakerThr === 3 ? 'selected' : '' ?>>3 Events in 10 Mins (Strict)</option>
                        <option value="5" <?= $breakerThr === 5 ? 'selected' : '' ?>>5 Events in 10 Mins (Balanced)</option>
                        <option value="10" <?= $breakerThr === 10 ? 'selected' : '' ?>>10 Events in 10 Mins (Relaxed)</option>
                        <option value="20" <?= $breakerThr === 20 ? 'selected' : '' ?>>20 Events in 10 Mins (High Volume)</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label">Geo-IP Country Lock (SDK Access Region)</label>
                    <select name="allowed_countries" class="ios-select">
                        <option value="ALL" <?= $curCountries === 'ALL' ? 'selected' : '' ?>>Global (All Countries Allowed)</option>
                        <option value="IN" <?= $curCountries === 'IN' ? 'selected' : '' ?>>India Only (IN — Best for BGMI)</option>
                        <option value="IN,NP,BD,PK,AE,SA" <?= $curCountries === 'IN,NP,BD,PK,AE,SA' ? 'selected' : '' ?>>South Asia &amp; Middle East (IN, NP, BD, PK, AE, SA)</option>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <!-- Anti-Leak Floating Watermark Controller (Buyer On/Off Switch) -->
    <div class="ios-card">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
            <input type="hidden" name="form_action" value="buyer_save_watermark">
            <div class="section-header">
                <div>
                    <div class="section-title"><?= fbox_svg('shield', 18, 'var(--ios-purple)') ?> Anti-Leak Floating Watermark Controls</div>
                    <div class="section-desc">Choose whether to display a watermark on screen, or turn it completely OFF if you don't want any overlays.</div>
                </div>
                <button type="submit" class="ios-btn btn-primary">Save Watermark</button>
            </div>
            <div class="form-grid">
                <div class="field-group">
                    <label class="field-label">Watermark Display Mode</label>
                    <select name="watermark_mode" class="ios-select">
                        <option value="off" <?= ($myKey['watermark_mode'] ?? 'off') === 'off' ? 'selected' : '' ?>>Disabled (100% Clean Screen — No Watermark)</option>
                        <option value="on" <?= ($myKey['watermark_mode'] ?? '') === 'on' ? 'selected' : '' ?>>Enabled (Subtle Floating Anti-Leak Identifier)</option>
                        <option value="inherit" <?= ($myKey['watermark_mode'] ?? '') === 'inherit' ? 'selected' : '' ?>>Inherit Global Server Setting</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label">Custom Watermark Text (Optional)</label>
                    <input type="text" name="custom_watermark_text" class="ios-input"
                           value="<?= htmlspecialchars($myKey['custom_watermark_text'] ?? '') ?>"
                           placeholder="Leave blank for subtle HWID (e.g. FEATURESTIC BOX • A1B2)">
                </div>
            </div>
        </form>
    </div>

    <!-- Developer Diagnostics & Test Bot Integration -->
    <div class="ios-card" style="border-left:4px solid var(--ios-orange);">
        <div class="section-header">
            <div>
                <div class="section-title"><?= fbox_svg('alert', 18, 'var(--ios-orange)') ?> Developer .so Diagnostics &amp; Private Telegram Bot</div>
                <div class="section-desc">Test your custom .so plugins and offsets safely. When Diagnostic Mode is active, any SIGSEGV crash, offset fault address, and stacktrace are sent privately to your Telegram Bot. Production users remain silent and safe from reverse engineering.</div>
            </div>
            <div>
                <?php
                $todayStr      = gmdate('Y-m-d');
                $dailyLimitMin = (int)($myKey['daily_diag_limit_minutes'] ?? 120);
                $usedTodayMin  = ($myKey['diag_used_date'] === $todayStr) ? (int)$myKey['diag_used_minutes_today'] : 0;
                $remTodayMin   = ($dailyLimitMin === -1) ? 999999 : max(0, $dailyLimitMin - $usedTodayMin);
                $isDiagActive  = ((int)$myKey['is_diagnostic_mode'] === 1 && ((int)$myKey['diagnostic_expires_at'] === 0 || time() < (int)$myKey['diagnostic_expires_at']));
                $remainingMin  = $isDiagActive ? max(1, round(((int)$myKey['diagnostic_expires_at'] - time()) / 60)) : 0;
                ?>
                <?php if ($isDiagActive): ?>
                    <span class="badge badge-orange" style="font-size:12px;padding:6px 12px;">
                        <?= fbox_svg('bolt', 12) ?> Diagnostic Active (<?= $remainingMin ?>m left)
                    </span>
                <?php else: ?>
                    <span class="badge badge-green" style="font-size:12px;padding:6px 12px;">
                        <?= fbox_svg('check', 12) ?> Production Mode (Silent)
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Form 1: Buyer Telegram Bot Credentials -->
        <form method="post" class="form-grid" style="margin-bottom:16px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
            <input type="hidden" name="form_action" value="buyer_save_bot">
            <div class="field-group">
                <label class="field-label">Your Private Telegram Bot Token</label>
                <input type="text" name="buyer_telegram_bot_token" class="ios-input"
                       value="<?= htmlspecialchars($myKey['buyer_telegram_bot_token'] ?? '') ?>"
                       placeholder="e.g. 123456:ABC-DEF1234ghIkl">
            </div>
            <div class="field-group">
                <label class="field-label">Your Telegram Chat ID (Group or User ID)</label>
                <input type="text" name="buyer_telegram_chat_id" class="ios-input"
                       value="<?= htmlspecialchars($myKey['buyer_telegram_chat_id'] ?? '') ?>"
                       placeholder="e.g. -100123456789 or 987654321">
            </div>
            <div class="field-group" style="justify-content:flex-end;">
                <button type="submit" class="ios-btn btn-secondary" style="height:40px;">Save Bot Config</button>
            </div>
        </form>

        <!-- Form 2: Toggle Diagnostic Mode Window -->
        <form method="post" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:var(--bg-primary);border:1px solid var(--border-subtle);border-radius:10px;padding:12px;margin-bottom:18px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
            <input type="hidden" name="form_action" value="buyer_toggle_diagnostic_mode">

            <span style="font-size:13px;font-weight:600;">Activate Testing Window:</span>
            <select name="diag_hours" class="ios-select" style="width:auto;min-width:140px;height:36px;padding:6px 12px;">
                <option value="1">1 Hour Test</option>
                <option value="4" selected>4 Hours Test</option>
                <option value="12">12 Hours Test</option>
                <option value="24">24 Hours Test</option>
                <option value="0">Turn OFF (Silent)</option>
            </select>
            <button type="submit" class="ios-btn btn-primary btn-sm"><?= fbox_svg('bolt', 12, '#fff') ?> Apply Diagnostic Window</button>
        </form>

        <!-- Table: Buyer's Own Crash Reports -->
        <div style="font-size:13px;font-weight:700;margin-bottom:8px;">Recent .so Crash &amp; Offset Diagnostics (Your Key Only):</div>
        <?php if (empty($myCrashReports)): ?>
            <div style="color:var(--text-tertiary);font-size:12.5px;padding:12px 0;">No crashes detected on your test device yet. All .so hooks running clean!</div>
        <?php else: ?>
                        <div style="margin-bottom:14px;position:relative;">
                <input type="text" class="ios-input" placeholder="Live Instant Search by Key, Username, Label, Status, Games..." onkeyup="filterTable(this, 'keysTable')" style="padding-left:36px;font-size:13px;border-radius:10px;">
                <div style="position:absolute;left:12px;top:10px;pointer-events:none;color:var(--text-tertiary);"><?= fbox_svg('eye', 15) ?></div>
            </div>
            <div class="table-wrap">
                <table class="ios-table" id="keysTable">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Game &amp; Loader</th>
                            <th>Crashing .so &amp; Offset</th>
                            <th>Fault Address</th>
                            <th>Diagnostics</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($myCrashReports as $bcr): ?>
                        <tr>
                            <td><?= date('d M H:i:s', (int)$bcr['created_at']) ?></td>
                            <td>
                                <b><?= htmlspecialchars($bcr['package_name']) ?></b>
                                <div style="font-size:11px;color:var(--text-tertiary);">Loader: <?= htmlspecialchars($bcr['loader_id']) ?></div>
                            </td>
                            <td>
                                <span class="badge badge-red"><?= htmlspecialchars($bcr['crashing_library'] ?: $bcr['crash_type']) ?></span>
                                <?php if (!empty($bcr['crashing_offset'])): ?>
                                    <span class="mono-code" style="margin-left:4px;"><?= htmlspecialchars($bcr['crashing_offset']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= !empty($bcr['fault_address']) ? '<code>' . htmlspecialchars($bcr['fault_address']) . '</code>' : '—' ?></td>
                            <td>
                                <button type="button" class="ios-btn btn-secondary btn-sm" onclick='showCrashModal(<?= json_encode($bcr) ?>)'>
                                    <?= fbox_svg('eye', 12) ?> View Stack
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Custom Offsets JSON -->
    <div class="ios-card">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
            <input type="hidden" name="form_action" value="buyer_save_offsets">
            <div class="section-header">
                <div>
                    <div class="section-title"><?= fbox_svg('terminal', 18, 'var(--ios-blue)') ?> Custom Offsets (Encrypted in RAM)</div>
                    <div class="section-desc">Encrypted with AES-256-CBC and decrypted only in RAM inside your app.</div>
                </div>
                <button type="submit" class="ios-btn btn-primary">Save Offsets</button>
            </div>
            <div class="field-group">
                <textarea name="custom_offsets_json" class="ios-textarea"><?= htmlspecialchars($myKey['custom_offsets_json'] ?: "{\n  \"offset_guard_hook\": \"0x0\",\n  \"offset_ace_worker\": \"0x0\"\n}") ?></textarea>
            </div>
        </form>
    </div>

    <!-- Active Session & Password -->
    <div class="ios-card">
        <?php if ($mySession): ?>
            <div style="background:rgba(52,199,89,0.08);border:1px solid rgba(52,199,89,0.24);border-radius:12px;padding:14px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <div style="font-weight:700;font-size:14px;"><?= fbox_svg('check', 14, 'var(--ios-green)') ?> Active Live Session Running</div>
                    <div style="font-size:12.5px;color:var(--text-secondary);margin-top:2px;">
                        Device: <b><?= htmlspecialchars($mySession['device_model']) ?></b> | IP: <?= htmlspecialchars($mySession['ip_address']) ?> | Game: <?= htmlspecialchars($mySession['active_game'] ?: 'Connected') ?>
                    </div>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                    <input type="hidden" name="form_action" value="buyer_kick_session">
                    <button type="submit" class="ios-btn btn-danger btn-sm">Kick Active Session</button>
                </form>
            </div>
        <?php endif; ?>

        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
            <input type="hidden" name="form_action" value="buyer_change_password">
            <div class="field-group">
                <label class="field-label">Update Your Portal Password</label>
                <input type="password" name="new_password" class="ios-input" placeholder="New password (min 6 characters)" required>
            </div>
            <div class="field-group" style="justify-content:flex-end;">
                <button type="submit" class="ios-btn btn-secondary" style="height:40px;">Change Password</button>
            </div>
        </form>
    </div>
</main>

<?php elseif ($role === 'reseller' && $myReseller): ?>
<!-- RESELLER / SUB-AGENT CONSOLE (ISOLATED VIEW) -->
<main class="container">
    <?php if ($flash !== ''): ?>
        <div class="flash-banner <?= $flashType === 'error' ? 'flash-error' : 'flash-success' ?>">
            <span><?= htmlspecialchars($flash) ?></span>
            <span style="font-size:12px;opacity:0.75;"><?= fbox_svg('check', 13) ?> Synced with Gateway</span>
        </div>
    <?php endif; ?>

    <!-- Reseller Hero -->
    <div class="buyer-hero">
        <div class="buyer-hero-left">
            <h1>Welcome, <?= htmlspecialchars($myReseller['username']) ?></h1>
            <p>Featurestic Box Reseller Hub • Manage Client Keys &amp; Wholesale Balance.</p>
        </div>
        <div>
            <span class="badge badge-purple" style="font-size:14px;padding:8px 16px;">Available Balance: <?= (int)$myReseller['credits'] ?> Credits</span>
        </div>
    </div>

    <!-- Generate Key Card -->
    <div class="ios-card">
        <div class="section-header">
            <div>
                <div class="section-title"><?= fbox_svg('key', 18, 'var(--ios-blue)') ?> Generate Client License Key</div>
                <div class="section-desc">Each generated license key costs 1 credit from your balance</div>
            </div>
        </div>
        <?php $resellerTiers = fbox_get_reseller_price_tiers(); ?>
        <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
            <input type="hidden" name="form_action" value="reseller_generate_key">
            <div>
                <label class="field-label">License Duration Tier</label>
                <select name="duration_days" class="ios-select" required>
                    <?php foreach ($resellerTiers as $days => $tier): ?>
                        <option value="<?= $days ?>" <?= $days === 30 ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tier['label']) ?> — <?= (int)$tier['credits'] ?> Credit<?= (int)$tier['credits'] > 1 ? 's' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="field-label">Quantity (Batch Count)</label>
                <select name="count" class="ios-select">
                    <option value="1" selected>1 Key (Single)</option>
                    <option value="5">5 Keys Batch</option>
                    <option value="10">10 Keys Batch</option>
                    <option value="25">25 Keys Wholesale</option>
                    <option value="50">50 Keys Wholesale</option>
                </select>
            </div>
            <div>
                <label class="field-label">Client Name / Label</label>
                <input type="text" name="label" class="ios-input" placeholder="e.g. Rahul VIP Loader" required>
            </div>
            <div style="display:flex;align-items:flex-end;">
                <button type="submit" class="ios-btn btn-primary" style="width:100%;padding:10px;">
                    <?= fbox_svg('key', 14, '#fff') ?> Issue Key &amp; Deduct Credits
                </button>
            </div>
        </form>
    </div>

    <!-- Keys Table -->

    <?php if (!empty($_SESSION['fbox_last_batch'])): 
        $batch = $_SESSION['fbox_last_batch'];
        $batchRaw = implode("
", $batch['keys']);
        $batchExport = "FEATURESTIC BOX — BATCH LICENSE KEYS (" . $batch['count'] . " Keys | Duration: " . $batch['days'] . " Days)
"
            . "Created: " . $batch['time'] . " | Games: " . $batch['games'] . "
"
            . "Portal: " . $panelUrl . "
"
            . "============================================================
"
            . $batchRaw . "
"
            . "============================================================
"
            . "Instructions:
"
            . "1. Provide each client with one unique license key from above.
"
            . "2. Clients enter their license key in their Loader app to connect.";
    ?>
    <div class="ios-card" style="margin-bottom:20px;border:1.5px solid var(--ios-blue);background:rgba(0,122,255,0.03);">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div class="nav-logo-icon" style="width:34px;height:34px;border-radius:9px;background:var(--ios-blue);">
                    <?= fbox_svg('key', 16, '#fff') ?>
                </div>
                <div>
                    <h3 style="font-size:15px;font-weight:700;margin:0;">Batch Generated: <?= $batch['count'] ?> Keys (<?= $batch['days'] ?> Days)</h3>
                    <div style="font-size:12px;color:var(--text-secondary);">Ready for immediate distribution to wholesale buyers or tournament teams</div>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" class="ios-btn btn-primary btn-sm" onclick="copyBatchText()"><?= fbox_svg('copy', 13, '#fff') ?> Copy All Keys</button>
                <button type="button" class="ios-btn btn-secondary btn-sm" onclick="downloadBatchFile()"><?= fbox_svg('bolt', 13) ?> Download .TXT</button>
                <a href="index.php?dismiss_batch=1" class="ios-btn btn-secondary btn-sm">Dismiss</a>
            </div>
        </div>
        <textarea id="batchTextarea" class="ios-input" style="height:130px;font-family:monospace;font-size:12px;white-space:pre;line-height:1.5;" readonly><?= htmlspecialchars($batchExport) ?></textarea>
    </div>
    <?php endif; ?>

    <div class="ios-card">
        <div class="section-header">
            <div>
                <div class="section-title"><?= fbox_svg('shield', 18, 'var(--ios-green)') ?> Your Issued Client Keys</div>
                <div class="section-desc">All keys generated under your reseller account</div>
            </div>
        </div>
        <div class="table-wrap">
            <table class="ios-table">
                <thead>
                    <tr>
                        <th>License Key</th>
                        <th>Client Label</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($myResellerKeys)): ?>
                    <tr><td colspan="6" style="color:var(--text-tertiary);text-align:center;padding:24px;">No client keys generated yet. Select a duration tier above to create your first key.</td></tr>
                <?php else: foreach ($myResellerKeys as $rk):
                    $st = $rk['status'];
                    $bClass = $st === 'active' ? 'badge-green' : ($st === 'blocked' ? 'badge-red' : ($st === 'paused' ? 'badge-orange' : 'badge-red'));
                    $exp = (int)$rk['expires_at'];
                ?>
                    <tr>
                        <td>
                            <span class="mono-code"><?= htmlspecialchars($rk['license_key']) ?></span>
                            <div style="font-size:11px;color:var(--text-tertiary);margin-top:2px;">
                                <?= $exp > 0 ? ('Expires: ' . date('d M Y, H:i', $exp)) : ('Duration: ' . (int)$rk['duration_days'] . ' Days') ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($rk['label'] ?: '—') ?></td>
                        <td><span class="badge <?= $bClass ?>"><?= strtoupper(htmlspecialchars($st)) ?></span></td>
                        <td><?= date('d M Y', (int)$rk['created_at']) ?></td>
                        <td colspan="2">
                            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                                <!-- Extend Form -->
                                <form method="post" style="display:inline-flex;gap:4px;align-items:center;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                                    <input type="hidden" name="form_action" value="reseller_extend_key">
                                    <input type="hidden" name="key_id" value="<?= (int)$rk['id'] ?>">
                                    <select name="extend_days" class="ios-select" style="width:auto;padding:3px 6px;font-size:11.5px;height:30px;">
                                        <option value="1">+1 Day (<?= (int)$resellerTiers[1]['credits'] ?>c)</option>
                                        <option value="7">+7 Days (<?= (int)$resellerTiers[7]['credits'] ?>c)</option>
                                        <option value="15">+15 Days (<?= (int)$resellerTiers[15]['credits'] ?>c)</option>
                                        <option value="30" selected>+30 Days (<?= (int)$resellerTiers[30]['credits'] ?>c)</option>
                                        <option value="60">+60 Days (<?= (int)$resellerTiers[60]['credits'] ?>c)</option>
                                    </select>
                                    <button type="submit" class="ios-btn btn-secondary btn-sm" style="padding:4px 8px;font-size:11.5px;">Extend</button>
                                </form>

                                <!-- Block / Activate Form -->
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                                    <input type="hidden" name="form_action" value="reseller_toggle_key_status">
                                    <input type="hidden" name="key_id" value="<?= (int)$rk['id'] ?>">
                                    <button type="submit" class="ios-btn <?= $st === 'active' ? 'btn-danger' : 'btn-success' ?> btn-sm" style="padding:4px 8px;font-size:11.5px;">
                                        <?= $st === 'active' ? 'Block' : 'Unblock' ?>
                                    </button>
                                </form>

                                <!-- Reset HWID -->
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                                    <input type="hidden" name="form_action" value="reseller_reset_hwid">
                                    <input type="hidden" name="key_id" value="<?= (int)$rk['id'] ?>">
                                    <button type="submit" class="ios-btn btn-ghost btn-sm" style="padding:4px 8px;font-size:11.5px;" onclick="return confirm('Reset HWID for this customer?');">Reset HWID</button>
                                </form>

                                <button type="button" class="ios-btn btn-primary btn-sm" style="padding:4px 8px;font-size:11.5px;" onclick="copyToClipboard('<?= htmlspecialchars($rk['license_key']) ?>')">Copy</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php else: ?>
<!-- MASTER ADMINISTRATOR CONSOLE -->
<main class="container">
    <?php if ($flash !== ''): ?>
        <div class="flash-banner <?= $flashType === 'error' ? 'flash-error' : 'flash-success' ?>">
            <span><?= htmlspecialchars($flash) ?></span>
            <span style="font-size:12px;opacity:0.75;"><?= fbox_svg('check', 13) ?> Synced with Gateway</span>
        </div>
    <?php endif; ?>

    <!-- 1-Click Client Delivery Pack (Generated Instantly for Buyer) -->
    <?php if ($deliveryPack): ?>
        <div class="delivery-pack-card">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                <div>
                    <div style="font-weight:800;font-size:16px;color:var(--ios-green);display:flex;align-items:center;gap:6px;">
                        <?= fbox_svg('check', 18, 'var(--ios-green)') ?> Client Access Pack Generated (Ready to Send)
                    </div>
                    <div style="font-size:12.5px;color:var(--text-secondary);margin-top:2px;">
                        Click the button below to copy the complete formatted credentials pack and send directly to your client via WhatsApp or Telegram!
                    </div>
                </div>
                <button type="button" class="ios-btn btn-primary" onclick="copyDeliveryPackText()">
                    <?= fbox_svg('copy', 15, '#fff') ?> Copy Delivery Pack
                </button>
            </div>
            <div class="pack-code-block" id="deliveryPackText">FEATURESTIC BOX — CLIENT ACCESS PACK
------------------------------------------------------------
License Key:   <?= htmlspecialchars($deliveryPack['license_key']) ?>

Portal URL:  <?= htmlspecialchars($deliveryPack['portal_url']) ?>

Username:      <?= htmlspecialchars($deliveryPack['username']) ?>

Password:      <?= htmlspecialchars($deliveryPack['password']) ?>

Allowed Games: <?= htmlspecialchars($deliveryPack['games']) ?>

Allowed Slots: <?= htmlspecialchars($deliveryPack['slots']) ?>

Duration:      <?= htmlspecialchars($deliveryPack['duration']) ?>

------------------------------------------------------------
Instructions:
1. Log in to the Customer Portal above to set your custom .so (GitHub link) & offsets.
2. Put the License Key into your Loader app to connect.</div>
        </div>
    <?php endif; ?>

    <!-- Segmented Navigation -->
    <nav class="segmented-bar" role="tablist">
        <?php if ($role === 'admin'): ?>
            <button type="button" class="seg-btn active" onclick="switchTab('overview', this)"><?= fbox_svg('bolt', 14) ?> Overview &amp; Switches</button>
        <?php endif; ?>
        <button type="button" class="seg-btn <?= $role === 'manager' ? 'active' : '' ?>" onclick="switchTab('keys', this)"><?= fbox_svg('key', 14) ?> License Keys (<?= count($licenseKeys) ?>)</button>
        <button type="button" class="seg-btn" onclick="switchTab('bans', this)"><?= fbox_svg('ban', 14) ?> Banned Devices (<?= $activeBansCount ?>)</button>
        <?php if ($role === 'admin'): ?>
            <button type="button" class="seg-btn" onclick="switchTab('vault', this)"><?= fbox_svg('terminal', 14) ?> Encrypted Offsets &amp; OTA</button>
            <button type="button" class="seg-btn" onclick="switchTab('settings', this)"><?= fbox_svg('gear', 14) ?> SDK &amp; Endpoint Security</button>
        <?php endif; ?>
        <button type="button" class="seg-btn" onclick="switchTab('logs', this)"><?= fbox_svg('shield', 14) ?> Security Logs</button>
        <button type="button" class="seg-btn" onclick="switchTab('crashes', this)"><?= fbox_svg('alert', 14) ?> Crash Telemetry (<?= count($crashReports) ?>)</button>
    </nav>

    <!-- TAB 1: OVERVIEW & MASTER SWITCHES -->
    <?php if ($role === 'admin'): ?><section id="tab-overview" class="tab-pane active"><?php endif; ?>
        <div class="kpi-grid">
            <div class="ios-card" style="margin-bottom:0;">
                <div class="kpi-top">
                    <span class="badge badge-blue">Validated</span>
                    <div class="squircle-icon" style="background:var(--ios-blue);"><?= fbox_svg('key', 18, '#fff') ?></div>
                </div>
                <div class="kpi-value"><?= $activeKeysCount ?></div>
                <div class="kpi-label">Active License Keys</div>
            </div>

            <div class="ios-card" style="margin-bottom:0;">
                <div class="kpi-top">
                    <span class="badge badge-green">Heartbeat</span>
                    <div class="squircle-icon" style="background:var(--ios-green);"><?= fbox_svg('bolt', 18, '#fff') ?></div>
                </div>
                <div class="kpi-value"><?= $onlineSessCount ?></div>
                <div class="kpi-label">Active Game Sessions</div>
            </div>

            <div class="ios-card" style="margin-bottom:0;">
                <div class="kpi-top">
                    <span class="badge badge-red">Anti-Crack</span>
                    <div class="squircle-icon" style="background:var(--ios-red);"><?= fbox_svg('ban', 18, '#fff') ?></div>
                </div>
                <div class="kpi-value"><?= $activeBansCount ?></div>
                <div class="kpi-label">Banned Device HWIDs</div>
            </div>

            <div class="ios-card" style="margin-bottom:0;">
                <div class="kpi-top">
                    <span class="badge badge-orange"><?= htmlspecialchars($settings['target_fps'] ?? '120') ?> FPS</span>
                    <div class="squircle-icon" style="background:var(--ios-indigo);"><?= fbox_svg('shield', 18, '#fff') ?></div>
                </div>
                <div class="kpi-value">AES-256</div>
                <div class="kpi-label">Encrypted Payload Tunnel</div>
            </div>
        </div>

        <div class="ios-card">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                <input type="hidden" name="form_action" value="save_quick_switches">
                <div class="section-header">
                    <div>
                        <div class="section-title"><?= fbox_svg('gear', 18, 'var(--ios-blue)') ?> Real-Time Master Anti-Bypass Switches</div>
                        <div class="section-desc">Changes apply immediately to all active logins and rolling heartbeats</div>
                    </div>
                    <button type="submit" class="ios-btn btn-primary">Save Switches</button>
                </div>

                <div class="ios-switch-row">
                    <div class="switch-meta">
                        <div class="squircle-icon" style="background:var(--ios-red);"><?= fbox_svg('ban', 18, '#fff') ?></div>
                        <div>
                            <div class="switch-title">Automatic Anti-Crack Device HWID Ban</div>
                            <div class="switch-sub">When someone tries to crack, hook (Frida/Xposed), attach a debugger, or modify the APK, SDK bans their HWID on the server. Can only be unbanned from this Panel.</div>
                        </div>
                    </div>
                    <label class="ui-switch">
                        <input type="checkbox" name="auto_ban_on_tamper" value="1" <?= ($settings['auto_ban_on_tamper'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <span class="ui-slider"></span>
                    </label>
                </div>

                <div class="ios-switch-row">
                    <div class="switch-meta">
                        <div class="squircle-icon" style="background:var(--ios-red);"><?= fbox_svg('globe', 18, '#fff') ?></div>
                        <div>
                            <div class="switch-title">Autonomous HWID + Linked IP Blacklisting</div>
                            <div class="switch-sub">Automatically bans both the offending device HWID and its IP address when tampering is detected.</div>
                        </div>
                    </div>
                    <label class="ui-switch">
                        <input type="checkbox" name="ban_ip_with_hwid" value="1" <?= ($settings['ban_ip_with_hwid'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <span class="ui-slider"></span>
                    </label>
                </div>

                <div class="ios-switch-row">
                    <div class="switch-meta">
                        <div class="squircle-icon" style="background:var(--ios-blue);"><?= fbox_svg('key', 18, '#fff') ?></div>
                        <div>
                            <div class="switch-title">Enforce Server Session Lease Gate</div>
                            <div class="switch-sub">Blocks direct game launch if the Loader did not obtain a signed session lease from this Panel.</div>
                        </div>
                    </div>
                    <label class="ui-switch">
                        <input type="checkbox" name="require_login_gate" value="1" <?= ($settings['require_login_gate'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <span class="ui-slider"></span>
                    </label>
                </div>

                <div class="ios-switch-row">
                    <div class="switch-meta">
                        <div class="squircle-icon" style="background:var(--ios-green);"><?= fbox_svg('bolt', 18, '#fff') ?></div>
                        <div>
                            <div class="switch-title">Enforce Challenge-Response Nonce &amp; Rolling Token Validation</div>
                            <div class="switch-sub">Prevents fake server responses, packet replay, and session cloning via 128-bit per-request nonces.</div>
                        </div>
                    </div>
                    <label class="ui-switch">
                        <input type="checkbox" name="enforce_request_hmac" value="1" <?= ($settings['enforce_request_hmac'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <span class="ui-slider"></span>
                    </label>
                </div>

                <div class="ios-switch-row">
                    <div class="switch-meta">
                        <div class="squircle-icon" style="background:var(--ios-indigo);"><?= fbox_svg('shield', 18, '#fff') ?></div>
                        <div>
                            <div class="switch-title">Enforce Loader APK SHA-256 Signature Lock</div>
                            <div class="switch-sub">Verifies live APK signing certificate against your whitelisted SHA-256.</div>
                        </div>
                    </div>
                    <label class="ui-switch">
                        <input type="checkbox" name="enforce_apk_signature" value="1" <?= ($settings['enforce_apk_signature'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <span class="ui-slider"></span>
                    </label>
                </div>

                <div class="ios-switch-row">
                    <div class="switch-meta">
                        <div class="squircle-icon" style="background:var(--ios-indigo);"><?= fbox_svg('eye', 18, '#fff') ?></div>
                        <div>
                            <div class="switch-title">Block HTTP Proxy / MITM Packet Sniffers</div>
                            <div class="switch-sub">Detects proxy routing or local loopback hosts spoofing and blocks/auto-bans the device.</div>
                        </div>
                    </div>
                    <label class="ui-switch">
                        <input type="checkbox" name="block_http_proxy" value="1" <?= ($settings['block_http_proxy'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <span class="ui-slider"></span>
                    </label>
                </div>

                <div class="ios-switch-row">
                    <div class="switch-meta">
                        <div class="squircle-icon" style="background:var(--ios-orange);"><?= fbox_svg('clock', 18, '#fff') ?></div>
                        <div>
                            <div class="switch-title">Global Server Maintenance Kill-Switch</div>
                            <div class="switch-sub">Pauses all game launches and gracefully closes active sessions with your maintenance message.</div>
                        </div>
                    </div>
                    <label class="ui-switch">
                        <input type="checkbox" name="maintenance_mode" value="1" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <span class="ui-slider"></span>
                    </label>
                </div>

                <div class="ios-switch-row">
                    <div class="switch-meta">
                        <div class="squircle-icon" style="background:var(--ios-red);"><?= fbox_svg('bolt', 18, '#fff') ?></div>
                        <div>
                            <div class="switch-title">Emergency Security Kill-Switch (Freeze System)</div>
                            <div class="switch-sub">Instantly locks the entire API gateway and freezes all container instances in case of emergency.</div>
                        </div>
                    </div>
                    <label class="ui-switch">
                        <input type="checkbox" name="kill_switch_active" value="1" <?= ($settings['kill_switch_active'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <span class="ui-slider"></span>
                    </label>
                </div>

                <div class="ios-switch-row">
                    <div class="switch-meta">
                        <div class="squircle-icon" style="background:var(--ios-purple);"><?= fbox_svg('shield', 18, '#fff') ?></div>
                        <div>
                            <div class="switch-title">Anti-Leak Floating Watermark Guard</div>
                            <div class="switch-sub">Directs the virtual container to display a subtle, semi-transparent HWID identifier to prevent video leaks.</div>
                        </div>
                    </div>
                    <label class="ui-switch">
                        <input type="checkbox" name="enable_leak_watermark" value="1" <?= ($settings['enable_leak_watermark'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <span class="ui-slider"></span>
                    </label>
                </div>
            </form>
        </div>


        <!-- DATABASE BACKUP & CLOUD VAULT CARD -->
        <div class="ios-card">
            <div class="section-header">
                <div>
                    <div class="section-title"><?= fbox_svg('terminal', 18, 'var(--ios-blue)') ?> Automated Database Backup &amp; Telegram Cloud Vault</div>
                    <div class="section-desc">Create 1-click encrypted snapshots of your entire database and push directly to your Telegram Bot</div>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                    <input type="hidden" name="form_action" value="backup_database_now">
                    <button type="submit" class="ios-btn btn-primary"><?= fbox_svg('bolt', 14, '#fff') ?> Backup to Telegram Now</button>
                </form>
            </div>
            <div style="font-size:13px;color:var(--text-secondary);line-height:1.6;">
                Snapshots are stored in <code>storage/backups/</code> with automated timestamping. If your Telegram Bot Token &amp; Chat ID are configured, the snapshot file is automatically dispatched to your private Telegram chat instantly.
            </div>
        </div>

        
        <!-- STAFF MANAGERS (RESTRICTED SUB-ADMINS) CARD -->
        <div class="ios-card">
            <div class="section-header">
                <div>
                    <div class="section-title"><?= fbox_svg('user', 18, 'var(--ios-blue)') ?> Staff Managers (Restricted Sub-Admins)</div>
                    <div class="section-desc">Create staff managers who can generate/extend keys and unban devices, without access to wholesale pricing or master security secrets</div>
                </div>
            </div>
            <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:14px;margin-bottom:20px;padding:16px;background:rgba(255,255,255,0.03);border-radius:12px;border:1px solid var(--border-color);">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                <input type="hidden" name="form_action" value="create_manager">
                <div>
                    <label class="field-label">Manager Username</label>
                    <input type="text" name="manager_username" class="ios-input" placeholder="e.g. staff_rahul" required>
                </div>
                <div>
                    <label class="field-label">Login Password</label>
                    <input type="password" name="manager_password" class="ios-input" placeholder="Enter password" required>
                </div>
                <div>
                    <label class="field-label">Staff Tag / Label</label>
                    <input type="text" name="manager_label" class="ios-input" placeholder="e.g. Day Shift Support">
                </div>
                <div style="display:flex;align-items:flex-end;">
                    <button type="submit" class="ios-btn btn-primary" style="width:100%;">Create Staff Manager</button>
                </div>
            </form>
            <div class="table-wrap">
                <table class="ios-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Role &amp; Tag</th>
                            <th>Permissions</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($managers)): ?>
                            <tr><td colspan="5" style="text-align:center;color:var(--text-tertiary);padding:18px;">No staff managers created yet.</td></tr>
                        <?php else: foreach ($managers as $m): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($m['username']) ?></strong></td>
                                <td><span class="badge badge-blue">Staff Manager</span> <?= htmlspecialchars($m['label']) ?></td>
                                <td><span class="badge badge-green">Keys &amp; Bans Only (No Secrets)</span></td>
                                <td><?= date('d M Y', (int)$m['created_at']) ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Delete this staff manager?');" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                                        <input type="hidden" name="form_action" value="delete_manager">
                                        <input type="hidden" name="manager_id" value="<?= (int)$m['id'] ?>">
                                        <button type="submit" class="ios-btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RESELLER / AGENT MANAGEMENT CARD -->
        <div class="ios-card">
            <div class="section-header">
                <div>
                    <div class="section-title"><?= fbox_svg('user', 18, 'var(--ios-purple)') ?> Reseller &amp; Sub-Agent Management</div>
                    <div class="section-desc">Issue wholesale key credits to authorized resellers who generate and manage keys independently</div>
                </div>
            </div>
            <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:14px;margin-bottom:20px;padding:16px;background:rgba(255,255,255,0.03);border-radius:12px;border:1px solid var(--border-color);">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                <input type="hidden" name="form_action" value="create_reseller">
                <div>
                    <label class="field-label">Reseller Username</label>
                    <input type="text" name="reseller_username" class="ios-input" placeholder="e.g. agent_vip" required>
                </div>
                <div>
                    <label class="field-label">Login Password</label>
                    <input type="password" name="reseller_password" class="ios-input" placeholder="Enter password" required>
                </div>
                <div>
                    <label class="field-label">Initial Credits</label>
                    <input type="number" name="reseller_credits" class="ios-input" value="25" min="0" max="10000" required>
                </div>
                <div>
                    <label class="field-label">Allowed Plan Tier for Reseller</label>
                    <select name="allowed_plan_tier" class="ios-select">
                        <option value="starter">Starter / Basic Plan</option>
                        <option value="pro" selected>Pro Developer Plan</option>
                        <option value="enterprise">Enterprise VIP Plan (All Features)</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Agent Label</label>
                    <input type="text" name="reseller_label" class="ios-input" placeholder="e.g. North India Partner">
                </div>
                <div style="display:flex;align-items:flex-end;">
                    <button type="submit" class="ios-btn btn-primary" style="width:100%;">Create Reseller</button>
                </div>
            </form>

            <?php $currentTiers = fbox_get_reseller_price_tiers(); ?>
            <form method="post" style="margin-bottom:20px;padding:16px;background:rgba(0,122,255,0.04);border:1px solid rgba(0,122,255,0.18);border-radius:12px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                <input type="hidden" name="form_action" value="save_reseller_pricing">
                <div style="font-weight:700;font-size:13.5px;margin-bottom:6px;color:var(--ios-blue);display:flex;align-items:center;gap:6px;">
                    <?= fbox_svg('gear', 15, 'var(--ios-blue)') ?> Wholesale Reseller Pricing Matrix (Credits Charged per Key Duration)
                </div>
                <div style="font-size:12px;color:var(--text-secondary);margin-bottom:12px;">
                    Admin has unlimited credits. Set the exact credit cost deducted when a reseller creates each key duration:
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(130px, 1fr));gap:12px;align-items:flex-end;">
                    <div>
                        <label class="field-label">1 Day Key</label>
                        <input type="number" name="price_1d" class="ios-input" value="<?= (int)$currentTiers[1]['credits'] ?>" min="1" max="1000">
                    </div>
                    <div>
                        <label class="field-label">7 Days Key</label>
                        <input type="number" name="price_7d" class="ios-input" value="<?= (int)$currentTiers[7]['credits'] ?>" min="1" max="1000">
                    </div>
                    <div>
                        <label class="field-label">15 Days Key</label>
                        <input type="number" name="price_15d" class="ios-input" value="<?= (int)$currentTiers[15]['credits'] ?>" min="1" max="1000">
                    </div>
                    <div>
                        <label class="field-label">1 Month (30d)</label>
                        <input type="number" name="price_30d" class="ios-input" value="<?= (int)$currentTiers[30]['credits'] ?>" min="1" max="1000">
                    </div>
                    <div>
                        <label class="field-label">2 Months (60d)</label>
                        <input type="number" name="price_60d" class="ios-input" value="<?= (int)$currentTiers[60]['credits'] ?>" min="1" max="1000">
                    </div>
                    <div>
                        <button type="submit" class="ios-btn btn-secondary" style="width:100%;height:38px;">Save Pricing</button>
                    </div>
                </div>
            </form>

                        <div style="margin-bottom:14px;position:relative;">
                <input type="text" class="ios-input" placeholder="Live Instant Search by HWID, Device Model, IP, Ban Reason..." onkeyup="filterTable(this, 'bansTable')" style="padding-left:36px;font-size:13px;border-radius:10px;">
                <div style="position:absolute;left:12px;top:10px;pointer-events:none;color:var(--text-tertiary);"><?= fbox_svg('eye', 15) ?></div>
            </div>
            <div class="table-wrap">
                <table class="ios-table" id="bansTable">
                    <thead>
                        <tr>
                            <th>Reseller</th>
                            <th>Available Credits</th>
                            <th>Label / Territory</th>
                            <th>Created</th>
                            <th>Add Credits</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($resellers)): ?>
                        <tr><td colspan="5" style="color:var(--text-tertiary);text-align:center;padding:20px;">No sub-resellers created yet.</td></tr>
                    <?php else: foreach ($resellers as $res): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($res['username']) ?></strong></td>
                            <td><span class="badge badge-purple"><?= (int)$res['credits'] ?> Credits</span></td>
                            <td><?= htmlspecialchars($res['label'] ?: '—') ?></td>
                            <td><?= date('d M Y', (int)$res['created_at']) ?></td>
                            <td>
                                <form method="post" style="display:inline-flex;gap:6px;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                                    <input type="hidden" name="form_action" value="add_reseller_credits">
                                    <input type="hidden" name="reseller_id" value="<?= (int)$res['id'] ?>">
                                    <input type="number" name="add_credits" class="ios-input" style="width:70px;padding:4px 8px;font-size:12px;" value="10" min="1" max="1000">
                                    <button type="submit" class="ios-btn btn-ghost" style="padding:4px 10px;font-size:12px;">+ Add</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="ios-card">
            <div class="section-header">
                <div>
                    <div class="section-title"><?= fbox_svg('device', 18, 'var(--ios-blue)') ?> Live Online Game Sessions</div>
                    <div class="section-desc">Devices currently running the virtual container with an active session lease</div>
                </div>
            </div>
                        <div style="margin-bottom:14px;position:relative;">
                <input type="text" class="ios-input" placeholder="Live Instant Search by Event, Threat Tag, HWID, Violation Details..." onkeyup="filterTable(this, 'logsTable')" style="padding-left:36px;font-size:13px;border-radius:10px;">
                <div style="position:absolute;left:12px;top:10px;pointer-events:none;color:var(--text-tertiary);"><?= fbox_svg('eye', 15) ?></div>
            </div>
            <div class="table-wrap">
                <table class="ios-table" id="logsTable">
                    <thead>
                        <tr>
                            <th>License Key</th>
                            <th>Device &amp; HWID</th>
                            <th>Active Game</th>
                            <th>IP Address</th>
                            <th>Last Heartbeat</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($sessions)): ?>
                        <tr><td colspan="6" style="color:var(--text-tertiary);text-align:center;padding:24px;">No active game sessions right now.</td></tr>
                    <?php else: foreach ($sessions as $s): ?>
                        <tr>
                            <td><span class="mono-code"><?= htmlspecialchars($s['license_key']) ?></span></td>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($s['device_model']) ?></div>
                                <div style="font-size:11.5px;color:var(--text-tertiary);font-family:monospace;"><?= htmlspecialchars(substr($s['hwid'], 0, 20)) ?>...</div>
                            </td>
                            <td><span class="badge badge-green"><?= htmlspecialchars($s['active_game'] ?: ($s['package_name'] ?: 'Connected')) ?></span></td>
                            <td><?= htmlspecialchars($s['ip_address']) ?></td>
                            <td><?= date('H:i:s', (int)$s['last_heartbeat_at']) ?></td>
                            <td>
                                <form method="post" style="display:inline-flex;gap:6px;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                                    <input type="hidden" name="form_action" value="session_action">
                                    <input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>">
                                    <button type="submit" name="sub_action" value="kick" class="ios-btn btn-secondary btn-sm">Kick</button>
                                    <button type="submit" name="sub_action" value="ban_hwid" class="ios-btn btn-danger btn-sm"><?= fbox_svg('ban', 12, '#fff') ?> Ban</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section><?php endif; ?>

    <!-- TAB 2: LICENSE KEYS & BUYER CREDENTIALS GENERATOR -->
    <section id="tab-keys" class="tab-pane <?= $role === 'manager' ? 'active' : '' ?>">
        <div class="ios-card">
            <div class="section-header">
                <div>
                    <div class="section-title"><?= fbox_svg('key', 18, 'var(--ios-blue)') ?> Create License Keys &amp; Assign Buyer Portal Login</div>
                    <div class="section-desc">Generate keys, authorize specific games, and allocate allowed loader slots. Produces a 1-click delivery pack for your client!</div>
                </div>
            </div>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                <input type="hidden" name="form_action" value="generate_keys">
                <div class="field-group">
                    <label class="field-label">Key Prefix</label>
                    <input type="text" name="prefix" class="ios-input" value="FBOX-VIP" required>
                </div>
                <div class="field-group">
                    <label class="field-label">Quantity (Batch Count)</label>
                    <select name="count" class="ios-select">
                        <option value="1" selected>1 Key (Single)</option>
                        <option value="5">5 Keys Batch</option>
                        <option value="10">10 Keys Batch</option>
                        <option value="25">25 Keys Wholesale</option>
                        <option value="50">50 Keys Wholesale</option>
                        <option value="100">100 Keys Mega Batch</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label">Duration</label>
                    <select name="duration_days" class="ios-select">
                        <option value="1">1 Day Trial</option>
                        <option value="7">7 Days Weekly</option>
                        <option value="30" selected>30 Days Monthly</option>
                        <option value="90">90 Days Season</option>
                        <option value="3650">Lifetime (10 Years)</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label">Player Device Limit (0 = Unlimited Players)</label>
                    <input type="number" name="max_devices" class="ios-input" value="0" min="0" max="10000" title="Keep 0 so unlimited end-user customers can use the Buyer's Loader APK">
                </div>
                <div class="field-group">
                    <label class="field-label">Allowed Loader Slots</label>
                    <select name="max_loader_slots" class="ios-select">
                        <option value="1" selected>1 Loader Slot (Single App)</option>
                        <option value="3">3 Loader Slots (Multi-Game)</option>
                        <option value="5">5 Loader Slots (Pro Studio)</option>
                        <option value="0">Unlimited Loader Slots</option>
                    </select>
                </div>

                <div class="field-group">
                    <label class="field-label">Buyer Portal Username</label>
                    <input type="text" name="buyer_username" class="ios-input" placeholder="e.g. client_john">
                </div>
                <div class="field-group">
                    <label class="field-label">Buyer Portal Password</label>
                    <input type="password" name="buyer_password" class="ios-input" placeholder="e.g. john1234">
                </div>
                <div class="field-group">
                    <label class="field-label">Client Name / Label</label>
                    <input type="text" name="label" class="ios-input" placeholder="Buyer tag or note">
                </div>


                <div class="field-group">
                    <label class="field-label">Subscription Plan Preset (Admin Deal)</label>
                    <select name="plan_tier" class="ios-select" onchange="applyPlanPreset(this.value, 'create')">
                        <option value="enterprise" selected>Enterprise / VIP Studio Plan (All Features Unlocked)</option>
                        <option value="pro">Pro Developer Plan (Cloud .so + Spoofer + Streamproof)</option>
                        <option value="starter">Starter / Basic Plan (Core Virtual Container Only)</option>
                        <option value="custom">Custom Deal (Pick Exact Features Below)</option>
                    </select>
                </div>

                <div class="field-group" style="grid-column:1/-1;background:rgba(0,122,255,0.04);border:1px solid rgba(0,122,255,0.18);border-radius:12px;padding:14px;">
                    <label class="field-label" style="color:var(--ios-blue);font-weight:700;margin-bottom:8px;">
                        Admin Deal Customization: Select Which SDK Features &amp; Powers This Buyer Gets
                    </label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(230px, 1fr));gap:8px;font-size:12.5px;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="ent_cloud_so" id="create_ent_cloud_so" value="1" checked> OTA Cloud <code>.so</code> &amp; Offsets Vault</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="ent_ram_only_so" id="create_ent_ram_only_so" value="1" checked> RAM-Only <code>.so</code> &amp; <code>libanogs</code> Maps Cloak</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="ent_device_spoofer" id="create_ent_device_spoofer" value="1" checked> Fake Flagship Device Spoofer (120 FPS)</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="ent_streamproof" id="create_ent_streamproof" value="1" checked> Streamer Mode (<code>FLAG_SECURE</code>)</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="ent_telemetry_silencer" id="create_ent_telemetry_silencer" value="1" checked> Anticheat Telemetry &amp; Log Silencer</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="ent_ram_diet" id="create_ent_ram_diet" value="1" checked> Low-End RAM Diet &amp; Thread Booster</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="ent_ban_wave_breaker" id="create_ent_ban_wave_breaker" value="1" checked> 24/7 Ban-Wave Circuit Breaker</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="ent_honeypot" id="create_ent_honeypot" value="1" checked> Silent Honeypot Fake Offsets Mode</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="ent_geo_lock" id="create_ent_geo_lock" value="1" checked> Geo-IP Country Lock Control</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="ent_watermark_off" id="create_ent_watermark_off" value="1" checked> Allow Buyer to Turn Watermark OFF</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="ent_crash_bot" id="create_ent_crash_bot" value="1" checked> Developer <code>.so</code> Crash Telegram Bot</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="buyer_can_roll_key" id="create_buyer_can_roll_key" value="1" checked> <b>Self-Service API Key Roll / Regenerate</b></label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="buyer_can_unban" value="1" checked> Allow Buyer to Unban Devices</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="buyer_can_toggle_autoban" value="1" checked> Allow Buyer to Toggle Auto-Ban</label>
                    </div>
                </div>

                <div class="field-group" style="grid-column:1/-1;">
                    <label class="field-label">Admin Rights: Authorized Games for This License</label>
                    <div style="display:flex;gap:12px;flex-wrap:wrap;padding:8px 0;">
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" name="allowed_games[]" value="*" checked> <b>Universal (All Games)</b>
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" name="allowed_games[]" value="com.pubg.imobile"> BGMI
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" name="allowed_games[]" value="com.tencent.ig"> PUBG Global
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" name="allowed_games[]" value="com.pubg.krmobile"> PUBG KR
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" name="allowed_games[]" value="com.dts.freefireth"> Free Fire
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" name="allowed_games[]" value="com.activision.callofduty.shooter"> COD Mobile
                        </label>
                    </div>
                </div>

                <div class="field-group" style="grid-column:1/-1;justify-content:flex-end;">
                    <button type="submit" class="ios-btn btn-primary" style="height:42px;">
                        <?= fbox_svg('plus', 14, '#fff') ?> Generate Key &amp; Buyer Access Pack
                    </button>
                </div>
            </form>
        </div>

        
    <?php if (!empty($_SESSION['fbox_last_batch'])): 
        $batch = $_SESSION['fbox_last_batch'];
        $batchRaw = implode("
", $batch['keys']);
        $batchExport = "FEATURESTIC BOX — BATCH LICENSE KEYS (" . $batch['count'] . " Keys | Duration: " . $batch['days'] . " Days)
"
            . "Created: " . $batch['time'] . " | Games: " . $batch['games'] . "
"
            . "Portal: " . $panelUrl . "
"
            . "============================================================
"
            . $batchRaw . "
"
            . "============================================================
"
            . "Instructions:
"
            . "1. Provide each client with one unique license key from above.
"
            . "2. Clients enter their license key in their Loader app to connect.";
    ?>
    <div class="ios-card" style="margin-bottom:20px;border:1.5px solid var(--ios-blue);background:rgba(0,122,255,0.03);">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div class="nav-logo-icon" style="width:34px;height:34px;border-radius:9px;background:var(--ios-blue);">
                    <?= fbox_svg('key', 16, '#fff') ?>
                </div>
                <div>
                    <h3 style="font-size:15px;font-weight:700;margin:0;">Batch Generated: <?= $batch['count'] ?> Keys (<?= $batch['days'] ?> Days)</h3>
                    <div style="font-size:12px;color:var(--text-secondary);">Ready for immediate distribution to wholesale buyers or tournament teams</div>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" class="ios-btn btn-primary btn-sm" onclick="copyBatchText()"><?= fbox_svg('copy', 13, '#fff') ?> Copy All Keys</button>
                <button type="button" class="ios-btn btn-secondary btn-sm" onclick="downloadBatchFile()"><?= fbox_svg('bolt', 13) ?> Download .TXT</button>
                <a href="index.php?dismiss_batch=1" class="ios-btn btn-secondary btn-sm">Dismiss</a>
            </div>
        </div>
        <textarea id="batchTextarea" class="ios-input" style="height:130px;font-family:monospace;font-size:12px;white-space:pre;line-height:1.5;" readonly><?= htmlspecialchars($batchExport) ?></textarea>
    </div>
    <?php endif; ?>

        <div class="ios-card">
            <div class="section-header">
                <div>
                    <div class="section-title"><?= fbox_svg('key', 18, 'var(--ios-blue)') ?> License Keys Directory</div>
                    <div class="section-desc">Manage keys, update buyer rights, edit authorized games, or view delivery packs</div>
                </div>
            </div>
            <div class="table-wrap">
                <table class="ios-table">
                    <thead>
                        <tr>
                            <th>License Key</th>
                            <th>Status</th>
                            <th>HWID</th>
                            <th>Buyer Account</th>
                            <th>Slots</th>
                            <th>Allowed Games</th>
                            <th>Expiry</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($licenseKeys as $k):
                        $hwids = json_decode((string)$k['bound_hwids_json'], true) ?: [];
                        $exp   = (int)$k['expires_at'];
                        $games = json_decode((string)($k['allowed_games_json'] ?? '["*"]'), true) ?: ['*'];
                        $gamesStr = in_array('*', $games, true) ? 'All (*)' : implode(', ', $games);
                        $slots = (int)($k['max_loader_slots'] ?? 1);
                    ?>
                        <tr>
                            <td>
                                <span class="mono-code"><?= htmlspecialchars($k['license_key']) ?></span>
                                <?php if ($k['label'] !== ''): ?>
                                    <div style="font-size:11.5px;color:var(--text-tertiary);margin-top:2px;"><?= htmlspecialchars($k['label']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($k['status'] === 'active'): ?>
                                    <span class="badge badge-green">Active</span>
                                <?php elseif ($k['status'] === 'paused'): ?>
                                    <span class="badge badge-orange">Paused</span>
                                <?php else: ?>
                                    <span class="badge badge-red"><?= htmlspecialchars(ucfirst($k['status'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-blue"><?= count($hwids) ?> / <?= (int)$k['max_devices'] ?></span>
                            </td>
                            <td>
                                <?php if (!empty($k['buyer_username'])): ?>
                                    <span class="badge badge-green"><?= fbox_svg('user', 11) ?> <?= htmlspecialchars($k['buyer_username']) ?></span>
                                <?php else: ?>
                                    <span style="font-size:11.5px;color:var(--text-tertiary);">Key Login</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-orange"><?= $slots === 0 ? 'Unlimited' : $slots . ' Slots' ?></span>
                            </td>
                            <td>
                                <span style="font-size:11.5px;color:var(--text-secondary);"><?= htmlspecialchars(substr($gamesStr, 0, 24)) . (strlen($gamesStr) > 24 ? '...' : '') ?></span>
                            </td>
                            <td>
                                <?= $exp === 0 ? ((int)$k['duration_days'] . ' Days') : date('d M Y', $exp) ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                    <button type="button" class="ios-btn btn-secondary btn-sm" onclick='openEditModal(<?= json_encode($k) ?>)'>
                                        <?= fbox_svg('edit', 12) ?> Edit
                                    </button>
                                    <button type="button" class="ios-btn btn-primary btn-sm" onclick='showKeyPack(<?= json_encode($k) ?>, "<?= htmlspecialchars($panelUrl) ?>", "<?= htmlspecialchars($autoApiUrl) ?>")'>
                                        <?= fbox_svg('share', 12, '#fff') ?> Pack
                                    </button>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                                        <input type="hidden" name="form_action" value="key_action">
                                        <input type="hidden" name="key_id" value="<?= (int)$k['id'] ?>">
                                        <button type="submit" name="sub_action" value="reset_hwid" class="ios-btn btn-secondary btn-sm" title="Reset HWID">
                                            <?= fbox_svg('refresh', 12) ?>
                                        </button>
                                        <button type="submit" name="sub_action" value="toggle_status" class="ios-btn <?= $k['status'] === 'active' ? 'btn-secondary' : 'btn-success' ?> btn-sm" title="Toggle Active / Blocked">
                                            <?= $k['status'] === 'active' ? 'Block' : 'Unblock' ?>
                                        </button>
                                        <button type="submit" name="sub_action" value="extend_30d" class="ios-btn btn-secondary btn-sm" title="Extend +30 Days">
                                            +30d
                                        </button>
                                        <button type="submit" name="sub_action" value="delete_key" class="ios-btn btn-danger btn-sm" onclick="return confirm('Delete key?');">
                                            <?= fbox_svg('trash', 12, '#fff') ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Admin Edit Key Profile & Rights Modal (Collapsible Box) -->
        <div id="adminEditModal" style="display:none;background:var(--bg-card);border:2px solid var(--ios-blue);border-radius:14px;padding:20px;margin-top:20px;">
            <div class="section-header">
                <div class="section-title"><?= fbox_svg('edit', 18, 'var(--ios-blue)') ?> Edit Buyer Profile, Games &amp; Access Rights</div>
                <button type="button" class="ios-btn btn-secondary btn-sm" onclick="closeEditModal()">Close</button>
            </div>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                <input type="hidden" name="form_action" value="admin_edit_key_profile">
                <input type="hidden" name="key_id" id="editKeyId" value="">

                <div class="field-group">
                    <label class="field-label">License Key</label>
                    <input type="text" id="editLicenseKey" class="ios-input" readonly style="opacity:0.75;">
                </div>
                <div class="field-group">
                    <label class="field-label">Client Name / Label</label>
                    <input type="text" name="label" id="editLabel" class="ios-input">
                </div>
                <div class="field-group">
                    <label class="field-label">Buyer Portal Username</label>
                    <input type="text" name="buyer_username" id="editBuyerUsername" class="ios-input">
                </div>
                <div class="field-group">
                    <label class="field-label">New Password (leave blank to keep current)</label>
                    <input type="password" name="buyer_password" id="editBuyerPassword" class="ios-input" placeholder="Enter new password">
                </div>
                <div class="field-group">
                    <label class="field-label">Allowed Loader Slots</label>
                    <select name="max_loader_slots" id="editMaxSlots" class="ios-select">
                        <option value="1">1 Loader Slot</option>
                        <option value="3">3 Loader Slots</option>
                        <option value="5">5 Loader Slots</option>
                        <option value="0">Unlimited Loader Slots</option>
                    </select>
                </div>

                <div class="field-group">
                    <label class="field-label">Max Bound Devices (HWID Limit)</label>
                    <input type="number" name="max_devices" id="editMaxDevices" class="ios-input" min="0" max="10000">
                </div>
                <div class="field-group">
                    <label class="field-label">Key Status</label>
                    <select name="status" id="editStatus" class="ios-select">
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label">Fake Flagship Device Spoofer</label>
                    <select name="custom_device_preset" id="editDevicePreset" class="ios-select">
                        <option value="ROG_PHONE_8">ASUS ROG Phone 8 (Adreno 750 • 120 FPS)</option>
                        <option value="IPAD_PRO_M4">iPad Pro M4 / Tab S9 Ultra (120 FPS)</option>
                        <option value="S24_ULTRA">Samsung Galaxy S24 Ultra (120 FPS)</option>
                        <option value="REDMAGIC_9_PRO">Nubia RedMagic 9 Pro (120 FPS)</option>
                        <option value="ONEPLUS_12">OnePlus 12 Flagship (90/120 FPS)</option>
                        <option value="IQOO_12">iQOO 12 5G Esports (120 FPS)</option>
                        <option value="XIAOMI_14_PRO">Xiaomi 14 Pro (120 FPS)</option>
                        <option value="NONE">OFF — Real Phone Hardware</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label">Target Frame Rate Unlock</label>
                    <select name="custom_target_fps" id="editTargetFps" class="ios-select">
                        <option value="120">120 FPS (Extreme+)</option>
                        <option value="90">90 FPS (Smooth)</option>
                        <option value="60">60 FPS (Standard)</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label">Silent Honeypot Fake Offsets Mode</label>
                    <select name="honeypot_on_tamper" id="editHoneypotMode" class="ios-select">
                        <option value="0">OFF (Normal Ban Screen)</option>
                        <option value="1">ON (Feed Fake 0xDEAD0000 Offsets to Crackers)</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label">Geo-IP Country Lock</label>
                    <select name="allowed_countries" id="editAllowedCountries" class="ios-select">
                        <option value="ALL">Global (All Countries)</option>
                        <option value="IN">India Only (IN)</option>
                        <option value="IN,NP,BD,PK,AE,SA">South Asia &amp; Middle East</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label">24/7 Ban-Wave Circuit Breaker (Optional)</label>
                    <select name="ban_wave_breaker_enabled" id="editBreakerEnabled" class="ios-select">
                        <option value="0">OFF (Disabled by Default)</option>
                        <option value="1">ON (Auto-Pause on Ban/Crash Spike)</option>
                    </select>
                </div>
                <div class="field-group" style="display:flex;align-items:flex-end;">
                    <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;padding:10px 0;">
                        <input type="checkbox" name="reset_circuit_breaker" value="1" id="editResetBreaker"> Reset Tripped Auto-Shield Pause
                    </label>
                </div>

                <div class="field-group">
                    <label class="field-label">Subscription Plan Preset</label>
                    <select name="plan_tier" id="editPlanTier" class="ios-select" onchange="applyPlanPreset(this.value, 'edit')">
                        <option value="enterprise">Enterprise / VIP Studio Plan (All Unlocked)</option>
                        <option value="pro">Pro Developer Plan</option>
                        <option value="starter">Starter / Basic Plan</option>
                        <option value="custom">Custom Deal</option>
                    </select>
                </div>

                <div class="field-group" style="grid-column:1/-1;background:rgba(0,122,255,0.04);border:1px solid rgba(0,122,255,0.18);border-radius:12px;padding:14px;">
                    <label class="field-label" style="color:var(--ios-blue);font-weight:700;margin-bottom:8px;">
                        Edit Buyer Feature Entitlements &amp; Self-Service API Key Rights (Upgrade / Downgrade Plan Anytime)
                    </label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:8px;font-size:12.5px;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="edit_ent_cloud_so" id="edit_ent_cloud_so" value="1"> OTA Cloud <code>.so</code> &amp; Offsets Vault</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="edit_ent_ram_only_so" id="edit_ent_ram_only_so" value="1"> RAM-Only <code>.so</code> &amp; <code>libanogs</code> Maps Cloak</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="edit_ent_device_spoofer" id="edit_ent_device_spoofer" value="1"> Fake Flagship Device Spoofer (120 FPS)</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="edit_ent_streamproof" id="edit_ent_streamproof" value="1"> Streamer Mode (<code>FLAG_SECURE</code>)</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="edit_ent_telemetry_silencer" id="edit_ent_telemetry_silencer" value="1"> Anticheat Telemetry &amp; Log Silencer</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="edit_ent_ram_diet" id="edit_ent_ram_diet" value="1"> Low-End RAM Diet &amp; Thread Booster</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="edit_ent_ban_wave_breaker" id="edit_ent_ban_wave_breaker" value="1"> 24/7 Ban-Wave Circuit Breaker</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="edit_ent_honeypot" id="edit_ent_honeypot" value="1"> Silent Honeypot Fake Offsets Mode</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="edit_ent_geo_lock" id="edit_ent_geo_lock" value="1"> Geo-IP Country Lock Control</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="edit_ent_watermark_off" id="edit_ent_watermark_off" value="1"> Allow Buyer to Turn Watermark OFF</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="edit_ent_crash_bot" id="edit_ent_crash_bot" value="1"> Developer <code>.so</code> Crash Telegram Bot</label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="checkbox" name="buyer_can_roll_key" id="edit_buyer_can_roll_key" value="1"> <b>Allow Self-Service API Key Roll</b></label>
                    </div>
                </div>

                <div class="field-group" style="grid-column:1/-1;">
                    <label class="field-label">Authorized Games for this Client</label>
                    <div style="display:flex;gap:12px;flex-wrap:wrap;padding:8px 0;" id="editGamesCheckboxes">
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" name="allowed_games[]" value="*" id="gameCheckAll"> <b>Universal (*)</b>
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" name="allowed_games[]" value="com.pubg.imobile" id="gameCheckBgmi"> BGMI
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" name="allowed_games[]" value="com.tencent.ig" id="gameCheckGl"> PUBG Global
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" name="allowed_games[]" value="com.pubg.krmobile" id="gameCheckKr"> PUBG KR
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" name="allowed_games[]" value="com.dts.freefireth" id="gameCheckFf"> Free Fire
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" name="allowed_games[]" value="com.activision.callofduty.shooter" id="gameCheckCod"> COD Mobile
                        </label>
                    </div>
                </div>

                <div class="field-group" style="grid-column:1/-1;justify-content:flex-end;flex-direction:row;gap:8px;">
                    <button type="button" class="ios-btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="ios-btn btn-primary"><?= fbox_svg('check', 14, '#fff') ?> Save Client Profile &amp; Rights</button>
                </div>
            </form>
        </div>
    </section>

    <!-- TAB 3: BANNED DEVICES -->
    <section id="tab-bans" class="tab-pane">
        <div class="ios-card">
            <div class="section-header">
                <div>
                    <div class="section-title"><?= fbox_svg('ban', 18, 'var(--ios-red)') ?> Banned Devices &amp; Unban Center</div>
                    <div class="section-desc">Devices automatically blacklisted by the SDK when crack, repackage, Frida, debugger, or proxy tampering is detected.</div>
                </div>
            </div>
            <div class="table-wrap">
                <table class="ios-table">
                    <thead>
                        <tr>
                            <th>Device HWID</th>
                            <th>Device Model &amp; IP</th>
                            <th>Reason</th>
                            <th>Source</th>
                            <th>Banned At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($deviceBans)): ?>
                        <tr><td colspan="6" style="color:var(--text-tertiary);text-align:center;padding:24px;">No banned devices recorded yet.</td></tr>
                    <?php else: foreach ($deviceBans as $b): ?>
                        <tr>
                            <td>
                                <span class="mono-code"><?= htmlspecialchars(substr($b['hwid'], 0, 24)) ?>...</span>
                                <?php if ($b['license_key'] !== ''): ?>
                                    <div style="font-size:11.5px;color:var(--text-tertiary);margin-top:2px;">Key: <?= htmlspecialchars($b['license_key']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($b['device_model'] ?: 'Android') ?></div>
                                <div style="font-size:11.5px;color:var(--text-tertiary);"><?= htmlspecialchars($b['ip_address']) ?></div>
                            </td>
                            <td><span class="badge badge-red"><?= htmlspecialchars($b['ban_reason']) ?></span></td>
                            <td><?= htmlspecialchars($b['ban_source']) ?></td>
                            <td><?= date('d M Y, H:i', (int)$b['banned_at']) ?></td>
                            <td>
                                <?php if ((int)$b['is_active'] === 1): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                                        <input type="hidden" name="form_action" value="unban_device">
                                        <input type="hidden" name="ban_id" value="<?= (int)$b['id'] ?>">
                                        <button type="submit" class="ios-btn btn-success btn-sm"><?= fbox_svg('check', 12, '#fff') ?> Unban Device</button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge badge-green">Unbanned</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="ios-card">
            <div class="section-header">
                <div class="section-title"><?= fbox_svg('ban', 18, 'var(--ios-red)') ?> Manual HWID Ban</div>
            </div>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                <input type="hidden" name="form_action" value="manual_ban_device">
                <div class="field-group">
                    <label class="field-label">Device HWID (64-char SHA-256)</label>
                    <input type="text" name="hwid" class="ios-input" placeholder="Paste device HWID" required>
                </div>
                <div class="field-group">
                    <label class="field-label">Device Label</label>
                    <input type="text" name="device_model" class="ios-input" placeholder="Optional model">
                </div>
                <div class="field-group">
                    <label class="field-label">Ban Reason</label>
                    <input type="text" name="ban_reason" class="ios-input" value="MANUAL_ADMIN_BAN" required>
                </div>
                <div class="field-group" style="justify-content:flex-end;">
                    <button type="submit" class="ios-btn btn-danger" style="height:40px;"><?= fbox_svg('ban', 14, '#fff') ?> Ban Device HWID</button>
                </div>
            </form>
        </div>
    </section>

    <!-- TAB 4: ENCRYPTED OFFSETS & CLOUD OTA PLUGIN VAULT -->
    <section id="tab-vault" class="tab-pane">
        <div class="ios-card">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                <input type="hidden" name="form_action" value="save_payload_vault">
                <div class="section-header">
                    <div>
                        <div class="section-title"><?= fbox_svg('terminal', 18, 'var(--ios-blue)') ?> Master Encrypted Offsets &amp; OTA Plugin Vault</div>
                        <div class="section-desc">Encrypted with AES-256-CBC and delivered in RAM. Per-buyer custom offsets override these defaults.</div>
                    </div>
                    <button type="submit" class="ios-btn btn-primary">Save Encrypted Vault</button>
                </div>

                <div class="field-group" style="margin-bottom:18px;">
                    <label class="field-label">Master Encrypted Offsets JSON (Never stored in APK)</label>
                    <textarea name="runtime_params_json" class="ios-textarea"><?= htmlspecialchars($settings['runtime_params_json'] ?? '{}') ?></textarea>
                </div>

                <div class="form-grid">
                    <div class="field-group">
                        <label class="field-label">Master 64-Bit .so Download URL (GitHub Raw)</label>
                        <input type="text" name="plugin_url_64" class="ios-input" value="<?= htmlspecialchars($settings['plugin_url_64'] ?? '') ?>" placeholder="https://raw.githubusercontent.com/.../lib64.so">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Master 32-Bit .so Download URL (GitHub Raw)</label>
                        <input type="text" name="plugin_url_32" class="ios-input" value="<?= htmlspecialchars($settings['plugin_url_32'] ?? '') ?>" placeholder="https://raw.githubusercontent.com/.../lib32.so">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Optional Master Plugin SHA-256</label>
                        <input type="text" name="plugin_sha256" class="ios-input" value="<?= htmlspecialchars($settings['plugin_sha256'] ?? '') ?>" placeholder="64-char hex">
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- TAB 5: SDK RUNTIME & SECURITY -->
    <section id="tab-settings" class="tab-pane">
        <div class="ios-card">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                <input type="hidden" name="form_action" value="save_sdk_settings">
                <div class="section-header">
                    <div>
                        <div class="section-title"><?= fbox_svg('gear', 18, 'var(--ios-blue)') ?> SDK Runtime, Alerts &amp; Endpoint Security</div>
                        <div class="section-desc">Manage Telegram/Discord crack alerts, forced version updates, and secrets.</div>
                    </div>
                    <button type="submit" class="ios-btn btn-primary">Save All Settings</button>
                </div>

                <div class="form-grid" style="margin-bottom:18px;">
                    <div class="field-group">
                        <label class="field-label">Remote Target FPS</label>
                        <select name="target_fps" class="ios-select">
                            <option value="60" <?= ($settings['target_fps'] ?? '120') === '60' ? 'selected' : '' ?>>60 FPS</option>
                            <option value="90" <?= ($settings['target_fps'] ?? '120') === '90' ? 'selected' : '' ?>>90 FPS</option>
                            <option value="120" <?= ($settings['target_fps'] ?? '120') === '120' ? 'selected' : '' ?>>120 FPS Ultra</option>
                        </select>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Hardware Device Profile</label>
                        <select name="device_preset" class="ios-select">
                            <?php foreach (['ROG_PHONE_8', 'REDMAGIC_9_PRO', 'S24_ULTRA', 'ONEPLUS_12', 'IQOO_12', 'XIAOMI_14_PRO'] as $p): ?>
                                <option value="<?= $p ?>" <?= ($settings['device_preset'] ?? 'ROG_PHONE_8') === $p ? 'selected' : '' ?>><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="field-group">
                        <label class="field-label">Telegram Bot Token (Live Crack Alerts)</label>
                        <input type="text" name="telegram_bot_token" class="ios-input" value="<?= htmlspecialchars($settings['telegram_bot_token'] ?? '') ?>" placeholder="e.g. 123456:ABC-DEF...">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Telegram Chat ID</label>
                        <input type="text" name="telegram_chat_id" class="ios-input" value="<?= htmlspecialchars($settings['telegram_chat_id'] ?? '') ?>" placeholder="e.g. -100123456789">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Discord Webhook URL (Live Crack Alerts)</label>
                        <input type="text" name="discord_webhook_url" class="ios-input" value="<?= htmlspecialchars($settings['discord_webhook_url'] ?? '') ?>" placeholder="https://discord.com/api/webhooks/...">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Minimum Loader Version (Version Guard)</label>
                        <input type="number" name="min_loader_version" class="ios-input" value="<?= htmlspecialchars($settings['min_loader_version'] ?? '1') ?>" min="1">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Update APK Download URL</label>
                        <input type="text" name="update_apk_url" class="ios-input" value="<?= htmlspecialchars($settings['update_apk_url'] ?? '') ?>" placeholder="https://your-domain.com/loader-update.apk">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Whitelisted Loader APK Signing SHA-256</label>
                        <input type="text" name="locked_apk_sha256" class="ios-input" value="<?= htmlspecialchars($settings['locked_apk_sha256'] ?? '') ?>">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Pinned Server TLS Cert SHA-256</label>
                        <input type="text" name="pinned_tls_cert_sha256" class="ios-input" value="<?= htmlspecialchars($settings['pinned_tls_cert_sha256'] ?? '') ?>" placeholder="Leave blank or 64-char SSL SHA-256">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Shared HMAC &amp; AES-256 Secret Key</label>
                        <input type="text" name="shared_api_secret" class="ios-input" value="<?= htmlspecialchars($settings['shared_api_secret'] ?? '') ?>">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Dynamic Primary API Endpoint URL</label>
                        <input type="text" name="dynamic_primary_endpoint" class="ios-input" value="<?= htmlspecialchars($settings['dynamic_primary_endpoint'] ?: $autoApiUrl) ?>">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Maintenance Banner Message</label>
                        <input type="text" name="maintenance_message" class="ios-input" value="<?= htmlspecialchars($settings['maintenance_message'] ?? '') ?>">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Change Admin Password</label>
                        <input type="password" name="new_admin_password" class="ios-input" placeholder="New password">
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- TAB 6: SECURITY & AUDIT LOGS -->
    <section id="tab-logs" class="tab-pane">
        <div class="ios-card">
            <div class="section-header">
                <div>
                    <div class="section-title"><?= fbox_svg('shield', 18, 'var(--ios-red)') ?> Anti-Crack &amp; Security Violations (7-Day Auto-Purge)</div>
                    <div class="section-desc">Compact 1-line tamper alerts. Full diagnostic dumps are preserved on your Telegram Bot.</div>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                    <input type="hidden" name="form_action" value="clear_logs">
                    <button type="submit" class="ios-btn btn-danger btn-sm"><?= fbox_svg('trash', 12, '#fff') ?> Clear Logs Now</button>
                </form>
            </div>

            <div style="font-size:12px;color:var(--text-secondary);margin-bottom:14px;padding:8px 12px;background:rgba(0,122,255,0.06);border-radius:8px;border:1px solid rgba(0,122,255,0.18);display:flex;align-items:center;gap:6px;">
                <?= fbox_svg('terminal', 13, 'var(--ios-blue)') ?> <b>Telegram Bot Cloud Archiving:</b> All security violations are pushed with full forensic details to Telegram. Panel logs show compact 1-line tags and automatically purge after 7 days to keep the database fast.
            </div>

            <div class="table-wrap">
                <table class="ios-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Threat Tag</th>
                            <th>Violation Summary</th>
                            <th>Offending Device &amp; HWID</th>
                            <th>Telegram Archive</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($securityLogs)): ?>
                        <tr><td colspan="5" style="color:var(--text-tertiary);text-align:center;padding:24px;">No security violations logged. All systems running clean!</td></tr>
                    <?php else: foreach ($securityLogs as $log):
                        $sev = $log['severity'];
                        $bClass = $sev === 'critical' ? 'badge-red' : ($sev === 'warning' ? 'badge-orange' : 'badge-blue');
                    ?>
                        <tr>
                            <td><?= date('d M H:i:s', (int)$log['created_at']) ?></td>
                            <td><span class="badge <?= $bClass ?>"><?= htmlspecialchars($log['event_type']) ?></span></td>
                            <td>
                                <div style="font-weight:600;font-size:12.5px;"><?= htmlspecialchars(substr($log['details'], 0, 80)) ?></div>
                                <?php if ($log['license_key'] !== ''): ?>
                                    <div style="font-size:11px;color:var(--text-tertiary);">Key: <code><?= htmlspecialchars($log['license_key']) ?></code></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($log['device_model'] ?: 'Android') ?></div>
                                <?php if ($log['hwid'] !== ''): ?>
                                    <div style="font-size:11px;color:var(--text-tertiary);font-family:monospace;"><?= htmlspecialchars(substr($log['hwid'], 0, 16)) ?>...</div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-blue"><?= fbox_svg('share', 11) ?> Archived on Bot</span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- TAB 7: CRASH DIAGNOSTICS & TELEMETRY -->
    <section id="tab-crashes" class="tab-pane">
        <div class="ios-card">
            <div class="section-header">
                <div>
                    <div class="section-title"><?= fbox_svg('alert', 18, 'var(--ios-orange)') ?> .so Crash Telemetry &amp; Diagnostics (7-Day Auto-Purge)</div>
                    <div class="section-desc">Compact 1-line crash indicators. Full memory offsets, fault addresses, and stack traces are dispatched directly to your Telegram Bot.</div>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['fbox_csrf']) ?>">
                    <input type="hidden" name="form_action" value="clear_crash_reports">
                    <button type="submit" class="ios-btn btn-danger btn-sm"><?= fbox_svg('trash', 12, '#fff') ?> Clear Crash Reports</button>
                </form>
            </div>

            <div style="font-size:12px;color:var(--text-secondary);margin-bottom:14px;padding:8px 12px;background:rgba(255,149,0,0.06);border-radius:8px;border:1px solid rgba(255,149,0,0.22);display:flex;align-items:center;gap:6px;">
                <?= fbox_svg('alert', 13, 'var(--ios-orange)') ?> <b>Direct Telegram Telemetry:</b> Deep stacktraces, crashing library names, and hex offsets are sent in real-time to your Telegram Bot. Panel retains compact summaries for 7 days before auto-cleanup.
            </div>

            <div class="table-wrap">
                <table class="ios-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Signal</th>
                            <th>Fault Location / Library</th>
                            <th>Game &amp; Loader</th>
                            <th>Device</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($crashReports)): ?>
                        <tr><td colspan="6" style="color:var(--text-tertiary);text-align:center;padding:24px;">No crash reports recorded. All game hooks running smoothly!</td></tr>
                    <?php else: foreach ($crashReports as $cr): ?>
                        <tr>
                            <td><?= date('d M H:i:s', (int)$cr['created_at']) ?></td>
                            <td><span class="badge badge-red"><?= htmlspecialchars($cr['crash_type']) ?></span></td>
                            <td>
                                <div><strong><?= htmlspecialchars($cr['crashing_library'] ?: 'libFeaturesticPlugin.so') ?></strong></div>
                                <?php if (!empty($cr['crashing_offset']) || !empty($cr['fault_address'])): ?>
                                    <div style="font-size:11px;color:var(--text-tertiary);font-family:monospace;">
                                        Offset: <?= htmlspecialchars($cr['crashing_offset'] ?: '0x0') ?> | Addr: <?= htmlspecialchars($cr['fault_address'] ?: '—') ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><span class="mono-code"><?= htmlspecialchars($cr['package_name'] ?: 'Unknown') ?></span></div>
                                <div style="font-size:11px;color:var(--text-tertiary);">Loader: <?= htmlspecialchars($cr['loader_id'] ?: 'default') ?></div>
                            </td>
                            <td><?= htmlspecialchars($cr['device_model'] ?: 'Android') ?></td>
                            <td>
                                <button type="button" class="ios-btn btn-secondary btn-sm" onclick='showCrashModal(<?= json_encode($cr) ?>)'>
                                    <?= fbox_svg('eye', 12) ?> View Stack
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Crash Stacktrace Modal Box -->
        <div id="crashDetailBox" style="display:none;background:var(--bg-card);border:2px solid var(--ios-orange);border-radius:var(--radius-card);padding:20px;margin-top:20px;">
            <div class="section-header">
                <div class="section-title" id="crashModalTitle"><?= fbox_svg('alert', 18, 'var(--ios-orange)') ?> Diagnostic Crash Detail</div>
                <button type="button" class="ios-btn btn-secondary btn-sm" onclick="closeCrashModal()">Close</button>
            </div>
            <div id="crashMetaLine" style="font-size:12.5px;color:var(--text-secondary);margin-bottom:12px;"></div>
            <pre id="crashStackContent" style="background:var(--bg-primary);border:1px solid var(--border-subtle);border-radius:10px;padding:14px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:11.5px;overflow-x:auto;max-height:400px;line-height:1.45;"></pre>
        </div>
    </section>
</main>
<?php endif; ?>

<script>

function copyBatchText() {
    var el = document.getElementById('batchTextarea');
    if (!el) return;
    navigator.clipboard.writeText(el.value).then(function() {
        alert('All keys copied to clipboard successfully!');
    });
}
function downloadBatchFile() {
    var el = document.getElementById('batchTextarea');
    if (!el) return;
    var blob = new Blob([el.value], {type: 'text/plain;charset=utf-8'});
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'FeaturesticBox_Keys_Batch.txt';
    a.click();
}

function filterTable(input, tableId) {
    const filter = (input.value || '').toLowerCase().trim();
    const table = document.getElementById(tableId);
    if (!table) return;
    const rows = table.getElementsByTagName('tr');
    for (let i = 1; i < rows.length; i++) {
        const text = (rows[i].textContent || rows[i].innerText || '').toLowerCase();
        rows[i].style.display = (filter === '' || text.indexOf(filter) > -1) ? '' : 'none';
    }
}

function showCrashModal(cr) {
    const box = document.getElementById('crashDetailBox');
    if (!box) return;
    document.getElementById('crashModalTitle').innerHTML = '<?= fbox_svg("alert", 18, "var(--ios-orange)") ?> Crash Log: ' + cr.crash_type;
    document.getElementById('crashMetaLine').innerHTML = 'Device: <b>' + cr.device_model + '</b> | Game: <b>' + cr.package_name + '</b> | Key: ' + cr.license_key + ' | Time: ' + new Date(cr.created_at * 1000).toLocaleString();
    document.getElementById('crashStackContent').innerText = cr.stack_trace + (cr.logcat_snippet ? "\n\n--- LOGCAT SNIPPET ---\n" + cr.logcat_snippet : "");
    box.style.display = 'block';
    box.scrollIntoView({ behavior: 'smooth' });
}

function closeCrashModal() {
    const box = document.getElementById('crashDetailBox');
    if (box) box.style.display = 'none';
}

function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.seg-btn').forEach(el => el.classList.remove('active'));
    const target = document.getElementById('tab-' + tabId);
    if (target) target.classList.add('active');
    if (btn) btn.classList.add('active');
    try { localStorage.setItem('fbox_active_tab', tabId); } catch (e) {}
}

function toggleTheme() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-theme') === 'dark';
    const next = isDark ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    try { localStorage.setItem('fbox_ios_theme', next); } catch (e) {}
}

function copyText(elementId, btn) {
    const el = document.getElementById(elementId);
    if (!el) return;
    const text = el.innerText || el.textContent;
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<?= fbox_svg("check", 12) ?> Copied!';
        setTimeout(() => { btn.innerHTML = orig; }, 2000);
    }).catch(() => {
        alert('Copied to clipboard!');
    });
}

function copyDeliveryPackText() {
    const el = document.getElementById('deliveryPackText');
    if (!el) return;
    const text = el.innerText || el.textContent;
    navigator.clipboard.writeText(text).then(() => {
        alert('Client Access Pack copied to clipboard! You can paste and send directly to your client via WhatsApp or Telegram.');
    });
}

function copyIntegrationSnippet() {
    const b1 = document.getElementById('boxAppCode').innerText;
    const b2 = document.getElementById('logActCode').innerText;
    const full = b1 + "\n\n" + b2;
    navigator.clipboard.writeText(full).then(() => {
        alert('Integration Java Code copied! Paste directly into your Android Studio Loader project.');
    });
}

function toggleAddLoaderForm() {
    const box = document.getElementById('addLoaderBox');
    if (box) {
        box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'block' : 'none';
    }
}

function setPkg(val) {
    const input = document.getElementById('targetPkgInput');
    if (input) input.value = val;
}


function applyPlanPreset(tier, prefix) {
    if (tier === 'custom') return;
    const presets = {
        starter: {
            ent_cloud_so: 0, ent_ram_only_so: 0, ent_device_spoofer: 0, ent_streamproof: 1,
            ent_telemetry_silencer: 1, ent_ram_diet: 1, ent_ban_wave_breaker: 0, ent_honeypot: 0,
            ent_geo_lock: 0, ent_watermark_off: 0, ent_crash_bot: 0
        },
        pro: {
            ent_cloud_so: 1, ent_ram_only_so: 0, ent_device_spoofer: 1, ent_streamproof: 1,
            ent_telemetry_silencer: 1, ent_ram_diet: 1, ent_ban_wave_breaker: 1, ent_honeypot: 0,
            ent_geo_lock: 1, ent_watermark_off: 1, ent_crash_bot: 1
        },
        enterprise: {
            ent_cloud_so: 1, ent_ram_only_so: 1, ent_device_spoofer: 1, ent_streamproof: 1,
            ent_telemetry_silencer: 1, ent_ram_diet: 1, ent_ban_wave_breaker: 1, ent_honeypot: 1,
            ent_geo_lock: 1, ent_watermark_off: 1, ent_crash_bot: 1
        }
    };
    const p = presets[tier] || presets.enterprise;
    Object.keys(p).forEach(k => {
        const el = document.getElementById(prefix + '_' + k);
        if (el) el.checked = (p[k] === 1);
    });
}

function openEditModal(keyData) {
    const modal = document.getElementById('adminEditModal');
    if (!modal) return;
    document.getElementById('editKeyId').value = keyData.id;
    document.getElementById('editLicenseKey').value = keyData.license_key;
    document.getElementById('editLabel').value = keyData.label || '';
    document.getElementById('editBuyerUsername').value = keyData.buyer_username || '';
    document.getElementById('editMaxSlots').value = keyData.max_loader_slots !== undefined ? keyData.max_loader_slots : 1;
    if (document.getElementById('editDailyDiagLimit')) document.getElementById('editDailyDiagLimit').value = keyData.daily_diag_limit_minutes !== undefined ? keyData.daily_diag_limit_minutes : 120;
    document.getElementById('editMaxDevices').value = keyData.max_devices !== undefined ? keyData.max_devices : 0;
    document.getElementById('editStatus').value = keyData.status || 'active';
    if (document.getElementById('editDevicePreset')) document.getElementById('editDevicePreset').value = keyData.custom_device_preset || 'ROG_PHONE_8';
    if (document.getElementById('editTargetFps')) document.getElementById('editTargetFps').value = keyData.custom_target_fps || 120;
    if (document.getElementById('editHoneypotMode')) document.getElementById('editHoneypotMode').value = keyData.honeypot_on_tamper || 0;
    if (document.getElementById('editAllowedCountries')) document.getElementById('editAllowedCountries').value = keyData.allowed_countries || 'ALL';
    if (document.getElementById('editBreakerEnabled')) document.getElementById('editBreakerEnabled').value = (keyData.ban_wave_breaker_enabled !== undefined) ? keyData.ban_wave_breaker_enabled : 0;
    if (document.getElementById('editResetBreaker')) document.getElementById('editResetBreaker').checked = (parseInt(keyData.circuit_breaker_tripped || 0) === 1);

    let games = [];
    try { games = JSON.parse(keyData.allowed_games_json || '["*"]'); } catch(e) { games = ['*']; }
    document.getElementById('gameCheckAll').checked = games.includes('*');
    document.getElementById('gameCheckBgmi').checked = games.includes('com.pubg.imobile');
    document.getElementById('gameCheckGl').checked = games.includes('com.tencent.ig');
    document.getElementById('gameCheckKr').checked = games.includes('com.pubg.krmobile');
    document.getElementById('gameCheckFf').checked = games.includes('com.dts.freefireth');
    document.getElementById('gameCheckCod').checked = games.includes('com.activision.callofduty.shooter');

    const tier = keyData.plan_tier || 'enterprise';
    if (document.getElementById('editPlanTier')) document.getElementById('editPlanTier').value = tier;
    if (document.getElementById('edit_buyer_can_roll_key')) {
        document.getElementById('edit_buyer_can_roll_key').checked = (keyData.buyer_can_roll_key === undefined || parseInt(keyData.buyer_can_roll_key) === 1);
    }
    applyPlanPreset(tier, 'edit');
    try {
        if (keyData.buyer_entitlements_json) {
            const ent = JSON.parse(keyData.buyer_entitlements_json);
            const keys = ['ent_cloud_so','ent_ram_only_so','ent_device_spoofer','ent_streamproof','ent_telemetry_silencer','ent_ram_diet','ent_ban_wave_breaker','ent_honeypot','ent_geo_lock','ent_watermark_off','ent_crash_bot'];
            keys.forEach(k => {
                const el = document.getElementById('edit_' + k);
                if (el && ent[k] !== undefined) el.checked = (parseInt(ent[k]) === 1);
            });
        }
    } catch (e) {}

    modal.style.display = 'block';
    modal.scrollIntoView({ behavior: 'smooth' });
}

function closeEditModal() {
    const modal = document.getElementById('adminEditModal');
    if (modal) modal.style.display = 'none';
}

function showKeyPack(k, portalUrl, gatewayUrl) {
    let games = [];
    try { games = JSON.parse(k.allowed_games_json || '["*"]'); } catch(e) { games = ['*']; }
    const gamesStr = games.includes('*') ? 'Universal (All Games)' : games.join(', ');
    const slotsStr = k.max_loader_slots === 0 ? 'Unlimited Loaders' : (k.max_loader_slots + ' Loaders Max');

    const pack = "FEATURESTIC BOX — CLIENT ACCESS PACK\n"
               + "------------------------------------------------------------\n"
               + "License Key:   " + k.license_key + "\n"
               + "Portal URL:  " + portalUrl + "\n"
               + "Username:      " + (k.buyer_username || 'Use Key to Log in') + "\n"
               + "Password:      (Password set during creation)\n"
               + "Allowed Games: " + gamesStr + "\n"
               + "Allowed Slots: " + slotsStr + "\n"
               + "Duration:      " + (k.duration_days ? k.duration_days + ' Days' : 'Lifetime') + "\n"
               + "------------------------------------------------------------\n"
               + "Instructions:\n"
               + "1. Log in to your Customer Portal to set your custom .so (GitHub link) & offsets.\n"
               + "2. Put the License Key into your Loader app to connect.";

    navigator.clipboard.writeText(pack).then(() => {
        alert("Client Delivery Pack Copied to Clipboard for " + k.license_key + "!\n\nReady to paste and send to your buyer via WhatsApp or Telegram.");
    }).catch(() => {
        prompt("Copy this Client Delivery Pack:", pack);
    });
}

(function initUi() {
    try {
        const savedTheme = localStorage.getItem('fbox_ios_theme');
        if (savedTheme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
        const savedTab = localStorage.getItem('fbox_active_tab');
        if (savedTab && document.getElementById('tab-' + savedTab)) {
            const buttons = document.querySelectorAll('.seg-btn');
            buttons.forEach(b => {
                if ((b.getAttribute('onclick') || '').indexOf("'" + savedTab + "'") !== -1) {
                    switchTab(savedTab, b);
                }
            });
        }
    } catch (e) {}
})();
</script>
</body>
</html>
