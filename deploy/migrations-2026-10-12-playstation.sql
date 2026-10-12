-- Game Watcher — PlayStation Store a cena s PS Plus (R20)
--
-- Odpovídá migracím 2026_10_12_100000_create_store_listings_table
-- a 2026_10_12_100100_add_platform_and_member_price_to_current_prices_table.
--
-- store_listings — produkty PlayStation Store připojené ke hře odkazem;
-- current_prices dostane platformu, edici, cenu s PS Plus a příznak katalogu
-- PS Plus. Stávající ceny dostanou platformu pc (všechny jsou z ITAD a gg.deals).
--
-- Skript je OPAKOVATELNÝ. Pusť ho PO migrations-2026-10-11-ceny.sql a PŘED
-- nahráním kódu.
--
-- @author Roman Hlaváček
-- @created 2026-10-12

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `store_listings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `game_id` bigint(20) unsigned NOT NULL,
  `source` varchar(16) NOT NULL COMMENT 'Obchod (enum PriceSource: playstation)',
  `external_id` varchar(64) NOT NULL COMMENT 'ID produktu v obchodě',
  `name` varchar(160) NOT NULL COMMENT 'Název produktu v obchodě (edice)',
  `platforms` varchar(32) DEFAULT NULL COMMENT 'Platformy produktu (PS5, PS4)',
  `url` varchar(500) NOT NULL COMMENT 'Stránka produktu v obchodě',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `store_listings_game_id_source_external_id_unique` (`game_id`,`source`,`external_id`),
  CONSTRAINT `store_listings_game_id_foreign` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `current_prices`
  ADD COLUMN IF NOT EXISTS `platform` varchar(16) NOT NULL DEFAULT 'pc' COMMENT 'Platforma (enum Platform: pc, playstation)' AFTER `shop_name`,
  ADD COLUMN IF NOT EXISTS `edition` varchar(160) DEFAULT NULL COMMENT 'Název produktu v obchodě, když ho obchod uvádí (edice)' AFTER `platform`,
  ADD COLUMN IF NOT EXISTS `member_amount` decimal(10,2) DEFAULT NULL COMMENT 'Cena pro předplatitele (PS Plus) [currency]' AFTER `regular_amount`,
  ADD COLUMN IF NOT EXISTS `member_included` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Hra je v katalogu předplatného (PS Plus Extra) — pro předplatitele zdarma' AFTER `member_amount`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_12_100000_create_store_listings_table', 4
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_12_100000_create_store_listings_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_12_100100_add_platform_and_member_price_to_current_prices_table', 4
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_12_100100_add_platform_and_member_price_to_current_prices_table');

-- Kontrola: migrace jsou zapsané, tabulka a sloupce existují
SELECT `migration`, `batch` FROM `migrations` WHERE `batch` = 4;
SHOW TABLES LIKE 'store_listings';
SHOW COLUMNS FROM `current_prices` WHERE `Field` IN ('platform', 'edition', 'member_amount', 'member_included');
