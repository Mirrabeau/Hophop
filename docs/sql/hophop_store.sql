-- HopHop — stockage JSON par membre forum (préfixe phpBB à adapter).
-- Exemple : phpbb_hophop_store si $table_prefix = 'phpbb_'

CREATE TABLE IF NOT EXISTS phpbb_hophop_store (
    user_id INT UNSIGNED NOT NULL,
    store_json MEDIUMTEXT NOT NULL,
    updated INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
