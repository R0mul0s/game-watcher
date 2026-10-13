-- Game Watcher — délka hraní z HowLongToBeat (R44)
--
-- Odpovídá migraci 2026_10_13_120000_add_playtime_to_games_table.
--
-- games dostane hru na HowLongToBeat a délky hraní v minutách (hlavní příběh,
-- s vedlejšími úkoly, na 100 %) — doplní je cron po malých dávkách.
--
-- Skript je OPAKOVATELNÝ. Pusť ho PO migrations-2026-10-13-release-alerts.sql
-- a PŘED nahráním kódu.
--
-- @author Roman Hlaváček
-- @created 2026-10-13

SET NAMES utf8mb4;

ALTER TABLE `games`
  ADD COLUMN IF NOT EXISTS `hltb_id` int(10) unsigned DEFAULT NULL COMMENT 'Hra na HowLongToBeat (howlongtobeat.com/game/<id>); NULL = nenalezena' AFTER `metacritic_checked_at`,
  ADD COLUMN IF NOT EXISTS `hltb_main_minutes` int(10) unsigned DEFAULT NULL COMMENT 'Hlavní příběh podle HLTB [min]' AFTER `hltb_id`,
  ADD COLUMN IF NOT EXISTS `hltb_extra_minutes` int(10) unsigned DEFAULT NULL COMMENT 'Příběh s vedlejšími úkoly podle HLTB [min]' AFTER `hltb_main_minutes`,
  ADD COLUMN IF NOT EXISTS `hltb_complete_minutes` int(10) unsigned DEFAULT NULL COMMENT 'Na 100 % podle HLTB [min]' AFTER `hltb_extra_minutes`,
  ADD COLUMN IF NOT EXISTS `hltb_checked_at` timestamp NULL DEFAULT NULL COMMENT 'Kdy se HowLongToBeat naposledy zkoušel (UTC); NULL = ještě ne' AFTER `hltb_complete_minutes`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_13_120000_add_playtime_to_games_table', 12
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_13_120000_add_playtime_to_games_table');

-- Kontrola: migrace je zapsaná a sloupce existují
SELECT `migration`, `batch` FROM `migrations` WHERE `batch` = 12;
SHOW COLUMNS FROM `games` WHERE `Field` LIKE 'hltb\_%';
