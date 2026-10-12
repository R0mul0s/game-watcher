-- Game Watcher — platforma, na které uživatel hru hlídá (R24)
--
-- Odpovídá migraci 2026_10_12_100200_add_platform_to_watchlist_items_table.
--
-- watchlist_items dostane sloupec platform: NULL = všude (výchozí, i pro
-- všechny dosud sledované hry), pc / playstation = jen ceny té platformy.
--
-- Skript je OPAKOVATELNÝ. Pusť ho PO migrations-2026-10-12-playstation.sql
-- a PŘED nahráním kódu.
--
-- @author Roman Hlaváček
-- @created 2026-10-12

SET NAMES utf8mb4;

ALTER TABLE `watchlist_items`
  ADD COLUMN IF NOT EXISTS `platform` varchar(16) DEFAULT NULL COMMENT 'Hlídaná platforma (enum Platform: pc, playstation); NULL = všude' AFTER `game_id`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_12_100200_add_platform_to_watchlist_items_table', 5
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_12_100200_add_platform_to_watchlist_items_table');

-- Kontrola: migrace je zapsaná a sloupec existuje
SELECT `migration`, `batch` FROM `migrations` WHERE `batch` = 5;
SHOW COLUMNS FROM `watchlist_items` WHERE `Field` = 'platform';
