<?php
/**
 * FEATURESTIC BOX — Telegram Security Bot & Remote Diagnostics Engine
 * Provides real-time log archiving, crash telemetry delivery, and remote admin commands:
 *   /status  - Live active sessions, active keys, and banned devices count
 *   /unban   - Instantly unban a device HWID directly from Telegram
 *   /backup  - Generate and receive instant encrypted database snapshot
 *   /recent  - View the 5 most recent security violations
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crypto.php';

$pdo      = fbox_db();
$settings = fbox_get_all_settings();
$botToken = $settings['telegram_bot_token'] ?? '';
$adminChat = $settings['telegram_chat_id'] ?? '';

// 1. Standalone Telegram Bot API Helper
function tg_send_message(string $token, string $chatId, string $htmlText, array $replyMarkup = []): bool {
    if ($token === '' || $chatId === '') return false;
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $payload = [
        'chat_id'                  => $chatId,
        'text'                     => $htmlText,
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => true,
    ];
    if (!empty($replyMarkup)) {
        $payload['reply_markup'] = $replyMarkup;
    }

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            'timeout' => 8,
        ],
    ]);
    $res = @file_get_contents($url, false, $ctx);
    return $res !== false;
}

// 2. Webhook Receiver for Telegram Bot Updates
$rawInput = file_get_contents('php://input');
if ($rawInput !== false && $rawInput !== '') {
    $update = json_decode($rawInput, true);
    if (is_array($update) && isset($update['message'])) {
        $msg    = $update['message'];
        $chatId = (string)($msg['chat']['id'] ?? '');
        $text   = trim((string)($msg['text'] ?? ''));

        // Security check: Only allow authorized admin chat ID
        if ($adminChat !== '' && $chatId !== $adminChat) {
            tg_send_message($botToken, $chatId, "<b>[ACCESS DENIED]</b> Unauthorized Telegram account.");
            exit;
        }

        // Command: /start or /help
        if ($text === '/start' || $text === '/help') {
            $help = "<b>FEATURESTIC BOX • Admin Control Bot</b>\n"
                  . "----------------------------------------\n"
                  . "Available Commands:\n"
                  . "• <code>/status</code> — View active sessions, bans, and server load\n"
                  . "• <code>/unban &lt;HWID&gt;</code> — Unban a blacklisted device instantly\n"
                  . "• <code>/backup</code> — Trigger full database backup to this chat\n"
                  . "• <code>/recent</code> — View last 5 security tamper violations\n"
                  . "• <code>/keys</code> — View summary of active license keys\n"
                  . "----------------------------------------\n"
                  . "All deep .so crash stacktraces & tamper alerts are archived here automatically.";
            tg_send_message($botToken, $chatId, $help);
            exit;
        }

        // Command: /status
        if ($text === '/status') {
            $actKeys = (int)$pdo->query("SELECT COUNT(*) FROM license_keys WHERE status = 'active'")->fetchColumn();
            $actBans = (int)$pdo->query("SELECT COUNT(*) FROM device_bans WHERE is_active = 1")->fetchColumn();
            $actSess = (int)$pdo->query("SELECT COUNT(*) FROM active_sessions WHERE expires_at > " . time())->fetchColumn();
            $resell  = (int)$pdo->query("SELECT COUNT(*) FROM resellers")->fetchColumn();

            $reply = "<b>FEATURESTIC BOX • Server Health Status</b>\n"
                   . "----------------------------------------\n"
                   . "• <b>Active Sessions:</b> $actSess devices online\n"
                   . "• <b>Active Licenses:</b> $actKeys valid keys\n"
                   . "• <b>Banned HWIDs:</b> $actBans blacklisted\n"
                   . "• <b>Sub-Resellers:</b> $resell registered\n"
                   . "• <b>Gateway Status:</b> ONLINE (SHA256withRSA)\n"
                   . "• <b>Log Auto-Purge:</b> Active (7-Day Rolling Retention)\n"
                   . "----------------------------------------\n"
                   . "Server Time: " . date('d M Y H:i:s');
            tg_send_message($botToken, $chatId, $reply);
            exit;
        }

        // Command: /unban <HWID>
        if (str_starts_with($text, '/unban')) {
            $parts = explode(' ', $text, 2);
            $targetHwid = trim($parts[1] ?? '');
            if ($targetHwid === '') {
                tg_send_message($botToken, $chatId, "<b>[USAGE]</b> <code>/unban &lt;device_hwid&gt;</code>");
                exit;
            }

            $stmt = $pdo->prepare("SELECT id, device_model, license_key FROM device_bans WHERE hwid = ? AND is_active = 1");
            $stmt->execute([$targetHwid]);
            $row = $stmt->fetch();
            if ($row) {
                $pdo->prepare("UPDATE device_bans SET is_active = 0, unbanned_at = ? WHERE id = ?")->execute([time(), (int)$row['id']]);
                fbox_log_security_event('DEVICE_UNBANNED_BY_TELEGRAM', 'info', $targetHwid, (string)$row['license_key'], (string)$row['device_model'], 'Admin unbanned device via Telegram command');
                tg_send_message($botToken, $chatId, "<b>[SUCCESS]</b> Device <code>" . htmlspecialchars($targetHwid) . "</code> has been UNBANNED.\nModel: " . htmlspecialchars($row['device_model']) . "\nUser can launch the game immediately.");
            } else {
                tg_send_message($botToken, $chatId, "<b>[NOT FOUND]</b> No active ban found matching HWID: <code>" . htmlspecialchars($targetHwid) . "</code>");
            }
            exit;
        }

        // Command: /backup
        if ($text === '/backup') {
            tg_send_message($botToken, $chatId, "<b>[BACKUP]</b> Generating encrypted database snapshot...");
            $res = fbox_backup_database($botToken, $chatId);
            if ($res['status'] === 'OK') {
                tg_send_message($botToken, $chatId, "<b>[BACKUP COMPLETE]</b> " . htmlspecialchars($res['message']));
            } else {
                tg_send_message($botToken, $chatId, "<b>[BACKUP FAILED]</b> " . htmlspecialchars($res['message']));
            }
            exit;
        }

        // Command: /recent
        if ($text === '/recent') {
            $logs = $pdo->query("SELECT * FROM security_logs ORDER BY id DESC LIMIT 5")->fetchAll();
            if (empty($logs)) {
                tg_send_message($botToken, $chatId, "<b>[CLEAN]</b> No security violations recorded recently.");
            } else {
                $reply = "<b>FEATURESTIC BOX • Recent Tamper Events</b>\n"
                       . "----------------------------------------\n";
                foreach ($logs as $l) {
                    $reply .= "• <b>" . htmlspecialchars($l['event_type']) . "</b> (" . date('d M H:i', (int)$l['created_at']) . ")\n"
                            . "  HWID: <code>" . htmlspecialchars(substr($l['hwid'], 0, 16)) . "...</code>\n"
                            . "  Details: " . htmlspecialchars(substr($l['details'], 0, 70)) . "\n\n";
                }
                tg_send_message($botToken, $chatId, $reply);
            }
            exit;
        }

        // Command: /keys
        if ($text === '/keys') {
            $total = (int)$pdo->query("SELECT COUNT(*) FROM license_keys")->fetchColumn();
            $active = (int)$pdo->query("SELECT COUNT(*) FROM license_keys WHERE status = 'active'")->fetchColumn();
            $paused = (int)$pdo->query("SELECT COUNT(*) FROM license_keys WHERE status = 'paused'")->fetchColumn();
            $resellerKeys = (int)$pdo->query("SELECT COUNT(*) FROM license_keys WHERE created_by_reseller != ''")->fetchColumn();

            $reply = "<b>FEATURESTIC BOX • License Keys Inventory</b>\n"
                   . "----------------------------------------\n"
                   . "• Total Issued Keys: <b>$total</b>\n"
                   . "• Active Keys: <b>$active</b>\n"
                   . "• Paused Keys: <b>$paused</b>\n"
                   . "• Reseller-Issued Keys: <b>$resellerKeys</b>\n"
                   . "----------------------------------------\n"
                   . "Manage tiers & rights via your web control panel.";
            tg_send_message($botToken, $chatId, $reply);
            exit;
        }
    }
}

// 3. Fallback Info when accessed directly in browser
header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'status'  => 'OK',
    'service' => 'FeaturesticBox Telegram Telemetry & Archival Daemon',
    'webhook' => 'Active',
    'retention' => '7-day rolling auto-purge on panel, permanent archival on Telegram'
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
