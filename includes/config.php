<?php
/**
 * Configuration HopHop — auto-hébergé (fichiers) ou intégré phpBB (SSO + MySQL).
 */

require_once __DIR__ . '/forum.php';

function hophop_local_config(): array
{
    static $cfg = null;

    if ($cfg !== null) {
        return $cfg;
    }

    $cfg = [];
    $local = __DIR__ . '/config.local.php';

    if (is_file($local)) {
        $loaded = require $local;

        if (is_array($loaded)) {
            $cfg = $loaded;
        }
    }

    return $cfg;
}

function hophop_auth_mode(): string
{
    $mode = (string) (hophop_local_config()['auth_mode'] ?? 'standalone');

    return $mode === 'phpbb' ? 'phpbb' : 'standalone';
}

function hophop_storage(): string
{
    $storage = (string) (hophop_local_config()['storage'] ?? 'file');

    return $storage === 'mysql' ? 'mysql' : 'file';
}

function hophop_uses_phpbb(): bool
{
    return hophop_auth_mode() === 'phpbb' || hophop_storage() === 'mysql';
}

if (hophop_uses_phpbb() && !hophop_bootstrap_phpbb()) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'HopHop : impossible de charger phpBB (vérifiez phpbb_root et le chemin forum/hophop).',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (hophop_auth_mode() === 'standalone') {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    if (!empty($_SERVER['HTTPS'])) {
        ini_set('session.cookie_secure', '1');
    }
    session_set_cookie_params(['samesite' => 'Lax']);
    session_start();
}

define('DATA_DIR', __DIR__ . '/../data');
define('STORE_DIR', DATA_DIR . '/store');
define('USERS_FILE', DATA_DIR . '/users.json');
define('MAX_SAVE_BYTES', 8 * 1024 * 1024);

foreach ([DATA_DIR, STORE_DIR] as $d) {
    if (!is_dir($d) && !mkdir($d, 0775, true) && !is_dir($d)) {
        http_response_code(500);
        die(json_encode(['ok' => false, 'error' => "Impossible de créer le dossier de données ($d)."]));
    }
}

function username_valid(string $u): bool
{
    return (bool) preg_match('/^[A-Za-z0-9_.\-@]{2,40}$/', $u);
}

function user_file(string $u): string
{
    return STORE_DIR . '/' . preg_replace('/[^A-Za-z0-9_.\-@]/', '_', $u) . '.json';
}
