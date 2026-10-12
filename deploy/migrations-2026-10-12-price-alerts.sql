-- Game Watcher — hlídání cen: cílová cena, nastavení upozornění, odběry Web Push (etapa 6, R26)
--
-- Odpovídá migraci 2026_10_12_100400_create_price_alert_tables.
--
-- watchlist_items dostane cílovou cenu a stav posledního upozornění (prázdné),
-- users volbu e-mailu (zapnutý) a PS Plus (vypnuté), nová tabulka
-- push_subscriptions drží zařízení pro upozornění na telefon.
--
-- Skript je OPAKOVATELNÝ. Pusť ho PO migrations-2026-10-12-game-details.sql
-- a PŘED nahráním kódu.
--
-- @author Roman Hlaváček
-- @created 2026-10-12

SET NAMES utf8mb4;

ALTER TABLE `watchlist_items`
  ADD COLUMN IF NOT EXISTS `target_amount` decimal(10,2) DEFAULT NULL COMMENT 'Cílová cena [target_currency]; NULL = upozornit na historické minimum' AFTER `platform`,
  ADD COLUMN IF NOT EXISTS `target_currency` char(3) DEFAULT NULL COMMENT 'Měna cílové ceny (měna zobrazení účtu při zadání)' AFTER `target_amount`,
  ADD COLUMN IF NOT EXISTS `notified_amount` decimal(10,2) DEFAULT NULL COMMENT 'Cena z posledního upozornění [notified_currency]; NULL = podmínka neplatí' AFTER `target_currency`,
  ADD COLUMN IF NOT EXISTS `notified_currency` char(3) DEFAULT NULL COMMENT 'Měna ceny z posledního upozornění' AFTER `notified_amount`,
  ADD COLUMN IF NOT EXISTS `notified_at` timestamp NULL DEFAULT NULL COMMENT 'Kdy odešlo poslední upozornění' AFTER `notified_currency`;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `notify_email` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Posílat upozornění na cenu e-mailem' AFTER `display_currency`,
  ADD COLUMN IF NOT EXISTS `has_ps_plus` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Má PS Plus — upozornění počítají cenu pro předplatitele' AFTER `notify_email`;

CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `endpoint` text NOT NULL COMMENT 'Adresa odběru u push služby prohlížeče',
  `endpoint_hash` char(64) NOT NULL COMMENT 'SHA-256 adresy odběru',
  `public_key` varchar(255) NOT NULL COMMENT 'Veřejný klíč prohlížeče pro šifrování (p256dh)',
  `auth_token` varchar(255) NOT NULL COMMENT 'Tajemství prohlížeče pro šifrování (auth)',
  `content_encoding` varchar(16) NOT NULL COMMENT 'Kódování obsahu zprávy (aes128gcm)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `push_subscriptions_endpoint_hash_unique` (`endpoint_hash`),
  KEY `push_subscriptions_user_id_foreign` (`user_id`),
  CONSTRAINT `push_subscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_12_100400_create_price_alert_tables', 7
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_12_100400_create_price_alert_tables');

-- Kontrola: migrace je zapsaná, sloupce a tabulka existují
SELECT `migration`, `batch` FROM `migrations` WHERE `batch` = 7;
SHOW COLUMNS FROM `watchlist_items` WHERE `Field` IN ('target_amount', 'target_currency', 'notified_amount', 'notified_currency', 'notified_at');
SHOW COLUMNS FROM `users` WHERE `Field` IN ('notify_email', 'has_ps_plus');
SHOW TABLES LIKE 'push_subscriptions';
