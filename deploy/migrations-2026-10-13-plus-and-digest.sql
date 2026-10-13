-- Game Watcher — úroveň katalogu PS Plus a měsíční hry (R35), týdenní souhrn (R36)
--
-- Odpovídá migraci 2026_10_13_100000_add_plus_tiers_and_weekly_digest.
--
-- current_prices dostane úroveň katalogu PS Plus (Extra / Premium) a příznak
-- měsíční hry PS Plus Essential — doplní je další stažení cen PS Store.
-- users dostane volbu týdenního souhrnu (zapnutá i pro dosavadní účty)
-- a čas posledního souhrnu.
--
-- Skript je OPAKOVATELNÝ. Pusť ho PO migrations-2026-10-12-ratings.sql
-- a PŘED nahráním kódu.
--
-- @author Roman Hlaváček
-- @created 2026-10-13

SET NAMES utf8mb4;

ALTER TABLE `current_prices`
  ADD COLUMN IF NOT EXISTS `member_tier` varchar(16) DEFAULT NULL COMMENT 'Úroveň PS Plus katalogu (enum PlusTier: extra, premium); NULL = neuvedeno' AFTER `member_included`,
  ADD COLUMN IF NOT EXISTS `member_free` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Měsíční hra PS Plus Essential — s předplatným zdarma' AFTER `member_tier`;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `notify_weekly` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Posílat týdenní souhrn slev e-mailem' AFTER `notify_email`,
  ADD COLUMN IF NOT EXISTS `weekly_digest_at` timestamp NULL DEFAULT NULL COMMENT 'Kdy odešel poslední týdenní souhrn (UTC)' AFTER `notify_weekly`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_13_100000_add_plus_tiers_and_weekly_digest', 10
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_13_100000_add_plus_tiers_and_weekly_digest');

-- Kontrola: migrace je zapsaná a sloupce existují
SELECT `migration`, `batch` FROM `migrations` WHERE `batch` = 10;
SHOW COLUMNS FROM `current_prices` WHERE `Field` IN ('member_tier', 'member_free');
SHOW COLUMNS FROM `users` WHERE `Field` IN ('notify_weekly', 'weekly_digest_at');
