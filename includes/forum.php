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

/** Préfixe tables phpBB (config.local), ex. phpbb_ — aligné sur config.php du forum. */
function hophop_phpbb_table_prefix_override(): ?string
{
    if (!function_exists('hophop_local_config')) {
        return null;
    }

    $prefix = hophop_local_config()['table_prefix'] ?? null;

    return is_string($prefix) && $prefix !== '' ? $prefix : null;
}

function hophop_patch_config_php_file(\phpbb\config_php_file $config): void
{
    $prefix = hophop_phpbb_table_prefix_override();

    if ($prefix === null) {
        return;
    }

    $config->get_all();
    $ref = new \ReflectionClass($config);
    $prop = $ref->getProperty('config_data');
    $prop->setAccessible(true);
    $data = $prop->getValue($config);
    $data['table_prefix'] = $prefix;
    $prop->setValue($config, $data);
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

    $forum_root = hophop_phpbb_root();

    if ($forum_root === null) {
        $GLOBALS['HOPHOP_PHPBB_BOOTSTRAP_ERROR'] = 'common.php introuvable (hophop dans phpBB334/hophop/, ou phpbb_root en config.local).';

        return false;
    }

    // Sous Apache/IIS le cwd n’est pas hophop/ : ../ serait interprété depuis un mauvais dossier → DI cache cassé.
    $forum_dir = rtrim(str_replace('\\', '/', $forum_root), '/');

    if (!@chdir($forum_dir)) {
        $GLOBALS['HOPHOP_PHPBB_BOOTSTRAP_ERROR'] = 'Impossible de chdir vers la racine phpBB : ' . $forum_dir;

        return false;
    }

    $phpbb_root_path = './';

    $with_session = !defined('HOPHOP_DIAGNOSTIC');

    try {
        hophop_bootstrap_phpbb_web_stack($with_session);
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
 * Bootstrap phpBB sans common.php (évite cache.driver + $phpbb_container global null).
 * Puis session forum comme index.php.
 */
function hophop_bootstrap_phpbb_web_stack(bool $start_session = true): void
{
    global $phpbb_root_path, $phpEx, $phpbb_container, $user, $auth, $table_prefix;

    require $phpbb_root_path . 'includes/startup.' . $phpEx;
    require $phpbb_root_path . 'phpbb/class_loader.' . $phpEx;

    $phpbb_class_loader = new \phpbb\class_loader('phpbb\\', $phpbb_root_path . 'phpbb/', $phpEx);
    $phpbb_class_loader->register();

    $phpbb_config_php_file = new \phpbb\config_php_file($phpbb_root_path, $phpEx);
    hophop_patch_config_php_file($phpbb_config_php_file);
    extract($phpbb_config_php_file->get_all());

    $prefix_override = hophop_phpbb_table_prefix_override();

    if ($prefix_override !== null) {
        $table_prefix = $prefix_override;
    } elseif (empty($table_prefix) && isset($dbprefix) && $dbprefix !== '') {
        $table_prefix = $dbprefix;
    }

    if (!defined('PHPBB_ENVIRONMENT')) {
        @define('PHPBB_ENVIRONMENT', 'production');
    }

    if (!defined('PHPBB_INSTALLED')) {
        throw new \RuntimeException('phpBB n’est pas installé (PHPBB_INSTALLED absent).');
    }

    require $phpbb_root_path . 'includes/constants.' . $phpEx;
    require $phpbb_root_path . 'includes/functions.' . $phpEx;
    require $phpbb_root_path . 'includes/functions_content.' . $phpEx;
    require $phpbb_root_path . 'includes/functions_compatibility.' . $phpEx;
    require $phpbb_root_path . 'includes/utf/utf_tools.' . $phpEx;

    set_error_handler(defined('PHPBB_MSG_HANDLER') ? PHPBB_MSG_HANDLER : 'msg_handler');

    $phpbb_class_loader_ext = new \phpbb\class_loader('\\', $phpbb_root_path . 'ext/', $phpEx);
    $phpbb_class_loader_ext->register();

    $phpbb_container_builder = new \phpbb\di\container_builder($phpbb_root_path, $phpEx);
    $phpbb_container = $phpbb_container_builder->with_config($phpbb_config_php_file)->get_container();

    $phpbb_container->get('request')->enable_super_globals();
    require $phpbb_root_path . 'includes/compatibility_globals.' . $phpEx;
    register_compatibility_globals();

    if ($prefix_override !== null) {
        $table_prefix = $prefix_override;
    }

    if ($start_session) {
        $user->session_begin();
        $auth->acl($user->data);
        $user->setup();
    }
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
