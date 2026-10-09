<?php
/**
 * API HopHop — POST JSON : {"action": "...", ...}
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function json_fail(string $msg, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_ok(array $extra = []): void
{
    echo json_encode(['ok' => true] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_fail('Méthode non autorisée.', 405);
}

$raw = file_get_contents('php://input');
if (strlen($raw) > MAX_SAVE_BYTES) {
    json_fail('Requête trop volumineuse.', 413);
}
$in = json_decode($raw, true);
if (!is_array($in)) {
    $in = [];
}
$action = $in['action'] ?? '';

switch ($action) {

    case 'register':
        register((string) ($in['username'] ?? ''), (string) ($in['password'] ?? ''));
        json_ok(['username' => current_user()] + hophop_session_payload());
        break;

    case 'login':
        do_login((string) ($in['username'] ?? ''), (string) ($in['password'] ?? ''));
        json_ok(['username' => current_user()] + hophop_session_payload());
        break;

    case 'logout':
        $extra = hophop_session_payload();
        do_logout();
        json_ok($extra);
        break;

    case 'session':
        json_ok(hophop_session_payload());
        break;

    case 'load':
        require_login();
        json_ok(['data' => hophop_load_user_data()]);
        break;

    case 'save':
        require_login();
        if (!isset($in['data']) || !is_array($in['data'])) {
            json_fail('Données manquantes ou invalides.');
        }
        if (strlen(json_encode($in['data'])) > MAX_SAVE_BYTES) {
            json_fail('Données trop volumineuses.', 413);
        }
        hophop_save_user_data($in['data']);
        json_ok();
        break;

    case 'delete_account':
        hophop_reject_if_forum_auth();
        require_login();
        $u = current_user();
        $users = get_users();
        unset($users[$u]);
        save_users($users);
        $ctx = hophop_user_context();
        if ($ctx) {
            hophop_user_store_delete($ctx);
        }
        do_logout();
        json_ok();
        break;

    default:
        json_fail('Action inconnue.', 404);
}
