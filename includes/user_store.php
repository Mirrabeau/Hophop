<?php

require_once __DIR__ . '/storage.php';

function hophop_store_table(): string
{
    global $table_prefix;

    return $table_prefix . 'hophop_store';
}

function hophop_mysql_store_ready(): bool
{
    if (!hophop_bootstrap_phpbb()) {
        return false;
    }

    global $db;

    $table = hophop_store_table();
    $sql = 'SHOW TABLES LIKE \'' . $db->sql_escape($table) . '\'';
    $result = $db->sql_query($sql);
    $row = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);

    return (bool) $row;
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
    if (!hophop_mysql_store_ready()) {
        return false;
    }

    global $db;

    $user_id = (int) $user_id;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        return false;
    }

    $now = time();
    $escaped = $db->sql_escape($json);

    $sql = 'INSERT INTO ' . hophop_store_table() . ' (user_id, store_json, updated)
        VALUES (' . $user_id . ", '" . $escaped . "', " . $now . ')
        ON DUPLICATE KEY UPDATE
            store_json = VALUES(store_json),
            updated = VALUES(updated)';

    $db->sql_query($sql);

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
