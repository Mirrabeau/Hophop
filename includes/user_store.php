<?php

require_once __DIR__ . '/storage.php';

function hophop_store_table(): string
{
    global $table_prefix;

    return $table_prefix . 'hophop_store';
}

function hophop_last_store_error(): string
{
    return (string) ($GLOBALS['HOPHOP_LAST_STORE_ERROR'] ?? '');
}

function hophop_mysql_store_ready(): bool
{
    if (!hophop_bootstrap_phpbb()) {
        return false;
    }

    global $db;

    if (!isset($db) || !is_object($db)) {
        return false;
    }

    try {
        $table = hophop_store_table();
        $sql = 'SHOW TABLES LIKE \'' . $db->sql_escape($table) . '\'';
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        return (bool) $row;
    } catch (\Throwable $e) {
        error_log('HopHop mysql_store_ready: ' . $e->getMessage());

        return false;
    }
}

/**
 * @param array{user_id: int, username: string} $ctx
 */
function hophop_user_store_load(array $ctx): ?array
{
    if (hophop_storage() === 'mysql') {
        return hophop_user_store_load_mysql((int) $ctx['user_id'], (string) $ctx['username']);
    }

    return read_json(user_file((string) $ctx['username']), null);
}

/**
 * @param array{user_id: int, username: string} $ctx
 */
function hophop_user_store_save(array $ctx, array $data): bool
{
    if (hophop_storage() === 'mysql') {
        return hophop_user_store_save_mysql((int) $ctx['user_id'], $data);
    }

    return write_json(user_file((string) $ctx['username']), $data);
}

/**
 * @param array{user_id: int, username: string} $ctx
 */
function hophop_user_store_delete(array $ctx): void
{
    if (hophop_storage() === 'mysql') {
        hophop_user_store_delete_mysql((int) $ctx['user_id']);

        return;
    }

    @unlink(user_file((string) $ctx['username']));
}

function hophop_user_store_load_mysql(int $user_id, string $username): ?array
{
    if (!hophop_mysql_store_ready()) {
        return null;
    }

    global $db;

    $user_id = (int) $user_id;
    $sql = 'SELECT store_json
        FROM ' . hophop_store_table() . '
        WHERE user_id = ' . $user_id;
    $result = $db->sql_query($sql);
    $row = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);

    if ($row) {
        $decoded = json_decode((string) $row['store_json'], true);

        return is_array($decoded) ? $decoded : null;
    }

    $legacy = read_json(user_file($username), null);

    if ($legacy !== null && hophop_user_store_save_mysql($user_id, $legacy)) {
        return $legacy;
    }

    return null;
}

function hophop_user_store_save_mysql(int $user_id, array $data): bool
{
    $GLOBALS['HOPHOP_LAST_STORE_ERROR'] = '';

    if (!hophop_mysql_store_ready()) {
        $GLOBALS['HOPHOP_LAST_STORE_ERROR'] = 'table MySQL absente ou phpBB non chargé.';

        return false;
    }

    global $db;

    $user_id = (int) $user_id;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    if ($json === false) {
        $GLOBALS['HOPHOP_LAST_STORE_ERROR'] = 'json_encode : ' . json_last_error_msg();

        return false;
    }

    $max_mediumtext = 16 * 1024 * 1024;

    if (strlen($json) > $max_mediumtext) {
        $GLOBALS['HOPHOP_LAST_STORE_ERROR'] = 'Données trop volumineuses pour store_json (MEDIUMTEXT).';

        return false;
    }

    $now = time();

    try {
        $insert = [
            'user_id' => $user_id,
            'store_json' => $json,
            'updated' => $now,
        ];
        $update = [
            'store_json' => $json,
            'updated' => $now,
        ];
        $sql = 'INSERT INTO ' . hophop_store_table() . ' ' . $db->sql_build_array('INSERT', $insert)
            . ' ON DUPLICATE KEY UPDATE ' . $db->sql_build_array('UPDATE', $update);
        $db->sql_query($sql);
    } catch (\Throwable $e) {
        $GLOBALS['HOPHOP_LAST_STORE_ERROR'] = $e->getMessage();
        error_log('HopHop store save: ' . $e->getMessage());

        return false;
    }

    return true;
}

function hophop_user_store_delete_mysql(int $user_id): void
{
    if (!hophop_mysql_store_ready()) {
        return;
    }

    global $db;

    $db->sql_query(
        'DELETE FROM ' . hophop_store_table() . ' WHERE user_id = ' . (int) $user_id
    );
}
