<?php

/**
 * Bootstrap phpBB 3.3 pour HopHop (même site, ex. /forum/hophop/).
 */

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

function hophop_bootstrap_phpbb(): bool
{
    if (defined('HOPHOP_PHPBB_BOOTSTRAPPED')) {
        return true;
    }

    $root = hophop_phpbb_root();

    if ($root === null) {
        return false;
    }

    if (!defined('IN_PHPBB')) {
        define('IN_PHPBB', true);
    }

    global $phpbb_root_path, $phpEx;
    $phpbb_root_path = $root;
    $phpEx = 'php';

    chdir($root);
    require $root . 'common.php';
    chdir(__DIR__ . '/..');

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

    return append_sid(generate_board_url(false) . '/ucp.php', 'mode=login', true);
}

function hophop_forum_logout_url(): string
{
    if (!hophop_bootstrap_phpbb()) {
        return '';
    }

    return append_sid(generate_board_url(false) . '/ucp.php', 'mode=logout', true);
}
