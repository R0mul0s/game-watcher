-- Game Watcher — katalog her a seznam sledovaných (etapa 2, R10, R11)
--
-- Odpovídá migracím 2026_10_11_100000_create_games_table
-- a 2026_10_11_100100_create_watchlist_items_table.
--
-- games — katalog her převzatý z IsThereAnyDeal (identita = itad_id);
-- watchlist_items — osobní seznam sledovaných her účtu. Obě začínají prázdné,
-- plní je aplikace při přidání hry.
--
-- Skript je OPAKOVATELNÝ. Pusť ho PO migrations-2026-10-11-init.sql (potřebuje
-- tabulku users) a PŘED nahráním kódu etapy 2.
--
-- @author Roman Hlaváček
-- @created 2026-10-11

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `games` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `itad_id` char(36) NOT NULL COMMENT 'ID hry v IsThereAnyDeal (UUID) — identita hry',
  `slug` varchar(255) NOT NULL COMMENT 'Slug z ITAD — adresa detailu hry',
  `title` varchar(255) NOT NULL COMMENT 'Název hry',
  `type` varchar(16) NOT NULL COMMENT 'Druh z ITAD: game, dlc…',
  `is_mature` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Obsah pro dospělé podle ITAD',
  `steam_app_id` int(10) unsigned DEFAULT NULL COMMENT 'Steam App ID — prázdné u her mimo Steam',
  `release_date` date DEFAULT NULL COMMENT 'Datum vydání',
  `developers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Vývojáři — seznam názvů' CHECK (json_valid(`developers`)),
  `publishers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Vydavatelé — seznam názvů' CHECK (json_valid(`publishers`)),
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Štítky ze Steamu přes ITAD' CHECK (json_valid(`tags`)),
  `reviews` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Hodnocení: zdroj, skóre 0–100, počet, odkaz' CHECK (json_valid(`reviews`)),
  `boxart_url` varchar(500) DEFAULT NULL COMMENT 'Obal hry (CDN ITAD)',
  `banner_url` varchar(500) DEFAULT NULL COMMENT 'Široký banner (CDN ITAD)',
  `itad_url` varchar(500) DEFAULT NULL COMMENT 'Stránka hry na isthereanydeal.com',
  `info_refreshed_at` datetime NOT NULL COMMENT 'Kdy se údaje naposledy převzaly z ITAD (UTC)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `games_itad_id_unique` (`itad_id`),
  UNIQUE KEY `games_slug_unique` (`slug`),
  KEY `games_steam_app_id_index` (`steam_app_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `watchlist_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `game_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `watchlist_items_user_id_game_id_unique` (`user_id`,`game_id`),
  KEY `watchlist_items_game_id_foreign` (`game_id`),
  CONSTRAINT `watchlist_items_game_id_foreign` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  CONSTRAINT `watchlist_items_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_11_100000_create_games_table', 2
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_11_100000_create_games_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_11_100100_create_watchlist_items_table', 2
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_11_100100_create_watchlist_items_table');

-- Kontrola: migrace jsou zapsané, tabulky existují
SELECT `migration`, `batch` FROM `migrations` WHERE `batch` = 2;
SHOW TABLES LIKE '%game%';
SHOW TABLES LIKE 'watchlist%';
