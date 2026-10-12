-- Game Watcher — ceny her, kurzy ČNB a měna zobrazení (etapa 3, R9, R14, R18)
--
-- Odpovídá migracím 2026_10_11_110000_create_price_tables,
-- 2026_10_11_110100_create_exchange_rates_table
-- a 2026_10_11_110200_add_display_currency_to_users_table.
--
-- current_prices — poslední cena hry v obchodech; price_lows — historická
-- minima; price_history — nejnižší cena dne po obchodech; exchange_rates —
-- denní kurzy ČNB; users.display_currency — přepínač CZK / EUR. Tabulky
-- začínají prázdné, naplní je první volání cron URL (/cron/ceny).
--
-- Skript je OPAKOVATELNÝ. Pusť ho PO migrations-2026-10-11-katalog.sql
-- a PŘED nahráním kódu etapy 3.
--
-- @author Roman Hlaváček
-- @created 2026-10-11

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `current_prices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `game_id` bigint(20) unsigned NOT NULL,
  `source` varchar(16) NOT NULL COMMENT 'Zdroj ceny (enum PriceSource: itad, ggdeals)',
  `shop_key` varchar(32) NOT NULL COMMENT 'Obchod: itad-<id obchodu v ITAD>, keyshops = nejnižší z obchodů s klíči',
  `shop_name` varchar(64) NOT NULL COMMENT 'Název obchodu ze zdroje',
  `amount` decimal(10,2) NOT NULL COMMENT 'Aktuální cena [měna ve sloupci currency]',
  `regular_amount` decimal(10,2) DEFAULT NULL COMMENT 'Běžná cena bez slevy [currency]',
  `currency` char(3) NOT NULL COMMENT 'Měna ceny (ISO 4217)',
  `cut` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Sleva [%]',
  `drm` varchar(64) DEFAULT NULL COMMENT 'Kde se hra aktivuje (Steam, Epic, DRM Free…)',
  `url` varchar(500) DEFAULT NULL COMMENT 'Odkaz do obchodu (partnerský odkaz ITAD se nesmí měnit)',
  `expires_at` datetime DEFAULT NULL COMMENT 'Konec slevy (UTC), když ho obchod uvádí',
  `fetched_at` datetime NOT NULL COMMENT 'Kdy se cena stáhla (UTC)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `current_prices_game_id_shop_key_unique` (`game_id`,`shop_key`),
  CONSTRAINT `current_prices_game_id_foreign` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `price_lows` (
  `game_id` bigint(20) unsigned NOT NULL,
  `scope` varchar(16) NOT NULL COMMENT 'official = oficiální obchody (ITAD), keyshops = obchody s klíči (gg.deals)',
  `amount` decimal(10,2) NOT NULL COMMENT 'Historicky nejnižší cena [currency]',
  `currency` char(3) NOT NULL COMMENT 'Měna (ISO 4217)',
  `fetched_at` datetime NOT NULL COMMENT 'Kdy se minimum stáhlo (UTC)',
  PRIMARY KEY (`game_id`,`scope`),
  CONSTRAINT `price_lows_game_id_foreign` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `price_history` (
  `game_id` bigint(20) unsigned NOT NULL,
  `shop_key` varchar(32) NOT NULL COMMENT 'Obchod jako v current_prices',
  `day` date NOT NULL COMMENT 'Místní den (Europe/Prague)',
  `amount` decimal(10,2) NOT NULL COMMENT 'Nejnižší cena dne [currency]',
  `currency` char(3) NOT NULL COMMENT 'Měna (ISO 4217)',
  PRIMARY KEY (`game_id`,`shop_key`,`day`),
  CONSTRAINT `price_history_game_id_foreign` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `exchange_rates` (
  `day` date NOT NULL COMMENT 'Den vyhlášení kurzu ČNB',
  `currency` char(3) NOT NULL COMMENT 'Kód měny (ISO 4217)',
  `rate` decimal(14,6) NOT NULL COMMENT 'Kurz [CZK za 1 jednotku měny]',
  PRIMARY KEY (`day`,`currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `display_currency` char(3) NOT NULL DEFAULT 'CZK' COMMENT 'Měna zobrazení cen (CZK, EUR)' AFTER `password`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_11_110000_create_price_tables', 3
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_11_110000_create_price_tables');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_11_110100_create_exchange_rates_table', 3
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_11_110100_create_exchange_rates_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_11_110200_add_display_currency_to_users_table', 3
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_11_110200_add_display_currency_to_users_table');

-- Kontrola: migrace jsou zapsané, tabulky a sloupec existují
SELECT `migration`, `batch` FROM `migrations` WHERE `batch` = 3;
SHOW TABLES LIKE '%price%';
SHOW TABLES LIKE 'exchange_rates';
SHOW COLUMNS FROM `users` LIKE 'display_currency';
