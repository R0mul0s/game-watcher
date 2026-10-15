-- Game Watcher — centrum upozornění a ranní souhrn e-mailem (R53)
--
-- Odpovídá migraci 2026_10_15_100000_create_notification_center.
--
-- Nová tabulka notifications (záznamy centra, prázdná — plní ji cron);
-- users: email_frequency místo notify_email (vypnutý e-mail zůstane vypnutý,
-- zapnutý chodí nově jednou denně ráno) a alert_email_sent_at.
--
-- Skript je OPAKOVATELNÝ. Pusť ho PO migrations-2026-10-13-game-pass-and-bundles.sql
-- a PŘED nahráním kódu.
--
-- @author Roman Hlaváček
-- @created 2026-10-15

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` uuid NOT NULL,
  `type` varchar(255) NOT NULL COMMENT 'Druh záznamu (NotificationKind)',
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL COMMENT 'Obsah záznamu (JSON)',
  `read_at` timestamp NULL DEFAULT NULL COMMENT 'Kdy uživatel záznam otevřel; NULL = nepřečtený',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Centrum upozornění (R53) — záznamy starší než games.notifications.retention_days maže cron';

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `email_frequency` varchar(10) NOT NULL DEFAULT 'daily' COMMENT 'E-mail s upozorněními: daily = souhrn ráno, instant = po každém běhu cronu, off = neposílat' AFTER `display_currency`,
  ADD COLUMN IF NOT EXISTS `alert_email_sent_at` timestamp NULL DEFAULT NULL COMMENT 'Do kdy jsou záznamy centra poslané e-mailem (UTC); NULL = zatím nic' AFTER `email_frequency`;

-- Vypnutý e-mail převzít do frekvence — jen dokud sloupec notify_email existuje
-- (při opakovaném spuštění už je smazaný, příkaz se pak nahradí prázdným)
SET @copy_off := IF(
  EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'notify_email'),
  'UPDATE `users` SET `email_frequency` = ''off'' WHERE `notify_email` = 0',
  'DO 0'
);
PREPARE copy_off FROM @copy_off;
EXECUTE copy_off;
DEALLOCATE PREPARE copy_off;

ALTER TABLE `users` DROP COLUMN IF EXISTS `notify_email`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_15_100000_create_notification_center', 17
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_15_100000_create_notification_center');

-- Kontrola: migrace je zapsaná, tabulka a sloupce existují, notify_email ne
SELECT `migration`, `batch` FROM `migrations` WHERE `batch` = 17;
SHOW TABLES LIKE 'notifications';
SHOW COLUMNS FROM `users` WHERE `Field` IN ('email_frequency', 'alert_email_sent_at', 'notify_email');
SELECT `email_frequency`, COUNT(*) FROM `users` GROUP BY `email_frequency`;
