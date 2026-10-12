-- Game Watcher — stav zdrojů: kdy se který krok cronu naposledy povedl (R29)
--
-- Odpovídá migraci 2026_10_12_100500_create_source_statuses_table.
--
-- Nová tabulka source_statuses — jeden řádek na zdroj (cron, kurzy ČNB, ITAD,
-- gg.deals, PlayStation Store, Steam, upozornění), prázdná; naplní ji první
-- běh cronu. Do té doby /health/zdroje hlásí výpadek (503).
--
-- Skript je OPAKOVATELNÝ. Pusť ho PO migrations-2026-10-12-price-alerts.sql
-- a PŘED nahráním kódu.
--
-- @author Roman Hlaváček
-- @created 2026-10-12

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `source_statuses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source` varchar(32) NOT NULL COMMENT 'Zdroj nebo krok cronu (enum MonitoredSource)',
  `last_run_at` timestamp NULL DEFAULT NULL COMMENT 'Poslední pokus (UTC)',
  `last_success_at` timestamp NULL DEFAULT NULL COMMENT 'Poslední úspěšný pokus (UTC)',
  `last_failure_at` timestamp NULL DEFAULT NULL COMMENT 'Poslední neúspěšný pokus (UTC)',
  `last_error` varchar(255) DEFAULT NULL COMMENT 'Zpráva poslední chyby (bez tajemství)',
  `failures_in_row` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Neúspěšné pokusy od posledního úspěchu',
  `processed` int(10) unsigned DEFAULT NULL COMMENT 'Počet zpracovaných položek při posledním úspěchu [her / produktů / upozornění]',
  `expected` int(10) unsigned DEFAULT NULL COMMENT 'Kolik položek měl zdroj zpracovat; NULL = neurčeno',
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_statuses_source_unique` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_12_100500_create_source_statuses_table', 8
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_12_100500_create_source_statuses_table');

-- Kontrola: migrace je zapsaná a tabulka existuje
SELECT `migration`, `batch` FROM `migrations` WHERE `batch` = 8;
SHOW TABLES LIKE 'source_statuses';
