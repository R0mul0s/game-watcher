-- Game Watcher — Game Pass a bundly z IsThereAnyDeal (R50, R51)
--
-- Odpovídá migraci 2026_10_13_160000_add_game_pass_and_bundles.
--
-- games: in_game_pass, game_pass_leaves_at (doplní cron); users: has_game_pass
-- (vypnuto, zapíná se v nastavení); watchlist_items: game_pass_notified_at,
-- notified_bundles; nová tabulka game_bundles — prázdná, naplní ji cron.
--
-- Skript je OPAKOVATELNÝ. Pusť ho PO migrations-2026-10-13-plus-leaving.sql
-- a PŘED nahráním kódu.
--
-- @author Roman Hlaváček
-- @created 2026-10-13

SET NAMES utf8mb4;

ALTER TABLE `games`
  ADD COLUMN IF NOT EXISTS `in_game_pass` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Hra je v Game Passu podle ITAD (R50)' AFTER `czech`,
  ADD COLUMN IF NOT EXISTS `game_pass_leaves_at` datetime DEFAULT NULL COMMENT 'Kdy hra z Game Passu odchází (UTC); NULL = neuvedeno' AFTER `in_game_pass`;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `has_game_pass` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Účet má Game Pass — upozornění na hry v něm (R50)' AFTER `has_ps_plus`;

ALTER TABLE `watchlist_items`
  ADD COLUMN IF NOT EXISTS `game_pass_notified_at` timestamp NULL DEFAULT NULL COMMENT 'Kdy odešlo upozornění „je v Game Passu“; NULL = ještě ne nebo hra z Game Passu odešla' AFTER `first_sale_notified_at`,
  ADD COLUMN IF NOT EXISTS `notified_bundles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'ID bundlů ITAD, na které už upozornění odešlo (R51)' CHECK (json_valid(`notified_bundles`)) AFTER `game_pass_notified_at`;

CREATE TABLE IF NOT EXISTS `game_bundles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `game_id` bigint(20) unsigned NOT NULL,
  `bundle_id` int(10) unsigned NOT NULL COMMENT 'ID bundlu v ITAD',
  `title` varchar(255) NOT NULL,
  `shop` varchar(64) NOT NULL COMMENT 'Obchod s bundlem (Humble Bundle, Fanatical…)',
  `url` varchar(500) NOT NULL COMMENT 'Partnerský odkaz ITAD — neupravovat',
  `amount` decimal(10,2) DEFAULT NULL COMMENT 'Cena nejlevnější úrovně s hrou; NULL = „Build your own“ bez ceny',
  `currency` varchar(3) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL COMMENT 'Konec bundlu (UTC); NULL = neuveden',
  PRIMARY KEY (`id`),
  UNIQUE KEY `game_bundles_game_id_bundle_id_unique` (`game_id`,`bundle_id`),
  CONSTRAINT `game_bundles_game_id_foreign` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Aktivní bundly s hrami katalogu z ITAD (R51) — celou ji přepisuje cron';

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_13_160000_add_game_pass_and_bundles', 16
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_13_160000_add_game_pass_and_bundles');

-- Kontrola: migrace je zapsaná, sloupce a tabulka existují
SELECT `migration`, `batch` FROM `migrations` WHERE `batch` = 16;
SHOW COLUMNS FROM `games` WHERE `Field` LIKE '%game\_pass%';
SHOW COLUMNS FROM `watchlist_items` WHERE `Field` IN ('game_pass_notified_at', 'notified_bundles');
SHOW TABLES LIKE 'game_bundles';
