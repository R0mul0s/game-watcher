-- Game Watcher — Metascore z Metacritic a hodnocení hráčů z PS Store (R33)
--
-- Odpovídá migraci 2026_10_12_100600_add_ratings_to_games_and_store_listings.
--
-- games dostane stránku hry na Metacritic a její Metascore (prázdné — doplní je
-- cron hrám, kterým Metascore nedává ITAD), store_listings průměr hodnocení
-- hráčů na PS Store a počet hodnocení (prázdné — doplní je další stažení cen).
--
-- Skript je OPAKOVATELNÝ. Pusť ho PO migrations-2026-10-12-source-statuses.sql
-- a PŘED nahráním kódu.
--
-- @author Roman Hlaváček
-- @created 2026-10-12

SET NAMES utf8mb4;

ALTER TABLE `games`
  ADD COLUMN IF NOT EXISTS `metacritic_slug` varchar(120) DEFAULT NULL COMMENT 'Stránka hry na Metacritic (metacritic.com/game/<slug>/); NULL = nenalezena' AFTER `reviews`,
  ADD COLUMN IF NOT EXISTS `metacritic_score` tinyint(3) unsigned DEFAULT NULL COMMENT 'Metascore z Metacritic [0–100]; NULL = bez hodnocení kritiků' AFTER `metacritic_slug`,
  ADD COLUMN IF NOT EXISTS `metacritic_reviews` int(10) unsigned DEFAULT NULL COMMENT 'Počet recenzí kritiků za Metascore' AFTER `metacritic_score`,
  ADD COLUMN IF NOT EXISTS `metacritic_checked_at` timestamp NULL DEFAULT NULL COMMENT 'Kdy se Metacritic naposledy zkoušel (UTC); NULL = ještě ne' AFTER `metacritic_reviews`;

ALTER TABLE `store_listings`
  ADD COLUMN IF NOT EXISTS `star_rating` decimal(3,2) DEFAULT NULL COMMENT 'Průměr hodnocení hráčů na PS Store [hvězdičky 0–5]' AFTER `online_players`,
  ADD COLUMN IF NOT EXISTS `star_ratings_count` int(10) unsigned DEFAULT NULL COMMENT 'Počet hodnocení hráčů na PS Store' AFTER `star_rating`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_12_100600_add_ratings_to_games_and_store_listings', 9
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_12_100600_add_ratings_to_games_and_store_listings');

-- Kontrola: migrace je zapsaná a sloupce existují
SELECT `migration`, `batch` FROM `migrations` WHERE `batch` = 9;
SHOW COLUMNS FROM `games` WHERE `Field` LIKE 'metacritic%';
SHOW COLUMNS FROM `store_listings` WHERE `Field` LIKE 'star%';
