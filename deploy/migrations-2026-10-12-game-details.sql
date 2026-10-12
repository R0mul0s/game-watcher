-- Game Watcher — žánry a režimy hry, počet hráčů v PS Store (R25)
--
-- Odpovídá migraci 2026_10_12_100300_add_genres_and_modes_to_games_table.
--
-- games dostane žánry a režimy ze Steamu a čas jejich stažení (prázdné —
-- doplní je cron); store_listings počet hráčů z PS Store (doplní cron s cenami).
--
-- Skript je OPAKOVATELNÝ. Pusť ho PO migrations-2026-10-12-watch-platform.sql
-- a PŘED nahráním kódu.
--
-- @author Roman Hlaváček
-- @created 2026-10-12

SET NAMES utf8mb4;

ALTER TABLE `games`
  ADD COLUMN IF NOT EXISTS `genres` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Žánry ze Steamu (česky)' CHECK (json_valid(`genres`)) AFTER `tags`,
  ADD COLUMN IF NOT EXISTS `modes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Režimy ze Steamu (enum GameMode: single, multi, coop, online_coop, local_coop, local_multi)' CHECK (json_valid(`modes`)) AFTER `genres`,
  ADD COLUMN IF NOT EXISTS `steam_details_at` timestamp NULL DEFAULT NULL COMMENT 'Kdy se stáhly žánry a režimy ze Steamu' AFTER `info_refreshed_at`;

ALTER TABLE `store_listings`
  ADD COLUMN IF NOT EXISTS `local_players` tinyint(3) unsigned DEFAULT NULL COMMENT 'Nejvyšší počet hráčů u jedné konzole (PS Store)' AFTER `platforms`,
  ADD COLUMN IF NOT EXISTS `online_players` tinyint(3) unsigned DEFAULT NULL COMMENT 'Nejvyšší počet hráčů online (PS Store)' AFTER `local_players`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_12_100300_add_genres_and_modes_to_games_table', 6
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_12_100300_add_genres_and_modes_to_games_table');

-- Kontrola: migrace je zapsaná a sloupce existují
SELECT `migration`, `batch` FROM `migrations` WHERE `batch` = 6;
SHOW COLUMNS FROM `games` WHERE `Field` IN ('genres', 'modes', 'steam_details_at');
SHOW COLUMNS FROM `store_listings` WHERE `Field` IN ('local_players', 'online_players');
