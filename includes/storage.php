<?php
/** Lecture/écriture JSON atomiques et verrouillées (accès concurrents multiples). */

function read_json(string $path, $default = null) {
    if (!file_exists($path)) return $default;
    $fp = fopen($path, 'r');
    if (!$fp) return $default;
    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($content, true);
    return $data === null && trim($content) !== 'null' ? $default : $data;
}

function write_json(string $path, $data): bool {
    $tmp = $path . '.tmp.' . getmypid() . '.' . mt_rand();
    $fp = fopen($tmp, 'c');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    $ok = fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    if ($ok) { $ok = rename($tmp, $path); } else { @unlink($tmp); }
    return $ok;
}
