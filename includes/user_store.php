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
    $table = hophop_store_table();

    if (!hophop_store_table_valid($table)) {
        $GLOBALS['HOPHOP_LAST_STORE_ERROR'] = 'Nom de table invalide.';

        return false;
    }

    $mysqli = hophop_phpbb_mysqli();

    if ($mysqli instanceof \mysqli) {
        try {
            hophop_store_debug_log('save_mysql prepared uid=' . $user_id . ' len=' . strlen($json));

            $check = $mysqli->prepare('SELECT user_id FROM ' . $table . ' WHERE user_id = ?');

            if ($check === false) {
                throw new \RuntimeException('prepare SELECT : ' . $mysqli->error);
            }

            $check->bind_param('i', $user_id);
            $check->execute();
            $exists = (bool) $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {
                $stmt = $mysqli->prepare(
                    'UPDATE ' . $table . ' SET store_json = ?, updated = ? WHERE user_id = ?'
                );

                if ($stmt === false) {
                    throw new \RuntimeException('prepare UPDATE : ' . $mysqli->error);
                }

                $stmt->bind_param('sii', $json, $now, $user_id);
            } else {
                $stmt = $mysqli->prepare(
                    'INSERT INTO ' . $table . ' (user_id, store_json, updated) VALUES (?, ?, ?)'
                );

                if ($stmt === false) {
                    throw new \RuntimeException('prepare INSERT : ' . $mysqli->error);
                }

                $stmt->bind_param('isi', $user_id, $json, $now);
            }

            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new \RuntimeException('execute : ' . $err);
            }

            $stmt->close();
            hophop_store_debug_log('save_mysql OK uid=' . $user_id);

            return true;
        } catch (\Throwable $e) {
            $GLOBALS['HOPHOP_LAST_STORE_ERROR'] = $e->getMessage();
            error_log('HopHop store save (mysqli): ' . $e->getMessage());
            hophop_store_debug_log('save_mysql FAIL prepared ' . $e->getMessage());

            return false;
        }
    }

    hophop_store_debug_log('save_mysql fallback sql_build_array uid=' . $user_id);

    try {
        $sql = 'SELECT user_id
            FROM ' . $table . '
            WHERE user_id = ' . $user_id;
        $result = $db->sql_query($sql);
        $exists = (bool) $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        $row = [
            'store_json' => $json,
            'updated' => $now,
        ];

        if ($exists) {
            $sql = 'UPDATE ' . $table . '
                SET ' . $db->sql_build_array('UPDATE', $row) . '
                WHERE user_id = ' . $user_id;
        } else {
            $row['user_id'] = $user_id;
            $sql = 'INSERT INTO ' . $table . ' ' . $db->sql_build_array('INSERT', $row);
        }

        $db->sql_query($sql);
    } catch (\Throwable $e) {
        $GLOBALS['HOPHOP_LAST_STORE_ERROR'] = $e->getMessage();
        error_log('HopHop store save: ' . $e->getMessage());
        hophop_store_debug_log('save_mysql FAIL uid=' . $user_id . ' len=' . strlen($json) . ' ' . $e->getMessage());

        return false;
    }

    hophop_store_debug_log('save_mysql OK uid=' . $user_id . ' len=' . strlen($json));

    return true;
}

function hophop_store_debug_log(string $line): void
{
    if (!defined('DATA_DIR') || !is_dir(DATA_DIR)) {
        return;
    }

    @file_put_contents(
        DATA_DIR . '/hophop-api.log',
        gmdate('c') . ' ' . $line . "\n",
        FILE_APPEND | LOCK_EX
    );
}

/** Connexion MySQLi sous-jacente phpBB (requêtes préparées, gros JSON). */
function hophop_phpbb_mysqli(): ?\mysqli
{
    global $db;

    if (!isset($db) || !is_object($db)) {
        return null;
    }

    foreach (['db_connect_id', 'conn'] as $prop) {
        try {
            $ref = new \ReflectionObject($db);

            if (!$ref->hasProperty($prop)) {
                continue;
            }

            $property = $ref->getProperty($prop);
            $property->setAccessible(true);
            $link = $property->getValue($db);

            if ($link instanceof \mysqli) {
                return $link;
            }
        } catch (\ReflectionException $e) {
            continue;
        }
    }

    return null;
}

function hophop_store_table_valid(string $table): bool
{
    return (bool) preg_match('/^[A-Za-z0-9_]+$/', $table);
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
