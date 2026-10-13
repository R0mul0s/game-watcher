-- Game Watcher — čeština ve hře ze Steamu a z PS Store (R47)
--
-- Odpovídá migraci 2026_10_13_140000_add_czech_to_games_and_store_listings.
--
-- games a store_listings dostanou sloupec czech (none / text / voice). Údaje
-- ze Steamu se označí jako nestažené — cron je po dávkách stáhne znovu
-- i s jazyky; produkty PS Store doplní nejbližší obnova cen.
--
-- Skript je OPAKOVATELNÝ (vynulování steam_details_at jen při prvním běhu).
-- Pusť ho PO migrations-2026-10-13-upcoming-releases.sql a PŘED nahráním kódu.
--
-- @author Roman Hlaváček
-- @created 2026-10-13

SET NAMES utf8mb4;

-- Vynulovat jen poprvé — dokud migrace není zapsaná
UPDATE `games` SET `steam_details_at` = NULL
WHERE `steam_details_at` IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_13_140000_add_czech_to_games_and_store_listings');

ALTER TABLE `games`
  ADD COLUMN IF NOT EXISTS `czech` varchar(8) DEFAULT NULL COMMENT 'Čeština podle Steamu: none / text (rozhraní a titulky) / voice (dabing); NULL = neznámá' AFTER `modes`;

ALTER TABLE `store_listings`
  ADD COLUMN IF NOT EXISTS `czech` varchar(8) DEFAULT NULL COMMENT 'Čeština podle PS Store: none / text (rozhraní a titulky) / voice (dabing); NULL = neznámá' AFTER `star_ratings_count`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_13_140000_add_czech_to_games_and_store_listings', 14
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_13_140000_add_czech_to_games_and_store_listings');

-- Kontrola: migrace je zapsaná a sloupce existují
SELECT `migration`, `batch` FROM `migrations` WHERE `batch` = 14;
SHOW COLUMNS FROM `games` WHERE `Field` = 'czech';
SHOW COLUMNS FROM `store_listings` WHERE `Field` = 'czech';
