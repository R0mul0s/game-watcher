-- Game Watcher — výchozí schéma (etapa 1)
--
-- Odpovídá migracím 0001_01_01_000000_create_users_table
-- a 0001_01_01_000001_create_cache_table.
--
-- users — účty (zakládá je správce, R4); cache a cache_locks — databázová
-- cache a zámky (hosting nemá Redis, R2); migrations — evidence migrací Laravelu.
-- Tabulky sessions, password_reset_tokens ani tabulky fronty nejsou (R4, R5, R2).
--
-- Skript je OPAKOVATELNÝ. Pusť ho PŘED prvním nahráním kódu.
--
-- @author Roman Hlaváček
-- @created 2026-10-11

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Zobrazované jméno',
  `email` varchar(255) NOT NULL COMMENT 'Přihlašovací e-mail',
  `password` varchar(255) NOT NULL COMMENT 'Hash hesla (bcrypt)',
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '0001_01_01_000000_create_users_table', 1
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '0001_01_01_000000_create_users_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '0001_01_01_000001_create_cache_table', 1
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '0001_01_01_000001_create_cache_table');

-- Kontrola: migrace jsou zapsané, tabulky existují
SELECT `migration`, `batch` FROM `migrations` ORDER BY `id`;
SHOW TABLES;
