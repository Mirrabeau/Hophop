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

    // null = ../ depuis hophop/ (phpBB334/hophop → phpBB334). Chemin absolu optionnel (ex. D:/…/phpBB334/).
    'phpbb_root' => null,

    // Préfixe des tables phpBB (identique à $table_prefix dans config.php du forum).
    'table_prefix' => 'phpbb_',

    // Optionnel : URL POST « Partager sur BrewRecipes » (sinon déduite via phpBB append_sid).
    // 'brewrecipes_hophop_preview' => '/forum/app.php/brewrecipes/hophop/preview',
];
