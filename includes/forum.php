<?php

/**
 * Bootstrap phpBB 3.3 pour HopHop (même site, ex. /forum/hophop/).
 */

function hophop_last_bootstrap_error(): string
{
    return (string) ($GLOBALS['HOPHOP_PHPBB_BOOTSTRAP_ERROR'] ?? '');
}

function hophop_guess_phpbb_root(): ?string
{
    $candidates = [
        realpath(__DIR__ . '/../..'),
        realpath(__DIR__ . '/..'),
    ];

    foreach ($candidates as $root) {
        if ($root !== false && is_file($root . '/common.php')) {
            return rtrim(str_replace('\\', '/', $root), '/') . '/';
        }
    }

    return null;
}

/** Chemin absolu vers la racine phpBB (diagnostic). */
function hophop_phpbb_root(): ?string
{
    $cfg = hophop_local_config();
    $configured = $cfg['phpbb_root'] ?? null;

    if (is_string($configured) && $configured !== '') {
        $root = rtrim(str_replace('\\', '/', $configured), '/') . '/';

        return is_file($root . 'common.php') ? $root : null;
    }

    return hophop_guess_phpbb_root();
}

/** Racine du dossier hophop/ (parent de includes/). */
function hophop_app_root(): string
{
    return dirname(__DIR__);
}

function hophop_bootstrap_phpbb(): bool
{
    if (defined('HOPHOP_PHPBB_BOOTSTRAPPED')) {
        return true;
    }

    $GLOBALS['HOPHOP_PHPBB_BOOTSTRAP_ERROR'] = '';

    if (!defined('IN_PHPBB')) {
        define('IN_PHPBB', true);
    }

    global $phpbb_root_path, $phpEx;
    $phpEx = 'php';

    $cfg = hophop_local_config();
    $configured = $cfg['phpbb_root'] ?? null;
    $common_file = null;

    if (is_string($configured) && $configured !== '') {
        $abs = rtrim(str_replace('\\', '/', $configured), '/') . '/';
        if (!is_file($abs . 'common.php')) {
            $GLOBALS['HOPHOP_PHPBB_BOOTSTRAP_ERROR'] = 'common.php introuvable : ' . $abs;

            return false;
        }
        if (!@chdir($abs)) {
            $GLOBALS['HOPHOP_PHPBB_BOOTSTRAP_ERROR'] = 'Impossible de se placer dans phpbb_root : ' . $abs;

            return false;
        }
        $phpbb_root_path = './';
        $common_file = $abs . 'common.php';
    } else {
        // phpBB 3.3 : $phpbb_root_path relatif depuis hophop/ (api.php), pas un chemin absolu.
        $hophop = hophop_app_root();
        $common_file = realpath($hophop . '/../common.php');
        if ($common_file === false || !is_file($common_file)) {
            $GLOBALS['HOPHOP_PHPBB_BOOTSTRAP_ERROR'] = 'common.php introuvable à côté de hophop/ (attendu : phpBB334/hophop/).';

            return false;
        }
        $phpbb_root_path = '../';
    }

    try {
        require $common_file;
    } catch (\Throwable $e) {
        $GLOBALS['HOPHOP_PHPBB_BOOTSTRAP_ERROR'] = $e->getMessage();
        error_log('HopHop phpBB bootstrap: ' . $e->getMessage());

        return false;
    }

    if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    define('HOPHOP_PHPBB_BOOTSTRAPPED', true);

    return true;
}

/**
 * @return array{user_id: int, username: string}|null
 */
function hophop_forum_user(): ?array
{
    if (!hophop_bootstrap_phpbb()) {
        return null;
    }

    global $user;

    $user_id = (int) $user->data['user_id'];

    if ($user_id <= 0 || $user_id === ANONYMOUS) {
        return null;
    }

    $username = (string) ($user->data['username'] ?? '');

    if ($username === '') {
        return null;
    }

    return ['user_id' => $user_id, 'username' => $username];
}

function hophop_forum_login_url(): string
{
    if (!hophop_bootstrap_phpbb()) {
        return '';
    }

    try {
        return append_sid(generate_board_url(false) . '/ucp.php', 'mode=login', true);
    } catch (\Throwable $e) {
        return generate_board_url(false) . '/ucp.php?mode=login';
    }
}

function hophop_forum_logout_url(): string
{
    if (!hophop_bootstrap_phpbb()) {
        return '';
    }

    try {
        return append_sid(generate_board_url(false) . '/ucp.php', 'mode=logout', true);
    } catch (\Throwable $e) {
        return generate_board_url(false) . '/ucp.php?mode=logout';
    }
}
