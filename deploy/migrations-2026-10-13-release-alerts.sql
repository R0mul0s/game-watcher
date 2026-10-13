-- Game Watcher — upozornění na vydání a první slevu sledované hry (R41)
--
-- Odpovídá migraci 2026_10_13_110000_add_release_alerts_to_watchlist_items.
--
-- watchlist_items dostane čas upozornění „právě vyšlo“ a „první sleva od
-- vydání“. Hry, které už vyšly, se označí jako upozorněné — upozornění se
-- týkají jen her sledovaných před vydáním.
--
-- Skript je OPAKOVATELNÝ (označení vydaných her mění jen prázdné hodnoty).
-- Pusť ho PO migrations-2026-10-13-plus-and-digest.sql a PŘED nahráním kódu.
--
-- @author Roman Hlaváček
-- @created 2026-10-13

SET NAMES utf8mb4;

ALTER TABLE `watchlist_items`
  ADD COLUMN IF NOT EXISTS `release_notified_at` timestamp NULL DEFAULT NULL COMMENT 'Kdy odešlo upozornění na vydání; NULL = hra ještě nevyšla nebo se čeká' AFTER `notified_at`,
  ADD COLUMN IF NOT EXISTS `first_sale_notified_at` timestamp NULL DEFAULT NULL COMMENT 'Kdy odešlo upozornění na první slevu od vydání; NULL = čeká se' AFTER `release_notified_at`;

UPDATE `watchlist_items`
SET `release_notified_at` = UTC_TIMESTAMP(), `first_sale_notified_at` = UTC_TIMESTAMP()
WHERE `release_notified_at` IS NULL
  AND `game_id` IN (SELECT `id` FROM `games` WHERE `release_date` <= UTC_DATE());

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_13_110000_add_release_alerts_to_watchlist_items', 11
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_13_110000_add_release_alerts_to_watchlist_items');

-- Kontrola: migrace je zapsaná, sloupce existují a kolik položek čeká na vydání
SELECT `migration`, `batch` FROM `migrations` WHERE `batch` = 11;
SHOW COLUMNS FROM `watchlist_items` WHERE `Field` IN ('release_notified_at', 'first_sale_notified_at');
SELECT COUNT(*) AS `waiting_for_release` FROM `watchlist_items` WHERE `release_notified_at` IS NULL;
