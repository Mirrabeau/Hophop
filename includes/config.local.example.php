<?php
/**
 * Copiez ce fichier en includes/config.local.php (non versionné) sur le serveur forum.
 *
 * @return array<string, mixed>
 */
return [
    // standalone = comptes HopHop locaux (auto-hébergé) | phpbb = session forum
    'auth_mode' => 'phpbb',

    // file = JSON dans data/ | mysql = table {prefix}hophop_store (nécessite bootstrap phpBB)
    'storage' => 'mysql',

    // null = détection auto (forum/hophop → racine forum). Sinon chemin absolu vers le dossier forum.
    'phpbb_root' => null,
];
