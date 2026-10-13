-- Game Watcher — nadcházející hry z IGDB pro záložku Objevit (R46)
--
-- Odpovídá migraci 2026_10_13_130000_create_upcoming_releases_table.
--
-- Nová tabulka upcoming_releases — prázdná; naplní ji první běh cronu, až bude
-- v .env GAMES_IGDB_CLIENT_ID a GAMES_IGDB_CLIENT_SECRET. Do té doby záložka
-- Nadcházející ukazuje „seznam se zatím nestáhl“.
--
-- Skript je OPAKOVATELNÝ. Pusť ho PO migrations-2026-10-13-playtime.sql
-- a PŘED nahráním kódu.
--
-- @author Roman Hlaváček
-- @created 2026-10-13

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `upcoming_releases` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `igdb_id` bigint(20) unsigned NOT NULL COMMENT 'ID hry v IGDB',
  `title` varchar(255) NOT NULL COMMENT 'Název hry podle IGDB',
  `igdb_url` varchar(512) DEFAULT NULL COMMENT 'Stránka hry na igdb.com',
  `hypes` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Popularita před vydáním podle IGDB (počet sledujících)',
  `steam_app_id` int(10) unsigned DEFAULT NULL COMMENT 'Steam App ID z IGDB — párování s ITAD',
  `itad_id` char(36) DEFAULT NULL COMMENT 'ID hry v IsThereAnyDeal po spárování; NULL = ještě nepárováno',
  `cover_image_id` varchar(64) DEFAULT NULL COMMENT 'Obal — image_id v IGDB',
  `banner_image_id` varchar(64) DEFAULT NULL COMMENT 'Banner (artwork nebo snímek) — image_id v IGDB',
  `genres` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Žánry podle IGDB — seznam názvů' CHECK (json_valid(`genres`)),
  `coop` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Hraní ve víc lidech spolu (kooperace, rozdělená obrazovka)',
  `pc_release_on` date DEFAULT NULL COMMENT 'Nadcházející vydání na PC; NULL = na PC nevychází nebo už vyšla',
  `pc_release_precision` varchar(16) DEFAULT NULL COMMENT 'Přesnost data na PC (enum ReleasePrecision)',
  `pc_early_access` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Na PC jde o start předběžného přístupu',
  `playstation_release_on` date DEFAULT NULL COMMENT 'Nadcházející vydání na PS5; NULL = na PS5 nevychází nebo už vyšla',
  `playstation_release_precision` varchar(16) DEFAULT NULL COMMENT 'Přesnost data na PS5 (enum ReleasePrecision)',
  `playstation_early_access` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Na PS5 jde o start předběžného přístupu',
  `next_release_on` date NOT NULL COMMENT 'Nejbližší z vydání na platformách — řazení podle data',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `upcoming_releases_igdb_id_unique` (`igdb_id`),
  KEY `upcoming_releases_steam_app_id_index` (`steam_app_id`),
  KEY `upcoming_releases_itad_id_index` (`itad_id`),
  KEY `upcoming_releases_next_release_on_index` (`next_release_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_13_130000_create_upcoming_releases_table', 13
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_13_130000_create_upcoming_releases_table');

-- Kontrola: migrace je zapsaná a tabulka existuje
SELECT `migration`, `batch` FROM `migrations` WHERE `batch` = 13;
SHOW TABLES LIKE 'upcoming_releases';
