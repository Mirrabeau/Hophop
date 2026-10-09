<?php
/**
 * Diagnostic HopHop — ouvrir …/hophop/health.php puis retirer ou protéger ce fichier.
 */
define('HOPHOP_DIAGNOSTIC', true);

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

$report = [
    'php' => PHP_VERSION,
    'hophop_dir' => __DIR__,
    'config_local' => is_file(__DIR__ . '/includes/config.local.php'),
];

try {
    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/user_store.php';

    $report['auth_mode'] = hophop_auth_mode();
    $report['storage'] = hophop_storage();
    $report['phpbb_root_guess'] = hophop_phpbb_root();

    if (hophop_uses_phpbb()) {
        $boot = hophop_bootstrap_phpbb();
        $report['phpbb_bootstrap'] = $boot;

        if (!$boot) {
            $report['bootstrap_error'] = hophop_last_bootstrap_error();
        } else {
            global $table_prefix;
            $report['session_started'] = false;
            $report['table_prefix'] = $table_prefix ?? '(missing)';
            $report['hophop_table'] = ($table_prefix ?? '') . 'hophop_store';
            $report['mysql_store_ready'] = hophop_storage() === 'mysql' ? hophop_mysql_store_ready() : null;
            $report['forum_user'] = '(session non démarrée en mode diagnostic — tester api.php connecté au forum)';
        }
    }
} catch (\Throwable $e) {
    $report['exception'] = $e->getMessage();
    $report['file'] = $e->getFile() . ':' . $e->getLine();
    http_response_code(500);
}

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
