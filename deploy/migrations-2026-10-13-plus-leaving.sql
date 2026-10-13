-- Game Watcher — odchod hry z katalogu PS Plus (R49)
--
-- Odpovídá migraci 2026_10_13_150000_add_member_leaves_at_to_current_prices.
--
-- current_prices dostane member_leaves_at — kdy hra odchází z katalogu PS Plus
-- Extra / Premium. Prázdný, doplní ho nejbližší obnova cen.
--
-- Skript je OPAKOVATELNÝ. Pusť ho PO migrations-2026-10-13-czech.sql a PŘED
-- nahráním kódu.
--
-- @author Roman Hlaváček
-- @created 2026-10-13

SET NAMES utf8mb4;

ALTER TABLE `current_prices`
  ADD COLUMN IF NOT EXISTS `member_leaves_at` datetime DEFAULT NULL COMMENT 'Kdy hra odchází z katalogu PS Plus (UTC); NULL = zůstává nebo v něm není' AFTER `member_free`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_13_150000_add_member_leaves_at_to_current_prices', 15
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_13_150000_add_member_leaves_at_to_current_prices');

-- Kontrola: migrace je zapsaná a sloupec existuje
SELECT `migration`, `batch` FROM `migrations` WHERE `batch` = 15;
SHOW COLUMNS FROM `current_prices` WHERE `Field` = 'member_leaves_at';
